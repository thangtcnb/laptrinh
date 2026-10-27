<?php
// Mail System Configuration & Defaults for UFM Quảng Ngãi Task Assignment System

if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', 'smtp.gmail.com');
}

if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', 587); // 587 for TLS, 465 for SSL
}

if (!defined('SMTP_ENCRYPTION')) {
    define('SMTP_ENCRYPTION', 'tls'); // 'tls' or 'ssl'
}

if (!defined('SMTP_USER')) {
    define('SMTP_USER', 'no-reply@ufm.edu.vn');
}

if (!defined('SMTP_PASS')) {
    define('SMTP_PASS', '');
}

if (!defined('SMTP_FROM')) {
    define('SMTP_FROM', defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'no-reply@ufm.edu.vn');
}

if (!defined('SMTP_FROM_EMAIL')) {
    define('SMTP_FROM_EMAIL', defined('SMTP_FROM') ? SMTP_FROM : 'no-reply@ufm.edu.vn');
}

if (!defined('SMTP_FROM_NAME')) {
    define('SMTP_FROM_NAME', 'UFM Quảng Ngãi - Phân công Công việc');
}

if (!defined('APP_URL')) {
    // Auto detect protocol and host if running in web request context
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('APP_URL', "$protocol://$host");
}
