<?php
session_start();
header('Content-Type: application/json');

include '../FilePHP/db.php';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้']);
    exit;
}

// รับค่าจาก POST
$data = $_POST;

$first_name = $data['first_name'] ?? '';
$last_name  = $data['last_name'] ?? '';
$phone      = $data['phone'] ?? '';
$house_number = $data['house_number'] ?? '';
$road       = $data['road'] ?? '';
$soi        = $data['soi'] ?? '';
$subdistrict = $data['subdistrict'] ?? '';
$province   = $data['province'] ?? '';
$district   = $data['district'] ?? '';
$new_password = $data['new_password'] ?? '';
$confirm_password = $data['confirm_password'] ?? '';

// ตรวจสอบรหัสผ่าน
if($new_password && $new_password !== $confirm_password) {
    echo json_encode(['status' => 'error', 'message' => 'รหัสผ่านไม่ตรงกัน']);
    exit;
}

// เข้ารหัสรหัสผ่าน
$hashed_password = $new_password ? password_hash($new_password, PASSWORD_DEFAULT) : null;

// ตรวจสอบ session
$user_id = $_SESSION['user_id'] ?? null;
if(!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'คุณยังไม่ได้ล็อกอิน']);
    exit;
}

// Prepare SQL
$sql = "UPDATE users SET
    firstname = ?, lastname = ?, phone = ?, address = ?, street = ?, alley = ?, subdistrict = ?, province = ?, district = ?"
    .($hashed_password ? ", password = ?" : "")
    ." WHERE id = ?";

$stmt = $pdo->prepare($sql);

$params = [$first_name, $last_name, $phone, $house_number, $road, $soi, $subdistrict, $province, $district];
if($hashed_password) $params[] = $hashed_password;
$params[] = $user_id;

try {
    $stmt->execute($params);
    // ส่ง status พร้อม url ให้ redirect
    echo json_encode(['status' => 'success', 'message' => 'อัปเดตข้อมูลสำเร็จ', 'redirect' => 'index.html']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล']);
}
