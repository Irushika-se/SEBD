<?php require_once __DIR__.'/../includes/auth.php'; require_role('HEALTHCARE');
$o=my_org($pdo); require_verified_org($o);
$cid=(int)get('id',$_POST['id']??0);
$s=$pdo->prepare("SELECT * FROM donation_campaigns WHERE campaign_id=? AND organization_id=?"); $s->execute([$cid,$o['organization_id']]); $c=$s->fetch();
if(!$c||!in_array($c['status'],['UPCOMING','ACTIVE'])){ flash('Campaign cannot be edited.','err'); redirect('healthcare/campaigns.php'); }

if($_SERVER['REQUEST_METHOD']==='POST'){
  $date=post('campaign_date');
  $max=(int)post('max_donors')>0?(int)post('max_donors'):null;
  $err=(post('campaign_name')===''||post('location')==='')?'Name and location are required.':campaign_check($pdo,$o['organization_id'],$date,post('start_time'),post('end_time'),post('location'),$max,$cid);
  if($err===''){
    $moved0=$date!==$c['campaign_date']||post('start_time').':00'!==$c['start_time']||post('end_time').':00'!==$c['end_time']||trim(post('location'))!==trim($c['location']);
    $dn=$pdo->prepare("SELECT COUNT(*) FROM donations WHERE campaign_id=?"); $dn->execute([$cid]);
    if($moved0 && (int)$dn->fetchColumn()>0) $err='Donations are already recorded for this campaign, so its date, time and location can no longer be changed. You can still edit the name, description and other details.';
    elseif($c['status']==='ACTIVE' && $date>date('Y-m-d')) $err='A campaign that has already started cannot be moved to a later date.';
  }
  if($err!==''){ flash($err,'err'); redirect('healthcare/edit_campaign.php?id='.$cid); }
  $pdo->beginTransaction();
  try{
    $pdo->prepare("SELECT campaign_id FROM donation_campaigns WHERE campaign_id=? FOR UPDATE")->execute([$cid]); // same lock donors take to register: a registration cannot slip in between the capacity check and this update
    if($max!==null){ $rc=$pdo->prepare("SELECT COUNT(*) FROM donor_campaign_registrations WHERE campaign_id=? AND status IN ('REGISTERED','ATTENDED')"); $rc->execute([$cid]); $nowTaken=(int)$rc->fetchColumn(); if($max<$nowTaken){ $pdo->rollBack(); flash('Max donors cannot be lower than the donors already registered ('.$nowTaken.').','err'); redirect('healthcare/edit_campaign.php?id='.$cid); } }
    $pdo->prepare("UPDATE donation_campaigns SET campaign_name=?,description=?,location=?,campaign_date=?,start_time=?,end_time=?,max_donors=? WHERE campaign_id=? AND organization_id=?")
        ->execute([post('campaign_name'),post('description'),post('location'),$date,post('start_time').':00',post('end_time').':00',$max,$cid,$o['organization_id']]);
    $moved=$date!==$c['campaign_date']||post('start_time').':00'!==$c['start_time']||post('end_time').':00'!==$c['end_time']||trim(post('location'))!==trim($c['location']);
    if($moved) campaign_notify_donors($pdo,$cid,'Campaign details changed','"'.post('campaign_name').'" is now on '.$date.', '.post('start_time').'-'.post('end_time').' at '.post('location').'. If this no longer suits you, please cancel your registration.');
    audit($pdo,$_SESSION['uid'],'UPDATE','donation_campaigns',$cid,'Campaign edited.');
    $pdo->commit();
  }catch(Throwable $e){ $pdo->rollBack(); flash('Could not update the campaign.','err'); redirect('healthcare/edit_campaign.php?id='.$cid); }
  flash('Campaign updated.'); redirect('healthcare/campaigns.php');
}
$title='Edit Campaign'; require '../includes/header.php'; ?>
<form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $cid ?>"><div class="row">
<div><label>Campaign name</label><input name="campaign_name" value="<?= h($c['campaign_name']) ?>" required></div>
<div><label>Date</label><input type="date" name="campaign_date" min="<?= h(min(date('Y-m-d'),$c['campaign_date'])) ?>" value="<?= h($c['campaign_date']) ?>" required></div>
<div><label>Start time</label><input type="time" name="start_time" value="<?= h(substr($c['start_time']??'09:00:00',0,5)) ?>" required></div><div><label>End time</label><input type="time" name="end_time" value="<?= h(substr($c['end_time']??'15:00:00',0,5)) ?>" required></div><div><label>Location</label><input name="location" value="<?= h($c['location']) ?>" required></div><div><label>Max donors <span class="muted">(blank = unlimited)</span></label><input type="number" name="max_donors" min="1" max="5000" value="<?= h($c['max_donors']??'') ?>"></div></div>
<label>Description</label><textarea name="description"><?= h($c['description']) ?></textarea><br>
<button class="btn">Save changes</button> <a class="btn alt" href="campaigns.php">Back</a></form>
<?php require '../includes/footer.php';
