<?php
session_start();
require_once 'config.php';
if (empty($_SESSION['user_id'])) { http_response_code(403); exit; }
$uid = (int)$_SESSION['user_id'];
$id  = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    $conn->query("UPDATE notifications SET is_read=1 WHERE id=$id AND user_id=$uid");
}
echo 'ok';