<?php
declare(strict_types=1);
session_start();

$localDbConfig = [];
$localDbFile = __DIR__.'/config.local.php';

if (is_file($localDbFile)) {
    $loadedConfig = require $localDbFile;
    if (is_array($loadedConfig)) {
        $localDbConfig = $loadedConfig;
    }
}

$dbHostEnv = getenv('SRU_DB_HOST');
$dbNameEnv = getenv('SRU_DB_NAME');
$dbUserEnv = getenv('SRU_DB_USER');
$dbPassEnv = getenv('SRU_DB_PASS');

define('DB_HOST', (string)($localDbConfig['host'] ?? ($dbHostEnv !== false && $dbHostEnv !== '' ? $dbHostEnv : '127.0.0.1')));
define('DB_NAME', (string)($localDbConfig['name'] ?? ($dbNameEnv !== false && $dbNameEnv !== '' ? $dbNameEnv : 'sru_alumni')));
define('DB_USER', (string)($localDbConfig['user'] ?? ($dbUserEnv !== false && $dbUserEnv !== '' ? $dbUserEnv : 'root')));
define('DB_PASS', (string)($localDbConfig['pass'] ?? ($dbPassEnv !== false ? $dbPassEnv : '')));
const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

function db(): PDO {
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);

        return $pdo;
    } catch (PDOException $e) {
        error_log('Database connection failed: '.$e->getMessage());
        throw new RuntimeException('ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาตรวจสอบค่าการเชื่อมต่อหรือสถานะของเซิร์ฟเวอร์ฐานข้อมูล', 0, $e);
    }
}

function db_connection_status(): array {
    try {
        $pdo = db();
        $ok = (int)$pdo->query('SELECT 1')->fetchColumn() === 1;

        if ($ok) {
            return [
                'ok' => true,
                'message' => 'เชื่อมต่อฐานข้อมูลสำเร็จ',
            ];
        }

        return [
            'ok' => false,
            'message' => 'เชื่อมต่อฐานข้อมูลไม่สำเร็จ',
        ];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'message' => 'เชื่อมต่อฐานข้อมูลไม่สำเร็จ',
        ];
    }
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
    $st=db()->prepare('SELECT * FROM admins WHERE id=?');
    $st->execute([$_SESSION['admin_id']]);
    $admin=$st->fetch() ?: null;
    if ($admin && isset($admin['is_active']) && (int)$admin['is_active'] !== 1) return null;
    return $admin;
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

function upload_image_file(string $field, string $prefix, int $maxBytes = MAX_UPLOAD_BYTES): ?string {
    if (empty($_FILES[$field]['name'])) return null;

    $f = $_FILES[$field];

    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('อัปโหลดรูปภาพไม่สำเร็จ');
    }

    if ($f['size'] > $maxBytes) {
        throw new RuntimeException('รูปภาพมีขนาดใหญ่เกินกำหนด');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('รองรับรูปภาพเฉพาะ JPG, PNG และ WEBP');
    }

    $dir = __DIR__.'/uploads';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์สำหรับรูปภาพได้');
    }

    $name = $prefix.bin2hex(random_bytes(12)).'.'.$allowed[$mime];

    if (!move_uploaded_file($f['tmp_name'], $dir.'/'.$name)) {
        throw new RuntimeException('บันทึกรูปภาพไม่สำเร็จ');
    }

    return 'uploads/'.$name;
}

function delete_managed_upload(?string $path, string $prefix): void {
    if (!$path || !str_starts_with($path, 'uploads/'.$prefix)) {
        return;
    }

    $file = __DIR__.'/'.$path;
    if (is_file($file)) {
        @unlink($file);
    }
}
function app_status_th(string $s): string {
    return [
      'draft'=>'ฉบับร่าง','pending_payment'=>'รอชำระค่าธรรมเนียม',
      'payment_review'=>'รอตรวจสอบหลักฐาน','payment_invalid'=>'หลักฐานไม่ถูกต้อง',
      'approved'=>'สมาชิกสมบูรณ์'
    ][$s] ?? $s;
}

function payment_status_th(string $s): string {
    return [
      'pending'=>'รอตรวจสอบหลักฐานการชำระเงิน',
      'paid'=>'ชำระเงินแล้ว',
      'invalid'=>'หลักฐานการชำระเงินไม่ถูกต้อง'
    ][$s] ?? $s;
}


function receipt_settings(): array {
    $defaults = [
        'payee_name' => 'ผู้รับเงิน',
        'payee_position' => 'สมาคมศิษย์เก่ามหาวิทยาลัยราชภัฏสุราษฎร์ธานี',
        'signature_path' => null,
    ];

    try {
        $st = db()->query('SELECT payee_name,payee_position,signature_path FROM receipt_settings WHERE id=1 LIMIT 1');
        $row = $st->fetch();
        if (!$row) return $defaults;

        return [
            'payee_name' => trim((string)($row['payee_name'] ?? '')) ?: $defaults['payee_name'],
            'payee_position' => trim((string)($row['payee_position'] ?? '')) ?: $defaults['payee_position'],
            'signature_path' => $row['signature_path'] ?? null,
        ];
    } catch (PDOException $e) {
        // รองรับระบบที่ยังไม่ได้รัน migrate_v4.sql โดยใช้ค่าเดิมชั่วคราว
        return $defaults;
    }
}


function member_card_settings(): array {
    $defaults = [
        'president_name' => '',
        'president_position' => 'นายกสมาคมศิษย์เก่า มรส.',
        'president_signature_path' => null,
    ];

    try {
        $st = db()->query(
            'SELECT president_name,president_position,president_signature_path
             FROM member_card_settings
             WHERE id=1
             LIMIT 1'
        );
        $row = $st->fetch();

        if (!$row) {
            return $defaults;
        }

        return [
            'president_name' => trim((string)($row['president_name'] ?? '')),
            'president_position' => trim((string)($row['president_position'] ?? ''))
                ?: $defaults['president_position'],
            'president_signature_path' => $row['president_signature_path'] ?? null,
        ];
    } catch (PDOException $e) {
        return $defaults;
    }
}
