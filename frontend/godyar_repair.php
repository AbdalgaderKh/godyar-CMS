<?php
declare(strict_types=1);

/**
 * Godyar CMS - Repair / Diagnostic Tool
 * --------------------------------------
 * ضع هذا الملف في: /godyar/frontend/godyar_repair.php
 * افتحه مثلاً: https://example.com/godyar/frontend/godyar_repair.php?key=12345
 * ولا تنسَ حذف الملف أو تعطيله بعد الانتهاء.
 */

//////////////////// إعدادات بسيطة ////////////////////

// ✅ كلمة المرور
$REPAIR_PASSWORD = '12345';

//////////////////// حماية السكربت ////////////////////

if ($REPAIR_PASSWORD !== '') {
    $given = $_GET['key'] ?? $_POST['key'] ?? '';
    if ($given !== $REPAIR_PASSWORD) {
        header('HTTP/1.1 403 Forbidden');
        echo 'ممنوع الوصول إلى أداة الصيانة. أضف ?key=PASSWORD إلى الرابط بعد تغيير المتغير $REPAIR_PASSWORD.';
        exit;
    }
}

//////////////////// تحديد الجذر و مسارات المشروع ////////////////////

// هذا الملف موجود في: /godyar/frontend/godyar_repair.php
$frontendPath = __DIR__;                 // /godyar/frontend
$projectRoot  = dirname($frontendPath);  // /godyar

//////////////////// تحميل bootstrap و الاتصال بقاعدة البيانات ////////////////////

// bootstrap كما في NewsController: __DIR__ . '/../../includes/bootstrap.php'
$bootstrapPath = $projectRoot . '/includes/bootstrap.php';

if (!is_file($bootstrapPath)) {
    die('لم يتم العثور على ملف bootstrap.php في: ' . htmlspecialchars($bootstrapPath, ENT_QUOTES, 'UTF-8'));
}

require_once $bootstrapPath;

$pdo = gdy_pdo_safe();

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

//////////////////// دوال مساعدة ////////////////////

/**
 * صناعة slug بسيط من العنوان (يدعم العربي والإنجليزي)
 */
function slugify_title(string $title): string
{
    $title = trim($title);
    if ($title === '') {
        return '';
    }

    // استبدال المسافات بـ -
    $title = preg_replace('/\s+/u', '-', $title);

    // السماح بالحروف (عربية + لاتينية) + أرقام + الشرطة
    $title = preg_replace('/[^\p{Arabic}\p{L}\p{N}\-]+/u', '', $title);

    // إزالة التكرارات للشرطة
    $title = preg_replace('/-+/u', '-', $title);

    // إزالة الشرطة من البداية والنهاية
    $title = trim($title, '-');

    return $title ?: '';
}

/**
 * إيجاد جدول حقيقي في قاعدة البيانات بناءً على اسم منطقي (news, categories, ...)
 * نحاول:
 *  - اسم مطابق تماماً
 *  - أو جدول ينتهي بالاسم (مثلاً godyar_news أو cms_news)
 *  - أو أي جدول يحتوي الاسم (للـ diagnostic فقط)
 */
function gdy_find_table(PDO $pdo, string $logicalName): ?string
{
    try {
        // دالة صغيرة تبني وتنفذ SHOW TABLES LIKE بدون prepared
        $runShowLike = function(string $pattern) use ($pdo): ?string {
            // نهرب % و _ حتى لا تتعامل كـ wildcards زيادة
            $pattern = str_replace(['%', '_'], ['\%', '\_'], $pattern);
            $sql     = 'SHOW TABLES LIKE ' . $pdo->quote($pattern);
            $stmt    = $pdo->query($sql);
            if (!$stmt) {
                return null;
            }
            $found = $stmt->fetchColumn();
            return $found ? (string)$found : null;
        };

        // 1) اسم مطابق تماماً
        $found = $runShowLike($logicalName);
        if ($found) {
            return $found;
        }

        // 2) جدول ينتهي بالاسم المنطقي (prefix_ + name)
        $found = $runShowLike('%_' . $logicalName);
        if ($found) {
            return $found;
        }

        // 3) أي جدول يحتوي الاسم (للـ diagnostic)
        $found = $runShowLike('%' . $logicalName . '%');
        if ($found) {
            return $found;
        }

        return null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * دالة متوافقة قديمة (لو محتاج boolean فقط)
 */
function gdy_table_exists(PDO $pdo, string $table): bool
{
    return gdy_find_table($pdo, $table) !== null;
}

$messages        = [];
$errors          = [];
$action          = $_POST['action'] ?? '';
$lookupResult    = null;
$dbOk            = $pdo instanceof PDO;
$tablesCheck     = [];
$resolvedTables  = []; // logicalName => realTableName (أو null)
$allTables       = [];
$newsTable       = null;
$newsEmptySlugRows = [];
$newsProblemSlugs  = [];

//////////////////// جلب قائمة الجداول وتحديد أسماء الجداول الفعلية ////////////////////

if ($dbOk) {
    try {
        $stmt      = $pdo->query('SHOW TABLES');
        $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $e) {
        $errors[] = 'تعذر جلب قائمة الجداول: ' . $e->getMessage();
    }

    $coreTables = ['news', 'categories', 'tags', 'news_tags', 'ads', 'settings'];

    foreach ($coreTables as $logical) {
        $realName = gdy_find_table($pdo, $logical);
        $resolvedTables[$logical] = $realName;
        $tablesCheck[$logical]    = $realName !== null;
    }

    $newsTable = $resolvedTables['news'] ?? null;
}

//////////////////// تنفيذ الإصلاحات ////////////////////

if ($dbOk && $newsTable) {

    // 1) إصلاح كل الأخبار التي لا تحتوي على slug
    if ($action === 'fix_empty_slugs') {
        try {
            $stmt = $pdo->query("
                SELECT id, title, slug
                FROM `{$newsTable}`
                WHERE (slug IS NULL OR slug = '')
                  AND title IS NOT NULL AND title <> ''
            ");

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$rows) {
                $messages[] = 'لا توجد أخبار بدون slug لإصلاحها.';
            } else {
                $updated = 0;
                $pdo->beginTransaction();
                foreach ($rows as $row) {
                    $newSlug = slugify_title($row['title']);
                    if ($newSlug === '') {
                        continue;
                    }

                    // تجنب التكرار
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM `{$newsTable}` WHERE slug = :slug AND id != :id");
                    $checkStmt->execute([
                        ':slug' => $newSlug,
                        ':id'   => (int)$row['id'],
                    ]);
                    if ((int)$checkStmt->fetchColumn() > 0) {
                        $newSlug .= '-' . (int)$row['id'];
                    }

                    $upd = $pdo->prepare("UPDATE `{$newsTable}` SET slug = :slug WHERE id = :id");
                    $upd->execute([
                        ':slug' => $newSlug,
                        ':id'   => (int)$row['id'],
                    ]);
                    $updated++;
                }
                $pdo->commit();
                $messages[] = "تم إصلاح slug لعدد {$updated} خبر/أخبار بنجاح.";
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'خطأ أثناء إصلاح slugs: ' . $e->getMessage();
        }
    }

    // 2) إصلاح slug لخبر واحد
    if ($action === 'fix_one_slug') {
        $id      = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $newSlug = isset($_POST['new_slug']) ? trim((string)$_POST['new_slug']) : '';

        if ($id <= 0 || $newSlug === '') {
            $errors[] = 'بيانات غير صحيحة لإصلاح slug لخبر واحد.';
        } else {
            try {
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM `{$newsTable}` WHERE slug = :slug AND id != :id");
                $checkStmt->execute([
                    ':slug' => $newSlug,
                    ':id'   => $id,
                ]);
                if ((int)$checkStmt->fetchColumn() > 0) {
                    $newSlug .= '-' . $id;
                }

                $upd = $pdo->prepare("UPDATE `{$newsTable}` SET slug = :slug WHERE id = :id");
                $upd->execute([
                    ':slug' => $newSlug,
                    ':id'   => $id,
                ]);
                $messages[] = "تم تحديث slug للخبر رقم {$id} إلى: {$newSlug}";
            } catch (Throwable $e) {
                $errors[] = 'خطأ أثناء تحديث slug لخبر واحد: ' . $e->getMessage();
            }
        }
    }

    // 3) نشر خبر معيّن
    if ($action === 'publish_one_news') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            $errors[] = 'رقم خبر غير صحيح للنشر.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE `{$newsTable}`
                    SET status = 'published',
                        published_at = IF(published_at IS NULL OR published_at = '0000-00-00 00:00:00', NOW(), published_at)
                    WHERE id = :id
                    LIMIT 1
                ");
                $stmt->execute([':id' => $id]);
                if ($stmt->rowCount() > 0) {
                    $messages[] = "تم نشر الخبر رقم {$id} (أو هو منشور أصلاً).";
                } else {
                    $errors[] = "لم يتم العثور على خبر برقم {$id} أو لم يحدث أي تعديل.";
                }
            } catch (Throwable $e) {
                $errors[] = 'خطأ أثناء نشر الخبر: ' . $e->getMessage();
            }
        }
    }

    // 4) بحث عن خبر (ID أو slug) وعرض بياناته
    if ($action === 'lookup_news') {
        $identifier = trim((string)($_POST['identifier'] ?? ''));
        if ($identifier === '') {
            $errors[] = 'الرجاء إدخال ID أو slug للبحث.';
        } else {
            try {
                $isNumeric = ctype_digit($identifier);
                if ($isNumeric) {
                    $sql = "SELECT * FROM `{$newsTable}` WHERE id = :id LIMIT 1";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([':id' => (int)$identifier]);
                } else {
                    $sql = "SELECT * FROM `{$newsTable}` WHERE slug = :slug LIMIT 1";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([':slug' => $identifier]);
                }
                $lookupResult = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

                if (!$lookupResult) {
                    $errors[] = 'لم يتم العثور على خبر مطابق.';
                }
            } catch (Throwable $e) {
                $errors[] = 'خطأ أثناء البحث عن الخبر: ' . $e->getMessage();
            }
        }
    }

    // إعادة حساب الأخبار بدون slug والمختلفة بعد أي تعديل
    try {
        $stmt = $pdo->query("
            SELECT id, title, slug
            FROM `{$newsTable}`
            WHERE (slug IS NULL OR slug = '')
              AND title IS NOT NULL AND title <> ''
            ORDER BY id DESC
            LIMIT 50
        ");
        $newsEmptySlugRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmt2 = $pdo->query("
            SELECT id, title, slug
            FROM `{$newsTable}`
            WHERE slug IS NOT NULL AND slug <> ''
              AND title IS NOT NULL AND title <> ''
            ORDER BY id DESC
            LIMIT 50
        ");
        $allRows = $stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($allRows as $r) {
            $expected = slugify_title($r['title']);
            if ($expected && $expected !== $r['slug']) {
                $newsProblemSlugs[] = [
                    'id'       => $r['id'],
                    'title'    => $r['title'],
                    'slug'     => $r['slug'],
                    'expected' => $expected,
                ];
            }
        }
    } catch (Throwable $e) {
        $errors[] = 'خطأ أثناء فحص جدول الأخبار: ' . $e->getMessage();
    }
} elseif ($dbOk && !$newsTable) {
    $errors[] = 'لم يتم العثور على جدول الأخبار (news). تأكد من وجود جدول يحمل هذا الاسم أو ينتهي بـ "news".';
}

//////////////////// فحوصات بيئة PHP و الملفات ////////////////////

$phpVersion  = PHP_VERSION;
$extensions  = [
    'pdo'       => extension_loaded('pdo'),
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'mbstring'  => extension_loaded('mbstring'),
    'json'      => extension_loaded('json'),
];

// فحص الملفات الأساسية
$filesCheck = [
    // includes من جذر المشروع
    'includes/bootstrap.php'      => is_file($projectRoot . '/includes/bootstrap.php'),
    'includes/TemplateEngine.php' => is_file($projectRoot . '/includes/TemplateEngine.php'),
    'includes/site_settings.php'  => is_file($projectRoot . '/includes/site_settings.php'),

    // controllers من داخل frontend
    'frontend/controllers/HomeController.php'     => is_file($frontendPath . '/controllers/HomeController.php'),
    'frontend/controllers/NewsController.php'     => is_file($frontendPath . '/controllers/NewsController.php'),
    'frontend/controllers/CategoryController.php' => is_file($frontendPath . '/controllers/CategoryController.php'),

    // views من داخل frontend
    'frontend/views/news_detail.php' => is_file($frontendPath . '/views/news_detail.php'),
    'frontend/views/category.php'    => is_file($frontendPath . '/views/category.php'),

    // htaccess (جذر المشروع أو frontend)
    '.htaccess (جذر المشروع أو frontend)' =>
        is_file($projectRoot . '/.htaccess') || is_file($frontendPath . '/.htaccess'),
];

?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>Godyar Repair Tool - أداة فحص/إصلاح</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f3f4f6;
            margin: 0;
            padding: 0;
        }
        .wrap {
            max-width: 1150px;
            margin: 2rem auto;
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 15px 45px rgba(15,23,42,.12);
            padding: 1.5rem 1.8rem 2rem;
        }
        h1 {
            margin-top: 0;
            font-size: 1.6rem;
        }
        h2 {
            font-size: 1.1rem;
            margin-top: 1.4rem;
        }
        h3 {
            font-size: .95rem;
        }
        .badge {
            display: inline-block;
            padding: .15rem .5rem;
            border-radius: .75rem;
            font-size: .75rem;
        }
        .badge-ok { background:#dcfce7; color:#15803d; }
        .badge-bad { background:#fee2e2; color:#b91c1c; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: .6rem;
        }
        th, td {
            border: 1px solid #e5e7eb;
            padding: .45rem .5rem;
            font-size: .85rem;
        }
        th {
            background: #f9fafb;
            text-align: right;
        }
        .messages, .errors {
            margin: .7rem 0;
            padding: .6rem .8rem;
            border-radius: .5rem;
            font-size: .85rem;
        }
        .messages { background:#ecfdf5; color:#166534; }
        .errors { background:#fef2f2; color:#b91c1c; }
        .btn {
            display: inline-block;
            padding: .4rem .9rem;
            border-radius: .6rem;
            border: 1px solid #d1d5db;
            background:#111827;
            color:#f9fafb;
            font-size:.85rem;
            cursor:pointer;
        }
        .btn-secondary {
            background:#f9fafb;
            color:#111827;
        }
        .btn-xs {
            padding: .25rem .6rem;
            font-size: .78rem;
        }
        .small {
            font-size:.8rem;
            color:#6b7280;
        }
        .section {
            margin-top: 1.5rem;
            padding-top: .7rem;
            border-top: 1px dashed #e5e7eb;
        }
        input[type="text"] {
            padding: .35rem .5rem;
            border-radius: .4rem;
            border: 1px solid #d1d5db;
            font-size: .85rem;
            min-width: 200px;
        }
        code {
            background:#f3f4f6;
            padding:0 .25rem;
            border-radius:.25rem;
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>🛠 أداة فحص/إصلاح Godyar</h1>
    <p class="small">
        يُنصح بقوة بحذف هذا الملف أو تغييره بعد الانتهاء، وعدم تركه متاحاً للجميع.
    </p>

    <?php if ($messages): ?>
        <div class="messages">
            <?php foreach ($messages as $m): ?>
                <div>✅ <?= h($m) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="errors">
            <?php foreach ($errors as $e): ?>
                <div>⚠️ <?= h($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="section">
        <h2>1. فحص بيئة PHP</h2>
        <p>نسخة PHP: <strong><?= h($phpVersion) ?></strong></p>
        <table>
            <tr>
                <th>الامتداد</th>
                <th>الحالة</th>
            </tr>
            <?php foreach ($extensions as $ext => $ok): ?>
                <tr>
                    <td><?= h($ext) ?></td>
                    <td>
                        <?php if ($ok): ?>
                            <span class="badge badge-ok">موجود</span>
                        <?php else: ?>
                            <span class="badge badge-bad">مفقود</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="section">
        <h2>2. فحص الاتصال بقاعدة البيانات</h2>
        <p>
            الحالة:
            <?php if ($dbOk): ?>
                <span class="badge badge-ok">متصل</span>
            <?php else: ?>
                <span class="badge badge-bad">غير متصل - راجع إعدادات bootstrap / الاتصال بـ PDO</span>
            <?php endif; ?>
        </p>
    </div>

    <div class="section">
        <h2>3. فحص الملفات والمسارات الأساسية</h2>
        <table>
            <tr>
                <th>المسار</th>
                <th>الحالة</th>
            </tr>
            <?php foreach ($filesCheck as $file => $ok): ?>
                <tr>
                    <td><?= h($file) ?></td>
                    <td>
                        <?php if ($ok): ?>
                            <span class="badge badge-ok">موجود</span>
                        <?php else: ?>
                            <span class="badge badge-bad">مفقود / المسار غير صحيح</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <?php if ($dbOk && $allTables): ?>
        <div class="section">
            <h2>4. قائمة كل الجداول في قاعدة البيانات الحالية</h2>
            <p class="small">هذه الجداول الموجودة فعلياً في قاعدة البيانات التي يتصل بها السكربت الآن:</p>
            <table>
                <tr>
                    <th>#</th>
                    <th>اسم الجدول</th>
                </tr>
                <?php foreach ($allTables as $i => $tName): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><code><?= h($tName) ?></code></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>

    <div class="section">
        <h2>5. فحص الجداول الأساسية في قاعدة البيانات</h2>
        <?php if (!$dbOk): ?>
            <p class="small">غير متصل بقاعدة البيانات، لا يمكن فحص الجداول.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>الاسم المنطقي</th>
                    <th>الجدول الفعلي</th>
                    <th>الحالة</th>
                </tr>
                <?php foreach ($tablesCheck as $logical => $ok): ?>
                    <tr>
                        <td><?= h($logical) ?></td>
                        <td>
                            <?php if (!empty($resolvedTables[$logical])): ?>
                                <code><?= h($resolvedTables[$logical]) ?></code>
                            <?php else: ?>
                                <span class="small">غير محدد</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($ok): ?>
                                <span class="badge badge-ok">موجود</span>
                            <?php else: ?>
                                <span class="badge badge-bad">غير موجود</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <?php if ($dbOk && $newsTable): ?>
        <div class="section">
            <h2>6. فحص/إصلاح slugs في جدول الأخبار (<?= h($newsTable) ?>)</h2>

            <h3>6.1 أخبار بدون slug (إصلاح جماعي بضغطة زر)</h3>
            <?php if (!$newsEmptySlugRows): ?>
                <p class="small">لا توجد أخبار بدون slug.</p>
            <?php else: ?>
                <p class="small">
                    سيتم توليد slug من العنوان (title) باستخدام قاعدة بسيطة:
                    استبدال المسافات بـ <code>-</code> وحذف الرموز الغريبة.
                </p>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>العنوان</th>
                        <th>slug الحالي</th>
                        <th>slug المقترح</th>
                    </tr>
                    <?php foreach ($newsEmptySlugRows as $row): ?>
                        <?php $expected = slugify_title($row['title']); ?>
                        <tr>
                            <td><?= (int)$row['id'] ?></td>
                            <td><?= h($row['title']) ?></td>
                            <td><em>فارغ</em></td>
                            <td><?= h($expected ?: 'لا يمكن توليد slug من العنوان') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <form method="post" style="margin-top:.8rem;">
                    <input type="hidden" name="key" value="<?= h($REPAIR_PASSWORD) ?>">
                    <input type="hidden" name="action" value="fix_empty_slugs">
                    <button type="submit" class="btn">
                        ✅ إصلاح جميع الأخبار التي لا تحتوي على slug تلقائياً
                    </button>
                </form>
            <?php endif; ?>

            <h3 style="margin-top:1.4rem;">6.2 أخبار slug مختلف عن slug المتوقع من العنوان (إصلاح فردي سريع)</h3>
            <p class="small">
                هذه الأخبار قد يكون slug فيها لا يطابق العنوان. يمكنك إصلاح كل خبر منها
                فردياً بحيث يصبح slug = slug المتوقع من العنوان.
            </p>
            <?php if (!$newsProblemSlugs): ?>
                <p class="small">لا توجد حالات ظاهرة في أول 50 خبر.</p>
            <?php else: ?>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>العنوان</th>
                        <th>slug الحالي</th>
                        <th>slug المتوقع</th>
                        <th>إجراء سريع</th>
                    </tr>
                    <?php foreach ($newsProblemSlugs as $row): ?>
                        <tr>
                            <td><?= (int)$row['id'] ?></td>
                            <td><?= h($row['title']) ?></td>
                            <td><?= h($row['slug']) ?></td>
                            <td><?= h($row['expected']) ?></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="key" value="<?= h($REPAIR_PASSWORD) ?>">
                                    <input type="hidden" name="action" value="fix_one_slug">
                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                    <input type="hidden" name="new_slug" value="<?= h($row['expected']) ?>">
                                    <button type="submit" class="btn btn-secondary btn-xs">
                                        إصلاح slug لهذا الخبر
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>7. البحث عن خبر معين وإصلاحه فوراً</h2>
            <p class="small">
                يمكنك البحث عن خبر بـ <strong>ID</strong> أو بـ <strong>slug</strong> (مثلاً:  
                <code>من-الكتابة-إلى-الإدارة-الشاملة-للمحتوى-والذكاء-الاصطناعي</code>)
                ثم تعديل slug أو نشره بضغطة زر.
            </p>

            <form method="post" style="margin-bottom:1rem;">
                <input type="hidden" name="key" value="<?= h($REPAIR_PASSWORD) ?>">
                <input type="hidden" name="action" value="lookup_news">
                <label class="small">
                    ID أو slug:
                    <input type="text" name="identifier" value="<?= h($_POST['identifier'] ?? '') ?>">
                </label>
                <button type="submit" class="btn btn-secondary">🔍 بحث</button>
            </form>

            <?php if (isset($lookupResult) && is_array($lookupResult) && $lookupResult): ?>
                <h3>نتيجة البحث:</h3>
                <table>
                    <tr><th>ID</th><td><?= (int)$lookupResult['id'] ?></td></tr>
                    <tr><th>العنوان</th><td><?= h($lookupResult['title'] ?? '') ?></td></tr>
                    <tr><th>slug الحالي</th><td><?= h($lookupResult['slug'] ?? '') ?></td></tr>
                    <tr><th>الحالة (status)</th><td><?= h($lookupResult['status'] ?? '') ?></td></tr>
                    <tr><th>تاريخ النشر (published_at)</th><td><?= h($lookupResult['published_at'] ?? '') ?></td></tr>
                </table>

                <?php
                $recommendedSlug = slugify_title($lookupResult['title'] ?? '');
                ?>

                <div style="margin-top:.8rem;">
                    <form method="post" style="display:inline-block; margin-left:.5rem;">
                        <input type="hidden" name="key" value="<?= h($REPAIR_PASSWORD) ?>">
                        <input type="hidden" name="action" value="fix_one_slug">
                        <input type="hidden" name="id" value="<?= (int)$lookupResult['id'] ?>">
                        <input type="hidden" name="new_slug" value="<?= h($recommendedSlug) ?>">
                        <button type="submit" class="btn btn-secondary btn-xs">
                            تعيين slug من العنوان (المقترح): <?= h($recommendedSlug) ?>
                        </button>
                    </form>

                    <form method="post" style="display:inline-block; margin-left:.5rem;">
                        <input type="hidden" name="key" value="<?= h($REPAIR_PASSWORD) ?>">
                        <input type="hidden" name="action" value="publish_one_news">
                        <input type="hidden" name="id" value="<?= (int)$lookupResult['id'] ?>">
                        <button type="submit" class="btn btn-secondary btn-xs">
                            نشر هذا الخبر الآن
                        </button>
                    </form>
                </div>

            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="section">
        <h2>8. ملاحظات واقتراحات</h2>
        <ul class="small">
            <li>بعد الانتهاء من الإصلاحات (خاصة slug والأخبار)، يُنصح بحذف هذا الملف أو نقله خارج مجلد frontend.</li>
            <li>يمكنك توسيع السكربت بنفس النمط لإصلاح أشياء أخرى (مثلاً: ضبط <code>status</code> لأخبار معينة، أو إصلاح روابط أقسام).</li>
            <li>من الأفضل أن تعتمد دالة <code>slugify_title()</code> نفسها عند حفظ الأخبار في لوحة التحكم لضمان توحيد الـ slug.</li>
        </ul>
    </div>

</div>
</body>
</html>
