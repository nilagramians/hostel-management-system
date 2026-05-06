<?php
header('Content-Type: application/json');
include('../config/db.php');
$token    = trim($_POST['token'] ?? '');
$password = trim($_POST['password'] ?? '');
if (strlen($password) < 8) { echo json_encode(['success'=>false,'message'=>'Password too short.']); exit(); }
$now = date('Y-m-d H:i:s');
$stmt = $conn->prepare("SELECT * FROM password_resets WHERE token=? AND expires_at>? AND used=0");
$stmt->bind_param("ss",$token,$now);$stmt->execute();
$rec = $stmt->get_result()->fetch_assoc();
if (!$rec) { echo json_encode(['success'=>false,'message'=>'Reset link expired. Request a new one.']); exit(); }
$hashed = password_hash($password, PASSWORD_BCRYPT);
$stmt2 = $conn->prepare("UPDATE users SET password=?, login_attempts=0, locked_until=NULL WHERE email=?");
$stmt2->bind_param("ss",$hashed,$rec['email']);$stmt2->execute();
$conn->query("UPDATE password_resets SET used=1 WHERE token='".addslashes($token)."'");
echo json_encode(['success'=>true]);
?>
