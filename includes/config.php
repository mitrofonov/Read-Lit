<?php
// Настройки БД
$host = 'localhost'; 
$dbname = 'sssscc0c_root'; 
$user = 'sssscc0c_root';
$pass = '123456Qq'; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
}
?>