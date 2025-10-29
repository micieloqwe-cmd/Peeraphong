<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
session_destroy();

echo json_encode(["success" => true, "message" => "ออกจากระบบเรียบร้อยแล้ว"], JSON_UNESCAPED_UNICODE);
exit;
