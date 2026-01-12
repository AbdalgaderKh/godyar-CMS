<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$user = $_SESSION['user'] ?? null;
if (!$user || empty($user['id'])) {
    // توجيه لصفحة تسجيل الدخول الأمامية
    $lang = function_exists('gdy_lang') ? (string)gdy_lang() : (isset($_GET['lang']) ? (string)$_GET['lang'] : 'ar');
    $rootUrl = function_exists('base_url') ? rtrim((string)base_url(), '/') : '';
    $navBaseUrl = ($rootUrl !== '' ? $rootUrl : '') . '/' . trim($lang, '/');
    if ($rootUrl === '') { $navBaseUrl = '/' . trim($lang, '/'); }
    $next = '/my';
    header('Location: ' . rtrim($navBaseUrl, '/') . '/login?next=' . rawurlencode($next));
    exit;
}

$pdo = gdy_pdo_safe();

// تحميل إعدادات الواجهة (تُستخدم داخل الهيدر/الفوتر الجديد)
$settings        = gdy_load_settings($pdo);
$frontendOptions = gdy_prepare_frontend_options($settings);
extract($frontendOptions, EXTR_OVERWRITE);

// تأكيد وجود baseUrl
if (!isset($baseUrl)) {
    $baseUrl = function_exists('base_url') ? rtrim(base_url(), '/') : '/godyar';
}

$bookmarkedNews = [];

if ($pdo instanceof PDO) {
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'user_bookmarks'");
        if ($check && $check->fetchColumn()) {
            $stmt = $pdo->prepare("
                SELECT n.id, n.title, n.slug, n.published_at, n.image
                FROM user_bookmarks b
                INNER JOIN news n ON n.id = b.news_id
                WHERE b.user_id = :uid
                ORDER BY b.created_at DESC
                LIMIT 30
            ");
            $stmt->execute([':uid' => (int)$user['id']]);
            $bookmarkedNews = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (Throwable $e) {
        @error_log('[Godyar My] ' . $e->getMessage());
    }
}

$pageTitle       = 'لك - ' . ($siteName ?? 'Godyar');
$pageDescription = 'أخبار محفوظة ومقترحة بناءً على ما تفضّله.';

// الهيدر الجديد
require __DIR__ . '/frontend/views/partials/header.php';
?>
<div class="my-5">
  <h1 class="h3 mb-3">لك</h1>
  <p class="text-muted small mb-4">
    هذه الصفحة تعرض الأخبار التي قمت بحفظها في المفضّلة. يمكن تطويرها لاحقاً لتشمل توصيات ذكية.
  </p>

  <?php if (empty($bookmarkedNews)): ?>
    <p class="text-muted">لم تحفظ أي خبر حتى الآن.</p>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($bookmarkedNews as $item): ?>
        <div class="col-12 col-md-6 col-lg-4">
          <div class="card h-100">
            <?php if (!empty($item['image'])): ?>
              <a href="<?= htmlspecialchars($baseUrl . '/news/id/' . (int)($item['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">
                <img
                  src="<?= htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8') ?>"
                  class="card-img-top"
                  alt="<?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>"
                  onerror="this.style.display='none'">
              </a>
            <?php endif; ?>
            <div class="card-body">
              <h2 class="h6 card-title">
                <a
                  href="<?= htmlspecialchars($baseUrl . '/news/id/' . (int)($item['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>"
                  class="text-decoration-none">
                  <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                </a>
              </h2>
              <?php if (!empty($item['published_at'])): ?>
                <div class="text-muted small mb-1">
                  <?= htmlspecialchars($item['published_at'], ENT_QUOTES, 'UTF-8') ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php
// الفوتر الجديد
require __DIR__ . '/frontend/views/partials/footer.php';
