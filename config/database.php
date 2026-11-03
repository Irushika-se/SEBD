<?php
date_default_timezone_set('Asia/Colombo');
$DB_HOST='127.0.0.1'; $DB_PORT=3306; $DB_NAME='sebd'; $DB_USER='root'; $DB_PASS='';
try {
  $pdo = new PDO("mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false]);
  // Make MySQL NOW() agree with PHP's clock (expiry, escalation and 'needed by' checks compare both).
  $pdo->exec("SET time_zone='".date('P')."'");
} catch (PDOException $e) { die('Database connection failed. Check config/database.php'); }
