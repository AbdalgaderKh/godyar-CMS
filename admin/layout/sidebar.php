<?php
// admin/layout/sidebar.php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/lang.php';

if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// قاعدة الروابط
$siteBase  = function_exists('base_url') ? rtrim(base_url(), '/') : '';
$adminBase = $siteBase . '/admin';

// الصفحة الحالية (تُمرَّر من الصفحات)
$currentPage = $currentPage ?? 'dashboard';

// إحصاءات مبسطة (يمكن تمريرها من الصفحات)
$quickStats = $quickStats ?? [
    'posts'    => 0,
    'users'    => 0,
    'comments' => 0,
];

// بيانات المستخدم من الجلسة
$userName   = $_SESSION['user']['name'] ?? ($_SESSION['user']['display_name'] ?? ($_SESSION['user']['email'] ?? 'مشرف النظام'));
$userRole   = $_SESSION['user']['role'] ?? 'admin';
$userAvatar = $_SESSION['user']['avatar'] ?? null;

// تحميل Auth عند الحاجة
if (!class_exists(\Godyar\Auth::class)) {
    @require_once __DIR__ . '/../../includes/auth.php';
}

$isWriter = class_exists(\Godyar\Auth::class) && \Godyar\Auth::isWriter();

// -------------------------
// RBAC helpers (permissions)
// -------------------------
$pdo = gdy_pdo_safe();

// Unread notifications count (best effort)
$__notifUnread = 0;
if ($pdo instanceof \PDO) {
    try {
        $uid = (int)($_SESSION['user']['id'] ?? 0);
        $chk = $pdo->query("SHOW TABLES LIKE 'admin_notifications'");
        $has = $chk && $chk->fetchColumn();
        if ($has) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_notifications WHERE is_read=0 AND (user_id IS NULL OR user_id=:uid)");
            $stmt->execute([':uid' => $uid]);
            $__notifUnread = (int)($stmt->fetchColumn() ?? 0);
        }
    } catch (\Throwable $e) {
        $__notifUnread = 0;
    }
}


/**
 * هل يملك المستخدم صلاحية محددة؟
 * - إن كانت الصلاحية فارغة => متاح
 * - إن لم توجد Auth => نستخدم منطق الكاتب فقط
 */
$can = function (?string $perm) use ($isWriter): bool {
    $perm = $perm ? trim((string)$perm) : '';
    if ($perm === '') return true;

    if (class_exists(\Godyar\Auth::class) && method_exists(\Godyar\Auth::class, 'hasPermission')) {
        try {
            return \Godyar\Auth::hasPermission($perm);
        } catch (\Throwable $e) {
            return false;
        }
    }

    // fallback: الكاتب ممنوع من أي شيء غير الأخبار
    return !$isWriter;
};

/**
 * قائمة ديناميكية من قاعدة البيانات (اختياري):
 * جدول مقترح: admin_menu
 * (id, section, label, sub_label, href, icon, perm, sort_order, is_active)
 */
$dbMenu = [];
if ($pdo instanceof \PDO) {
    try {
        $chk = $pdo->query("SHOW TABLES LIKE 'admin_menu'");
        $has = $chk && $chk->fetchColumn();
        if ($has) {
            $stmt = $pdo->query("SELECT section,label,sub_label,href,icon,perm,sort_order,is_active
                                 FROM admin_menu
                                 WHERE is_active=1
                                 ORDER BY section ASC, sort_order ASC, id ASC");
            $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
            foreach ($rows as $r) {
                $sec = (string)($r['section'] ?? 'عام');
                $dbMenu[$sec][] = [
                    'label' => (string)($r['label'] ?? ''),
                    'sub'   => (string)($r['sub_label'] ?? ''),
                    'href'  => (string)($r['href'] ?? '#'),
                    'icon'  => (string)($r['icon'] ?? 'circle'),
                    'perm'  => (string)($r['perm'] ?? ''),
                ];
            }
        }
    } catch (\Throwable $e) {
        $dbMenu = [];
    }
}

// ملاحظة توافق:
// بعض النسخ تحتوي جدول admin_menu غير مكتمل (قد ينتج سلايدبار برابط/روابط قليلة فقط).
// في هذه الحالة نعود للقائمة الثابتة الافتراضية حتى لا تختفي الروابط الأساسية.
$dbMenuItemCount = 0;
foreach ($dbMenu as $sec => $items) {
    $dbMenuItemCount += is_array($items) ? count($items) : 0;
}
if ($dbMenuItemCount < 5) {
    $dbMenu = [];
}


// عناصر القائمة (إزالة الروابط المكررة: إضافة خبر جديد/إدارة الأقسام/رفع وسائط/الإضافات)
// - "إضافة خبر جديد": نترك "الأخبار" حيث توجد إضافة داخلها
// - "إدارة الأقسام": نكتفي بـ "التصنيفات" و/أو "الأقسام" (بدون تكرار)
// - "رفع وسائط": نكتفي بـ "مكتبة الوسائط" (تحتوي رفع)
// - "الإضافات": نترك رابط واحد فقط


// ملاحظة مهمة:
// بعض العملاء لديهم جدول admin_menu غير مكتمل (مثلاً عنصر واحد فقط)،
// مما يؤدي لاختفاء روابط السايدبار بالكامل.
// في هذه الحالة نستخدم قائمة السايدبار الافتراضية (الثابتة) الموجودة أسفل الملف.
$menuCount = 0;
foreach ($dbMenu as $sec => $items) {
    $menuCount += is_array($items) ? count($items) : 0;
}
if ($menuCount < 5) {
    $dbMenu = [];
}

?>


<aside class="admin-sidebar" id="adminSidebar" role="navigation" aria-label="<?= h(__("admin_sidebar")) ?>">
  <div class="admin-sidebar__card">

    <header class="admin-sidebar__header">
      <div class="admin-sidebar__brand">
        <div class="admin-sidebar__logo" aria-hidden="true">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg>
        </div>
        <div class="admin-sidebar__brand-text">
          <div class="admin-sidebar__title">Godyar News</div>
          <div class="admin-sidebar__subtitle"><?= h(__("admin_panel")) ?></div>
        </div>
      </div>
      <button class="admin-sidebar__toggle" type="button" id="sidebarToggle" aria-label="<?= h(__("toggle_sidebar")) ?>">
        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#menu"></use></svg>
      </button>
    </header>

    <div class="admin-sidebar__search-wrapper">
      <div class="admin-sidebar__search">
        <input id="sidebarSearch" class="admin-sidebar__search-input" type="search" placeholder="<?= h(__("search_menus")) ?>" autocomplete="off" />
        <svg class="gdy-icon admin-sidebar__search-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#search"></use></svg>
        <div id="sidebarSearchResults" class="admin-sidebar__search-results" role="listbox" aria-label="<?= h(__("search_results")) ?>"></div>
      </div>
    </div>

    <?php if (!$isWriter): ?>
      <div class="admin-sidebar__quick" aria-label="<?= h(__("quick_stats")) ?>">
        <div class="admin-sidebar__quick-item" title="<?= h(__("comments")) ?>">
          <div class="admin-sidebar__quick-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></div>
          <div class="admin-sidebar__quick-value"><?= (int)($quickStats['comments'] ?? 0) ?></div>
        </div>
        <div class="admin-sidebar__quick-item" title="<?= h(__("users")) ?>">
          <div class="admin-sidebar__quick-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></div>
          <div class="admin-sidebar__quick-value"><?= (int)($quickStats['users'] ?? 0) ?></div>
        </div>
        <div class="admin-sidebar__quick-item" title="<?= h(__("news")) ?>">
          <div class="admin-sidebar__quick-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg></div>
          <div class="admin-sidebar__quick-value"><?= (int)($quickStats['posts'] ?? 0) ?></div>
        </div>
      </div>
    <?php endif; ?>

    <nav class="admin-sidebar__nav" role="list">
<?php if (!empty($dbMenu)): ?>
  <?php foreach ($dbMenu as $secLabel => $items): ?>
    <div class="admin-sidebar__section" aria-label="<?= h($secLabel) ?>">
      <div class="admin-sidebar__section-title"><?= h($secLabel) ?></div>
      <?php foreach ($items as $it): ?>
        <?php
          $perm = $it['perm'] ?? '';
          if (!$can($perm)) continue;
          $href = $it['href'] ?? '#';
          // دعم روابط نسبية داخل /admin/ إذا لم تبدأ بشرطة
          if ($href !== '' && $href[0] !== '/' && strpos($href, 'http') !== 0) {
              $href = $adminBase . '/' . ltrim($href, '/');
          }
          $icon = $it['icon'] ?? 'circle';
          $label = $it['label'] ?? '';
          $sub   = $it['sub'] ?? '';
        ?>
        <div class="admin-sidebar__link-card" data-search="<?= h($label) ?>">
          <a class="admin-sidebar__link" href="<?= h($href) ?>">
            <div class="admin-sidebar__link-main">
              <div class="admin-sidebar__link-icon"><svg class="gdy-icon h($icon) ?>" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></div>
              <div class="admin-sidebar__link-text">
                <div class="admin-sidebar__link-label"><?= h($label) ?></div>
                <?php if ($sub !== ''): ?><div class="admin-sidebar__link-sub"><?= h($sub) ?></div><?php endif; ?>
              </div>
            </div>
            <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
<?php else: ?>


      <div class="admin-sidebar__section" aria-label="<?= h(__('نظرة عامة')) ?>">
        <div class="admin-sidebar__section-title"><?= h(__('نظرة عامة')) ?></div>

        <div class="admin-sidebar__link-card <?= $currentPage === 'dashboard' ? 'is-active' : '' ?>" data-search="الرئيسية لوحة التحكم dashboard">
          <a class="admin-sidebar__link" href="<?= h($adminBase) ?>/index.php">
            <div class="admin-sidebar__link-main">
              <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#home"></use></svg></div>
              <div class="admin-sidebar__link-text">
                <div class="admin-sidebar__link-label"><?= h(__('الرئيسية')) ?></div>
                <div class="admin-sidebar__link-sub"><?= h(__('نظرة عامة على أداء النظام')) ?></div>
              </div>
            </div>
            <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#home"></use></svg>
          </a>
        </div>

        <?php if (!$isWriter): ?>
          
<div class="admin-sidebar__link-card <?= ($currentPage === 'search') ? 'is-active' : '' ?>" data-search="بحث search global">
  <a class="admin-sidebar__link" href="<?= h($adminBase) ?>/search/index.php">
    <div class="admin-sidebar__link-main">
      <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#search"></use></svg></div>
      <div class="admin-sidebar__link-text">
        <div class="admin-sidebar__link-label"><?= h(__('بحث شامل')) ?></div>
        <div class="admin-sidebar__link-sub"><?= h(__('ابحث داخل اللوحة')) ?></div>
      </div>
    </div>
  </a>
</div>

<div class="admin-sidebar__link-card <?= ($currentPage === 'notifications') ? 'is-active' : '' ?>" data-search="إشعارات notifications">
  <a class="admin-sidebar__link" href="<?= h($adminBase) ?>/notifications/index.php">
    <div class="admin-sidebar__link-main">
      <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></div>
      <div class="admin-sidebar__link-text">
        <div class="admin-sidebar__link-label"><?= h(__('الإشعارات')) ?></div>
        <div class="admin-sidebar__link-sub"><?= h(__('مركز الإشعارات')) ?></div>
      </div>
      <?php if (!empty($__notifUnread)): ?>
        <span class="badge bg-danger rounded-pill" style="margin-inline-start:auto;align-self:center;">
          <?= (int)$__notifUnread ?>
        </span>
      <?php endif; ?>
    </div>
  </a>
</div>

<div class="admin-sidebar__link-card <?= ($currentPage === 'analytics') ? 'is-active' : '' ?>" data-search="تحليلات analytics heatmap">
  <a class="admin-sidebar__link" href="<?= h($adminBase) ?>/analytics/heatmap.php">
    <div class="admin-sidebar__link-main">
      <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></div>
      <div class="admin-sidebar__link-text">
        <div class="admin-sidebar__link-label"><?= h(__('خريطة النشاط')) ?></div>
        <div class="admin-sidebar__link-sub"><?= h(__('اليوم / الساعة')) ?></div>
      </div>
    </div>
  </a>
</div>

<div class="admin-sidebar__link-card" data-search="تصدير export csv excel">
  <a class="admin-sidebar__link" href="<?= h($adminBase) ?>/export.php?entity=news">
    <div class="admin-sidebar__link-main">
      <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></div>
      <div class="admin-sidebar__link-text">
        <div class="admin-sidebar__link-label"><?= h(__('تصدير CSV')) ?></div>
        <div class="admin-sidebar__link-sub"><?= h(__('الأخبار (مثال)')) ?></div>
      </div>
    </div>
  </a>
</div>
<div class="admin-sidebar__link-card <?= $currentPage === 'reports' ? 'is-active' : '' ?>" data-search="التقارير analytics احصائيات">
            <a class="admin-sidebar__link" href="<?= h($adminBase) ?>/reports/index.php">
              <div class="admin-sidebar__link-main">
                <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#chart"></use></svg></div>
                <div class="admin-sidebar__link-text">
                  <div class="admin-sidebar__link-label"><?= h(__('التقارير')) ?></div>
                  <div class="admin-sidebar__link-sub"><?= h(__('لوحة مؤشرات أداء')) ?></div>
                </div>
              </div>
              <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </a>
          </div>
        <?php endif; ?>
      </div>

      <div class="admin-sidebar__section" aria-label="<?= h(__('المحتوى')) ?>">
        <div class="admin-sidebar__section-title"><?= h(__('المحتوى')) ?></div>
        <?php
          // صفحات الأخبار قد تستخدم currentPage = posts في بعض النسخ
          $newsMenuOpen = in_array($currentPage, ['news','posts','posts_review','news_review','feeds'], true);

          // عدّاد الأخبار بانتظار المراجعة (للمدراء فقط)
          $pendingReviewCount = 0;
          if (!$isWriter && ($pdo instanceof \PDO)) {
              try {
                  $hasStatus = false;
                  $chkCol = $pdo->query("SHOW COLUMNS FROM news LIKE 'status'");
                  if ($chkCol && $chkCol->fetch(\PDO::FETCH_ASSOC)) {
                      $hasStatus = true;
                  }
                  if ($hasStatus) {
                      $st = $pdo->query("SELECT COUNT(*) FROM news WHERE status = 'pending'");
                      $pendingReviewCount = $st ? (int)$st->fetchColumn() : 0;
                  }
              } catch (\Throwable $e) {
                  $pendingReviewCount = 0;
              }
          }
        ?>
        <div class="admin-sidebar__link-card <?= $newsMenuOpen ? 'is-active' : '' ?>" data-search="الأخبار المقالات المحتوى news articles rss feeds">
          <button type="button"
                  class="admin-sidebar__link admin-sidebar__link--toggle"
                  data-bs-toggle="collapse"
                  data-bs-target="#gdyNewsMenu"
                  aria-expanded="<?= $newsMenuOpen ? 'true' : 'false' ?>"
                  aria-controls="gdyNewsMenu">
            <div class="admin-sidebar__link-main">
              <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg></div>
              <div class="admin-sidebar__link-text">
                <div class="admin-sidebar__link-label">
                  <?= h(__('الأخبار')) ?>
                  <?php if (!$isWriter && $pendingReviewCount > 0): ?>
                    <span class="badge bg-danger ms-2" title="<?= h(__('بانتظار المراجعة')) ?>">
                      <?= (int)$pendingReviewCount ?>
                    </span>
                  <?php endif; ?>
                </div>
                <div class="admin-sidebar__link-sub"><?= h(__('إدارة الأخبار والمقالات')) ?></div>
              </div>
            </div>
            <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          </button>
        </div>

        <div class="collapse <?= $newsMenuOpen ? 'show' : '' ?>" id="gdyNewsMenu">
          <div class="admin-sidebar__subnav">

            <div class="admin-sidebar__link-card admin-sidebar__link-card--sub <?= in_array($currentPage, ['news','posts'], true) ? 'is-active' : '' ?>" data-search="إدارة الأخبار posts news">
              <a class="admin-sidebar__link" href="<?= h($adminBase) ?>/news/index.php">
                <div class="admin-sidebar__link-main">
                  <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg></div>
                  <div class="admin-sidebar__link-text">
                    <div class="admin-sidebar__link-label"><?= h(__('إدارة الأخبار')) ?></div>
                    <div class="admin-sidebar__link-sub"><?= h(__('قائمة الأخبار والمسودات')) ?></div>
                  </div>
                </div>
                <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              </a>
            </div>



            <?php if ($can('posts.view')): ?>
              <div class="admin-sidebar__link-card admin-sidebar__link-card--sub <?= ($currentPage==='translations') ? 'is-active' : '' ?>" data-search="ترجمة ترجميات translations language">
                <a class="admin-sidebar__link" href="<?= h($adminBase) ?>/news/translations.php">
                  <div class="admin-sidebar__link-main">
                    <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#globe"></use></svg></div>
                    <div class="admin-sidebar__link-text">
                      <div class="admin-sidebar__link-label"><?= h(__('ترجمات الأخبار')) ?></div>
                      <div class="admin-sidebar__link-sub"><?= h(__('إدارة نسخ اللغات للمقالات')) ?></div>
                    </div>
                  </div>
                  <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                </a>
              </div>

              <div class="admin-sidebar__link-card admin-sidebar__link-card--sub <?= ($currentPage==='polls') ? 'is-active' : '' ?>" data-search="استطلاع استطلاعات polls vote">
                <a class="admin-sidebar__link" href="<?= h($adminBase) ?>/news/polls.php">
                  <div class="admin-sidebar__link-main">
                    <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></div>
                    <div class="admin-sidebar__link-text">
                      <div class="admin-sidebar__link-label"><?= h(__('استطلاعات الأخبار')) ?></div>
                      <div class="admin-sidebar__link-sub"><?= h(__('إنشاء وإدارة استطلاعات داخل المقال')) ?></div>
                    </div>
                  </div>
                  <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                </a>
              </div>

              <div class="admin-sidebar__link-card admin-sidebar__link-card--sub <?= ($currentPage==='questions') ? 'is-active' : '' ?>" data-search="اسأل الكاتب أسئلة questions qna">
                <a class="admin-sidebar__link" href="<?= h($adminBase) ?>/news/questions.php">
                  <div class="admin-sidebar__link-main">
                    <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></div>
                    <div class="admin-sidebar__link-text">
                      <div class="admin-sidebar__link-label"><?= h(__('أسئلة القرّاء')) ?></div>
                      <div class="admin-sidebar__link-sub"><?= h(__('مراجعة أسئلة اسأل الكاتب والرد عليها')) ?></div>
                    </div>
                  </div>
                  <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                </a>
              </div>
            <?php endif; ?>

            <?php if (!$isWriter): ?>
              <div class="admin-sidebar__link-card admin-sidebar__link-card--sub <?= in_array($currentPage, ['posts_review','news_review'], true) ? 'is-active' : '' ?>" data-search="مراجعة الأخبار pending review queue">
                <a class="admin-sidebar__link" href="<?= h($adminBase) ?>/news/review.php">
                  <div class="admin-sidebar__link-main">
                    <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#check"></use></svg></div>
                    <div class="admin-sidebar__link-text">
                      <div class="admin-sidebar__link-label">
                        <?= h(__('مراجعة الأخبار')) ?>
                        <?php if ($pendingReviewCount > 0): ?>
                          <span class="badge bg-danger ms-2"><?= (int)$pendingReviewCount ?></span>
                        <?php endif; ?>
                      </div>
                      <div class="admin-sidebar__link-sub"><?= h(__('طابور المراجعة والاعتماد')) ?></div>
                    </div>
                  </div>
                  <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                </a>
              </div>
            <?php endif; ?>

            <?php if (!$isWriter): ?>
              <div class="admin-sidebar__link-card admin-sidebar__link-card--sub <?= $currentPage === 'feeds' ? 'is-active' : '' ?>" data-search="مصادر rss خلاصات feeds import">
                <a class="admin-sidebar__link" href="<?= h($adminBase) ?>/feeds/index.php">
                  <div class="admin-sidebar__link-main">
                    <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></div>
                    <div class="admin-sidebar__link-text">
                      <div class="admin-sidebar__link-label"><?= h(__('مصادر RSS')) ?></div>
                      <div class="admin-sidebar__link-sub"><?= h(__('استيراد أخبار كمَسودّات')) ?></div>
                    </div>
                  </div>
                  <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                </a>
              </div>
            <?php endif; ?>

          </div>
        </div>
<?php if (!$isWriter): ?>
          <div class="admin-sidebar__link-card <?= $currentPage === 'categories' ? 'is-active' : '' ?>" data-search="التصنيفات الأقسام categories sections">
            <a class="admin-sidebar__link" href="<?= h($adminBase) ?>/categories/index.php">
              <div class="admin-sidebar__link-main">
                <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#category"></use></svg></div>
                <div class="admin-sidebar__link-text">
                  <div class="admin-sidebar__link-label"><?= h(__('التصنيفات')) ?></div>
                  <div class="admin-sidebar__link-sub"><?= h(__('إدارة التصنيفات والأقسام')) ?></div>
                </div>
              </div>
              <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </a>
          </div>

          <div class="admin-sidebar__link-card <?= $currentPage === 'tags' ? 'is-active' : '' ?>" data-search="الوسوم tags">
            <a class="admin-sidebar__link" href="<?= h($adminBase) ?>/tags/index.php">
              <div class="admin-sidebar__link-main">
                <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#tag"></use></svg></div>
                <div class="admin-sidebar__link-text">
                  <div class="admin-sidebar__link-label"><?= h(__('الوسوم')) ?></div>
                  <div class="admin-sidebar__link-sub"><?= h(__('إدارة وسوم الأخبار')) ?></div>
                </div>
              </div>
              <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </a>
          </div>

          <div class="admin-sidebar__link-card <?= $currentPage === 'media' ? 'is-active' : '' ?>" data-search="مكتبة الوسائط media رفع صور">
            <a class="admin-sidebar__link" href="<?= h($adminBase) ?>/media/index.php">
              <div class="admin-sidebar__link-main">
                <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#image"></use></svg></div>
                <div class="admin-sidebar__link-text">
                  <div class="admin-sidebar__link-label"><?= h(__('مكتبة الوسائط')) ?></div>
                  <div class="admin-sidebar__link-sub"><?= h(__('رفع وإدارة الصور والملفات')) ?></div>
                </div>
              </div>
              <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </a>
          </div>

          <div class="admin-sidebar__link-card <?= $currentPage === 'videos' ? 'is-active' : '' ?>" data-search="الفيديوهات المميزة فيديو featured videos">
            <a class="admin-sidebar__link" href="<?= h($adminBase) ?>/manage_videos.php">
              <div class="admin-sidebar__link-main">
                <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#video"></use></svg></div>
                <div class="admin-sidebar__link-text">
                  <div class="admin-sidebar__link-label"><?= h(__('الفيديوهات المميزة')) ?></div>
                  <div class="admin-sidebar__link-sub"><?= h(__('إدارة فيديوهات الصفحة الرئيسية')) ?></div>
                </div>
              </div>
              <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </a>
          </div>

          <div class="admin-sidebar__link-card <?= $currentPage === 'comments' ? 'is-active' : '' ?>" data-search="التعليقات comments">
            <a class="admin-sidebar__link" href="<?= h($adminBase) ?>/comments/index.php">
              <div class="admin-sidebar__link-main">
                <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#comment"></use></svg></div>
                <div class="admin-sidebar__link-text">
                  <div class="admin-sidebar__link-label"><?= h(__('التعليقات')) ?></div>
                  <div class="admin-sidebar__link-sub"><?= h(__('مراجعة وإدارة التعليقات')) ?></div>
                </div>
              </div>
              <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </a>
          </div>
        <?php endif; ?>
      </div>

      <?php if (!$isWriter): ?>
      <div class="admin-sidebar__section" aria-label="<?= h(__('الإدارة')) ?>">
        <div class="admin-sidebar__section-title"><?= h(__('الإدارة')) ?></div>

        <?php if ($can('manage_users')): ?>
        <div class="admin-sidebar__link-card <?= $currentPage === 'users' ? 'is-active' : '' ?>" data-search="المستخدمون users">
          <a class="admin-sidebar__link" href="<?= h($adminBase) ?>/users/index.php">
            <div class="admin-sidebar__link-main">
              <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#users"></use></svg></div>
              <div class="admin-sidebar__link-text">
                <div class="admin-sidebar__link-label"><?= h(__('المستخدمون')) ?></div>
                <div class="admin-sidebar__link-sub"><?= h(__('إدارة الحسابات والصلاحيات')) ?></div>
              </div>
            </div>
            <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          </a>
        </div>
        <?php endif; ?>

        

        <?php if ($can('manage_roles')): ?>
        <div class="admin-sidebar__link-card <?= $currentPage === 'roles' ? 'is-active' : '' ?>" data-search="الأدوار roles">
          <a class="admin-sidebar__link" href="<?= h($adminBase) ?>/roles/index.php">
            <div class="admin-sidebar__link-main">
              <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg></div>
              <div class="admin-sidebar__link-text">
                <div class="admin-sidebar__link-label"><?= h(__('الأدوار')) ?></div>
                <div class="admin-sidebar__link-sub"><?= h(__('صلاحيات النظام')) ?></div>
              </div>
            </div>
            <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          </a>
        </div>
        <?php endif; ?>



        
        <?php if ($can('opinion_authors.manage')): ?>
        <div class="admin-sidebar__link-card <?= $currentPage === 'opinion_authors' ? 'is-active' : '' ?>" data-search="كتاب الرأي opinion authors">
          <a class="admin-sidebar__link" href="<?= h($adminBase) ?>/opinion_authors/index.php">
            <div class="admin-sidebar__link-main">
              <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#pen"></use></svg></div>
              <div class="admin-sidebar__link-text">
                <div class="admin-sidebar__link-label"><?= h(__('كتاب الرأي')) ?></div>
                <div class="admin-sidebar__link-sub"><?= h(__('إدارة كتّاب الرأي')) ?></div>
              </div>
            </div>
            <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          </a>
        </div>
        <?php endif; ?>

        <?php if ($can('team.manage')): ?>
        <div class="admin-sidebar__link-card <?= $currentPage === 'team' ? 'is-active' : '' ?>" data-search="فريق العمل team">
          <a class="admin-sidebar__link" href="<?= h($adminBase) ?>/team/index.php">
            <div class="admin-sidebar__link-main">
              <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#team"></use></svg></div>
              <div class="admin-sidebar__link-text">
                <div class="admin-sidebar__link-label"><?= h(__('فريق العمل')) ?></div>
                <div class="admin-sidebar__link-sub"><?= h(__('إدارة صفحة فريق العمل')) ?></div>
              </div>
            </div>
            <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          </a>
        </div>
        <?php endif; ?>

        <?php if ($can('contact.manage')): ?>
        <div class="admin-sidebar__link-card <?= $currentPage === 'contact' ? 'is-active' : '' ?>" data-search="رسائل التواصل contact">
          <a class="admin-sidebar__link" href="<?= h($adminBase) ?>/contact/index.php">
            <div class="admin-sidebar__link-main">
              <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#mail"></use></svg></div>
              <div class="admin-sidebar__link-text">
                <div class="admin-sidebar__link-label"><?= h(__('رسائل التواصل')) ?></div>
                <div class="admin-sidebar__link-sub"><?= h(__('قراءة وإدارة رسائل الموقع')) ?></div>
              </div>
            </div>
            <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          </a>
        </div>
        <?php endif; ?>

        <?php if ($can('ads.manage')): ?>
        <div class="admin-sidebar__link-card <?= $currentPage === 'ads' ? 'is-active' : '' ?>" data-search="الإعلانات ads">
          <a class="admin-sidebar__link" href="<?= h($adminBase) ?>/ads/index.php">
            <div class="admin-sidebar__link-main">
              <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#ads"></use></svg></div>
              <div class="admin-sidebar__link-text">
                <div class="admin-sidebar__link-label"><?= h(__('الإعلانات')) ?></div>
                <div class="admin-sidebar__link-sub"><?= h(__('إدارة أماكن الإعلانات')) ?></div>
              </div>
            </div>
            <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          </a>
        </div>
        <?php endif; ?>

        <?php if ($can('glossary.manage')): ?>
        <div class="admin-sidebar__link-card <?= $currentPage === 'glossary' ? 'is-active' : '' ?>" data-search="القاموس glossary">
          <a class="admin-sidebar__link" href="<?= h($adminBase) ?>/glossary/index.php">
            <div class="admin-sidebar__link-main">
              <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#book"></use></svg></div>
              <div class="admin-sidebar__link-text">
                <div class="admin-sidebar__link-label"><?= h(__('القاموس')) ?></div>
                <div class="admin-sidebar__link-sub"><?= h(__('إدارة المصطلحات')) ?></div>
              </div>
            </div>
            <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          </a>
        </div>
        <?php endif; ?>

<?php if ($can('manage_plugins')): ?>
        <div class="admin-sidebar__link-card <?= $currentPage === 'plugins' ? 'is-active' : '' ?>" data-search="الإضافات plugins">
          <a class="admin-sidebar__link" href="<?= h($adminBase) ?>/plugins/index.php">
            <div class="admin-sidebar__link-main">
              <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#plugin"></use></svg></div>
              <div class="admin-sidebar__link-text">
                <div class="admin-sidebar__link-label"><?= h(__('الإضافات')) ?></div>
                <div class="admin-sidebar__link-sub"><?= h(__('تفعيل/تعطيل مكونات النظام')) ?></div>
              </div>
            </div>
            <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          </a>
        </div>
        <?php endif; ?>



        <div class="admin-sidebar__link-card <?= $currentPage === 'settings' ? 'is-active' : '' ?>" data-search="الإعدادات settings">
          <a class="admin-sidebar__link" href="<?= h($adminBase) ?>/settings/index.php">
            <div class="admin-sidebar__link-main">
              <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#settings"></use></svg></div>
              <div class="admin-sidebar__link-text">
                <div class="admin-sidebar__link-label"><?= h(__('الإعدادات')) ?></div>
                <div class="admin-sidebar__link-sub"><?= h(__('إعدادات الموقع العامة')) ?></div>
              </div>
            </div>
            <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          </a>
        </div>

        <div class="admin-sidebar__link-card <?= $currentPage === 'elections' ? 'is-active' : '' ?>" data-search="الانتخابات elections">
          <a class="admin-sidebar__link" href="<?= h($adminBase) ?>/elections/index.php">
            <div class="admin-sidebar__link-main">
              <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#election"></use></svg></div>
              <div class="admin-sidebar__link-text">
                <div class="admin-sidebar__link-label"><?= h(__('الانتخابات')) ?></div>
                <div class="admin-sidebar__link-sub"><?= h(__('إدارة نظام الانتخابات')) ?></div>
              </div>
            </div>
            <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          </a>
        </div>

      </div>
      <?php endif; ?>

    
<?php endif; ?>
</nav>

    <footer class="admin-sidebar__footer">
      <div class="admin-sidebar__user">
        <div class="admin-sidebar__user-avatar">
          <?php if ($userAvatar): ?>
            <img src="<?= h($userAvatar) ?>" alt="صورة المستخدم" />
          <?php else: ?>
            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg>
          <?php endif; ?>
        </div>
        <div class="admin-sidebar__user-info">
          <div class="admin-sidebar__user-name"><?= h($userName) ?></div>
          <div class="admin-sidebar__user-role"><?= h($userRole) ?></div>
        </div>
      </div>
      <div class="admin-sidebar__footer-actions">
        <a href="<?= h($siteBase) ?>/" class="admin-sidebar__action-btn" title="الموقع الرئيسي" aria-label="الانتقال للموقع الرئيسي" target="_blank" rel="noopener">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#home"></use></svg>
        </a>
        <button class="admin-sidebar__action-btn" id="darkModeToggle" type="button" title="الوضع الليلي" aria-label="تبديل الوضع الليلي">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#moon"></use></svg>
        </button>
        <a href="<?= h($adminBase) ?>/logout.php" class="admin-sidebar__action-btn admin-sidebar__action-btn--danger" title="تسجيل الخروج" aria-label="تسجيل الخروج">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#logout"></use></svg>
        </a>
      </div>
    </footer>

  </div>

    <div class="admin-sidebar__section" aria-label="<?= h(__('language')) ?>">
      <div class="admin-sidebar__section-title"><?= h(__('language')) ?></div>
      <div style="display:flex;gap:8px;padding:10px 6px;flex-wrap:wrap;">
        <a class="admin-sidebar__pill <?= gdy_lang()==='ar' ? 'is-active' : '' ?>" href="<?= h(gdy_lang_url('ar')) ?>">AR</a>
        <a class="admin-sidebar__pill <?= gdy_lang()==='en' ? 'is-active' : '' ?>" href="<?= h(gdy_lang_url('en')) ?>">EN</a>
        <a class="admin-sidebar__pill <?= gdy_lang()==='fr' ? 'is-active' : '' ?>" href="<?= h(gdy_lang_url('fr')) ?>">FR</a>
      </div>
    </div>

</aside>

<style>
:root {
  --gdy-sidebar-bg: radial-gradient(circle at top, #020617 0, #020617 55%);
  --gdy-sidebar-border: rgba(148,163,184,0.18);
  --gdy-sidebar-card-bg: rgba(15, 23, 42, 0.98);
  --gdy-sidebar-text: #e5e7eb;
  --gdy-sidebar-muted: #9ca3af;

  /* Tie sidebar accent to Admin Theme preset (data-admin-theme)
     so the whole admin UI changes consistently. */
  --gdy-sidebar-accent: var(--gdy-accent);
  --gdy-sidebar-accent-soft: color-mix(in srgb, var(--gdy-accent) 55%, #ffffff 45%);
  --gdy-sidebar-danger: var(--gdy-danger);
}

.admin-sidebar {
  position: fixed;
  top: 0;
  bottom: 0;
  width: 260px;
  right: 0;
  left: auto;
  background: var(--gdy-sidebar-bg);
  color: var(--gdy-sidebar-text);
  z-index: 1040;
}


/* LTR support: move sidebar to the left */
html[dir="ltr"] .admin-sidebar{
  left: 0;
  right: auto;
}

/* Mobile: off-canvas sidebar to avoid overlapping content */
@media (max-width: 991.98px){
  .admin-sidebar{
    transition: transform .18s ease;
    transform: translateX(100%);
  }
  html[dir="ltr"] .admin-sidebar{
    transform: translateX(-100%);
  }
  body.admin-sidebar-open .admin-sidebar{
    transform: translateX(0);
  }
  .admin-sidebar__toggle{
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
}

.admin-sidebar__card {
  height: 100%;
  display: flex;
  flex-direction: column;
  background: var(--gdy-sidebar-card-bg);
  border-inline-start: 1px solid var(--gdy-sidebar-border);
  box-shadow: 0 0 25px rgba(15, 23, 42, 0.75);
}

@media (min-width: 992px) {
  html[dir="rtl"] .admin-content, html[dir="rtl"] .gdy-admin-page { margin-right: 260px !important; margin-left: 0 !important; }
  html[dir="ltr"] .admin-content, html[dir="ltr"] .gdy-admin-page { margin-left: 260px !important; margin-right: 0 !important; }
}
@media (max-width: 991.98px) {
  .admin-content, .gdy-admin-page { margin: 0 !important; }
}


.admin-sidebar__header{
  display:flex;align-items:center;justify-content:space-between;
  padding:.85rem .9rem;
  border-bottom:1px solid var(--gdy-sidebar-border);
}

.admin-sidebar__brand{display:flex;align-items:center;gap:.6rem;}
.admin-sidebar__logo{
  width:38px;height:38px;border-radius:16px;
  background: radial-gradient(circle at top, var(--gdy-sidebar-accent-soft), var(--gdy-sidebar-accent));
  color:#0b1120;
  display:flex;align-items:center;justify-content:center;
  box-shadow: 0 0 18px color-mix(in srgb, var(--gdy-sidebar-accent-soft) 60%, transparent 40%);
}
.admin-sidebar__title{font-size:.95rem;font-weight:700;line-height:1;}
.admin-sidebar__subtitle{font-size:.75rem;color:var(--gdy-sidebar-muted);margin-top:.15rem;}
.admin-sidebar__toggle{display:none;width:34px;height:34px;border-radius:12px;border:1px solid var(--gdy-sidebar-border);background:#020617;color:var(--gdy-sidebar-text);}

.admin-sidebar__search-wrapper{padding:.55rem .75rem .35rem;}
.admin-sidebar__search{position:relative;}
.admin-sidebar__search-input{
  width:100%;padding:.55rem .75rem .55rem 2.2rem;
  border-radius:999px;border:1px solid var(--gdy-sidebar-border);
  background:#020617;color:var(--gdy-sidebar-text);font-size:.8rem;
}
.admin-sidebar__search-input::placeholder{color:var(--gdy-sidebar-muted);}
.admin-sidebar__search-icon{
  position:absolute;left:.6rem;top:50%;transform:translateY(-50%);
  font-size:.85rem;color:var(--gdy-sidebar-muted);
}
.admin-sidebar__search-results{
  display:none;position:absolute;top:110%;left:0;right:0;
  background:#020617;border-radius:12px;border:1px solid var(--gdy-sidebar-border);
  box-shadow:0 18px 40px rgba(15,23,42,.95);
  max-height:260px;overflow:auto;z-index:1200;
}
.admin-sidebar__search-result-item{display:block;padding:.45rem .7rem;font-size:.8rem;color:var(--gdy-sidebar-text);text-decoration:none;border-bottom:1px solid rgba(31,41,55,.75)}
.admin-sidebar__search-result-item:last-child{border-bottom:0;}
.admin-sidebar__search-result-item:hover{background:#0b1120;}

.admin-sidebar__quick{
  padding:.25rem .75rem .55rem;
  display:grid;grid-template-columns:repeat(3,1fr);gap:.35rem;
}
.admin-sidebar__quick-item{
  border:1px solid rgba(31,41,55,.9);
  background: radial-gradient(circle at top,
    color-mix(in srgb, var(--gdy-sidebar-accent) 14%, transparent 86%),
    rgba(15,23,42,.98)
  );
  border-radius:12px;
  padding:.25rem .35rem;
  display:flex;align-items:center;justify-content:space-between;gap:.35rem;
}
.admin-sidebar__quick-icon{
  width:26px;height:26px;border-radius:10px;
  display:flex;align-items:center;justify-content:center;
  background:#020617;color:var(--gdy-sidebar-accent);font-size:.82rem;
}
.admin-sidebar__quick-value{font-weight:700;font-variant-numeric:tabular-nums;font-size:.85rem;}

.admin-sidebar__nav{padding:.2rem .55rem 0;overflow:auto;flex:1;}
.admin-sidebar__section{padding:.35rem 0 .15rem;}
.admin-sidebar__section-title{font-size:.74rem;color:var(--gdy-sidebar-muted);padding:.35rem .55rem .25rem;}

.admin-sidebar__link-card{
  border:1px solid rgba(148,163,184,0.14);
  border-radius:14px;
  background: radial-gradient(circle at top left,
    color-mix(in srgb, var(--gdy-sidebar-accent) 8%, transparent 92%),
    rgba(15,23,42,.98)
  );
  margin:.35rem .45rem;
  overflow:hidden;
  transition: transform .12s ease, border-color .12s ease;
}
.admin-sidebar__link-card:hover{transform: translateY(-1px);border-color: color-mix(in srgb, var(--gdy-sidebar-accent) 35%, transparent 65%);}
.admin-sidebar__link-card.is-active{border-color: color-mix(in srgb, var(--gdy-sidebar-accent) 55%, transparent 45%); box-shadow: 0 0 0 1px color-mix(in srgb, var(--gdy-sidebar-accent) 20%, transparent 80%) inset;}

.admin-sidebar__link{display:block;color:inherit;text-decoration:none;padding:.55rem .6rem;}
.admin-sidebar__link-main{display:flex;align-items:flex-start;gap:.55rem;}
.admin-sidebar__link-icon{
  width:34px;height:34px;border-radius:12px;
  background: rgba(2,6,23,.85);
  border:1px solid rgba(148,163,184,0.18);
  display:flex;align-items:center;justify-content:center;
  color: var(--gdy-sidebar-accent);
  flex: 0 0 34px;
}
.admin-sidebar__link-text{min-width:0;}
.admin-sidebar__link-label{font-size:.86rem;font-weight:650;line-height:1.1;}
.admin-sidebar__link-sub{font-size:.73rem;color:var(--gdy-sidebar-muted);margin-top:.15rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.admin-sidebar__link-arrow{float:left;color:rgba(148,163,184,.8);margin-top:.35rem;}

.admin-sidebar__footer{border-top:1px solid var(--gdy-sidebar-border);padding:.7rem .75rem;}
.admin-sidebar__user{display:flex;align-items:center;gap:.55rem;margin-bottom:.55rem;}
.admin-sidebar__user-avatar{
  width:38px;height:38px;border-radius:16px;
  background:#020617;border:1px solid rgba(148,163,184,.22);
  display:flex;align-items:center;justify-content:center;overflow:hidden;
}
.admin-sidebar__user-avatar img{width:100%;height:100%;object-fit:cover;}
.admin-sidebar__user-name{font-size:.86rem;font-weight:700;line-height:1.1;}
.admin-sidebar__user-role{font-size:.74rem;color:var(--gdy-sidebar-muted);}

.admin-sidebar__footer-actions{display:flex;gap:.35rem;justify-content:space-between;}
.admin-sidebar__action-btn{
  width:36px;height:36px;border-radius:14px;
  border:1px solid rgba(148,163,184,.22);
  background:#020617;color:var(--gdy-sidebar-text);
  display:inline-flex;align-items:center;justify-content:center;
  text-decoration:none;
}
.admin-sidebar__action-btn:hover{border-color: color-mix(in srgb, var(--gdy-sidebar-accent) 45%, transparent 55%);}
.admin-sidebar__action-btn--danger{border-color: color-mix(in srgb, var(--gdy-danger) 35%, transparent 65%);}
.admin-sidebar__action-btn--danger:hover{border-color: color-mix(in srgb, var(--gdy-danger) 65%, transparent 35%);}

</style>

<script>
document.addEventListener('DOMContentLoaded', function () {

  // Mobile off-canvas sidebar controls
  const openBtn = document.getElementById('gdyAdminMenuBtn');
  const closeBtn = document.getElementById('sidebarToggle');
  const backdrop = document.getElementById('gdyAdminBackdrop');

  function openSidebar(){
    document.body.classList.add('admin-sidebar-open');
    if (backdrop) backdrop.hidden = false;
  }
  function closeSidebar(){
    document.body.classList.remove('admin-sidebar-open');
    if (backdrop) backdrop.hidden = true;
  }

  if (openBtn) openBtn.addEventListener('click', openSidebar);
  if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
  if (backdrop) backdrop.addEventListener('click', closeSidebar);

  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') closeSidebar();
  });

  // Auto-close sidebar when a link is clicked on small screens
  document.querySelectorAll('.admin-sidebar a').forEach(function(a){
    a.addEventListener('click', function(){
      if (window.matchMedia && window.matchMedia('(max-width: 991.98px)').matches) {
        closeSidebar();
      }
    });
  });


  const searchInput = document.getElementById('sidebarSearch');
  const searchResults = document.getElementById('sidebarSearchResults');
  const darkToggle = document.getElementById('darkModeToggle');
  const cards = Array.from(document.querySelectorAll('.admin-sidebar__link-card'));

  if (searchInput && searchResults) {
    searchInput.addEventListener('input', function () {
      const q = (searchInput.value || '').trim().toLowerCase();
      searchResults.innerHTML = '';
      if (!q) {
        searchResults.style.display = 'none';
        return;
      }

      const matches = cards.filter(card => {
        const hay = (card.getAttribute('data-search') || '') + ' ' + (card.textContent || '');
        return hay.toLowerCase().includes(q);
      }).slice(0, 12);

      searchResults.style.display = 'block';
      if (!matches.length) {
        const div = document.createElement('div');
        div.className = 'admin-sidebar__search-result-item';
        div.textContent = 'لا توجد نتائج مطابقة';
        searchResults.appendChild(div);
        return;
      }

      matches.forEach(card => {
        const link = card.querySelector('a');
        if (!link) return;
        const labelEl = card.querySelector('.admin-sidebar__link-label');
        const label = labelEl ? labelEl.textContent.trim() : link.textContent.trim();

        const a = document.createElement('a');
        a.href = link.getAttribute('href');
        a.className = 'admin-sidebar__search-result-item';
        a.textContent = label;
        searchResults.appendChild(a);
      });
    });

    document.addEventListener('click', function (e) {
      if (!searchResults.contains(e.target) && e.target !== searchInput) {
        searchResults.style.display = 'none';
      }
    });
  }

  if (darkToggle) {
    darkToggle.addEventListener('click', function () {
      document.body.classList.toggle('godyar-dark');
    });
  }
});
</script>
