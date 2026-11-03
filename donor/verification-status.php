<?php
require_once __DIR__.'/../includes/auth.php'; require_role('DONOR');
$d=my_donor($pdo);
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['rereview'])){
  if($d['verification_status']==='REJECTED' && empty($d['re_review_requested'])){
    $pdo->prepare('UPDATE donors SET re_review_requested=1 WHERE donor_id=?')->execute([$d['donor_id']]);
    foreach($pdo->query("SELECT user_id FROM users WHERE role='ADMIN' AND status='ACTIVE'")->fetchAll(PDO::FETCH_COLUMN) as $au) notify($pdo,(int)$au,'REREVIEW_REQUEST','Donor requested re-review',"{$d['full_name']} asked for their rejected donor registration to be reviewed again.");
    audit($pdo,$_SESSION['uid'],'REQUEST','donors',$d['donor_id'],'Donor requested re-review.'); flash('Your re-review request was sent to the administrator.');
  } else flash('A re-review request is not available.','err');
  redirect('donor/verification-status.php');
}
$s=$pdo->prepare('SELECT c.*,o.organization_name,o.address,o.city,o.phone FROM donor_checkups c JOIN healthcare_organizations o USING(organization_id) WHERE c.donor_id=? ORDER BY c.scheduled_date DESC,c.checkup_id DESC'); $s->execute([$d['donor_id']]); $checkups=$s->fetchAll();
$pend=null; foreach($checkups as $c) if($c['outcome']==='PENDING'){ $pend=$c; break; }
$title='Verification Status'; $page_intro='Track your health checkups and account verification progress.'; require '../includes/header.php'; ?>
<div class="grid"><div class="stat"><b><?= badge($d['verification_status']) ?></b><span>Administrator verification</span></div><div class="stat"><b><?= badge($d['health_status']) ?></b><span>Latest health status</span></div><div class="stat"><b><?= badge($d['availability_status']) ?></b><span>Donor availability</span></div></div>
<div class="card"><h2>What your status means</h2>
<?php if($d['verification_status']==='VERIFIED' && $d['health_status']==='CLEARED'): ?><p>Your donor account is verified and your health status is marked cleared. Keep your profile accurate and update availability when it changes.</p>
<?php elseif($d['verification_status']==='REJECTED'): ?><p>Your verification was rejected.</p><p><b>Reason:</b> <?= h($d['rejection_reason']?:'No reason was recorded.') ?></p>
<?php if(!empty($d['re_review_requested'])): ?><p class="muted">Your re-review request is waiting for the administrator.</p><?php else: ?><form method="post"><?= csrf_field() ?><button class="btn sm" name="rereview" value="1" data-confirm="Ask the administrator to review your registration again?">Request re-review</button></form><?php endif; ?>
<?php else: ?><p>Your account is being reviewed. <b>Next step:</b> <?= $pend?'attend your checkup on '.h($pend['scheduled_date']).' at '.h($pend['organization_name']).'.':'the administrator will schedule your checkup and notify you.' ?> After the organization records the outcome, the administrator completes final verification. Emergency matching is only enabled for verified donors.</p><?php endif; ?>
<a class="btn sm" href="appointments.php">My Checkup</a> <a class="btn sm alt" href="profile.php">Review health details</a></div>
<h2>Health checkup history</h2><div class="tw"><table><thead><tr><th>Organization</th><th>Scheduled date</th><th>Actual date</th><th>Outcome</th><th>Notes</th></tr></thead><tbody>
<?php foreach($checkups as $c): ?><tr><td><?= h($c['organization_name']) ?><br><small><?= h($c['address']?:$c['city']) ?> &middot; <?= h($c['phone']?:'') ?></small></td><td><?= h($c['scheduled_date']) ?></td><td><?= h($c['actual_date']??'Not recorded') ?></td><td><?= badge($c['outcome']) ?></td><td><?= h($c['notes']??'—') ?></td></tr><?php endforeach; ?>
<?php if(!$checkups): ?><tr><td colspan="5">No checkups have been scheduled yet. The administrator will assign one.</td></tr><?php endif; ?></tbody></table></div>
<?php require '../includes/footer.php'; ?>
