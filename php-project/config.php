<?php
declare(strict_types=1);

/*
 * SRU Alumni Association - Production bootstrap/security configuration
 * เก็บค่าฐานข้อมูลจริงไว้ใน config.local.php หรือ Environment Variables เท่านั้น
 */

function is_https_request(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    // ใช้ X-Forwarded-Proto เฉพาะเมื่อผู้ดูแลเปิดใช้งาน reverse proxy mode เอง
    $trustProxy = getenv('SRU_TRUST_PROXY') === '1';
    if ($trustProxy) {
        $proto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0] ?? ''));
        return $proto === 'https';
    }

    return false;
}

$forceHttps = getenv('SRU_FORCE_HTTPS') === '1';
if (
    $forceHttps &&
    PHP_SAPI !== 'cli' &&
    !is_https_request() &&
    !headers_sent() &&
    !empty($_SERVER['HTTP_HOST']) &&
    !empty($_SERVER['REQUEST_URI'])
) {
    $host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string)$_SERVER['HTTP_HOST']);
    header('Location: https://'.$host.$_SERVER['REQUEST_URI'], true, 301);
    exit;
}

$production = (getenv('SRU_APP_ENV') ?: 'production') === 'production';
if ($production) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}
ini_set('log_errors', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

$cookieSecure = is_https_request() || $forceHttps;
session_name('SRUALUMNISESSID');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $cookieSecure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$now = time();
$sessionIdleSeconds = 60 * 60 * 2;
$sessionRotateSeconds = 60 * 15;

if (!empty($_SESSION['_last_activity']) && ($now - (int)$_SESSION['_last_activity']) > $sessionIdleSeconds) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => $now - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => (bool)$params['secure'],
            'httponly' => (bool)$params['httponly'],
            'samesite' => $params['samesite'] ?: 'Lax',
        ]);
    }
    session_destroy();
    session_start();
}

$_SESSION['_last_activity'] = $now;

if (empty($_SESSION['_last_regeneration'])) {
    $_SESSION['_last_regeneration'] = $now;
} elseif (($now - (int)$_SESSION['_last_regeneration']) > $sessionRotateSeconds) {
    session_regenerate_id(true);
    $_SESSION['_last_regeneration'] = $now;
}

function apply_security_headers(): void {
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }

    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cache-Control: no-store, private, max-age=0');
    header('Pragma: no-cache');

    $csp = [
        "default-src 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "frame-ancestors 'none'",
        "form-action 'self'",
        "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "font-src 'self' https://fonts.gstatic.com data:",
        "img-src 'self' data: blob: https://quickchart.io",
        "connect-src 'self'",
        "worker-src 'self' blob:",
    ];

    if (is_https_request()) {
        $csp[] = 'upgrade-insecure-requests';
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    header('Content-Security-Policy: '.implode('; ', $csp));
}

apply_security_headers();

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
const MAX_IMAGE_PIXELS = 32000000;
const MAX_IMAGE_DIMENSION = 8000;

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
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);

        return $pdo;
    } catch (PDOException $e) {
        error_log('Database connection failed: '.$e->getMessage());
        throw new RuntimeException(
            'ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาติดต่อผู้ดูแลระบบ',
            0,
            $e
        );
    }
}

function db_connection_status(): array {
    try {
        $pdo = db();
        $ok = (int)$pdo->query('SELECT 1')->fetchColumn() === 1;

        return [
            'ok' => $ok,
            'message' => $ok ? 'เชื่อมต่อฐานข้อมูลสำเร็จ' : 'เชื่อมต่อฐานข้อมูลไม่สำเร็จ',
        ];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'message' => 'เชื่อมต่อฐานข้อมูลไม่สำเร็จ',
        ];
    }
}

function h(?string $v): string {
    return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function safe_error_message(Throwable $e, string $fallback = 'เกิดข้อผิดพลาด กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ'): string {
    if ($e instanceof RuntimeException && !($e->getPrevious() instanceof PDOException)) {
        return $e->getMessage();
    }

    error_log(get_class($e).': '.$e->getMessage());
    return $fallback;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf'];
}

function verify_csrf(): void {
    $submitted = (string)($_POST['csrf'] ?? '');
    if ($submitted === '' || !hash_equals((string)($_SESSION['csrf'] ?? ''), $submitted)) {
        http_response_code(419);
        exit('คำขอหมดอายุหรือไม่ถูกต้อง กรุณากลับไปยังหน้าก่อนหน้าแล้วลองใหม่');
    }
}

function verify_csrf_value(?string $token): bool {
    return $token !== null
        && $token !== ''
        && hash_equals((string)($_SESSION['csrf'] ?? ''), $token);
}

function citizen_hash(string $citizen): string {
    return hash('sha256', preg_replace('/\D/', '', $citizen));
}

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $st = db()->prepare('SELECT * FROM users WHERE id=?');
    $st->execute([(int)$_SESSION['user_id']]);

    return $st->fetch() ?: null;
}

function require_login(): array {
    $u = current_user();

    if (!$u) {
        header('Location: index.php');
        exit;
    }

    $script = basename((string)($_SERVER['PHP_SELF'] ?? ''));
    if (
        isset($u['must_change_password']) &&
        (int)$u['must_change_password'] === 1 &&
        $script !== 'change_password.php'
    ) {
        header('Location: change_password.php');
        exit;
    }

    return $u;
}

function password_meets_policy(string $password): bool {
    return mb_strlen($password, 'UTF-8') >= 12
        && preg_match('/[A-Za-z]/', $password)
        && preg_match('/[0-9]/', $password);
}

function current_admin(): ?array {
    if (empty($_SESSION['admin_id'])) {
        return null;
    }

    $st = db()->prepare('SELECT * FROM admins WHERE id=?');
    $st->execute([(int)$_SESSION['admin_id']]);
    $admin = $st->fetch() ?: null;

    if ($admin && isset($admin['is_active']) && (int)$admin['is_active'] !== 1) {
        unset($_SESSION['admin_id']);
        return null;
    }

    return $admin;
}

function thai_year(): int {
    return (int)date('Y') + 543;
}

function client_ip(): string {
    // ไม่เชื่อ X-Forwarded-For โดยอัตโนมัติ เพื่อป้องกัน spoofing
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function public_base_url(): string {
    $configured = trim((string)(getenv('SRU_PUBLIC_BASE_URL') ?: ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $scheme = is_https_request() ? 'https' : 'http';
    return $scheme.'://'.$host;
}

function auth_identity_hash(string $scope, string $identity): string {
    return hash('sha256', $scope.'|'.mb_strtolower(trim($identity), 'UTF-8'));
}

function auth_rate_limit_status(string $scope, string $identity): array {
    $identityHash = auth_identity_hash($scope, $identity);
    $ipHash = hash('sha256', client_ip());

    try {
        $st = db()->prepare(
            'SELECT
                COUNT(*) AS failures,
                GREATEST(
                    0,
                    TIMESTAMPDIFF(
                        SECOND,
                        NOW(),
                        DATE_ADD(MAX(attempted_at), INTERVAL 15 MINUTE)
                    )
                ) AS retry_after
             FROM auth_attempts
             WHERE scope=?
               AND identity_hash=?
               AND ip_hash=?
               AND was_success=0
               AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
        );
        $st->execute([$scope, $identityHash, $ipHash]);
        $identityRow = $st->fetch() ?: ['failures'=>0,'retry_after'=>0];

        $st = db()->prepare(
            'SELECT
                COUNT(*) AS failures,
                GREATEST(
                    0,
                    TIMESTAMPDIFF(
                        SECOND,
                        NOW(),
                        DATE_ADD(MAX(attempted_at), INTERVAL 15 MINUTE)
                    )
                ) AS retry_after
             FROM auth_attempts
             WHERE scope=?
               AND ip_hash=?
               AND was_success=0
               AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
        );
        $st->execute([$scope, $ipHash]);
        $ipRow = $st->fetch() ?: ['failures'=>0,'retry_after'=>0];

        $identityBlocked = (int)$identityRow['failures'] >= 5;
        $ipBlocked = (int)$ipRow['failures'] >= 30;

        return [
            'blocked' => $identityBlocked || $ipBlocked,
            'retry_after' => max(
                (int)($identityRow['retry_after'] ?? 0),
                (int)($ipRow['retry_after'] ?? 0)
            ),
        ];
    } catch (PDOException $e) {
        error_log('Rate limit unavailable: '.$e->getMessage());
        return ['blocked'=>false,'retry_after'=>0];
    }
}

function record_auth_attempt(string $scope, string $identity, bool $success): void {
    $identityHash = auth_identity_hash($scope, $identity);
    $ipHash = hash('sha256', client_ip());

    try {
        if ($success) {
            $st = db()->prepare(
                'DELETE FROM auth_attempts
                 WHERE scope=? AND identity_hash=? AND ip_hash=?'
            );
            $st->execute([$scope, $identityHash, $ipHash]);
        } else {
            $st = db()->prepare(
                'INSERT INTO auth_attempts(scope,identity_hash,ip_hash,was_success)
                 VALUES(?,?,?,0)'
            );
            $st->execute([$scope, $identityHash, $ipHash]);
        }

        db()->exec(
            'DELETE FROM auth_attempts
             WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 7 DAY)'
        );
    } catch (PDOException $e) {
        error_log('Rate limit logging failed: '.$e->getMessage());
    }
}

function validate_uploaded_image(string $tmpName): string {
    if (!is_uploaded_file($tmpName)) {
        throw new RuntimeException('ไฟล์อัปโหลดไม่ถูกต้อง');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('รองรับรูปภาพเฉพาะ JPG, PNG และ WEBP');
    }

    $imageInfo = @getimagesize($tmpName);
    if ($imageInfo === false) {
        throw new RuntimeException('ไฟล์รูปภาพไม่ถูกต้อง');
    }

    $width = (int)($imageInfo[0] ?? 0);
    $height = (int)($imageInfo[1] ?? 0);

    if (
        $width <= 0 ||
        $height <= 0 ||
        $width > MAX_IMAGE_DIMENSION ||
        $height > MAX_IMAGE_DIMENSION ||
        ($width * $height) > MAX_IMAGE_PIXELS
    ) {
        throw new RuntimeException('ขนาดหรือความละเอียดของรูปภาพสูงเกินกำหนด');
    }

    return $allowed[$mime];
}

function ensure_upload_directory(): string {
    $dir = __DIR__.'/uploads';

    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์สำหรับอัปโหลดไฟล์ได้');
    }

    return $dir;
}

function upload_file(string $field, string $prefix): ?string {
    if (empty($_FILES[$field]['name'])) {
        return null;
    }

    $f = $_FILES[$field];

    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('อัปโหลดไฟล์ไม่สำเร็จ');
    }

    $size = (int)($f['size'] ?? 0);
    if ($size <= 0 || $size > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('ไฟล์ต้องมีขนาดไม่เกิน 5 MB');
    }

    $tmpName = (string)($f['tmp_name'] ?? '');
    if (!is_uploaded_file($tmpName)) {
        throw new RuntimeException('ไฟล์อัปโหลดไม่ถูกต้อง');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
    ];

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('รองรับเฉพาะ JPG, PNG และ PDF');
    }

    if (str_starts_with($mime, 'image/')) {
        validate_uploaded_image($tmpName);
    } else {
        $handle = fopen($tmpName, 'rb');
        $magic = $handle ? fread($handle, 5) : false;
        if (is_resource($handle)) {
            fclose($handle);
        }

        if ($magic !== '%PDF-') {
            throw new RuntimeException('ไฟล์ PDF ไม่ถูกต้อง');
        }
    }

    $dir = ensure_upload_directory();
    $name = $prefix.bin2hex(random_bytes(16)).'.'.$allowed[$mime];

    if (!move_uploaded_file($tmpName, $dir.'/'.$name)) {
        throw new RuntimeException('บันทึกไฟล์ไม่สำเร็จ');
    }

    @chmod($dir.'/'.$name, 0644);

    return 'uploads/'.$name;
}

function upload_image_file(string $field, string $prefix, int $maxBytes = MAX_UPLOAD_BYTES): ?string {
    if (empty($_FILES[$field]['name'])) {
        return null;
    }

    $f = $_FILES[$field];

    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('อัปโหลดรูปภาพไม่สำเร็จ');
    }

    $size = (int)($f['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
        throw new RuntimeException('รูปภาพมีขนาดใหญ่เกินกำหนด');
    }

    $tmpName = (string)($f['tmp_name'] ?? '');
    $extension = validate_uploaded_image($tmpName);

    $dir = ensure_upload_directory();
    $name = $prefix.bin2hex(random_bytes(16)).'.'.$extension;

    if (!move_uploaded_file($tmpName, $dir.'/'.$name)) {
        throw new RuntimeException('บันทึกรูปภาพไม่สำเร็จ');
    }

    @chmod($dir.'/'.$name, 0644);

    return 'uploads/'.$name;
}

function delete_managed_upload(?string $path, string $prefix): void {
    if (!$path) {
        return;
    }

    $pattern = '#^uploads/'.preg_quote($prefix, '#').'[a-f0-9]{24,32}\.(?:jpg|jpeg|png|webp|pdf)$#i';
    if (!preg_match($pattern, $path)) {
        return;
    }

    $file = __DIR__.'/'.$path;
    $uploadRoot = realpath(__DIR__.'/uploads');
    $realFile = realpath($file);

    if ($uploadRoot && $realFile && str_starts_with($realFile, $uploadRoot.DIRECTORY_SEPARATOR) && is_file($realFile)) {
        @unlink($realFile);
    }
}

function app_status_th(string $s): string {
    return [
        'draft'=>'ฉบับร่าง',
        'pending_payment'=>'รอชำระค่าธรรมเนียม',
        'payment_review'=>'รอตรวจสอบหลักฐาน',
        'payment_invalid'=>'หลักฐานไม่ถูกต้อง',
        'approved'=>'สมาชิกสมบูรณ์',
    ][$s] ?? $s;
}

function payment_status_th(string $s): string {
    return [
        'pending'=>'รอตรวจสอบหลักฐานการชำระเงิน',
        'paid'=>'ชำระเงินแล้ว',
        'invalid'=>'หลักฐานการชำระเงินไม่ถูกต้อง',
    ][$s] ?? $s;
}

function receipt_settings(): array {
    $defaults = [
        'payee_name' => 'ผู้รับเงิน',
        'payee_position' => 'สมาคมศิษย์เก่ามหาวิทยาลัยราชภัฏสุราษฎร์ธานี',
        'signature_path' => null,
    ];

    try {
        $st = db()->query(
            'SELECT payee_name,payee_position,signature_path
             FROM receipt_settings
             WHERE id=1
             LIMIT 1'
        );
        $row = $st->fetch();

        if (!$row) {
            return $defaults;
        }

        return [
            'payee_name' => trim((string)($row['payee_name'] ?? '')) ?: $defaults['payee_name'],
            'payee_position' => trim((string)($row['payee_position'] ?? '')) ?: $defaults['payee_position'],
            'signature_path' => $row['signature_path'] ?? null,
        ];
    } catch (PDOException $e) {
        error_log('Receipt settings unavailable: '.$e->getMessage());
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
        error_log('Member card settings unavailable: '.$e->getMessage());
        return $defaults;
    }
}

function admin_bootstrap_enabled(): bool {
    return getenv('SRU_ALLOW_ADMIN_BOOTSTRAP') === '1';
}
