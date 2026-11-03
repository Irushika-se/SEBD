<?php session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS'])]); session_start(); require_once __DIR__.'/config/database.php'; require_once __DIR__.'/includes/functions.php';
if(!empty($_SESSION['uid'])) audit($pdo,$_SESSION['uid'],'LOGOUT','users',$_SESSION['uid'],'User logged out.');
$_SESSION=[]; session_destroy(); session_start(); flash('You have been logged out.'); redirect('login.php');
