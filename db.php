<?php
$host = 'localhost';
$db   = 'education'; // your database name
$user = 'root';      // your DB username
$pass = '';          // your DB password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options); // THIS LINE CREATES $pdo
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}