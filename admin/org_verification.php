<?php require_once __DIR__.'/../includes/auth.php'; require_role('ADMIN');
if($_SERVER['REQUEST_METHOD']==='POST' && in_array(post('do'),['loc_approve','loc_reject'],true)){
  $oid=(int)post('organization_id'); $reason=post('reason'); $approve=post('do')==='loc_approve';
  $q=$pdo->prepare("SELECT * FROM healthcare_organizations WHERE organization_id=?"); $q->execute([$oid]); $o=$q->fetch();
  if(!$o || $o['verification_status']!=='VERIFIED' || !$o['pending_city']) flash('No pending location change for this organization.','err');
  elseif(!$approve && $reason==='') flash('Enter a reason for rejecting the location change.','err');
  elseif(mb_strlen($reason)>255) flash('Reason must be 255 characters or fewer.','err');
  else{
    $newLoc="{$o['pending_city']}, {$o['pending_address']}"; $oldLoc="{$o['city']}, {$o['address']}";
    if($approve){ [$lat,$lng]=city_coords($o['pending_city']);
      $pdo->prepare("UPDATE healthcare_organizations SET city=pending_city,address=pending_address,latitude=?,longitude=?,pending_city=NULL,pending_address=NULL,location_change_at=NULL WHERE organization_id=? AND pending_city IS NOT NULL")->execute([$lat,$lng,$oid]);
    } else $pdo->prepare("UPDATE healthcare_organizations SET pending_city=NULL,pending_address=NULL,location_change_at=NULL WHERE organization_id=?")->execute([$oid]);
    audit($pdo,$_SESSION['uid'],$approve?'APPROVE_LOCATION':'REJECT_LOCATION','healthcare_organizations',$oid,"Organization '{$o['organization_name']}' location change ".($approve?'approved':'rejected').": '$oldLoc' -> '$newLoc'.".($approve?'':" Reason: $reason"));
    notify($pdo,$o['user_id'],'VERIFICATION',$approve?'Location change approved':'Location change rejected',$approve?"Your organization's location is now $newLoc.":"Your location change request to $newLoc was not approved. Reason: $reason");
    flash('Location change '.($approve?'approved':'rejected').'.');
  }
  redirect('admin/org_verification.php');
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  $st=post('do')==='verify'?'VERIFIED':(post('do')==='reject'?'REJECTED':null);
  if($st){
    $oid=(int)post('organization_id'); $reason=post('reason');
    $q=$pdo->prepare("SELECT organization_id,user_id,organization_name,verification_status,certificate_file FROM healthcare_organizations WHERE organization_id=?"); $q->execute([$oid]); $o=$q->fetch();
    if(!$o) flash('Organization not found.','err');
    elseif($o['verification_status']!=='PENDING') flash('This organization has already been processed ('.$o['verification_status'].').','err');
    elseif($st==='VERIFIED' && !org_cert_valid_name($o['certificate_file'])) flash('Cannot verify: no registration certificate on file. Reject it with a reason so the organization can resubmit.','err');
    elseif($st==='REJECTED' && $reason==='') flash('Enter a reason for rejection.','err');
    elseif(mb_strlen($reason)>255) flash('Reason must be 255 characters or fewer.','err');
    else{
      $u=$pdo->prepare("UPDATE healthcare_organizations SET verification_status=?,rejection_reason=?,reviewed_by=?,reviewed_at=CURRENT_TIMESTAMP WHERE organization_id=? AND verification_status='PENDING'");
      $u->execute([$st,$st==='REJECTED'?$reason:null,$_SESSION['uid'],$oid]);
      if($u->rowCount()){
        audit($pdo,$_SESSION['uid'],$st,'healthcare_organizations',$oid,"Organization '{$o['organization_name']}' (ID $oid): PENDING -> $st.".($st==='REJECTED'?" Reason: $reason":''));
        notify($pdo,$o['user_id'],'VERIFICATION',$st==='VERIFIED'?'Organization verified':'Organization registration rejected',
          $st==='VERIFIED'?'Your organization has been verified. You can now raise requests, manage inventory and run campaigns. First step: open Inventory and enter your opening blood stock and a minimum stock level for each blood group.':"Your organization registration was not approved. Reason: $reason. You can upload a corrected certificate from your Profile and resubmit.");
        flash("Organization {$o['organization_name']} $st.");
      } else flash('This organization has already been processed.','err');
    }
  }
  redirect('admin/org_verification.php');
}
$rows=$pdo->query("SELECT o.*,u.email,u.full_name contact FROM healthcare_organizations o JOIN users u USING(user_id) WHERE verification_status='PENDING' ORDER BY o.created_at")->fetchAll();
$title='Organization Verification'; require '../includes/header.php'; ?>
<p class="muted">Check the registration certificate against the details below (name, registration number, type) before verifying. Rejection needs a reason.</p>
<div class="tw"><table><thead><tr><th>Organization</th><th>Type</th><th>Reg. no.</th><th>Contact person</th><th>Address</th><th>Email</th><th>Phone</th><th>Submitted</th><th>Certificate</th><th>Decision</th></tr></thead><tbody>
<?php foreach($rows as $o): $hasCert=org_cert_valid_name($o['certificate_file']); ?><tr><td><?= h($o['organization_name']) ?></td><td><?= h($o['organization_type']) ?></td><td><?= h($o['registration_number']) ?></td><td><?= h($o['contact']) ?></td><td><?= h(trim($o['address'].', '.$o['city'],', ')) ?></td><td><?= h($o['email']) ?></td><td><?= h($o['phone']) ?></td><td><?= h($o['created_at']) ?></td>
<td><?= $hasCert ? '<a class="btn sm alt" target="_blank" rel="noopener" href="org_document.php?id='.(int)$o['organization_id'].'">View</a>' : badge('MISSING') ?></td>
<td><form method="post"><?= csrf_field() ?><input type="hidden" name="organization_id" value="<?= $o['organization_id'] ?>"><input name="reason" maxlength="255" placeholder="Reason (required to reject)"><br><button name="do" value="verify" class="btn sm" data-confirm="Verify this organization?">Verify</button> <button name="do" value="reject" class="btn sm alt" data-confirm="Reject this organization?">Reject</button></form></td></tr><?php endforeach; ?></tbody></table></div>
<?php if(!$rows) echo '<div class="empty">No organizations waiting.</div>'; ?>
<?php $loc=$pdo->query("SELECT o.* FROM healthcare_organizations o WHERE o.verification_status='VERIFIED' AND o.pending_city IS NOT NULL ORDER BY o.location_change_at")->fetchAll(); ?>
<h2>Location change requests</h2><p class="muted">A verified organization's location only changes after you approve it, because donor matching uses it.</p>
<div class="tw"><table><thead><tr><th>Organization</th><th>Current location</th><th>Requested location</th><th>Requested</th><th>Decision</th></tr></thead><tbody>
<?php foreach($loc as $o): ?><tr><td><?= h($o['organization_name']) ?></td><td><?= h($o['city']) ?>, <?= h($o['address']) ?></td><td><b><?= h($o['pending_city']) ?>, <?= h($o['pending_address']) ?></b></td><td><?= h($o['location_change_at']) ?></td>
<td><form method="post"><?= csrf_field() ?><input type="hidden" name="organization_id" value="<?= (int)$o['organization_id'] ?>"><input name="reason" maxlength="255" placeholder="Reason (required to reject)"><br><button name="do" value="loc_approve" class="btn sm" data-confirm="Approve this location change?">Approve</button> <button name="do" value="loc_reject" class="btn sm alt" data-confirm="Reject this location change?">Reject</button></form></td></tr><?php endforeach; ?></tbody></table></div>
<?php if(!$loc) echo '<div class="empty">No location change requests.</div>'; require '../includes/footer.php';
