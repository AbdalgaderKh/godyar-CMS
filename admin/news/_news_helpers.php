<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';

/**
 * News helpers (tags + datetime)
 */

function gdy_dt_local_to_sql(?string $v): ?string {
    $v = trim((string)$v);
    if ($v === '') return null;
    $v = str_replace('T', ' ', $v);
    if (strlen($v) === 16) $v .= ':00';
    return $v;
}

function gdy_slugify(string $text): string {
    $text = trim($text);
    $text = preg_replace('~\s+~u', '-', $text);
    $text = preg_replace('~[^\p{L}\p{N}\-_]+~u', '', $text);
    $text = trim($text, '-');
    return mb_strtolower($text, 'UTF-8');
}

function gdy_parse_tags(string $tags): array {
    $parts = preg_split('~[،,]+~u', $tags);
    $out = [];
    foreach ($parts as $p) {
        $t = trim($p);
        if ($t === '') continue;
        $out[] = $t;
    }
    // unique
    $out = array_values(array_unique($out));
    // limit
    return array_slice($out, 0, 20);
}

function gdy_sync_news_tags(PDO $pdo, int $newsId, string $tagsInput): void {
    $tags = gdy_parse_tags($tagsInput);
    // clear existing
    $pdo->prepare("DELETE FROM news_tags WHERE news_id = ?")->execute([$newsId]);
    if (empty($tags)) return;

	// Some installs have UNIQUE(name) instead of UNIQUE(slug). We support both safely.
	$sel = $pdo->prepare("SELECT id FROM tags WHERE slug = ? OR name = ? LIMIT 1");
	// MySQL: return existing id on duplicate key via LAST_INSERT_ID trick.
	$ins = $pdo->prepare(
		"INSERT INTO tags (name, slug, created_at)
		 VALUES (?, ?, NOW())
		 ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), slug = VALUES(slug)"
	);
    $link = $pdo->prepare("INSERT INTO news_tags (news_id, tag_id) VALUES (?, ?)");

    foreach ($tags as $name) {
        $slug = gdy_slugify($name);
        if ($slug === '') continue;

		$sel->execute([$slug, $name]);
        $id = (int)($sel->fetchColumn() ?: 0);
        if ($id <= 0) {
			$ins->execute([$name, $slug]);
			$id = (int)$pdo->lastInsertId();
        }
        if ($id > 0) {
            $link->execute([$newsId, $id]);
        }
    }
}

function gdy_get_news_tags(PDO $pdo, int $newsId): string {
    $stmt = $pdo->prepare("SELECT t.name
        FROM news_tags nt
        JOIN tags t ON t.id = nt.tag_id
        WHERE nt.news_id = ?
        ORDER BY t.name ASC");
    $stmt->execute([$newsId]);
    $names = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $sep = (function_exists('gdy_current_lang') && gdy_current_lang() === 'ar') ? '، ' : ', ';
    return implode($sep, array_map('strval', $names ?: []));
}

// -----------------------------------------------------------------------------
// Attachments helpers (PDF/Word/Excel/...)
// -----------------------------------------------------------------------------

/**
 * Cache table columns to avoid repeated SHOW COLUMNS queries.
 */
function gdy_db_columns(PDO $pdo, string $table): array {
    static $cache = [];
    $key = spl_object_id($pdo) . ':' . $table;
    if (isset($cache[$key])) return $cache[$key];

    $cols = [];
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $cols[(string)$r['Field']] = true;
        }
    } catch (Throwable $e) {
        @error_log('[News Helpers] SHOW COLUMNS failed for ' . $table . ': ' . $e->getMessage());
    }
    $cache[$key] = $cols;
    return $cols;
}

function gdy_db_column_exists(PDO $pdo, string $table, string $column): bool {
    $cols = gdy_db_columns($pdo, $table);
    return isset($cols[$column]);
}

function gdy_db_table_exists(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function gdy_ensure_news_attachments_table(PDO $pdo): void {
    if (gdy_db_table_exists($pdo, 'news_attachments')) return;

    $sql = "CREATE TABLE IF NOT EXISTS `news_attachments` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `news_id` INT UNSIGNED NOT NULL,
        `original_name` VARCHAR(255) NOT NULL,
        `file_path` VARCHAR(255) NOT NULL,
        `mime_type` VARCHAR(120) NULL,
        `file_size` INT UNSIGNED NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_news_id` (`news_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    try {
        $pdo->exec($sql);
    } catch (Throwable $e) {
        @error_log('[News Helpers] failed creating news_attachments: ' . $e->getMessage());
    }
}

function gdy_normalize_files_array(array $files): array {
    // supports both single and multiple upload structure
    $out = [];
    if (!isset($files['name'])) return $out;

    if (is_array($files['name'])) {
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            $out[] = [
                'name' => (string)($files['name'][$i] ?? ''),
                'type' => (string)($files['type'][$i] ?? ''),
                'tmp_name' => (string)($files['tmp_name'][$i] ?? ''),
                'error' => (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int)($files['size'][$i] ?? 0),
            ];
        }
    } else {
        $out[] = [
            'name' => (string)($files['name'] ?? ''),
            'type' => (string)($files['type'] ?? ''),
            'tmp_name' => (string)($files['tmp_name'] ?? ''),
            'error' => (int)($files['error'] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int)($files['size'] ?? 0),
        ];
    }
    return $out;
}

function gdy_attachment_icon_class(string $filenameOrExt): string {
    $ext = strtolower(pathinfo($filenameOrExt, PATHINFO_EXTENSION));
    if ($ext === '') $ext = strtolower($filenameOrExt);

    // PHP 7.4 compatibility: avoid "match" (PHP 8+)
    switch ($ext) {
        case 'pdf':
            return 'fa-regular fa-file-pdf';
        case 'doc':
        case 'docx':
            return 'fa-regular fa-file-word';
        case 'xls':
        case 'xlsx':
            return 'fa-regular fa-file-excel';
        case 'ppt':
        case 'pptx':
            return 'fa-regular fa-file-powerpoint';
        case 'zip':
        case 'rar':
        case '7z':
            return 'fa-regular fa-file-zipper';
        case 'txt':
        case 'rtf':
            return 'fa-regular fa-file-lines';
        case 'png':
        case 'jpg':
        case 'jpeg':
        case 'gif':
        case 'webp':
            return 'fa-regular fa-file-image';
        default:
            return 'fa-regular fa-file';
    }
}

function gdy_save_news_attachments(PDO $pdo, int $newsId, array $files, array &$errors = []): void {
    gdy_ensure_news_attachments_table($pdo);

    $items = gdy_normalize_files_array($files);
    if (empty($items)) return;

    $uploadDir = __DIR__ . '/../../uploads/news/attachments/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    // hard limits
    $maxSize = 20 * 1024 * 1024; // 20MB per file
    $allowedExt = ['pdf','doc','docx','xls','xlsx','ppt','pptx','zip','rar','7z','txt','rtf','png','jpg','jpeg','gif','webp'];

    $ins = $pdo->prepare("INSERT INTO news_attachments (news_id, original_name, file_path, mime_type, file_size, created_at)
                          VALUES (:news_id, :original_name, :file_path, :mime_type, :file_size, NOW())");

    foreach ($items as $f) {
        $err = (int)$f['error'];
        if ($err === UPLOAD_ERR_NO_FILE) continue;
        if ($err !== UPLOAD_ERR_OK) {
            $errors['attachments'] = __('t_556244d2a1', 'حدث خطأ أثناء رفع أحد المرفقات.');
            continue;
        }

        $orig = trim((string)$f['name']);
        $tmp  = (string)$f['tmp_name'];
        $size = (int)$f['size'];

        if ($orig === '' || $tmp === '') continue;
        if ($size <= 0 || $size > $maxSize) {
            $errors['attachments'] = __('t_67b942a769', 'حجم أحد المرفقات أكبر من المسموح (20 ميجابايت).');
            continue;
        }

        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, $allowedExt, true)) {
            $errors['attachments'] = __('t_054b2b5303', 'نوع أحد المرفقات غير مسموح. يُسمح بـ PDF/Word/Excel وغيرها.');
            continue;
        }

        $mime = '';
        try {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = $finfo ? (string)finfo_file($finfo, $tmp) : '';
            if ($finfo) finfo_close($finfo);
        } catch (Throwable) {}

        $baseName = date('Ymd_His') . '_' . bin2hex(random_bytes(6));
        $safeName = $baseName . '.' . $ext;
        $target   = $uploadDir . $safeName;

        if (!move_uploaded_file($tmp, $target)) {
            $errors['attachments'] = __('t_7148406c0e', 'تعذر حفظ أحد المرفقات على الخادم.');
            continue;
        }

        $relPath = 'uploads/news/attachments/' . $safeName;
        try {
            $ins->execute([
                ':news_id' => $newsId,
                ':original_name' => $orig,
                ':file_path' => $relPath,
                ':mime_type' => $mime !== '' ? $mime : null,
                ':file_size' => $size,
            ]);
        } catch (Throwable $e) {
            @error_log('[News Helpers] insert attachment failed: ' . $e->getMessage());
        }
    }
}

function gdy_get_news_attachments(PDO $pdo, int $newsId): array {
    if (!gdy_db_table_exists($pdo, 'news_attachments')) return [];
    try {
        $stmt = $pdo->prepare("SELECT id, original_name, file_path, mime_type, file_size, created_at
                               FROM news_attachments
                               WHERE news_id = ?
                               ORDER BY id DESC");
        $stmt->execute([$newsId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        @error_log('[News Helpers] get attachments failed: ' . $e->getMessage());
        return [];
    }
}

function gdy_delete_news_attachment(PDO $pdo, int $attachmentId, int $newsId): bool {
    if (!gdy_db_table_exists($pdo, 'news_attachments')) return false;
    try {
        $stmt = $pdo->prepare("SELECT file_path FROM news_attachments WHERE id = ? AND news_id = ? LIMIT 1");
        $stmt->execute([$attachmentId, $newsId]);
        $path = (string)($stmt->fetchColumn() ?: '');
        if ($path !== '') {
            $abs = realpath(__DIR__ . '/../../' . ltrim($path, '/'));
            // only delete inside uploads/news/attachments
            $root = realpath(__DIR__ . '/../../uploads/news/attachments');
            if ($abs && $root && str_starts_with($abs, $root)) {
                @unlink($abs);
            }
        }
        $del = $pdo->prepare("DELETE FROM news_attachments WHERE id = ? AND news_id = ? LIMIT 1");
        $del->execute([$attachmentId, $newsId]);
        return true;
    } catch (Throwable $e) {
        @error_log('[News Helpers] delete attachment failed: ' . $e->getMessage());
        return false;
    }
}


// -----------------------------------------------------------------------------

// -----------------------------------------------------------------------------
// Editorial workflow helpers (notes + revisions)
// -----------------------------------------------------------------------------

function gdy_ensure_news_notes_table(PDO $pdo): void
{
    if (gdy_db_table_exists($pdo, 'news_notes')) {
        return;
    }

    $sql = "CREATE TABLE IF NOT EXISTS `news_notes` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `news_id` INT UNSIGNED NOT NULL,
        `user_id` INT UNSIGNED NULL,
        `note` TEXT NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_news_id` (`news_id`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    try {
        $pdo->exec($sql);
    } catch (Throwable $e) {
        @error_log('[News Helpers] failed creating news_notes: ' . $e->getMessage());
    }
}

function gdy_add_news_note(PDO $pdo, int $newsId, ?int $userId, string $note): bool
{
    $note = trim($note);
    if ($newsId <= 0 || $note === '') return false;

    gdy_ensure_news_notes_table($pdo);

    try {
        $stmt = $pdo->prepare("INSERT INTO news_notes (news_id, user_id, note, created_at)
                              VALUES (:news_id, :user_id, :note, NOW())");
        $stmt->bindValue(':news_id', $newsId, PDO::PARAM_INT);
        if ($userId && $userId > 0) $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        else $stmt->bindValue(':user_id', null, PDO::PARAM_NULL);
        $stmt->bindValue(':note', $note, PDO::PARAM_STR);
        return (bool)$stmt->execute();
    } catch (Throwable $e) {
        @error_log('[News Helpers] add note failed: ' . $e->getMessage());
        return false;
    }
}

function gdy_get_news_notes(PDO $pdo, int $newsId, int $limit = 50): array
{
    if ($newsId <= 0) return [];
    if (!gdy_db_table_exists($pdo, 'news_notes')) return [];

    $limit = max(1, min(200, $limit));

    try {
        $stmt = $pdo->prepare("SELECT n.id, n.note, n.created_at, n.user_id,
                                      COALESCE(u.name, u.username, '') AS user_name
                               FROM news_notes n
                               LEFT JOIN users u ON u.id = n.user_id
                               WHERE n.news_id = :nid
                               ORDER BY n.id DESC
                               LIMIT {$limit}");
        $stmt->bindValue(':nid', $newsId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        @error_log('[News Helpers] get notes failed: ' . $e->getMessage());
        return [];
    }
}

function gdy_ensure_news_revisions_table(PDO $pdo): void
{
    if (gdy_db_table_exists($pdo, 'news_revisions')) {
        return;
    }

    $sql = "CREATE TABLE IF NOT EXISTS `news_revisions` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `news_id` INT UNSIGNED NOT NULL,
        `user_id` INT UNSIGNED NULL,
        `action` VARCHAR(30) NOT NULL DEFAULT 'update',
        `payload` LONGTEXT NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_news_id` (`news_id`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    try {
        $pdo->exec($sql);
    } catch (Throwable $e) {
        @error_log('[News Helpers] failed creating news_revisions: ' . $e->getMessage());
    }
}

function gdy_capture_news_revision(PDO $pdo, int $newsId, ?int $userId, string $action, array $newsRow, string $tags = ''): bool
{
    if ($newsId <= 0) return false;

    gdy_ensure_news_revisions_table($pdo);

    // Keep only relevant keys
    $payload = [
        'news' => $newsRow,
        'tags' => $tags,
    ];

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json) || $json === '') return false;

    try {
        $stmt = $pdo->prepare("INSERT INTO news_revisions (news_id, user_id, action, payload, created_at)
                              VALUES (:news_id, :user_id, :action, :payload, NOW())");
        $stmt->bindValue(':news_id', $newsId, PDO::PARAM_INT);
        if ($userId && $userId > 0) $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        else $stmt->bindValue(':user_id', null, PDO::PARAM_NULL);
        $stmt->bindValue(':action', $action !== '' ? $action : 'update', PDO::PARAM_STR);
        $stmt->bindValue(':payload', $json, PDO::PARAM_STR);
        return (bool)$stmt->execute();
    } catch (Throwable $e) {
        @error_log('[News Helpers] capture revision failed: ' . $e->getMessage());
        return false;
    }
}

function gdy_get_news_revisions(PDO $pdo, int $newsId, int $limit = 30): array
{
    if ($newsId <= 0) return [];
    if (!gdy_db_table_exists($pdo, 'news_revisions')) return [];

    $limit = max(1, min(200, $limit));

    try {
        $stmt = $pdo->prepare("SELECT r.id, r.action, r.created_at, r.user_id,
                                      COALESCE(u.name, u.username, '') AS user_name
                               FROM news_revisions r
                               LEFT JOIN users u ON u.id = r.user_id
                               WHERE r.news_id = :nid
                               ORDER BY r.id DESC
                               LIMIT {$limit}");
        $stmt->bindValue(':nid', $newsId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        @error_log('[News Helpers] get revisions failed: ' . $e->getMessage());
        return [];
    }
}

function gdy_get_revision_payload(PDO $pdo, int $revisionId): ?array
{
    if ($revisionId <= 0) return null;
    if (!gdy_db_table_exists($pdo, 'news_revisions')) return null;

    try {
        $stmt = $pdo->prepare("SELECT payload FROM news_revisions WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $revisionId, PDO::PARAM_INT);
        $stmt->execute();
        $payload = $stmt->fetchColumn();
        if (!is_string($payload) || $payload === '') return null;

        $data = json_decode($payload, true);
        return is_array($data) ? $data : null;
    } catch (Throwable $e) {
        @error_log('[News Helpers] get revision payload failed: ' . $e->getMessage());
        return null;
    }
}

function gdy_restore_news_from_revision(PDO $pdo, int $newsId, int $revisionId, ?int $actorUserId = null): bool
{
    if ($newsId <= 0 || $revisionId <= 0) return false;

    $payload = gdy_get_revision_payload($pdo, $revisionId);
    if (!$payload || empty($payload['news']) || !is_array($payload['news'])) {
        return false;
    }

    $news = $payload['news'];
    $tags = isset($payload['tags']) ? (string)$payload['tags'] : '';

    $cols = gdy_db_columns($pdo, 'news');

    $sets = [];
    $params = [':id' => $newsId];

    $map = [
        'title' => 'title',
        'slug' => 'slug',
        'excerpt' => 'excerpt',
        'summary' => 'summary',
        'content' => 'content',
        'body' => 'body',
        'category_id' => 'category_id',
        'author_id' => 'author_id',
        'opinion_author_id' => 'opinion_author_id',
        'status' => 'status',
        'featured' => 'featured',
        'is_breaking' => 'is_breaking',
        'published_at' => 'published_at',
        'publish_at' => 'publish_at',
        'unpublish_at' => 'unpublish_at',
        'seo_title' => 'seo_title',
        'seo_description' => 'seo_description',
        'seo_keywords' => 'seo_keywords',
        'image' => 'image',
    ];

    foreach ($map as $k => $col) {
        if (!isset($cols[$col])) continue;
        if (!array_key_exists($k, $news)) continue;

        $sets[] = "`{$col}` = :{$col}";
        $params[":{$col}"] = $news[$k];
    }

    if (empty($sets)) return false;

    try {
        // capture current state as a revision before restoring
        try {
            $stmt0 = $pdo->prepare('SELECT * FROM news WHERE id = :id LIMIT 1');
            $stmt0->execute([':id' => $newsId]);
            $currentRow = $stmt0->fetch(PDO::FETCH_ASSOC) ?: [];
            $currentTags = '';
            try { $currentTags = gdy_get_news_tags($pdo, $newsId); } catch (Throwable) {}
            gdy_capture_news_revision($pdo, $newsId, $actorUserId, 'restore_backup', $currentRow, $currentTags);
        } catch (Throwable) {}

        $sql = 'UPDATE news SET ' . implode(', ', $sets) . ' WHERE id = :id LIMIT 1';
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();

        // restore tags
        try {
            gdy_sync_news_tags($pdo, $newsId, $tags);
        } catch (Throwable) {}

        // capture restoration action
        try {
            $stmt1 = $pdo->prepare('SELECT * FROM news WHERE id = :id LIMIT 1');
            $stmt1->execute([':id' => $newsId]);
            $restoredRow = $stmt1->fetch(PDO::FETCH_ASSOC) ?: [];
            $restoredTags = '';
            try { $restoredTags = gdy_get_news_tags($pdo, $newsId); } catch (Throwable) {}
            gdy_capture_news_revision($pdo, $newsId, $actorUserId, 'restore', $restoredRow, $restoredTags);
        } catch (Throwable) {}

        return true;
    } catch (Throwable $e) {
        @error_log('[News Helpers] restore revision failed: ' . $e->getMessage());
        return false;
    }
}


/**
 * Smart Suggestions: related news based on shared tags.
 * Returns recent news items that share tags with current one.
 */
function gdy_get_related_news(PDO $pdo, int $newsId, int $limit = 6): array {
    $limit = max(1, min(20, $limit));
    try {
        $chk = $pdo->query("SHOW TABLES LIKE 'news_tags'");
        $has = $chk && $chk->fetchColumn();
        if (!$has) return [];
    } catch (Throwable) { return []; }

    try {
        $sql = "
            SELECT n.id, n.title, n.status, n.created_at, COUNT(*) AS score
            FROM news_tags nt
            INNER JOIN news_tags nt2 ON nt2.tag_id = nt.tag_id AND nt2.news_id <> nt.news_id
            INNER JOIN news n ON n.id = nt2.news_id
            WHERE nt.news_id = :id
            GROUP BY n.id
            ORDER BY score DESC, n.id DESC
            LIMIT $limit
        ";
        $st = $pdo->prepare($sql);
        $st->execute(['id' => $newsId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) {
        return [];
    }
}
