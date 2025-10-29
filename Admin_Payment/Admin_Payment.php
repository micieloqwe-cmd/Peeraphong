<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

if (function_exists('ob_get_level')) {
  while (ob_get_level()) {
    ob_end_clean();
  }
}

require_once '../FilePHP/db.php';

// ===== ฟังก์ชัน normalize สถานะ =====
function normalize_status($s) {
  $s = trim((string)$s);
  // เพิ่มสถานะ "เตรียมส่งสินค้าให้คุณแล้ว"
  $allowed = [
    'ชำระเงินสำเร็จรอการตรวจสอบ',
    'ชำระเงินสมบูรณ์รอจัดส่ง',
    'เตรียมส่งสินค้าให้คุณแล้ว',
    'ยกเลิกคำสั่งจากระบบ'
  ];
  return in_array($s, $allowed, true) ? $s : null;
}

// ===== 1) ดึงข้อมูลทั้งหมด =====
if (isset($_GET['get_all_payments'])) {
  try {
    // ใช้โครงสร้าง orders ตามฐานข้อมูลใหม่ (ENUM มีแค่ 3 ค่า)
    $sqlOrders = "
      SELECT
        o.order_id, o.order_number, o.user_id, o.total_amount, o.status,
        o.created_at, o.updated_at,
        u.email, u.firstname, u.lastname
      FROM orders o
      LEFT JOIN users u ON u.id = o.user_id
      ORDER BY o.created_at DESC, o.order_id DESC
    ";
    $resOrders = $conn->query($sqlOrders);
    $orders = [];
    $cancelCount = 0;
    $checkingCount = 0;
    while ($r = $resOrders->fetch_assoc()) {
      if ($r['status'] === 'ยกเลิกคำสั่งจากระบบ') $cancelCount++;
      // ตรวจสอบคำขึ้นต้นของ o.status
      if (strpos($r['status'], 'ชำระเงินสำเร็จรอการตรวจสอบ') === 0) $checkingCount++;
      $orders[] = $r;
    }

    // ดึง payments เฉพาะ field ที่จำเป็น
    $sqlPayments = "
      SELECT
        payment_code, order_number, product_name, stock_out, slip_image
      FROM payments
      ORDER BY created_at DESC, id DESC
    ";
    $resPayments = $conn->query($sqlPayments);
    $payments = [];
    while ($p = $resPayments->fetch_assoc()) {
      $payments[] = $p;
    }

    echo json_encode([
      'success' => true,
      'orders' => $orders,
      'payments' => $payments,
      'cancelCount' => $cancelCount,
      'checkingCount' => $checkingCount
    ], JSON_UNESCAPED_UNICODE);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
  }
  exit;
}

// ===== 2) อัปเดตสถานะ =====
if (isset($_POST['edit_status'])) {
  $payment_code = trim($_POST['payment_code'] ?? '');
  $new_status   = normalize_status($_POST['new_status'] ?? '');

  // debug เพิ่มเติม
  // error_log("payment_code: $payment_code, new_status: $new_status");

  if ($payment_code === '' || $new_status === null) {
    echo json_encode([
      'success' => false,
      'message' => 'ข้อมูลไม่ถูกต้อง: payment_code ว่าง หรือสถานะไม่อยู่ในระบบที่อนุญาต'
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $conn->begin_transaction();
  try {
    $q = $conn->prepare("SELECT order_number FROM payments WHERE payment_code = ? LIMIT 1");
    $q->bind_param("s", $payment_code);
    $q->execute();
    $res = $q->get_result();
    $row = $res->fetch_assoc();
    $q->close();

    if (!$row) throw new Exception('ไม่พบรายการชำระเงินตามรหัสที่ระบุ');

    $u = $conn->prepare("UPDATE payments SET status = ?, updated_at = NOW() WHERE payment_code = ? LIMIT 1");
    $u->bind_param("ss", $new_status, $payment_code);
    $u->execute();
    $u->close();

    if (!empty($row['order_number'])) {
      $uo = $conn->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE order_number = ? LIMIT 1");
      $uo->bind_param("ss", $new_status, $row['order_number']);
      $uo->execute();
      $uo->close();
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'อัปเดตสถานะสำเร็จ', 'status' => $new_status], JSON_UNESCAPED_UNICODE);
  } catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
  }
  exit;
}

// ===== 3) เพิ่มข้อมูลใหม่ =====
if (isset($_POST['add_payment'])) {
  $order_number = trim($_POST['order_number'] ?? '');
  $product_name = trim($_POST['product_name'] ?? '');
  $stock_out = trim($_POST['stock_out'] ?? '');
  $slip_image = trim($_POST['slip_image'] ?? '');
  $status = 'ชำระเงินสำเร็จรอการตรวจสอบ'; // ค่า default ENUM

  // เพิ่มข้อมูลใน payments
  $stmt = $conn->prepare("INSERT INTO payments (order_number, product_name, stock_out, slip_image, status) VALUES (?, ?, ?, ?, ?)");
  $stmt->bind_param("sssss", $order_number, $product_name, $stock_out, $slip_image, $status);
  $stmt->execute();
  $stmt->close();

  // ถ้าต้องการเพิ่ม/อัปเดต orders ให้ใช้ ENUM เดียวกัน
  // $stmt2 = $conn->prepare("UPDATE orders SET status = ? WHERE order_number = ? LIMIT 1");
  // $stmt2->bind_param("ss", $status, $order_number);
  // $stmt2->execute();
  // $stmt2->close();

  echo json_encode(['success' => true, 'message' => 'เพิ่มข้อมูลการชำระเงินสำเร็จ'], JSON_UNESCAPED_UNICODE);
  exit;
}

echo json_encode(['success' => false, 'message' => 'no route'], JSON_UNESCAPED_UNICODE);
