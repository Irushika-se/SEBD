<?php require_once __DIR__.'/../includes/auth.php'; require_role('HEALTHCARE');
$o=my_org($pdo); require_verified_org($o);
if($_SERVER['REQUEST_METHOD']==='POST'){
  $max=(int)post('max_donors')>0?(int)post('max_donors'):null;
  $err=(post('campaign_name')===''||post('location')==='')?'Name and location are required.':campaign_check($pdo,$o['organization_id'],post('campaign_date'),post('start_time'),post('end_time'),post('location'),$max);
  if($err!==''){ flash($err,'err'); redirect('healthcare/create_campaign.php'); }
  $pdo->prepare("INSERT INTO donation_campaigns(organization_id,campaign_name,description,location,campaign_date,start_time,end_time,max_donors) VALUES(?,?,?,?,?,?,?,?)")->execute([$o['organization_id'],post('campaign_name'),post('description'),post('location'),post('campaign_date'),post('start_time').':00',post('end_time').':00',$max]);
  $cid=$pdo->lastInsertId();
  audit($pdo,$_SESSION['uid'],'CREATE','donation_campaigns',$cid,'Campaign created.'); flash('Campaign published.'); redirect('healthcare/campaigns.php');
}
$title='Create Campaign'; require '../includes/header.php'; ?>
<form method="post" class="card"><?= csrf_field() ?><div class="row"><div><label>Campaign name</label><input name="campaign_name" required></div>
<div><label>Date</label><input type="date" name="campaign_date" min="<?= date('Y-m-d') ?>" required></div><div><label>Start time</label><input type="time" name="start_time" value="09:00" required></div><div><label>End time</label><input type="time" name="end_time" value="15:00" required></div><div><label>Location</label><input name="location" required></div><div><label>Max donors <span class="muted">(blank = unlimited)</span></label><input type="number" name="max_donors" min="1" max="5000"></div></div>
<label>Description</label><textarea name="description"></textarea><br><button class="btn">Publish</button></form>
<?php require '../includes/footer.php';
