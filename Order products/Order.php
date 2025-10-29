<?php


error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

// ป้องกัน output ที่ db.php อาจพิมพ์ออกมา (เช่น HTML/warning)
// โดยเก็บไว้ใน buffer แล้ว discard ทันที
ob_start();
include '../FilePHP/db.php';
ob_end_clean();

$conn = @new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'เชื่อมต่อฐานข้อมูลล้มเหลว: ' . $conn->connect_error]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$action = $input['action'] ?? null;

// ============================================================
// 🔹 สร้างคำสั่งซื้อใหม่ (create_order)
// ============================================================
if ($action === 'create_order') {
    session_start();
    $user_id = $_SESSION['user_id'] ?? 0;
    $total = floatval($input['total'] ?? 0);

    // สร้างหมายเลขคำสั่งซื้อที่น่าจะไม่ซ้ำ
    $order_number = 'ORD-' . round(microtime(true) * 1000) . mt_rand(100,999);
    // กำหนดเวลา expire (5 นาทีจากปัจจุบัน)
    $expire_seconds = 5 * 60; // <-- changed to 5 minutes
    $expire_at = date("Y-m-d H:i:s", time() + $expire_seconds);
    // ตั้งสถานะเริ่มต้นเป็น "ชำระเงินสำเร็จรอการตรวจสอบ"
    $status = 'ชำระเงินสำเร็จรอการตรวจสอบ';

    $stmt = $conn->prepare("
        INSERT INTO orders (order_number, user_id, total_amount, status, created_at, expire_at, updated_at)
        VALUES (?, ?, ?, ?, NOW(), ?, NOW())
    ");
    if (!$stmt) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'เตรียมคำสั่ง SQL ล้มเหลว: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("sidss", $order_number, $user_id, $total, $status, $expire_at);
    if (!$stmt->execute()) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถสร้างคำสั่งซื้อได้: ' . $stmt->error]);
        $stmt->close();
        exit;
    }
    $stmt->close();

    ob_clean();
    echo json_encode([
        'status'     => 'success',
        'order_number' => $order_number,
        'expire_at'  => $expire_at
    ]);
    exit;
}

// ============================================================
// 🔹 ตรวจสอบเวลานับถอยหลัง (get_timer)
// ============================================================
if ($action === 'get_timer') {
    $order_number = trim($input['order_number'] ?? '');
    if ($order_number === '') {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'ไม่มีหมายเลขคำสั่งซื้อ']);
        exit;
    }

    // อ่าน expire_at จาก orders
    $stmt = $conn->prepare("SELECT expire_at, status FROM orders WHERE order_number = ?");
    if (!$stmt) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'เตรียมคำสั่ง SQL ล้มเหลว: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("s", $order_number);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $expire_at = $row['expire_at'];
        $order_status = $row['status'] ?? '';
        if (!$expire_at) {
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'ไม่มี expire_at สำหรับคำสั่งซื้อ']);
            $stmt->close();
            exit;
        }
        $now_ts = time();
        $expire_ts = strtotime($expire_at);
        $diff = $expire_ts - $now_ts;

        if ($diff <= 0) {
            // หมดเวลา -> ยกเลิกคำสั่ง
            $stmt2 = $conn->prepare("UPDATE orders SET status='ยกเลิกคำสั่งจากระบบ' WHERE order_number = ?");
            if ($stmt2) {
                $stmt2->bind_param("s", $order_number);
                $stmt2->execute();
                $stmt2->close();
            }
            ob_clean();
            echo json_encode(['status' => 'expired', 'message' => 'หมดเวลา']);
            $stmt->close();
            exit;
        } else {
            ob_clean();
            echo json_encode(['status' => 'counting', 'seconds_left' => (int)$diff]);
            $stmt->close();
            exit;
        }
    } else {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบคำสั่งซื้อ']);
        $stmt->close();
        exit;
    }
}

// 🔹 แจ้งโอนเงิน
if ($action === 'notify_payment') {
    session_start();
    $user_id = $_SESSION['user_id'] ?? null;

    $order_id = intval($input['order_id'] ?? 0);
    $amount = floatval($input['amount'] ?? 0);
    $payment_method = $input['payment_method'] ?? 'bank_transfer';
    $bank_account = $input['bank_account'] ?? '';
    $slip_image = $input['slip_image'] ?? '';
    $status = 'ชำระเงินสำเร็จรอการตรวจสอบ';
    $admin_notes = '';

    // ดึงข้อมูลจาก users
    $email = '';
    $firstname = '';
    $lastname = '';
    if ($user_id) {
        $stmtUser = $conn->prepare("SELECT email, firstname, lastname FROM users WHERE id = ?");
        $stmtUser->bind_param("i", $user_id);
        $stmtUser->execute();
        $resultUser = $stmtUser->get_result();
        if ($rowUser = $resultUser->fetch_assoc()) {
            $email = $rowUser['email'];
            $firstname = $rowUser['firstname'];
            $lastname = $rowUser['lastname'];
        }
        $stmtUser->close();
    }

    // รับหมายเลขคำสั่งซื้อจาก frontend
    $order_number = $input['order_number'] ?? '';

    // ตรวจสอบว่า order_number มีอยู่ใน orders จริงหรือไม่
    $stmtOrder = $conn->prepare("SELECT COUNT(*) FROM orders WHERE order_number = ?");
    $stmtOrder->bind_param("s", $order_number);
    $stmtOrder->execute();
    $stmtOrder->bind_result($orderExists);
    $stmtOrder->fetch();
    $stmtOrder->close();

    // ถ้าไม่พบ order_number ให้สร้างใหม่และบันทึกลง orders ก่อน
    if ($orderExists == 0) {
        // สร้าง order_number ใหม่
        $new_order_number = $order_number ?: ('ORD-' . date('YmdHis') . mt_rand(1000,9999));
        $user_id_for_order = $user_id ?: 0;
        $total_amount = $amount ?: 0;
        // ตั้งสถานะเริ่มต้นเป็น "ชำระเงินสำเร็จรอการตรวจสอบ"
        $order_status = 'ชำระเงินสำเร็จรอการตรวจสอบ';
        $stmtNewOrder = $conn->prepare("INSERT INTO orders (order_number, user_id, total_amount, status, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
        $stmtNewOrder->bind_param("sids", $new_order_number, $user_id_for_order, $total_amount, $order_status);
        if ($stmtNewOrder->execute()) {
            $order_number = $new_order_number;
            $stmtNewOrder->close();
        } else {
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถสร้างคำสั่งซื้อใหม่ได้']);
            exit;
        }
    }

    // ดึงชื่อสินค้าและจำนวนจาก input (ควรส่งมาจาก frontend)
    $product_name = '';
    $stock_out = 0;
    $product_id = null;
    if (!empty($input['items']) && is_array($input['items'])) {
        foreach ($input['items'] as $item) {
            // ตรวจสอบชื่อสินค้าว่ามีใน products หรือไม่
            $stmtCheck = $conn->prepare("SELECT product_id FROM products WHERE product_name = ?");
            $stmtCheck->bind_param("s", $item['product_name']);
            $stmtCheck->execute();
            $stmtCheck->bind_result($pid);
            if ($stmtCheck->fetch()) {
                $product_name = $item['product_name'];
                $stock_out = intval($item['quantity'] ?? 0);
                $product_id = $pid;
                // ตัดสต๊อกทันทีเมื่อยืนยันคำสั่งซื้อ
                $stmtCheck->close();
                $stmtStock = $conn->prepare("UPDATE products SET stock = stock - ? WHERE product_id = ? AND stock >= ?");
                $stmtStock->bind_param("iii", $stock_out, $product_id, $stock_out);
                $stmtStock->execute();
                $stmtStock->close();
                break;
            }
            $stmtCheck->close();
        }
    }

    // เก็บไฟล์ใบเสร็จ
    $slip_filename = null;
    if ($slip_image && strpos($slip_image, 'base64,') !== false) {
        $base64 = explode('base64,', $slip_image)[1];
        $imgdata = base64_decode($base64);
        $slip_dir = '../Admin_Payment/slips_Admin/';
        if (!is_dir($slip_dir)) mkdir($slip_dir, 0777, true);
        $slip_filename = 'slip_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.png';
        file_put_contents($slip_dir . $slip_filename, $imgdata);
    }

    // สร้างรหัสชำระ
    $payment_code = 'PAY-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);

    // บันทึกลงตาราง payments
    $stmt = $conn->prepare("INSERT INTO payments 
        (payment_code, order_number, email, firstname, lastname, product_name, amount, payment_method, bank_account, slip_image, status, admin_notes, stock_out, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param("ssssssdsdsssi", 
        $payment_code,      // s
        $order_number,      // s
        $email,             // s
        $firstname,         // s
        $lastname,          // s
        $product_name,      // s
        $amount,            // d
        $payment_method,    // s
        $bank_account,      // s
        $slip_filename,     // s
        $status,            // s
        $admin_notes,       // s
        $stock_out          // i
    );

    if ($stmt->execute()) {
        ob_clean();
        echo json_encode([
            'status' => 'success',
            'message' => 'บันทึกข้อมูลสำเร็จ',
            'payment_code' => $payment_code,
            'order_number' => $order_number,
            'email' => $email,
            'fullname' => "$firstname $lastname",
            'product_name' => $product_name,
            'stock_out' => $stock_out,
            'payment_status' => $status
        ]);
    } else {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    $stmt->close();
    exit;
}

// 🔹 ตัดสต๊อกทันทีเมื่อกด "ดำเนินการสั่งซื้อ" (reserve)
if ($action === 'reserve_stock') {
    if (!empty($input['items']) && is_array($input['items'])) {
        foreach ($input['items'] as $item) {
            $product_name = $item['product_name'] ?? $item['name'] ?? '';
            $qty = intval($item['quantity'] ?? $item['qty'] ?? 0);
            if ($product_name && $qty > 0) {
                $stmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE product_name = ? AND stock >= ?");
                $stmt->bind_param("isi", $qty, $product_name, $qty);
                $stmt->execute();
                $stmt->close();
            }
        }
        ob_clean();
        echo json_encode(['status' => 'success', 'message' => 'ตัดสต๊อกสำเร็จ']);
        exit;
    }
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'ไม่มีรายการสินค้า']);
    exit;
}

// 🔹 ยกเลิกคำสั่งซื้อโดยผู้ซื้อหรือระบบ
if ($action === 'cancel_order' || $action === 'cancel_by_system') {
    // คืนสต๊อกสินค้าเมื่อยกเลิก
    if (!empty($input['items']) && is_array($input['items'])) {
        foreach ($input['items'] as $item) {
            $product_name = $item['product_name'] ?? $item['name'] ?? '';
            $qty = intval($item['quantity'] ?? $item['qty'] ?? 0);
            if ($product_name && $qty > 0) {
                $stmt = $conn->prepare("UPDATE products SET stock = stock + ? WHERE product_name = ?");
                $stmt->bind_param("is", $qty, $product_name);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    ob_clean();
    echo json_encode(['status' => 'success', 'message' => 'ยกเลิกคำสั่งซื้อแล้ว']);
    exit;
}

// 🔹 กรณี GET หรือไม่มี action: ส่งเบอร์บัญชีร้าน
ob_clean();
echo json_encode([
    'phone' => '0964014011'
]);
exit;
exit;
// 🔹 ยกเลิกคำสั่งซื้อโดยผู้ซื้อหรือระบบ
if ($action === 'cancel_order' || $action === 'cancel_by_system') {
    ob_clean();
    echo json_encode(['status' => 'success', 'message' => 'ยกเลิกคำสั่งซื้อแล้ว']);
    exit;
}

// 🔹 กรณี GET หรือไม่มี action: ส่งเบอร์บัญชีร้าน
ob_clean();
echo json_encode([
    'phone' => '0964014011'
]);
exit;
exit;
