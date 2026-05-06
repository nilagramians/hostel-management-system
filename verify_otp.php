<?php
// verify_otp.php
header('Content-Type: application/json');
session_start();
include('../config/db.php');
$email = trim($_POST['email'] ?? '');
$otp   = trim($_POST['otp'] ?? '');
$now   = date('Y-m-d H:i:s');
$stmt  = $conn->prepare("SELECT * FROM otp_codes WHERE email=? AND code=? AND purpose='reset' AND expires_at>? AND used=0 ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("sss",$email,$otp,$now);$stmt->execute();
$rec = $stmt->get_result()->fetch_assoc();
if (!$rec) { echo json_encode(['success'=>false,'message'=>'Invalid or expired OTP.']); exit(); }
// Mark used and generate reset token
$token = bin2hex(random_bytes(32));
$tokenExpiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
$conn->query("UPDATE otp_codes SET used=1 WHERE otp_id={$rec['otp_id']}");
$stmt2 = $conn->prepare("INSERT INTO password_resets (email,token,expires_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE token=VALUES(token),expires_at=VALUES(expires_at),used=0");
$stmt2->bind_param("sss",$email,$token,$tokenExpiry);$stmt2->execute();
echo json_encode(['success'=>true,'token'=>$token]);
?>
