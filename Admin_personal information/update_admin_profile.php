<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
include '../FilePHP/db.php';

$response = ["success" => false, "message" => "ไม่สามารถอัปเดตข้อมูลได้"];

if (isset($_SESSION['admin_id']) && $_SESSION['is_admin'] == 1) {
    $firstname = $_POST['firstname'] ?? '';
    $lastname  = $_POST['lastname'] ?? '';
    $email     = $_POST['email'] ?? '';

    if (!empty($firstname) && !empty($lastname) && !empty($email)) {
        $sql = "UPDATE users SET firstname = ?, lastname = ?, email = ? WHERE id = ? AND is_admin = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $firstname, $lastname, $email, $_SESSION['admin_id']);
        if ($stmt->execute()) {
            $response = ["success" => true, "message" => "อัปเดตข้อมูลสำเร็จ"];
        }
    } else {
        $response["message"] = "กรุณากรอกข้อมูลให้ครบ";
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
