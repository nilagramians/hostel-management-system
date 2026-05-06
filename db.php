<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hostel_ultimate');
define('SITE_URL', 'http://localhost/hostel-ultimate');
define('SITE_NAME', 'Hostel Pro Ultimate');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("<div style='font-family:Segoe UI;padding:40px;background:#050a14;color:#ff4d6d;min-height:100vh'>
        <h2 style='font-size:24px;margin-bottom:12px'>⚠ Database Connection Failed</h2>
        <p style='color:#8fafc8;margin-bottom:8px'>Make sure XAMPP MySQL is running and you have imported <strong>database.sql</strong></p>
        <p style='color:#4a6a8a;font-size:13px'>".$conn->connect_error."</p>
        <a href='../install/' style='color:#00d4ff;margin-top:20px;display:inline-block'>→ Run Setup Wizard</a>
    </div>");
}
$conn->set_charset("utf8mb4");

function logActivity($conn, $user_id, $action, $details = '') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    if ($stmt) { $stmt->bind_param("isss", $user_id, $action, $details, $ip); $stmt->execute(); }
}
function getUserTheme($conn, $user_id) {
    $r = $conn->query("SELECT theme, accent_color FROM settings WHERE user_id=".intval($user_id));
    if ($r && $r->num_rows > 0) return $r->fetch_assoc();
    return ['theme' => 'dark', 'accent_color' => '#00D4FF'];
}
function generateStudentCode() {
    return 'STU-' . date('Y') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
}
function generateInvoiceNo() {
    return 'INV-' . date('Ym') . '-' . rand(1000, 9999);
}
function generateAvatar($name, $color = '#00D4FF') {
    $initials = '';
    foreach (explode(' ', trim($name)) as $word) {
        if ($word) $initials .= strtoupper($word[0]);
        if (strlen($initials) >= 2) break;
    }
    return ['initials' => $initials ?: '?', 'color' => $color];
}
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
function validateCNIC($cnic) {
    return preg_match('/^\d{5}-\d{7}-\d{1}$/', $cnic);
}
function validatePhone($phone) {
    return preg_match('/^(03\d{9}|0\d{3}-\d{7})$/', $phone);
}
?>
