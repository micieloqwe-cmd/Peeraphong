<?php
// 1. เปิด error reporting เพื่อดีบัก (แก้ตอน dev เท่านั้น)
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
ob_start();
ob_clean();

header('Content-Type: application/json');

// 3. เช็ค login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'กรุณาสมัครระบบ']);
    exit;
}

include '../FilePHP/db.php'; // เชื่อมฐานข้อมูล

$user_id = $_SESSION['user_id'];
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$house_number = trim($_POST['house_number'] ?? '');
$road = trim($_POST['road'] ?? '');
$soi = trim($_POST['soi'] ?? '');
$subdistrict = trim($_POST['subdistrict'] ?? '');
$province = trim($_POST['province'] ?? '');
$district = trim($_POST['district'] ?? '');
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// ตรวจสอบรหัสผ่านใหม่
if ($new_password !== $confirm_password) {
    echo json_encode(['success' => false, 'message' => 'รหัสผ่านใหม่ไม่ตรงกัน']);
    exit;
}

// ตรวจสอบข้อมูลจำเป็น
if ($first_name === '' || $last_name === '' || $phone === '' || $house_number === '' || $province === '' || $district === '') {
    echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบ']);
    exit;
}

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'เชื่อมต่อฐานข้อมูลล้มเหลว']);
    exit;
}

// สร้าง SQL query แบบ dynamic
$sql = "UPDATE users SET firstname=?, lastname=?, phone=?, address=?, street=?, alley=?, subdistrict=?, province=?, district=?";
$params = [$first_name, $last_name, $phone, $house_number, $road, $soi, $subdistrict, $province, $district];
$types = "sssssssss";

if ($new_password !== '') {
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $sql .= ", password=?";
    $params[] = $hashed_password;
    $types .= "s";
}

$sql .= " WHERE id=?";
$params[] = $user_id;
$types .= "i";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare statement ล้มเหลว: ' . $conn->error]);
    exit;
}

$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'อัปเดตข้อมูลเรียบร้อย']);
} else {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล: ' . $stmt->error]);
}

$stmt->close();
$conn->close();

exit;

