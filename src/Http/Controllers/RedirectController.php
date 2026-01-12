<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use Godyar\Services\NewsService;
use Godyar\Services\CategoryService;

/**
 * RedirectController
 *
 * Step 15: مثال عملي على Constructor Injection داخل Controller.
 * - يُستخدم لتحويل id -> slug لمسارات news/category.
 */
final class RedirectController
{
    /** @var NewsService */
    private NewsService $news;

    /** @var CategoryService */
    private CategoryService $categories;

    /** @var string */
    private string $basePrefix;

    public function __construct(NewsService $news, CategoryService $categories, string $basePrefix = '')
    {
        $this->news = $news;
        $this->categories = $categories;
        $this->basePrefix = rtrim($basePrefix, '/');
    }

    public function newsIdToSlug(int $id): void
    {
        $slug = $this->news->slugById($id) ?? '';

        if ($slug === '') {
            http_response_code(404);
            echo 'Not Found';
            exit;
        }

        header('Location: ' . $this->basePrefix . '/news/id/' . (int)$id, true, 301);
        exit;
    }

    public function categoryIdToSlug(int $id): void
    {
        $slug = $this->categories->slugById($id) ?? '';

        if ($slug === '') {
            http_response_code(404);
            echo 'Not Found';
            exit;
        }

        header('Location: ' . $this->basePrefix . '/category/' . rawurlencode($slug), true, 301);
        exit;
    }
}
