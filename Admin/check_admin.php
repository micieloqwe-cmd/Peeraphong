<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (isset($_SESSION['admin_id']) && $_SESSION['is_admin'] == 1) {
    echo json_encode(["loggedIn" => true]);
} else {
    echo json_encode(["loggedIn" => false]);
}
exit;
