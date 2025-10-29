<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
include '../FilePHP/db.php';

// ค่าเริ่มต้น
$response = [
    'loggedIn' => false,
    'user_id' => null,
    'email' => null,
    'firstname' => null,
    'lastname' => null,
    'phone' => null,
    'address' => null,
    'street' => null,
    'alley' => null,
    'subdistrict' => null,
    'district' => null,
    'province' => null,
    'created_at' => null
];

// ✅ ตรวจสอบว่ามี session หรือไม่
if (isset($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);

    // ✅ ดึงข้อมูลจากตาราง users
    $stmt = $conn->prepare("
        SELECT 
            id,
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
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        $response = [
            'loggedIn'    => true,
            'user_id'     => $user['id'],
            'email'       => $user['email'],
            'firstname'   => $user['firstname'],
            'lastname'    => $user['lastname'],
            'phone'       => $user['phone'],
            'address'     => $user['address'],
            'street'      => $user['street'],
            'alley'       => $user['alley'],
            'subdistrict' => $user['subdistrict'],
            'district'    => $user['district'],
            'province'    => $user['province'],
            'created_at'  => $user['created_at']
        ];
    }

    $stmt->close();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
$conn->close();
?>
