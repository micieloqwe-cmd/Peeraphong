<?php
session_start();
include '../FilePHP/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบก่อน']);
    exit;
}

$user_id = $_SESSION['user_id'];
$sql = "SELECT firstname, lastname, phone, address, street, alley, subdistrict, province, district FROM users WHERE id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลผู้ใช้']);
    exit;
}

$user = $result->fetch_assoc();

echo json_encode([
    'success' => true,
    'data' => [
        'first_name' => $user['firstname'],
        'last_name' => $user['lastname'],
        'phone' => $user['phone'],
        'house_number' => $user['address'],
        'road' => $user['street'],
        'soi' => $user['alley'],
        'subdistrict' => $user['subdistrict'],
        'province' => $user['province'],
        'district' => $user['district']
    ]
]);

$stmt->close();
$conn->close();
?>