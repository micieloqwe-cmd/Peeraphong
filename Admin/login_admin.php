<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
include '../FilePHP/db.php';

$email = $_POST['admin_email'] ?? '';
$password = $_POST['admin_password'] ?? '';

$response = ["success" => false, "message" => "อีเมล์หรือรหัสผ่านไม่ถูกต้อง"];

if ($email && $password) {
    // ตรวจสอบว่ามีผู้ดูแลระบบที่ email ตรงและ is_admin=1
    $sql = "SELECT * FROM users WHERE email = ? AND is_admin = 1 LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        // ตรวจสอบรหัสผ่าน (password_hash)
        if (password_verify($password, $user['password'])) {
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['is_admin'] = 1;
            $response = ["success" => true, "message" => "เข้าสู่ระบบสำเร็จ"];
        } else {
            $response["message"] = "รหัสผ่านไม่ถูกต้อง";
        }
    } else {
        $response["message"] = "ไม่พบผู้ใช้หรือไม่ใช่ผู้ดูแลระบบ";
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
