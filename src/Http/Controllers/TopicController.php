<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\FrontendRenderer;
use App\Http\Presenters\SeoPresenter;
use Godyar\Services\TagService;
use PDO;
use Throwable;

final class TopicController
{
    private TagService $tags;
    private FrontendRenderer $view;
    private SeoPresenter $seo;
    private PDO $pdo;

    public function __construct(TagService $tags, FrontendRenderer $view, SeoPresenter $seo, PDO $pdo)
    {
        $this->tags = $tags;
        $this->view = $view;
        $this->seo = $seo;
        $this->pdo = $pdo;
        $this->ensureTopicMeta();
    }

    public function show(string $slug, int $page = 1): void
    {
        $page = max(1, $page);
        $slug = trim($slug);
        $tag = $this->tags->findBySlug($slug);

        if (!$tag || empty($tag['id'])) {
            http_response_code(404);
            $this->view->render('frontend/views/404.php', [], ['pageSeo' => ['title' => '404']]);
            return;
        }

        $list = $this->tags->listNews((int)$tag['id'], $page, 12);

        // topic meta (intro/cover)
        $meta = $this->topicMeta((int)$tag['id']);

        // best articles by views
        $best = $this->bestForTag((int)$tag['id'], 6);

        // latest updates (first page already contains latest but we keep a smaller strip)
        $latest = $this->bestForTag((int)$tag['id'], 10, false);

        ob_start();
        $this->view->render(
            'frontend/views/topic.php',
            [
                'tag' => $tag,
                'meta' => $meta,
                'best' => $best,
                'latest' => $latest,
                'items' => $list['items'],
                'page' => $page,
                'pages' => $list['total_pages'],
            ],
            [
                'pageSeo' => $this->seo->tag($tag),
            ]
        );
        $html = ob_get_clean();
        echo (string)$html;
    }

    private function ensureTopicMeta(): void
    {
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS tag_meta (
                tag_id INT UNSIGNED NOT NULL PRIMARY KEY,
                intro TEXT NULL,
                cover_path VARCHAR(255) NULL,
                updated_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Throwable $e) {
            // ignore
        }
    }

    private function topicMeta(int $tagId): array
    {
        try {
            $st = $this->pdo->prepare("SELECT intro, cover_path FROM tag_meta WHERE tag_id = :id LIMIT 1");
            $st->execute([':id'=>$tagId]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            return [
                'intro' => (string)($row['intro'] ?? ''),
                'cover_path' => (string)($row['cover_path'] ?? ''),
            ];
        } catch (Throwable $e) {
            return ['intro'=>'','cover_path'=>''];
        }
    }

    private function bestForTag(int $tagId, int $limit = 6, bool $byViews = true): array
    {
        $limit = max(1, min(30, $limit));
        try {
            $dateCol = 'id';
            // detect best date column
            $cols = $this->pdo->query("SHOW COLUMNS FROM news");
            if ($cols) {
                $names = array_map(fn($r) => (string)$r['Field'], $cols->fetchAll(PDO::FETCH_ASSOC) ?: []);
                if (in_array('publish_at', $names, true)) $dateCol = 'publish_at';
                elseif (in_array('published_at', $names, true)) $dateCol = 'published_at';
                elseif (in_array('created_at', $names, true)) $dateCol = 'created_at';
            }

            $order = $byViews ? "n.views DESC, n.id DESC" : "n.$dateCol DESC, n.id DESC";

            $sql = "SELECT n.id, n.title, n.slug, n.image, n.views, n.publish_at, n.published_at, n.created_at
                FROM news_tags nt
                JOIN news n ON n.id = nt.news_id
                WHERE nt.tag_id = :tid
                ORDER BY $order
                LIMIT $limit";
            $st = $this->pdo->prepare($sql);
            $st->execute([':tid'=>$tagId]);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}
