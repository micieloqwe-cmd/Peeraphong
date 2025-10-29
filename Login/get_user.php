<?php
session_start();
include("db.php");
// ตัวอย่างจำลองผู้ใช้ล็อกอิน
if(!isset($_SESSION['email'])){
    // เก็บเป็น lowercase เพื่อให้สอดคล้องกับการตรวจสอบไม่สนใจตัวพิมพ์
    $_SESSION['email'] = strtolower("user@example.com");
}

header('Content-Type: application/json');
echo json_encode(['email' => $_SESSION['email']]);
