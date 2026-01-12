<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
// /godyar/admin/pages/edit.php

require_once __DIR__ . '/../../includes/bootstrap.php';

use Godyar\Auth;

$currentPage = 'pages';
$pageTitle   = __('t_6314df28de', 'تعديل صفحة ثابتة');

// تحقّق من تسجيل الدخول
try {
    if (class_exists(Auth::class) && method_exists(Auth::class, 'isLoggedIn')) {
        if (!Auth::isLoggedIn()) {
            header('Location: ../login.php');
            exit;
        }
    } else {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (empty($_SESSION['user']['id']) || (($_SESSION['user']['role'] ?? '') === 'guest')) {
            header('Location: ../login.php');
            exit;
        }
    }
} catch (Throwable $e) {
    @error_log('[Admin Pages Edit] auth check error: ' . $e->getMessage());
    header('Location: ../login.php');
    exit;
}

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    die('Database connection not available.');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php?error=invalid_id');
    exit;
}

$errors  = [];

// تحميل بيانات الصفحة
try {
    $stmt = $pdo->prepare("SELECT id, title, slug, content, status, created_at, updated_at FROM pages WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$page) {
        header('Location: index.php?error=not_found');
        exit;
    }
} catch (Throwable $e) {
    @error_log('[Admin Pages Edit] load page error: ' . $e->getMessage());
    header('Location: index.php?error=load_failed');
    exit;
}

$title      = (string)($page['title'] ?? '');
$slug       = (string)($page['slug'] ?? '');
$content    = (string)($page['content'] ?? '');
$status     = (string)($page['status'] ?? 'draft');
$created_at = (string)($page['created_at'] ?? '');
$updated_at = (string)($page['updated_at'] ?? '');

// رابط المعاينة في الواجهة
$frontSlug  = $slug !== '' ? $slug : (string)$id;
$previewUrl = '/page/' . rawurlencode($frontSlug);

// معالجة التحديث
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = trim((string)($_POST['title'] ?? ''));
    $slug    = trim((string)($_POST['slug'] ?? ''));
    $content = (string)($_POST['content'] ?? '');
    $status  = (string)($_POST['status'] ?? 'draft');

    if ($title === '') {
        $errors[] = __('t_c38c9ab206', 'عنوان الصفحة مطلوب.');
    }

    if ($slug === '') {
        $slug = $title;
    }

    // توليد slug بسيط
    $slug = preg_replace('/[^\p{Arabic}A-Za-z0-9]+/u', '-', $slug);
    $slug = trim($slug, "- \t\n\r\0\x0B");
    if ($slug === '') {
        $slug = 'page-' . $id;
    }

    // تحقق من عدم تكرار الـ slug
    try {
        $stmtSlug = $pdo->prepare("SELECT COUNT(*) FROM pages WHERE slug = :slug AND id != :id");
        $stmtSlug->execute([
            'slug' => $slug,
            'id'   => $id,
        ]);
        if ((int)$stmtSlug->fetchColumn() > 0) {
            $errors[] = __('t_7e93229117', 'هذا الـ slug مستخدم لصفحة أخرى، الرجاء اختيار آخر.');
        }
    } catch (Throwable $e) {
        @error_log('[Admin Pages Edit] slug check error: ' . $e->getMessage());
        $errors[] = __('t_02d52a4506', 'حدث خطأ أثناء فحص الـ slug.');
    }

    if (empty($errors)) {
        try {
            $stmtUp = $pdo->prepare("
                UPDATE pages
                SET title = :title,
                    slug = :slug,
                    content = :content,
                    status = :status,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmtUp->execute([
                'title'   => $title,
                'slug'    => $slug,
                'content' => $content,
                'status'  => $status,
                'id'      => $id,
            ]);

            header('Location: index.php?saved=1');
            exit;
        } catch (Throwable $e) {
            @error_log('[Admin Pages Edit] update error: ' . $e->getMessage());
            $errors[] = __('t_ef95baa3c9', 'حدث خطأ أثناء حفظ التعديلات، الرجاء المحاولة لاحقاً.');
        }
    }
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>
<style>
:root {
  --gdy-primary: #0ea5e9;
  --gdy-primary-dark: #0369a1;
  --gdy-accent: #22c55e;
  --gdy-danger: #ef4444;
  --gdy-warning: #eab308;
}
.admin-content {
  background: radial-gradient(circle at top left, #020617 0%, #020617 45%, #020617 100%);
  min-height: 100vh;
  color: #e5e7eb;
}

/* الهيدر */
.gdy-page-header {
  padding: 1rem 1.25rem;
  margin-bottom: 1.25rem;
  border-radius: 1rem;
  background: linear-gradient(135deg, #0f172a 0%, #020617 60%, #0b1120 100%);
  border: 1px solid rgba(148,163,184,0.35);
  box-shadow: 0 14px 30px rgba(15,23,42,0.9);
  color: #e5e7eb;
}
.gdy-page-header h1 {
  font-weight: 700;
  margin-bottom: .35rem;
}
.gdy-page-header p {
  font-size: .9rem;
  margin: 0;
}

/* كارت معلومات الصفحة */
.gdy-page-card {
  border-radius: 1rem;
  border: 1px solid rgba(148,163,184,0.4);
  background: radial-gradient(circle at top left, rgba(15,23,42,0.98), rgba(15,23,42,0.99));
  box-shadow: 0 20px 45px rgba(15,23,42,0.96);
  overflow: hidden;
}
.gdy-page-card-header {
  padding: .75rem 1rem;
  border-bottom: 1px solid rgba(55,65,81,0.9);
  background: linear-gradient(135deg, #020617, #0b1120);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: .75rem;
}

/* شريط الميتا */
.gdy-page-meta-strip {
  display: flex;
  flex-wrap: wrap;
  gap: .5rem;
  padding: .6rem 1rem;
  border-bottom: 1px solid rgba(55,65,81,0.7);
  background: rgba(15,23,42,0.98);
}
.gdy-page-meta-item {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  padding: .25rem .6rem;
  border-radius: 999px;
  font-size: .75rem;
  color: #cbd5f5;
  background: rgba(15,23,42,0.96);
  border: 1px solid rgba(148,163,184,0.45);
}
.gdy-page-meta-item i {
  font-size: .8rem;
}

/* شارة الحالة */
.gdy-status-pill {
  font-size: .78rem;
  padding: .2rem .55rem;
  border-radius: 999px;
  border-width: 1px;
  border-style: solid;
  display: inline-flex;
  align-items: center;
  gap: .3rem;
}
.gdy-status-pill.published {
  background: rgba(22,163,74,0.15);
  color: #4ade80;
  border-color: rgba(34,197,94,0.6);
}
.gdy-status-pill.draft {
  background: rgba(148,163,184,0.12);
  color: #e5e7eb;
  border-color: rgba(148,163,184,0.5);
}

/* حقول الإدخال */
.gdy-label-small {
  font-size: .86rem;
}
.gdy-counter-badge {
  display: inline-block;
  margin-right: .5rem;
  font-size: .72rem;
  padding: .15rem .45rem;
  border-radius: .75rem;
  background: rgba(15,23,42,0.9);
  border: 1px solid rgba(148,163,184,0.5);
  color: #9ca3af;
}

/* محرر المحتوى */
textarea#contentInput {
  min-height: 260px;
  resize: vertical;
}

/* منع التمرير الأفقي + حاوية موحدة للمحتوى */
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

/* استجابة */
@media (max-width: 767.98px) {
  .gdy-page-header {
    padding: .8rem .9rem;
  }
}
</style>

<div class="admin-content container-fluid py-4">
  <div class="gdy-layout-wrap">
    <div class="gdy-page-header d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <div>
        <h1 class="h4 text-white mb-1"><?= h(__('t_ac4518563a', 'تعديل صفحة')) ?></h1>
        <p class="text-muted mb-0"><?= h(__('t_b9aec28ca6', 'قم بتحديث بيانات الصفحة الثابتة.')) ?></p>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a href="<?= h($previewUrl) ?>" target="_blank" class="btn btn-sm btn-outline-info">
          <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_d051fa8276', 'معاينة الصفحة')) ?>
        </a>
        <a href="index.php" class="btn btn-sm btn-secondary">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_fed95e1016', 'عودة للقائمة')) ?>
        </a>
      </div>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach ($errors as $err): ?>
            <li><?= h($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="card gdy-page-card">
      <div class="gdy-page-card-header">
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-primary-subtle text-light border border-primary">
            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> صفحة #<?= (int)$id ?>
          </span>
        </div>
        <div>
          <?php if ($status === 'published'): ?>
            <span class="gdy-status-pill published">
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_c67d973434', 'منشورة')) ?>
            </span>
          <?php else: ?>
            <span class="gdy-status-pill draft">
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_9071af8f2d', 'مسودة')) ?>
            </span>
          <?php endif; ?>
        </div>
      </div>

      <div class="gdy-page-meta-strip">
        <div class="gdy-page-meta-item">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          <span><strong><?= h(__('t_3276131673', 'إنشاء:')) ?></strong> <?= h($created_at ?: __('t_5883c3555c', 'غير متوفر')) ?></span>
        </div>
        <div class="gdy-page-meta-item">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          <span><strong><?= h(__('t_5385d5784c', 'آخر تعديل:')) ?></strong> <?= h($updated_at ?: __('t_5883c3555c', 'غير متوفر')) ?></span>
        </div>
        <div class="gdy-page-meta-item">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          <span class="text-truncate" style="max-width:260px;">
            <strong>Slug:</strong> <code class="small"><?= h($slug) ?></code>
          </span>
        </div>
      </div>

      <div class="card-body">
        <form method="post" id="pageEditForm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label text-white gdy-label-small">
                <?= h(__('t_3463295a54', 'عنوان الصفحة')) ?>
                <span class="text-danger">*</span>
                <span class="gdy-counter-badge" id="titleCounter"><?= h(__('t_02ad3bab33', '0 حرف')) ?></span>
              </label>
              <input
                type="text"
                name="title"
                id="titleInput"
                class="form-control form-control-sm bg-dark text-light border-secondary"
                required
                value="<?= h($title) ?>"
                placeholder="<?= h(__('t_f71c026e6a', 'مثال: من نحن، اتصل بنا، سياسة الخصوصية...')) ?>"
              >
            </div>

            <div class="col-md-4">
              <label class="form-label text-white gdy-label-small">
                <?= h(__('t_1253eb5642', 'الحالة')) ?>
              </label>
              <select name="status" class="form-select form-select-sm bg-dark text-light border-secondary">
                <option value="published" <?= $status === 'published' ? 'selected' : '' ?>><?= h(__('t_c67d973434', 'منشورة')) ?></option>
                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>><?= h(__('t_9071af8f2d', 'مسودة')) ?></option>
              </select>
            </div>

            <div class="col-md-8">
              <label class="form-label text-white gdy-label-small">
                <?= h(__('t_0781965540', 'الرابط (Slug)')) ?>
                <span class="gdy-counter-badge" id="slugCounter"><?= h(__('t_02ad3bab33', '0 حرف')) ?></span>
              </label>
              <div class="input-group input-group-sm">
                <input
                  type="text"
                  name="slug"
                  id="slugInput"
                  class="form-control bg-dark text-light border-secondary"
                  value="<?= h($slug) ?>"
                  placeholder="<?= h(__('t_30b8969c1f', 'مثال: about-us, contact, privacy-policy ...')) ?>"
                >
                <button class="btn btn-outline-secondary" type="button" id="btnGenerateSlug">
                  <?= h(__('t_9ac999098c', 'توليد تلقائي')) ?>
                </button>
              </div>
              <small class="text-muted d-block mt-1">
                <?= h(__('t_4f4c278c33', 'يظهر هذا الجزء في رابط الصفحة، حاول أن يكون قصيراً وواضحاً.')) ?>
              </small>
            </div>

            <div class="col-md-12">
              <label class="form-label text-white gdy-label-small">
                <?= h(__('t_e261adf643', 'محتوى الصفحة')) ?>
              </label>
              <textarea
                name="content"
                id="contentInput"
                class="form-control bg-dark text-light border-secondary"
                rows="10"
              ><?= h($content) ?></textarea>
            </div>
          </div>

          <div class="mt-3 d-flex justify-content-between flex-wrap gap-2">
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
              <?= h(__('t_a7a59a8f5f', 'إلغاء والعودة للقائمة')) ?>
            </a>
            <button type="submit" class="btn btn-primary btn-sm">
              <?= h(__('t_02f31ae27c', 'حفظ التغييرات')) ?>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var titleInput = document.getElementById('titleInput');
  var slugInput  = document.getElementById('slugInput');
  var titleCounter = document.getElementById('titleCounter');
  var slugCounter  = document.getElementById('slugCounter');
  var btnGenerateSlug = document.getElementById('btnGenerateSlug');

  function updateCounter(input, counterEl) {
    if (!input || !counterEl) return;
    var len = input.value.length;
    counterEl.textContent = len + ' حرف';
  }

  if (titleInput && titleCounter) {
    titleInput.addEventListener('input', function () {
      updateCounter(titleInput, titleCounter);
    });
    updateCounter(titleInput, titleCounter);
  }

  if (slugInput && slugCounter) {
    slugInput.addEventListener('input', function () {
      updateCounter(slugInput, slugCounter);
    });
    updateCounter(slugInput, slugCounter);
  }

  function jsSlugify(str) {
    str = (str || '').trim().toLowerCase();
    str = str.replace(/[^\u0600-\u06FFa-z0-9]+/g, '-');
    str = str.replace(/-+/g, '-');
    str = str.replace(/^-|-$/g, '');
    return str || 'page-<?= (int)$id ?>';
  }

  if (btnGenerateSlug && titleInput && slugInput) {
    btnGenerateSlug.addEventListener('click', function () {
      var base = slugInput.value.trim() || titleInput.value.trim();
      if (!base) return;
      slugInput.value = jsSlugify(base);
      updateCounter(slugInput, slugCounter);
    });

    titleInput.addEventListener('blur', function () {
      if (!slugInput.value.trim()) {
        slugInput.value = jsSlugify(titleInput.value);
        updateCounter(slugInput, slugCounter);
      }
    });
  }
});
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
