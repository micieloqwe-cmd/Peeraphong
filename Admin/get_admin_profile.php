<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
include '../FilePHP/db.php';

$response = ["success" => false];

if (isset($_SESSION['admin_id']) && $_SESSION['is_admin'] == 1) {
    $sql = "SELECT firstname, lastname, email FROM users WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_SESSION['admin_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $response = [
            "success" => true,
            "firstname" => $row['firstname'],
            "lastname" => $row['lastname'],
            "email" => $row['email']
        ];
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
