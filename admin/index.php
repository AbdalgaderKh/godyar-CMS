<?php
declare(strict_types=1);


require_once __DIR__ . '/_admin_guard.php';
// admin/index.php — لوحة تحكم Godyar

require_once __DIR__ . '/../includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}



require_once __DIR__ . '/../includes/auth.php';

use Godyar\Auth;

if (!Auth::isLoggedIn()) {
    header('Location: /admin/login');
    exit;
}

// الكاتب يذهب مباشرة للوحة الأخبار
if (Auth::isWriter()) {
    header('Location: /admin/news/index.php');
    exit;
}

// هيلبر للهروب الآمن
if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}

// تحديد روابط الأساس للموقع ولوحة التحكم بالاعتماد على base_url()
$siteBase  = function_exists('base_url') ? rtrim(base_url(), '/') : '';
$adminBase = $siteBase . '/admin';

// التحقق من الجلسة
$user    = $_SESSION['user'] ?? null;
$isAdmin = is_array($user) && (($user['role'] ?? '') === 'admin');

// نحاول جلب PDO من البوتستراب
$pdo = gdy_pdo_safe();

// إحصائيات متقدمة
$stats = [
    'news'             => 0,
    'categories'       => 0,
    'users'            => 0,
    'comments'         => 0,
    'today_news'       => 0,
    'today_users'      => 0,
    'today_comments'   => 0,
    'popular_news'     => 0,
    'storage_usage'    => 0,
    'unread_messages'  => 0, // رسائل اتصل بنا غير المقروءة / الجديدة
];

// إحصائيات إضافية
$recentNews     = [];
$systemInfo     = [];
$popularNews    = [];
$newsLast7Days  = [];

if ($pdo instanceof PDO) {
    try {
        // نتحقق من وجود الجداول قبل الاستعلام لتفادي الأخطاء لو جدول ناقص
        $tables = [];
        $stmtTables = $pdo->query("SHOW TABLES");
        if ($stmtTables) {
            $tables = $stmtTables->fetchAll(PDO::FETCH_COLUMN) ?: [];
        }

        $hasNews          = in_array('news', $tables, true);
        $hasCategories    = in_array('categories', $tables, true);
        $hasUsers         = in_array('users', $tables, true);
        $hasComments      = in_array('comments', $tables, true);
        $hasContact       = in_array('contact_messages', $tables, true);

        // الإحصائيات الأساسية للأخبار
        if ($hasNews) {
            $stats['news']       = (int) $pdo->query("SELECT COUNT(*) FROM news")->fetchColumn();
            $stats['today_news'] = (int) $pdo->query("SELECT COUNT(*) FROM news WHERE DATE(created_at) = CURDATE()")->fetchColumn();

            // الأخبار الأكثر مشاهدة
            try {
                $stmt        = $pdo->query("SELECT title, views FROM news ORDER BY views DESC LIMIT 5");
                $popularNews = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                @error_log('[Dashboard] popularNews error: ' . $e->getMessage());
            }

            // آخر الأخبار (عنوان + تاريخ)
            try {
                $stmt       = $pdo->query("SELECT id, title, created_at FROM news ORDER BY created_at DESC LIMIT 6");
                $recentNews = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                @error_log('[Dashboard] recentNews error: ' . $e->getMessage());
            }

	            // نشاط آخر 7 أيام
	            // الافتراضي: زيارات صفحات الأخبار (article) إن توفر جدول visits
	            try {
	                $hasVisitsForChart = function_exists('db_table_exists') ? db_table_exists($pdo, 'visits') : false;
	                if ($hasVisitsForChart) {
	                    $stmt7 = $pdo->query("
	                        SELECT DATE(created_at) AS d, COUNT(*) AS c
	                        FROM visits
	                        WHERE page = 'article'
	                          AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
	                        GROUP BY DATE(created_at)
	                        ORDER BY d ASC
	                    ");
	                } else {
	                    // fallback قديم: عدد الأخبار المنشورة/المضافة
	                    $stmt7 = $pdo->query("
	                        SELECT DATE(created_at) AS d, COUNT(*) AS c
	                        FROM news
	                        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
	                        GROUP BY DATE(created_at)
	                        ORDER BY d ASC
	                    ");
	                }
	                $rows7 = $stmt7 ? ($stmt7->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

                $today = new DateTimeImmutable('today');
                // نجهز الأيام السبعة (من قبل 6 أيام حتى اليوم)
                for ($i = 6; $i >= 0; $i--) {
                    $day = $today->sub(new DateInterval('P' . $i . 'D'));
                    $key = $day->format('Y-m-d');
                    $newsLast7Days[$key] = 0;
                }
                foreach ($rows7 as $r) {
                    $d = $r['d'] ?? null;
                    if ($d && isset($newsLast7Days[$d])) {
                        $newsLast7Days[$d] = (int) ($r['c'] ?? 0);
                    }
                }
            } catch (Throwable $e) {
                @error_log('[Dashboard] last7days error: ' . $e->getMessage());
            }
        }

        if ($hasCategories) {
            $stats['categories'] = (int) $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
        }

        if ($hasUsers) {
            $stats['users'] = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            // المستخدمون المسجلون اليوم (لو حقل created_at موجود)
            try {
                $stats['today_users'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn();
            } catch (Throwable $e) {
                @error_log('[Dashboard] today_users error: ' . $e->getMessage());
            }
        }

        if ($hasComments) {
            $stats['comments'] = (int) $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();
            // التعليقات المضافة اليوم (لو حقل created_at موجود)
            try {
                $stats['today_comments'] = (int) $pdo->query("SELECT COUNT(*) FROM comments WHERE DATE(created_at) = CURDATE()")->fetchColumn();
            } catch (Throwable $e) {
                @error_log('[Dashboard] today_comments error: ' . $e->getMessage());
            }
        }

        // رسائل اتصل بنا غير المقروءة / الجديدة
        if ($hasContact) {
            try {
                // نحاول احتساب الرسائل الجديدة بناء على status / is_read إن وُجدا
                $sqlContact = "SELECT COUNT(*) FROM contact_messages";
                // لو يوجد أعمدة status أو is_read نستخدمها لتقييد الرسائل
                $colsStmt = $pdo->query("SHOW COLUMNS FROM contact_messages");
                $cols = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_COLUMN) : [];

                if (in_array('status', $cols, true) || in_array('is_read', $cols, true)) {
                    $where = [];
                    if (in_array('status', $cols, true)) {
                        $where[] = "status = 'new'";
                    }
                    if (in_array('is_read', $cols, true)) {
                        $where[] = "is_read = 0";
                    }
                    if (!empty($where)) {
                        $sqlContact .= " WHERE " . implode(' OR ', $where);
                    }
                }

                $stats['unread_messages'] = (int) $pdo->query($sqlContact)->fetchColumn();
            } catch (Throwable $e) {
                @error_log('[Dashboard] contact_messages error: ' . $e->getMessage());
            }
        }

    } catch (Throwable $e) {
        @error_log('[Dashboard] database error: ' . $e->getMessage());
    }
}

// محاولة جلب نسخة MySQL الفعلية
$mysqlVersion = __('t_6b5e6d57ba', 'غير معروف');
if ($pdo instanceof PDO) {
    try {
        $vStmt = $pdo->query("SELECT VERSION() AS v");
        if ($vStmt) {
            $row = $vStmt->fetch(PDO::FETCH_ASSOC);
            if (!empty($row['v'])) {
                $mysqlVersion = $row['v'];
            }
        }
    } catch (Throwable $e) {
        @error_log('[Dashboard] MySQL version error: ' . $e->getMessage());
    }
}

// معلومات النظام
$systemInfo = [
    'php_version'         => PHP_VERSION,
    'server_software'     => $_SERVER['SERVER_SOFTWARE'] ?? __('t_6b5e6d57ba', 'غير معروف'),
    'mysql_version'       => $mysqlVersion,
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'memory_limit'        => ini_get('memory_limit')
];

$avgNewsPerCategory = ($stats['categories'] > 0)
    ? round($stats['news'] / max($stats['categories'], 1), 1)
    : 0.0;

// نسب تقدّمية لشرائط الأداء
$newsTodayPercent = ($stats['news'] > 0)
    ? min(($stats['today_news'] / max($stats['news'], 1)) * 100, 100)
    : 0;

$categoriesUsagePercent = ($avgNewsPerCategory > 0)
    ? min(($avgNewsPerCategory / 10) * 100, 100) // نفترض 10 أخبار/تصنيف كحد جيد
    : 0;

$usersPercent = min(($stats['users'] / 100) * 100, 100); // مقياس نسبي من 0–100

$commentsPerNews = ($stats['news'] > 0)
    ? $stats['comments'] / max($stats['news'], 1)
    : 0;
$commentsPercent = min($commentsPerNews * 20, 100); // مثلاً 5 تعليقات/خبر = 100%

// مؤشرات أداء النظام
$memoryUsageMb = function_exists('memory_get_usage')
    ? round(memory_get_usage(true) / 1024 / 1024, 1)
    : null;

$systemLoad = null;
if (function_exists('sys_getloadavg')) {
    $loadArr = sys_getloadavg();
    if (is_array($loadArr) && isset($loadArr[0])) {
        $systemLoad = round((float) $loadArr[0], 2);
    }
}

// وضع التصحيح (للتنبيه فقط)
$debugMode = (bool) ini_get('display_errors');

/* =========================
   Analytics (visits / sources / most read today)
   ========================= */
$visitAnalytics = [
    'today' => 0,
    'unique_today' => 0,
    'sources' => ['direct'=>0,'search'=>0,'social'=>0,'referral'=>0],
	    'os' => [],
	    'browsers' => [],
    'top_news' => [], // ['title'=>..., 'count'=>..., 'id'=>...]
];
$visitAnalyticsEnabled = false;

if ($pdo instanceof PDO) {
    try {
        $hasVisits = function_exists('db_table_exists') ? db_table_exists($pdo, 'visits') : false;
        $hasNewsT  = function_exists('db_table_exists') ? db_table_exists($pdo, 'news') : false;

        if ($hasVisits) {
            $visitAnalyticsEnabled = true;

            $visitAnalytics['today'] = (int)$pdo->query("SELECT COUNT(*) FROM visits WHERE DATE(created_at) = CURDATE()")->fetchColumn();

            // عدد الزوار المميزين اليوم (حسب IP إن وُجد)
            try {
                $visitAnalytics['unique_today'] = (int)$pdo->query("SELECT COUNT(DISTINCT COALESCE(user_ip,'')) FROM visits WHERE DATE(created_at) = CURDATE()")->fetchColumn();
            } catch (Throwable $e) {
                $visitAnalytics['unique_today'] = 0;
            }

            // مصادر الزيارات
            $srcStmt = $pdo->query("SELECT source, COUNT(*) AS c FROM visits WHERE DATE(created_at)=CURDATE() GROUP BY source");
            $srcRows = $srcStmt ? ($srcStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
            foreach ($srcRows as $r) {
                $k = strtolower((string)($r['source'] ?? ''));
                $c = (int)($r['c'] ?? 0);
                if ($k === '') continue;
                if (!isset($visitAnalytics['sources'][$k])) $visitAnalytics['sources'][$k] = 0;
                $visitAnalytics['sources'][$k] += $c;
            }

	            // نوع النظام + نوع المتصفح (اليوم)
	            try {
	                $hasOs = function_exists('db_column_exists') ? db_column_exists($pdo, 'visits', 'os') : false;
	                $hasBr = function_exists('db_column_exists') ? db_column_exists($pdo, 'visits', 'browser') : false;

	                if ($hasOs) {
	                    $osStmt = $pdo->query("SELECT COALESCE(NULLIF(os,''),'Unknown') AS k, COUNT(*) AS c FROM visits WHERE DATE(created_at)=CURDATE() GROUP BY k ORDER BY c DESC LIMIT 6");
	                    $visitAnalytics['os'] = $osStmt ? ($osStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: []) : [];
	                }
	                if ($hasBr) {
	                    $brStmt = $pdo->query("SELECT COALESCE(NULLIF(browser,''),'Unknown') AS k, COUNT(*) AS c FROM visits WHERE DATE(created_at)=CURDATE() GROUP BY k ORDER BY c DESC LIMIT 6");
	                    $visitAnalytics['browsers'] = $brStmt ? ($brStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: []) : [];
	                }

	                // fallback إذا الأعمدة غير موجودة: نحاول التحليل من user_agent لعدد محدود من السجلات
	                if (!$hasOs || !$hasBr) {
	                    $uaStmt = $pdo->query("SELECT user_agent FROM visits WHERE DATE(created_at)=CURDATE() ORDER BY id DESC LIMIT 1500");
	                    $uaRows = $uaStmt ? ($uaStmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: []) : [];
	                    foreach ($uaRows as $ua) {
	                        $ua = (string)$ua;
	                        if (!$hasOs && function_exists('gdy_parse_os')) {
	                            $k = gdy_parse_os($ua);
	                            $visitAnalytics['os'][$k] = (int)($visitAnalytics['os'][$k] ?? 0) + 1;
	                        }
	                        if (!$hasBr && function_exists('gdy_parse_browser')) {
	                            $k = gdy_parse_browser($ua);
	                            $visitAnalytics['browsers'][$k] = (int)($visitAnalytics['browsers'][$k] ?? 0) + 1;
	                        }
	                    }
	                    if (!empty($visitAnalytics['os'])) {
	                        arsort($visitAnalytics['os']);
	                        $visitAnalytics['os'] = array_slice($visitAnalytics['os'], 0, 6, true);
	                    }
	                    if (!empty($visitAnalytics['browsers'])) {
	                        arsort($visitAnalytics['browsers']);
	                        $visitAnalytics['browsers'] = array_slice($visitAnalytics['browsers'], 0, 6, true);
	                    }
	                }
	            } catch (Throwable $e) {
	                @error_log('[Dashboard] os/browser analytics: ' . $e->getMessage());
	            }

            // الأكثر قراءة اليوم (حسب الزيارات في صفحة article)
            if ($hasNewsT) {
                $topStmt = $pdo->query("
                    SELECT news_id, COUNT(*) AS c
                    FROM visits
                    WHERE page = 'article'
                      AND news_id IS NOT NULL
                      AND DATE(created_at)=CURDATE()
                    GROUP BY news_id
                    ORDER BY c DESC
                    LIMIT 7
                ");
                $topRows = $topStmt ? ($topStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

                if (!empty($topRows)) {
                    $ids = array_map(fn($x)=> (int)($x['news_id'] ?? 0), $topRows);
                    $ids = array_values(array_filter($ids, fn($x)=>$x>0));
                    $titlesById = [];

                    if (!empty($ids)) {
                        $in = implode(',', array_fill(0, count($ids), '?'));
                        $tStmt = $pdo->prepare("SELECT id, title FROM news WHERE id IN ($in)");
                        $tStmt->execute($ids);
                        $tRows = $tStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                        foreach ($tRows as $tr) {
                            $titlesById[(int)$tr['id']] = (string)$tr['title'];
                        }
                    }

                    foreach ($topRows as $r) {
                        $nid = (int)($r['news_id'] ?? 0);
                        $cnt = (int)($r['c'] ?? 0);
                        if ($nid <= 0) continue;
                        $visitAnalytics['top_news'][] = [
                            'id' => $nid,
                            'title' => $titlesById[$nid] ?? (__('t_32ce97af03', 'خبر #') . $nid),
                            'count' => $cnt,
                        ];
                    }
                }
            }
        }
    } catch (Throwable $e) {
        @error_log('[Dashboard] visits analytics error: ' . $e->getMessage());
    }
}

// نسب مصادر الزيارات (للمخطط)
$visitTotalSources = array_sum($visitAnalytics['sources']) ?: 0;
$visitSourcePct = [];
foreach ($visitAnalytics['sources'] as $k => $v) {
    $visitSourcePct[$k] = $visitTotalSources > 0 ? round(($v / $visitTotalSources) * 100) : 0;
}


$currentPage = 'dashboard';
$pageTitle   = __('t_a06ee671f4', 'لوحة التحكم');

// قوالب الهيدر/السيدبار/الفوتر العامة
$headerPath  = __DIR__ . '/layout/header.php';
$sidebarPath = __DIR__ . '/layout/sidebar.php';
$footerPath  = __DIR__ . '/layout/footer.php';

// لو المستخدم مش أدمن → صفحة مصغرة فقط
if (!$isAdmin): ?>
    <!doctype html>
    <html lang="<?= htmlspecialchars((string)(function_exists('current_lang') ? current_lang() : (string)($_SESSION['lang'] ?? 'ar')), ENT_QUOTES, 'UTF-8') ?>" dir="<?= ((function_exists('current_lang') ? current_lang() : (string)($_SESSION['lang'] ?? 'ar')) === 'ar' ? 'rtl' : 'ltr') ?>">
    <head>
      <meta charset="utf-8">
      <title><?= h(__('t_559b292797', 'لوحة التحكم — تسجيل الدخول مطلوب')) ?></title>
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <link href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css" rel="stylesheet">
      </head>
    <body class="bg-dark text-light">
      <div class="container py-5">
        <h1 class="h4 mb-3"><?= h(__('t_399cbf37c4', 'لوحة التحكم — Godyar')) ?></h1>
        <div class="alert alert-warning">
          <?= h(__('t_c027002e8e', 'يجب تسجيل الدخول كمدير للوصول إلى لوحة التحكم.')) ?>
        </div>
        <a href="<?= h($adminBase) ?>/login.php" class="btn btn-primary"><?= h(__('t_a874173c2b', 'الذهاب لصفحة تسجيل الدخول')) ?></a>
        <a href="<?= h($siteBase) ?>/" class="btn btn-outline-light ms-2"><?= h(__('t_3a5661ec20', 'العودة للموقع')) ?></a>
      </div>
    </body>
    </html>
    <?php
    exit;
endif;

// من هنا المستخدم أدمن
if (is_file($headerPath))  require $headerPath;
if (is_file($sidebarPath)) require $sidebarPath;

// روابط سريعة (شريط المهام السريعة)
$quickLinks = [
    ['icon'=>'fa-circle-plus','text'=>__('t_158122ca5e', 'إضافة خبر'),'href'=>$adminBase . '/news/create.php','color'=>'success'],
    ['icon'=>'fa-newspaper','text'=>__('t_e06a9f8f17', 'إدارة الأخبار'),'href'=>$adminBase . '/news/','color'=>'primary'],
    ['icon'=>'fa-layer-group','text'=>__('t_14f0cf5e77', 'التصنيفات'),'href'=>$adminBase . '/categories/','color'=>'warning'],
    ['icon'=>'fa-users','text'=>__('t_39d3073371', 'المستخدمون'),'href'=>$adminBase . '/users/','color'=>'secondary'],
    ['icon'=>'fa-chart-line','text'=>__('t_4d4e102c5e', 'التقارير'),'href'=>$adminBase . '/reports/','color'=>'info'],
    ['icon'=>'fa-gear','text'=>__('t_1f60020959', 'الإعدادات'),'href'=>$adminBase . '/settings/','color'=>'dark'],
];
?>
<style>
:root {
  /* ألوان قريبة من هوية Godyar (أزرق/تركوازي) */
  --gdy-primary: #0ea5e9;
  --gdy-primary-dark: #0369a1;
  --gdy-accent: #22c55e;
  --gdy-text-main: #f9fafb;
  --gdy-text-muted: #cbd5f5;
}

/* خلفية عامة داكنة مع منع التمدد الأفقي */
body {
  background-color: #020617;
  color: var(--gdy-text-main);
  overflow-x: hidden;
}

/* غلاف لوحة التحكم (عرض كامل مع هوامش بسيطة) */
.gdy-dashboard-wrapper {
  background: radial-gradient(circle at top.left, #020617 0%, #020617 40%, #020617 100%);
  min-height: 100vh;
  color: var(--gdy-text-main);
  padding-inline: 0;
}

/* حافة داخلية للمحتوى مع مراعاة السايدبار */
.gdy-dashboard-wrapper > .container-fluid {
  padding-inline-start: 1.25rem;
  padding-inline-end: 1rem; /* مسافة مناسبة قبل قائمة اللوحة الجانبية */
}
@media (max-width: 991.98px) {
  .gdy-dashboard-wrapper > .container-fluid {
    padding-inline-start: .75rem;
    padding-inline-end: .75rem;
  }
}

/* رأس الصفحة */
.gdy-page-header {
  border-bottom: 1px solid rgba(148,163,184,0.35);
  padding-bottom: .75rem;
  margin-bottom: 1.5rem;
}
.gdy-page-header h1 {
  font-size: 1.35rem;
}
.gdy-page-header p {
  font-size: .92rem;
  color: var(--gdy-text-muted);
}

/* شريط الترحيب + المهام السريعة */
.gdy-quickbar {
  background: linear-gradient(135deg, var(--gdy-primary-dark) 0%, #020617 55%, #020617 100%);
  color: var(--gdy-text-main);
  box-shadow: 0 16px 35px rgba(15,23,42,0.9);
  border-radius: 1rem;
  border: 1px solid rgba(148,163,184,0.45);
  padding: 1.25rem 1.5rem;
  margin-bottom: 1.75rem;
  position: relative;
  overflow: hidden;
}
.gdy-quickbar::before {
  content: '';
  position: absolute;
  inset: -40%;
  background: radial-gradient(circle at top.right, rgba(14,165,233,0.2), transparent 60%);
  opacity: .9;
  pointer-events: none;
}
.gdy-quickbar-inner {
  position: relative;
  z-index: 1;
}

/* صندوق الترحيب */
.gdy-welcome-title {
  font-size: 1.3rem;
  font-weight: 700;
  margin-bottom: .25rem;
}
.gdy-welcome-sub {
  font-size: .9rem;
  color: var(--gdy-text-muted);
  margin-bottom: .75rem;
}
.gdy-welcome-meta {
  font-size: .88rem;
  color: var(--gdy-text-main);
  display: flex;
  flex-direction: column;
  gap: .15rem;
}
.gdy-welcome-meta span i {
  color: var(--gdy-primary);
}

/* شريط المهام السريعة */
.gdy-quickbar-title {
  font-size: .9rem;
  font-weight: 600;
  color: var(--gdy-text-main);
  margin-bottom: .4rem;
  display: inline-flex;
  align-items: center;
  gap: .4rem;
}
.gdy-quickbar-title i {
  color: var(--gdy-accent);
}

.gdy-quick-actions {
  display: flex;
  flex-wrap: wrap;
  gap: .4rem;
  justify-content: flex-end;
}

.gdy-quick-btn {
  padding: 0.45rem 0.9rem;
  border-radius: 999px;
  border: 1px solid rgba(148,163,184,0.7);
  font-weight: 500;
  font-size: .78rem;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  backdrop-filter: blur(10px);
  background: rgba(15,23,42,0.9);
  color: var(--gdy-text-main);
  transition: all 0.25s.ease;
}
.gdy-quick-btn i {
  font-size: .8rem;
}
.gdy-quick-btn:hover {
  transform: translateY(-2px);
  border-color: var(--gdy-primary);
  box-shadow: 0 8px 18px rgba(15,23,42,0.9);
  background: rgba(15,23,42,1);
}

/* شبكة الإحصائيات */
.gdy-stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
  gap: 1rem;
  margin-bottom: 1.75rem;
}
.gdy-stat-card {
  background: radial-gradient(circle at top.left, rgba(15,23,42,0.96), rgba(15,23,42,1));
  border-radius: 1rem;
  border: 1px solid rgba(148,163,184,0.5);
  padding: 1.4rem 1.3rem;
  position: relative;
  overflow: hidden;
  transition: all 0.25s.ease;
}
.gdy-stat-card::before {
  content: '';
  position: absolute;
  top: 0; right: 0;
  width: 100%;
  height: 3px;
  background: linear-gradient(90deg, var(--accent-color), transparent 70%);
}
.gdy-stat-card[data-color="blue"]   { --accent-color: #0ea5e9; }
.gdy-stat-card[data-color="green"]  { --accent-color: #22c55e; }
.gdy-stat-card[data-color="yellow"] { --accent-color: #eab308; }
.gdy-stat-card[data-color="purple"] { --accent-color: #8b5cf6; }
.gdy-stat-card[data-color="red"]    { --accent-color: #ef4444; }

.gdy-stat-label {
  font-size: .78rem;
  color: #e5e7eb;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .04em;
}
.gdy-stat-value {
  font-size: 2.1rem;
  font-weight: 800;
  margin: .4rem 0 .2rem;
  background: linear-gradient(135deg, var(--accent-color), #f9fafb);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}
.gdy-stat-trend {
  font-size: .8rem;
  color: var(--gdy-text-muted);
  display: flex;
  align-items: center;
  gap: .35rem;
}

/* البطاقات العامة */
.gdy-content-card {
  background: radial-gradient(circle at top.left, rgba(15,23,42,0.98), rgba(15,23,42,1));
  border-radius: 1rem;
  border: 1px solid rgba(148,163,184,0.5);
  padding: 1.5rem 1.4rem;
  margin-bottom: 1.5rem;
  transition: all 0.25s.ease;
}
.gdy-content-card:hover {
  border-color: rgba(148,163,184,0.9);
  box-shadow: 0 16px 35px rgba(15,23,42,0.95);
}


/* ✅ بطاقة بدون إطار (لتنسيق تحليلات الزيارات اليوم بدون إطار خارجي) */
.gdy-content-card--frameless{
  background: transparent !important;
  border: none !important;
  box-shadow: none !important;
  padding: 0 !important;
}
.gdy-content-card--frameless .gdy-card-header{
  background: transparent !important;
  border: none !important;
  padding: 0 0 .75rem 0 !important;
}
.gdy-content-card--frameless .p-3{
  padding: 0 !important;
}
.gdy-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  padding-bottom: .7rem;
  border-bottom: 1px solid rgba(55,65,81,0.9);
}
.gdy-card-title {
  font-size: 1rem;
  font-weight: 600;
  margin: 0;
  color: var(--gdy-text-main);
}
.gdy-card-header .btn {
  font-size: .78rem;
}

/* قائمة الأخبار */
.gdy-news-list {
  display: grid;
  gap: .8rem;
}
.gdy-news-item {
  display: flex;
  gap: .7rem;
  padding: .7rem .7rem;
  border-radius: .9rem;
  background: rgba(15,23,42,0.9);
  border: 1px solid rgba(31,41,55,0.95);
  transition: all 0.25s.ease;
}
.gdy-news-item:hover {
  transform: translateY(-2px);
  border-color: var(--gdy-primary);
  box-shadow: 0 10px 20px rgba(15,23,42,1);
}
.gdy-news-icon {
  width: 36px;
  height: 36px;
  border-radius: .9rem;
  display: flex;
  align-items: center;
  justify-content: center;
  background: radial-gradient(circle at top, var(--gdy-primary), transparent);
  flex-shrink: 0;
}
.gdy-news-title {
  font-size: .86rem;
  font-weight: 600;
  margin-bottom: .15rem;
  color: var(--gdy-text-main);
}
.gdy-news-meta {
  font-size: .75rem;
  color: var(--gdy-text-muted);
  display: flex;
  flex-wrap: wrap;
  gap: .6rem;
}

/* شبكة البطاقات السفلية */
.gdy-cards-grid {
  display: grid;
  grid-template-columns: minmax(0,2.1fr) minmax(0,1.4fr);
  gap: 1rem;
}
@media (max-width: 991.98px) {
  .gdy-cards-grid {
    grid-template-columns: minmax(0,1fr);
  }
}

/* مؤشرات النظام */
.gdy-system-indicators {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: .7rem;
  margin-top: .6rem;
}
.gdy-indicator {
  padding: .7rem .6rem;
  border-radius: .9rem;
  background: rgba(15,23,42,1);
  border: 1px solid rgba(31,41,55,0.98);
  text-align: center;
}
.gdy-indicator-value {
  font-size: 1rem;
  font-weight: 600;
  margin: .15rem 0;
  color: var(--gdy-text-main);
}
.gdy-indicator-label {
  font-size: .72rem;
  color: var(--gdy-text-muted);
}

/* أشرطة الأداء */
.gdy-performance-bar {
  height: 6px;
  background: rgba(31,41,55,0.9);
  border-radius: 999px;
  overflow: hidden;
  margin-top: .4rem;
}
.gdy-performance-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--gdy-primary), var(--gdy-accent));
  border-radius: 999px;
  transition: width 0.8s ease;
}

/* مؤشرات أداء إضافية في حالة النظام */
.gdy-performance-indicators {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: .7rem;
  margin-top: .9rem;
}
.gdy-performance-card {
  padding: .7rem .6rem;
  border-radius: .9rem;
  background: rgba(15,23,42,1);
  border: 1px solid rgba(31,41,55,0.98);
  text-align: center;
}
.gdy-performance-card i {
  font-size: 1.2rem;
  margin-bottom: .25rem;
  color: var(--gdy-primary);
}

/* مخطط نشاط آخر 7 أيام */
.gdy-activity-chart {
  margin-bottom: 1.5rem;
}
.gdy-activity-bars {
  display: flex;
  align-items: flex-end;
  gap: .5rem;
  min-height: 120px;
}

/* ✅ RTL: اجعل أعمدة نشاط آخر 7 أيام تُعرض من اليمين لليسار بشكل طبيعي */
html[dir="rtl"] .gdy-activity-bars{
  flex-direction: row-reverse;
}
.gdy-activity-bar {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: .25rem;
}
.gdy-activity-bar-inner {
  width: 70%;
  border-radius: 999px;
  background: linear-gradient(180deg, var(--gdy-primary), var(--gdy-accent));
  box-shadow: 0 6px 14px rgba(15,23,42,1);
  transition: height .3s ease;
}
.gdy-activity-bar-label {
  font-size: .7rem;
  color: var(--gdy-text-muted);
}

/* فوتر الإدارة */
.gdy-admin-footer {
  margin-top: 2.2rem;
  padding-top: 1rem;
  border-top: 1px solid rgba(31,41,55,0.98);
  font-size: .8rem;
  color: var(--gdy-text-muted);
}

/* حركات خفيفة (بدون إخفاء المحتوى إذا كانت الحركات معطلة) */
.gdy-stat-card,
.gdy-content-card {
  opacity: 1;
  transform: none;
}

/* نفعّل الحركة فقط إذا كانت مفضّلة لدى المتصفح */
@media (prefers-reduced-motion: no-preference) {
  .gdy-stat-card,
  .gdy-content-card {
    opacity: 0;
    transform: translateY(18px);
    animation: gdy-slide-up .5s ease forwards;
  }
}

@keyframes gdy-slide-up {
  to { opacity: 1; transform: translateY(0); }
}

/* تحسين العرض على الشاشات الصغيرة */
@media (max-width: 768px) {
  .gdy-quickbar {
    padding: 1rem 1rem;
  }
  .gdy-quick-actions {
    justify-content: flex-start;
  }
}


/* ===== Analytics cards ===== */
.gdy-analytics-grid{
  display: grid;
  grid-template-columns: repeat(12, 1fr);
  gap: 1rem;
  margin-bottom: 1rem;
}
.gdy-analytics-card{
  /* ✅ نفس خصائص بطاقات (إجمالي الأخبار/التصنيفات/التعليقات...) */
  background: radial-gradient(circle at top.left, rgba(15,23,42,0.96), rgba(15,23,42,1));
  border-radius: 1rem;
  border: 1px solid rgba(148,163,184,0.5);
  position: relative;
  overflow: hidden;
  transition: all 0.25s ease;
  box-shadow: none;
  --accent-color: var(--gdy-primary);
}
.gdy-analytics-card::before{
  content: '';
  position: absolute;
  top: 0; right: 0;
  width: 100%;
  height: 3px;
  background: linear-gradient(90deg, var(--accent-color), transparent 70%);
}
.gdy-analytics-card:hover{
  border-color: rgba(148,163,184,0.9);
  box-shadow: 0 16px 35px rgba(15,23,42,0.95);
  transform: translateY(-2px);
}
.gdy-analytics-card .gdy-card-header{
  /* مثل ترويسة بطاقات لوحة التحكم */
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:.75rem;
  padding: 1rem 1rem;
  margin: 0;
  border-bottom: 1px solid rgba(55,65,81,0.9);
}
.gdy-analytics-card .gdy-card-title{
  margin:0;
  font-size: .95rem;
  font-weight: 700;
  display:flex;
  align-items:center;
  gap:.5rem;
}
.gdy-analytics-card .gdy-card-body{
  padding: 1rem;
}
.gdy-analytics-card[data-span="4"]{ grid-column: span 4; }
.gdy-analytics-card[data-span="8"]{ grid-column: span 8; }
.gdy-analytics-card[data-span="12"]{ grid-column: span 12; }
@media (max-width: 1199.98px){
  .gdy-analytics-card[data-span="4"]{ grid-column: span 6; }
  .gdy-analytics-card[data-span="8"]{ grid-column: span 12; }
}
@media (max-width: 767.98px){
  .gdy-analytics-card[data-span="4"]{ grid-column: span 12; }
}

/* ✅ تحليلات الزيارات اليوم: نفس حجم/خصائص بطاقات الإحصائيات الأساسية */
.gdy-analytics-grid--today{
  display: grid;
  gap: 1rem;
  /* 3 بطاقات متساوية على الشاشات الكبيرة */
  grid-template-columns: repeat(3, minmax(230px, 1fr));
}
/* تجاهل data-span داخل هذا القسم حتى لا تنضغط الأعمدة */
.gdy-analytics-grid--today .gdy-analytics-card{
  grid-column: auto !important;
}
/* على الشاشات الصغيرة: نفس سلوك بطاقات (إجمالي الأخبار...) = بطاقات بعرض كامل */
@media (max-width: 991.98px){
  .gdy-analytics-grid--today{
    grid-template-columns: 1fr;
  }
}

/* منع تكسّر النص داخل بطاقات التحليلات */
.gdy-analytics-card, .gdy-analytics-card *{
  word-break: normal;
  overflow-wrap: anywhere;
}


.gdy-kpi{
  display:flex;
  align-items:center;
  justify-content:space-between;
  padding: .75rem .85rem;
  background: rgba(2,6,23,0.55);
  border: 1px solid rgba(148,163,184,0.22);
  border-radius: .85rem;
  margin-bottom: .6rem;
}
.gdy-kpi .label{ color: var(--gdy-text-muted); font-size: .85rem; }
.gdy-kpi .value{ font-size: 1.3rem; font-weight: 800; letter-spacing: .2px; }
.gdy-kpi i{ color: var(--gdy-primary); }

.gdy-source-row{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:.75rem;
  margin-bottom:.6rem;
  font-size:.9rem;
}
.gdy-source-row .name{
  min-width: 92px;
  color: #e5e7eb;
  display:flex;
  align-items:center;
  gap:.45rem;
}
.gdy-source-row .bar{
  flex:1;
  height: 10px;
  border-radius: 999px;
  background: rgba(148,163,184,0.18);
  overflow:hidden;
  border: 1px solid rgba(148,163,184,0.18);
}
.gdy-source-row .fill{
  height: 100%;
  width: 0%;
  background: linear-gradient(90deg, rgba(14,165,233,0.95), rgba(34,197,94,0.85));
}
.gdy-source-row .pct{
  width: 46px;
  text-align:left;
  color: var(--gdy-text-muted);
  font-variant-numeric: tabular-nums;
}
.gdy-top-news{
  width:100%;
  border-collapse: collapse;
}
.gdy-top-news th, .gdy-top-news td{
  padding: .55rem .4rem;
  border-bottom: 1px solid rgba(148,163,184,0.18);
  vertical-align: top;
}
.gdy-top-news th{
  color: var(--gdy-text-muted);
  font-size:.82rem;
  font-weight:600;
}
.gdy-top-news td{
  font-size:.9rem;
}
.gdy-top-news a{ color: #e5e7eb; }
.gdy-top-news a:hover{ color: #fff; text-decoration: underline; }



/* ✅ إصلاح تشوه بطاقات تحليلات الزيارات على الشاشات الصغيرة/بدون viewport صحيح
   - يجعل الشبكة مرنة مع حد أدنى للعرض، ويمنع انضغاط البطاقات إلى أعمدة ضيقة
*/
/* تحسينات إضافية لقراءة النص داخل كروت التحليلات */
.gdy-analytics-card .gdy-card-title{
  max-width: 100%;
  white-space: normal;
}
/* ✅ منع أي تمدد أفقي بسبب الشبكات/الكروت */
.admin-content,
.gdy-dashboard-wrapper,
.gdy-dashboard-wrapper > .container-fluid {
  max-width: 100%;
}

.gdy-stats-grid,
.gdy-analytics-grid,
.gdy-cards-grid,
.row,
.col,
[class*="col-"] {
  min-width: 0;
}

.gdy-content-card,
.gdy-stat-card,
.gdy-analytics-card {
  min-width: 0;
}

</style>

<div class="admin-content gdy-dashboard-wrapper">
  <div class="container-fluid py-4">

    <!-- الصندوق 1: عنوان لوحة التحكم -->
    <div class="gdy-page-header">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <h1 class="mb-1 text-white fw-bold"><?= h(__('t_a06ee671f4', 'لوحة التحكم')) ?></h1>
          <p class="mb-0">
            <?= h(__('t_4fa3486d5e', 'نظرة عامة على أداء النظام والمحتوى والتفاعل.')) ?>
          </p>
        </div>
        <div class="text-sm text-secondary d-flex align-items-center flex-wrap gap-2">
          <span class="me-2">
            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg> <?= h(__('t_c2cde46825', 'مدير النظام')) ?>
          </span>
          <a class="text-secondary text-decoration-none me-2" href="<?= h($siteBase) ?>/" target="_blank" rel="noopener" title="<?= h(__('t_8a0d450cfd', 'الانتقال للموقع الرئيسي')) ?>">
            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#home"></use></svg> <?= h(__('t_03b57332e5', 'الموقع الرئيسي')) ?>
          </a>
          <?php if ($debugMode): ?>
            <span class="badge bg-danger">
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              <?= h(__('t_d78fba4389', 'وضع التطوير (display_errors مفعل)')) ?>
            </span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- الصندوق 2 + الشريط 3: الترحيب + شريط المهام السريعة -->
    <div class="gdy-quickbar">
      <div class="gdy-quickbar-inner">
        <div class="row align-items-center gy-3">
          <!-- الترحيب -->
          <div class="col-md-5">
            <div class="gdy-welcome-box">
              <h2 class="gdy-welcome-title mb-1">
                أهلاً <?= h($user['name'] ?? $user['email'] ?? 'admin') ?>! 👋
              </h2>
              <p class="gdy-welcome-sub">
                <?= h(__('t_4b682fc953', 'نظرة شاملة على أداء النظام والإحصائيات الحيوية')) ?>
              </p>
              <div class="gdy-welcome-meta">
                <span>
                  <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  <span class="gdy-date-value"><?= date('Y-m-d') ?></span>
                </span>
                <span>
                  <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  <span class="gdy-time-value"><?= date('H:i:s') ?></span>
                </span>
                <span>
                  <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg>
                  أخبار اليوم: <?= (int) $stats['today_news'] ?>
                  / تعليقات اليوم: <?= (int) $stats['today_comments'] ?>
                  / رسائل جديدة: <?= (int) $stats['unread_messages'] ?>
                </span>
              </div>
            </div>
          </div>

          <!-- شريط المهام السريعة -->
          <div class="col-md-7 text-md-end">
            <div class="gdy-quickbar-title mb-2">
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              <span><?= h(__('t_5383623868', 'شريط المهام السريعة')) ?></span>
            </div>
            <div class="gdy-quick-actions">
              <?php foreach ($quickLinks as $link): ?>
                <a href="<?= h($link['href']) ?>" class="gdy-quick-btn">
                  <svg class="gdy-icon <?= h($link['icon']) ?>" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  <span><?= h($link['text']) ?></span>
                </a>
              <?php endforeach; ?>

              <!-- زر التحكم في إظهار/إخفاء القائمة الجانبية في الواجهة -->
              <button type="button" id="gdy-sidebar-global-toggle" class="gdy-quick-btn">
                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                <span class="gdy-sidebar-global-label"><?= h(__('t_a0c2dca9fc', 'إخفاء القائمة الجانبية في الواجهة')) ?></span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- الإحصائيات الرئيسية -->
    <div class="gdy-stats-grid">
      <div class="gdy-stat-card" data-color="blue">
        <div class="gdy-stat-label"><?= h(__('t_93e37eb4e5', 'إجمالي الأخبار')) ?></div>
        <div class="gdy-stat-value"><?= number_format($stats['news']) ?></div>
        <div class="gdy-stat-trend">
          <svg class="gdy-icon text-success" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          <span>+<?= (int) $stats['today_news'] ?> خبر جديد اليوم</span>
        </div>
        <div class="gdy-performance-bar">
          <div class="gdy-performance-fill" style="width: <?= $newsTodayPercent ?>%;"></div>
        </div>
      </div>

      <div class="gdy-stat-card" data-color="green">
        <div class="gdy-stat-label"><?= h(__('t_14f0cf5e77', 'التصنيفات')) ?></div>
        <div class="gdy-stat-value"><?= number_format($stats['categories']) ?></div>
        <div class="gdy-stat-trend">
          <span>متوسط <?= $avgNewsPerCategory ?> خبر لكل تصنيف</span>
        </div>
        <div class="gdy-performance-bar">
          <div class="gdy-performance-fill" style="width: <?= $categoriesUsagePercent ?>%;"></div>
        </div>
      </div>

      <div class="gdy-stat-card" data-color="yellow">
        <div class="gdy-stat-label"><?= h(__('t_39d3073371', 'المستخدمون')) ?></div>
        <div class="gdy-stat-value"><?= number_format($stats['users']) ?></div>
        <div class="gdy-stat-trend">
          <svg class="gdy-icon text-info" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          <span>+<?= (int) $stats['today_users'] ?> مستخدم جديد اليوم</span>
        </div>
        <div class="gdy-performance-bar">
          <div class="gdy-performance-fill" style="width: <?= $usersPercent ?>%;"></div>
        </div>
      </div>

      <div class="gdy-stat-card" data-color="purple">
        <div class="gdy-stat-label"><?= h(__('t_422df4da8b', 'التعليقات')) ?></div>
        <div class="gdy-stat-value"><?= number_format($stats['comments']) ?></div>
        <div class="gdy-stat-trend">
          <svg class="gdy-icon text-primary" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          <span><?= (int) $stats['today_comments'] ?> تعليق جديد اليوم</span>
        </div>
        <div class="gdy-performance-bar">
          <div class="gdy-performance-fill" style="width: <?= $commentsPercent ?>%;"></div>
        </div>
      </div>

      <!-- بطاقة إضافية: رسائل التواصل الجديدة -->
      <div class="gdy-stat-card" data-color="red">
        <div class="gdy-stat-label"><?= h(__('t_1be2ee1784', 'رسائل التواصل الجديدة')) ?></div>
        <div class="gdy-stat-value"><?= number_format($stats['unread_messages']) ?></div>
        <div class="gdy-stat-trend">
          <svg class="gdy-icon text-danger" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          <span><?= h(__('t_af2b984782', 'رسائل من نموذج "اتصل بنا" تحتاج للمراجعة')) ?></span>
        </div>
        <div class="gdy-performance-bar">
          <?php
          $msgPercent = $stats['unread_messages'] > 0
              ? min($stats['unread_messages'] * 10, 100)
              : 0;
          ?>
          <div class="gdy-performance-fill" style="width: <?= $msgPercent ?>%;"></div>
        </div>
      </div>

    </div><!-- ✅ إغلاق .gdy-stats-grid -->

    <!-- تحليلات الزيارات اليوم -->
    <div class="gdy-content-card mb-3 gdy-content-card--frameless">
      <div class="gdy-card-header">
        <h3 class="gdy-card-title">
          <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          <?= h(__('t_f850499360', 'تحليلات الزيارات اليوم')) ?>
        </h3>
        <div class="small text-muted">
          <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          <?= date('Y-m-d') ?>
        </div>
      </div>

      <div class="p-3">
        <?php if ($visitAnalyticsEnabled): ?>
          <div class="gdy-analytics-grid gdy-analytics-grid--today">
            <!-- زيارات اليوم -->
            <div class="gdy-analytics-card" data-span="4">
              <div class="gdy-card-header">
                <div class="gdy-card-title"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_1860c23886', 'زيارات اليوم')) ?></div>
              </div>
              <div class="gdy-card-body">
                <div class="gdy-kpi">
                  <div class="label"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_a5481217bd', 'إجمالي الزيارات')) ?></div>
                  <div class="value"><?= number_format((int)$visitAnalytics['today']) ?></div>
                </div>
                <div class="gdy-kpi mb-0">
                  <div class="label"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_da2ae4cedf', 'زوار مميزون')) ?></div>
                  <div class="value"><?= number_format((int)$visitAnalytics['unique_today']) ?></div>
                </div>
                <div class="small text-muted mt-2">
                  <?= h(__('t_b94e35403a', 'يتم احتساب "زوار مميزون" حسب IP إن كان متوفرًا.')) ?>
                </div>
              </div>
            </div>

            <!-- مصادر الزيارات -->
            <div class="gdy-analytics-card" data-span="4">
              <div class="gdy-card-header">
                <div class="gdy-card-title"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_354b3cc224', 'مصادر الزيارات')) ?></div>
              </div>
              <div class="gdy-card-body">
                <?php
                  $srcIcons = [
                    'direct'   => 'fa-bolt',
                    'search'   => 'fa-magnifying-glass',
                    'social'   => 'fa-hashtag',
                    'referral' => 'fa-link',
                  ];
                  $srcNames = [
                    'direct'   => __('t_d9e423c5cb', 'مباشر'),
                    'search'   => __('t_ab79fc1485', 'بحث'),
                    'social'   => __('t_869f4ae0fd', 'اجتماعي'),
                    'referral' => __('t_699dcac9c4', 'إحالات'),
                  ];
                ?>
                <?php foreach (['direct','search','social','referral'] as $k): ?>
                  <?php
                    $val = (int)($visitAnalytics['sources'][$k] ?? 0);
                    $pct = (int)($visitSourcePct[$k] ?? 0);
                    $icon = $srcIcons[$k] ?? 'fa-circle';
                    $name = $srcNames[$k] ?? $k;
                  ?>
                  <div class="gdy-source-row" title="<?= h($name) ?>: <?= $val ?> (<?= $pct ?>%)">
                    <div class="name">
                      <svg class="gdy-icon <?= h($icon) ?>" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                      <span><?= h($name) ?></span>
                    </div>
                    <div class="bar"><div class="fill" style="width: <?= $pct ?>%"></div></div>
                    <div class="pct"><?= $pct ?>%</div>
                  </div>
                <?php endforeach; ?>

                <div class="small text-muted mt-2">
                  <?= h(__('t_7043ad5a85', 'التصنيف: مباشر / بحث / اجتماعي / إحالات (حسب referrer).')) ?>
                </div>
              </div>
            </div>

            <!-- الأكثر قراءة اليوم -->
            <div class="gdy-analytics-card" data-span="4">
              <div class="gdy-card-header">
                <div class="gdy-card-title"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_f175185d34', 'الأكثر قراءة اليوم')) ?></div>
              </div>
              <div class="gdy-card-body">
                <?php if (!empty($visitAnalytics['top_news'])): ?>
                  <table class="gdy-top-news">
                    <thead>
                      <tr>
                        <th style="width: 70%;"><?= h(__('t_213a03802a', 'الخبر')) ?></th>
                        <th style="width: 30%;"><?= h(__('t_635e970ab9', 'الزيارات')) ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($visitAnalytics['top_news'] as $row): ?>
                        <tr>
                          <td>
                            <a href="<?= h($adminBase) ?>/news/edit.php?id=<?= (int)$row['id'] ?>">
                              <?= h($row['title']) ?>
                            </a>
                          </td>
                          <td><?= number_format((int)$row['count']) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                <?php else: ?>
                  <div class="text-muted"><?= h(__('t_90c052a29d', 'لا توجد بيانات كافية اليوم للأخبار الأكثر قراءة.')) ?></div>
                <?php endif; ?>

                <div class="small text-muted mt-2">
                  <?= h(__('t_0db1ace986', 'يتم الاحتساب من جدول الزيارات (visits) لصفحة')) ?> <b>article</b>.
                </div>
              </div>
            </div>

	          <!-- نوع النظام اليوم -->
	          <div class="gdy-analytics-card" data-span="6">
	            <div class="gdy-card-header">
	              <div class="gdy-card-title"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_b6f3b4f0a9', 'نوع النظام')) ?></div>
	            </div>
	            <div class="gdy-card-body">
	              <?php $osTotal = (int)array_sum((array)($visitAnalytics['os'] ?? [])); ?>
	              <?php if ($osTotal > 0): ?>
	                <?php foreach (($visitAnalytics['os'] ?? []) as $k => $v): ?>
	                  <?php $pct = $osTotal ? (int)round(((int)$v / $osTotal) * 100) : 0; ?>
	                  <div class="gdy-source-row" title="<?= h((string)$k) ?>: <?= (int)$v ?> (<?= $pct ?>%)">
	                    <div class="name">
	                      <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
	                      <span><?= h((string)$k) ?></span>
	                    </div>
	                    <div class="bar"><div class="fill" style="width: <?= $pct ?>%"></div></div>
	                    <div class="pct"><?= $pct ?>%</div>
	                  </div>
	                <?php endforeach; ?>
	              <?php else: ?>
	                <div class="text-muted"><?= h(__('t_6d8a2b5f58', 'لا توجد بيانات كافية اليوم.')) ?></div>
	              <?php endif; ?>
	            </div>
	          </div>

	          <!-- نوع المتصفح اليوم -->
	          <div class="gdy-analytics-card" data-span="6">
	            <div class="gdy-card-header">
	              <div class="gdy-card-title"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#globe"></use></svg> <?= h(__('t_6f1a3f7d22', 'نوع المتصفح')) ?></div>
	            </div>
	            <div class="gdy-card-body">
	              <?php $brTotal = (int)array_sum((array)($visitAnalytics['browsers'] ?? [])); ?>
	              <?php if ($brTotal > 0): ?>
	                <?php foreach (($visitAnalytics['browsers'] ?? []) as $k => $v): ?>
	                  <?php $pct = $brTotal ? (int)round(((int)$v / $brTotal) * 100) : 0; ?>
	                  <div class="gdy-source-row" title="<?= h((string)$k) ?>: <?= (int)$v ?> (<?= $pct ?>%)">
	                    <div class="name">
	                      <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
	                      <span><?= h((string)$k) ?></span>
	                    </div>
	                    <div class="bar"><div class="fill" style="width: <?= $pct ?>%"></div></div>
	                    <div class="pct"><?= $pct ?>%</div>
	                  </div>
	                <?php endforeach; ?>
	              <?php else: ?>
	                <div class="text-muted"><?= h(__('t_6d8a2b5f58', 'لا توجد بيانات كافية اليوم.')) ?></div>
	              <?php endif; ?>
	            </div>
	          </div>
          </div>
        <?php else: ?>
          <div class="alert alert-info mb-0">
            <?= h(__('t_1ae643cf89', 'لم يتم تفعيل إحصائيات الزيارات بعد. بمجرد دخول الزوار لصفحات الأخبار (صفحات الأخبار /news) سيتم إنشاء بيانات جدول')) ?> <b>visits</b> <?= h(__('t_c2e7b3e9e8', 'تلقائيًا.')) ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    </div>

    <!-- نشاط آخر 7 أيام للأخبار -->
    <?php if (!empty($newsLast7Days)): ?>
      <div class="gdy-content-card mb-3">
        <div class="gdy-card-header">
          <h3 class="gdy-card-title">
            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <?= h(__('t_390d8d902c', 'نشاط الأخبار خلال آخر 7 أيام')) ?>
          </h3>
        </div>
        <div class="gdy-activity-chart">
          <div class="gdy-activity-bars">
            <?php
            $maxNews7 = max($newsLast7Days) ?: 1;
            foreach ($newsLast7Days as $day => $count):
                $height = $maxNews7 > 0 ? round(($count / $maxNews7) * 100) : 0;
            ?>
              <div class="gdy-activity-bar">
                <div class="gdy-activity-bar-inner"
                     style="height: <?= $height ?>%;"
	                     title="<?= h($day) ?>: <?= (int) $count ?>"></div>
                <div class="gdy-activity-bar-label">
                  <?= date('D', strtotime($day)) ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- الصف الثاني: أحدث الأخبار + حالة النظام -->
    <div class="row g-3">
      <div class="col-lg-8">
        <div class="gdy-content-card">
          <div class="gdy-card-header">
            <h3 class="gdy-card-title">
              <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              <?= h(__('t_e610ba581c', 'آخر الأخبار المضافة')) ?>
            </h3>
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <div class="input-group input-group-sm" style="max-width: 230px;">
                <span class="input-group-text bg-dark border-secondary text-light">
                  <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#search"></use></svg>
                </span>
                <input type="text"
                       id="recent-news-search"
                       class="form-control bg-dark border-secondary text-light"
                       placeholder="<?= h(__('t_c10cc4a01e', 'بحث في الأخبار...')) ?>">
              </div>
              <a href="<?= h($adminBase) ?>/news/" class="btn btn-sm btn-outline-light">
                <?= h(__('t_7c627fa50f', 'عرض كل الأخبار')) ?>
              </a>
            </div>
          </div>
          <div class="gdy-news-list" id="recent-news-list">
            <?php if (!empty($recentNews)): ?>
              <?php foreach ($recentNews as $news): ?>
                <div class="gdy-news-item">
                  <div class="gdy-news-icon">
                    <svg class="gdy-icon text-white" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg>
                  </div>
                  <div>
                    <div class="gdy-news-title"><?= h($news['title']) ?></div>
                    <div class="gdy-news-meta">
                      <span><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= date('Y-m-d', strtotime($news['created_at'])) ?></span>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="text-center text-muted py-4">
                <svg class="gdy-icon mb-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg>
                <p class="mb-0"><?= h(__('t_6b2cba49e9', 'لا توجد أخبار حديثة حالياً.')) ?></p>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="gdy-content-card">
          <div class="gdy-card-header">
            <h3 class="gdy-card-title">
              <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              <?= h(__('t_640a46691d', 'حالة النظام')) ?>
            </h3>
          </div>
          <div class="gdy-system-indicators">
            <div class="gdy-indicator">
              <svg class="gdy-icon text-info" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              <div class="gdy-indicator-value"><?= h($systemInfo['php_version']) ?></div>
              <div class="gdy-indicator-label">PHP</div>
            </div>
            <div class="gdy-indicator">
              <svg class="gdy-icon text-success" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              <div class="gdy-indicator-value"><?= h($systemInfo['mysql_version']) ?></div>
              <div class="gdy-indicator-label">MySQL</div>
            </div>
            <div class="gdy-indicator">
              <svg class="gdy-icon text-warning" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              <div class="gdy-indicator-value"><?= h($systemInfo['memory_limit']) ?></div>
              <div class="gdy-indicator-label"><?= h(__('t_6e3781c6ec', 'حد الذاكرة')) ?></div>
            </div>
            <div class="gdy-indicator">
              <svg class="gdy-icon text-primary" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              <div class="gdy-indicator-value"><?= h($systemInfo['upload_max_filesize']) ?></div>
              <div class="gdy-indicator-label"><?= h(__('t_eac09e95a8', 'حجم الرفع الأقصى')) ?></div>
            </div>
          </div>

          <?php if ($memoryUsageMb !== null || $systemLoad !== null): ?>
            <div class="gdy-performance-indicators">
              <?php if ($memoryUsageMb !== null): ?>
                <div class="gdy-performance-card">
                  <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  <div class="gdy-indicator-value"><?= number_format($memoryUsageMb, 1) ?> MB</div>
                  <div class="gdy-indicator-label"><?= h(__('t_11f69e717a', 'استخدام الذاكرة (PHP)')) ?></div>
                </div>
              <?php endif; ?>
              <?php if ($systemLoad !== null): ?>
                <div class="gdy-performance-card">
                  <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  <div class="gdy-indicator-value"><?= number_format($systemLoad, 2) ?></div>
                  <div class="gdy-indicator-label"><?= h(__('t_aa45c9e9a9', 'تحميل النظام')) ?></div>
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- الصف الثالث: الأخبار الشائعة + نصائح الأمان -->
    <div class="gdy-cards-grid mt-2">
      <div class="gdy-content-card">
        <div class="gdy-card-header">
          <h3 class="gdy-card-title">
            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <?= h(__('t_c5bc8cdb85', 'الأخبار الأكثر مشاهدة')) ?>
          </h3>
        </div>
        <div class="gdy-news-list">
          <?php if (!empty($popularNews)): ?>
            <?php foreach ($popularNews as $index => $news): ?>
              <div class="gdy-news-item">
                <div class="gdy-news-icon">
                  <svg class="gdy-icon $index < 3 ? 'fire' : 'eye' ?> text-white" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                </div>
                <div>
                  <div class="gdy-news-title"><?= h($news['title']) ?></div>
                  <div class="gdy-news-meta">
                    <span><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= number_format($news['views']) ?> مشاهدة</span>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="text-center text-muted py-3">
              <svg class="gdy-icon mb-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              <p class="mb-0"><?= h(__('t_565b68e9d9', 'لا توجد بيانات مشاهدات كافية بعد.')) ?></p>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="gdy-content-card">
        <div class="gdy-card-header">
          <h3 class="gdy-card-title">
            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <?= h(__('t_7a8965fe52', 'نصائح الأمان وتنبيهات الإدارة')) ?>
          </h3>
        </div>
        <div class="gdy-news-list">
          <div class="gdy-news-item">
            <div class="gdy-news-icon">
              <svg class="gdy-icon text-white" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </div>
            <div>
              <div class="gdy-news-title"><?= h(__('t_80022702ae', 'استخدم كلمات مرور قوية')) ?></div>
              <div class="gdy-news-meta">
                <?= h(__('t_e185d0a75c', 'اجعل كلمة المرور طويلة وتحتوي على حروف وأرقام ورموز.')) ?>
              </div>
            </div>
          </div>
          <div class="gdy-news-item">
            <div class="gdy-news-icon">
              <svg class="gdy-icon text-white" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </div>
            <div>
              <div class="gdy-news-title"><?= h(__('t_c0679dc67e', 'تحديثات دورية للنظام')) ?></div>
              <div class="gdy-news-meta">
                <?= h(__('t_3f57ffd869', 'حافظ على تحديث السكربت والإضافات لسد الثغرات الأمنية.')) ?>
              </div>
            </div>
          </div>
          <div class="gdy-news-item">
            <div class="gdy-news-icon">
              <svg class="gdy-icon text-white" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </div>
            <div>
              <div class="gdy-news-title"><?= h(__('t_0bb7a7bb4d', 'نسخ احتياطية منتظمة')) ?></div>
              <div class="gdy-news-meta">
                <?= h(__('t_455ede10af', 'قم بإنشاء نسخ احتياطية لقاعدة البيانات والملفات بشكل دوري.')) ?>
              </div>
            </div>
          </div>

          <!-- تنبيه جديد عن رسائل التواصل -->
          <div class="gdy-news-item">
            <div class="gdy-news-icon">
              <svg class="gdy-icon text-white" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </div>
            <div>
              <div class="gdy-news-title">
                رسائل جديدة من الزوار
                <?php if ($stats['unread_messages'] > 0): ?>
                  <span class="badge bg-danger ms-1"><?= (int)$stats['unread_messages'] ?></span>
                <?php endif; ?>
              </div>
              <div class="gdy-news-meta">
                <?= h(__('t_1d036d5265', 'راجع رسائل نموذج "اتصل بنا" أولاً بأول لتحسين التفاعل مع القرّاء.')) ?>
                <a href="<?= h($adminBase) ?>/contact/" class="text-info text-decoration-none ms-1">
                  <?= h(__('t_262e3c1f97', 'الذهاب لصفحة الرسائل')) ?>
                  <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                </a>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- الفوتر -->
    <div class="gdy-admin-footer">
      <div class="row align-items-center">
        <div class="col-md-6 mb-2 mb-md-0">
          &copy; <?= date('Y') ?> Godyar News — نظام إدارة المحتوى المتقدم
        </div>
        <div class="col-md-6 text-md-end">
          <span class="me-2">
            <svg class="gdy-icon text-success" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <?= h(__('t_0cef8a4c1b', 'النظام يعمل بشكل طبيعي')) ?>
          </span>
          <span>
            <svg class="gdy-icon text-secondary" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            Godyar v3.1
          </span>
        </div>
      </div>
    </div>

  </div><!-- /.container-fluid -->
</div><!-- /.gdy-dashboard-wrapper -->

<script>
document.addEventListener('DOMContentLoaded', function() {
  // تحريك أشرطة الأداء
  const bars = document.querySelectorAll('.gdy-performance-fill');
  bars.forEach(bar => {
    const w = bar.style.width || '70%';
    bar.style.width = '0';
    setTimeout(() => { bar.style.width = w; }, 400);
  });

  // تحديث الوقت والتاريخ بالصندوق 2
  const dateSpan = document.querySelector('.gdy-date-value');
  const timeSpan = document.querySelector('.gdy-time-value');

  function updateDateTime() {
    const now = new Date();
    if (dateSpan) dateSpan.textContent = now.toLocaleDateString('ar-EG');
    if (timeSpan) timeSpan.textContent = now.toLocaleTimeString('ar-EG');
  }
  updateDateTime();
  setInterval(updateDateTime, 1000);

  // بحث في "آخر الأخبار"
  const recentSearch = document.getElementById('recent-news-search');
  const recentList   = document.getElementById('recent-news-list');
  if (recentSearch && recentList) {
    const items = recentList.querySelectorAll('.gdy-news-item');
    recentSearch.addEventListener('input', function() {
      const q = this.value.toLowerCase();
      items.forEach(function(item) {
        const titleEl = item.querySelector('.gdy-news-title');
        const text    = titleEl ? titleEl.textContent.toLowerCase() : '';
        item.style.display = (!q || text.indexOf(q) !== -1) ? '' : 'none';
      });
    });
  }

  // زر التحكم في إظهار/إخفاء القائمة الجانبية في الواجهة
  const sidebarGlobalBtn   = document.getElementById('gdy-sidebar-global-toggle');
  const sidebarGlobalLabel = sidebarGlobalBtn ? sidebarGlobalBtn.querySelector('.gdy-sidebar-global-label') : null;
  const SIDEBAR_KEY        = 'godyar_sidebar_visible'; // نفس المفتاح المستخدم في صفحة التصنيف

  function refreshSidebarLabel() {
    if (!sidebarGlobalBtn || !sidebarGlobalLabel) return;

    let value = localStorage.getItem(SIDEBAR_KEY);
    if (value === null) value = '1'; // افتراضي: القائمة ظاهرة

    if (value === '1') {
      sidebarGlobalLabel.textContent = 'إخفاء القائمة الجانبية في الواجهة';
      sidebarGlobalBtn.title = 'الحالة الحالية: القائمة الجانبية ظاهرة';
    } else {
      sidebarGlobalLabel.textContent = 'إظهار القائمة الجانبية في الواجهة';
      sidebarGlobalBtn.title = 'الحالة الحالية: القائمة الجانبية مخفية';
    }
  }

  if (sidebarGlobalBtn) {
    // ضبط النص عند تحميل لوحة التحكم
    refreshSidebarLabel();

    // عند الضغط على الزر
    sidebarGlobalBtn.addEventListener('click', function() {
      let value = localStorage.getItem(SIDEBAR_KEY);
      if (value === null) value = '1';
      const next = (value === '1') ? '0' : '1';
      localStorage.setItem(SIDEBAR_KEY, next);
      refreshSidebarLabel();
    });
  }
});
</script>

<?php
// فوتر القالب العام
if (is_file($footerPath)) {
    require $footerPath;
}
