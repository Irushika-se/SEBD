<?php require_once __DIR__.'/../includes/auth.php'; require_role('HEALTHCARE');
$o=my_org($pdo); $bg=get('blood_group'); $t=get('type'); $ff=get('from'); $ft=get('to');
$okd=fn($x)=>$x===''||(bool)(DateTime::createFromFormat('Y-m-d',$x)&&DateTime::createFromFormat('Y-m-d',$x)->format('Y-m-d')===$x); if(!$okd($ff))$ff=''; if(!$okd($ft))$ft='';
$sql="SELECT l.*,u.full_name FROM inventory_logs l JOIN users u ON u.user_id=l.changed_by WHERE l.organization_id=?"; $p=[$o['organization_id']];
if($bg){$sql.=" AND l.blood_group=?";$p[]=$bg;} if($t){$sql.=" AND l.change_type=?";$p[]=$t;}
if($ff){$sql.=" AND DATE(l.created_at)>=?";$p[]=$ff;} if($ft){$sql.=" AND DATE(l.created_at)<=?";$p[]=$ft;}
$s=$pdo->prepare($sql." ORDER BY l.created_at DESC,l.inventory_log_id DESC LIMIT 500"); $s->execute($p);
$title='Inventory History'; require '../includes/header.php'; ?>
<form class="filter"><?= blood_select('blood_group',$bg,true) ?><select name="type"><option value="">All types</option><?php foreach(['DONATION','EMERGENCY_USE','ADJUSTMENT','RESTOCK','EXPIRED'] as $x) echo "<option ".($t===$x?'selected':'').">$x</option>"; ?></select><label class="dl">Stock changed from <input type="date" name="from" value="<?= h($ff) ?>"></label><label class="dl">Stock changed to <input type="date" name="to" value="<?= h($ft) ?>"></label><button class="btn">Filter</button></form>
<div class="tw"><table><thead><tr><th>Date</th><th>Group</th><th>Type</th><th>Change</th><th>Before</th><th>After</th><th>By</th></tr></thead><tbody>
<?php foreach($s as $r): ?><tr><td><?= h($r['created_at']) ?></td><td><?= h($r['blood_group']) ?></td><td><?= h($r['change_type']) ?></td><td><?= $r['quantity_change'] ?></td><td><?= $r['previous_units'] ?></td><td><?= $r['new_units'] ?></td><td><?= h($r['full_name']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php require '../includes/footer.php';
