<?php
session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit(); }
include('../config/db.php');
$uid = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
if ($action === 'theme') {
    $theme = in_array($_POST['theme'], ['dark','light','cyber','ocean','sunset']) ? $_POST['theme'] : 'dark';
    $conn->query("INSERT INTO settings (user_id,theme) VALUES ($uid,'$theme') ON DUPLICATE KEY UPDATE theme='$theme'");
    echo json_encode(['success'=>true]);
} elseif ($action === 'accent') {
    $color = preg_match('/^#[0-9A-Fa-f]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#00D4FF';
    $conn->query("INSERT INTO settings (user_id,accent_color) VALUES ($uid,'$color') ON DUPLICATE KEY UPDATE accent_color='$color'");
    echo json_encode(['success'=>true]);
}
?>
