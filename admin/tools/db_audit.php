<?php
declare(strict_types=1);

/**
 * admin/tools/db_audit.php
 * ------------------------------------------------------------
 * أداة فحص قاعدة البيانات لسكربت Godyar (متوافقة مع schema v3.32)
 *
 * ✅ لا تعتمد على information_schema (مناسب للاستضافات المشتركة)
 * ✅ تتحقق من وجود الجداول/الأعمدة الأساسية + تقترح تحسينات
 * ✅ مخرجات: HTML افتراضي، أو JSON (?format=json) أو SQL (?format=sql)
 *
 * ملاحظة أمنية: بعد الاستخدام يفضّل حذف الملف أو تقييده.
 */

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

// i18n (لو موجود)
$__i18n = __DIR__ . '/../i18n.php';
if (is_file($__i18n)) {
    require_once $__i18n;
}

/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo 'DB connection unavailable';
    exit;
}

// صلاحيات بسيطة: admin / super_admin
$role = (string)(($_SESSION['user']['role'] ?? '') ?: ($_SESSION['user']['role_name'] ?? ''));
$allowed = in_array($role, ['admin', 'super_admin'], true);
if (!$allowed) {
    http_response_code(403);
    echo __('t_ae924aef0c', 'يجب أن تكون مديراً لاستخدام أداة فحص قاعدة البيانات.');
    exit;
}

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/**
 * Helper: get tables list (SHOW TABLES)
 * @return string[]
 */
function db_tables(PDO $pdo): array {
    $stmt = $pdo->query('SHOW TABLES');
    return $stmt ? ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];
}

/**
 * Helper: get columns list for a table (SHOW COLUMNS)
 * @return string[]
 */
function db_columns(PDO $pdo, string $table): array {
    $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
    return $stmt ? ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];
}

/**
 * Helper: get index info for a table
 * @return array<int,array<string,mixed>>
 */
function db_indexes(PDO $pdo, string $table): array {
    $stmt = $pdo->query('SHOW INDEX FROM `' . str_replace('`', '``', $table) . '`');
    return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
}

/**
 * Helper: get table status (Engine/Collation/Rows)
 * @return array<string,array<string,mixed>> keyed by Name
 */
function db_table_status(PDO $pdo): array {
    $stmt = $pdo->query('SHOW TABLE STATUS');
    $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    $out = [];
    foreach ($rows as $r) {
        if (!empty($r['Name'])) {
            $out[(string)$r['Name']] = $r;
        }
    }
    return $out;
}

/**
 * Defines the expected schema for Godyar CMS v3.32 (aligned with your DB).
 * Each column can be a string (exact) or an array of aliases (any one is acceptable).
 */
$expectedSchema = [
    // الأخبار
    'news' => [
        'required' => [
            'id',
            ['title', 'name'],
            'slug',
            ['content', 'body'],
            ['status', 'state'],
            ['created_at', 'created_on'],
        ],
        'recommended' => [
            'category_id',
            'author_id',
            'published_at',
            'publish_at',
            'deleted_at',
            // SEO (اختياري)
            'seo_title', 'seo_description',
        ],
    ],

    // التصنيفات
    'categories' => [
        'required' => [
            'id',
            ['name', 'title'],
            ['slug'],
        ],
        'recommended' => [
            'parent_id',
            'display_order',
            'created_at',
        ],
    ],

    // الوسوم
    'tags' => [
        'required' => ['id', ['name', 'title'], 'slug'],
        'recommended' => ['created_at'],
    ],

    // ربط الأخبار بالوسوم
    'news_tags' => [
        'required' => [
            ['news_id', 'post_id'],
            ['tag_id'],
        ],
        'recommended' => [],
    ],

    // المستخدمون
    'users' => [
        'required' => [
            'id',
            ['email'],
            // اسم مستخدم/عرض (يختلف حسب النسخة)
            ['username', 'name', 'display_name'],
            // كلمة المرور: password_hash أو password
            ['password_hash', 'password'],
            ['role'],
        ],
        'recommended' => [
            'status',
            'created_at',
            'last_login_at',
            'last_login_ip',
            // ميزات Ultra Pack
            'twofa_enabled', 'twofa_secret',
            'session_version',
        ],
    ],

    // التعليقات (قد يكون لديك comments + news_comments)
    'comments' => [
        'required' => [
            'id',
            ['news_id', 'post_id'],
            // نص التعليق
            ['body', 'comment', 'content'],
            ['created_at', 'created_on'],
        ],
        'recommended' => [
            'user_id',
            ['name', 'author_name'],
            ['email', 'author_email'],
            'status',
            'ip',
            'user_agent',
            'parent_id',
        ],
    ],

    // التعليقات المرتبطة بالأخبار في بعض النسخ
    'news_comments' => [
        'required' => [
            'id',
            ['news_id', 'post_id'],
            ['body', 'comment', 'content'],
            ['created_at', 'created_on'],
        ],
        'recommended' => [
            'user_id',
            ['name', 'author_name'],
            ['email', 'author_email'],
            'status',
            'ip',
            'user_agent',
            'parent_id',
        ],
    ],

    // الإعلانات
    'ads' => [
        'required' => [
            'id',
            ['title', 'name'],
        ],
        'recommended' => [
            ['code', 'html'],
            ['placement', 'position', 'location'],
            ['starts_at', 'start_at'],
            ['ends_at', 'end_at'],
            'status',
            'created_at',
        ],
    ],

    // كتّاب الرأي
    'opinion_authors' => [
        'required' => ['id', ['name', 'title']],
        'recommended' => ['slug', 'bio', 'created_at', 'updated_at'],
    ],

    // رسائل التواصل
    'contact_messages' => [
        'required' => ['id', ['name'], ['email'], ['message', 'content']],
        'recommended' => ['subject', 'created_at', 'replied_at', 'replied_by'],
    ],

    // صفحات ثابتة
    'pages' => [
        'required' => ['id', ['title', 'name'], 'slug', ['content', 'body']],
        'recommended' => ['status', 'created_at', 'updated_at'],
    ],

    // مكتبة الوسائط (اسمها عندك media وليس media_files)
    'media' => [
        'required' => ['id'],
        'recommended' => [
            ['file_name', 'filename', 'name'],
            ['file_type', 'type', 'mime'],
            ['created_at', 'uploaded_at'],
        ],
    ],

    // الإعدادات
    'settings' => [
        'required' => ['id', ['key', 'name'], 'value'],
        'recommended' => ['group_name', 'updated_at'],
    ],

    // الصلاحيات (تصميمك الحالي يعتمد role_permissions)
    'roles' => [
        'required' => ['id', ['name']],
        'recommended' => ['label', 'created_at', 'updated_at'],
    ],
    'permissions' => [
        'required' => ['id', ['name']],
        'recommended' => ['label', 'category', 'is_active', 'created_at', 'updated_at'],
    ],
    'role_permissions' => [
        'required' => ['id', ['role_id'], ['permission_key', 'permission', 'permission_id']],
        'recommended' => ['created_at'],
    ],
];

// Collect actual info
$tables = db_tables($pdo);
$tablesLower = array_map('strtolower', $tables);
$status = db_table_status($pdo);

// Audit results per table
$results = [];
$sqlFixes = [];

/**
 * Check if any of aliases exists
 */
$hasAny = function(array $existingCols, $colOrAliases): bool {
    if (is_array($colOrAliases)) {
        foreach ($colOrAliases as $c) {
            if (in_array($c, $existingCols, true)) return true;
        }
        return false;
    }
    return in_array((string)$colOrAliases, $existingCols, true);
};

foreach ($expectedSchema as $table => $rules) {
    $tableLower = strtolower($table);
    $exists = in_array($tableLower, $tablesLower, true);
    $realName = $exists ? $tables[array_search($tableLower, $tablesLower, true)] : $table;

    $row = [
        'table' => $table,
        'exists' => $exists,
        'missing_required' => [],
        'missing_recommended' => [],
        'extra_columns' => [],
        'engine' => null,
        'collation' => null,
        'rows' => null,
    ];

    if ($exists) {
        $cols = [];
        try {
            $cols = db_columns($pdo, $realName);
        } catch (Throwable $e) {
            $row['error'] = $e->getMessage();
        }

        $required = (array)($rules['required'] ?? []);
        $recommended = (array)($rules['recommended'] ?? []);

        foreach ($required as $c) {
            if (!$hasAny($cols, $c)) {
                $row['missing_required'][] = is_array($c) ? implode(' | ', $c) : (string)$c;
            }
        }
        foreach ($recommended as $c) {
            if (!$hasAny($cols, $c)) {
                $row['missing_recommended'][] = is_array($c) ? implode(' | ', $c) : (string)$c;
            }
        }

        // extras = cols not listed in required/recommended exact names (informational)
        $flatExpected = [];
        foreach (array_merge($required, $recommended) as $c) {
            if (is_array($c)) {
                foreach ($c as $a) $flatExpected[] = (string)$a;
            } else {
                $flatExpected[] = (string)$c;
            }
        }
        foreach ($cols as $c) {
            if (!in_array($c, $flatExpected, true)) {
                $row['extra_columns'][] = $c;
            }
        }

        // engine/collation/rows
        if (isset($status[$realName])) {
            $row['engine'] = (string)($status[$realName]['Engine'] ?? '');
            $row['collation'] = (string)($status[$realName]['Collation'] ?? '');
            $row['rows'] = (int)($status[$realName]['Rows'] ?? 0);
        }

        // SQL suggestions (only for recommended safe fields)
        if ($table === 'categories' && in_array('display_order', $row['missing_recommended'], true)) {
            $sqlFixes[] = "ALTER TABLE `categories` ADD COLUMN `display_order` INT(11) NULL DEFAULT 0;";
        }
        if ($table === 'settings' && in_array('group_name', $row['missing_recommended'], true)) {
            $sqlFixes[] = "ALTER TABLE `settings` ADD COLUMN `group_name` VARCHAR(100) NULL DEFAULT NULL;";
        }

    } else {
        // Missing table — propose only if it's core and safe.
        if ($table === 'media') {
            // Only propose if both media and media_files are missing.
            if (!in_array('media', $tablesLower, true) && !in_array('media_files', $tablesLower, true)) {
                $sqlFixes[] = "CREATE TABLE IF NOT EXISTS `media` (\n" .
                    "  `id` INT(11) NOT NULL AUTO_INCREMENT,\n" .
                    "  `file_name` VARCHAR(255) NULL,\n" .
                    "  `file_type` VARCHAR(100) NULL,\n" .
                    "  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,\n" .
                    "  PRIMARY KEY (`id`)\n" .
                    ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            }
        }
    }

    $results[] = $row;
}

// Unknown tables (not included in expected schema)
$expectedKeysLower = array_map('strtolower', array_keys($expectedSchema));
$unknown = [];
foreach ($tables as $t) {
    if (!in_array(strtolower($t), $expectedKeysLower, true)) {
        $unknown[] = $t;
    }
}

// Recommendations
$recommendations = [
    'db' => [],
    'security' => [],
    'admin_features' => [],
];

// Collation consistency
$nonUnicode = [];
foreach ($status as $tbl => $st) {
    $coll = (string)($st['Collation'] ?? '');
    if ($coll !== '' && $coll !== 'utf8mb4_unicode_ci') {
        $nonUnicode[] = [$tbl, $coll];
    }
}
if ($nonUnicode) {
    $recommendations['db'][] = 'يوجد جداول Collation مختلفة عن utf8mb4_unicode_ci: ' . implode(', ', array_map(fn($x) => $x[0] . ' (' . $x[1] . ')', $nonUnicode));
    $recommendations['db'][] = 'يفضّل توحيد Collation للجداول النصية إلى utf8mb4_unicode_ci لتحسين العربية والترتيب.';
}

// Engines
$nonInno = [];
foreach ($status as $tbl => $st) {
    $eng = (string)($st['Engine'] ?? '');
    if ($eng !== '' && strtolower($eng) !== 'innodb') {
        $nonInno[] = [$tbl, $eng];
    }
}
if ($nonInno) {
    $recommendations['db'][] = 'يوجد جداول ليست InnoDB: ' . implode(', ', array_map(fn($x) => $x[0] . ' (' . $x[1] . ')', $nonInno));
}

// Security suggestions (based on known columns)
$usersCols = in_array('users', $tablesLower, true) ? db_columns($pdo, $tables[array_search('users', $tablesLower, true)]) : [];
if ($usersCols) {
    if (in_array('twofa_enabled', $usersCols, true) && in_array('twofa_secret', $usersCols, true)) {
        $recommendations['security'][] = 'يمكنك فرض 2FA للأدوار الإدارية (admin/super_admin) مع صفحة إعدادات 2FA.';
    }
    if (in_array('session_version', $usersCols, true)) {
        $recommendations['security'][] = 'Session invalidation مفعّل عبر session_version: استخدم Logout all devices لطرد الجلسات القديمة.';
    }
    if (in_array('last_login_ip', $usersCols, true)) {
        $recommendations['security'][] = 'تتبّع IP لآخر دخول متوفر (last_login_ip). اعرضه في صفحة المستخدم لمراجعة أمنية.';
    }
}

// Admin features
if (in_array('admin_saved_filters', $tablesLower, true)) {
    $recommendations['admin_features'][] = 'Saved Filters: فعّل زر حفظ/استدعاء الفلاتر في صفحات القوائم (news/users/comments/tags/categories).';
}
if (in_array('admin_notifications', $tablesLower, true)) {
    $recommendations['admin_features'][] = 'Notifications: أضف إشعارات عند: تعليق مشتبه، نشر مجدول، تغيير صلاحية مستخدم، bulk actions.';
}
if (in_array('admin_audit_log', $tablesLower, true)) {
    $recommendations['security'][] = 'Audit Log: سجّل الأحداث الحساسة (حذف/تغيير صلاحيات/تسجيل دخول) في admin_audit_log.';
}

// Output
$format = strtolower((string)($_GET['format'] ?? 'html'));

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'results' => $results,
        'unknown_tables' => $unknown,
        'recommendations' => $recommendations,
        'sql_suggestions' => $sqlFixes,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($format === 'sql') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "-- Review carefully before running\n\n";
    if (!$sqlFixes) {
        echo "-- No safe SQL suggestions detected.\n";
    } else {
        foreach ($sqlFixes as $s) {
            echo $s . "\n\n";
        }
    }
    exit;
}

// HTML
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>DB Audit — Godyar</title>
  <link href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css" rel="stylesheet">
  <style>
    body{ background:#0b1220; color:#e5e7eb; }
    .card{ background:#0f172a; border:1px solid rgba(148,163,184,.25); }
    .table{ color:#e5e7eb; }
    .table thead th{ background:#0b1220; color:#cbd5e1; border-color:rgba(148,163,184,.25); }
    .table td{ border-color:rgba(148,163,184,.15); }
    .badge-soft{ background:rgba(34,197,94,.15); color:#86efac; border:1px solid rgba(34,197,94,.35); }
    .badge-warn{ background:rgba(245,158,11,.15); color:#fdba74; border:1px solid rgba(245,158,11,.35); }
    .badge-danger{ background:rgba(239,68,68,.15); color:#fca5a5; border:1px solid rgba(239,68,68,.35); }
    code{ color:#eab308; }
  </style>
</head>
<body>
  <div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h3 class="mb-1">أداة فحص قاعدة البيانات</h3>
        <div class="text-secondary">تتحقق هذه الأداة من وجود الجداول والأعمدة الأساسية لسكربت Godyar وتعرض أي نقص محتمل.</div>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-light btn-sm" href="?format=json">JSON</a>
        <a class="btn btn-outline-warning btn-sm" href="?format=sql">SQL Suggestions</a>
      </div>
    </div>

    <div class="card p-3 mb-3">
      <h5 class="mb-3">نتيجة الفحص حسب الجداول المتوقعة</h5>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>الجدول</th>
              <th>الحالة</th>
              <th>أعمدة ناقصة (أساسية)</th>
              <th>أعمدة ناقصة (مقترحة)</th>
              <th>Engine</th>
              <th>Collation</th>
              <th>Rows</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($results as $r): ?>
              <tr>
                <td><code><?= h($r['table']) ?></code></td>
                <td>
                  <?php if (!$r['exists']): ?>
                    <span class="badge badge-danger">غير موجود</span>
                  <?php elseif (!empty($r['missing_required'])): ?>
                    <span class="badge badge-danger">ناقص</span>
                  <?php elseif (!empty($r['missing_recommended'])): ?>
                    <span class="badge badge-warn">موجود (تحسينات)</span>
                  <?php else: ?>
                    <span class="badge badge-soft">مكتمل</span>
                  <?php endif; ?>
                </td>
                <td><?= $r['missing_required'] ? h(implode(', ', $r['missing_required'])) : '—' ?></td>
                <td><?= $r['missing_recommended'] ? h(implode(', ', $r['missing_recommended'])) : '—' ?></td>
                <td><?= h((string)($r['engine'] ?? '—')) ?></td>
                <td><?= h((string)($r['collation'] ?? '—')) ?></td>
                <td><?= h((string)($r['rows'] ?? '—')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card p-3 mb-3">
      <h5 class="mb-2">جداول موجودة في قاعدة البيانات وغير معرفة في الأداة</h5>
      <div class="text-secondary small mb-2">هذه الجداول ليست ضمن قائمة "الأساسيات"—غالبًا تخص وحدات إضافية (مثل elections/weather/news_*). راجعها فقط إذا كنت تشك بوجود بقايا قديمة.</div>
      <div class="small">
        <?php if (!$unknown): ?>
          —
        <?php else: ?>
          <?= h(implode('  ', $unknown)) ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="card p-3">
      <h5 class="mb-2">اقتراحات تحسين وميزات</h5>
      <div class="row g-3">
        <div class="col-12 col-lg-4">
          <div class="fw-semibold mb-1">db</div>
          <ul class="mb-0">
            <?php if (!empty($recommendations['db'])): foreach ($recommendations['db'] as $t): ?>
              <li><?= h($t) ?></li>
            <?php endforeach; else: ?>
              <li>لا توجد ملاحظات DB حرجة.</li>
            <?php endif; ?>
          </ul>
        </div>
        <div class="col-12 col-lg-4">
          <div class="fw-semibold mb-1">security</div>
          <ul class="mb-0">
            <?php if (!empty($recommendations['security'])): foreach ($recommendations['security'] as $t): ?>
              <li><?= h($t) ?></li>
            <?php endforeach; else: ?>
              <li>لا توجد ملاحظات أمنية حرجة.</li>
            <?php endif; ?>
          </ul>
        </div>
        <div class="col-12 col-lg-4">
          <div class="fw-semibold mb-1">admin_features</div>
          <ul class="mb-0">
            <?php if (!empty($recommendations['admin_features'])): foreach ($recommendations['admin_features'] as $t): ?>
              <li><?= h($t) ?></li>
            <?php endforeach; else: ?>
              <li>لا توجد ملاحظات ميزات حرجة.</li>
            <?php endif; ?>
          </ul>
        </div>
      </div>

      <hr class="border-secondary" />
      <div class="small text-secondary">
        يمكنك أيضًا فتحها كنص فقط عبر: <code>?format=sql</code> أو JSON: <code>?format=json</code>.
        <br />
        بعد الانتهاء يفضّل حذف هذا الملف أو حمايته.
      </div>
    </div>
  </div>

  <script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
