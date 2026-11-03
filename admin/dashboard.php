<?php require_once __DIR__.'/../includes/auth.php'; require_role('ADMIN');
$c=fn($sql)=>$pdo->query($sql)->fetchColumn();
$stats=['Users'=>$c("SELECT COUNT(*) FROM users"),'Donors pending verification'=>$c("SELECT COUNT(*) FROM donors WHERE verification_status='PENDING'"),
'Cleared - awaiting final verification'=>$c("SELECT COUNT(*) FROM donors WHERE verification_status='PENDING' AND health_status='CLEARED'"),'Verified donors'=>$c("SELECT COUNT(*) FROM donors WHERE verification_status='VERIFIED'"),'Organizations pending'=>$c("SELECT COUNT(*) FROM healthcare_organizations WHERE verification_status='PENDING'"),
'Open requests'=>$c("SELECT COUNT(*) FROM emergency_requests WHERE status IN ('PENDING','MATCHING','PARTIALLY_FULFILLED') AND required_time>=NOW()"),'Overdue requests (closing soon)'=>$c("SELECT COUNT(*) FROM emergency_requests WHERE status IN ('PENDING','MATCHING','PARTIALLY_FULFILLED') AND required_time<NOW()"),'Donor matches'=>$c("SELECT COUNT(*) FROM donor_matches"),
'Units in quarantine (screening)'=>$c("SELECT COALESCE(SUM(units_remaining),0) FROM blood_batches WHERE status='QUARANTINE'"),'Low-stock entries'=>$c("SELECT COUNT(*) FROM blood_inventory WHERE units_available<=minimum_stock"),];
$a=$pdo->query("SELECT a.*,u.full_name FROM audit_logs a JOIN users u USING(user_id) ORDER BY created_at DESC LIMIT 8");
$title='Administrator Dashboard'; require '../includes/header.php'; ?>
<div class="grid"><?php foreach($stats as $k=>$v) echo "<div class='stat'><b>$v</b><span>".h($k)."</span></div>"; ?></div>
<h2>Recent audit activity</h2><div class="tw"><table><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Description</th></tr></thead><tbody>
<?php foreach($a as $r) echo "<tr><td>".h($r['created_at'])."</td><td>".h($r['full_name'])."</td><td>".h($r['action'])."</td><td>".h($r['description'])."</td></tr>"; ?></tbody></table></div>
<?php require '../includes/footer.php';
