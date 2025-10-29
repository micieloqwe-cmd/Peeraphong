<?php
header('Content-Type: application/json');

include '../FilePHP/db.php';

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['error' => 'Connection failed: ' . $conn->connect_error]);
    exit;
}

// ตั้งค่า charset เป็น utf8 เพื่อรองรับภาษาไทย
$conn->set_charset("utf8");

// ดึงข้อมูลประเภทอุปกรณ์ฟุตบอล (มี image)
$categories = [];
$cat_sql = "SELECT * FROM product_categories";
$cat_result = $conn->query($cat_sql);
if ($cat_result && $cat_result->num_rows > 0) {
    while($row = $cat_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// ดึงข้อมูลสินค้า
$products = [];
$sql = "SELECT * FROM products";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

$conn->close();

// ส่งออกข้อมูลทั้งสองแบบ
echo json_encode([
    'categories' => $categories,
    'products' => $products
]);
