<?php
require __DIR__.'/config.php';

if (!verify_csrf_value($_GET['token'] ?? null)) {
    http_response_code(419);
    exit('คำขอออกจากระบบไม่ถูกต้อง');
}

unset($_SESSION['user_id']);
session_regenerate_id(true);
$_SESSION['_last_regeneration'] = time();

header('Location: index.php');
exit;
