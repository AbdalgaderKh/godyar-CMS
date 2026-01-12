<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use Godyar\Services\NewsService;
use Godyar\Services\CategoryService;
use Godyar\Services\TagService;
use Godyar\Services\AdService;
use PDO;

final class NewsController
{
    /** @var PDO */
    private PDO $pdo;
    /** @var NewsService */
    private NewsService $news;
    /** @var CategoryService */
    private CategoryService $categories;
    /** @var TagService */
    private TagService $tags;
    /** @var AdService */
    private AdService $ads;
    /** @var string */
    private string $basePrefix;

    public function __construct(PDO $pdo, NewsService $news, CategoryService $categories, TagService $tags, AdService $ads, string $basePrefix = '')
    {
        $this->pdo = $pdo;
        $this->news = $news;
        $this->categories = $categories;
        $this->tags = $tags;
        $this->ads = $ads;
        $this->basePrefix = rtrim($basePrefix, '/');
    }

    public function show(string $slug, bool $forcePreview = false): void
    {
        $slug = trim($slug);
        if ($slug === '') {
            $this->renderMessage(404, 'الخبر غير موجود', 'لم يتم تحديد الخبر.');
        }

        // Support ?preview=1 (legacy) in addition to /preview/news/{id}
        $isPreview = $forcePreview || ((string)($_GET['preview'] ?? '') === '1');

        // Admin-only preview
        if ($isPreview && !$this->isAdmin()) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }

        $root = dirname(__DIR__, 3);
        require_once $root . '/includes/TemplateEngine.php';
        require_once $root . '/includes/site_settings.php';

        // Settings + frontend options
        $settings = function_exists('gdy_load_settings') ? gdy_load_settings($this->pdo) : [];
        $frontendOptions = function_exists('gdy_prepare_frontend_options') ? gdy_prepare_frontend_options($settings) : [];
        if (is_array($frontendOptions)) {
            extract($frontendOptions, EXTR_OVERWRITE);
        }

        $baseUrl = rtrim($this->baseUrl(), '/');
        $headerCategories = $this->categories->headerCategories(6);

        $cacheKey = 'news_show_slug_' . sha1($slug);
        $bundle = null;

        $useCache = class_exists('Cache') && !$isPreview;
        if ($useCache) {
            $bundle = \Cache::remember($cacheKey, 300, function () use ($slug) {
                $news = $this->news->findBySlugOrId($slug, false);
                if (!$news) {
                    return ['news' => null, 'related' => [], 'tags' => [], 'latest' => [], 'mostRead' => []];
                }

                $categoryId = (int)($news['category_id'] ?? 0);
                $newsId = (int)($news['id'] ?? 0);

                return [
                    'news' => $news,
                    'related' => $this->news->relatedByCategory($categoryId, $newsId, 6, false),
                    'tags' => $this->tags->forNews($newsId),
                    'latest' => $this->news->latest(6),
                    'mostRead' => $this->news->mostRead(6),
                ];
            });
        }

        if (!is_array($bundle)) {
            $news = $this->news->findBySlugOrId($slug, $isPreview);
            if (!$news) {
                $this->renderMessage(404, 'الخبر غير موجود', 'الخبر غير موجود أو غير منشور.');
            }

            $categoryId = (int)($news['category_id'] ?? 0);
            $newsId = (int)($news['id'] ?? 0);

            $bundle = [
                'news' => $news,
                'related' => $this->news->relatedByCategory($categoryId, $newsId, 6, $isPreview),
                'tags' => $this->tags->forNews($newsId),
                'latest' => $this->news->latest(6),
                'mostRead' => $this->news->mostRead(6),
            ];
        }

        $news = $bundle['news'] ?? null;
        if (!$news) {
            $this->renderMessage(404, 'الخبر غير موجود', 'الخبر غير موجود أو غير منشور.');
        }

        // ---------------------------------------------------------
        // Normalize fields across schema variants (content/body, image/featured_image, summary/excerpt)
        // ---------------------------------------------------------
        if (is_array($news)) {
            if ((!isset($news['body']) || trim((string)$news['body']) === '') && isset($news['content'])) {
                $news['body'] = (string)$news['content'];
            }
            if ((!isset($news['content']) || trim((string)$news['content']) === '') && isset($news['body'])) {
                $news['content'] = (string)$news['body'];
            }
            if ((empty($news['excerpt'] ?? '') || trim((string)($news['excerpt'] ?? '')) === '') && isset($news['summary'])) {
                $news['excerpt'] = (string)$news['summary'];
            }
            if ((empty($news['featured_image'] ?? '') || trim((string)($news['featured_image'] ?? '')) === '') && isset($news['image'])) {
                $news['featured_image'] = (string)$news['image'];
            }
        }

        // Increment views per request (fixes the cache miss-only increment bug)
        if (!$isPreview) {
            $this->news->incrementViews((int)($news['id'] ?? 0));
        }

        $readingTime = $this->calculateReadingTime((string)($news['content'] ?? $news['body'] ?? ''));
        if (is_array($news) && (empty($news['read_time'] ?? 0) || (int)($news['read_time'] ?? 0) <= 0)) {
            $news['read_time'] = $readingTime;
        }
        $articleUrlFull = $baseUrl . '/news/id/' . (int)($news['id'] ?? 0);

$category = null;
        $categoryId = (int)($news['category_id'] ?? 0);
        if ($categoryId > 0) {
            $category = $this->categories->findById($categoryId);
        }
        // fallback (older schemas / join-only)
        if (!$category && (!empty($news['category_name']) || !empty($news['category_slug']))) {
            $category = [
                'id'   => $categoryId,
                'name' => (string)($news['category_name'] ?? ''),
                'slug' => (string)($news['category_slug'] ?? ''),
                'is_members_only' => 0,
            ];
        }

        $newsMembersOnly = (int)($news['is_members_only'] ?? 0) === 1;
        $catMembersOnly  = (int)($category['is_members_only'] ?? 0) === 1;
        $membersOnly     = $newsMembersOnly || $catMembersOnly;

        // Guests will see list + lock badge, but article content becomes paywalled
        $canReadFull = $this->isLoggedIn() || $this->isAdmin() || $isPreview;
$templateData = [
            // Global (like HomeController)
            'siteName' => $siteName ?? '',
            'siteTagline' => $siteTagline ?? '',
            'siteLogo' => $siteLogo ?? '',
            'primaryColor' => $primaryColor ?? '',
            'primaryDark' => $primaryDark ?? '',
            'baseUrl' => $baseUrl,
            'themeClass' => $themeClass ?? '',
            'searchPlaceholder' => $searchPlaceholder ?? '',
            'headerCategories' => $headerCategories,
            'isLoggedIn' => $this->isLoggedIn(),
            'isAdmin' => $this->isAdmin(),
                        'isPreview' => $isPreview,
            'membersOnly' => $membersOnly,
            'canReadFull' => $canReadFull,
'showCarbonBadge' => $showCarbonBadge ?? false,
            'carbonBadgeText' => $carbonBadgeText ?? '',

            // News
            'news' => $news,
            'category' => $category,
            'tags' => $bundle['tags'] ?? [],
            'articleUrlFull' => $articleUrlFull,
            'readingTime' => $readingTime,

            // Sidebars
            'related' => $bundle['related'] ?? [],
            'latestNews' => $bundle['latest'] ?? [],
            'mostReadNews' => $bundle['mostRead'] ?? [],

            // Ads
            'contentTopAd' => $this->ads->render('content_top', $baseUrl),
            'contentBottomAd' => $this->ads->render('content_bottom', $baseUrl),
            'sidebarTopAd' => $this->ads->render('sidebar_top', $baseUrl),
            'sidebarBottomAd' => $this->ads->render('sidebar_bottom', $baseUrl),
        ];

        $template = new \TemplateEngine();
        $viewPath = $root . '/frontend/views/news_detail.php';
        if (!is_file($viewPath)) {
            $this->renderMessage(500, 'خطأ', 'ملف العرض غير موجود.');
        }

        $template->render($viewPath, $templateData);
        exit;
    }

    
    public function print(int $newsId): void
    {
        if ($newsId <= 0) {
            $this->renderMessage(404, 'الخبر غير موجود', 'تعذر تحديد الخبر.');
        }

        $root = dirname(__DIR__, 3);

        // Load site settings helpers (print view uses branding/settings)
        require_once $root . '/includes/site_settings.php';

        $news = $this->news->findBySlugOrId((string)$newsId, false);
        if (!$news) {
            $this->renderMessage(404, 'الخبر غير موجود', 'لم يتم العثور على الخبر.');
        }

        $baseUrl = $this->baseUrl();

        // Ensure $GLOBALS['site_settings'] exists for templates that expect it (print/PDF, SEO helpers...)
        // The project historically stores settings with both dotted keys (site.name) and underscored keys (site_name).
        try {
            $raw = function_exists('gdy_load_settings') ? gdy_load_settings($this->pdo) : [];
            if (is_array($raw)) {
                $siteSettings = [
                    'site_name'           => $raw['site_name']    ?? $raw['site.name']    ?? 'Godyar News',
                    'site_desc'           => $raw['site_desc']    ?? $raw['site.desc']    ?? ($raw['site_description'] ?? 'منصة إخبارية متكاملة'),
                    'site_logo'           => $raw['site_logo']    ?? $raw['site.logo']    ?? '',
                    'site_url'            => $raw['site_url']     ?? $raw['site.url']     ?? (string)rtrim((string)$baseUrl, '/'),
                    'site_locale'         => $raw['site_locale']  ?? $raw['site.locale']  ?? 'ar',
                    'site_timezone'       => $raw['site_timezone']?? $raw['site.timezone']?? 'Asia/Riyadh',
                    'theme_primary'       => $raw['theme_primary']?? $raw['theme.primary']?? '#111111',
                    'theme_primary_dark'  => $raw['theme_primary_dark'] ?? $raw['theme.primary_dark'] ?? '#0369a1',
                    'layout_sidebar_mode' => $raw['layout_sidebar_mode'] ?? $raw['layout.sidebar_mode'] ?? 'visible',
                ];

                if (!isset($GLOBALS['site_settings']) || !is_array($GLOBALS['site_settings'])) {
                    $GLOBALS['site_settings'] = [];
                }
                $GLOBALS['site_settings'] = array_merge($GLOBALS['site_settings'], $siteSettings);
                if (!isset($GLOBALS['baseUrl'])) {
                    $GLOBALS['baseUrl'] = rtrim((string)$baseUrl, '/');
                }
            }
        } catch (\Throwable $e) {
            // ignore settings load failures in print mode
        }
        $articleUrlFull = rtrim((string)$baseUrl, '/') . '/news/id/' . (int)($news['id'] ?? 0);

        $category = null;
        try {
            $categoryId = (int)($news['category_id'] ?? 0);
            if ($categoryId > 0) {
                $cat = $this->categories->findById($categoryId);
                if ($cat) {
                    $category = [
                        'name' => (string)($cat['name'] ?? ''),
                        'slug' => (string)($cat['slug'] ?? ''),
                    ];
                }
            }
        } catch (\Throwable $e) {
            $category = null;
        }

        // IMPORTANT: Print/PDF page must be rendered WITHOUT the global header/footer.
        // The legacy TemplateEngine always wraps views with the site header/footer, which pollutes print output.
        // لذلك نُدرج ملف الطباعة مباشرةً.
        $viewPath = $root . '/frontend/views/news_print.php';
        if (!is_file($viewPath)) {
            $this->renderMessage(500, 'خطأ', 'ملف الطباعة غير موجود.');
        }

        $data = [
            'post' => $news,
            'baseUrl' => $baseUrl,
            'articleUrlFull' => $articleUrlFull,
            'category' => $category,
        ];
        extract($data, EXTR_SKIP);
        require $viewPath;
        exit;
    }

public function preview(int $newsId): void
    {
        if ($newsId <= 0) {
            $this->renderMessage(404, 'الخبر غير موجود', 'تعذر تحديد الخبر.');
        }

        // Preview must be admin
        if (!$this->isAdmin()) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }

        // Allow preview by id (without forcing redirects)
        $_GET['preview'] = '1';
        $this->show((string)$newsId, true);
    }

    private function calculateReadingTime(string $content): int
    {
        $plain = trim(strip_tags($content));
        if ($plain === '') {
            return 1;
        }
        $chars = mb_strlen($plain, 'UTF-8');
        return max(1, (int)ceil($chars / 800));
    }

    private function isLoggedIn(): bool
    {
        $currentUser = $_SESSION['user'] ?? null;
        return is_array($currentUser)
            && !empty($currentUser['role'])
            && $currentUser['role'] !== 'guest'
            && (($currentUser['status'] ?? 'active') === 'active');
    }

    private function isAdmin(): bool
    {
        $currentUser = $_SESSION['user'] ?? null;
        if (!is_array($currentUser)) {
            return false;
        }
        if (($currentUser['role'] ?? '') === 'admin') {
            return true;
        }
        return (int)($currentUser['is_admin'] ?? 0) === 1;
    }

    private function baseUrl(): string
    {
        if (function_exists('base_url')) {
            $b = rtrim((string)base_url(), '/');
            if ($b !== '') {
                return $b;
            }
        }

        if (defined('BASE_URL')) {
            $b = rtrim((string)BASE_URL, '/');
            if ($b !== '') {
                return $b;
            }
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return rtrim($scheme . '://' . $host . $this->basePrefix, '/');
    }

    private function renderMessage(int $code, string $title, string $message): void
    {
        http_response_code($code);
        echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>'
            . '<link href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css" rel="stylesheet">'
            . '</head><body class="bg-dark text-light"><main class="container py-5">'
            . '<div class="alert alert-info rounded-3 shadow-sm bg-opacity-75">'
            . '<h1 class="h4 mb-2">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>'
            . '<p class="mb-0">' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p>'
            . '</div></main></body></html>';
        exit;
    }
}
