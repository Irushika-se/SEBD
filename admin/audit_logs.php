<?php require_once __DIR__.'/../includes/auth.php'; require_role('ADMIN');
$a=get('action'); $f=get('from'); $t=get('to'); $who=get('user'); $tb=get('table');
$okd=fn($x)=>$x===''||(bool)(($o=DateTime::createFromFormat('Y-m-d',$x))&&$o->format('Y-m-d')===$x); if(!$okd($f))$f=''; if(!$okd($t))$t='';
// LEFT JOIN: system / unknown-user events (user_id NULL) must still be listed
$sql="SELECT a.*,u.full_name,u.role FROM audit_logs a LEFT JOIN users u USING(user_id) WHERE 1"; $p=[];
if($a){$sql.=" AND a.action=?";$p[]=$a;} if($tb){$sql.=" AND a.table_name=?";$p[]=$tb;}
if($who!==''){$sql.=" AND (u.full_name LIKE ? OR u.email LIKE ?)";$p[]="%$who%";$p[]="%$who%";}
if($f){$sql.=" AND DATE(a.created_at)>=?";$p[]=$f;} if($t){$sql.=" AND DATE(a.created_at)<=?";$p[]=$t;}
$s=$pdo->prepare($sql." ORDER BY a.created_at DESC,a.audit_id DESC LIMIT 500"); $s->execute($p);
$acts=$pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
$tbls=$pdo->query("SELECT DISTINCT table_name FROM audit_logs WHERE table_name IS NOT NULL ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
$title='Audit Logs'; require '../includes/header.php'; ?>
<form class="filter"><input id="tsearch" placeholder="Search table..."><input name="user" value="<?= h($who) ?>" placeholder="User name / e-mail"><select name="action"><option value="">All actions</option><?php foreach($acts as $x) echo "<option ".($a===$x?'selected':'').">".h($x)."</option>"; ?></select>
<select name="table"><option value="">All tables</option><?php foreach($tbls as $x) echo "<option ".($tb===$x?'selected':'').">".h($x)."</option>"; ?></select>
<label class="dl">Activity from <input type="date" name="from" value="<?= h($f) ?>"></label><label class="dl">Activity to <input type="date" name="to" value="<?= h($t) ?>"></label><button class="btn">Filter</button></form>
<p class="muted">Showing the latest 500 matching records. Failed sign-ins with an unregistered e-mail appear as LOGIN_UNKNOWN_EMAIL.</p>
<div class="tw"><table class="searchable"><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Table</th><th>Record</th><th>Description</th><th>IP</th></tr></thead><tbody>
<?php foreach($s as $r): ?><tr><td><?= h($r['created_at']) ?></td><td><?= $r['full_name']!==null ? h($r['full_name']).' ('.h($r['role']).')' : '<span class="muted">(not signed in)</span>' ?></td><td><?= h($r['action']) ?></td><td><?= h($r['table_name']) ?></td><td><?= h($r['record_id']) ?></td><td><?= h($r['description']) ?></td><td><?= h($r['ip_address']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php require '../includes/footer.php';
