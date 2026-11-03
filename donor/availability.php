<?php require_once __DIR__.'/../includes/auth.php'; require_role('DONOR');
$d=my_donor($pdo);
$eligible=$d['verification_status']==='VERIFIED' && $d['health_status']==='CLEARED' && donor_gap_ok($d['last_donation_date']);
if($_SERVER['REQUEST_METHOD']==='POST'){
  $new=post('status');
  if(!in_array($new,['AVAILABLE','UNAVAILABLE'])) flash('Choose a valid availability status.','err');
  elseif($new==='AVAILABLE' && !$eligible) flash('You can only mark yourself available when verified, health-cleared, and at least '.DONATION_GAP_MONTHS.' months past your last donation.','err');
  elseif($new===$d['availability_status']) flash('Your availability is already set to that status.','err');
  else{
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE donors SET availability_status=? WHERE donor_id=?')->execute([$new,$d['donor_id']]);
    $pdo->prepare('INSERT INTO donor_availability_history(donor_id,availability_status) VALUES(?,?)')->execute([$d['donor_id'],$new]);
    audit($pdo,$_SESSION['uid'],'UPDATE','donors',$d['donor_id'],"Availability set to $new."); $pdo->commit(); flash('Availability updated.');
  }
  redirect('donor/availability.php');
}
$h=$pdo->prepare('SELECT * FROM donor_availability_history WHERE donor_id=? ORDER BY changed_at DESC'); $h->execute([$d['donor_id']]);
$title='Availability'; $page_intro='Control whether healthcare organizations can match you to emergency requests.'; require '../includes/header.php'; ?>
<div class="card"><h2>Current status: <?= badge($d['availability_status']) ?></h2>
<?php if(!$eligible): ?><div class="alert">You cannot become available yet. Current verification: <?= h($d['verification_status']) ?>; health status: <?= h($d['health_status']) ?>. The matching system also applies a <?= DONATION_GAP_MONTHS ?>-month minimum interval after a recorded donation.</div><a class="btn sm alt" href="verification-status.php">View verification status</a><?php endif; ?>
<form method="post"><?= csrf_field() ?><br><button name="status" value="AVAILABLE" class="btn" <?= (!$eligible||$d['availability_status']==='AVAILABLE')?'disabled':'' ?>>Set Available</button> <button name="status" value="UNAVAILABLE" class="btn alt" <?= $d['availability_status']==='UNAVAILABLE'?'disabled':'' ?>>Set Unavailable</button></form></div>
<h2>Availability history</h2><div class="tw"><table><thead><tr><th>Status</th><th>Changed at</th></tr></thead><tbody><?php foreach($h as $r): ?><tr><td><?= badge($r['availability_status']) ?></td><td><?= h($r['changed_at']) ?></td></tr><?php endforeach; ?><?php if(!$h->rowCount()): ?><tr><td colspan="2">No availability changes recorded.</td></tr><?php endif; ?></tbody></table></div>
<?php require '../includes/footer.php'; ?>
