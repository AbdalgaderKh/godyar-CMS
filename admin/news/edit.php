<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/_news_helpers.php';
// godyar/admin/news/edit.php — تعديل خبر

$bootstrapPaths = [
    __DIR__ . '/../../includes/bootstrap.php',
    __DIR__ . '/../../godyar/includes/bootstrap.php',
    __DIR__ . '/../../bootstrap.php',
];

$bootstrapLoaded = false;
foreach ($bootstrapPaths as $path) {
    if (is_file($path)) {
        require_once $path;
        $bootstrapLoaded = true;
        break;
    }
}
if (!$bootstrapLoaded) die('Bootstrap file not found.');

$authPaths = [
    __DIR__ . '/../../includes/auth.php',
    __DIR__ . '/../../godyar/includes/auth.php',
    __DIR__ . '/../../auth.php',
];

$authLoaded = false;
foreach ($authPaths as $path) {
    if (is_file($path)) {
        require_once $path;
        $authLoaded = true;
        break;
    }
}
if (!$authLoaded) die('Auth file not found.');

use Godyar\Auth;

$currentPage = 'posts';
$pageTitle   = __('t_48ecc24b1d', 'تعديل خبر');

if (!Auth::isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

if (!Auth::hasPermission('posts.edit') && !Auth::hasPermission('posts.edit_own')) {
    header('Location: /admin/index.php');
    exit;
}

$isWriter = Auth::isWriter();
$userId   = (int)($_SESSION['user']['id'] ?? 0);

/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) die(__('t_f1ef308d2e', 'لا يوجد اتصال بقاعدة البيانات'));

// تأكد من وجود جداول workflow (ملاحظات + سجل التعديلات)
try {
    gdy_ensure_news_notes_table($pdo);
    gdy_ensure_news_revisions_table($pdo);
} catch (Throwable) {}

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// -----------------------------------------------------------------------------
// جلب الخبر
// -----------------------------------------------------------------------------
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die(__('t_c0fe704015', 'معرّف الخبر غير صالح.'));

try {
    $stmt = $pdo->prepare("SELECT * FROM news WHERE id = :id LIMIT 1");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $news = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$news) die(__('t_d7e7355e98', 'لم يتم العثور على الخبر المطلوب.'));
    $oldStatus = (string)($news['status'] ?? '');

} catch (Throwable $e) {
    @error_log('Error fetching news for edit: ' . $e->getMessage());
    die(__('t_de84129e91', 'حدث خطأ أثناء جلب بيانات الخبر.'));
}

if ($isWriter) {
    $ownerId = (int)($news['author_id'] ?? 0);
    if ($ownerId !== $userId) {
        http_response_code(403);
        exit(__('t_402b2914c9', 'غير مسموح لك تعديل هذا المقال.'));
    }
}

// -----------------------------------------------------------------------------
// جلب التصنيفات
// -----------------------------------------------------------------------------
$categories = [];
try {
    $stmt = $pdo->query("SELECT id, name, slug, parent_id 
                         FROM categories 
                         ORDER BY parent_id IS NULL DESC, parent_id ASC, name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    @error_log('Error fetching categories: ' . $e->getMessage());
}

$categoriesTree = [];
foreach ($categories as $cat) {
    $parentId = $cat['parent_id'] ?? null;
    if ($parentId === null) {
        $categoriesTree[$cat['id']] = [
            'id'       => (int)$cat['id'],
            'name'     => (string)$cat['name'],
            'slug'     => (string)$cat['slug'],
            'children' => [],
        ];
    }
}
foreach ($categories as $cat) {
    $parentId = $cat['parent_id'] ?? null;
    if ($parentId !== null && isset($categoriesTree[$parentId])) {
        $categoriesTree[$parentId]['children'][] = [
            'id'   => (int)$cat['id'],
            'name' => (string)$cat['name'],
            'slug' => (string)$cat['slug'],
        ];
    }
}

// -----------------------------------------------------------------------------
// جلب كتّاب الرأي
// -----------------------------------------------------------------------------
$opinionAuthors = [];
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'opinion_authors'");
    if ($stmt->fetchColumn()) {
        $stmt2 = $pdo->query("SELECT id, name FROM opinion_authors ORDER BY name ASC");
        $opinionAuthors = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    @error_log('Error checking / fetching opinion_authors: ' . $e->getMessage());
}

// -----------------------------------------------------------------------------
// تهيئة القيم
// -----------------------------------------------------------------------------
$newsCols = gdy_db_columns($pdo, 'news');

$title        = (string)($news['title'] ?? '');
$slug         = (string)($news['slug'] ?? '');
$excerpt      = (string)($news['excerpt'] ?? ($news['summary'] ?? ''));
$content      = (string)($news['content'] ?? ($news['body'] ?? ''));

$tags_input = '';
try { $tags_input = gdy_get_news_tags($pdo, $id); } catch (Throwable $e) { $tags_input = ''; }
if ($tags_input === '' && isset($news['tags'])) $tags_input = (string)$news['tags'];

$relatedNews = [];
try { $relatedNews = gdy_get_related_news($pdo, $id, 6); } catch (Throwable) { $relatedNews = []; }


$publish_at   = isset($news['publish_at']) ? substr(str_replace(' ', 'T', (string)$news['publish_at']), 0, 16) : '';
$unpublish_at = isset($news['unpublish_at']) ? substr(str_replace(' ', 'T', (string)$news['unpublish_at']), 0, 16) : '';

$seo_title       = (string)($news['seo_title'] ?? '');
$seo_description = (string)($news['seo_description'] ?? '');
$seo_keywords    = (string)($news['seo_keywords'] ?? '');

$category_id  = (int)($news['category_id'] ?? 0);

// ✅ مهم: احتفظ بـ author_id الأصلي ولا تغيّره لأن الحقل لن يظهر في النموذج
$author_id    = (int)($news['author_id'] ?? 0);
if ($author_id <= 0) $author_id = $userId;

$opinion_author_id = (int)($news['opinion_author_id'] ?? 0);

$status       = (string)($news['status'] ?? 'published');
$featured     = (int)($news['featured'] ?? 0);
$is_breaking  = (int)($news['is_breaking'] ?? 0);

$published_at = '';
if (!empty($news['published_at']) && $news['published_at'] !== '0000-00-00 00:00:00') {
    $published_at = date('Y-m-d\TH:i', strtotime((string)$news['published_at']));
}

$attachments = gdy_get_news_attachments($pdo, $id);

// ملاحظات التحرير + سجل التعديلات
$editorialNotes = [];
$revisionHistory = [];
try { $editorialNotes = gdy_get_news_notes($pdo, $id, 50); } catch (Throwable) { $editorialNotes = []; }
try { $revisionHistory = gdy_get_news_revisions($pdo, $id, 20); } catch (Throwable) { $revisionHistory = []; }

$errors  = [];
$updated = false;

// -----------------------------------------------------------------------------
// معالجة التحديث
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['delete_attachment_id'])) {
        $delId = (int)($_POST['delete_attachment_id'] ?? 0);
        if ($delId > 0) {
            gdy_delete_news_attachment($pdo, $delId, $id);
        }
        header('Location: edit.php?id=' . $id . '&att_deleted=1');
        exit;
    }

    // إضافة ملاحظة تحرير (بدون تعديل بقية الحقول)
    if (isset($_POST['add_note'])) {
        $note = trim((string)($_POST['new_note'] ?? ''));
        if ($note !== '') {
            try { gdy_add_news_note($pdo, $id, $userId ?: null, $note); } catch (Throwable) {}
        }
        header('Location: edit.php?id=' . $id . '#editorial-notes');
        exit;
    }

    // استرجاع نسخة سابقة
    if (isset($_POST['restore_revision_id'])) {
        $revId = (int)($_POST['restore_revision_id'] ?? 0);
        if ($revId > 0) {
            try { gdy_restore_news_revision($pdo, $id, $revId, $userId ?: null); } catch (Throwable) {}
        }
        header('Location: edit.php?id=' . $id . '#revision-history');
        exit;
    }

    $title        = trim((string)($_POST['title'] ?? ''));
    $slug         = trim((string)($_POST['slug'] ?? ''));
    $excerpt      = trim((string)($_POST['excerpt'] ?? ''));
    $content      = (string)($_POST['content'] ?? '');

    $publish_at = trim((string)($_POST['publish_at'] ?? ''));
    $publish_at_sql = gdy_dt_local_to_sql($publish_at);

    $unpublish_at = trim((string)($_POST['unpublish_at'] ?? ''));
    $unpublish_at_sql = gdy_dt_local_to_sql($unpublish_at);

    $seo_title = trim((string)($_POST['seo_title'] ?? ''));
    $seo_description = trim((string)($_POST['seo_description'] ?? ''));
    $seo_keywords = trim((string)($_POST['seo_keywords'] ?? ''));

    $category_id  = (int)($_POST['category_id'] ?? 0);

    // ✅ لا نقرأ author_id من POST (لأنه غير موجود) — نحافظ على قيمة الخبر
    // الكاتب دائماً يظل نفسه
    if ($isWriter) {
        $author_id = $userId;
    }

    $opinion_author_id = (int)($_POST['opinion_author_id'] ?? 0);
    $tags_input   = trim((string)($_POST['tags'] ?? ''));
    $status       = trim((string)($_POST['status'] ?? 'draft'));

    // زرّ سير العمل (حفظ / إرسال / اعتماد / نشر / أرشفة)
    $workflowAction = trim((string)($_POST['workflow_action'] ?? 'save'));
    if ($workflowAction === '') $workflowAction = 'save';

    if ($workflowAction === 'submit') {
        $status = 'pending';
    }
    if (!$isWriter) {
        if ($workflowAction === 'approve') {
            $status = 'approved';
        } elseif ($workflowAction === 'publish') {
            $status = 'published';
            if (trim((string)($_POST['published_at'] ?? '')) === '') {
                // إن لم يُحدد تاريخ نشر، اعتمده الآن
                $published_at = date('Y-m-d\\TH:i');
            }
        } elseif ($workflowAction === 'archive') {
            $status = 'archived';
        }
    }

    // ✅ دعم archived لأنه موجود عندك في قائمة الحالة
    $allowedStatuses = $isWriter ? ['draft','pending'] : ['published','draft','pending','approved','archived'];
    if (!in_array($status, $allowedStatuses, true)) {
        $status = $isWriter ? 'pending' : 'draft';
    }
    if ($isWriter && $status === 'published') $status = 'pending';

    $featured     = isset($_POST['featured']) ? 1 : 0;
    if ($isWriter) $featured = 0;

    $is_breaking  = isset($_POST['is_breaking']) ? 1 : 0;
    if ($isWriter) $is_breaking = 0;

    $published_at = trim((string)($_POST['published_at'] ?? ($published_at ?? '')));

    if ($title === '') $errors['title'] = __('t_a177201400', 'يرجى إدخال عنوان الخبر.');

    // slug اختياري: إن كان فارغاً نحاول توليده من العنوان (يدعم العربية)
    $slug = trim($slug);
    if ($slug === '' && $title !== '') {
        $slug = $title;
    }

    // تنظيف الـ slug: السماح بالحروف/الأرقام العربية والإنجليزية مع الشرطات
    if ($slug !== '') {
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $slug);
        $slug = trim((string)$slug, '-');
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


    if ($category_id <= 0) $errors['category_id'] = __('t_96bde08b29', 'يرجى اختيار التصنيف.');

    $publishedAtForDb = gdy_dt_local_to_sql($published_at);

    if (!$errors) {

        // ---------------------------------------------------------------------
        // (اختياري) رفع صورة جديدة للخبر — تستبدل الصورة الحالية عند الحفظ
        // ---------------------------------------------------------------------
        $oldImagePath = (string)($news['image'] ?? '');
        $imagePath = $oldImagePath !== '' ? $oldImagePath : null;
        $uploadedNewImage = false;

        if (isset($_FILES['image']) && (($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) {
            if (($_FILES['image']['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_OK) {
                $tmpName  = $_FILES['image']['tmp_name'] ?? '';
                $origName = $_FILES['image']['name'] ?? '';
                $size     = (int)($_FILES['image']['size'] ?? 0);

                $maxSize = 5 * 1024 * 1024;
                if ($size > $maxSize) {
                    $errors['image'] = __('t_75a6c044df', 'حجم الصورة أكبر من المسموح (5 ميجابايت).');
                } else {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime  = $finfo ? finfo_file($finfo, $tmpName) : '';
                    if ($finfo) finfo_close($finfo);

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
                        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

                        $baseName   = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
                        $fileName   = $baseName . '.' . $ext;
                        $targetPath = $uploadDir . $fileName;

                        if (move_uploaded_file($tmpName, $targetPath)) {
                            $imagePath = 'uploads/news/' . $fileName;
                            $uploadedNewImage = true;
                        } else {
                            $errors['image'] = __('t_6b9a5a9ac9', 'تعذر حفظ ملف الصورة على الخادم.');
                        }
                    }
                }
            } else {
                $errors['image'] = __('t_14a4dd5d81', 'حدث خطأ أثناء رفع الصورة. حاول مرة أخرى.');
            }
        }

        if (!$errors) {
            try {
            // قبل الحفظ: خزّن نسخة من الحالة الحالية (Revision)
            try {
                $preTags = '';
                try { $preTags = gdy_get_news_tags($pdo, $id); } catch (Throwable) {}
                $action = $workflowAction;
                if (!in_array($action, ['submit','approve','publish','archive','restore'], true)) $action = 'update';
                gdy_capture_news_revision($pdo, $id, $userId ?: null, $action, $news, $preTags);
            } catch (Throwable) {}

            $newsCols = gdy_db_columns($pdo, 'news');

            $sets = [];
            $addSet = function(string $col, string $ph) use (&$sets): void {
                $sets[] = "{$col} = {$ph}";
            };

            $addSet('title', ':title');
            $addSet('slug', ':slug');

            if (isset($newsCols['excerpt'])) $addSet('excerpt', ':excerpt');
            elseif (isset($newsCols['summary'])) $addSet('summary', ':excerpt');

            if (isset($newsCols['content'])) $addSet('content', ':content');
            elseif (isset($newsCols['body'])) $addSet('body', ':content');

            if (isset($newsCols['category_id'])) $addSet('category_id', ':category_id');

            // ✅ نحافظ على author_id الحالي (نحدّثه بنفس القيمة فقط)
            if (isset($newsCols['author_id'])) $addSet('author_id', ':author_id');

            if (isset($newsCols['opinion_author_id'])) $addSet('opinion_author_id', ':opinion_author_id');
            if (isset($newsCols['status'])) $addSet('status', ':status');
            if (isset($newsCols['featured'])) $addSet('featured', ':featured');
            if (isset($newsCols['is_breaking'])) $addSet('is_breaking', ':is_breaking');
            if (isset($newsCols['published_at'])) $addSet('published_at', ':published_at');
            if (isset($newsCols['publish_at'])) $addSet('publish_at', ':publish_at');
            if (isset($newsCols['unpublish_at'])) $addSet('unpublish_at', ':unpublish_at');
            if (isset($newsCols['seo_title'])) $addSet('seo_title', ':seo_title');
            if (isset($newsCols['seo_description'])) $addSet('seo_description', ':seo_description');
            if (isset($newsCols['seo_keywords'])) $addSet('seo_keywords', ':seo_keywords');
            if (isset($newsCols['image'])) $addSet('image', ':image');

            $sql = "UPDATE news SET " . implode(",\n", $sets) . "\nWHERE id = :id LIMIT 1";
            $stmt = $pdo->prepare($sql);

            $stmt->bindValue(':title', $title, PDO::PARAM_STR);
            $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);

            if (isset($newsCols['excerpt']) || isset($newsCols['summary'])) $stmt->bindValue(':excerpt', $excerpt, PDO::PARAM_STR);
            if (isset($newsCols['content']) || isset($newsCols['body'])) $stmt->bindValue(':content', $content, PDO::PARAM_STR);

            if (isset($newsCols['category_id'])) $stmt->bindValue(':category_id', $category_id, PDO::PARAM_INT);
            if (isset($newsCols['author_id']))   $stmt->bindValue(':author_id', $author_id, PDO::PARAM_INT);

            if (isset($newsCols['opinion_author_id'])) {
                $stmt->bindValue(':opinion_author_id', $opinion_author_id > 0 ? $opinion_author_id : null,
                    $opinion_author_id > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
            }

            if (isset($newsCols['status'])) $stmt->bindValue(':status', $status, PDO::PARAM_STR);
            if (isset($newsCols['featured'])) $stmt->bindValue(':featured', $featured, PDO::PARAM_INT);
            if (isset($newsCols['is_breaking'])) $stmt->bindValue(':is_breaking', $is_breaking, PDO::PARAM_INT);

            if (isset($newsCols['published_at'])) {
                if ($publishedAtForDb !== null) $stmt->bindValue(':published_at', $publishedAtForDb, PDO::PARAM_STR);
                else $stmt->bindValue(':published_at', null, PDO::PARAM_NULL);
            }

            if (isset($newsCols['publish_at'])) {
                $stmt->bindValue(':publish_at', $publish_at_sql, $publish_at_sql === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            }
            if (isset($newsCols['unpublish_at'])) {
                $stmt->bindValue(':unpublish_at', $unpublish_at_sql, $unpublish_at_sql === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            }

            if (isset($newsCols['seo_title'])) $stmt->bindValue(':seo_title', $seo_title, PDO::PARAM_STR);
            if (isset($newsCols['seo_description'])) $stmt->bindValue(':seo_description', $seo_description, PDO::PARAM_STR);
            if (isset($newsCols['seo_keywords'])) $stmt->bindValue(':seo_keywords', $seo_keywords, PDO::PARAM_STR);

            if (isset($newsCols['image'])) {
                $stmt->bindValue(':image', $imagePath, $imagePath ? PDO::PARAM_STR : PDO::PARAM_NULL);
            }

            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            // IndexNow: notify on publish or significant URL update
            try {
                $u = function_exists('base_url') ? rtrim((string)base_url(), '/') : '';
                $newsUrl = $u !== '' ? ($u . '/news/id/' . $id) : '';
                $sitemapUrl = $u !== '' ? ($u . '/sitemap.xml') : '';
                $newStatus = (string)($status ?? '');
                if ($pdo instanceof PDO) {
                    if ($newStatus === 'published' && $oldStatus !== 'published') {
                        @gdy_indexnow_submit($pdo, array_filter([$newsUrl, $sitemapUrl]));
                    } elseif ($newStatus === 'published' && $oldStatus === 'published') {
                        // تحديثات على خبر منشور: نرسل رابط الخبر + sitemap
                        @gdy_indexnow_submit($pdo, array_filter([$newsUrl, $sitemapUrl]));
                    }
                }
            } catch (Throwable $e) {
                @error_log('[IndexNow] edit ping failed: ' . $e->getMessage());
            }


            

// SEO cache invalidation (sitemap/rss)
$root = dirname(__DIR__, 2);
@unlink($root . '/cache/sitemap.xml');
@unlink($root . '/cache/rss.xml');
// بعد نجاح الحفظ: احذف الصورة القديمة إذا تم رفع صورة جديدة
            if (!empty($uploadedNewImage) && $uploadedNewImage && $oldImagePath !== '' && $imagePath && $oldImagePath !== $imagePath) {
                $oldFs = __DIR__ . '/../../' . ltrim($oldImagePath, '/');
                if (is_file($oldFs) && str_starts_with($oldImagePath, 'uploads/news/')) {
                    @unlink($oldFs);
                }
            }

            // حدّث قيمة الصورة في النسخة الحالية للعرض
            if (isset($newsCols['image'])) {
                $news['image'] = $imagePath;
            }

            try { gdy_sync_news_tags($pdo, $id, (string)$tags_input); }
            catch (Throwable $e) { @error_log('Error syncing tags: ' . $e->getMessage()); }

            try {
                if (isset($_FILES['attachments'])) {
                    $tmpErrors = [];
                    gdy_save_news_attachments($pdo, $id, (array)$_FILES['attachments'], $tmpErrors);
                }
            } catch (Throwable $e) {
                @error_log('Error saving attachments: ' . $e->getMessage());
            }

            $attachments = gdy_get_news_attachments($pdo, $id);
            try { $editorialNotes = gdy_get_news_notes($pdo, $id, 50); } catch (Throwable) { $editorialNotes = []; }
            try { $revisionHistory = gdy_get_news_revisions($pdo, $id, 20); } catch (Throwable) { $revisionHistory = []; }
            $updated = true;

        } catch (Throwable $e) {
            @error_log('Error updating news: ' . $e->getMessage());
            $errors['general'] = __('t_6f622496c6', 'حدث خطأ أثناء حفظ التعديلات. يرجى المحاولة مرة أخرى.');
        }
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
html, body { overflow-x: hidden; }
@media (min-width: 992px) { .admin-content { margin-right: 260px !important; } }
.admin-content.gdy-page .container-fluid { max-width: 1200px; margin: 0 auto; padding: 1.5rem 1rem 2rem; }
.admin-content.gdy-page { background: linear-gradient(135deg, #0f172a 0%, #020617 100%); min-height: 100vh; color: #e5e7eb; font-family: "Cairo", system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
.gdy-header { background: linear-gradient(135deg, #0ea5e9, #0369a1); color: #fff; padding: 1.5rem 2rem; margin: 0 -1rem 1rem; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.6); border-radius: 1rem; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; }
.gdy-header h1 { margin: 0 0 .35rem; font-size: 1.4rem; font-weight: 700; }
.gdy-header p { margin: 0; font-size: .9rem; opacity: .9; }
.gdy-actions { display: flex; flex-wrap: wrap; gap: .5rem; }
.gdy-card { background: rgba(15, 23, 42, 0.85); border-radius: 1rem; border: 1px solid rgba(148, 163, 184, 0.45); box-shadow: 0 15px 45px rgba(15, 23, 42, 0.9); overflow: hidden; }
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
.badge-soft { padding: .15rem .4rem; border-radius: .75rem; font-size: .7rem; border: 1px solid rgba(148,163,184,.4); color: #e5e7eb; background: rgba(15,23,42,.9); }
.gdy-options-box { border-radius: .9rem; border: 1px solid rgba(148,163,184,.4); background: radial-gradient(circle at top left, rgba(15,23,42,.95), rgba(15,23,42,1)); padding: 1rem; color:#e5e7eb; }
.gdy-options-box h3 { font-size: .95rem; margin-bottom: .75rem; }
.gdy-options-box .form-check { margin-bottom: .4rem; }
.gdy-form-footer { border-top:1px solid rgba(148,163,184,.25); border-bottom:none; }
.gdy-form-body { padding-bottom: .75rem; }
.gdy-btn { border-radius: .7rem; border: none; padding: .55rem 1.1rem; font-size: .85rem; font-weight: 600; display: inline-flex; align-items: center; gap: .5rem; text-decoration: none; cursor: pointer; transition: all .2s ease; }
.gdy-btn i { font-size: .9rem; }
.gdy-btn-primary { background: linear-gradient(135deg, #22c55e, #16a34a); color: #0f172a; }
.gdy-btn-primary:hover { filter: brightness(1.1); transform: translateY(-1px); }
.gdy-btn-secondary { background: rgba(15,23,42,1); color: #e5e7eb; border: 1px solid rgba(148,163,184,.5); }
.gdy-btn-secondary:hover { background: rgba(15,23,42,.9); transform: translateY(-1px); }
.tags-input { min-height: 38px; }
.gdy-note-box { border-radius: .75rem; border: 1px dashed rgba(148,163,184,.7); background: rgba(15,23,42,.85); padding: .75rem .9rem; font-size: .8rem; color:#d1d5db; }
@media (max-width: 767.98px) { .gdy-header { padding: 1.25rem 1.25rem; margin: 0 -0.75rem 1rem; } .gdy-card-body { padding: 1rem; } }

.gdy-dropzone { border-radius: .9rem; border: 1.5px dashed rgba(148,163,184,.55); padding: 1.1rem; background: rgba(15,23,42,.6); text-align: center; cursor: pointer; transition: all .2s ease; }
.gdy-dropzone:hover, .gdy-dropzone.is-dragover { border-color: #0ea5e9; background: rgba(15,23,42,1); }
.gdy-dropzone-inner i { font-size: 1.6rem; color: #38bdf8; }
.gdy-image-preview img { max-height: 220px; object-fit: contain; }


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
    <div class="gdy-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
      <div>
        <h1><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_12df53dbfc', 'تعديل الخبر #')) ?><?= (int)$id ?></h1>
        <p><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg><?= h(__('t_927a001b84', 'عدّل بيانات الخبر ثم احفظ التغييرات')) ?></p>
      </div>
      <div class="gdy-actions">
        <a href="create.php" class="gdy-btn gdy-btn-secondary"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_0d1f6ecf66', 'إضافة خبر جديد')) ?></a>
        <a href="index.php" class="gdy-btn gdy-btn-primary"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_a4c812b3d4', 'عرض الأخبار')) ?></a>
      </div>
    </div>

    <?php if ($updated && empty($errors['general'])): ?>
      <div class="alert alert-success"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_16e9a82b3b', 'تم حفظ التعديلات بنجاح.')) ?></div>
    <?php endif; ?>

    <?php if (!empty($errors['general'])): ?>
      <div class="alert alert-danger"><?= h($errors['general']) ?></div>
    <?php endif; ?>

    <div class="gdy-card mb-4">
      <div class="gdy-card-header">
        <h2><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_6c83766790', 'بيانات الخبر الأساسية')) ?></h2>
        <small><?= h(__('t_7e11daf821', 'يمكنك تعديل العنوان والمحتوى والتصنيف وبقية الحقول')) ?></small>
      </div>

      <div class="gdy-card-body gdy-form-body">
        <form method="post" action="" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

          <div class="row g-3">
            <div class="col-md-8">

              <div class="mb-3">
                <label class="gdy-form-label"><span class="text-danger">*</span><?= h(__('t_6dc6588082', 'العنوان')) ?></label>
                <input type="text" name="title"
                  class="form-control form-control-sm <?= isset($errors['title']) ? 'is-invalid' : '' ?>"
                  value="<?= h($title) ?>" placeholder="<?= h(__('t_d48cac613d', 'أدخل عنوان الخبر هنا...')) ?>">
                <?php if (isset($errors['title'])): ?>
                  <div class="invalid-feedback"><?= h($errors['title']) ?></div>
                <?php else: ?>
                  <div class="form-text"><?= h(__('t_9c84024cc6', 'اجعل العنوان واضحاً وجاذباً.')) ?></div>
                <?php endif; ?>
              </div>

              <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <label class="gdy-form-label mb-0"><?= h(__('t_0781965540', 'الرابط (Slug)')) ?></label>
                  <span class="badge-soft"><?= h(__('t_f756530783', 'يمكنك تغييره بعناية، يؤثر على رابط الخبر')) ?></span>
                </div>
                <input type="text" name="slug"
                  class="form-control form-control-sm <?= isset($errors['slug']) ? 'is-invalid' : '' ?>"
                  value="<?= h($slug) ?>" placeholder="<?= h(__('t_0f7738c806', 'مثال: breaking-news-2024')) ?>">
                <?php if (isset($errors['slug'])): ?>
                  <div class="invalid-feedback"><?= h($errors['slug']) ?></div>
                <?php endif; ?>
              </div>

              <div class="mb-3">
                <label class="gdy-form-label"><?= h(__('t_17220bb323', 'ملخص قصير (اختياري)')) ?></label>
                <textarea name="excerpt" rows="3" class="form-control form-control-sm"
                  placeholder="<?= h(__('t_774de80702', 'يمكن كتابة فقرة قصيرة تلخص أهم ما في الخبر.')) ?>"><?= h($excerpt) ?></textarea>
              </div>

              <div class="mb-3">
                <label class="gdy-form-label"><?= h(__('t_1db9e4530e', 'نص الخبر / المقال')) ?></label>
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


                <div class="form-text"><?= h(__('t_cdab1836ce', 'يمكنك ربط هذا الحقل بمحرر WYSIWYG لاحقاً.')) ?></div>
              </div>

              
              <?php $currentImage = (string)($news['image'] ?? ''); ?>
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

                <div id="image-preview" class="gdy-image-preview mt-2 <?= $currentImage ? '' : 'd-none' ?>">
                  <img src="<?= $currentImage ? '/' . h(ltrim($currentImage, '/')) : '' ?>"
                    alt="<?= h(__('t_0075044f10', 'معاينة الصورة')) ?>" class="img-fluid rounded mb-2">
                  <div class="small text-muted" id="image-info">
                    <?php if ($currentImage): ?>
                      <?= h(__('t_9b0b0e5e2a', 'الصورة الحالية')) ?>: <?= h(basename($currentImage)) ?><br>
                      <?= h(__('t_3b0b82f5d7', 'رفع صورة جديدة سيستبدل الصورة الحالية.')) ?>
                    <?php endif; ?>
                  </div>
                </div>

                <?php if (isset($errors['image'])): ?>
                  <div class="invalid-feedback d-block"><?= h($errors['image']) ?></div>
                <?php endif; ?>
              </div>

<div class="mb-3">
                <label class="gdy-form-label"><?= h(__('t_5deb5044ef', 'مرفقات الخبر (PDF / Word / Excel ...)')) ?></label>
                <input type="file" name="attachments[]" id="attachments-input" class="form-control form-control-sm" multiple
                  accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.7z,.txt,.rtf,image/*">
                <div class="form-text"><?= h(__('t_9127f92063', 'يمكنك إضافة عدة ملفات (حتى 20MB لكل ملف).')) ?></div>
                <div id="attachments-preview" class="mt-2 small text-muted"></div>
              </div>

              <?php if (!empty($attachments)): ?>
                <div class="mb-3">
                  <div class="gdy-options-box" style="padding:.85rem;">
                    <h3 class="mb-2"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_90e8368fd9', 'المرفقات الحالية')) ?></h3>
                    <div class="d-flex flex-column gap-2">
                      <?php foreach ($attachments as $att):
                        $attUrl = '/' . ltrim((string)$att['file_path'], '/');
                        $icon = gdy_attachment_icon_class((string)$att['original_name']);
                      ?>
                        <div class="d-flex align-items-center justify-content-between gap-2 p-2 rounded"
                             style="border:1px solid rgba(148,163,184,.25);background:rgba(2,6,23,.6);">
                          <div class="d-flex align-items-center gap-2" style="min-width:0;">
                            <i class="<?= h($icon) ?>"></i>
                            <div style="min-width:0;">
                              <div class="small text-white text-truncate" style="max-width:420px;"><?= h($att['original_name']) ?></div>
                              <div class="text-muted small">
                                <?php if (!empty($att['file_size'])): ?>
                                  <?= number_format(((int)$att['file_size'])/1024, 0) ?> KB
                                <?php endif; ?>
                              </div>
                            </div>
                          </div>

                          <div class="d-flex align-items-center gap-2 flex-shrink-0">
                            <a class="btn btn-sm btn-outline-light" href="<?= h($attUrl) ?>" target="_blank" title="<?= h(__('t_6e63a5f0af', 'عرض')) ?>"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></a>
                            <a class="btn btn-sm btn-outline-info" href="<?= h($attUrl) ?>" download title="<?= h(__('t_969879d297', 'تحميل')) ?>"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></a>
                            <button type="submit" name="delete_attachment_id" value="<?= (int)$att['id'] ?>"
                              class="btn btn-sm btn-outline-danger" formnovalidate
                              data-confirm=<?= json_encode(__('t_9bc98bfbb1', 'حذف هذا المرفق؟'), JSON_UNESCAPED_UNICODE) ?> title="<?= h(__('t_3b9854e1bb', 'حذف')) ?>">
                              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                            </button>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
              <?php endif; ?>

            </div>

            <div class="col-md-4">
              <div class="gdy-options-box mb-3">
                <h3><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_cf14329701', 'التصنيف')) ?></h3>

                <div class="mb-3">
                  <label class="gdy-form-label"><span class="text-danger">*</span><?= h(__('t_cf14329701', 'التصنيف')) ?></label>
                  <select name="category_id" class="form-select form-select-sm <?= isset($errors['category_id']) ? 'is-invalid' : '' ?>">
                    <option value="0"><?= h(__('t_0a8d417cf7', '-- اختر التصنيف --')) ?></option>
                    <?php foreach ($categoriesTree as $parent): ?>
                      <optgroup label="<?= h($parent['name']) ?>">
                        <?php if (!empty($parent['children'])): ?>
                          <?php foreach ($parent['children'] as $child): ?>
                            <option value="<?= (int)$child['id'] ?>" <?= (int)$child['id'] === (int)$category_id ? 'selected' : '' ?>>
                              <?= h($child['name']) ?>
                            </option>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <option value="<?= (int)$parent['id'] ?>" <?= (int)$parent['id'] === (int)$category_id ? 'selected' : '' ?>>
                            <?= h($parent['name']) ?>
                          </option>
                        <?php endif; ?>
                      </optgroup>
                    <?php endforeach; ?>
                  </select>
                  <?php if (isset($errors['category_id'])): ?>
                    <div class="invalid-feedback"><?= h($errors['category_id']) ?></div>
                  <?php endif; ?>
                </div>

                <?php if (!empty($opinionAuthors)): ?>
                  <div class="mb-3">
                    <label class="gdy-form-label"><?= h(__('t_9fd4be08b2', 'كاتب الرأي (إن كان مقال رأي)')) ?></label>
                    <select name="opinion_author_id" class="form-select form-select-sm">
                      <option value="0"><?= h(__('t_7f3152db47', '-- ليس مقال رأي --')) ?></option>
                      <?php foreach ($opinionAuthors as $oa): ?>
                        <option value="<?= (int)$oa['id'] ?>" <?= (int)$oa['id'] === (int)$opinion_author_id ? 'selected' : '' ?>>
                          <?= h($oa['name']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <div class="form-text"><?= h(__('t_68e0482040', 'لو اخترت كاتب رأي، يُفضّل أن يكون التصنيف مناسباً (مثل الرأي).')) ?></div>
                  </div>
                <?php endif; ?>
              </div>

              <div class="gdy-options-box mb-3">
                <h3><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_78865bbc36', 'حالة النشر')) ?></h3>

                <div class="mb-2">
                  <label class="gdy-form-label"><?= h(__('t_1253eb5642', 'الحالة')) ?></label>
                  <select name="status" class="form-select form-select-sm">
                    <option value="published" <?= $status === 'published' ? 'selected' : '' ?>><?= h(__('t_ecfb62b400', 'منشور')) ?></option>
                    <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>><?= h(__('t_aeb4d514db', 'جاهز للنشر')) ?></option>
                    <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>><?= h(__('t_9071af8f2d', 'مسودة')) ?></option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>><?= h(__('t_e9210fb9c2', 'بانتظار المراجعة')) ?></option>
                    <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>><?= h(__('t_2e67aea8ca', 'مؤرشف')) ?></option>
                  </select>
                </div>

                <div class="mb-2">
                  <label class="gdy-form-label"><?= h(__('t_9260bd801b', 'جدولة نشر (publish_at)')) ?></label>
                  <input type="datetime-local" name="publish_at" class="form-control form-control-sm" value="<?= h($publish_at ?? '') ?>">
                </div>

                <div class="mb-2">
                  <label class="gdy-form-label"><?= h(__('t_58e272983c', 'جدولة إلغاء نشر (unpublish_at)')) ?></label>
                  <input type="datetime-local" name="unpublish_at" class="form-control form-control-sm" value="<?= h($unpublish_at ?? '') ?>">
                </div>

                <div class="mb-2">
                  <label class="gdy-form-label"><?= h(__('t_9928bed160', 'تاريخ النشر (اختياري)')) ?></label>
                  <input type="datetime-local" name="published_at" class="form-control form-control-sm" value="<?= h($published_at) ?>">
                  <div class="form-text"><?= h(__('t_47d90f80b5', 'إذا تركته فارغاً، يظل كما هو.')) ?></div>
                </div>

                <div class="form-check mb-2">
                  <input class="form-check-input" type="checkbox" value="1" id="featured" name="featured" <?= $featured ? 'checked' : '' ?>>
                  <label class="form-check-label" for="featured"><?= h(__('t_6b9fb336b6', 'خبر مميز (Featured)')) ?></label>
                </div>

                <div class="form-check mb-2">
                  <input class="form-check-input" type="checkbox" value="1" id="is_breaking" name="is_breaking" <?= $is_breaking ? 'checked' : '' ?>>
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
                  <textarea name="tags" rows="2" class="form-control form-control-sm tags-input"
                    placeholder="<?= h(__('t_4669e64c64', 'اكتب الوسوم مفصولة بفاصلة')) ?>"><?= h($tags_input) ?></textarea>
                  <div class="form-text"><?= h(__('t_feeeca0c74', 'تساعد الوسوم في تنظيم المحتوى وتحسين البحث.')) ?></div>
                </div>
              </div>

          
<!-- Smart Suggestions: Related News -->
<?php if (!empty($relatedNews)): ?>
<div class="card mb-3">
  <div class="card-body">
    <h3><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_admin_related_news', 'اقتراحات: أخبار مشابهة')) ?></h3>
    <div class="list-group">
      <?php foreach ($relatedNews as $rn): ?>
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
           href="edit.php?id=<?= (int)$rn['id'] ?>">
          <span><?= h((string)($rn['title'] ?? ('#' . (int)$rn['id']))) ?></span>
          <span class="badge bg-secondary"><?= (int)($rn['score'] ?? 0) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="form-text mt-2"><?= h(__('t_admin_related_news_hint', 'النتائج تعتمد على تشابه الوسوم (Tags).')) ?></div>
  </div>
</div>
<?php endif; ?>
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



              <div class="gdy-options-box mb-3" id="editorial-notes">
                <h3><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_6d5f18f157', 'ملاحظات فريق التحرير')) ?></h3>

                <!-- ملاحظة: لا تضع <form> داخل <form> (يسبب تعطل أزرار الحفظ/النشر في المتصفح) -->
                <div class="mb-2">
                  <textarea name="new_note" rows="3" class="form-control form-control-sm" placeholder="<?= h(__('t_7f7c715e6d', 'اكتب ملاحظة للخبر (تظهر داخل لوحة التحكم فقط)')) ?>"></textarea>
                  <div class="d-flex justify-content-between align-items-center mt-2">
                    <div class="form-text"><?= h(__('t_ebe14519dc', 'للتعليقات الداخلية بين المحرر والكاتب (لا تظهر للزوار).')) ?></div>
                    <button type="submit" name="add_note" value="1" class="gdy-btn gdy-btn-secondary" formnovalidate>
                      <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_b9508aa2a9', 'إضافة')) ?>
                    </button>
                  </div>
                </div>

                <?php if (!empty($editorialNotes)): ?>
                  <div class="mt-2" style="max-height: 240px; overflow: auto;">
                    <?php foreach ($editorialNotes as $n): ?>
                      <div class="p-2 mb-2" style="border:1px solid rgba(148,163,184,.25);border-radius:.75rem;background:rgba(15,23,42,.55)">
                        <div class="d-flex justify-content-between gap-2">
                          <div class="small" style="color:#cbd5e1">
                            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg>
                            <?= h(($n['user_name'] ?? '') ?: '—') ?>
                          </div>
                          <div class="small" style="color:#94a3b8">
                            <?= h($n['created_at'] ?? '') ?>
                          </div>
                        </div>
                        <div class="mt-1" style="white-space:pre-wrap;line-height:1.6;">
                          <?= h($n['note'] ?? '') ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <div class="small" style="color:#94a3b8"><?= h(__('t_792758e182', 'لا توجد ملاحظات بعد.')) ?></div>
                <?php endif; ?>
              </div>

              <div class="gdy-options-box mb-3" id="revision-history">
                <h3><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_caa79d9298', 'سجل التعديلات')) ?></h3>
                <div class="form-text mb-2"><?= h(__('t_f3bc0558d2', 'يمكنك استرجاع نسخة سابقة عند الحاجة.')) ?></div>

                <?php if (!empty($revisionHistory)): ?>
                  <div style="max-height: 260px; overflow: auto;">
                    <?php foreach ($revisionHistory as $r): ?>
                      <div class="d-flex justify-content-between align-items-center gap-2 p-2 mb-2" style="border:1px solid rgba(148,163,184,.25);border-radius:.75rem;background:rgba(15,23,42,.55)">
                        <div>
                          <div class="small" style="color:#e5e7eb">
                            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                            <?= h($r['action'] ?? 'update') ?>
                          </div>
                          <div class="small" style="color:#94a3b8">
                            <?= h($r['created_at'] ?? '') ?>
                            <?php if (!empty($r['user_name'])): ?>
                              • <?= h($r['user_name']) ?>
                            <?php endif; ?>
                          </div>
                        </div>
                        <!-- نفس السبب: تجنب nested forms داخل النموذج الرئيسي -->
                        <button
                          type="submit"
                          name="restore_revision_id"
                          value="<?= (int)($r['id'] ?? 0) ?>"
                          class="gdy-btn gdy-btn-secondary"
                          formnovalidate
                          data-confirm=<?= json_encode(__('t_66f9d119fa', 'هل تريد استرجاع هذه النسخة؟ سيتم حفظ نسخة من الحالة الحالية أيضاً.'), JSON_UNESCAPED_UNICODE) ?>>
                          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_14c4cb02e3', 'استرجاع')) ?>
                        </button>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <div class="small" style="color:#94a3b8"><?= h(__('t_fcd3c7e498', 'لا توجد نسخ محفوظة بعد.')) ?></div>
                <?php endif; ?>
              </div>

              <?php if (!empty($opinionAuthors)): ?>
                <div class="gdy-note-box">
                  <strong><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_db8da3e12f', 'ملاحظة:')) ?></strong>
                  <div class="mt-1"><?= h(__('t_b6e2560649', 'تأكد من توافق')) ?><b><?= h(__('t_af47abc07d', 'كاتب الرأي')) ?></b><?= h(__('t_d5a86ca5b4', 'مع التصنيف (مثل تصنيف الرأي).')) ?></div>
                </div>
              <?php endif; ?>

            </div>
          </div>

          <div class="gdy-card-header gdy-form-footer mt-3">
            <div class="small text-muted"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_6297868797', 'تأكد من مراجعة التعديلات قبل الحفظ.')) ?></div>
            <div class="d-flex gap-2">
              <button type="submit" name="workflow_action" value="save" class="gdy-btn gdy-btn-primary">
                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_871a087a1d', 'حفظ')) ?>
              </button>

              <?php if ($isWriter && $status === 'draft'): ?>
                <button type="submit" name="workflow_action" value="submit" class="gdy-btn gdy-btn-secondary">
                  <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_a04c53724d', 'إرسال للمراجعة')) ?>
                </button>
              <?php endif; ?>

              <?php if (!$isWriter && $status === 'pending'): ?>
                <button type="submit" name="workflow_action" value="approve" class="gdy-btn gdy-btn-secondary">
                  <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_51cc4e6944', 'اعتماد')) ?>
                </button>
              <?php endif; ?>

              <?php if (!$isWriter && $status !== 'published'): ?>
                <button type="submit" name="workflow_action" value="publish" class="gdy-btn gdy-btn-secondary" data-confirm=<?= json_encode(__('t_b5797f62a0', 'هل تريد نشر هذا الخبر الآن؟'), JSON_UNESCAPED_UNICODE) ?>>
                  <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_1f0a9584a7', 'نشر')) ?>
                </button>
              <?php endif; ?>

              <?php if (!$isWriter && $status !== 'archived'): ?>
                <button type="submit" name="workflow_action" value="archive" class="gdy-btn gdy-btn-secondary" data-confirm=<?= json_encode(__('t_7e6cd317b1', 'هل تريد أرشفة هذا الخبر؟'), JSON_UNESCAPED_UNICODE) ?>>
                  <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_44e09190ab', 'أرشفة')) ?>
                </button>
              <?php endif; ?>
              <a href="view.php?id=<?= (int)$id ?>" class="gdy-btn gdy-btn-secondary"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_a477d64bb1', 'عرض الخبر')) ?></a>
              <a href="/preview/news/<?= (int)$id ?>" target="_blank" class="gdy-btn gdy-btn-secondary"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_26d308e1c7', 'معاينة في الموقع')) ?></a>
            </div>
          </div>

        </form>
      </div>
    </div>

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

  // Featured image upload (dropzone)
  const dropzone    = document.getElementById('image-dropzone');
  const fileInput   = document.getElementById('image-input');
  const previewWrap = document.getElementById('image-preview');
  const previewImg  = previewWrap ? previewWrap.querySelector('img') : null;
  const infoEl      = document.getElementById('image-info');

  function handleFile(file) {
    if (!file || !previewWrap || !previewImg) return;
    if (!file.type || file.type.indexOf('image/') !== 0) {
      alert(<?= json_encode(__('t_126be999c1', 'الرجاء اختيار ملف صورة صالح.'), JSON_UNESCAPED_UNICODE) ?>);
      return;
    }
    previewWrap.classList.remove('d-none');
    previewImg.src = URL.createObjectURL(file);
    if (infoEl) {
      const sizeKB = Math.round((file.size || 0) / 1024);
      infoEl.textContent = file.name + ' (' + sizeKB + ' KB)';
    }
  }

  if (dropzone && fileInput) {
    dropzone.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', (e) => {
      const file = e.target.files && e.target.files[0];
      if (file) handleFile(file);
    });

    ['dragenter','dragover'].forEach((evtName) => {
      dropzone.addEventListener(evtName, (e) => {
        e.preventDefault(); e.stopPropagation();
        dropzone.classList.add('is-dragover');
      });
    });

    ['dragleave','dragend','drop'].forEach((evtName) => {
      dropzone.addEventListener(evtName, (e) => {
        e.preventDefault(); e.stopPropagation();
        dropzone.classList.remove('is-dragover');
      });
    });

    dropzone.addEventListener('drop', (e) => {
      const dt = e.dataTransfer;
      if (!dt || !dt.files || !dt.files.length) return;
      fileInput.files = dt.files;
      handleFile(dt.files[0]);
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



<?php require_once __DIR__ . '/../layout/footer.php'; ?>