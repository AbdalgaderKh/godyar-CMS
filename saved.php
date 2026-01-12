<?php
declare(strict_types=1);

// صفحة محفوظاتي (للجوال + سطح المكتب)
// - بدون تسجيل: تحفظ محلياً على الجهاز عبر localStorage
// - مع تسجيل: تحفظ في قاعدة البيانات (user_bookmarks) + مزامنة من المحفوظات المحلية

require_once __DIR__ . '/includes/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$pdo = gdy_pdo_safe();

// تحميل إعدادات الواجهة (تُستخدم داخل الهيدر/الفوتر الجديد)
$settings        = $pdo ? gdy_load_settings($pdo) : [];
$frontendOptions = is_array($settings) ? gdy_prepare_frontend_options($settings) : [];
extract($frontendOptions, EXTR_SKIP);

// baseUrl
$baseUrl = function_exists('base_url') ? rtrim((string)base_url(), '/') : '';
$siteName = $siteName ?? 'Godyar';

$currentUser = $_SESSION['user'] ?? null;
$userId = (int)($currentUser['id'] ?? 0);

$bookmarkedNews = [];
$tableMissing = false;

if ($pdo instanceof PDO && $userId > 0) {
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'user_bookmarks'");
        if (!$check || !$check->fetchColumn()) {
            $tableMissing = true;
        } else {
            $stmt = $pdo->prepare("
                SELECT n.id, n.title, n.slug, n.published_at, n.image
                FROM user_bookmarks b
                INNER JOIN news n ON n.id = b.news_id
                WHERE b.user_id = :uid
                ORDER BY b.created_at DESC
                LIMIT 100
            ");
            $stmt->execute([':uid' => $userId]);
            $bookmarkedNews = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (Throwable $e) {
        @error_log('[Godyar Saved] ' . $e->getMessage());
    }
}

$pageTitle       = 'محفوظاتي - ' . ($siteName ?? 'Godyar');
$pageDescription = 'المقالات والأخبار التي قمت بحفظها للقراءة لاحقاً.';

require __DIR__ . '/frontend/views/partials/header.php';
?>

<div class="my-5">
  <h1 class="h3 mb-2">محفوظاتي</h1>
  <p class="text-muted small mb-4">
    يمكنك حفظ الأخبار للقراءة لاحقاً. بدون تسجيل يتم الحفظ على جهازك، ومع تسجيل يتم حفظها في حسابك وتظهر هنا على كل أجهزتك.
  </p>

  <?php if ($userId > 0): ?>
    <div class="d-flex flex-wrap gap-2 mb-4">
      <a class="btn btn-outline-secondary btn-sm" href="<?= h($baseUrl) ?>/profile">حسابي</a>
      <button type="button" class="btn btn-primary btn-sm" id="gdySyncBookmarks">مزامنة محفوظات الجهاز</button>
    </div>

    <?php if ($tableMissing): ?>
      <div class="alert alert-warning">
        جدول <code>user_bookmarks</code> غير موجود. شغّل ملف الترحيل: <code>database/migrations/2025_11_21_0001_create_user_bookmarks.sql</code>
      </div>
    <?php endif; ?>

    <h2 class="h5 mb-3">المحفوظات في حسابك</h2>
    <?php if (empty($bookmarkedNews)): ?>
      <p class="text-muted">لا توجد محفوظات في حسابك حتى الآن.</p>
    <?php else: ?>
      <div class="row g-3" id="gdyServerBookmarks">
        <?php foreach ($bookmarkedNews as $item): ?>
          <div class="col-12 col-md-6 col-lg-4">
            <div class="card h-100">
              <?php if (!empty($item['image'])): ?>
                <a href="<?= h($baseUrl . '/news/id/' . (int)($item['id'] ?? 0)) ?>">
                  <img src="<?= h($item['image']) ?>" class="card-img-top" alt="<?= h($item['title'] ?? '') ?>" onerror="this.style.display='none'">
                </a>
              <?php endif; ?>
              <div class="card-body">
                <h3 class="h6 card-title">
                  <a href="<?= h($baseUrl . '/news/id/' . (int)($item['id'] ?? 0)) ?>" class="text-decoration-none"><?= h($item['title'] ?? '') ?></a>
                </h3>
                <?php if (!empty($item['published_at'])): ?>
                  <div class="text-muted small mb-1"><?= h($item['published_at']) ?></div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <hr class="my-4">

    <h2 class="h5 mb-3">محفوظات هذا الجهاز (حتى بدون تسجيل)</h2>
    <div id="gdyLocalBookmarksWrap">
      <p class="text-muted" id="gdyLocalBookmarksEmpty">لا توجد محفوظات على هذا الجهاز.</p>
      <div class="row g-3" id="gdyLocalBookmarks"></div>
    </div>

  <?php else: ?>

    <div class="alert alert-info d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div>لمزامنة محفوظاتك على كل الأجهزة، قم بتسجيل الدخول.</div>
      <a class="btn btn-primary btn-sm" href="<?= h($baseUrl) ?>/login">تسجيل الدخول</a>
    </div>

    <h2 class="h5 mb-3">محفوظات هذا الجهاز</h2>
    <div id="gdyLocalBookmarksWrap">
      <p class="text-muted" id="gdyLocalBookmarksEmpty">لا توجد محفوظات على هذا الجهاز.</p>
      <div class="row g-3" id="gdyLocalBookmarks"></div>
    </div>

  <?php endif; ?>
</div>

<?php require __DIR__ . '/frontend/views/partials/footer.php'; ?>
