<?php require_once __DIR__.'/../includes/auth.php'; require_role('HEALTHCARE');
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(isset($_POST['read_all'])) $pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$_SESSION['uid']]);
  elseif(isset($_POST['read_one'])) $pdo->prepare('UPDATE notifications SET is_read=1 WHERE notification_id=? AND user_id=?')->execute([(int)post('notification_id'),$_SESSION['uid']]);
  redirect('healthcare/notifications.php');
}
$unreadOnly=get('filter')==='unread'; $s=$pdo->prepare('SELECT * FROM notifications WHERE user_id=?'.($unreadOnly?' AND is_read=0':'').' ORDER BY created_at DESC LIMIT 200'); $s->execute([$_SESSION['uid']]); $rows=$s->fetchAll();
$title='Notifications'; $page_intro='Donor responses and checkup assignments.'; require '../includes/header.php'; ?>
<div class="filter"><a class="btn sm <?= $unreadOnly?'alt':'' ?>" href="notifications.php">All</a> <a class="btn sm <?= $unreadOnly?'':'alt' ?>" href="notifications.php?filter=unread">Unread only</a><form method="post"><?= csrf_field() ?><button name="read_all" value="1" class="btn sm">Mark all as read</button></form></div><br>
<div class="tw"><table><thead><tr><th>Date</th><th>Title</th><th>Message</th><th>Action</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?= h($r['created_at']) ?></td><td><?= h($r['title']) ?> <?= !$r['is_read']?'<span class="badge">NEW</span>':'' ?></td><td><?= h($r['message']) ?></td><td><?php if($r['related_request_id']): ?><a class="btn sm" href="matches.php?request_id=<?= (int)$r['related_request_id'] ?>">View matches</a><?php elseif($r['notification_type']==='STOCK_EXPIRED'): ?><a class="btn sm" href="inventory_history.php?type=EXPIRED">View expired units</a><?php elseif(str_contains($r['notification_type'],'CHECKUP')): ?><a class="btn sm" href="checkups.php">View checkups</a><?php endif; ?><?php if(!$r['is_read']): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="notification_id" value="<?= (int)$r['notification_id'] ?>"><button class="btn sm alt" name="read_one" value="1">Mark read</button></form><?php endif; ?></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="4">You have no notifications.</td></tr><?php endif; ?></tbody></table></div>
<?php require '../includes/footer.php'; ?>
