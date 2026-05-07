<?php
mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
ini_set('display_errors', 0); // Hide raw errors from output to prevent JSON parsing issues
// Billing System - Database Configuration
// Update these values with your Hostinger MySQL credentials

define('DB_HOST', 'localhost');
define('DB_USER', 'u123456789_admin'); // Replace with your MySQL Username
define('DB_PASS', 'your_password');     // Replace with your MySQL Password
define('DB_NAME', 'u123456789_billing'); // Replace with your MySQL Database Name

function get_db_connection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        // Quick check for tables existence
        $check = $conn->query("SHOW TABLES LIKE 'settings'");
        if ($check->num_rows == 0) {
            // Tables missing, suggest importing database.sql
            die(json_encode(['ok' => false, 'error' => "Database connected, but tables are missing. Please import 'database.sql' in your phpMyAdmin."]));
        }
        return $conn;
    } catch (Exception $e) {
        die(json_encode(['ok' => false, 'error' => "Database Connection Failed. Check Hostinger credentials in php/config.php."]));
    }
}
?>
