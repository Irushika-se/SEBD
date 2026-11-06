<?php
date_default_timezone_set('Asia/Colombo');

$DB_HOST = '127.0.0.1';
$DB_PORT = 3306;
$DB_NAME = 'your_database_name';
$DB_USER = 'your_database_username';
$DB_PASS = 'your_database_password';

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

} catch (PDOException $e) {
    die('Database connection failed. Check database configuration.');
}
