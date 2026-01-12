<?php
declare(strict_types=1);

/**
 * /categories_list.php
 * صفحة "الفئات" (قائمة جميع الأقسام) — محسّنة للجوال و PWA
 *
 * يتم تضمينها عبر app.php route: /categories
 */

require_once __DIR__ . '/includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();

// تحميل إعدادات الواجهة (تُستخدم داخل الهيدر/الفوتر)
$settings        = $pdo ? gdy_load_settings($pdo) : [];
$frontendOptions = is_array($settings) ? gdy_prepare_frontend_options($settings) : [];
extract($frontendOptions, EXTR_SKIP);

// baseUrl
$baseUrl = function_exists('base_url') ? rtrim((string)base_url(), '/') : '';
$siteName = $siteName ?? 'Godyar';

// تحديد لغة الواجهة
$lang = function_exists('gdy_lang') ? (string)gdy_lang() : (isset($_GET['lang']) ? (string)$_GET['lang'] : 'ar');

// بناء navBaseUrl (للروابط داخل الموقع مع بادئة اللغة)
$rootUrl = function_exists('base_url') ? rtrim((string)base_url(), '/') : '';
$navBaseUrl = ($rootUrl !== '' ? $rootUrl : '') . '/' . trim($lang, '/');
if ($rootUrl === '') {
    $navBaseUrl = '/' . trim($lang, '/');
}

// تحميل الأقسام من قاعدة البيانات (مع توافق أسماء الأعمدة)
$categories = [];
if ($pdo instanceof PDO) {
    try {
        $cols = [];
        try {
            $stmtCols = $pdo->query("SHOW COLUMNS FROM categories");
            $cols = $stmtCols ? ($stmtCols->fetchAll(PDO::FETCH_COLUMN, 0) ?: []) : [];
        } catch (Throwable $e) {
            $cols = [];
        }

        $nameCol = 'name';
        if (!in_array('name', $cols, true)) {
            if (in_array('category_name', $cols, true)) $nameCol = 'category_name';
            elseif (in_array('cat_name', $cols, true)) $nameCol = 'cat_name';
        }

        $slugCol = 'slug';
        if (!in_array('slug', $cols, true)) {
            if (in_array('category_slug', $cols, true)) $slugCol = 'category_slug';
            elseif (in_array('cat_slug', $cols, true)) $slugCol = 'cat_slug';
        }

        $parentCol = in_array('parent_id', $cols, true) ? 'parent_id' : (in_array('parent', $cols, true) ? 'parent' : '');

        $sql = "SELECT id, " . ($parentCol ? ($parentCol . " AS parent_id") : "0 AS parent_id") . ", "
            . $nameCol . " AS name, " . ($slugCol ? ($slugCol . " AS slug") : "'' AS slug") . "
            FROM categories
            ORDER BY " . (in_array('sort_order', $cols, true) ? 'sort_order' : 'id') . " ASC";

        $st = $pdo->query($sql);
        $categories = $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    } catch (Throwable $e) {
        $categories = [];
    }
}

// Build tree
$byId = [];
foreach ($categories as $c) {
    $id = (int)($c['id'] ?? 0);
    if ($id <= 0) continue;
    $byId[$id] = [
        'id' => $id,
        'parent_id' => (int)($c['parent_id'] ?? 0),
        'name' => trim((string)($c['name'] ?? '')),
        'slug' => trim((string)($c['slug'] ?? '')),
        'children' => [],
    ];
}
foreach ($byId as $id => $c) {
    $pid = (int)$c['parent_id'];
    if ($pid > 0 && isset($byId[$pid])) {
        $byId[$pid]['children'][] = $c;
    }
}
$tree = array_values(array_filter($byId, fn($c) => (int)$c['parent_id'] === 0));

$pageTitle = __('categories') . ' - ' . ($siteName ?? 'Godyar');
$pageDescription = __('site_sections');
$searchPlaceholder = __('search_placeholder');

if (class_exists('HomeController')) {
    try { $siteSettings = HomeController::getSiteSettings(); } catch (Throwable $e) { $siteSettings = []; }
}
$isLoggedIn = !empty($_SESSION['user']['id']);

require __DIR__ . '/frontend/views/partials/header.php';
?>
<main class="container" style="padding:16px 0;">
  <div class="page-head" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <h1 style="margin:0; font-size: clamp(20px, 4vw, 28px);"><?= h(__('categories')) ?></h1>
    <a class="btn btn-outline" href="<?= h($navBaseUrl) ?>" style="border-radius: 14px; padding: 10px 14px; text-decoration:none;">
      <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#home"></use></svg> <?= h(__('home')) ?>
    </a>
  </div>

  <div style="margin-top:14px;">
    <input id="gdyCatsFilter" type="search" placeholder="<?= h(__('search_placeholder')) ?>" style="width:100%;padding:12px 14px;border:1px solid rgba(0,0,0,.12);border-radius:14px;">
  </div>

  <div id="gdyCatsGrid" style="margin-top:16px; display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px;">
    <?php if (empty($tree)): ?>
      <div class="alert alert-warning" style="border-radius: 14px; padding: 12px;">
        <?= h(__('لا توجد فئات حالياً.')) ?>
      </div>
    <?php else: ?>
      <?php foreach ($tree as $cat): ?>
        <?php
          $slug = $cat['slug'] ?: (string)$cat['id'];
          $url = rtrim($navBaseUrl, '/') . '/category/' . rawurlencode($slug);
        ?>
        <section class="gdy-catcard" data-name="<?= h(mb_strtolower($cat['name'])) ?>" style="border:1px solid rgba(0,0,0,.10); border-radius: 18px; padding: 14px; background: rgba(255,255,255,.9);">
          <a href="<?= h($url) ?>" style="text-decoration:none;color:inherit;display:flex;align-items:center;justify-content:space-between;gap:10px;">
            <strong style="font-size: 16px;"><?= h($cat['name']) ?></strong>
            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          </a>

          <?php if (!empty($cat['children'])): ?>
            <div style="margin-top:10px; display:flex; flex-wrap:wrap; gap:8px;">
              <?php foreach ($cat['children'] as $ch): ?>
                <?php
                  $chSlug = $ch['slug'] ?: (string)$ch['id'];
                  $chUrl = rtrim($navBaseUrl, '/') . '/category/' . rawurlencode($chSlug);
                ?>
                <a href="<?= h($chUrl) ?>" class="badge" style="text-decoration:none; padding: 8px 10px; border-radius: 999px; background: rgba(0,0,0,.06);">
                  <?= h($ch['name']) ?>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</main>

<script>
(function(){
  var input = document.getElementById('gdyCatsFilter');
  var grid  = document.getElementById('gdyCatsGrid');
  if(!input || !grid) return;
  function norm(s){ return (s||'').toString().trim().toLowerCase(); }
  input.addEventListener('input', function(){
    var q = norm(input.value);
    var cards = grid.querySelectorAll('.gdy-catcard');
    cards.forEach(function(card){
      var name = norm(card.getAttribute('data-name'));
      card.style.display = (!q || name.indexOf(q) !== -1) ? '' : 'none';
    });
  });
})();
</script>

<?php require __DIR__ . '/frontend/views/partials/footer.php'; ?>
