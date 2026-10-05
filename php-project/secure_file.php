<?php
require __DIR__.'/config.php';

$kind = trim((string)($_GET['kind'] ?? ''));
$id = (int)($_GET['id'] ?? 0);

$user = current_user();
$admin = current_admin();
$path = null;

try {
    switch ($kind) {
        case 'slip':
            if (!$admin || $id <= 0) {
                http_response_code(403);
                exit('Forbidden');
            }

            $st = db()->prepare('SELECT slip_path FROM payments WHERE id=? LIMIT 1');
            $st->execute([$id]);
            $path = $st->fetchColumn() ?: null;
            break;

        case 'member_photo':
            if ($id <= 0 || (!$user && !$admin)) {
                http_response_code(403);
                exit('Forbidden');
            }

            $st = db()->prepare(
                'SELECT user_id,member_photo_path
                 FROM applications
                 WHERE id=?
                 LIMIT 1'
            );
            $st->execute([$id]);
            $row = $st->fetch();

            if (!$row) {
                http_response_code(404);
                exit('Not Found');
            }

            if (!$admin && (int)$row['user_id'] !== (int)$user['id']) {
                http_response_code(403);
                exit('Forbidden');
            }

            $path = $row['member_photo_path'] ?: null;
            break;

        case 'certificate':
            if ($id <= 0 || (!$user && !$admin)) {
                http_response_code(403);
                exit('Forbidden');
            }

            $st = db()->prepare(
                'SELECT t.certificate_path,m.user_id
                 FROM trainings t
                 JOIN members m ON m.id=t.member_id
                 WHERE t.id=?
                 LIMIT 1'
            );
            $st->execute([$id]);
            $row = $st->fetch();

            if (!$row) {
                http_response_code(404);
                exit('Not Found');
            }

            if (!$admin && (int)$row['user_id'] !== (int)$user['id']) {
                http_response_code(403);
                exit('Forbidden');
            }

            $path = $row['certificate_path'] ?: null;
            break;

        case 'receipt_signature':
            if (!$user && !$admin) {
                http_response_code(403);
                exit('Forbidden');
            }

            $st = db()->query(
                'SELECT signature_path
                 FROM receipt_settings
                 WHERE id=1
                 LIMIT 1'
            );
            $path = $st->fetchColumn() ?: null;
            break;

        case 'president_signature':
            if (!$user && !$admin) {
                http_response_code(403);
                exit('Forbidden');
            }

            $st = db()->query(
                'SELECT president_signature_path
                 FROM member_card_settings
                 WHERE id=1
                 LIMIT 1'
            );
            $path = $st->fetchColumn() ?: null;
            break;

        default:
            http_response_code(404);
            exit('Not Found');
    }
} catch (Throwable $e) {
    error_log('Secure file lookup failed: '.$e->getMessage());
    http_response_code(500);
    exit('Unable to load file');
}

if (!$path || !preg_match('#^uploads/[A-Za-z0-9._-]+$#', (string)$path)) {
    http_response_code(404);
    exit('Not Found');
}

$uploadRoot = realpath(__DIR__.'/uploads');
$file = realpath(__DIR__.'/'.(string)$path);

if (
    !$uploadRoot ||
    !$file ||
    !str_starts_with($file, $uploadRoot.DIRECTORY_SEPARATOR) ||
    !is_file($file)
) {
    http_response_code(404);
    exit('Not Found');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
$allowed = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'application/pdf',
];

if (!in_array($mime, $allowed, true)) {
    http_response_code(415);
    exit('Unsupported file type');
}

header_remove('Content-Security-Policy');
header('Content-Type: '.$mime);
header('Content-Length: '.(string)filesize($file));
header('Content-Disposition: inline; filename="'.rawurlencode(basename($file)).'"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');

readfile($file);
exit;
