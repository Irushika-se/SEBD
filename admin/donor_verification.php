<?php require_once __DIR__.'/../includes/auth.php'; require_role('ADMIN');
if($_SERVER['REQUEST_METHOD']==='POST'){
  $do=post('do'); $did=(int)post('donor_id');
  $s=$pdo->prepare("SELECT d.*,u.full_name FROM donors d JOIN users u USING(user_id) WHERE donor_id=?"); $s->execute([$did]); $d=$s->fetch();
  $okDate=fn($x)=>(bool)(($o=DateTime::createFromFormat('Y-m-d',$x))&&$o->format('Y-m-d')===$x&&$x>=date('Y-m-d'));
  if($do==='schedule' && $d){
    $org=(int)post('organization_id'); $dt=post('scheduled_date');
    if(!$okDate($dt)||!$org){ flash('Choose an organization and a valid date (today or later).','err'); redirect('admin/donor_verification.php'); }
    if($d['verification_status']!=='PENDING'){ flash('A checkup can only be scheduled for a donor who is waiting for verification (re-open a rejected donor first).','err'); redirect('admin/donor_verification.php'); }
    $dup=$pdo->prepare("SELECT COUNT(*) FROM donor_checkups WHERE donor_id=? AND outcome='PENDING'"); $dup->execute([$did]); if((int)$dup->fetchColumn()>0){ flash('This donor already has a pending checkup.','err'); redirect('admin/donor_verification.php'); }
    $os=$pdo->prepare("SELECT o.organization_name,o.address,o.city,o.phone FROM healthcare_organizations o JOIN users u USING(user_id) WHERE o.organization_id=? AND o.verification_status='VERIFIED' AND u.status='ACTIVE'"); $os->execute([$org]); $orginfo=$os->fetch(); if(!$orginfo){ flash('Choose a verified, active organization (suspended organizations cannot take checkups).','err'); redirect('admin/donor_verification.php'); }
    $pdo->prepare("INSERT INTO donor_checkups(donor_id,organization_id,scheduled_by,scheduled_date) VALUES(?,?,?,?)")->execute([$did,$org,$_SESSION['uid'],$dt]);
    notify($pdo,$d['user_id'],'CHECKUP','Body checkup scheduled',"Your checkup is scheduled for $dt at {$orginfo['organization_name']}, {$orginfo['address']}, {$orginfo['city']}. Phone: {$orginfo['phone']}. View My Checkup for details.");
    audit($pdo,$_SESSION['uid'],'CREATE','donor_checkups',$pdo->lastInsertId(),"Checkup scheduled for donor #$did."); flash('Checkup scheduled.');
  } elseif($do==='reschedule' && $d){
    $org=(int)post('organization_id'); $dt=post('scheduled_date');
    if(!$okDate($dt)||!$org){ flash('Choose an organization and a valid date (today or later).','err'); redirect('admin/donor_verification.php'); }
    $os=$pdo->prepare("SELECT o.organization_name,o.address,o.city,o.phone,o.user_id FROM healthcare_organizations o JOIN users u USING(user_id) WHERE o.organization_id=? AND o.verification_status='VERIFIED' AND u.status='ACTIVE'"); $os->execute([$org]); $orginfo=$os->fetch();
    if(!$orginfo){ flash('Choose a verified, active organization (suspended organizations cannot take checkups).','err'); redirect('admin/donor_verification.php'); }
    $cs=$pdo->prepare("SELECT c.*,o.user_id old_org_user,o.organization_name old_org FROM donor_checkups c JOIN healthcare_organizations o USING(organization_id) WHERE c.donor_id=? AND c.outcome='PENDING' ORDER BY c.checkup_id DESC LIMIT 1"); $cs->execute([$did]); $pc=$cs->fetch();
    if(!$pc){ flash('This donor has no pending checkup to change. Use Schedule checkup instead.','err'); redirect('admin/donor_verification.php'); }
    if((int)$pc['organization_id']===$org && $pc['scheduled_date']===$dt){ flash('The checkup already has this organization and date.','err'); redirect('admin/donor_verification.php'); }
    $pdo->prepare("UPDATE donor_checkups SET organization_id=?,scheduled_date=?,scheduled_by=? WHERE checkup_id=? AND outcome='PENDING'")->execute([$org,$dt,$_SESSION['uid'],$pc['checkup_id']]);
    notify($pdo,$d['user_id'],'CHECKUP','Body checkup rescheduled',"Your checkup is now scheduled for $dt at {$orginfo['organization_name']}, {$orginfo['address']}, {$orginfo['city']}. Phone: {$orginfo['phone']}. It replaces your earlier appointment ({$pc['scheduled_date']} at {$pc['old_org']}).");
    if((int)$pc['organization_id']!==$org && $pc['old_org_user']) notify($pdo,(int)$pc['old_org_user'],'CHECKUP','Checkup moved to another organization',"The checkup of donor #$did (was {$pc['scheduled_date']}) was moved to another organization by the administrator. You no longer need to record it.");
    if($orginfo['user_id']) notify($pdo,(int)$orginfo['user_id'],'CHECKUP','New checkup assigned',"A donor checkup (donor #$did) is scheduled at your organization on $dt.");
    audit($pdo,$_SESSION['uid'],'UPDATE','donor_checkups',$pc['checkup_id'],"Checkup rescheduled: {$pc['scheduled_date']} @ {$pc['old_org']} -> $dt @ {$orginfo['organization_name']}."); flash('Checkup rescheduled. The donor and the organization were notified.');
  } elseif($do==='verify' && $d){
    if($d['verification_status']!=='PENDING'){ flash('Only a donor waiting for verification can be verified (a rejected donor must be re-opened first).','err'); }
    elseif($d['health_status']!=='CLEARED'){ flash('Donor must be CLEARED at the checkup first.','err'); }
    else {
      $proof=$pdo->prepare("SELECT COUNT(*) FROM donor_checkups WHERE checkup_id=(SELECT MAX(checkup_id) FROM donor_checkups WHERE donor_id=?) AND outcome='CLEARED' AND actual_date IS NOT NULL AND recorded_by IS NOT NULL"); $proof->execute([$did]);
      if(!(int)$proof->fetchColumn()){ flash('The donor\'s LATEST checkup must be CLEARED, with an actual date and an organization-recorded outcome.','err'); }
      else { $pdo->prepare("UPDATE donors SET verification_status='VERIFIED',rejection_reason=NULL,re_review_requested=0 WHERE donor_id=?")->execute([$did]); notify($pdo,$d['user_id'],'VERIFICATION','Account verified','You are now a verified donor. You can set your availability.'); audit($pdo,$_SESSION['uid'],'VERIFY','donors',$did,'Donor verified.'); flash('Donor verified.'); }
    }
  } elseif($do==='reopen_checkup' && $d){
    $why=mb_substr(post('reason'),0,200);
    if($why===''){ flash('Give a reason for reopening the checkup (it is written to the audit log).','err'); redirect('admin/donor_verification.php'); }
    $ls=$pdo->prepare("SELECT c.*,o.user_id org_user FROM donor_checkups c JOIN healthcare_organizations o USING(organization_id) WHERE c.checkup_id=(SELECT MAX(checkup_id) FROM donor_checkups WHERE donor_id=?)"); $ls->execute([$did]); $lc=$ls->fetch();
    if(!$lc || $lc['outcome']==='PENDING' || $d['verification_status']!=='PENDING'){ flash('Only the latest recorded checkup of an unverified donor can be reopened.','err'); redirect('admin/donor_verification.php'); }
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE donor_checkups SET outcome='PENDING',actual_date=NULL,recorded_by=NULL WHERE checkup_id=? AND outcome=?")->execute([$lc['checkup_id'],$lc['outcome']]);
    $pdo->prepare("UPDATE donors SET health_status='PENDING',availability_status='UNAVAILABLE' WHERE donor_id=?")->execute([$did]);
    audit($pdo,$_SESSION['uid'],'UPDATE','donor_checkups',$lc['checkup_id'],"Checkup reopened (was {$lc['outcome']}). Reason: $why");
    $pdo->commit();
    if($lc['org_user']) notify($pdo,(int)$lc['org_user'],'CHECKUP','Checkup reopened','The administrator reopened the checkup of donor #'.$did.' ('.$lc['outcome'].' result removed). Please record the correct outcome. Reason: '.$why);
    notify($pdo,$d['user_id'],'CHECKUP','Checkup result under review','Your previous checkup result is being corrected by the administrator. Your health status is pending until it is recorded again.');
    flash('Checkup reopened. The organization must record the outcome again.');
  } elseif($do==='reject' && $d){
    if($d['verification_status']!=='PENDING'){ flash('Only a donor who is waiting for verification can be rejected.','err'); redirect('admin/donor_verification.php'); }
    $reason=mb_substr(post('reason'),0,255)?:'No reason given.'; $pdo->prepare("UPDATE donors SET verification_status='REJECTED',availability_status='UNAVAILABLE',rejection_reason=?,re_review_requested=0 WHERE donor_id=?")->execute([$reason,$did]);
    notify($pdo,$d['user_id'],'VERIFICATION','Verification rejected','Your donor registration was not approved. Reason: '.$reason.' You can request a re-review from Verification.'); audit($pdo,$_SESSION['uid'],'REJECT','donors',$did,'Donor rejected.'); flash('Donor rejected.');
  } elseif($do==='reopen' && $d && $d['verification_status']==='REJECTED'){
    $pdo->prepare("UPDATE donors SET verification_status='PENDING',health_status='PENDING',availability_status='UNAVAILABLE',re_review_requested=0 WHERE donor_id=?")->execute([$did]);
    notify($pdo,$d['user_id'],'VERIFICATION','Registration re-opened','Your registration was re-opened for review. You must attend a new checkup; the administrator will schedule it.'); audit($pdo,$_SESSION['uid'],'UPDATE','donors',$did,'Rejected donor re-opened.'); flash('Registration re-opened.');
  }
  redirect('admin/donor_verification.php');
}
$donors=$pdo->query("SELECT d.*,u.full_name,u.email,u.phone,o.organization_name pref_org,o.city pref_city,(SELECT outcome FROM donor_checkups x WHERE x.donor_id=d.donor_id ORDER BY x.checkup_id DESC LIMIT 1) last_outcome,(SELECT CONCAT(c.outcome,' @ ',o.organization_name,' | scheduled ',c.scheduled_date,' | actual date ',COALESCE(c.actual_date,'not recorded'),' | recorded by ',COALESCE((SELECT ru.full_name FROM users ru WHERE ru.user_id=c.recorded_by),'not recorded')) FROM donor_checkups c JOIN healthcare_organizations o USING(organization_id) WHERE c.donor_id=d.donor_id ORDER BY c.checkup_id DESC LIMIT 1) chk
 FROM donors d JOIN users u USING(user_id) LEFT JOIN healthcare_organizations o ON o.organization_id=d.preferred_organization_id WHERE d.verification_status='PENDING' OR (d.verification_status='REJECTED' AND d.re_review_requested=1) ORDER BY d.created_at")->fetchAll();
$orgs=$pdo->query("SELECT o.organization_id,o.organization_name FROM healthcare_organizations o JOIN users u USING(user_id) WHERE o.verification_status='VERIFIED' AND u.status='ACTIVE' ORDER BY o.organization_name")->fetchAll();
$title='Donor Verification'; require '../includes/header.php'; ?>
<?php foreach($donors as $d): ?><div class="card"><b><?= h($d['full_name']) ?></b> - <?= h($d['blood_group']) ?> - <?= h($d['city']) ?> <?= badge($d['health_status']) ?> <?= badge($d['verification_status']) ?>
<p>Email: <?= h($d['email']) ?> | Phone: <?= h($d['phone']) ?> | DOB: <?= h($d['date_of_birth']) ?> | Weight: <?= h($d['weight_kg']) ?> kg | Last donation: <?= h($d['last_donation_date']?:'none') ?><br>
Recent illness: <?= h($d['recent_illness']?:'none') ?> | Medication: <?= h($d['medication']?:'none') ?><br>Preferred organization: <?= h($d['pref_org']?:'No preference') ?> (<?= h($d['pref_city']?:$d['city']) ?>)<br>Latest checkup: <?= h($d['chk']?:'not scheduled') ?><?php if($d['verification_status']==='REJECTED'): ?><br><b>Rejected for:</b> <?= h($d['rejection_reason']) ?> &mdash; donor asked for a re-review.<?php endif; ?></p>
<?php if($d['verification_status']==='REJECTED'): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="donor_id" value="<?= $d['donor_id'] ?>"><button name="do" value="reopen" class="btn sm" data-confirm="Re-open this registration for review?">Re-open for review</button></form></div><?php continue; endif; ?>
<form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="donor_id" value="<?= $d['donor_id'] ?>"><div class="filter">
<select name="organization_id"><?php foreach($orgs as $o) echo "<option value='{$o['organization_id']}' ".((int)$o['organization_id']===(int)$d['preferred_organization_id']?'selected':'').">".h($o['organization_name'])."</option>"; ?></select><input type="date" name="scheduled_date" min="<?= date('Y-m-d') ?>">
<?php if(($d['last_outcome']??'')==='PENDING'): ?><button name="do" value="reschedule" class="btn sm alt" data-confirm="Change the pending checkup to the organization and date selected here?">Reschedule checkup</button><?php else: ?><button name="do" value="schedule" class="btn sm alt">Schedule checkup</button><?php endif; ?>
<?php $canVerify=$d['health_status']==='CLEARED' && ($d['last_outcome']??'')==='CLEARED'; ?><button name="do" value="verify" class="btn sm" data-confirm="Verify this donor?" <?= $canVerify?'':'disabled title="Available only after the organization records a CLEARED checkup"' ?>>Final verify</button>
<?php if(in_array($d['last_outcome']??'',['CLEARED','NO_SHOW'],true)): ?><button name="do" value="reopen_checkup" class="btn sm alt" data-confirm="Reopen the latest checkup so the organization can correct it? Enter a reason first.">Reopen checkup</button><?php endif; ?>
<input name="reason" maxlength="255" placeholder="Reason (reject / reopen checkup)"><button name="do" value="reject" class="btn sm alt" data-confirm="Reject this donor?">Reject</button></div></form></div><?php endforeach; if(!$donors) echo '<div class="empty">No donors waiting.</div>'; ?>
<?php $overdue=$pdo->query("SELECT c.checkup_id,c.scheduled_date,d.donor_id,u.full_name,o.organization_name,ou.status org_status FROM donor_checkups c JOIN donors d USING(donor_id) JOIN users u ON u.user_id=d.user_id JOIN healthcare_organizations o USING(organization_id) JOIN users ou ON ou.user_id=o.user_id WHERE c.outcome='PENDING' AND (c.scheduled_date<CURDATE() OR ou.status<>'ACTIVE') ORDER BY c.scheduled_date")->fetchAll(); ?>
<h2>Overdue or blocked checkups</h2><p class="muted">Overdue = the date has passed without a recorded outcome. Blocked = the assigned organization is suspended; reschedule the donor to an active organization.</p><div class="tw"><table><thead><tr><th>Donor</th><th>Organization</th><th>Scheduled date</th><th>Status</th></tr></thead><tbody><?php foreach($overdue as $x): ?><tr><td><?= h($x['full_name']) ?></td><td><?= h($x['organization_name']) ?></td><td><?= h($x['scheduled_date']) ?></td><td><?= $x['org_status']!=='ACTIVE'?badge('ORG_SUSPENDED').' ':'' ?><?= $x['scheduled_date']<date('Y-m-d')?badge('OVERDUE'):'' ?></td></tr><?php endforeach; ?><?php if(!$overdue): ?><tr><td colspan="4">No overdue or blocked checkups.</td></tr><?php endif; ?></tbody></table></div>
<?php require '../includes/footer.php';
