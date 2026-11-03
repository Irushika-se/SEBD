<?php require_once __DIR__.'/../includes/auth.php'; require_role('HEALTHCARE');
$o=my_org($pdo);
function notify_admins($pdo,$title,$msg){ foreach($pdo->query("SELECT user_id FROM users WHERE role='ADMIN' AND status='ACTIVE'")->fetchAll(PDO::FETCH_COLUMN) as $aid) notify($pdo,(int)$aid,'VERIFICATION',$title,$msg); }
if($_SERVER['REQUEST_METHOD']==='POST' && post('do')==='resubmit'){
  $err=[]; $c=$o['verification_status']==='REJECTED' ? org_cert_check($_FILES['certificate']??null,$err) : null;
  if($o['verification_status']!=='REJECTED') $err[]='Only rejected registrations can be resubmitted.';
  $new=(!$err && $c) ? org_cert_save($c) : null;
  if($err || !$new){ flash($err ? implode(' ',$err) : 'Could not save the certificate.','err'); redirect('healthcare/profile.php'); }
  $u=$pdo->prepare("UPDATE healthcare_organizations SET verification_status='PENDING',certificate_file=?,rejection_reason=NULL,reviewed_by=NULL,reviewed_at=NULL WHERE organization_id=? AND verification_status='REJECTED'"); $u->execute([$new,$o['organization_id']]);
  if($u->rowCount()){ org_cert_delete($o['certificate_file']); audit($pdo,$_SESSION['uid'],'RESUBMIT','healthcare_organizations',$o['organization_id'],'Registration resubmitted with a new certificate.');
    notify_admins($pdo,'Organization resubmitted',"{$o['organization_name']} resubmitted its registration for verification.");
    flash('Registration resubmitted. An administrator will review it.'); }
  else { org_cert_delete($new); flash('Could not resubmit.','err'); }
  redirect('healthcare/profile.php');
}
$status=$o['verification_status'];
// Identity fields (name, type, city, address) are what the administrator reviews:
//  REJECTED -> editable (fix, then resubmit); PENDING -> locked while under review; VERIFIED -> name/type locked, location changes need admin approval.
$editIdentity=($status==='REJECTED'); $locationViaRequest=($status==='VERIFIED');
if($_SERVER['REQUEST_METHOD']==='POST' && post('do')==='cancel_location' && $locationViaRequest && $o['pending_city']){
  $pdo->prepare("UPDATE healthcare_organizations SET pending_city=NULL,pending_address=NULL,location_change_at=NULL WHERE organization_id=?")->execute([$o['organization_id']]);
  audit($pdo,$_SESSION['uid'],'UPDATE','healthcare_organizations',$o['organization_id'],'Location change request withdrawn.'); flash('Location change request withdrawn.'); redirect('healthcare/profile.php');
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  $err=[]; $phone=post('phone');
  $name=$editIdentity?post('organization_name'):$o['organization_name']; $type=$editIdentity?post('organization_type'):$o['organization_type'];
  $city=($editIdentity||$locationViaRequest)?post('city'):$o['city']; $addr=($editIdentity||$locationViaRequest)?post('address'):$o['address'];
  if(mb_strlen($name)<2||mb_strlen($name)>150) $err[]='Organization name must be 2–150 characters.';
  if(!in_array($type,['Blood Bank','Hospital'],true)) $err[]='Select an allowed organization type.';
  if(!isset(SL_CITIES[$city])) $err[]='Select a city from the provided list.';
  if(mb_strlen($addr)>255) $err[]='Address must be 255 characters or fewer.';
  if(!preg_match('/^[0-9]{10}$/',$phone)) $err[]='Phone number must be exactly 10 digits.';
  else{
    $d=$pdo->prepare("SELECT 1 FROM users WHERE phone=? AND user_id<>?"); $d->execute([$phone,$_SESSION['uid']]); if($d->fetch()) $err[]='Phone number already registered.';
    $d=$pdo->prepare("SELECT 1 FROM healthcare_organizations WHERE phone=? AND organization_id<>?"); $d->execute([$phone,$o['organization_id']]); if($d->fetch()) $err[]='Phone number already registered.';
  }
  if($editIdentity && !$err && (strcasecmp($name,$o['organization_name'])!==0||$city!==$o['city'])){
    $d=$pdo->prepare("SELECT 1 FROM healthcare_organizations WHERE LOWER(organization_name)=LOWER(?) AND city=? AND organization_id<>?"); $d->execute([$name,$city,$o['organization_id']]); if($d->fetch()) $err[]='An organization with this name is already registered in this city.';
  }
  if($err){ flash(implode(' ',$err),'err'); redirect('healthcare/profile.php'); }
  $ch=[]; $note=function($label,$old,$new) use(&$ch){ if((string)$old!==(string)$new) $ch[]="$label: '$old' -> '$new'"; };
  $note('phone',$o['phone'],$phone);
  try{
    $pdo->beginTransaction();
    if($editIdentity){
      [$lat,$lng]=city_coords($city);
      $note('name',$o['organization_name'],$name); $note('type',$o['organization_type'],$type); $note('city',$o['city'],$city); $note('address',$o['address'],$addr);
      $pdo->prepare("UPDATE healthcare_organizations SET organization_name=?,organization_type=?,address=?,city=?,phone=?,latitude=?,longitude=? WHERE organization_id=?")->execute([$name,$type,$addr,$city,$phone,$lat,$lng,$o['organization_id']]);
    } else {
      $pdo->prepare("UPDATE healthcare_organizations SET phone=? WHERE organization_id=?")->execute([$phone,$o['organization_id']]);
    }
    $pdo->prepare("UPDATE users SET phone=? WHERE user_id=?")->execute([$phone,$_SESSION['uid']]);
    $locReq=false;
    if($locationViaRequest && ($city!==$o['city']||$addr!==(string)$o['address'])){
      $pdo->prepare("UPDATE healthcare_organizations SET pending_city=?,pending_address=?,location_change_at=CURRENT_TIMESTAMP WHERE organization_id=?")->execute([$city,$addr,$o['organization_id']]);
      $ch[]="location change requested: '{$o['city']}, {$o['address']}' -> '$city, $addr' (awaiting administrator approval)"; $locReq=true;
    }
    audit($pdo,$_SESSION['uid'],'UPDATE','healthcare_organizations',$o['organization_id'],'Organization profile updated'.($ch?': '.implode('; ',$ch).'.':' (no field changed).'));
    $pdo->commit();
  }catch(PDOException $e){ if($pdo->inTransaction()) $pdo->rollBack(); flash('Could not update the profile.','err'); redirect('healthcare/profile.php'); }
  if($locReq) notify_admins($pdo,'Location change requested',"{$o['organization_name']} asked to change its location from {$o['city']}, {$o['address']} to $city, $addr. Review it under Org Verification.");
  elseif($status==='VERIFIED' && $o['phone']!==$phone) notify_admins($pdo,'Organization contact updated',"{$o['organization_name']} changed its phone from {$o['phone']} to $phone.");
  flash($locReq?'Phone saved. The location change was sent for administrator approval; your current location stays until it is approved.':'Profile updated.'); redirect('healthcare/profile.php');
}
$title='Organization Profile'; require '../includes/header.php'; ?>
<?php if($o['pending_city']): ?><div class="alert">Location change awaiting administrator approval: <b><?= h($o['pending_city']) ?>, <?= h($o['pending_address']) ?></b> (current: <?= h($o['city']) ?>, <?= h($o['address']) ?>).
<form method="post" class="inline"><?= csrf_field() ?><button name="do" value="cancel_location" class="btn sm alt">Withdraw request</button></form></div><?php endif; ?>
<?php if($status==='PENDING'): ?><div class="alert">Your registration is under review. Name, type and location are locked until the administrator decides.</div><?php endif; ?>
<form method="post" class="card"><?= csrf_field() ?><div class="row">
<div><label>Organization name<?= $editIdentity?'':' (locked)' ?></label><input name="organization_name" value="<?= h($o['organization_name']) ?>" maxlength="150" <?= $editIdentity?'required':'disabled' ?>></div>
<div><label>Type<?= $editIdentity?'':' (locked)' ?></label><select name="organization_type" <?= $editIdentity?'':'disabled' ?>><?php foreach(['Blood Bank','Hospital'] as $t) echo "<option ".($o['organization_type']===$t?'selected':'').">$t</option>"; ?></select></div>
<div><label>Registration number</label><input value="<?= h($o['registration_number']) ?>" disabled></div>
<div><label>Phone</label><input name="phone" value="<?= h($o['phone']) ?>" required pattern="[0-9]{10}" maxlength="10" inputmode="numeric" title="Enter exactly 10 digits"></div>
<div><label>City<?= $locationViaRequest?' (change needs admin approval)':($editIdentity?'':' (locked)') ?></label><?php if($editIdentity||$locationViaRequest) echo city_select('city',$o['city']); else echo '<input value="'.h($o['city']).'" disabled>'; ?></div>
<div><label>Address<?= $locationViaRequest?' (change needs admin approval)':($editIdentity?'':' (locked)') ?></label><input name="address" value="<?= h($o['address']) ?>" maxlength="255" <?= ($editIdentity||$locationViaRequest)?'':'disabled' ?>></div>
<div><label>Verification</label><p><?= badge($status) ?></p></div></div><br><button class="btn">Save</button> <a class="btn alt" href="change-password.php">Change password</a></form>
<?php if($status==='REJECTED'): ?><form method="post" enctype="multipart/form-data" class="card"><?= csrf_field() ?><input type="hidden" name="do" value="resubmit"><b>Registration rejected</b><p>Reason: <?= h($o['rejection_reason']) ?></p><label>Upload corrected registration certificate (PDF, JPG or PNG, max 2 MB)</label><input type="file" name="certificate" accept=".pdf,.jpg,.jpeg,.png" required><br><br><button class="btn">Resubmit for verification</button></form><?php endif; ?>
<?php require '../includes/footer.php';
