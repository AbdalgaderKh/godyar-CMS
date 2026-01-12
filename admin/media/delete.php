<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';

use Godyar\Auth;

header('Content-Type: application/json; charset=utf-8');

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => __('t_1b1d8327cf', 'غير مصرح')]);
    exit;
}

$csrf = (string)($_POST['csrf_token'] ?? '');
if (function_exists('verify_csrf') && !verify_csrf($csrf)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => __('t_9a3bb26b6c', 'CSRF غير صالح')]);
    exit;
}

/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB not available']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => __('t_6e5c4020ef', 'معرّف غير صالح')]);
    exit;
}

// Verify table exists
try {
    $check = $pdo->query("SHOW TABLES LIKE 'media'");
    $exists = (bool)($check && $check->fetchColumn());
    if (!$exists) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => __('t_4f86cc0b6b', 'جدول media غير موجود')]);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB error']);
    exit;
}

// Load record
$stmt = $pdo->prepare("SELECT id, file_path, file_name FROM media WHERE id=:id LIMIT 1");
$stmt->execute([':id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => __('t_0b1e9d7b59', 'غير موجود')]);
    exit;
}

$fileUrl = (string)($row['file_path'] ?? '');
$docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$fsPath = '';

if ($fileUrl !== '') {
    $parsed = parse_url($fileUrl);
    $path = (string)($parsed['path'] ?? $fileUrl);
    // Allow only local uploads path
    if (str_starts_with($path, '/assets/uploads/media/')) {
        $candidate = $docRoot . $path;
        $real = realpath($candidate) ?: $candidate;
        // Ensure within uploads dir
        $uploadsDir = realpath($docRoot . '/assets/uploads/media') ?: ($docRoot . '/assets/uploads/media');
        if ($uploadsDir && str_starts_with($real, $uploadsDir)) {
            $fsPath = $real;
        }
    }
}

$pdo->beginTransaction();
try {
    $del = $pdo->prepare("DELETE FROM media WHERE id=:id LIMIT 1");
    $del->execute([':id' => $id]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => __('t_7d50552a10', 'فشل حذف السجل')]);
    exit;
}

// Delete file on disk (best-effort)
if ($fsPath !== '' && is_file($fsPath)) {
    @unlink($fsPath);
    // Delete possible webp variant
    $webp = preg_replace('~\.(jpe?g|png)$~i', '.webp', $fsPath);
    if ($webp && $webp !== $fsPath && is_file($webp)) @unlink($webp);
}

echo json_encode(['ok' => true]);
