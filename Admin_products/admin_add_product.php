<?php
header('Content-Type: application/json');
include '../FilePHP/db.php';

$name = $_POST['product_name'] ?? '';
$brand = $_POST['brand'] ?? '';
$category_id = intval($_POST['category_id'] ?? 0);
$price = floatval($_POST['price'] ?? 0);
$stock = intval($_POST['stock'] ?? 0);
$branch = $_POST['branch'] ?? 'สกลนคร'; // default branch, adjust as needed
$image = '';

// ตรวจสอบว่า category_id มีอยู่จริงในตาราง product_categories หรือไม่
$cat_check = $conn->prepare("SELECT category_id FROM product_categories WHERE category_id = ?");
$cat_check->bind_param("i", $category_id);
$cat_check->execute();
$cat_check->store_result();
if ($cat_check->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'หมวดหมู่สินค้าไม่ถูกต้อง']);
    exit;
}
$cat_check->close();

// อัปโหลดรูปภาพ (ใช้ uploads/products/)
if (isset($_FILES['image']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $filename = 'uploads/products/' . uniqid('prod_', true) . '.' . $ext;
    $target = dirname(__DIR__) . '/admin_products/' . $filename; // อัปโหลดไปที่ admin_products/uploads/products/
    // สร้างโฟลเดอร์ถ้ายังไม่มี
    $dir = dirname($target);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
        $image = 'admin_products/' . $filename; // เก็บ path แบบ relative จาก root
    }
}

// ตรวจสอบค่า category_id, price, stock ไม่ให้เกินขอบเขต INT
$maxInt = 2147483647;
if ($category_id < 0) $category_id = 0;
if ($stock < 0) $stock = 0;
if ($price < 0) $price = 0;
if ($category_id > $maxInt) $category_id = $maxInt;
if ($stock > $maxInt) $stock = $maxInt;

// เพิ่มสินค้า (ระบุ branch และ image)
$sql = "INSERT INTO products (product_name, brand, price, stock, branch, category_id, image) VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssdisis", $name, $brand, $price, $stock, $branch, $category_id, $image);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'เพิ่มสินค้าสำเร็จ', 'closeModal' => true, 'image' => $image]);
} else {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $stmt->error]);
}

