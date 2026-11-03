<?php require_once __DIR__.'/../includes/auth.php'; require_role('ADMIN');
if($_SERVER['REQUEST_METHOD']==='POST'){
  $uid=(int)post('user_id'); $st=post('status');
  if(post('do')==='reset_password'){ // no e-mail service: the administrator issues a one-time temporary password and the user must change it at next login
    $t=$pdo->prepare("SELECT full_name FROM users WHERE user_id=? AND role<>'ADMIN'"); $t->execute([$uid]); $tn=$t->fetchColumn();
    if($tn===false||$uid===(int)$_SESSION['uid']) flash('Not allowed.','err');
    else{
      $al='abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ'; $tmp=''; for($i=0;$i<8;$i++) $tmp.=$al[random_int(0,strlen($al)-1)]; $tmp.=random_int(10,99);
      $pdo->prepare("UPDATE users SET password=?,must_change_password=1 WHERE user_id=?")->execute([password_hash($tmp,PASSWORD_DEFAULT),$uid]);
      audit($pdo,$_SESSION['uid'],'PASSWORD_RESET','users',$uid,"Administrator issued a temporary password for $tn (must be changed at next login).");
      flash("Temporary password for $tn: $tmp  - give it to the user in person or by phone (shown only once). They must set a new password at next login.");
    }
    redirect('admin/users.php');
  }
  if($uid!==(int)$_SESSION['uid'] && in_array($st,['ACTIVE','SUSPENDED'])){
    $rematch=[];
    try{ $pdo->beginTransaction();
      $u=$pdo->prepare("UPDATE users SET status=? WHERE user_id=? AND role<>'ADMIN'"); $u->execute([$st,$uid]);
      if($st==='SUSPENDED' && $u->rowCount()) $rematch=suspend_cleanup($pdo,$uid,(int)$_SESSION['uid']); // free matches / cancel open requests of the suspended account
      $pdo->commit();
    }catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); flash('Could not update the account.','err'); redirect('admin/users.php'); }
    foreach($rematch as $rr) rematch_if_exhausted($pdo,$rr);
    audit($pdo,$_SESSION['uid'],$st==='ACTIVE'?'ACTIVATE':'SUSPEND','users',$uid,"Account set to $st."); flash("Account set to $st.");
  } else flash('Not allowed.','err');
  redirect('admin/users.php');
}
$r=get('role'); $st=get('status'); $sql="SELECT * FROM users WHERE 1"; $p=[]; if($r){$sql.=" AND role=?";$p[]=$r;} if($st){$sql.=" AND status=?";$p[]=$st;}
$s=$pdo->prepare($sql." ORDER BY created_at DESC"); $s->execute($p);
$title='User Management'; require '../includes/header.php'; ?>
<form class="filter"><input id="tsearch" placeholder="Search table..."><select name="role"><option value="">All roles</option><?php foreach(['DONOR','HEALTHCARE','ADMIN'] as $x) echo "<option ".($r===$x?'selected':'').">$x</option>"; ?></select>
<select name="status"><option value="">All status</option><?php foreach(['PENDING','ACTIVE','SUSPENDED'] as $x) echo "<option ".($st===$x?'selected':'').">$x</option>"; ?></select><button class="btn">Filter</button></form>
<div class="tw"><table class="searchable"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Phone</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach($s as $u): ?><tr><td><?= h($u['full_name']) ?></td><td><?= h($u['email']) ?></td><td><?= h($u['role']) ?></td><td><?= h($u['phone']) ?></td><td><?= badge($u['status']) ?></td>
<td><?php if($u['role']!=='ADMIN'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
<?php if($u['status']==='SUSPENDED'): ?><button name="status" value="ACTIVE" class="btn sm">Activate</button><?php else: ?><button name="status" value="SUSPENDED" class="btn sm alt" data-confirm="Suspend this account?">Suspend</button><?php endif; ?> <button name="do" value="reset_password" class="btn sm alt" data-confirm="Issue a new temporary password for this user? Their current password stops working.">Reset password</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php require '../includes/footer.php';
