<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
// godyar/admin/news/create.php — إضافة خبر جديد (مع ميزات احترافية)

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/_news_helpers.php';

use Godyar\Auth;

if (!Auth::isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

// صلاحية إنشاء مقال
Auth::requirePermission('posts.create');

$isWriter = Auth::isWriter();
$userId   = (int)($_SESSION['user']['id'] ?? 0);

$currentPage = 'posts';
$pageTitle   = __('t_0d1f6ecf66', 'إضافة خبر جديد');

/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    die('Database not available');
}

// دالة مساعدة للهروب من المخرجات HTML
if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// -----------------------------------------------------------------------------
// فحص إذا كان هناك عمود image في جدول news لاستخدامه اختياريًا
// -----------------------------------------------------------------------------
$imageColumnExists = false;
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM news LIKE 'image'");
    if ($stmt && $stmt->fetch(PDO::FETCH_ASSOC)) {
        $imageColumnExists = true;
    }
} catch (Throwable $e) {
    @error_log('Could not check image column in news: ' . $e->getMessage());
}

// -----------------------------------------------------------------------------
// جلب التصنيفات
// -----------------------------------------------------------------------------
$categories = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    @error_log('Error fetching categories: ' . $e->getMessage());
}

// -----------------------------------------------------------------------------
// جلب كتّاب الرأي (اختياري)
// -----------------------------------------------------------------------------
$opinionAuthors = [];
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'opinion_authors'");
    if ($stmt->fetchColumn()) {
        $stmt2 = $pdo->query("SELECT id, name FROM opinion_authors ORDER BY name ASC");
        $opinionAuthors = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    @error_log('Error fetching opinion_authors: ' . $e->getMessage());
}

// -----------------------------------------------------------------------------
// تهيئة القيم الافتراضية
// -----------------------------------------------------------------------------
$errors      = [];
$title       = '';
$slug        = '';
$excerpt     = '';
$content     = '';
$publish_at   = '';
$unpublish_at = '';
$publish_at_db = null;
$unpublish_at_db = null;
$seo_title = '';
$seo_description = '';
$seo_keywords = '';
$tags        = '';
// الافتراضي: الكاتب يرسل للمراجعة، بينما المدير يبدأ كمسودة (أفضل لسياسة التحرير)
$status      = $isWriter ? 'pending' : 'draft';
$category_id = 0;

// ✅ مهم: سيتم تعيين المؤلف تلقائياً للمستخدم الحالي
$author_id   = $userId;

$opinion_author_id = 0;
$featured    = 0;
$is_breaking = 0;
$published_at = '';
$imagePath   = null;

// -----------------------------------------------------------------------------
// معالجة النموذج
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim((string)($_POST['title'] ?? ''));
    $slug        = trim((string)($_POST['slug'] ?? ''));
    $excerpt     = trim((string)($_POST['excerpt'] ?? ''));
    $content     = (string)($_POST['content'] ?? '');

    $stripFormatting = (int)($_POST['strip_formatting'] ?? 0) === 1;
    $stripBg         = (int)($_POST['strip_bg'] ?? 0) === 1;

    // تنظيف المحتوى قبل الحفظ (Server-side) لضمان عدم رجوع التنسيقات
    if ($stripBg) {
        $content = preg_replace('/\sbgcolor\s*=\s*([\'\"]).*?\1/i', '', $content);
        $content = preg_replace('/background-color\s*:\s*[^;"]+;?/i', '', $content);
    }
    if ($stripFormatting) {
        $content = preg_replace('/\sstyle\s*=\s*([\'\"]).*?\1/i', '', $content);
        $content = preg_replace('/\sclass\s*=\s*([\'\"]).*?\1/i', '', $content);
        $content = preg_replace('/<\/?font[^>]*>/i', '', $content);
    }

    // توليد تلقائي لحقول SEO/Tags إذا كانت فارغة
    if ($seo_title === '' && $title !== '') $seo_title = mb_substr($title, 0, 60);
    if ($seo_description === '') {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($content)));
        $seo_description = mb_substr($plain !== '' ? $plain : $title, 0, 160);
    }
    if ($seo_keywords === '') {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($content)));
        $seo_keywords = mb_substr(trim($title . ' ' . $plain), 0, 180);
    }
    if ($tags === '') {
        // tags بسيطة من أول كلمات العنوان
        $words = preg_split('/\s+/', trim($title));
        $words = array_values(array_filter($words, fn($w)=>mb_strlen($w) >= 3));
        $tags = implode(', ', array_slice($words, 0, 6));
    }


    $publish_at = trim((string)($_POST['publish_at'] ?? ''));
    $publish_at_db = gdy_dt_local_to_sql($publish_at);

    $unpublish_at = trim((string)($_POST['unpublish_at'] ?? ''));
    $unpublish_at_db = gdy_dt_local_to_sql($unpublish_at);

    $seo_title = trim((string)($_POST['seo_title'] ?? ''));
    $seo_description = trim((string)($_POST['seo_description'] ?? ''));
    $seo_keywords = trim((string)($_POST['seo_keywords'] ?? ''));

    $tags        = trim((string)($_POST['tags'] ?? ''));
    $tags_input  = $tags;

    $status      = trim((string)($_POST['status'] ?? 'published'));
    $category_id = (int)($_POST['category_id'] ?? 0);

    // ✅ لا يوجد اختيار كاتب — دائماً المستخدم الحالي
    $author_id = $userId;

    $opinion_author_id = (int)($_POST['opinion_author_id'] ?? 0);
    $published_at = trim((string)($_POST['published_at'] ?? ''));

    $featured    = isset($_POST['featured']) ? 1 : 0;
    $is_breaking = isset($_POST['is_breaking']) ? 1 : 0;

    if ($isWriter) {
        $featured = 0;
        $is_breaking = 0;
    }

    // التحقق من العنوان
    if ($title === '') {
        $errors['title'] = __('t_a177201400', 'يرجى إدخال عنوان الخبر.');
    }

    // slug اختياري: إن كان فارغاً نحاول توليده من العنوان (يدعم العربية)
    $slug = trim($slug);
    if ($slug === '' && $title !== '') {
        $slug = $title;
    }

    // تنظيف الـ slug: السماح بالحروف/الأرقام العربية والإنجليزية مع الشرطات
    if ($slug !== '') {
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $slug);
        $slug = trim((string)$slug, '-');
        // lowercase للإنجليزية فقط (العربية لا تتأثر)
        if (function_exists('mb_strtolower')) {
            $slug = mb_strtolower($slug, 'UTF-8');
        } else {
            $slug = strtolower($slug);
        }
    }

    // إذا بقي فارغاً (عنوان رموز فقط) نولد slug آمن
    if ($slug === '') {
        $slug = 'news-' . date('YmdHis') . '-' . random_int(100, 999);
    }

    // تصنيف مطلوب
    if ($category_id <= 0) {
        $errors['category_id'] = __('t_96bde08b29', 'يرجى اختيار التصنيف.');
    }

    // حالة الخبر
    // دعم سير عمل التحرير: pending -> approved -> published
    $allowedStatuses = $isWriter ? ['draft','pending'] : ['published','draft','pending','approved','archived'];
    if (!in_array($status, $allowedStatuses, true)) {
        $status = $isWriter ? 'pending' : 'draft';
    }

    if ($isWriter && $status === 'published') {
        $status = 'pending';
    }

    // تاريخ النشر (يمكن أن يكون فارغاً)
    $publishedAtForDb = gdy_dt_local_to_sql($published_at);

    // -------------------------------------------------------------------------
    // رفع الصورة (اختياري)
    // -------------------------------------------------------------------------
    if (empty($errors)) {
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['image']['tmp_name'] ?? '';
                $origName = $_FILES['image']['name'] ?? '';
                $size = (int)($_FILES['image']['size'] ?? 0);

                $maxSize = 5 * 1024 * 1024;
                if ($size > $maxSize) {
                    $errors['image'] = __('t_75a6c044df', 'حجم الصورة أكبر من المسموح (5 ميجابايت).');
                } else {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime  = $finfo ? finfo_file($finfo, $tmpName) : '';
                    if ($finfo) {
                        finfo_close($finfo);
                    }

                    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (!in_array($mime, $allowedMimes, true)) {
                        $errors['image'] = __('t_1d4df6cce9', 'يُسمح فقط برفع صور بصيغ JPG أو PNG أو GIF أو WebP.');
                    } else {
                        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                        if ($ext === '') {
                            $map = [
                                'image/jpeg' => 'jpg',
                                'image/png'  => 'png',
                                'image/gif'  => 'gif',
                                'image/webp' => 'webp',
                            ];
                            $ext = $map[$mime] ?? 'jpg';
                        }

                        $uploadDir = __DIR__ . '/../../uploads/news/';
                        if (!is_dir($uploadDir)) {
                            @mkdir($uploadDir, 0755, true);
                        }

                        $baseName = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
                        $fileName = $baseName . '.' . $ext;
                        $targetPath = $uploadDir . $fileName;

                        if (move_uploaded_file($tmpName, $targetPath)) {
                            $imagePath = 'uploads/news/' . $fileName;
                        } else {
                            $errors['image'] = __('t_6b9a5a9ac9', 'تعذر حفظ ملف الصورة على الخادم.');
                        }
                    }
                }
            } else {
                $errors['image'] = __('t_14a4dd5d81', 'حدث خطأ أثناء رفع الصورة. حاول مرة أخرى.');
            }
        }
    }

    // -------------------------------------------------------------------------
    // إدخال السجل في قاعدة البيانات
    // -------------------------------------------------------------------------
    if (!$errors) {
        try {
            $newsCols = gdy_db_columns($pdo, 'news');

            $columns = [];
            $placeholders = [];

            $add = function(string $col, string $ph) use (&$columns, &$placeholders): void {
                $columns[] = $col;
                $placeholders[] = $ph;
            };

            $add('title', ':title');
            $add('slug', ':slug');

            if (isset($newsCols['excerpt'])) {
                $add('excerpt', ':excerpt');
            } elseif (isset($newsCols['summary'])) {
                $add('summary', ':excerpt');
            }

            if (isset($newsCols['content'])) {
                $add('content', ':content');
            } elseif (isset($newsCols['body'])) {
                $add('body', ':content');
            }

            if (isset($newsCols['category_id'])) $add('category_id', ':category_id');
            if (isset($newsCols['author_id'])) $add('author_id', ':author_id');
            if (isset($newsCols['opinion_author_id'])) $add('opinion_author_id', ':opinion_author_id');
            if (isset($newsCols['status'])) $add('status', ':status');
            if (isset($newsCols['featured'])) $add('featured', ':featured');
            if (isset($newsCols['is_breaking'])) $add('is_breaking', ':is_breaking');
            if (isset($newsCols['published_at'])) $add('published_at', ':published_at');
            if (isset($newsCols['publish_at'])) $add('publish_at', ':publish_at');
            if (isset($newsCols['unpublish_at'])) $add('unpublish_at', ':unpublish_at');
            if (isset($newsCols['seo_title'])) $add('seo_title', ':seo_title');
            if (isset($newsCols['seo_description'])) $add('seo_description', ':seo_description');
            if (isset($newsCols['seo_keywords'])) $add('seo_keywords', ':seo_keywords');

            if (isset($newsCols['image'])) {
                array_unshift($columns, 'image');
                array_unshift($placeholders, ':image');
            }

            if (isset($newsCols['created_at'])) {
                $columns[] = 'created_at';
                $placeholders[] = 'NOW()';
            }

            $sql = 'INSERT INTO news (' . implode(', ', $columns) . ')
                    VALUES (' . implode(', ', $placeholders) . ')';

            $stmt = $pdo->prepare($sql);

            $stmt->bindValue(':title', $title, PDO::PARAM_STR);
            $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);

            if (in_array('excerpt', $columns, true) || in_array('summary', $columns, true)) {
                $stmt->bindValue(':excerpt', $excerpt, PDO::PARAM_STR);
            }
            if (in_array('content', $columns, true) || in_array('body', $columns, true)) {
                $stmt->bindValue(':content', $content, PDO::PARAM_STR);
            }

            if (in_array('category_id', $columns, true)) {
                $stmt->bindValue(':category_id', $category_id, PDO::PARAM_INT);
            }

            if (in_array('author_id', $columns, true)) {
                $stmt->bindValue(':author_id', $author_id, PDO::PARAM_INT);
            }

            if (in_array('opinion_author_id', $columns, true)) {
                if ($opinion_author_id > 0) {
                    $stmt->bindValue(':opinion_author_id', $opinion_author_id, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue(':opinion_author_id', null, PDO::PARAM_NULL);
                }
            }

            if (in_array('status', $columns, true)) {
                $stmt->bindValue(':status', $status, PDO::PARAM_STR);
            }
            if (in_array('featured', $columns, true)) {
                $stmt->bindValue(':featured', $featured, PDO::PARAM_INT);
            }
            if (in_array('is_breaking', $columns, true)) {
                $stmt->bindValue(':is_breaking', $is_breaking, PDO::PARAM_INT);
            }

            if (in_array('published_at', $columns, true)) {
                if ($publishedAtForDb !== null) {
                    $stmt->bindValue(':published_at', $publishedAtForDb, PDO::PARAM_STR);
                } else {
                    $stmt->bindValue(':published_at', null, PDO::PARAM_NULL);
                }
            }
            if (in_array('publish_at', $columns, true)) {
                $stmt->bindValue(':publish_at', $publish_at_db, $publish_at_db === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            }
            if (in_array('unpublish_at', $columns, true)) {
                $stmt->bindValue(':unpublish_at', $unpublish_at_db, $unpublish_at_db === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            }

            if (in_array('seo_title', $columns, true)) {
                $stmt->bindValue(':seo_title', $seo_title, PDO::PARAM_STR);
            }
            if (in_array('seo_description', $columns, true)) {
                $stmt->bindValue(':seo_description', $seo_description, PDO::PARAM_STR);
            }
            if (in_array('seo_keywords', $columns, true)) {
                $stmt->bindValue(':seo_keywords', $seo_keywords, PDO::PARAM_STR);
            }

            if (isset($newsCols['image'])) {
                $stmt->bindValue(':image', $imagePath, $imagePath ? PDO::PARAM_STR : PDO::PARAM_NULL);
            }

            $stmt->execute();
            $newsId = (int)$pdo->lastInsertId();

            // IndexNow: notify when published
            try {
                if ($status === 'published') {
                    $u = function_exists('base_url') ? rtrim((string)base_url(), '/') : '';
                    $newsUrl = $u !== '' ? ($u . '/news/id/' . $newsId) : '';
                    $sitemapUrl = $u !== '' ? ($u . '/sitemap.xml') : '';
                    if ($pdo instanceof PDO) {
                        @gdy_indexnow_submit($pdo, array_filter([$newsUrl, $sitemapUrl]));
                    }
                }
            } catch (Throwable $e) {
                @error_log('[IndexNow] create ping failed: ' . $e->getMessage());
            }


            try {
                gdy_sync_news_tags($pdo, $newsId, (string)($tags_input ?? ''));
            } catch (Throwable $e) {
                @error_log('Error syncing tags: ' . $e->getMessage());
            }

            try {
                if (isset($_FILES['attachments'])) {
                    $tmpErrors = [];
                    gdy_save_news_attachments($pdo, $newsId, (array)$_FILES['attachments'], $tmpErrors);
                }
            } catch (Throwable $e) {
                @error_log('Error saving attachments: ' . $e->getMessage());
            }
// SEO cache invalidation (sitemap/rss)
$root = dirname(__DIR__, 2);
@unlink($root . '/cache/sitemap.xml');
@unlink($root . '/cache/rss.xml');



            header('Location: edit.php?id=' . $newsId . '&created=1');
            exit;

        } catch (Throwable $e) {
            @error_log('Error inserting news: ' . $e->getMessage());
            $errors['general'] = __('t_c38467d8bb', 'حدث خطأ أثناء حفظ الخبر. يرجى المحاولة مرة أخرى.');
        }
    }
}

// -----------------------------------------------------------------------------
// الواجهة
// -----------------------------------------------------------------------------
$__base = defined('GODYAR_BASE_URL') ? rtrim((string)GODYAR_BASE_URL, '/') : '';
$pageHead = '<link rel="stylesheet" href="' . $__base . '/admin/assets/editor/gdy-editor.css">';
$pageScripts = '<script src="' . $__base . '/admin/assets/editor/gdy-editor.js"></script>';
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>
<style>
/* التصميم الموحد للعرض - منع التمرير الأفقي + عدم التداخل مع السايدبار */
html, body { overflow-x: hidden; }
@media (min-width: 992px) { .admin-content { margin-right: 260px !important; } }
.admin-content.gdy-page .container-fluid { max-width: 1200px; margin: 0 auto; padding: 1.5rem 1rem 2rem; }
.admin-content.gdy-page { background: linear-gradient(135deg, #0f172a 0%, #020617 100%); min-height: 100vh; color: #e5e7eb; font-family: "Cairo", system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
.gdy-header { background: linear-gradient(135deg, #0ea5e9, #0369a1); color: #fff; padding: 1.5rem 2rem; margin: 0 -1rem 1.25rem; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.6); border-radius: 1rem; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; }
.gdy-header h1 { margin: 0 0 .3rem; font-size: 1.4rem; font-weight: 700; }
.gdy-header p { margin: 0; font-size: .9rem; opacity: .9; }
.gdy-actions { display: flex; flex-wrap: wrap; gap: .5rem; }
.gdy-card { background: rgba(15, 23, 42, 0.9); border-radius: 1rem; border: 1px solid rgba(148, 163, 184, 0.45); box-shadow: 0 15px 45px rgba(15, 23, 42, 0.9); overflow: hidden; }
.gdy-card-header { padding: 1rem 1.25rem; border-bottom: 1px solid rgba(148, 163, 184, 0.25); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .75rem; }
.gdy-card-header h2 { margin: 0; font-size: 1rem; display: flex; align-items: center; gap: .5rem; }
.gdy-card-header h2 i { color: #0ea5e9; }
.gdy-card-header small { color: #9ca3af; font-size: .8rem; }
.gdy-card-body { padding: 1.25rem; }
.gdy-form-label { font-weight: 600; font-size: .85rem; margin-bottom: .25rem; }
.form-control, .form-select { border-radius: .7rem; border-color: rgba(148, 163, 184, 0.4); background-color: rgba(15, 23, 42, 0.9); color: #e5e7eb; font-size: .85rem; }
.form-control:focus, .form-select:focus { border-color: #0ea5e9; box-shadow: 0 0 0 0.15rem rgba(14, 165, 233, 0.35); background-color: rgba(15, 23, 42, 1); color: #e5e7eb; }
.form-text { color: #9ca3af; font-size: .8rem; }
.invalid-feedback { display: block; font-size: .8rem; }
.gdy-options-box { border-radius: .9rem; border: 1px solid rgba(148,163,184,.4); background: radial-gradient(circle at top left, rgba(15,23,42,.95), rgba(15,23,42,1)); padding: 1rem; color:#e5e7eb; }
.gdy-options-box h3 { font-size: .95rem; margin-bottom: .75rem; }
.gdy-btn { border-radius: .7rem; border: none; padding: .55rem 1.1rem; font-size: .85rem; font-weight: 600; display: inline-flex; align-items: center; gap: .5rem; text-decoration: none; cursor: pointer; transition: all .2s ease; }
.gdy-btn i { font-size: .9rem; }
.gdy-btn-primary { background: linear-gradient(135deg, #22c55e, #16a34a); color: #0f172a; }
.gdy-btn-primary:hover { filter: brightness(1.1); transform: translateY(-1px); }
.gdy-btn-secondary { background: rgba(15,23,42,1); color: #e5e7eb; border: 1px solid rgba(148,163,184,.5); }
.gdy-btn-secondary:hover { background: rgba(15,23,42,.9); transform: translateY(-1px); }
.gdy-dropzone { border-radius: .9rem; border: 1.5px dashed rgba(148,163,184,.7); background: rgba(15,23,42,.8); padding: 1rem; text-align: center; cursor: pointer; transition: all .2s ease; }
.gdy-dropzone:hover, .gdy-dropzone.is-dragover { border-color: #0ea5e9; background: rgba(15,23,42,1); }
.gdy-dropzone-inner i { font-size: 1.6rem; color: #38bdf8; }
.gdy-image-preview img { max-height: 220px; object-fit: contain; }
.tags-input-wrapper { font-size: .82rem; }
.tags-container { display: flex; flex-wrap: wrap; gap: .35rem; min-height: 32px; padding: .35rem .5rem; border-radius: .6rem; background: rgba(15,23,42,.8); border: 1px dashed rgba(148,163,184,.6); }
.tag-pill { display: inline-flex; align-items: center; gap: .35rem; padding: .2rem .55rem; border-radius: 999px; background: rgba(37,99,235,.15); color: #e5e7eb; font-size: .78rem; }
.tag-pill button { border: none; background: transparent; color: #9ca3af; cursor: pointer; font-size: .75rem; padding: 0; line-height: 1; }
.tags-input-wrapper .btn-link { color: #38bdf8; text-decoration: none; }
.tags-input-wrapper .btn-link:hover { text-decoration: underline; }
#title-counter { font-size: .75rem; color: #9ca3af; }
@media (max-width: 767.98px) { .gdy-header { padding: 1.25rem 1.25rem; margin: 0 -0.75rem 1rem; } .gdy-card-body { padding: 1rem; } }

/* --- Professional additions: Pre-publish checklist + OpenGraph preview + Internal links suggestions --- */
.gdy-checklist { display: grid; gap: .45rem; }
.gdy-checklist li { display:flex; align-items:center; gap:.5rem; font-size:.92rem; color: rgba(226,232,240,.92); }
.gdy-checklist li .ci { width: 1.1rem; text-align:center; opacity:.9; }
.gdy-checklist li.ok { color: rgba(134,239,172,.95); }
.gdy-checklist li.warn { color: rgba(253,230,138,.95); }
.gdy-checklist li.bad { color: rgba(252,165,165,.95); }
.gdy-checklist small { color: rgba(148,163,184,.9); }

.gdy-og-preview { border:1px solid rgba(148,163,184,.25); border-radius: 14px; overflow:hidden; background: rgba(2,6,23,.35); }
.gdy-og-img { height: 150px; background: rgba(15,23,42,.6); display:flex; align-items:center; justify-content:center; color: rgba(226,232,240,.7); font-size:.9rem; }
.gdy-og-img img { width:100%; height:100%; object-fit:cover; display:block; }
.gdy-og-meta { padding: .75rem .9rem; }
.gdy-og-domain { font-size: .75rem; color: rgba(148,163,184,.95); margin-bottom:.25rem; direction:ltr; text-align:left; }
.gdy-og-title { font-weight: 800; font-size: .98rem; line-height:1.25; margin-bottom:.25rem; color: rgba(226,232,240,.98); }
.gdy-og-desc { font-size: .85rem; line-height:1.35; color: rgba(203,213,225,.92); max-height: 3.7em; overflow:hidden; }
.gdy-og-meta .muted { color: rgba(148,163,184,.95); }

.gdy-internal-links { border:1px dashed rgba(148,163,184,.25); border-radius: 14px; padding: .75rem .85rem; background: rgba(2,6,23,.25); }
.gdy-internal-links .results a { display:block; padding:.45rem .55rem; border-radius: 10px; border:1px solid rgba(148,163,184,.18); margin-top:.4rem; color: rgba(226,232,240,.95); text-decoration:none; }
.gdy-internal-links .results a:hover { background: rgba(148,163,184,.12); }
.gdy-internal-links .results .meta { font-size:.78rem; color: rgba(148,163,184,.95); margin-top:.15rem; direction:ltr; text-align:left; }


</style>

<div class="admin-content gdy-page">
  <div class="container-fluid">

    <div class="gdy-header">
      <div>
        <h1><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg><?= h(__('t_0d1f6ecf66', 'إضافة خبر جديد')) ?></h1>
        <p><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_459cc72253', 'املأ النموذج أدناه لإضافة خبر جديد إلى الموقع.')) ?></p>
      </div>
      <div class="gdy-actions">
        <a href="index.php" class="gdy-btn gdy-btn-secondary">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_f2401e0914', 'قائمة الأخبار')) ?>
        </a>
      </div>
    </div>

    <?php if (!empty($errors['general'])): ?>
      <div class="alert alert-danger">
        <?= h($errors['general']) ?>
      </div>
    <?php endif; ?>

    <div class="gdy-card mb-4">
      <div class="gdy-card-header">
        <h2><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_ab484184b3', 'بيانات الخبر')) ?></h2>
        <small><?= h(__('t_fa60f5381a', 'املأ الحقول الأساسية، باقي الإعدادات يمكن تعديلها لاحقاً من صفحة التعديل.')) ?></small>
      </div>
      <div class="gdy-card-body">
        <form method="post" action="" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

          <div class="row g-3">
            <div class="col-md-8">

              <div class="mb-3">
                <label class="gdy-form-label"><span class="text-danger">*</span><?= h(__('t_6dc6588082', 'العنوان')) ?></label>
                <input type="text" name="title"
                  class="form-control form-control-sm <?= isset($errors['title']) ? 'is-invalid' : '' ?>"
                  value="<?= h($title) ?>" placeholder="<?= h(__('t_64a18e598a', 'اكتب عنوان الخبر هنا')) ?>">
                <?php if (isset($errors['title'])): ?>
                  <div class="invalid-feedback"><?= h($errors['title']) ?></div>
                <?php endif; ?>
                <div class="d-flex justify-content-between mt-1">
                  <small class="text-muted"><?= h(__('t_9c84024cc6', 'اجعل العنوان واضحاً وجاذباً.')) ?></small>
                  <small id="title-counter">0 / 120</small>
                </div>
              </div>

              <div class="mb-3">
                <label class="gdy-form-label"><?= h(__('t_0781965540', 'الرابط (Slug)')) ?></label>
                <input type="text" name="slug"
                  class="form-control form-control-sm <?= isset($errors['slug']) ? 'is-invalid' : '' ?>"
                  value="<?= h($slug) ?>" placeholder="<?= h(__('t_049c6abb70', 'اتركه فارغاً لتوليد رابط تلقائي من العنوان')) ?>">
                <?php if (isset($errors['slug'])): ?>
                  <div class="invalid-feedback"><?= h($errors['slug']) ?></div>
                <?php else: ?>
                  <div class="form-text"><?= h(__('t_6881e8f9d5', 'مثال:')) ?><code>breaking-news-2025</code><?= h(__('t_be9a7dfdd3', '— يمكنك تغييره يدويًا.')) ?></div>
                <?php endif; ?>
              </div>

              

              <div class="mb-3">
                <label class="gdy-form-label"><?= h(__('t_b649baf3ad', 'نص الخبر / المحتوى')) ?></label>
                <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
                  <label class="form-check form-check-inline small m-0">
                    <input class="form-check-input" type="checkbox" name="strip_formatting" value="1" checked>
                    <span class="form-check-label"><?= h(__('t_strip_fmt', 'إزالة التنسيق')) ?></span>
                  </label>
                  <label class="form-check form-check-inline small m-0">
                    <input class="form-check-input" type="checkbox" name="strip_bg" value="1" checked>
                    <span class="form-check-label"><?= h(__('t_strip_bg', 'إزالة لون الخلفية')) ?></span>
                  </label>
                  <button type="button" class="btn btn-sm btn-outline-light" id="btn-clean-content">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_clean_now', 'تنظيف المحتوى الآن')) ?>
                  </button>
                  <span class="text-muted small"><?= h(__('t_clean_hint', 'يتم تطبيق التنظيف قبل الحفظ إذا كانت الخيارات مفعّلة.')) ?></span>
                </div>

                <textarea data-gdy-editor="1" name="content" rows="10" class="form-control form-control-sm"
                  placeholder="<?= h(__('t_52461cefbe', 'اكتب نص الخبر كاملاً أو الصقه هنا.')) ?>"><?= h($content) ?></textarea>

                <!-- Professional: Internal links suggestions -->
                <div class="gdy-internal-links mt-2" id="internal-links-panel">
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-outline-info" id="btn-suggest-links">
                      <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_il_suggest','اقتراح روابط داخلية')) ?>
                    </button>
                    <input type="text" class="form-control form-control-sm" id="internal-link-query"
                      style="max-width: 260px"
                      placeholder="<?= h(__('t_il_query','كلمة/عبارة (اختياري)')) ?>">
                    <span class="text-muted small"><?= h(__('t_il_tip','حدد نصًا داخل المحتوى ثم اضغط "اقتراح".')) ?></span>
                  </div>
                  <div class="results mt-2" id="internal-links-results"></div>
                </div>


                <div class="form-text"><?= h(__('t_bba37921e9', 'يمكن ربط هذا الحقل لاحقاً بمحرر متقدم WYSIWYG.')) ?></div>
              </div>

              <div class="mb-3">
                <label class="gdy-form-label"><?= h(__('t_cd376ca9a8', 'صورة الخبر (اختياري)')) ?></label>
                <div id="image-dropzone" class="gdy-dropzone">
                  <input type="file" name="image" id="image-input" accept="image/*" class="d-none">
                  <div class="gdy-dropzone-inner">
                    <svg class="gdy-icon mb-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                    <p class="mb-1"><?= h(__('t_82302b8afb', 'اسحب وأفلت الصورة هنا أو اضغط للاختيار')) ?></p>
                    <small class="text-muted"><?= h(__('t_5968ccc71d', 'يفضل صور JPG أو PNG أو GIF أو WebP بحجم أقل من 5MB.')) ?></small>
                  </div>
<div class="mt-2 d-flex gap-2 flex-wrap">
  <button type="button" id="gdy-pick-featured" class="btn btn-sm btn-outline-light">اختيار من المكتبة</button>
  <small class="text-muted">يمكنك اختيار صورة الخبر من مكتبة الوسائط بدون رفع جديد.</small>
</div>
<input type="hidden" name="image_url" value="<?= isset($image_url) ? h($image_url) : '' ?>">

                </div>
                <div id="image-preview" class="gdy-image-preview d-none mt-2">
                  <img src="" alt="<?= h(__('t_0075044f10', 'معاينة الصورة')) ?>" class="img-fluid rounded mb-2">
                  <div class="small text-muted" id="image-info"></div>
                </div>
                <?php if (isset($errors['image'])): ?>
                  <div class="invalid-feedback d-block"><?= h($errors['image']) ?></div>
                <?php endif; ?>
              </div>

              <div class="mb-3">
                <label class="gdy-form-label"><?= h(__('t_5deb5044ef', 'مرفقات الخبر (PDF / Word / Excel ...)')) ?></label>
                <input type="file" name="attachments[]" id="attachments-input"
                  class="form-control form-control-sm" multiple
                  accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.7z,.txt,.rtf,image/*">
                <div class="form-text"><?= h(__('t_02b2d51ef0', 'يمكنك رفع عدة ملفات (حتى 20MB لكل ملف).')) ?></div>
                <div id="attachments-preview" class="mt-2 small text-muted"></div>
                <?php if (isset($errors['attachments'])): ?>
                  <div class="invalid-feedback d-block"><?= h($errors['attachments']) ?></div>
                <?php endif; ?>
              </div>

            </div>

            <div class="col-md-4">
              <div class="gdy-options-box mb-3">
                <h3><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_cf14329701', 'التصنيف')) ?></h3>

                <div class="mb-3">
                  <label class="gdy-form-label"><span class="text-danger">*</span><?= h(__('t_cf14329701', 'التصنيف')) ?></label>
                  <select name="category_id" class="form-select form-select-sm <?= isset($errors['category_id']) ? 'is-invalid' : '' ?>">
                    <option value="0"><?= h(__('t_0a8d417cf7', '-- اختر التصنيف --')) ?></option>
                    <?php foreach ($categories as $cat): ?>
                      <option value="<?= (int)$cat['id'] ?>" <?= (int)$cat['id'] === (int)$category_id ? 'selected' : '' ?>>
                        <?= h($cat['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <?php if (isset($errors['category_id'])): ?>
                    <div class="invalid-feedback"><?= h($errors['category_id']) ?></div>
                  <?php endif; ?>
                </div>

                <?php if (!empty($opinionAuthors)): ?>
                  <div class="mb-3">
                    <label class="gdy-form-label"><?= h(__('t_a53485b1ba', 'كاتب رأي (إن كان مقال رأي)')) ?></label>
                    <select name="opinion_author_id" class="form-select form-select-sm">
                      <option value="0"><?= h(__('t_7f3152db47', '-- ليس مقال رأي --')) ?></option>
                      <?php foreach ($opinionAuthors as $oa): ?>
                        <option value="<?= (int)$oa['id'] ?>" <?= (int)$oa['id'] === (int)$opinion_author_id ? 'selected' : '' ?>>
                          <?= h($oa['name']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                <?php endif; ?>
              </div>

              <div class="gdy-options-box mb-3">
                <h3><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_917daa2b85', 'النشر والحالة')) ?></h3>

                <div class="mb-2">
                  <label class="gdy-form-label"><?= h(__('t_1253eb5642', 'الحالة')) ?></label>
                  <select name="status" class="form-select form-select-sm">
                    <?php if ($isWriter): ?>
                      <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>><?= h(__('t_9071af8f2d', 'مسودة')) ?></option>
                      <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>><?= h(__('t_e9210fb9c2', 'بانتظار المراجعة')) ?></option>
                    <?php else: ?>
                      <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>><?= h(__('t_9071af8f2d', 'مسودة')) ?></option>
                      <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>><?= h(__('t_e9210fb9c2', 'بانتظار المراجعة')) ?></option>
                      <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>><?= h(__('t_aeb4d514db', 'جاهز للنشر')) ?></option>
                      <option value="published" <?= $status === 'published' ? 'selected' : '' ?>><?= h(__('t_ecfb62b400', 'منشور')) ?></option>
                      <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>><?= h(__('t_2e67aea8ca', 'مؤرشف')) ?></option>
                    <?php endif; ?>
                  </select>
                </div>

                <div class="mb-2">
                  <label class="gdy-form-label"><?= h(__('t_9928bed160', 'تاريخ النشر (اختياري)')) ?></label>
                  <input type="datetime-local" name="published_at" class="form-control form-control-sm" value="<?= h($published_at) ?>">
                </div>

                <div class="mb-2">
                  <label class="gdy-form-label"><?= h(__('t_9260bd801b', 'جدولة نشر (publish_at)')) ?></label>
                  <input type="datetime-local" name="publish_at" class="form-control form-control-sm" value="<?= h($publish_at ?? '') ?>">
                </div>

                <div class="mb-2">
                  <label class="gdy-form-label"><?= h(__('t_58e272983c', 'جدولة إلغاء نشر (unpublish_at)')) ?></label>
                  <input type="datetime-local" name="unpublish_at" class="form-control form-control-sm" value="<?= h($unpublish_at ?? '') ?>">
                </div>

                <div class="form-check mb-2">
                  <input class="form-check-input" type="checkbox" name="featured" id="featured" value="1" <?= $featured ? 'checked' : '' ?>>
                  <label class="form-check-label" for="featured"><?= h(__('t_6b9fb336b6', 'خبر مميز (Featured)')) ?></label>
                </div>

                <div class="form-check mb-2">
                  <input class="form-check-input" type="checkbox" name="is_breaking" id="is_breaking" value="1" <?= $is_breaking ? 'checked' : '' ?>>
                  <label class="form-check-label" for="is_breaking"><?= h(__('t_d8a9550063', 'خبر عاجل (Breaking)')) ?></label>
                </div>
              </div>

              <div class="gdy-options-box mb-3">
                <h3><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#search"></use></svg><?= h(__('t_5584163b0c', 'إعدادات SEO')) ?></h3>

                <div class="mb-2">
                  <label class="gdy-form-label">SEO Title</label>
                  <input type="text" name="seo_title" class="form-control form-control-sm" value="<?= h($seo_title ?? '') ?>" placeholder="<?= h(__('t_439b74907d', 'عنوان لمحركات البحث (اختياري)')) ?>">
                </div>

                <div class="mb-2">
                  <label class="gdy-form-label">SEO Description</label>
                  <textarea name="seo_description" rows="3" class="form-control form-control-sm" placeholder="<?= h(__('t_29002a42e6', 'وصف مختصر لمحركات البحث (اختياري)')) ?>"><?= h($seo_description ?? '') ?></textarea>
                </div>

                <div class="mb-2">
                  <label class="gdy-form-label">SEO Keywords</label>
                  <input type="text" name="seo_keywords" class="form-control form-control-sm" value="<?= h($seo_keywords ?? '') ?>" placeholder="<?= h(__('t_dd1cc5fb86', 'كلمات مفتاحية (اختياري)')) ?>">
                </div>
              </div>

              <div class="gdy-options-box mb-3">
                <h3><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_84c1b773c5', 'الوسوم')) ?></h3>
                <div class="mb-2">
                  <label class="gdy-form-label"><?= h(__('t_51071ad5c6', 'وسوم (Tags)')) ?></label>
                  <div class="tags-input-wrapper">
                    <div id="tags-container" class="tags-container" data-initial-tags="<?= h($tags) ?>"></div>
                    <div class="input-group input-group-sm mt-2">
                      <input type="text" id="tag-input" class="form-control form-control-sm" placeholder="<?= h(__('t_d533eba2ee', 'اكتب الوسم ثم اضغط إضافة أو Enter')) ?>">
                      <button type="button" class="btn btn-outline-secondary" id="add-tag-btn">
                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_7764b0999a', 'إضافة وسم')) ?>
                      </button>
                    </div>
                    <button type="button" class="btn btn-link btn-sm p-0 mt-1" id="auto-tags-btn">
                      <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_0c8a1e0b20', 'اقتراح وسوم تلقائيًا من العنوان')) ?>
                    </button>
                    <input type="hidden" name="tags" id="tags-hidden" value="<?= h($tags) ?>">
                    <div class="form-text"><?= h(__('t_2a23b5bb82', 'يمكن إزالة أي وسم بالضغط على علامة ×. يتم حفظ الوسوم كمجموعة مفصولة بفواصل.')) ?></div>
                  </div>
                </div>
              </div>

            </div>
          </div>


          <!-- Professional: Pre-publish Checklist -->
          <div class="gdy-card mt-3" id="prepublish-checklist-card">
            <div class="gdy-card-header">
              <h3><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_precheck', 'قائمة التحقق قبل النشر')) ?></h3>
              <span class="badge bg-secondary-subtle text-secondary-emphasis" id="precheck-badge">—</span>
            </div>
            <div class="gdy-card-body">
              <ul class="list-unstyled mb-0 gdy-checklist" id="prepublish-checklist">
                <li data-key="title"><span class="ci">○</span><span><?= h(__('t_ck_title','العنوان')) ?></span></li>
                <li data-key="category"><span class="ci">○</span><span><?= h(__('t_ck_category','التصنيف')) ?></span></li>
                <li data-key="content"><span class="ci">○</span><span><?= h(__('t_ck_content','المحتوى')) ?></span></li>
                <li data-key="tags"><span class="ci">○</span><span><?= h(__('t_ck_tags','الوسوم (Tags)')) ?></span> <small>(<?= h(__('t_ck_rec','مستحسن')) ?>)</small></li>
                <li data-key="seo"><span class="ci">○</span><span><?= h(__('t_ck_seo','SEO Title/Description')) ?></span> <small>(<?= h(__('t_ck_rec','مستحسن')) ?>)</small></li>
                <li data-key="image"><span class="ci">○</span><span><?= h(__('t_ck_image','صورة الخبر')) ?></span> <small>(<?= h(__('t_ck_rec','مستحسن')) ?>)</small></li>
              </ul>
              <div class="form-text mt-2"><?= h(__('t_ck_hint','يمكنك حفظ الخبر كمسودة بدون اكتمال القائمة. عند اختيار الحالة "منشور" سيتم التحقق من العناصر الأساسية.')) ?></div>
            </div>
          </div>

          <!-- Professional: OpenGraph Preview -->
          <div class="gdy-card mt-3" id="og-preview-card">
            <div class="gdy-card-header">
              <h3><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_ogprev','معاينة المشاركة (OpenGraph)')) ?></h3>
            </div>
            <div class="gdy-card-body">
              <div class="gdy-og-preview">
                <div class="gdy-og-img" id="ogp-img"><span class="muted"><?= h(__('t_og_img','صورة المعاينة')) ?></span></div>
                <div class="gdy-og-meta">
                  <div class="gdy-og-domain" id="ogp-domain">—</div>
                  <div class="gdy-og-title" id="ogp-title">—</div>
                  <div class="gdy-og-desc" id="ogp-desc">—</div>
                </div>
              </div>
              <div class="form-text mt-2"><?= h(__('t_og_hint','هذه معاينة تقريبية لكيف سيظهر الرابط عند المشاركة في واتساب/فيسبوك. الشكل النهائي يختلف حسب المنصة.')) ?></div>
            </div>
          </div>


          <div class="gdy-card-header mt-3">
            <div class="small text-muted">
              <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_27ee17180f', 'تأكد من مراجعة البيانات قبل الحفظ.')) ?></div>
            <div class="d-flex gap-2">
              <button type="submit" class="gdy-btn gdy-btn-primary">
                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_73795fa19c', 'حفظ الخبر')) ?>
              </button>
              <a href="index.php" class="gdy-btn gdy-btn-secondary">
                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_47c2fa66bd', 'إلغاء والعودة')) ?>
              </a>
            </div>
          </div>

        </form>
      </div>
    </div>

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var titleInput   = document.querySelector('input[name="title"]');
  var titleCounter = document.getElementById('title-counter');
  var slugInput    = document.querySelector('input[name="slug"]');
  var userEditedSlug = false;

  function updateTitleCounter() {
    if (!titleInput || !titleCounter) return;
    var len = titleInput.value.length;
    titleCounter.textContent = len + ' / 120';
  }
  if (titleInput) {
    titleInput.addEventListener('input', updateTitleCounter);
    updateTitleCounter();
  }

  function slugify(str) {
    try {
      // يدعم العربية والإنجليزية والأرقام
      return str.toString().trim()
        .replace(/[^\p{L}\p{N}]+/gu, '-')
        .replace(/^-+|-+$/g, '');
    } catch (e) {
      // fallback للمتصفحات القديمة
      return str.toString().trim()
        .replace(/[^A-Za-z0-9\u0600-\u06FF]+/g, '-')
        .replace(/^-+|-+$/g, '');
    }
  }
if (slugInput) {
    slugInput.addEventListener('input', function () {
      userEditedSlug = slugInput.value.trim() !== '';
    });
  }

  if (titleInput && slugInput) {
    titleInput.addEventListener('input', function () {
      if (userEditedSlug) return;
      slugInput.value = slugify(titleInput.value);
    });
  }

  var dropzone    = document.getElementById('image-dropzone');
  var fileInput   = document.getElementById('image-input');
  var previewWrap = document.getElementById('image-preview');
  var previewImg  = previewWrap ? previewWrap.querySelector('img') : null;
  var infoEl      = document.getElementById('image-info');

  function handleFile(file) {
    if (!file || !previewWrap || !previewImg) return;
    if (!file.type || file.type.indexOf('image/') !== 0) {
      alert(<?= json_encode(__('t_126be999c1', 'الرجاء اختيار ملف صورة صالح.'), JSON_UNESCAPED_UNICODE) ?>);
      return;
    }
    previewWrap.classList.remove('d-none');
    previewImg.src = URL.createObjectURL(file);
    if (infoEl) {
      var sizeKB = Math.round(file.size / 1024);
      infoEl.textContent = file.name + ' (' + sizeKB + ' KB)';
    }
  }

  if (dropzone && fileInput) {
    dropzone.addEventListener('click', function () { fileInput.click(); });

    fileInput.addEventListener('change', function (e) {
      var file = e.target.files && e.target.files[0];
      if (file) handleFile(file);
    });

    ['dragenter','dragover'].forEach(function(evtName) {
      dropzone.addEventListener(evtName, function(e) {
        e.preventDefault(); e.stopPropagation();
        dropzone.classList.add('is-dragover');
      });
    });
    ['dragleave','dragend','drop'].forEach(function(evtName) {
      dropzone.addEventListener(evtName, function(e) {
        e.preventDefault(); e.stopPropagation();
        dropzone.classList.remove('is-dragover');
      });
    });
    dropzone.addEventListener('drop', function(e) {
      var dt = e.dataTransfer;
      if (!dt || !dt.files || !dt.files.length) return;
      fileInput.files = dt.files;
      handleFile(dt.files[0]);
    });
  }

  var tagsContainer = document.getElementById('tags-container');
  var tagInput      = document.getElementById('tag-input');
  var addTagBtn     = document.getElementById('add-tag-btn');
  var autoTagsBtn   = document.getElementById('auto-tags-btn');
  var tagsHidden    = document.getElementById('tags-hidden');

  var tagsList = [];

  function renderTags() {
    if (!tagsContainer || !tagsHidden) return;
    tagsContainer.innerHTML = '';
    tagsList.forEach(function(tag, index) {
      var pill = document.createElement('span');
      pill.className = 'tag-pill';
      pill.textContent = tag;

      var removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.innerHTML = '&times;';
      removeBtn.addEventListener('click', function() {
        tagsList.splice(index, 1);
        renderTags();
      });

      pill.appendChild(removeBtn);
      tagsContainer.appendChild(pill);
    });
    tagsHidden.value = tagsList.join(', ');
  }

  function addTagFromInput() {
    if (!tagInput) return;
    var val = tagInput.value.trim();
    if (!val) return;
    if (tagsList.indexOf(val) === -1) {
      tagsList.push(val);
      renderTags();
    }
    tagInput.value = '';
    tagInput.focus();
  }

  if (addTagBtn && tagInput) {
    addTagBtn.addEventListener('click', addTagFromInput);
    tagInput.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') { e.preventDefault(); addTagFromInput(); }
    });
  }

  if (tagsContainer && tagsHidden) {
    var initial = tagsContainer.getAttribute('data-initial-tags') || '';
    if (initial) {
      initial.split(/[،,]/).forEach(function(t) {
        var v = t.trim();
        if (v && tagsList.indexOf(v) === -1) tagsList.push(v);
      });
      renderTags();
    }
  }

  if (autoTagsBtn && titleInput) {
    autoTagsBtn.addEventListener('click', function() {
      var source = titleInput.value || '';
      var words = source
        .replace(/[^\u0600-\u06FFa-zA-Z0-9\s]/g, ' ')
        .split(/\s+/)
        .map(function(w) { return w.trim(); })
        .filter(function(w) { return w.length > 3; });

      var unique = [];
      words.forEach(function(w) { if (unique.indexOf(w) === -1) unique.push(w); });
      unique.slice(0, 6).forEach(function(w) {
        if (tagsList.indexOf(w) === -1) tagsList.push(w);
      });
      renderTags();
    });
  }

  const attachmentsInput = document.getElementById('attachments-input');
  const attachmentsPreview = document.getElementById('attachments-preview');
  function renderAttachments() {
    if (!attachmentsInput || !attachmentsPreview) return;
    const files = Array.from(attachmentsInput.files || []);
    if (!files.length) { attachmentsPreview.textContent = ''; return; }
    attachmentsPreview.innerHTML = files.map(f => {
      const kb = Math.round((f.size || 0) / 1024);
      return `<div class="d-flex align-items-center gap-2 mb-1">
        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
        <span>${f.name}</span>
        <span class="text-muted">(${kb} KB)</span>
      </div>`;
    }).join('');
  }
  if (attachmentsInput) attachmentsInput.addEventListener('change', renderAttachments);
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Clean content now (client-side)
  var btn = document.getElementById('btn-clean-content');
  if (btn) {
    btn.addEventListener('click', function() {
      var ta = document.querySelector('textarea[name="content"]');
      if (!ta) return;
      var html = ta.value || '';
      // remove background color + bgcolor
      html = html.replace(/\sbgcolor\s*=\s*(['"]).*?\1/gi, '');
      html = html.replace(/background-color\s*:\s*[^;"]+;?/gi, '');
      // strip style/class/font/span wrappers (light)
      html = html.replace(/\sstyle\s*=\s*(['"]).*?\1/gi, '');
      html = html.replace(/\sclass\s*=\s*(['"]).*?\1/gi, '');
      html = html.replace(/<\/?font[^>]*>/gi, '');
      // collapse multiple spaces
      html = html.replace(/\s{2,}/g, ' ');
      ta.value = html.trim();
      // Let editor (if initialized) refresh from textarea
      ta.dispatchEvent(new Event('input', { bubbles: true }));
    });
  }

  // Auto-generate SEO + Keywords + Tags if empty and not edited by user
  var titleInput   = document.querySelector('input[name="title"]');
  var contentTa    = document.querySelector('textarea[name="content"]');
  var seoTitle     = document.querySelector('input[name="seo_title"]');
  var seoDesc      = document.querySelector('textarea[name="seo_description"]');
  var seoKeys      = document.querySelector('input[name="seo_keywords"]');
  var tagsInput    = document.querySelector('input[name="tags"]');
  if (!titleInput) return;

  var touched = { seo_title:false, seo_description:false, seo_keywords:false, tags:false };
  function markTouched(el, key){
    if(!el) return;
    el.addEventListener('input', function(){ touched[key]=true; });
  }
  markTouched(seoTitle,'seo_title');
  markTouched(seoDesc,'seo_description');
  markTouched(seoKeys,'seo_keywords');
  markTouched(tagsInput,'tags');

  function plainText(html){
    var div = document.createElement('div');
    div.innerHTML = html || '';
    return (div.textContent || div.innerText || '').replace(/\s+/g,' ').trim();
  }
  function topWords(text, max){
    text = (text || '').toLowerCase();
    // remove punctuation
    text = text.replace(/[\u061F\u060C\u061B\.,!?:;"'()\[\]{}<>\/\\-]/g,' ');
    var words = text.split(/\s+/).filter(Boolean);
    // basic stopwords (Arabic + English)
    var stop = new Set(['في','على','من','إلى','عن','هذا','هذه','ذلك','تلك','و','أو','ثم','كما','مع','ما','لم','لن','قد','هو','هي','هم','هن','the','and','or','to','of','in','on','for','a','an']);
    var freq = new Map();
    words.forEach(function(w){
      if (w.length < 3) return;
      if (stop.has(w)) return;
      freq.set(w, (freq.get(w)||0)+1);
    });
    return Array.from(freq.entries()).sort((a,b)=>b[1]-a[1]).slice(0,max).map(x=>x[0]);
  }

  function regenerate(){
    var title = (titleInput.value || '').trim();
    var body = plainText(contentTa ? contentTa.value : '');
    if (seoTitle && !touched.seo_title && !seoTitle.value.trim()) {
      seoTitle.value = title.slice(0, 60);
    }
    if (seoDesc && !touched.seo_description && !seoDesc.value.trim()) {
      var d = body || title;
      seoDesc.value = d.slice(0, 160);
    }
    if (seoKeys && !touched.seo_keywords && !seoKeys.value.trim()) {
      var keys = topWords((title+' '+body), 10);
      seoKeys.value = keys.join(', ');
    }
    if (tagsInput && !touched.tags && !tagsInput.value.trim()) {
      var tags = topWords(title, 6);
      tagsInput.value = tags.join(', ');
    }
  }

  titleInput.addEventListener('input', regenerate);
  if (contentTa) contentTa.addEventListener('input', function(){ setTimeout(regenerate, 120); });
  regenerate();
});

  // ---------------------------------------------------------------------------
  // ميزات احترافية: عداد كلمات + وقت قراءة + حفظ مسودة محلي + Ctrl+S
  // ---------------------------------------------------------------------------
  (function () {
    var contentEl = document.getElementById('content') || document.querySelector('[name="content"]');
    var titleEl = document.getElementById('title') || document.querySelector('[name="title"]');
    var tagsEl = document.getElementById('tags') || document.querySelector('[name="tags"]');
    var seoTitleEl = document.getElementById('seo_title') || document.querySelector('[name="seo_title"]');
    var seoDescEl = document.getElementById('seo_description') || document.querySelector('[name="seo_description"]');
    var seoKeysEl = document.getElementById('seo_keywords') || document.querySelector('[name="seo_keywords"]');

    // إنشاء صندوق الإحصائيات داخل بطاقة SEO إن وُجدت
    if (!document.getElementById('gdyContentStats')) {
      var seoCard = document.querySelector('#seoCardBody, .seo-card-body, .card-body.seo-settings') || null;
      if (seoCard) {
        var statsBox = document.createElement('div');
        statsBox.id = 'gdyContentStats';
        statsBox.style.cssText = 'margin-top:10px;padding:10px;border:1px solid rgba(255,255,255,.08);border-radius:10px;font-size:13px;line-height:1.8;';
        statsBox.innerHTML =
          '<div><b>إحصائيات المحتوى</b></div>' +
          '<div id="gdyWordCount">الكلمات: 0</div>' +
          '<div id="gdyReadingTime">وقت القراءة: 0 دقيقة</div>' +
          '<div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;">' +
            '<button type="button" class="btn btn-sm btn-outline-light" id="gdyRestoreDraft">استرجاع مسودة</button>' +
            '<button type="button" class="btn btn-sm btn-outline-danger" id="gdyClearDraft">مسح المسودة</button>' +
          '</div>' +
          '<div id="gdyDraftHint" style="margin-top:6px;opacity:.8"></div>';
        seoCard.appendChild(statsBox);
      }
    }

    function stripHtml(html) {
      var tmp = document.createElement('div');
      tmp.innerHTML = html || '';
      return (tmp.textContent || tmp.innerText || '').trim();
    }

    function getContentText() {
      if (!contentEl) return '';
      // في أغلب الحالات content textarea
      var v = (contentEl.value !== undefined) ? contentEl.value : (contentEl.innerHTML || '');
      return stripHtml(v);
    }

    function updateStats() {
      var text = getContentText();
      var words = text ? text.split(/\s+/).filter(Boolean).length : 0;
      var minutes = Math.max(1, Math.round(words / 200));
      var wc = document.getElementById('gdyWordCount');
      var rt = document.getElementById('gdyReadingTime');
      if (wc) wc.textContent = 'الكلمات: ' + words;
      if (rt) rt.textContent = 'وقت القراءة: ' + minutes + ' دقيقة';
    }

    if (contentEl) {
      contentEl.addEventListener('input', function () { updateStats(); queueAutosave(); });
      setInterval(updateStats, 4000);
      updateStats();
    }

    // Ctrl+S للحفظ
    document.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
        e.preventDefault();
        var form = document.querySelector('form');
        if (form) form.submit();
      }
    });

    // حفظ مسودة محليًا
    var DRAFT_KEY = 'gdy_news_create_draft_v1';
    var saveTimer = null;

    function collectDraft() {
      return {
        ts: Date.now(),
        title: titleEl ? titleEl.value : '',
        content: contentEl ? ((contentEl.value !== undefined) ? contentEl.value : (contentEl.innerHTML || '')) : '',
        tags: tagsEl ? tagsEl.value : '',
        seo_title: seoTitleEl ? seoTitleEl.value : '',
        seo_description: seoDescEl ? seoDescEl.value : '',
        seo_keywords: seoKeysEl ? seoKeysEl.value : ''
      };
    }

    function saveDraftNow() {
      try {
        localStorage.setItem(DRAFT_KEY, JSON.stringify(collectDraft()));
        var hint = document.getElementById('gdyDraftHint');
        if (hint) hint.textContent = 'تم حفظ مسودة محلية تلقائيًا.';
      } catch (e) {}
    }

    function queueAutosave() {
      if (saveTimer) clearTimeout(saveTimer);
      saveTimer = setTimeout(saveDraftNow, 15000);
    }

    function loadDraft() {
      try {
        var raw = localStorage.getItem(DRAFT_KEY);
        if (!raw) return null;
        return JSON.parse(raw);
      } catch (e) { return null; }
    }

    function restoreDraft(force) {
      var d = loadDraft();
      if (!d) return;
      var hasData = (titleEl && titleEl.value.trim() !== '') || (getContentText().trim() !== '');
      if (hasData && !force) return;

      if (titleEl && d.title) titleEl.value = d.title;
      if (contentEl && d.content) {
        if (contentEl.value !== undefined) contentEl.value = d.content;
        else contentEl.innerHTML = d.content;
      }
      if (tagsEl && d.tags) tagsEl.value = d.tags;
      if (seoTitleEl && d.seo_title) seoTitleEl.value = d.seo_title;
      if (seoDescEl && d.seo_description) seoDescEl.value = d.seo_description;
      if (seoKeysEl && d.seo_keywords) seoKeysEl.value = d.seo_keywords;

      updateStats();
      var hint = document.getElementById('gdyDraftHint');
      if (hint) hint.textContent = 'تم استرجاع المسودة.';
    }

    // استرجاع تلقائي إذا آخر مسودة خلال 6 ساعات والحقول فاضية
    var d0 = loadDraft();
    if (d0 && (Date.now() - (d0.ts || 0) < 6 * 60 * 60 * 1000)) {
      restoreDraft(false);
    }

    var btnRestore = document.getElementById('gdyRestoreDraft');
    var btnClear = document.getElementById('gdyClearDraft');

    if (btnRestore) btnRestore.addEventListener('click', function () { restoreDraft(true); });
    if (btnClear) btnClear.addEventListener('click', function () {
      try { localStorage.removeItem(DRAFT_KEY); } catch (e) {}
      var hint = document.getElementById('gdyDraftHint');
      if (hint) hint.textContent = 'تم مسح المسودة.';
    });
  })();

</script>

<!-- GDY Admin Enhancements: WYSIWYG + Safe Preview + Media Picker (v9.6) -->
<style>
.gdy-wysiwyg{border:1px solid rgba(255,255,255,.12); border-radius:14px; overflow:hidden; background:rgba(0,0,0,.08)}
.gdy-wysiwyg-toolbar{display:flex; flex-wrap:wrap; gap:8px; padding:10px; border-bottom:1px solid rgba(255,255,255,.10); background:rgba(0,0,0,.12)}
.gdy-wysiwyg-toolbar button{border:1px solid rgba(255,255,255,.18); background:rgba(0,0,0,.15); color:#fff; padding:6px 10px; border-radius:10px; font-size:13px; cursor:pointer}
.gdy-wysiwyg-toolbar button:hover{background:rgba(0,0,0,.28)}
.gdy-wysiwyg-toolbar button.active{outline:2px solid rgba(0,153,255,.35)}
.gdy-wysiwyg-editor{min-height:340px; padding:14px; outline:none; color:#fff; line-height:1.9}
.gdy-wysiwyg-editor img{max-width:100%; height:auto}
.gdy-wysiwyg-editor a{color:#7cc4ff}
.gdy-wysiwyg-editor h2,.gdy-wysiwyg-editor h3{margin:0.8em 0 0.35em}
.gdy-modal-backdrop{position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:9998; display:none}
.gdy-modal{position:fixed; inset:5vh 5vw; background:#0b1220; border:1px solid rgba(255,255,255,.14); border-radius:16px; z-index:9999; display:none; overflow:hidden}
.gdy-modal-header{display:flex; align-items:center; justify-content:space-between; padding:10px 12px; border-bottom:1px solid rgba(255,255,255,.12)}
.gdy-modal-title{color:#fff; font-weight:800}
.gdy-modal-header button{border:1px solid rgba(255,255,255,.18); background:rgba(255,255,255,.06); color:#fff; padding:6px 10px; border-radius:10px; cursor:pointer}
</style>

<div id="gdy-media-backdrop" class="gdy-modal-backdrop"></div>
<div id="gdy-media-modal" class="gdy-modal" aria-hidden="true">
  <div class="gdy-modal-header">
    <div class="gdy-modal-title">مكتبة الوسائط</div>
    <div style="display:flex; gap:8px;">
      <a href="../media/upload.php" target="_blank" rel="noopener" class="btn btn-sm btn-outline-info">رفع جديد</a>
      <button type="button" id="gdy-media-close">إغلاق</button>
    </div>
  </div>
  <iframe id="gdy-media-frame" src="../media/picker.php?target=content" style="width:100%;height:calc(90vh - 54px);border:0;"></iframe>
</div>


<!-- Editor initialized by /admin/assets/editor/gdy-editor.js -->


<?php require_once __DIR__ . '/../layout/footer.php'; ?>