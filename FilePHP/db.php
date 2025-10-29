<?php
$host = 'localhost';
$db = 'datapj';
$user = 'PLA';
$pass = '1234';
$charset = 'utf8mb4';

// สำหรับ mysqli
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "datapj";
?>