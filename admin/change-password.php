<?php
require_once __DIR__.'/../includes/auth.php'; require_role('ADMIN');
if($_SERVER['REQUEST_METHOD']==='POST'){
  $current=$_POST['current_password']??''; $new=$_POST['new_password']??''; $confirm=$_POST['confirm_password']??'';
  $s=$pdo->prepare('SELECT password FROM users WHERE user_id=? AND role=\'ADMIN\''); $s->execute([$_SESSION['uid']]); $hash=$s->fetchColumn();
  if(!$hash || !password_verify($current,$hash)) flash('Your current password is incorrect.','err');
  elseif(strlen($new)<8 || !preg_match('/[A-Za-z]/',$new) || !preg_match('/[0-9]/',$new)) flash('Use at least 8 characters, including a letter and a number.','err');
  elseif($new!==$confirm) flash('The new passwords do not match.','err');
  elseif(password_verify($new,$hash)) flash('Choose a password different from your current password.','err');
  else{
    $pdo->prepare('UPDATE users SET password=?,must_change_password=0 WHERE user_id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$_SESSION['uid']]);
    audit($pdo,$_SESSION['uid'],'CHANGE_PASSWORD','users',$_SESSION['uid'],'Administrator changed their password.');
    session_regenerate_id(true); flash('Password changed successfully.'); redirect('admin/change-password.php');
  }
}
$title='Change Password'; $page_intro='Change your account password.'; require '../includes/header.php'; ?>
<div class="card" style="max-width:720px"><h2> Account security</h2><p class="muted">Your password is stored as a secure hash. Never share it with anyone.</p>
<form method="post" autocomplete="off"><?= csrf_field() ?>
<label>Current password</label><input type="password" name="current_password" autocomplete="current-password" required>
<label>New password</label><input type="password" name="new_password" minlength="8" autocomplete="new-password" required><small class="muted">At least 8 characters, including a letter and a number.</small>
<label>Confirm new password</label><input type="password" name="confirm_password" minlength="8" autocomplete="new-password" required><br><button class="btn">Update password</button> <a class="btn alt" href="profile.php">Back to profile</a></form></div>
<?php require '../includes/footer.php'; ?>
