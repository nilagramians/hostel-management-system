-- ============================================================
-- AI-POWERED SECURE SMART HOSTEL MANAGEMENT SYSTEM
-- ULTIMATE EDITION — Complete Database Schema
-- Run this in phpMyAdmin > SQL tab
-- ============================================================

CREATE DATABASE IF NOT EXISTS hostel_ultimate CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hostel_ultimate;

-- 1. USERS (Admin + Students)
CREATE TABLE IF NOT EXISTS users (
    user_id    INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(100) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    role       ENUM('admin','student') NOT NULL DEFAULT 'student',
    avatar     VARCHAR(255) DEFAULT NULL,
    is_active  TINYINT(1) DEFAULT 1,
    login_attempts INT DEFAULT 0,
    locked_until DATETIME DEFAULT NULL,
    last_login DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. PASSWORD RESET TOKENS
CREATE TABLE IF NOT EXISTS password_resets (
    reset_id   INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(100) NOT NULL,
    token      VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used       TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. ROOMS
CREATE TABLE IF NOT EXISTS rooms (
    room_id     INT AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(20) NOT NULL UNIQUE,
    floor       VARCHAR(10) DEFAULT '1',
    block       VARCHAR(10) DEFAULT 'A',
    room_type   ENUM('single','double','triple','quad') DEFAULT 'double',
    capacity    INT NOT NULL DEFAULT 2,
    occupied    INT NOT NULL DEFAULT 0,
    price       DECIMAL(10,2) DEFAULT 8000.00,
    status      ENUM('available','maintenance','closed') DEFAULT 'available',
    amenities   TEXT DEFAULT NULL,
    notes       TEXT DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_occupied CHECK (occupied <= capacity)
);

-- 4. STUDENTS
CREATE TABLE IF NOT EXISTS students (
    student_id   INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL UNIQUE,
    student_code VARCHAR(20) UNIQUE,
    cnic         VARCHAR(20) NOT NULL UNIQUE,
    phone        VARCHAR(20) NOT NULL,
    address      TEXT,
    room_id      INT DEFAULT NULL,
    profile_pic  VARCHAR(255) DEFAULT NULL,
    avatar_color VARCHAR(7) DEFAULT '#00D4FF',
    gender       ENUM('male','female','other') DEFAULT 'male',
    dob          DATE DEFAULT NULL,
    guardian     VARCHAR(100) DEFAULT NULL,
    guardian_phone VARCHAR(20) DEFAULT NULL,
    enrollment_date DATE DEFAULT NULL,
    status       ENUM('active','inactive','suspended') DEFAULT 'active',
    notes        TEXT DEFAULT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE SET NULL
);

-- 5. FEES
CREATE TABLE IF NOT EXISTS fees (
    fee_id      INT AUTO_INCREMENT PRIMARY KEY,
    student_id  INT NOT NULL,
    amount      DECIMAL(10,2) NOT NULL,
    discount    DECIMAL(10,2) DEFAULT 0.00,
    fine        DECIMAL(10,2) DEFAULT 0.00,
    status      ENUM('paid','unpaid','partial') DEFAULT 'unpaid',
    payment_method ENUM('cash','online','bank','wallet') DEFAULT 'cash',
    due_date    DATE NOT NULL,
    paid_date   DATE DEFAULT NULL,
    month_year  VARCHAR(10) DEFAULT NULL,
    invoice_no  VARCHAR(20) DEFAULT NULL,
    notes       TEXT DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
);

-- 6. ATTENDANCE
CREATE TABLE IF NOT EXISTS attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id    INT NOT NULL,
    date          DATE NOT NULL,
    status        ENUM('present','absent','leave','late') DEFAULT 'present',
    time_in       TIME DEFAULT NULL,
    method        ENUM('manual','face_recognition') DEFAULT 'manual',
    notes         TEXT DEFAULT NULL,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    UNIQUE KEY unique_att (student_id, date)
);

-- 7. NOTIFICATIONS
CREATE TABLE IF NOT EXISTS notifications (
    notif_id   INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    title      VARCHAR(255) NOT NULL,
    message    TEXT NOT NULL,
    type       ENUM('info','warning','danger','success') DEFAULT 'info',
    is_read    TINYINT(1) DEFAULT 0,
    link       VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- 8. COMPLAINTS
CREATE TABLE IF NOT EXISTS complaints (
    complaint_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    category     ENUM('maintenance','noise','food','security','other') DEFAULT 'other',
    subject      VARCHAR(255) NOT NULL,
    message      TEXT NOT NULL,
    priority     ENUM('low','medium','high','urgent') DEFAULT 'medium',
    admin_reply  TEXT DEFAULT NULL,
    status       ENUM('open','in_progress','resolved','closed') DEFAULT 'open',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- 9. LEAVE REQUESTS
CREATE TABLE IF NOT EXISTS leave_requests (
    leave_id   INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    leave_type ENUM('home','medical','emergency','other') DEFAULT 'home',
    from_date  DATE NOT NULL,
    to_date    DATE NOT NULL,
    reason     TEXT NOT NULL,
    status     ENUM('pending','approved','rejected') DEFAULT 'pending',
    admin_note TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
);

-- 10. ACTIVITY LOGS
CREATE TABLE IF NOT EXISTS activity_logs (
    log_id     INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT DEFAULT NULL,
    action     VARCHAR(255) NOT NULL,
    details    TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 11. THEMES / SETTINGS
CREATE TABLE IF NOT EXISTS settings (
    setting_id  INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL UNIQUE,
    theme       ENUM('dark','light','cyber','ocean','sunset') DEFAULT 'dark',
    accent_color VARCHAR(7) DEFAULT '#00D4FF',
    sidebar_mini TINYINT(1) DEFAULT 0,
    notifications_email TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- 12. OTP VERIFICATIONS
CREATE TABLE IF NOT EXISTS otp_codes (
    otp_id     INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(100) NOT NULL,
    code       VARCHAR(6) NOT NULL,
    purpose    ENUM('reset','verify','login') DEFAULT 'reset',
    expires_at DATETIME NOT NULL,
    used       TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 13. ROOM MAINTENANCE
CREATE TABLE IF NOT EXISTS maintenance (
    maint_id   INT AUTO_INCREMENT PRIMARY KEY,
    room_id    INT NOT NULL,
    issue      VARCHAR(255) NOT NULL,
    description TEXT,
    status     ENUM('pending','in_progress','completed') DEFAULT 'pending',
    reported_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE
);

-- ============================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================
CREATE INDEX idx_fees_student ON fees(student_id);
CREATE INDEX idx_fees_status ON fees(status);
CREATE INDEX idx_att_student ON attendance(student_id);
CREATE INDEX idx_att_date ON attendance(date);
CREATE INDEX idx_notif_user ON notifications(user_id, is_read);
CREATE INDEX idx_complaints_status ON complaints(status);

-- ============================================================
-- SAMPLE DATA
-- ============================================================

-- Admin account (password will be hashed via fix_passwords.php)
INSERT IGNORE INTO users (name, email, password, role) VALUES
('Hostel Admin', 'admin@hostel.com', 'admin123', 'admin');

-- Admin settings
INSERT IGNORE INTO settings (user_id, theme) VALUES (1, 'dark');

-- Sample rooms
INSERT IGNORE INTO rooms (room_number, floor, block, room_type, capacity, price) VALUES
('A-101', '1', 'A', 'single',  1, 12000.00),
('A-102', '1', 'A', 'double',  2, 8000.00),
('A-103', '1', 'A', 'double',  2, 8000.00),
('A-104', '1', 'A', 'triple',  3, 6000.00),
('B-101', '1', 'B', 'double',  2, 8500.00),
('B-102', '1', 'B', 'quad',    4, 5000.00),
('B-103', '2', 'B', 'single',  1, 12000.00),
('C-201', '2', 'C', 'double',  2, 9000.00);

-- ============================================================
-- After running SQL:
-- Visit: http://localhost/hostel-ultimate/fix_passwords.php
-- Delete that file after running!
-- ============================================================
