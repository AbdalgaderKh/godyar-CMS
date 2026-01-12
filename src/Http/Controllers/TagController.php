<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\FrontendRenderer;
use App\Http\Presenters\SeoPresenter;
use Godyar\Services\TagService;

final class TagController
{
    private TagService $tags;
    private FrontendRenderer $view;
    private SeoPresenter $seo;

    public function __construct(TagService $tags, FrontendRenderer $view, SeoPresenter $seo)
    {
        $this->tags = $tags;
        $this->view = $view;
        $this->seo  = $seo;
    }

    public function show(string $slug, int $page = 1): void
    {
        $slug = trim($slug);
        if ($slug === '') {
            http_response_code(404);
            echo 'الوسم غير موجود';
            exit;
        }

        $page = max(1, $page);
        $perPage = 12;

        // GDY_PAGE_CACHE_V8 — cache full rendered HTML for guests
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        $isLogged = isset($_SESSION['user']) && is_array($_SESSION['user']);
        $usePageCache = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && !$isLogged && class_exists('Cache');
        $pageCacheKey = 'page:tag:' . $slug . ':' . (int)$page;
        if ($usePageCache) {
            $cached = \Cache::get($pageCacheKey);
            if (is_string($cached) && $cached !== '') {
                header('X-Godyar-Cache: HIT');
                echo $cached;
                exit;
            }
        }

        $tag = $this->tags->findBySlug($slug);
        if (!$tag) {
            http_response_code(404);
            echo 'الوسم غير موجود';
            exit;
        }

        $list = $this->tags->listNews((int)($tag['id'] ?? 0), $page, $perPage);

        if ($usePageCache) {
            ob_start();
            $this->view->render(
                'frontend/views/tag.php',
                [
                    'tag' => $tag,
                    'items' => $list['items'],
                    'page' => $page,
                    'pages' => $list['total_pages'],
                ],
                [
                    'pageSeo' => $this->seo->tag($tag),
                ]
            );
            $html = ob_get_clean();
            if (is_string($html) && $html !== '') {
                \Cache::put($pageCacheKey, $html, 300);
                header('X-Godyar-Cache: MISS');
                echo $html;
                exit;
            }
        }

        $this->view->render(
            'frontend/views/tag.php',
            [
                'tag' => $tag,
                'items' => $list['items'],
                'page' => $page,
                'pages' => $list['total_pages'],
            ],
            [
                'pageSeo' => $this->seo->tag($tag),
            ]
        );
    }
}
