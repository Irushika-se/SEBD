<?php require_once __DIR__.'/../includes/auth.php'; require_role('ADMIN');
$bg=get('blood_group'); $v=get('verification_status'); $a=get('availability_status');
$sql="SELECT d.*,u.full_name,u.email,u.status ustatus FROM donors d JOIN users u USING(user_id) WHERE 1"; $p=[];
foreach(['blood_group'=>$bg,'verification_status'=>$v,'availability_status'=>$a] as $k=>$x) if($x){ $sql.=" AND d.$k=?"; $p[]=$x; }
$s=$pdo->prepare($sql." ORDER BY d.created_at DESC"); $s->execute($p);
$title='Donors'; require '../includes/header.php'; ?>
<form class="filter"><input id="tsearch" placeholder="Search table..."><?= blood_select('blood_group',$bg,true) ?>
<select name="verification_status"><option value="">Verification</option><?php foreach(['PENDING','VERIFIED','REJECTED'] as $x) echo "<option ".($v===$x?'selected':'').">$x</option>"; ?></select>
<select name="availability_status"><option value="">Availability</option><?php foreach(['AVAILABLE','UNAVAILABLE'] as $x) echo "<option ".($a===$x?'selected':'').">$x</option>"; ?></select><button class="btn">Filter</button></form>
<div class="tw"><table class="searchable"><thead><tr><th>Name</th><th>Email</th><th>Group</th><th>City</th><th>Health</th><th>Verification</th><th>Availability</th><th>Last donation</th></tr></thead><tbody>
<?php foreach($s as $d): ?><tr><td><?= h($d['full_name']) ?></td><td><?= h($d['email']) ?></td><td><?= h($d['blood_group']) ?></td><td><?= h($d['city']) ?></td><td><?= badge($d['health_status']) ?></td><td><?= badge($d['verification_status']) ?></td><td><?= badge($d['availability_status']) ?></td><td><?= h($d['last_donation_date']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php require '../includes/footer.php';
