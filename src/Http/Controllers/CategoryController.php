<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use Godyar\Services\CategoryService;

final class CategoryController
{
    /** @var CategoryService */
    private CategoryService $categories;

    /** @var string */
    private string $basePrefix;

    public function __construct(CategoryService $categories, string $basePrefix = '')
    {
        $this->categories = $categories;
        $this->basePrefix = rtrim($basePrefix, '/');
    }

    public function show(string $slug, int $page = 1, string $sort = 'latest', string $period = 'all'): void
    {
        $slug = trim($slug, "/ \t\n\r\0\x0B");
        // منع أي محاولات لتمرير مسارات متعددة
        if ($slug === '' || strpos($slug, '/') !== false) {
            $this->renderMessage(404, 'القسم غير موجود', 'لم يتم تحديد اسم القسم في الرابط.');
        }


        // GDY_PAGE_CACHE_V8 — cache full rendered HTML for guests (category/tag)
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $isLogged = isset($_SESSION['user']) && is_array($_SESSION['user']);
        $usePageCache = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && !$isLogged && class_exists('Cache');
        $pageCacheKey = 'page:category:' . $slug . ':' . (int)$page . ':' . $sort . ':' . $period;
        if ($usePageCache) {
            $cached = \Cache::get($pageCacheKey);
            if (is_string($cached) && $cached !== '') {
                header('X-Godyar-Cache: HIT');
                echo $cached;
                exit;
            }
        }
        if ($slug === '') {
            $this->renderMessage(404, 'القسم غير موجود', 'لم يتم تحديد اسم القسم في الرابط.');
        }

        $category = $this->categories->findBySlug($slug);
        if (!$category) {
            $this->renderMessage(404, 'القسم غير موجود', 'هذا القسم غير موجود.');
        }

        // is_active=0 => Gone
        if (array_key_exists('is_active', $category) && $category['is_active'] !== null) {
            $active = (int)$category['is_active'];
            if ($active === 0) {
                $this->renderMessage(410, 'القسم غير متاح', 'هذا القسم غير نشط حالياً.');
            }
        }

        $categoryId = (int)($category['id'] ?? 0);
        if ($categoryId <= 0) {
            $this->renderMessage(404, 'القسم غير موجود', 'تعذر تحديد القسم.');
        }

        $perPage = 12;
        $result = $this->categories->listPublishedNews($categoryId, $page, $perPage, $sort, $period);

        $baseUrl = $this->baseUrl();

        $items = [];
        foreach (($result['items'] ?? []) as $row) {
            $id = (int)($row['id'] ?? 0);
            $newsSlug = (string)($row['slug'] ?? '');
            $url = $baseUrl . '/news/id/' . $id;

            $catMembersOnly = (int)($category['is_members_only'] ?? 0) === 1;
            $rowMembersOnly = (int)($row['is_members_only'] ?? 0) === 1;
            $isLocked = $catMembersOnly || $rowMembersOnly;

            $items[] = array_merge($row, [
                'url' => $url,
                'is_locked' => $isLocked ? 1 : 0,
            ]);
}

        $subcategories = $this->categories->subcategories($categoryId, 10);
        $parentId = isset($category['parent_id']) ? (int)$category['parent_id'] : null;
        if ($parentId === 0) {
            $parentId = null;
        }
        $siblingCategories = $this->categories->siblingCategories($parentId, $categoryId, 8);

        $categoryName = (string)($category['name'] ?? '');
        $categoryDescription = (string)($category['description'] ?? '');

        $currentCategoryUrl = $baseUrl . '/category/' . rawurlencode($slug);
        $canonicalUrl = $currentCategoryUrl . $this->canonicalQuery($page, $sort, $period);

        $viewData = [
            'category' => $category,
            'items' => $items,
            'subcategories' => $subcategories,
            'siblingCategories' => $siblingCategories,
            'totalItems' => (int)($result['total'] ?? 0),
            'itemsPerPage' => $perPage,
            'currentPage' => max(1, (int)$page),
            'pages' => (int)($result['total_pages'] ?? 1),
            'baseUrl' => $baseUrl,
            'homeUrl' => $baseUrl . '/',
            'currentCategoryUrl' => $currentCategoryUrl,
            'breadcrumbs' => [
                ['label' => 'الرئيسية', 'url' => $baseUrl . '/'],
                ['label' => $categoryName ?: $slug, 'url' => $currentCategoryUrl],
            ],
            'pageTitle' => $category['meta_title'] ?? (($categoryName ?: $slug) . ' - أخبار'),
            'metaDescription' => $category['meta_description']
                ?? ($categoryDescription !== '' ? $categoryDescription : 'أحدث الأخبار في قسم ' . ($categoryName ?: $slug)),
            'canonicalUrl' => $canonicalUrl,
            'rss' => $baseUrl . '/rss/category/' . rawurlencode((string)($category['slug'] ?? $slug)) . '.xml',
        ];

        $viewPath = dirname(__DIR__, 3) . '/frontend/views/category.php';
        if (is_file($viewPath)) {
            if (!empty($usePageCache)) {
                ob_start();
                extract($viewData, EXTR_SKIP);
                require $viewPath;
                $html = ob_get_clean();
                if (is_string($html) && $html !== '') {
                    \Cache::put($pageCacheKey, $html, 300);
                    header('X-Godyar-Cache: MISS');
                    echo $html;
                    exit;
                }
            }
            extract($viewData, EXTR_SKIP);
            require $viewPath;
            exit;
        }

        $this->renderMessage(500, 'خطأ', 'ملف العرض غير موجود.');
    }

    private function canonicalQuery(int $page, string $sort, string $period): string
    {
        $q = [];
        if ($page > 1) {
            $q['page'] = $page;
        }
        if ($sort !== 'latest') {
            $q['sort'] = $sort;
        }
        if ($period !== 'all') {
            $q['period'] = $period;
        }
        return $q ? ('?' . http_build_query($q)) : '';
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
