<?php
require __DIR__.'/config.php';

if (!verify_csrf_value($_GET['token'] ?? null)) {
    http_response_code(419);
    exit('คำขอออกจากระบบไม่ถูกต้อง');
}

// ล้างข้อมูลทั้งหมดใน Session
$_SESSION = [];

// ลบ Session Cookie ของ PHP
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'] ?: '/',
        'domain' => $params['domain'] ?: '',
        'secure' => (bool)$params['secure'],
        'httponly' => (bool)$params['httponly'],
        'samesite' => $params['samesite'] ?: 'Lax',
    ]);
}

// ทำลาย Session ฝั่ง Server
session_destroy();

// กลับไปหน้า Login
header('Location: index.php');
exit;
