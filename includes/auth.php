<?php
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS'])]);
session_start();
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/functions.php';
csrf_check();
// Enforce a 30-minute inactivity timeout for authenticated sessions.
if(!empty($_SESSION['uid'])) { if(isset($_SESSION['last_activity']) && time()-(int)$_SESSION['last_activity']>1800) { $_SESSION=[]; session_destroy(); session_start(); flash('Your session expired after 30 minutes of inactivity. Please log in again.','err'); redirect('login.php'); } $_SESSION['last_activity']=time(); }
function require_role($role){
  global $pdo;
  if(empty($_SESSION['uid'])) redirect('login.php');
  $s=$pdo->prepare("SELECT status,must_change_password FROM users WHERE user_id=?"); $s->execute([$_SESSION['uid']]); $ur=$s->fetch(); $st=$ur['status']??null; $mustChange=!empty($ur['must_change_password']);
  if($st!=='ACTIVE'){ session_destroy(); session_start(); flash('Your account is not active.','err'); redirect('login.php'); }
  if($mustChange && $_SESSION['role']===$role && basename($_SERVER['SCRIPT_NAME'])!=='change-password.php'){ flash('An administrator reset your password. Please choose a new password to continue.','err'); redirect(strtolower($_SESSION['role']).'/change-password.php'); }
  if($_SESSION['role']!==$role) redirect(strtolower($_SESSION['role'])==='healthcare'?'healthcare/dashboard.php':(strtolower($_SESSION['role'])==='admin'?'admin/dashboard.php':'donor/dashboard.php'));
}
function home_for($role){ return ['DONOR'=>'donor','HEALTHCARE'=>'healthcare','ADMIN'=>'admin'][$role].'/dashboard.php'; }
if(!empty($_SESSION['uid'])) campaign_auto_complete($pdo); // close campaigns whose session has ended
process_requests($pdo); // auto-close expired requests and widen the donor search when nobody accepts
