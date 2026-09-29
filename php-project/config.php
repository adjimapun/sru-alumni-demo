<?php
declare(strict_types=1);
session_start();

const DB_HOST = '127.0.0.1';
const DB_NAME = 'sru_alumni';
const DB_USER = 'root';
const DB_PASS = '';
const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
function h(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verify_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419); exit('CSRF token invalid');
    }
}
function citizen_hash(string $citizen): string { return hash('sha256', preg_replace('/\D/', '', $citizen)); }
function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $st=db()->prepare('SELECT * FROM users WHERE id=?'); $st->execute([$_SESSION['user_id']]);
    return $st->fetch() ?: null;
}
function require_login(): array {
    $u=current_user();
    if(!$u){ header('Location: index.php'); exit; }
    return $u;
}
function current_admin(): ?array {
    if (empty($_SESSION['admin_id'])) return null;
    $st=db()->prepare('SELECT * FROM admins WHERE id=?'); $st->execute([$_SESSION['admin_id']]);
    return $st->fetch() ?: null;
}
function thai_year(): int { return (int)date('Y') + 543; }
function upload_file(string $field, string $prefix): ?string {
    if (empty($_FILES[$field]['name'])) return null;
    $f=$_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('อัปโหลดไฟล์ไม่สำเร็จ');
    if ($f['size'] > MAX_UPLOAD_BYTES) throw new RuntimeException('ไฟล์ต้องไม่เกิน 5 MB');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','application/pdf'=>'pdf'];
    if(!isset($allowed[$mime])) throw new RuntimeException('รองรับเฉพาะ JPG, PNG และ PDF');
    $dir=__DIR__.'/uploads';
    if(!is_dir($dir)) mkdir($dir,0755,true);
    $name=$prefix.bin2hex(random_bytes(12)).'.'.$allowed[$mime];
    if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$name)) throw new RuntimeException('บันทึกไฟล์ไม่สำเร็จ');
    return 'uploads/'.$name;
}
function app_status_th(string $s): string {
    return [
      'draft'=>'ฉบับร่าง','pending_payment'=>'รอชำระค่าธรรมเนียม',
      'payment_review'=>'รอตรวจสอบหลักฐาน','payment_invalid'=>'หลักฐานไม่ถูกต้อง',
      'approved'=>'สมาชิกสมบูรณ์'
    ][$s] ?? $s;
}
