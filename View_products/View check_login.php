<?php
session_start();  // ✅ เรียกครั้งเดียวเท่านั้น

// Debug mode (ปิดเมื่อขึ้น production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// ถ้าไม่ได้ล็อกอิน
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['loggedIn' => false]);
    exit;
}

// ✅ ตรวจสอบว่า path ของ db.php ถูกต้อง
include '../FilePHP/db.php';

$id = $_SESSION['user_id'];

// ใช้ชื่อฟิลด์ให้ตรงกับตารางจริง
$stmt = $conn->prepare("
    SELECT 
        firstname, 
        lastname, 
        email, 
        phone, 
        address, 
        street, 
        alley, 
        subdistrict, 
        district, 
        province, 
        created_at 
    FROM users 
    WHERE id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user) {
    echo json_encode([
        'loggedIn'   => true,
        'firstname'  => $user['firstname'],
        'lastname'   => $user['lastname'],
        'email'      => $user['email'],
        'phone'      => $user['phone'],
        'address'    => $user['address'],
        'street'     => $user['street'],
        'alley'      => $user['alley'],
        'subdistrict'=> $user['subdistrict'],
        'district'   => $user['district'],
        'province'   => $user['province'],
        'created_at' => $user['created_at']
        // ถ้ามี profile_image ต้องเช็คว่าฟิลด์นี้มีจริงในตารางหรือไม่
    ]);
} else {
    echo json_encode(['loggedIn' => false]);
}

$stmt->close();
$conn->close();
