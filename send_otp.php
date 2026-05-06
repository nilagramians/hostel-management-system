<?php
header('Content-Type: application/json');
session_start();
include('../config/db.php');

$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success'=>false,'message'=>'Invalid email.']); exit();
}
$stmt = $conn->prepare("SELECT user_id FROM users WHERE email=? AND is_active=1");
$stmt->bind_param("s",$email);$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) { echo json_encode(['success'=>false,'message'=>'Email not found in system.']); exit(); }

// Generate 6-digit OTP
$otp = str_pad(rand(0,999999),6,'0',STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));

// Store OTP
$conn->query("DELETE FROM otp_codes WHERE email='".addslashes($email)."'");
$stmt2 = $conn->prepare("INSERT INTO otp_codes (email,code,purpose,expires_at) VALUES (?,?,'reset',?)");
$stmt2->bind_param("sss",$email,$otp,$expires);$stmt2->execute();

// In production, send email. For demo: log to file
$logFile = '../assets/otp_log.txt';
file_put_contents($logFile, date('Y-m-d H:i:s')." | Email: $email | OTP: $otp\n", FILE_APPEND);

// If you have PHPMailer set up, use it here:
// require('../phpmailer/PHPMailer.php');
// ... send email code ...

$_SESSION['reset_email'] = $email;
echo json_encode(['success'=>true,'message'=>"OTP sent to $email",'otp_demo'=>$otp]); // Remove otp_demo in production
?>
