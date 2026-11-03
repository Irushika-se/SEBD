<?php require_once __DIR__.'/../includes/auth.php'; require_role('ADMIN');
$bg=get('blood_group'); $low=get('low')==='1'; $sql="SELECT i.*,o.organization_name FROM blood_inventory i JOIN healthcare_organizations o USING(organization_id) WHERE 1"; $p=[]; if($bg){$sql.=" AND i.blood_group=?";$p[]=$bg;} if($low) $sql.=" AND i.units_available<=i.minimum_stock";
$s=$pdo->prepare($sql." ORDER BY o.organization_name,i.blood_group"); $s->execute($p);
$today=date('Y-m-d'); $soon=date('Y-m-d',strtotime('+'.EXPIRY_WARN_DAYS.' days'));
$bq=function($where,$args=[]) use($pdo){ $q=$pdo->prepare("SELECT b.*,o.organization_name FROM blood_batches b JOIN healthcare_organizations o USING(organization_id) WHERE $where ORDER BY b.expiry_date,b.batch_id LIMIT 200"); $q->execute($args); return $q->fetchAll(); };
$expiring=$bq("b.status='ACTIVE' AND b.units_remaining>0 AND b.expiry_date<=?",[$soon]);
$quar=$bq("b.status='QUARANTINE' AND b.units_remaining>0");
$dispose=$bq("b.status IN ('EXPIRED','REJECTED') AND b.disposed_at IS NULL AND b.units_remaining>0");
$title='System Inventory'; $page_intro='Read-only oversight of every organization\'s stock, batches in screening, units about to expire and expired bags not yet discarded.'; require '../includes/header.php';
$bt=function($rows,$empty,$cols) { echo '<div class="tw"><table><thead><tr><th>Organization</th><th>Group</th><th>Units</th><th>Collected</th><th>Expiry</th>'.$cols.'</tr></thead><tbody>'; global $today;
  foreach($rows as $r){ $ex=$r['expiry_date']<$GLOBALS['today']; echo '<tr><td>'.h($r['organization_name']).'</td><td>'.h($r['blood_group']).'</td><td>'.(int)$r['units_remaining'].'</td><td>'.h($r['collected_date']).'</td><td>'.h($r['expiry_date']).($ex?' '.badge('EXPIRED'):'').'</td><td>'.badge($r['status']).'</td></tr>'; }
  if(!$rows) echo '<tr><td colspan="6">'.h($empty).'</td></tr>'; echo '</tbody></table></div>'; }; ?>
<h2>Stock by organization</h2>
<form class="filter"><?= blood_select('blood_group',$bg,true) ?><label><input type="checkbox" name="low" value="1" <?= $low?'checked':'' ?>> Low stock only</label><button class="btn">Filter</button></form>
<div class="tw"><table><thead><tr><th>Organization</th><th>Group</th><th>Units</th><th>Minimum</th><th>Alert</th><th>Updated</th></tr></thead><tbody>
<?php foreach($s as $r): ?><tr><td><?= h($r['organization_name']) ?></td><td><?= h($r['blood_group']) ?></td><td><?= $r['units_available'] ?></td><td><?= $r['minimum_stock'] ?></td><td><?= $r['units_available']<=$r['minimum_stock']?badge('LOW'):'OK' ?></td><td><?= h($r['last_updated']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<h2>Expiring within <?= EXPIRY_WARN_DAYS ?> days (or already past expiry, still counted as stock)</h2><?php $bt($expiring,'No batches close to expiry.','<th>Status</th>'); ?>
<h2>In quarantine (screening tests pending, not counted as stock)</h2><?php $bt($quar,'No units in quarantine.','<th>Status</th>'); ?>
<h2>Expired / rejected bags waiting to be discarded</h2><?php $bt($dispose,'Nothing waiting for disposal.','<th>Status</th>'); ?>
<?php require '../includes/footer.php';
