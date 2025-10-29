<?php
session_start();
ob_start(); // กัน output ที่ไม่ตั้งใจ
header('Content-Type: application/json');

// Database config
include("db.php");

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['status'=>'error','message'=>'ไม่สามารถเชื่อมต่อฐานข้อมูลได้: '.$e->getMessage()]);
    exit;
}

// ตรวจสอบผู้ใช้ล็อกอิน
$user_id = $_SESSION['user_id'] ?? null;
if(!$user_id){
    ob_end_clean();
    echo json_encode(['status'=>'error','message'=>'คุณยังไม่ได้ล็อกอิน']);
    exit;
}

// รับค่าจาก POST
$first_name = $_POST['first_name'] ?? '';
$last_name  = $_POST['last_name'] ?? '';
$phone      = $_POST['phone'] ?? '';
$house_number = $_POST['house_number'] ?? '';
$road       = $_POST['road'] ?? '';
$soi        = $_POST['soi'] ?? '';
$subdistrict = $_POST['subdistrict'] ?? '';
$province   = $_POST['province'] ?? '';
$district   = $_POST['district'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// ตรวจสอบรหัสผ่าน
if($new_password && $new_password !== $confirm_password){
    ob_end_clean();
    echo json_encode(['status'=>'error','message'=>'รหัสผ่านไม่ตรงกัน']);
    exit;
}

// เข้ารหัสรหัสผ่าน
$hashed_password = $new_password ? password_hash($new_password, PASSWORD_DEFAULT) : null;

// Prepare SQL
$sql = "UPDATE users SET 
    firstname=?, lastname=?, phone=?, address=?, street=?, alley=?, subdistrict=?, province=?, district=?" 
    .($hashed_password ? ", password=?" : "")
    ." WHERE id=?";
$stmt = $pdo->prepare($sql);

$params = [$first_name, $last_name, $phone, $house_number, $road, $soi, $subdistrict, $province, $district];
if($hashed_password) $params[] = $hashed_password;
$params[] = $user_id;

try{
    $stmt->execute($params);
    ob_end_clean();
    echo json_encode(['status'=>'success','message'=>'อัปเดตข้อมูลสำเร็จ','redirect'=>'index.html']);
} catch(Exception $e){
    ob_end_clean();
    echo json_encode(['status'=>'error','message'=>'เกิดข้อผิดพลาดในการบันทึกข้อมูล']);
}
