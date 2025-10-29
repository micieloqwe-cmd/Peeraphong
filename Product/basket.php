<?php
// ==========================
// Football Store - Insert Payment (Fixed JSON Version)
// ==========================

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');
header("Content-Type: application/json; charset=utf-8");
date_default_timezone_set("Asia/Bangkok");

// ✅ ฟังก์ชันส่ง JSON ที่ปลอดภัย
function send_json($data, $exit = true) {
    // ล้าง buffer ทั้งหมดเพื่อกันขยะหลุดออก
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($exit) exit;
}

// ✅ เชื่อมต่อฐานข้อมูลโดยตรง (ไม่ include ไฟล์อื่นเพื่อกัน echo)
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "datapj";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    send_json(["status" => "error", "message" => "เชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error]);
}
$conn->set_charset("utf8mb4");

// ✅ รับ JSON Input จาก client
$raw = file_get_contents("php://input");
if (!$raw) send_json(["status" => "error", "message" => "ไม่มีข้อมูลส่งมา"]);
$data = json_decode($raw, true);
if (!is_array($data)) send_json(["status" => "error", "message" => "ข้อมูล JSON ไม่ถูกต้อง"]);

// ถ้ามี items ให้คำนวณชื่อสินค้าและยอดรวมจากรายการ
$items = $data['items'] ?? null;
if (is_array($items) && count($items) > 0) {
    $names = [];
    $sum = 0.0;
    foreach ($items as $it) {
        // คาดว่า item มี at least { name, qty } และอาจมี price
        $n = trim($it['name'] ?? ($it['product_name'] ?? ''));
        if ($n !== '') $names[] = $n;
        $price = isset($it['price']) ? floatval($it['price']) : (isset($it['amount']) ? floatval($it['amount']) : 0);
        $qty = isset($it['qty']) ? intval($it['qty']) : (isset($it['quantity']) ? intval($it['quantity']) : 1);
        $sum += $price * max(1, $qty);
    }
    $product_name = implode(", ", array_unique($names));
    $amount = $sum;
} else {
    $product_name = trim($data["product_name"] ?? "");
    $amount = floatval($data["amount"] ?? 0);
}

$order_number   = trim($data["order_number"] ?? ("ORD-" . round(microtime(true)*1000)));
$email          = trim($data["email"] ?? "");
$firstname      = trim($data["firstname"] ?? "");
$lastname       = trim($data["lastname"] ?? "");
$payment_method = $data["payment_method"] ?? "bank_transfer";
$bank_account   = "964014011";
$stock_out      = intval($data["stock_out"] ?? (is_array($items) ? count($items) : 0));
$status         = "ยืนยันการสั่งซื้อและรอชำระเงิน";

if (!$email || !$product_name || !$amount) {
    send_json(["status" => "error", "message" => "ข้อมูลไม่ครบ"]);
}

// ✅ สร้างรหัสการชำระ
$payment_code = "PAY-" . str_pad(rand(0, 99999), 5, "0", STR_PAD_LEFT);

// ก่อนบันทึก ให้เริ่ม transaction เพื่อให้การตัดสต็อกและการบันทึก payment เป็น atomic
$conn->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);

try {
    // ✅ ตรวจสอบผู้ใช้ในระบบ
    $stmtUser = $conn->prepare("SELECT id FROM users WHERE email = ?");
    if (!$stmtUser) throw new Exception("เตรียมคำสั่ง SQL ล้มเหลว: " . $conn->error);
    $stmtUser->bind_param("s", $email);
    $stmtUser->execute();
    $resUser = $stmtUser->get_result();
    if ($resUser->num_rows === 0) {
        $stmtUser->close();
        throw new Exception("ไม่พบผู้ใช้ในระบบ");
    }
    $user_id = intval($resUser->fetch_assoc()['id']);
    $stmtUser->close();

    // ✅ ตรวจสอบ/สร้าง order
    $stmtOrder = $conn->prepare("SELECT order_number FROM orders WHERE order_number = ?");
    if (!$stmtOrder) throw new Exception("เตรียมคำสั่งตรวจ order ล้มเหลว: " . $conn->error);
    $stmtOrder->bind_param("s", $order_number);
    $stmtOrder->execute();
    $stmtOrder->store_result();

    if ($stmtOrder->num_rows === 0) {
        // ถ้าไม่มีใน orders ให้สร้าง และใช้สถานะเริ่มต้นเป็น "ชำระเงินสำเร็จรอการตรวจสอบ"
        $stmtInsertOrder = $conn->prepare("
            INSERT INTO orders (order_number, user_id, total_amount, status, created_at)
            VALUES (?, ?, ?, 'ชำระเงินสำเร็จรอการตรวจสอบ', NOW())
        ");
        if (!$stmtInsertOrder) throw new Exception("เตรียมคำสั่งเพิ่ม order ล้มเหลว: " . $conn->error);
        $stmtInsertOrder->bind_param("sid", $order_number, $user_id, $amount);
        if (!$stmtInsertOrder->execute()) {
            $stmtInsertOrder->close();
            throw new Exception("ไม่สามารถสร้างคำสั่งซื้อได้: " . $stmtInsertOrder->error);
        }
        $stmtInsertOrder->close();
    }
    $stmtOrder->close();

    // ✅ ถ้ามี items ให้ตรวจสต็อกและตัดสต็อกทีละรายการ (ล็อกแถวด้วย FOR UPDATE)
    if (is_array($items) && count($items) > 0) {
        foreach ($items as $it) {
            $prod_id = isset($it['id']) ? intval($it['id']) : 0;
            $qty = isset($it['qty']) ? intval($it['qty']) : (isset($it['quantity']) ? intval($it['quantity']) : 1);
            if ($qty < 1) $qty = 1;

            if ($prod_id > 0) {
                // ดึงสต็อกปัจจุบันพร้อมล็อกแถว
                $stmtStock = $conn->prepare("SELECT stock, product_name FROM products WHERE product_id = ? FOR UPDATE");
                if (!$stmtStock) throw new Exception("เตรียมคำสั่งตรวจสต็อกล้มเหลว: " . $conn->error);
                $stmtStock->bind_param("i", $prod_id);
                $stmtStock->execute();
                $resStock = $stmtStock->get_result();
                if ($resStock->num_rows === 0) {
                    $stmtStock->close();
                    throw new Exception("ไม่พบสินค้าที่มี id = {$prod_id}");
                }
                $row = $resStock->fetch_assoc();
                $currentStock = intval($row['stock']);
                $prodNameFromDB = $row['product_name'] ?? '';
                $stmtStock->close();

                if ($currentStock < $qty) {
                    throw new Exception("สินค้ารายการ \"{$prodNameFromDB}\" (id:{$prod_id}) สต็อกไม่พอ - คงเหลือ {$currentStock}, ต้องการ {$qty}");
                }

                // ลดสต็อก
                $stmtUpdate = $conn->prepare("UPDATE products SET stock = stock - ? WHERE product_id = ?");
                if (!$stmtUpdate) throw new Exception("เตรียมคำสั่งอัปเดตสต็อกล้มเหลว: " . $conn->error);
                $stmtUpdate->bind_param("ii", $qty, $prod_id);
                if (!$stmtUpdate->execute()) {
                    $stmtUpdate->close();
                    throw new Exception("อัปเดตสต็อกล้มเหลว: " . $stmtUpdate->error);
                }
                $stmtUpdate->close();
            } else {
                // ถ้าไม่มี product_id ให้พยายามใช้ product_name เพื่อตัดสต็อก (fallback)
                $name = trim($it['name'] ?? ($it['product_name'] ?? ''));
                if ($name === '') continue; // ข้ามรายการที่ไม่มีข้อมูล
                $stmtStock = $conn->prepare("SELECT product_id, stock FROM products WHERE product_name = ? FOR UPDATE");
                if (!$stmtStock) throw new Exception("เตรียมคำสั่งตรวจสต็อก (name) ล้มเหลว: " . $conn->error);
                $stmtStock->bind_param("s", $name);
                $stmtStock->execute();
                $resStock = $stmtStock->get_result();
                if ($resStock->num_rows === 0) {
                    $stmtStock->close();
                    throw new Exception("ไม่พบสินค้าที่ชื่อ \"{$name}\"");
                }
                $row = $resStock->fetch_assoc();
                $prod_id_db = intval($row['product_id']);
                $currentStock = intval($row['stock']);
                $stmtStock->close();

                if ($currentStock < $qty) {
                    throw new Exception("สินค้ารายการ \"{$name}\" สต็อกไม่พอ - คงเหลือ {$currentStock}, ต้องการ {$qty}");
                }

                $stmtUpdate = $conn->prepare("UPDATE products SET stock = stock - ? WHERE product_id = ?");
                if (!$stmtUpdate) throw new Exception("เตรียมคำสั่งอัปเดตสต็อกล้มเหลว: " . $conn->error);
                $stmtUpdate->bind_param("ii", $qty, $prod_id_db);
                if (!$stmtUpdate->execute()) {
                    $stmtUpdate->close();
                    throw new Exception("อัปเดตสต็อกล้มเหลว: " . $stmtUpdate->error);
                }
                $stmtUpdate->close();
            }
        } // end foreach items
    } // end if items

    // ✅ บันทึกข้อมูลการชำระเงิน
    $stmtPay = $conn->prepare("
        INSERT INTO payments 
        (payment_code, order_number, email, firstname, lastname, product_name, amount, payment_method, bank_account, status, stock_out)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmtPay) throw new Exception("เตรียมคำสั่งเพิ่ม payment ล้มเหลว: " . $conn->error);

    $stmtPay->bind_param(
        "ssssssdsssi",
        $payment_code,
        $order_number,
        $email,
        $firstname,
        $lastname,
        $product_name,
        $amount,
        $payment_method,
        $bank_account,
        $status,
        $stock_out
    );

    if (!$stmtPay->execute()) {
        throw new Exception("บันทึกข้อมูลไม่สำเร็จ: " . $stmtPay->error);
    }
    $stmtPay->close();

    // ✅ commit transaction เมื่อทุกอย่างผ่าน
    $conn->commit();

    // ปิดการเชื่อมต่อแล้วส่งผลลัพธ์
    $conn->close();
    send_json([
        "status" => "success",
        "payment_code" => $payment_code,
        "order_number" => $order_number,
        "message" => "✅ บันทึกข้อมูลการสั่งซื้อสำเร็จ และตัดสต็อกเรียบร้อย"
    ]);

} catch (Exception $ex) {
    // rollback และส่งข้อความ error เป็น JSON
    if ($conn->connect_errno === 0) {
        $conn->rollback();
    }
    // บันทึกข้อผิดพลาดลง log เพื่อดีบัก
    error_log("Order error: " . $ex->getMessage());
    // ปิด connection เงียบๆ
    if ($conn) $conn->close();
    send_json(["status" => "error", "message" => $ex->getMessage()]);
}
?>
