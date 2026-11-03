<?php require_once __DIR__.'/../includes/auth.php'; require_role('DONOR');
$d=my_donor($pdo); $did=(int)$d['donor_id'];
if($_SERVER['REQUEST_METHOD']==='POST'){
 $kind=post('request_type'); $note=substr(post('request_note'),0,1000);
 if(!in_array($kind,['RESCHEDULE','LOCATION'],true)){ flash('Choose a valid request type.','err'); redirect('donor/appointments.php'); }
 $s=$pdo->prepare("SELECT checkup_id,scheduled_date,organization_id FROM donor_checkups WHERE donor_id=? AND outcome='PENDING' ORDER BY scheduled_date DESC LIMIT 1"); $s->execute([$did]); $c=$s->fetch();
 if(!$c){ flash('There is no pending checkup to change. Please contact the administrator if you need help.','err'); redirect('donor/appointments.php'); }
 $admins=$pdo->query("SELECT user_id FROM users WHERE role='ADMIN' AND status='ACTIVE'")->fetchAll(PDO::FETCH_COLUMN);
 $label=$kind==='RESCHEDULE'?'reschedule':'different location'; foreach($admins as $uid) notify($pdo,(int)$uid,'CHECKUP_CHANGE_REQUEST','Donor requested '.$label,"{$d['full_name']} requested a {$label} for checkup #{$c['checkup_id']} scheduled on {$c['scheduled_date']}. Note: ".($note?:'No note provided.'));
 audit($pdo,$_SESSION['uid'],'REQUEST','donor_checkups',$c['checkup_id'],'Donor requested '.$label.'.'); flash('Your request was sent to the administrator. The assigned checkup remains unchanged until the administrator updates it.'); redirect('donor/appointments.php');
}
$s=$pdo->prepare("SELECT c.*,o.organization_name,o.address,o.city,o.phone FROM donor_checkups c JOIN healthcare_organizations o USING(organization_id) WHERE c.donor_id=? ORDER BY (c.outcome='PENDING') DESC,c.scheduled_date DESC,c.checkup_id DESC"); $s->execute([$did]); $rows=$s->fetchAll();
$title='My Checkup'; $page_intro='Your administrator-assigned donor health checkup.'; require '../includes/header.php';
?>
<?php $current=null; foreach($rows as $r) if($r['outcome']==='PENDING'){ $current=$r; break; } ?>
<div class="card"><h2>Assigned checkup</h2><?php if($current): ?>
<p><b>Organization:</b> <?= h($current['organization_name']) ?><br><b>Address:</b> <?= h($current['address']?:$current['city']) ?><br><b>City:</b> <?= h($current['city']) ?><br><b>Phone:</b> <?= h($current['phone']?:'Contact administrator for details') ?><br><b>Date:</b> <?= h($current['scheduled_date']) ?><br><b>Status:</b> <?= badge($current['outcome']) ?></p>
<p class="muted">Only the administrator can change your assigned checkup. The healthcare organization records your outcome after your visit.</p>
<form method="post"><?= csrf_field() ?><label>Request</label><select name="request_type" required><option value="RESCHEDULE">Request reschedule</option><option value="LOCATION">Request different location</option></select><label>Reason (optional)</label><textarea name="request_note" maxlength="1000" rows="3"></textarea><button class="btn">Send request to administrator</button></form>
<?php else: ?><p>No pending checkup is currently assigned to you. Your checkup will appear here after the administrator schedules it.</p><?php endif; ?></div>
<h2>Checkup history</h2><div class="tw"><table><thead><tr><th>Organization</th><th>Scheduled</th><th>Actual date</th><th>Outcome</th><th>Notes</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= h($r['organization_name']) ?><br><?= h($r['address']?:$r['city']) ?><br><?= h($r['phone']?:'') ?></td><td><?= h($r['scheduled_date']) ?></td><td><?= h($r['actual_date']?:'—') ?></td><td><?= badge($r['outcome']) ?></td><td><?= h($r['notes']?:'—') ?></td></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="5">No checkup history yet.</td></tr><?php endif; ?></tbody></table></div><?php require '../includes/footer.php'; ?>