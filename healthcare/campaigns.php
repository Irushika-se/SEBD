<?php require_once __DIR__.'/../includes/auth.php'; require_role('HEALTHCARE');
$o=my_org($pdo); $oid=$o['organization_id'];
if($_SERVER['REQUEST_METHOD']==='POST'){
  require_verified_org($o);
  $st=post('status'); $from=['ACTIVE'=>['UPCOMING'],'COMPLETED'=>['UPCOMING','ACTIVE'],'CANCELLED'=>['UPCOMING','ACTIVE']]; // a closed or cancelled campaign cannot be re-opened
  if(isset($from[$st])){
    $in=implode(',',array_map(fn($x)=>"'$x'",$from[$st])); $cid=(int)post('campaign_id');
    $pdo->beginTransaction();
    try{
      $q=$pdo->prepare("SELECT campaign_name,campaign_date FROM donation_campaigns WHERE campaign_id=? AND organization_id=?"); $q->execute([$cid,$oid]); $cc=$q->fetch();
      if($cc && $st==='ACTIVE' && $cc['campaign_date']>date('Y-m-d')){ $pdo->rollBack(); flash('A campaign can only be started on its own date.','err'); redirect('healthcare/campaigns.php'); }
      $u=$pdo->prepare("UPDATE donation_campaigns SET status=? WHERE campaign_id=? AND organization_id=? AND status IN ($in)"); $u->execute([$st,$cid,$oid]);
      if($u->rowCount()){
        if($st==='CANCELLED') campaign_notify_donors($pdo,$cid,'Campaign cancelled','"'.$cc['campaign_name'].'" planned for '.$cc['campaign_date'].' has been cancelled by the organizer. Your registration was cancelled.',true);
        elseif($st==='COMPLETED') campaign_notify_donors($pdo,$cid,'Campaign closed','"'.$cc['campaign_name'].'" has been closed and you were not marked as attended, so your registration was closed.',true);
        audit($pdo,$_SESSION['uid'],'UPDATE','donation_campaigns',$cid,"Campaign status $st."); $pdo->commit(); flash('Campaign updated.');
      } else { $pdo->rollBack(); flash('This campaign cannot be changed to '.$st.' (it is already closed or cancelled).','err'); }
    }catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); flash('Could not update the campaign.','err'); }
  } else flash('Invalid status.','err');
  redirect('healthcare/campaigns.php');
}
$f=get('status'); $sql="SELECT c.*,(SELECT COUNT(*) FROM donations d WHERE d.campaign_id=c.campaign_id) dc,(SELECT COUNT(*) FROM donor_campaign_registrations r WHERE r.campaign_id=c.campaign_id AND r.status='REGISTERED') rc FROM donation_campaigns c WHERE organization_id=?"; $p=[$oid];
if($f){$sql.=" AND c.status=?";$p[]=$f;} $s=$pdo->prepare($sql." ORDER BY campaign_date DESC"); $s->execute($p);
$wk=$pdo->prepare("SELECT a.requested_date,a.requested_time,a.status,u.full_name,d.blood_group FROM donor_appointments a JOIN donors d USING(donor_id) JOIN users u ON u.user_id=d.user_id WHERE a.organization_id=? AND a.status IN ('PENDING','CONFIRMED') AND a.requested_date>=CURDATE() ORDER BY a.requested_date,a.requested_time"); $wk->execute([$oid]); $walkins=$wk->fetchAll();
$title='Donation Campaigns'; require '../includes/header.php'; ?>
<a class="btn" href="create_campaign.php">New campaign</a><br><br>
<form class="filter"><select name="status"><option value="">All</option><?php foreach(['UPCOMING','ACTIVE','COMPLETED','CANCELLED'] as $x) echo "<option ".($f===$x?'selected':'').">$x</option>"; ?></select><button class="btn">Filter</button></form>
<div class="tw"><table><thead><tr><th>Name</th><th>Date</th><th>Location</th><th>Registrations</th><th>Donations</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach($s as $c): ?><tr><td><?= h($c['campaign_name']) ?></td><td><?= h($c['campaign_date']) ?><?php if(!empty($c['start_time'])): ?><br><span class="muted"><?= h(substr($c['start_time'],0,5)) ?>&ndash;<?= h(substr($c['end_time'],0,5)) ?></span><?php endif; ?></td><td><?= h($c['location']) ?></td><td><a class="btn sm alt" href="campaign_attendees.php?id=<?= (int)$c['campaign_id'] ?>"><?= (int)$c['rc'] ?><?= !empty($c['max_donors'])?' / '.(int)$c['max_donors']:'' ?> registered</a></td><td><?= (int)$c['dc'] ?></td><td><?= badge($c['status']) ?></td>
<td><?php if(in_array($c['status'],['UPCOMING','ACTIVE'])): ?><a class="btn sm alt" href="edit_campaign.php?id=<?= $c['campaign_id'] ?>">Edit</a> <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="campaign_id" value="<?= $c['campaign_id'] ?>">
<?php if($c['status']==='UPCOMING') echo '<button name="status" value="ACTIVE" class="btn sm alt">Start</button> '; ?><button name="status" value="COMPLETED" class="btn sm">Close</button>
<button name="status" value="CANCELLED" class="btn sm alt" data-confirm="Cancel campaign?">Cancel</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
<h2>Upcoming walk-in bookings</h2><div class="tw"><table><thead><tr><th>Date</th><th>Time</th><th>Donor</th><th>Group</th><th>Status</th></tr></thead><tbody>
<?php foreach($walkins as $w): ?><tr><td><?= h($w['requested_date']) ?></td><td><?= h(substr($w['requested_time'],0,5)) ?></td><td><?= h($w['full_name']) ?></td><td><?= h($w['blood_group']) ?></td><td><?= badge($w['status']) ?></td></tr><?php endforeach; ?><?php if(!$walkins): ?><tr><td colspan="5">No walk-in bookings yet.</td></tr><?php endif; ?></tbody></table></div>
<?php require '../includes/footer.php';
