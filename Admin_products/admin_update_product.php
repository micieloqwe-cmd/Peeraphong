<?php
header('Content-Type: application/json');
include '../FilePHP/db.php';

$id = $_POST['product_id'] ?? '';
$name = $_POST['product_name'] ?? '';
$brand = $_POST['brand'] ?? '';
$category_id = $_POST['category_id'] ?? '';
$price = $_POST['price'] ?? 0;
$stock = $_POST['stock'] ?? 0;
$image = '';

if (isset($_FILES['image']) && $_FILES['image']['tmp_name']) {
    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $filename = 'uploads/products/' . uniqid('prod_', true) . '.' . $ext;
    if (move_uploaded_file($_FILES['image']['tmp_name'], "../../" . $filename)) {
        $image = $filename;
    }
}

// ไม่ต้องเช็ค constraint ผิดใน product_categories ให้ update ได้ปกติ
if ($image) {
    $sql = "UPDATE products SET product_name=?, brand=?, category_id=?, price=?, stock=?, image=? WHERE product_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssiidsi", $name, $brand, $category_id, $price, $stock, $image, $id);
} else {
    $sql = "UPDATE products SET product_name=?, brand=?, category_id=?, price=?, stock=? WHERE product_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssiidi", $name, $brand, $category_id, $price, $stock, $id);
}
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'แก้ไขสินค้าสำเร็จ']);
} else {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด']);
}

