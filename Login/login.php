<?php
session_start();
include("../FilePHP/db.php");

// ตรวจสอบว่ามีการส่งข้อมูลมาหรือไม่
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // normalize email (trim + lowercase) ก่อนตรวจสอบ
    $email    = strtolower(trim($_POST['email']));
    $password = $_POST['password'];

    // ใช้ Prepared Statement เพื่อความปลอดภัย
    // ค้นหาไม่สนใจตัวพิมพ์ใหญ่/เล็ก
    $sql = "SELECT * FROM users WHERE LOWER(email) = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    // ตรวจสอบว่าพบอีเมลหรือไม่
    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        // ตรวจสอบรหัสผ่าน (กรณีที่เก็บแบบ hash)
        if (password_verify($password, $row['password'])) {
            // เก็บ session
            $_SESSION['user_id'] = $row['id'];
            // เก็บ email ตามฐานข้อมูล (ให้ตรงกับ stored case) หรือใช้ normalized
            $_SESSION['email']   = $row['email'];
            $_SESSION['name']    = $row['name'];

            // ไปหน้าแรก
            header("Location: ../index/index.html");
            exit();
        } else {
            echo "<script>alert('รหัสผ่านไม่ถูกต้อง'); window.history.back();</script>";
        }
    } else {
        echo "<script>alert('ไม่พบบัญชีผู้ใช้นี้'); window.history.back();</script>";
    }
}
?>
