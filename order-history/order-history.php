<?php
header('Content-Type: application/json; charset=utf-8');
include '../FilePHP/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบก่อน']);
    exit;
}

$user_id = $_SESSION['user_id'];

// 🔹 ดึงข้อมูลอีเมลของผู้ใช้เพื่อตรงกับในตาราง payments
$userQuery = $conn->prepare("SELECT email, firstname, lastname FROM users WHERE id = ?");
$userQuery->bind_param("i", $user_id);
$userQuery->execute();
$userResult = $userQuery->get_result();
$user = $userResult->fetch_assoc();
$email = $user['email'] ?? null;

// 🔹 ดึงข้อมูลจากตาราง payments + orders
$sql = "
    SELECT 
        p.payment_code,
        p.order_number,
        p.product_name,
        p.amount,
        p.status,
        p.slip_image,
        p.created_at,
        p.stock_out,
        o.status AS order_status
    FROM payments p
    LEFT JOIN orders o ON p.order_number = o.order_number
    WHERE p.email = ?
    ORDER BY p.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = [
        'payment_code' => $row['payment_code'],
        'order_number' => $row['order_number'],
        'product_name' => $row['product_name'],
        'amount' => $row['amount'],
        'status' => $row['order_status'], // ใช้ status จาก orders
        'order_status' => $row['order_status'],
        'slip_image' => $row['slip_image'],
        'stock_out' => $row['stock_out'],
        'created_at' => date('Y-m-d H:i', strtotime($row['created_at']))
    ];
}

echo json_encode(['success' => true, 'orders' => $orders], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$conn->close();
?>
