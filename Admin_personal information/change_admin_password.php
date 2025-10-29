<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
include '../FilePHP/db.php';

$response = ["success" => false, "message" => "ไม่สามารถเปลี่ยนรหัสผ่านได้"];

if (isset($_SESSION['admin_id']) && $_SESSION['is_admin'] == 1) {
    $admin_id = $_SESSION['admin_id'];
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!$current_password || !$new_password || !$confirm_password) {
        $response["message"] = "กรุณากรอกข้อมูลให้ครบ";
    } else if ($new_password !== $confirm_password) {
        $response["message"] = "รหัสผ่านใหม่ไม่ตรงกัน";
    } else {
        // ตรวจสอบรหัสผ่านเดิม
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? AND is_admin = 1");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $stmt->bind_result($hash);
        $stmt->fetch();
        $stmt->close();

        if (!$hash || !password_verify($current_password, $hash)) {
            $response["message"] = "รหัสผ่านปัจจุบันไม่ถูกต้อง";
        } else {
            // อัปเดตรหัสผ่านใหม่
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ? AND is_admin = 1");
            $stmt->bind_param("si", $new_hash, $admin_id);
            if ($stmt->execute()) {
                $response = ["success" => true, "message" => "เปลี่ยนรหัสผ่านสำเร็จ"];
            } else {
                $response["message"] = "เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน";
            }
            $stmt->close();
        }
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
