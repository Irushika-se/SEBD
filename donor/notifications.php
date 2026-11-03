<?php require_once __DIR__.'/../includes/auth.php'; require_role('DONOR');
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(isset($_POST['read_all'])) $pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$_SESSION['uid']]);
  elseif(isset($_POST['read_one'])) $pdo->prepare('UPDATE notifications SET is_read=1 WHERE notification_id=? AND user_id=?')->execute([(int)post('notification_id'),$_SESSION['uid']]);
  redirect('donor/notifications.php');
}
$unreadOnly=get('filter')==='unread'; $sql='SELECT * FROM notifications WHERE user_id=?'.($unreadOnly?' AND is_read=0':'').' ORDER BY created_at DESC'; $s=$pdo->prepare($sql); $s->execute([$_SESSION['uid']]); $rows=$s->fetchAll();
$nmsg=function($x){ return $x['notification_type']==='WELCOME' ? 'Your donor account has been created and is pending administrator review. Next, the administrator will schedule your health checkup and you will be notified here.' : $x['message']; };
$ndate=function($t){ return date('d M Y, g:i A',strtotime($t)); };
$title='Notifications'; $page_intro='Emergency alerts, appointment updates, and account messages.'; require '../includes/header.php'; ?>
<div class="filter"><a class="btn sm <?= $unreadOnly?'alt':'' ?>" href="notifications.php">All notifications</a> <a class="btn sm <?= $unreadOnly?'':'alt' ?>" href="notifications.php?filter=unread">Unread only</a><form method="post"><?= csrf_field() ?><button name="read_all" value="1" class="btn sm">Mark all as read</button></form></div>
<div class="card nt-list">
<?php foreach($rows as $r): ?>
<article class="nt-item<?= !$r['is_read']?' unread':'' ?>">
  <div class="nt-body">
    <div class="nt-top"><b><?= h($r['title']) ?></b><?= !$r['is_read']?'<span class="badge nb">NEW</span>':'' ?></div>
    <p><?= h($nmsg($r)) ?></p>
    <span class="nr-time"> <?= h($ndate($r['created_at'])) ?></span>
  </div>
  <div class="nt-actions">
    <?php if($r['related_match_id']||$r['related_request_id']): ?><a class="btn sm" href="emergency_requests.php">View request</a><?php elseif(in_array($r['notification_type'],['VERIFICATION'],true)): ?><a class="btn sm" href="verification-status.php">View status</a><?php elseif($r['notification_type']==='WELCOME'): ?><a class="btn sm" href="dashboard.php">Go to dashboard</a><?php elseif(str_contains($r['notification_type'],'CHECKUP')): ?><a class="btn sm" href="appointments.php">View checkup</a><?php elseif(str_contains($r['notification_type'],'DONATION')): ?><a class="btn sm" href="donation_history.php">View history</a><?php endif; ?>
    <?php if(!$r['is_read']): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="notification_id" value="<?= (int)$r['notification_id'] ?>"><button class="btn sm alt" name="read_one" value="1">Mark read</button></form><?php endif; ?>
  </div>
</article>
<?php endforeach; ?>
<?php if(!$rows): ?><div class="es"><div><span>You have no notifications yet.</span><small>Important updates will appear here.</small></div></div><?php endif; ?>
</div>
<?php require '../includes/footer.php'; ?>
