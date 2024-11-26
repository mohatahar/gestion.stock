<?php
// Database configuration
$host = 'localhost';     // Database host
$dbname = 'gestion_stock';  // Database name
$username = 'root';      // Database username
$password = '';          // Database password
$charset = 'utf8mb4';    // Character set

// DSN (Data Source Name)
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

// PDO options
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Create a PDO instance
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // Log error and prevent detailed error exposure
    error_log("Database Connection Error: " . $e->getMessage());
    die("Une erreur de connexion s'est produite.");
}
?>