<?php require_once __DIR__.'/../includes/auth.php'; require_role('ADMIN');
[$f,$t]=date_range(date('Y-m-01'),date('Y-m-d'));
function grp($pdo,$sql,$p=[]){ $s=$pdo->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_KEY_PAIR); }
function tb($a,$h){ echo "<div class='card'><b>".h($h)."</b><table>"; foreach($a as $k=>$v) echo "<tr><td>".h($k)."</td><td>".h($v)."</td></tr>"; if(!$a) echo "<tr><td class='muted'>No data</td></tr>"; echo "</table></div>"; }
$R=[ // title => [data, scope]  (scope: 'period' = follows the date filter, 'now' = current snapshot)
 'New registrations in period (by role)'=>[grp($pdo,"SELECT role,COUNT(*) FROM users WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY role",[$f,$t]),'period'],
 'Users by role (all time)'=>[grp($pdo,"SELECT role,COUNT(*) FROM users GROUP BY role"),'now'],
 'Users by status (all time)'=>[grp($pdo,"SELECT status,COUNT(*) FROM users GROUP BY status"),'now'],
 'Donors by verification (all time)'=>[grp($pdo,"SELECT verification_status,COUNT(*) FROM donors GROUP BY verification_status"),'now'],
 'Organizations by verification (all time)'=>[grp($pdo,"SELECT verification_status,COUNT(*) FROM healthcare_organizations GROUP BY verification_status"),'now'],
 'Requests by status'=>[grp($pdo,"SELECT status,COUNT(*) FROM emergency_requests WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY status",[$f,$t]),'period'],
 'Requests by urgency'=>[grp($pdo,"SELECT urgency,COUNT(*) FROM emergency_requests WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY urgency",[$f,$t]),'period'],
 'Matches by status'=>[grp($pdo,"SELECT status,COUNT(*) FROM donor_matches WHERE DATE(matched_at) BETWEEN ? AND ? GROUP BY status",[$f,$t]),'period'],
 'Units donated by type'=>[grp($pdo,"SELECT donation_type,SUM(units_donated) FROM donations WHERE status='COMPLETED' AND DATE(donation_date) BETWEEN ? AND ? GROUP BY donation_type",[$f,$t]),'period'],
 'Units donated by blood group'=>[grp($pdo,"SELECT blood_group,SUM(units_donated) FROM donations WHERE status='COMPLETED' AND DATE(donation_date) BETWEEN ? AND ? GROUP BY blood_group",[$f,$t]),'period'],
 'Total stock by group (now)'=>[grp($pdo,"SELECT blood_group,SUM(units_available) FROM blood_inventory GROUP BY blood_group"),'now'],
 'Units issued (emergency use)'=>[grp($pdo,"SELECT blood_group,SUM(-quantity_change) FROM inventory_logs WHERE change_type='EMERGENCY_USE' AND DATE(created_at) BETWEEN ? AND ? GROUP BY blood_group",[$f,$t]),'period'],
 'Units expired / discarded'=>[grp($pdo,"SELECT blood_group,SUM(-quantity_change) FROM inventory_logs WHERE change_type='EXPIRED' AND DATE(created_at) BETWEEN ? AND ? GROUP BY blood_group",[$f,$t]),'period'],
 'Organizations with low-stock groups (now)'=>[grp($pdo,"SELECT o.organization_name,GROUP_CONCAT(i.blood_group ORDER BY i.blood_group SEPARATOR ', ') FROM blood_inventory i JOIN healthcare_organizations o USING(organization_id) WHERE i.units_available<=i.minimum_stock GROUP BY o.organization_id"),'now'],
];
if(get('export')==='csv'){
  audit($pdo,$_SESSION['uid'],'EXPORT','reports',null,"Administrative report exported as CSV ($f to $t).");
  header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="sebd-admin-report-'.$f.'_to_'.$t.'.csv"'); header('Cache-Control: private, no-store');
  $o=fopen('php://output','w'); fwrite($o,"\xEF\xBB\xBF"); // BOM so Excel reads UTF-8
  $safe=fn($v)=>is_string($v)&&preg_match('/^[=+\-@\t\r]/',$v)?"'".$v:$v; // stop spreadsheet formula injection
  fputcsv($o,['Report period',$f,$t]); fputcsv($o,['Generated',date('Y-m-d H:i:s')]); fputcsv($o,[]);
  foreach($R as $name=>[$data,$scope]){ fputcsv($o,[$name,$scope==='period'?'(period)':'(current)']); foreach($data as $k=>$v) fputcsv($o,[$safe((string)$k),$safe((string)$v)]); if(!$data) fputcsv($o,['No data']); fputcsv($o,[]); }
  fclose($o); exit;
}
$title='Administrative Report'; $page_intro='Tables marked "all time" or "now" are current totals; the others follow the date range.'; require '../includes/header.php'; ?>
<form class="filter"><label class="dl">Report from <input type="date" name="from" value="<?= h($f) ?>"></label><label class="dl">Report to <input type="date" name="to" value="<?= h($t) ?>"></label><button class="btn">Generate</button>
<a class="btn alt" href="?from=<?= h($f) ?>&amp;to=<?= h($t) ?>&amp;export=csv">Export CSV</a><button type="button" class="btn alt" onclick="window.print()">Print</button></form>
<p class="muted">Period: <?= h($f) ?> to <?= h($t) ?></p>
<div class="row"><?php foreach($R as $name=>[$data,$scope]) tb($data,$name); ?></div>
<?php require '../includes/footer.php';
