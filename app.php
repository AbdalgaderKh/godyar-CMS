<?php
declare(strict_types=1);

/**
 * app.php — Front Controller (routing)
 *
 * • يعمل مع Apache/Nginx عبر rewrite (انظر .htaccess و deploy/nginx.conf.snippet)
 * • يوجّه المسارات إلى الكنترولرات الموجودة في frontend/controllers
 * • يحافظ على توافق الصفحات القديمة قدر الإمكان.
 */
// Step 17: Class NewsController + Services extraction
use App\Core\Router;
use App\Core\FrontendRenderer;
use App\Http\Presenters\SeoPresenter;
use App\Http\Controllers\RedirectController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\LegacyIncludeController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\Api\NewsExtrasController;

require_once __DIR__ . '/includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

if (!function_exists('godyar_route_base_prefix')) {
    /**
     * يحاول تحديد Prefix المشروع لو كان داخل مجلد فرعي (مثلاً /godyar)
     * بناءً على SCRIPT_NAME الخاص بـ app.php.
     */
    function godyar_route_base_prefix(): string
    {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = str_replace('\\', '/', dirname($script));
        if ($dir === '/' || $dir === '.' || $dir === '\\') {
            return '';
        }
        return rtrim($dir, '/');
    }
}

if (!function_exists('godyar_request_path')) {
    function godyar_request_path(): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') $path = '/';
        return $path;
    }
}

if (!function_exists('godyar_render_404')) {
    function godyar_render_404(): void
    {
        http_response_code(404);

        // حاول استخدام قالب الفرونت-اند إن وجد
        $header = __DIR__ . '/frontend/templates/header.php';
        $footer = __DIR__ . '/frontend/templates/footer.php';

        // قيم افتراضية للعنوان (قد يلتقطها الهيدر)
        $siteTitle = '404 - الصفحة غير موجودة';
        $siteDescription = 'الصفحة التي طلبتها غير موجودة.';

        if (is_file($header)) {
            require $header;
        }

        echo '<main class="container my-5">';
        echo '<h1 style="margin-bottom:12px;">الصفحة غير موجودة (404)</h1>';
        echo '<p>قد يكون الرابط غير صحيح أو تم نقل الصفحة.</p>';
        $home = rtrim((string)($GLOBALS['baseUrl'] ?? ''), '/');
        echo '<p><a href="' . htmlspecialchars($home ?: '/', ENT_QUOTES, 'UTF-8') . '">العودة للصفحة الرئيسية</a></p>';
        echo '</main>';

        if (is_file($footer)) {
            require $footer;
        }

        exit;
    }
}

// ---------------------------------------------------------
// Normalize path (remove base prefix if any)
// ---------------------------------------------------------
$basePrefix  = godyar_route_base_prefix();       // e.g. /godyar
$requestPath = godyar_request_path();            // e.g. /godyar/news/slug

if ($basePrefix !== '' && str_starts_with($requestPath, $basePrefix . '/')) {
    $requestPath = substr($requestPath, strlen($basePrefix));
}
$requestPath = '/' . ltrim($requestPath, '/');

// ---------------------------------------------------------
// 🔒 Legacy query param hardening: block LFI/Traversal via ?page=
// - Historically some links used /?page=about (string). This is dangerous if used for includes.
// - We now ONLY allow safe slugs and redirect them to the canonical /page/<slug> route.
// - Numeric values are allowed only for pagination purposes (and are kept as-is).
// ---------------------------------------------------------
if ($requestPath === '/' && isset($_GET['page'])) {
    $legacyPage = trim((string)$_GET['page']);

    // Empty => ignore
    if ($legacyPage === '') {
        unset($_GET['page']);
    } elseif (ctype_digit($legacyPage)) {
        // Keep numeric page (pagination) but normalize
        $_GET['page'] = (int)$legacyPage;
    } else {
        // Treat as legacy page slug (about/contact/privacy/...)
        $slug = rawurldecode($legacyPage);

        // Allow ONLY letters/numbers/_/- (no dots, no slashes, no traversal)
        if (!preg_match('/^[\p{L}\p{N}_-]{1,80}$/u', $slug)) {
            godyar_render_404();
            exit;
        }

        // Optional: confirm page exists (avoid redirecting to non-existent pages)
        $exists = false;
        try {
            if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
                $stmt = $GLOBALS['pdo']->prepare("SELECT 1 FROM pages WHERE slug = :slug LIMIT 1");
                $stmt->execute([':slug' => $slug]);
                $exists = (bool)$stmt->fetchColumn();
            }
        } catch (Throwable $e) {
            // If DB check fails, don't risk exposing info; fall back to redirect attempt
            $exists = true;
        }

        if ($exists) {
            $prefix = rtrim($basePrefix, '/');
            header('Location: ' . ($prefix === '' ? '' : $prefix) . '/page/' . rawurlencode($slug), true, 301);
            exit;
        }

        godyar_render_404();
        exit;
    }
}

// ---------------------------------------------------------
// Social OAuth endpoints (front-end)
// ---------------------------------------------------------
if ($requestPath === '/oauth/github') {
    require __DIR__ . '/oauth/github.php';
    exit;
}
if ($requestPath === '/oauth/github/callback') {
    require __DIR__ . '/oauth/github_callback.php';
    exit;
}

if ($requestPath === '/oauth/google') {
    require __DIR__ . '/oauth/google.php';
    exit;
}
if ($requestPath === '/oauth/google/callback') {
    require __DIR__ . '/oauth/google_callback.php';
    exit;
}

if ($requestPath === '/oauth/facebook') {
    require __DIR__ . '/oauth/facebook.php';
    exit;
}
if ($requestPath === '/oauth/facebook/callback') {
    require __DIR__ . '/oauth/facebook_callback.php';
    exit;
}

// ---------------------------------------------------------
// Homepage support (for /en , /fr , /ar after lang_prefix strips prefix)
// ---------------------------------------------------------
if ($requestPath === '/' || $requestPath === '') {
    // Render the same homepage flow used by /index.php
    require __DIR__ . '/index.php';
    exit;
}

// ---------------------------------------------------------
// Legacy endpoints handling (when legacy PHP files are removed)
// ---------------------------------------------------------
if (in_array($requestPath, ['/article.php', '/category.php', '/page.php', '/archive.php', '/trending.php'], true)) {
    $base = rtrim(godyar_route_base_prefix(), '/');
    $qs   = (string)($_SERVER['QUERY_STRING'] ?? '');

    if ($requestPath === '/article.php') {
        $preview = isset($_GET['preview']) && (string)$_GET['preview'] === '1';

        if ($preview) {
            if (isset($_GET['id']) && ctype_digit((string)$_GET['id'])) {
                $id = (int)$_GET['id'];
                header('Location: ' . $base . '/preview/news/' . $id, true, 302);
                exit;
            }
            if (!empty($_GET['slug'])) {
                $slug = (string)$_GET['slug'];
                header('Location: ' . $base . '/news/id/' . (int)$id . '?preview=1', true, 302);
                exit;
            }
            http_response_code(410);
            echo 'Gone';
            exit;
        }

        if (!empty($_GET['slug'])) {
            $slug = (string)$_GET['slug'];
            header('Location: ' . $base . '/news/id/' . (int)$id . ($qs !== '' ? ('?' . $qs) : ''), true, 301);
            exit;
        }
        if (isset($_GET['id']) && ctype_digit((string)$_GET['id'])) {
            $id = (int)$_GET['id'];
            header('Location: ' . $base . '/news/id/' . $id . ($qs !== '' ? ('?' . $qs) : ''), true, 301);
            exit;
        }

        http_response_code(410);
        echo 'Gone';
        exit;
    }

    if ($requestPath === '/category.php') {
        if (!empty($_GET['slug'])) {
            $slug = (string)$_GET['slug'];
            if (!empty($_GET['page']) && ctype_digit((string)$_GET['page'])) {
                $page = (int)$_GET['page'];
                header('Location: ' . $base . '/category/' . rawurlencode($slug) . '/page/' . $page . ($qs !== '' ? ('?' . $qs) : ''), true, 301);
                exit;
            }
            header('Location: ' . $base . '/category/' . rawurlencode($slug) . ($qs !== '' ? ('?' . $qs) : ''), true, 301);
            exit;
        }
        if (isset($_GET['id']) && ctype_digit((string)$_GET['id'])) {
            $id = (int)$_GET['id'];
            header('Location: ' . $base . '/category/id/' . $id . ($qs !== '' ? ('?' . $qs) : ''), true, 301);
            exit;
        }

        http_response_code(410);
        echo 'Gone';
        exit;
    }

    if ($requestPath === '/page.php') {
        if (!empty($_GET['slug'])) {
            $slug = (string)$_GET['slug'];
            header('Location: ' . $base . '/page/' . rawurlencode($slug) . ($qs !== '' ? ('?' . $qs) : ''), true, 301);
            exit;
        }
        http_response_code(410);
        echo 'Gone';
        exit;
    }

    if ($requestPath === '/archive.php') {
        header('Location: ' . $base . '/archive' . ($qs !== '' ? ('?' . $qs) : ''), true, 301);
        exit;
    }

    if ($requestPath === '/trending.php') {
        header('Location: ' . $base . '/trending' . ($qs !== '' ? ('?' . $qs) : ''), true, 301);
        exit;
    }
}


// ---------------------------------------------------------
// Routing table (مرحلة 1)
// ---------------------------------------------------------

// Shared instances
$container = $GLOBALS['container'] ?? null;
if (!$container instanceof \Godyar\Container) {
    $container = new \Godyar\Container(\Godyar\DB::pdo());
}

$redirectController = new RedirectController(
    $container->news(),
    $container->categories(),
    godyar_route_base_prefix()
);

$categoryController = new CategoryController(
    $container->categories(),
    godyar_route_base_prefix()
);

$newsController = new NewsController(
    $container->pdo(),
    $container->news(),
    $container->categories(),
    $container->tags(),
    $container->ads(),
    godyar_route_base_prefix()
);

$basePrefix = godyar_route_base_prefix();
$renderer = new FrontendRenderer(__DIR__, $basePrefix);
$seo = new SeoPresenter($basePrefix);

$tagController = new TagController($container->tags(), $renderer, $seo);
$archiveController = new ArchiveController($container->news(), $renderer, $seo);
$searchController = new SearchController($container->news(), $container->categories(), $seo, __DIR__, $basePrefix);

$topicController = new TopicController($container->tags(), $renderer, $seo, $container->pdo());
$extrasApi = new NewsExtrasController($container->pdo(), $container->news(), $container->tags(), $container->categories());

$legacy = new LegacyIncludeController(__DIR__);
$router = new Router();


// SEO endpoints
$router->get('#^/sitemap\.xml$#', function () : void { require __DIR__ . '/seo/sitemap.php'; });
$router->get('#^/rss\.xml$#', function () : void { require __DIR__ . '/seo/rss.php'; });
// RSS per category/tag
$router->get('#^/rss/category/([^/]+)\.xml$#', function (array $m) : void { $_GET['slug']=rawurldecode((string)$m[1]); require __DIR__ . '/seo/rss_category.php'; });
$router->get('#^/rss/category/([^/]+)/?$#', function (array $m) : void { $_GET['slug']=rawurldecode((string)$m[1]); require __DIR__ . '/seo/rss_category.php'; });
$router->get('#^/rss/tag/([^/]+)\.xml$#', function (array $m) : void { $_GET['slug']=rawurldecode((string)$m[1]); require __DIR__ . '/seo/rss_tag.php'; });
$router->get('#^/rss/tag/([^/]+)/?$#', function (array $m) : void { $_GET['slug']=rawurldecode((string)$m[1]); require __DIR__ . '/seo/rss_tag.php'; });

$router->get('#^/og/news/([0-9]+)\.png$#', function (array $m) : void {
    $_GET['id'] = (int)$m[1];
    require __DIR__ . '/og_news.php';
});
// /category/{slug}[/page/{n}]
$router->get('#^/category/([^/]+)/page/([0-9]+)/?$#', function (array $m) use ($categoryController): void {
    $sort = isset($_GET['sort']) ? (string)$_GET['sort'] : 'latest';
    $period = isset($_GET['period']) ? (string)$_GET['period'] : 'all';
    $categoryController->show(rawurldecode((string)$m[1]), (int)$m[2], $sort, $period);
});
$router->get('#^/category/([^/]+)/?$#', function (array $m) use ($categoryController): void {
    $sort = isset($_GET['sort']) ? (string)$_GET['sort'] : 'latest';
    $period = isset($_GET['period']) ? (string)$_GET['period'] : 'all';
    $categoryController->show(rawurldecode((string)$m[1]), 1, $sort, $period);
});

// /news/print/{id} — صفحة طباعة/ PDF (GDY v8)
$router->get('#^/news/print/([0-9]+)/?$#', function (array $m) use ($newsController): void { $newsController->print((int)$m[1]); });
$router->get('#^/news/pdf/([0-9]+)/?$#', function (array $m) use ($newsController): void { $newsController->print((int)$m[1]); });

// /news/id/{id} — العرض الأساسي بالـ id (بدون تحويل إلى slug)
$router->get('#^/news/id/([0-9]+)/?$#', function (array $m) use ($newsController): void {
    $id = (int)$m[1];
    $newsController->show((string)$id, false);
});

// /category/id/{id} — تحويل ID إلى slug
$router->get('#^/category/id/([0-9]+)/?$#', fn(array $m) => $redirectController->categoryIdToSlug((int)$m[1]));

// /preview/news/{id} (Admin preview only)
$router->get('#^/preview/news/([0-9]+)/?$#', fn(array $m) => $newsController->preview((int)$m[1]));

// /news/{slug} و /article/{slug} (alias) — نحول إلى رابط الـ id لتفادي الروابط الطويلة
$router->get('#^/(?:news|article)/([^/]+)/?$#', function (array $m) use ($container, $newsController): void {
    $slug = rawurldecode((string)$m[1]);
    $id = $container->news()->idBySlug($slug);
    if ($id !== null && $id > 0) {
        $prefix = rtrim(godyar_route_base_prefix(), '/');
        header('Location: ' . $prefix . '/news/id/' . $id, true, 301);
        exit;
    }
    // fallback (يعطي رسالة "غير موجود" بنفس قالب الموقع)
    $newsController->show($slug, false);
});

// /page/{slug}
$router->get('#^/page/([^/]+)/?$#', fn(array $m) => $legacy->include('frontend/controllers/PageController.php', [
    'slug' => rawurldecode((string)$m[1]),
]));

// /tag/{slug}[/page/{n}]

// /topic/{slug} — صفحة موضوعات غنية (مثل tags ولكن بواجهة محسنة)
$router->get('#^/topic/([^/]+)/page/([0-9]+)/?$#', function (array $m) use ($topicController): void {
    $topicController->show(urldecode((string)$m[1]), (int)$m[2]);
});
$router->get('#^/topic/([^/]+)/?$#', function (array $m) use ($topicController): void {
    $topicController->show(urldecode((string)$m[1]), 1);
});

$router->get('#^/tag/([^/]+)/page/([0-9]+)/?$#', fn(array $m) => $tagController->show(rawurldecode((string)$m[1]), (int)$m[2]));
$router->get('#^/tag/([^/]+)/?$#', fn(array $m) => $tagController->show(rawurldecode((string)$m[1]), (int)($_GET['page'] ?? 1)));

// /trending
$router->get('#^/trending/?$#', fn() => $legacy->include('frontend/controllers/TrendingController.php'));



// ---------------------------------------------------------
// Auth (support /ar/login, /ar/profile, ... after lang prefix strip)
// ---------------------------------------------------------
$router->get('#^/login/?$#', fn() => $legacy->include('login.php'));
$router->get('#^/register/?$#', fn() => $legacy->include('register.php'));
$router->get('#^/profile/?$#', fn() => $legacy->include('profile.php'));
$router->get('#^/logout/?$#', fn() => $legacy->include('logout.php'));
$router->get('#^/my/?$#', fn() => $legacy->include('my.php'));

// ---------------------------------------------------------
// /categories (list all categories)
// ---------------------------------------------------------
$router->get('#^/categories/?$#', fn() => $legacy->include('categories_list.php'));


// /saved (bookmarks)
$router->get('#^/saved/?$#', fn() => $legacy->include('saved.php'));
// /archive (supports optional year/month and /page/N)
$router->get('#^/archive/page/([0-9]+)/?$#', fn(array $m) => $archiveController->index((int)$m[1]));
$router->get('#^/archive/([0-9]{4})/page/([0-9]+)/?$#', fn(array $m) => $archiveController->index((int)$m[2], (int)$m[1], null));
$router->get('#^/archive/([0-9]{4})/([0-9]{1,2})/page/([0-9]+)/?$#', fn(array $m) => $archiveController->index((int)$m[3], (int)$m[1], (int)$m[2]));
$router->get('#^/archive/([0-9]{4})/([0-9]{1,2})/?$#', fn(array $m) => $archiveController->index(1, (int)$m[1], (int)$m[2]));
$router->get('#^/archive/([0-9]{4})/?$#', fn(array $m) => $archiveController->index(1, (int)$m[1], null));
$router->get('#^/archive/?$#', fn() => $archiveController->index( (int)($_GET['page'] ?? 1) ));

// /search?q=...

// ---------------------------------------------------------
// API (ميزات إضافية)
// ---------------------------------------------------------
$router->get('#^/api/capabilities/?$#', fn() => $extrasApi->capabilities());

// bookmarks (saved)
$router->get('#^/api/bookmarks/list/?$#', fn() => $extrasApi->bookmarksList());
$router->get('#^/api/bookmarks/status/?$#', fn() => $extrasApi->bookmarkStatus());
$router->get('#^/api/bookmarks/toggle/?$#', fn() => $extrasApi->bookmarksToggle());
$router->get('#^/api/bookmarks/import/?$#', fn() => $extrasApi->bookmarksImport());

// reactions
$router->get('#^/api/news/reactions/?$#', fn() => $extrasApi->reactions());
$router->get('#^/api/news/react/?$#', fn() => $extrasApi->react());

// polls
$router->get('#^/api/news/poll/?$#', fn() => $extrasApi->poll());
$router->get('#^/api/news/poll/vote/?$#', fn() => $extrasApi->pollVote());

// Q&A
$router->get('#^/api/news/questions/?$#', fn() => $extrasApi->questions());
$router->get('#^/api/news/ask/?$#', fn() => $extrasApi->ask());

// Translation + TTS
$router->get('#^/api/news/tts/?$#', fn() => $extrasApi->tts());

// Search suggestions
$router->get('#^/api/search/suggest/?$#', fn() => $extrasApi->suggest());

// PWA helpers
$router->get('#^/api/latest/?$#', fn() => $extrasApi->latest());

// Push subscriptions (POST)
$router->post('#^/api/push/subscribe/?$#', fn() => $extrasApi->pushSubscribe());
$router->post('#^/api/push/unsubscribe/?$#', fn() => $extrasApi->pushUnsubscribe());

$router->get('#^/search/?$#', fn() => $searchController->index());

// /api/newsletter/subscribe  (POST) — حفظ بريد النشرة البريدية
$router->post('#^/api/newsletter/subscribe/?$#', function () use ($pdo): void {
    // CSRF (browser form) — if token provided, verify
    if (!empty($_POST['csrf_token']) && function_exists('csrf_verify_or_die')) { csrf_verify_or_die(); }

    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
        return;
    }

    // Accept form-encoded OR JSON
    $email = '';
    if (!empty($_POST['newsletter_email'])) {
        $email = trim((string)$_POST['newsletter_email']);
    } else {
        $raw = (string)file_get_contents('php://input');
        if ($raw !== '') {
            $j = json_decode($raw, true);
            if (is_array($j) && !empty($j['newsletter_email'])) {
                $email = trim((string)$j['newsletter_email']);
            } elseif (is_array($j) && !empty($j['email'])) {
                $email = trim((string)$j['email']);
            }
        }
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'البريد الإلكتروني غير صحيح'], JSON_UNESCAPED_UNICODE);
        return;
    }

    // Create table if missing (safe on shared hosting)
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS newsletter_subscribers (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              email VARCHAR(190) NOT NULL,
              status VARCHAR(20) NOT NULL DEFAULT 'active',
              lang VARCHAR(10) NULL,
              ip VARCHAR(45) NULL,
              ua VARCHAR(255) NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME NULL,
              UNIQUE KEY uniq_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (Throwable $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'خطأ في قاعدة البيانات'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $lang = '';
    if (!empty($_COOKIE['lang'])) $lang = (string)$_COOKIE['lang'];
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO newsletter_subscribers (email, status, lang, ip, ua, created_at, updated_at)
            VALUES (:email, 'active', :lang, :ip, :ua, NOW(), NOW())
            ON DUPLICATE KEY UPDATE status='active', lang=VALUES(lang), ip=VALUES(ip), ua=VALUES(ua), updated_at=NOW()
        ");
        $stmt->execute([
            ':email' => $email,
            ':lang'  => $lang ?: null,
            ':ip'    => $ip ?: null,
            ':ua'    => $ua ?: null,
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'تعذر حفظ الاشتراك'], JSON_UNESCAPED_UNICODE);
        return;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'message' => 'تم الاشتراك بنجاح ✅'], JSON_UNESCAPED_UNICODE);
});


if ($router->dispatch($requestPath)) {
    exit;
}

// Fallback: لو وصلنا هنا فالمسار غير معروف.
godyar_render_404();