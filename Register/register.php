<?php
// เชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "PLA";     // ค่าเริ่มต้น XAMPP
$password = "1234";         // ค่าเริ่มต้น XAMPP
$dbname = "datapj";     // ชื่อฐานข้อมูล

$conn = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ตรวจสอบว่ามีการส่งข้อมูลด้วย POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = $_POST['email'] ?? null;
    $password = $_POST['password'] ?? null;
    $confirm_password = $_POST['confirm_password'] ?? null;
    $firstname = $_POST['firstname'] ?? null;
    $lastname = $_POST['lastname'] ?? null;
    $address = $_POST['address'] ?? null;
    $street = $_POST['street'] ?? null;
    $alley = $_POST['alley'] ?? null;
    $subdistrict = $_POST['subdistrict'] ?? null;
    $province = $_POST['province'] ?? null;
    $district = $_POST['district'] ?? null;
    $phone = $_POST['phone'] ?? null;

    // ตรวจสอบว่ากรอก Email และ Password หรือไม่
    if (!$email || !$password || !$confirm_password) {
        die("กรุณากรอก Email และ Password ให้ครบถ้วน");
    }

    // ตรวจสอบรหัสผ่านตรงกัน
    if ($password !== $confirm_password) {
        die("รหัสผ่านไม่ตรงกัน");
    }

    // เข้ารหัสรหัสผ่าน
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // ตรวจสอบว่า email ซ้ำหรือไม่
    $checkEmail = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $checkEmail->bind_param("s", $email);
    $checkEmail->execute();
    $result = $checkEmail->get_result();

    if ($result->num_rows > 0) {
        die("อีเมลนี้ถูกใช้งานแล้ว กรุณาใช้อีเมลอื่น");
    }

    // Validation
    function isThaiText($str) {
        return preg_match('/^[ก-๙\s]+$/u', $str);
    }
    function isNoNumberText($str) {
        return !preg_match('/[0-9]/', $str);
    }
    function isHouseNumber($str) {
        return preg_match('/^[0-9\/]+$/', $str);
    }
    function isPhone($str) {
        return preg_match('/^0[0-9]{9}$/', $str);
    }

    // แจ้งเตือนแบบ popup ถ้ากรอกผิด
    if (!isThaiText($firstname)) {
        echo "<script>alert('ชื่อ ต้องเป็นตัวอักษรไทยเท่านั้น'); window.history.back();</script>";
        exit();
    }
    if (!isThaiText($lastname)) {
        echo "<script>alert('นามสกุล ต้องเป็นตัวอักษรไทยเท่านั้น'); window.history.back();</script>";
        exit();
    }
    if (!isHouseNumber($address)) {
        echo "<script>alert('บ้านเลขที่ ต้องเป็นตัวเลขหรือ / เท่านั้น'); window.history.back();</script>";
        exit();
    }
    if (!isNoNumberText($street)) {
        echo "<script>alert('ถนน ต้องไม่เป็นตัวเลข'); window.history.back();</script>";
        exit();
    }
    if (!isNoNumberText($alley)) {
        echo "<script>alert('ซอย ต้องไม่เป็นตัวเลข'); window.history.back();</script>";
        exit();
    }
    if (!isThaiText($district)) {
        echo "<script>alert('ตำบล ต้องเป็นตัวอักษรไทยเท่านั้น'); window.history.back();</script>";
        exit();
    }
    if (!isPhone($phone)) {
        echo "<script>alert('เบอร์โทรศัพท์ต้องเป็นตัวเลข 10 หลักขึ้นต้นด้วย 0'); window.history.back();</script>";
        exit();
    }

    // เตรียมคำสั่ง insert
    $stmt = $conn->prepare("INSERT INTO users (email, password, firstname, lastname, address, street, alley, subdistrict, province, district, phone) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssss", $email, $hashed_password, $firstname, $lastname, $address, $street, $alley, $subdistrict, $province, $district, $phone);

    if ($stmt->execute()) {
        // แสดง alert แล้ว redirect ไปหน้า login
        echo "<script>
            alert('สมัครสมาชิกสำเร็จ!');
            window.location.href = '../Login/login.html';
          </script>";
        exit();
    } else {
        echo "เกิดข้อผิดพลาด: " . $stmt->error;
    }
    $stmt->close();
}

$conn->close();
?>