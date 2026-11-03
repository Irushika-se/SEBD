<?php require_once __DIR__.'/../includes/auth.php'; require_role('HEALTHCARE');
$o=my_org($pdo); require_verified_org($o); $cid=(int)get('id');
$c=$pdo->prepare('SELECT * FROM donation_campaigns WHERE campaign_id=? AND organization_id=?'); $c->execute([$cid,$o['organization_id']]); $campaign=$c->fetch();
if(!$campaign){flash('Campaign not found.','err');redirect('healthcare/campaigns.php');}
if($_SERVER['REQUEST_METHOD']==='POST'){
  $did=(int)post('donor_id'); $act=post('action');
  if(!in_array($act,['ATTENDED','CANCELLED'],true)) flash('Invalid action.','err');
  else{
    $s=$pdo->prepare("UPDATE donor_campaign_registrations SET status=? WHERE campaign_id=? AND donor_id=? AND status='REGISTERED'"); $s->execute([$act,$cid,$did]);
    if($s->rowCount()) {audit($pdo,$_SESSION['uid'],'UPDATE','donor_campaign_registrations',$cid,"Campaign registration marked $act.");flash('Registration updated.');} else flash('Active registration not found.','err');
  }
  redirect('healthcare/campaign_attendees.php?id='.$cid);
}
$s=$pdo->prepare("SELECT r.*,u.full_name,u.email,u.phone,d.blood_group,d.city FROM donor_campaign_registrations r JOIN donors d USING(donor_id) JOIN users u ON u.user_id=d.user_id WHERE r.campaign_id=? ORDER BY FIELD(r.status,'REGISTERED','ATTENDED','CANCELLED'),r.registered_at DESC"); $s->execute([$cid]); $rows=$s->fetchAll();
$title='Campaign Registrations'; $page_intro='Donor registration list for '.($campaign['campaign_name']??'campaign').'. Handle donor contact details responsibly.'; require '../includes/header.php'; ?>
<div class="card"><h2><?= h($campaign['campaign_name']) ?></h2><p><?= h($campaign['campaign_date']) ?> · <?= h($campaign['location']) ?></p><a class="btn sm alt" href="campaigns.php">Back to campaigns</a></div>
<div class="tw"><table><thead><tr><th>Donor</th><th>Blood group</th><th>City</th><th>Contact</th><th>Registered</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?= h($r['full_name']) ?></td><td><?= h($r['blood_group']) ?></td><td><?= h($r['city']) ?></td><td><?= h($r['email']) ?><br><?= h($r['phone']) ?></td><td><?= h($r['registered_at']) ?></td><td><?= badge($r['status']) ?></td><td><?php if($r['status']==='REGISTERED'): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="donor_id" value="<?= (int)$r['donor_id'] ?>"><button name="action" value="ATTENDED" class="btn sm">Mark attended</button><button name="action" value="CANCELLED" class="btn sm alt" data-confirm="Cancel this registration?">Cancel</button></form><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="7">No donor registrations yet.</td></tr><?php endif; ?></tbody></table></div>
<?php require '../includes/footer.php'; ?>
