<?php require_once __DIR__.'/../includes/auth.php'; require_role('ADMIN');
function temp_password(){ $al='abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ'; $t=''; for($i=0;$i<8;$i++) $t.=$al[random_int(0,strlen($al)-1)]; return $t.random_int(10,99); }
$me=(int)$_SESSION['uid'];
if($_SERVER['REQUEST_METHOD']==='POST'){
  $do=post('do');
  if($do==='create'){
    $name=post('full_name'); $email=post('email'); $phone=post('phone'); $err=[];
    if(mb_strlen($name)<2||mb_strlen($name)>100) $err[]='Enter a name (2-100 characters).';
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>100) $err[]='Enter a valid e-mail address.';
    if($phone!=='' && !preg_match('/^[0-9]{10}$/',$phone)) $err[]='Phone number must be exactly 10 digits.';
    if(!$err && $phone!==''){ $c=$pdo->prepare("SELECT 1 FROM users WHERE phone=?"); $c->execute([$phone]); if($c->fetch()) $err[]='Phone number already registered.'; }
    if(!$err){ $c=$pdo->prepare("SELECT 1 FROM users WHERE email=?"); $c->execute([$email]); if($c->fetch()) $err[]='That e-mail is already registered.'; }
    if($err) flash(implode(' ',$err),'err');
    else{
      $tmp=temp_password();
      $pdo->prepare("INSERT INTO users(full_name,email,password,role,phone,status,must_change_password) VALUES(?,?,?,'ADMIN',?,'ACTIVE',1)")->execute([$name,$email,password_hash($tmp,PASSWORD_DEFAULT),$phone?:null]);
      audit($pdo,$me,'CREATE_ADMIN','users',$pdo->lastInsertId(),"Administrator account created for $email (must change password at first login).");
      flash("Administrator account created for $name. Temporary password: $tmp  - give it to them in person or by phone (shown only once). They must set a new password at first login.");
    }
  } else {
    $uid=(int)post('user_id'); $t=$pdo->prepare("SELECT full_name,status FROM users WHERE user_id=? AND role='ADMIN'"); $t->execute([$uid]); $tu=$t->fetch();
    if(!$tu||$uid===$me) flash('Not allowed. Use the Password page to change your own password; you cannot suspend yourself.','err');
    elseif($do==='reset_password'){
      $tmp=temp_password(); $pdo->prepare("UPDATE users SET password=?,must_change_password=1 WHERE user_id=?")->execute([password_hash($tmp,PASSWORD_DEFAULT),$uid]);
      audit($pdo,$me,'PASSWORD_RESET','users',$uid,"Temporary password issued for administrator {$tu['full_name']}.");
      flash("Temporary password for {$tu['full_name']}: $tmp  - shown only once. They must change it at next login.");
    } elseif(in_array($do,['ACTIVE','SUSPENDED'],true)){
      $pdo->prepare("UPDATE users SET status=? WHERE user_id=?")->execute([$do,$uid]);
      audit($pdo,$me,$do==='ACTIVE'?'ACTIVATE':'SUSPEND','users',$uid,"Administrator account {$tu['full_name']} set to $do."); flash("Administrator account set to $do.");
    } else flash('Unknown action.','err');
  }
  redirect('admin/admins.php');
}
$rows=$pdo->query("SELECT user_id,full_name,email,phone,status,must_change_password,created_at FROM users WHERE role='ADMIN' ORDER BY created_at")->fetchAll();
$title='Administrator Accounts'; $page_intro='Create administrators, suspend or reactivate them and issue temporary passwords. You cannot suspend yourself, so at least one administrator always stays active.'; require '../includes/header.php'; ?>
<div class="card"><h2>New administrator</h2><form method="post" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="do" value="create">
<label>Full name</label><input name="full_name" maxlength="100" required><label>E-mail</label><input type="email" name="email" maxlength="100" required><label>Phone (optional)</label><input name="phone" maxlength="20">
<br><button class="btn" data-confirm="Create this administrator account? A one-time temporary password will be shown.">Create administrator</button></form></div>
<h2>Administrators</h2><div class="tw"><table><thead><tr><th>Name</th><th>E-mail</th><th>Phone</th><th>Status</th><th>Created</th><th></th></tr></thead><tbody>
<?php foreach($rows as $u): ?><tr><td><?= h($u['full_name']) ?><?= (int)$u['user_id']===$me?' <span class="badge">YOU</span>':'' ?></td><td><?= h($u['email']) ?></td><td><?= h($u['phone']) ?></td><td><?= badge($u['status']) ?><?= $u['must_change_password']?' '.badge('PASSWORD_RESET'):'' ?></td><td><?= h($u['created_at']) ?></td>
<td><?php if((int)$u['user_id']!==$me): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
<?php if($u['status']==='SUSPENDED'): ?><button name="do" value="ACTIVE" class="btn sm">Activate</button><?php else: ?><button name="do" value="SUSPENDED" class="btn sm alt" data-confirm="Suspend this administrator?">Suspend</button><?php endif; ?> <button name="do" value="reset_password" class="btn sm alt" data-confirm="Issue a new temporary password? Their current password stops working.">Reset password</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php require '../includes/footer.php';
