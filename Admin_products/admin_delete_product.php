<?php
header('Content-Type: application/json');
include '../FilePHP/db.php';

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Product ID ไม่ถูกต้อง']);
    exit;
}

try {
    $conn->begin_transaction();

    // ดึงชื่อสินค้า (product_name) และ image (ถ้ามี) ก่อนลบ
    $stmtSel = $conn->prepare("SELECT product_name, image FROM products WHERE product_id = ?");
    if (!$stmtSel) throw new Exception("เตรียมคำสั่ง SELECT ล้มเหลว: " . $conn->error);
    $stmtSel->bind_param("i", $id);
    $stmtSel->execute();
    $resSel = $stmtSel->get_result();
    if ($resSel->num_rows === 0) {
        $stmtSel->close();
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'ไม่พบสินค้าที่ต้องการลบ']);
        exit;
    }
    $row = $resSel->fetch_assoc();
    $prodName = $row['product_name'];
    $prodImage = $row['image'];
    $stmtSel->close();

    // ทำให้ payments ที่อ้างอิงชื่อสินค้าชี้เป็น NULL ก่อน (หลีกเลี่ยง FK constraint)
    if (!empty($prodName)) {
        $stmtUpd = $conn->prepare("UPDATE payments SET product_name = NULL WHERE product_name = ?");
        if (!$stmtUpd) throw new Exception("เตรียมคำสั่งอัปเดต payments ล้มเหลว: " . $conn->error);
        $stmtUpd->bind_param("s", $prodName);
        if (!$stmtUpd->execute()) {
            $err = $stmtUpd->error;
            $stmtUpd->close();
            throw new Exception("อัปเดต payments ล้มเหลว: " . $err);
        }
        $stmtUpd->close();
    }

    // ลบสินค้าจากตาราง products
    $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
    if (!$stmt) throw new Exception("เตรียมคำสั่งลบสินค้าล้มเหลว: " . $conn->error);
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new Exception("ลบสินค้าล้มเหลว: " . $err);
    }

    if ($stmt->affected_rows > 0) {
        $stmt->close();
        $conn->commit();

        // ลบไฟล์รูป (พยายามอย่างปลอดภัย — ไม่บังคับ)
        if (!empty($prodImage)) {
            // prodImage อาจเป็น path เช่น 'uploads/...' หรือ 'admin_products/uploads/...' -> normalize
            $candidate = $prodImage;
            // ถ้า path ไม่เป็น absolute ให้แปลงเป็น absolute ตาม project root
            if (!preg_match('#^(?:/|[a-zA-Z]:\\\\)#', $candidate)) {
                $candidate = __DIR__ . "/../" . ltrim($candidate, "/\\");
            }
            if (file_exists($candidate) && is_file($candidate)) {
                @unlink($candidate);
            }
        }

        echo json_encode(['success' => true, 'message' => 'ลบสินค้าสำเร็จ']);
        exit;
    } else {
        $stmt->close();
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'ไม่พบสินค้าที่ต้องการลบ']);
        exit;
    }
} catch (Exception $e) {
    if ($conn && $conn->connect_errno === 0) {
        @$conn->rollback();
    }
    $msg = $e->getMessage();
    if (stripos($msg, 'foreign') !== false || stripos($msg, 'constraint') !== false) {
        $msg = "ไม่สามารถลบสินค้าได้ เนื่องจากมีข้อมูลอ้างอิงในตารางอื่น โปรดลบข้อมูลอ้างอิงก่อน";
    }
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}
