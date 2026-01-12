<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

use Godyar\Auth;

$currentPage = 'pages';
$pageTitle   = __('t_0db7b2425d', 'إنشاء صفحة جديدة');

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// تحقّق من تسجيل الدخول
try {
    if (class_exists(Auth::class) && method_exists(Auth::class, 'isLoggedIn')) {
        if (!Auth::isLoggedIn()) {
            header('Location: ../login.php');
            exit;
        }
    } else {
        if (empty($_SESSION['user']) || (($_SESSION['user']['role'] ?? '') === 'guest')) {
            header('Location: ../login.php');
            exit;
        }
    }
} catch (Throwable $e) {
    @error_log('[Godyar Pages Create] Auth error: ' . $e->getMessage());
    if (empty($_SESSION['user']) || (($_SESSION['user']['role'] ?? '') === 'guest')) {
        header('Location: ../login.php');
        exit;
    }
}

$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    die('Database connection not available.');
}

// slugify بسيط يدعم العربية (يُبقي الحروف العربية ويسمح بالـ -)
function godyar_slugify(string $str): string {
    $str = trim($str);
    $str = preg_replace('~[^\p{Arabic}A-Za-z0-9]+~u', '-', $str);
    $str = trim($str, '-');
    if (function_exists('mb_strtolower')) {
        $str = mb_strtolower($str, 'UTF-8');
    } else {
        $str = strtolower($str);
    }
    return $str !== '' ? $str : ('page-' . time());
}

// POST معالجة
$errors  = [];
$title   = '';
$slug    = '';
$content = '';
$status  = 'draft';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = trim((string)($_POST['title'] ?? ''));
    $slug    = trim((string)($_POST['slug'] ?? ''));
    $content = trim((string)($_POST['content'] ?? ''));
    $status  = (string)($_POST['status'] ?? 'draft');

    if ($title === '') {
        $errors[] = __('t_cdf985bb4c', 'الرجاء إدخال عنوان الصفحة.');
    }

    if ($slug === '') {
        $slug = godyar_slugify($title);
    } else {
        $slug = godyar_slugify($slug);
    }

    if ($status !== 'published') {
        $status = 'draft';
    }

    if (!$errors) {
        try {
            // تأكد من فريدية الـ slug
            $baseSlug = $slug;
            $i = 2;
            while (true) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM pages WHERE slug = :slug");
                $stmt->execute(['slug' => $slug]);
                if ((int)$stmt->fetchColumn() === 0) {
                    break;
                }
                $slug = $baseSlug . '-' . $i;
                $i++;
            }

            $stmt = $pdo->prepare("
                INSERT INTO pages (title, slug, content, status, created_at, updated_at)
                VALUES (:title, :slug, :content, :status, NOW(), NOW())
            ");
            $stmt->execute([
                'title'   => $title,
                'slug'    => $slug,
                'content' => $content,
                'status'  => $status,
            ]);

            header('Location: index.php?saved=1');
            exit;
        } catch (Throwable $e) {
            $errors[] = __('t_18a2b72a1b', 'حدث خطأ أثناء الحفظ، الرجاء المحاولة لاحقاً.');
            @error_log('[Godyar Pages Create] Insert error: ' . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>
<style>
/* أدوات الذكاء الاصطناعي في إنشاء الصفحات */
.gdy-ai-tools {
  margin-top: 0.5rem;
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}
.gdy-btn-sm {
  padding: 0.25rem 0.7rem;
  font-size: 0.78rem;
  border-radius: 999px;
}
.gdy-ai-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.7rem;
  padding: 0.1rem 0.5rem;
  border-radius: 999px;
  background: rgba(56, 189, 248, 0.12);
  color: #7dd3fc;
}

html, body {
  overflow-x: hidden;
}

.admin-content.container-fluid {
  padding-top: 1.2rem;
  padding-bottom: 1.4rem;
}

.gdy-layout-wrap {
  width: 100%;
  max-width: 1180px;
  margin-right: .75rem;
  margin-left: auto;
}

@media (max-width: 991.98px) {
  .gdy-layout-wrap {
    margin-right: .5rem;
    margin-left: .5rem;
    max-width: 100%;
  }
}
</style>

<div class="admin-content container-fluid py-4">
  <div class="gdy-layout-wrap">
  <div class="gdy-page-header d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 text-white mb-1"><?= h(__('t_0db7b2425d', 'إنشاء صفحة جديدة')) ?></h1>
      <p class="text-muted mb-0"><?= h(__('t_2289e0c577', 'قم بإضافة صفحة ثابتة جديدة للموقع.')) ?></p>
    </div>
    <a href="index.php" class="btn btn-sm btn-secondary">
      <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_fed95e1016', 'عودة للقائمة')) ?>
    </a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $err): ?>
          <li><?= h($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="card shadow-sm glass-card" style="background:rgba(15,23,42,0.96);border-radius:16px;border:1px solid #1f2937;">
    <div class="card-body">
      <form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label text-white"><?= h(__('t_3463295a54', 'عنوان الصفحة')) ?></label>
            <input type="text" name="title" class="form-control" required
                   value="<?= h($title) ?>">
            <div class="gdy-ai-tools">
              <button type="button" class="btn btn-outline-info btn-sm gdy-btn-sm" data-action="ai-suggest-title">
                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                <?= h(__('t_8fcb7c90f2', 'اقتراح عنوان بالذكاء الاصطناعي')) ?>
              </button>
              <span class="gdy-ai-badge">
                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                <?= h(__('t_25fc51906e', 'مساعد ذكي')) ?>
              </span>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label text-white"><?= h(__('t_fd790f321a', 'الرابط المختصر (Slug)')) ?></label>
            <input type="text" name="slug" class="form-control"
                   placeholder="<?= h(__('t_1c2167a7f4', 'اختياري – يُولّد تلقائياً من العنوان')) ?>"
                   value="<?= h($slug) ?>">
          </div>
          <div class="col-12">
            <label class="form-label text-white"><?= h(__('t_e261adf643', 'محتوى الصفحة')) ?></label>
            <textarea name="content" rows="8" class="form-control"
                      placeholder="<?= h(__('t_e261adf643', 'محتوى الصفحة')) ?>"><?= h($content) ?></textarea>
            <div class="gdy-ai-tools">
              <button type="button" class="btn btn-success btn-sm gdy-btn-sm" data-action="ai-improve-content">
                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                <?= h(__('t_a796addf29', 'تحسين النص بالذكاء الاصطناعي')) ?>
              </button>
            </div>
          </div>
          <div class="col-md-3">
            <label class="form-label text-white"><?= h(__('t_1253eb5642', 'الحالة')) ?></label>
            <select name="status" class="form-select">
              <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>><?= h(__('t_9071af8f2d', 'مسودة')) ?></option>
              <option value="published" <?= $status === 'published' ? 'selected' : '' ?>><?= h(__('t_c67d973434', 'منشورة')) ?></option>
            </select>
          </div>
        </div>

        <div class="mt-3 d-flex justify-content-end gap-2">
          <button type="submit" class="btn btn-primary">
            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_871a087a1d', 'حفظ')) ?>
          </button>
        </div>
      </form>
    </div>
  </div>
  </div>
</div>
<?php require_once __DIR__ . '/../layout/footer.php'; ?>
