<?php require_once __DIR__.'/../includes/auth.php'; require_role('ADMIN');
$st=get('status'); $ur=get('urgency'); $bg=get('blood_group');
$rid=(int)get('id');
if($rid){ // detail view: request, every donor match and the status history (read-only oversight)
  $q=$pdo->prepare("SELECT r.*,o.organization_name,o.city org_city FROM emergency_requests r JOIN healthcare_organizations o USING(organization_id) WHERE r.request_id=?"); $q->execute([$rid]); $r=$q->fetch();
  if(!$r){ flash('Request not found.','err'); redirect('admin/requests.php'); }
  $m=$pdo->prepare("SELECT m.*,u.full_name,d.blood_group,d.city FROM donor_matches m JOIN donors d USING(donor_id) JOIN users u ON u.user_id=d.user_id WHERE m.request_id=? ORDER BY FIELD(m.status,'COMPLETED','ACCEPTED','NOTIFIED','DECLINED'),m.distance_km"); $m->execute([$rid]); $matches=$m->fetchAll();
  $hq=$pdo->prepare("SELECT h.*,u.full_name FROM emergency_request_history h LEFT JOIN users u ON u.user_id=h.changed_by WHERE h.request_id=? ORDER BY h.changed_at,h.history_id"); $hq->execute([$rid]); $hist=$hq->fetchAll();
  $cnt=array_count_values(array_column($matches,'status')); $title='Request #'.$rid; require '../includes/header.php'; ?>
<p><a class="btn sm alt" href="requests.php">&larr; All requests</a></p>
<div class="card"><b><?= h($r['organization_name']) ?></b> (<?= h($r['org_city']) ?>) &mdash; <?= h($r['blood_group']) ?>, <?= (int)$r['units_required'] ?> unit(s) <?= badge($r['urgency']) ?> <?= badge($r['status']) ?>
<p>Needed by: <?= h($r['required_time']) ?> | Location: <?= h($r['location']) ?> | Created: <?= h($r['created_at']) ?><?= $r['closed_at']?' | Closed: '.h($r['closed_at']):'' ?><br>Reason: <?= h($r['reason']?:'-') ?> | Notes: <?= h($r['notes']?:'-') ?><br>
Matches: <?= count($matches) ?> notified in total &mdash; completed <?= (int)($cnt['COMPLETED']??0) ?>, accepted <?= (int)($cnt['ACCEPTED']??0) ?>, waiting <?= (int)($cnt['NOTIFIED']??0) ?>, declined <?= (int)($cnt['DECLINED']??0) ?></p></div>
<h2>Donor matches</h2><div class="tw"><table><thead><tr><th>Donor</th><th>Group</th><th>City</th><th>Distance</th><th>Score</th><th>Status</th><th>Notified</th><th>Responded</th><th>Reason</th></tr></thead><tbody>
<?php foreach($matches as $x): ?><tr><td><?= h($x['full_name']) ?></td><td><?= h($x['blood_group']) ?></td><td><?= h($x['city']) ?></td><td><?= $x['distance_km']!==null?h($x['distance_km']).' km':'-' ?></td><td><?= h($x['match_score']) ?></td><td><?= badge($x['status']) ?></td><td><?= h($x['matched_at']) ?></td><td><?= h($x['responded_at']?:'-') ?></td><td><?= h($x['decline_reason']?:'') ?></td></tr><?php endforeach; if(!$matches) echo '<tr><td colspan="9">No donors were notified.</td></tr>'; ?></tbody></table></div>
<h2>History</h2><div class="tw"><table><thead><tr><th>Time</th><th>Change</th><th>By</th><th>Notes</th></tr></thead><tbody>
<?php foreach($hist as $x): ?><tr><td><?= h($x['changed_at']) ?></td><td><?= h(($x['old_status']?:'-').' → '.$x['new_status']) ?></td><td><?= h($x['full_name']?:'system') ?></td><td><?= h($x['notes']) ?></td></tr><?php endforeach; if(!$hist) echo '<tr><td colspan="4">No history.</td></tr>'; ?></tbody></table></div>
<?php require '../includes/footer.php'; exit; }
$sql="SELECT r.*,o.organization_name,(SELECT COUNT(*) FROM donor_matches m WHERE m.request_id=r.request_id) mc,(SELECT COUNT(*) FROM donor_matches m WHERE m.request_id=r.request_id AND m.status IN ('ACCEPTED','COMPLETED')) ac FROM emergency_requests r JOIN healthcare_organizations o USING(organization_id) WHERE 1"; $p=[];
foreach(['status'=>$st,'urgency'=>$ur,'blood_group'=>$bg] as $k=>$v) if($v){ $sql.=" AND r.$k=?"; $p[]=$v; }
$s=$pdo->prepare($sql." ORDER BY r.created_at DESC"); $s->execute($p);
$title='Emergency Requests (All)'; require '../includes/header.php'; ?>
<form class="filter"><select name="status"><option value="">All statuses</option><?php foreach(['PENDING','MATCHING','PARTIALLY_FULFILLED','FULFILLED','CANCELLED'] as $x) echo "<option ".($st===$x?'selected':'').">$x</option>"; ?></select>
<select name="urgency"><option value="">All urgency</option><?php foreach(['CRITICAL','HIGH','NORMAL'] as $x) echo "<option ".($ur===$x?'selected':'').">$x</option>"; ?></select><?= blood_select('blood_group',$bg,true) ?><button class="btn">Filter</button></form>
<div class="tw"><table><thead><tr><th>#</th><th>Organization</th><th>Group</th><th>Units</th><th>Urgency</th><th>Needed by</th><th>Location</th><th>Status</th><th>Accepted/Matched</th><th></th></tr></thead><tbody>
<?php foreach($s as $r): ?><tr><td><a href="requests.php?id=<?= (int)$r['request_id'] ?>">#<?= (int)$r['request_id'] ?></a></td><td><?= h($r['organization_name']) ?></td><td><?= h($r['blood_group']) ?></td><td><?= $r['units_required'] ?></td><td><?= badge($r['urgency']) ?></td><td><?= h($r['required_time']) ?></td><td><?= h($r['location']) ?></td><td><?= badge($r['status']) ?><?= in_array($r['status'],OPEN_REQUEST,true)&&strtotime($r['required_time'])<time()?' '.badge('OVERDUE'):'' ?></td><td><?= $r['ac'] ?>/<?= $r['mc'] ?></td><td><a class="btn sm alt" href="requests.php?id=<?= (int)$r['request_id'] ?>">Details</a></td></tr><?php endforeach; ?></tbody></table></div>
<?php require '../includes/footer.php';
