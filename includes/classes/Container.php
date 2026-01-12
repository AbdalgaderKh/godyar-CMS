<?php
declare(strict_types=1);

namespace Godyar;

use PDO;
use Godyar\Services\SettingsService;
use Godyar\Services\NewsService;
use Godyar\Services\CategoryService;
use Godyar\Services\TagService;
use Godyar\Services\AdService;

/**
 * Minimal DI Container
 *
 * الهدف: توفير Constructor Injection لخدمات المشروع الجديدة
 * مع الإبقاء على توافق الواجهات القديمة.
 */
final class Container
{
    private PDO $pdo;
    private ?SettingsService $settings = null;
    private ?NewsService $news = null;
    private ?CategoryService $categories = null;
    private ?TagService $tags = null;
    private ?AdService $ads = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function settings(): SettingsService
    {
        if ($this->settings === null) {
            $this->settings = new SettingsService($this->pdo);
        }
        return $this->settings;
    }

    public function news(): NewsService
    {
        if ($this->news === null) {
            $this->news = new NewsService($this->pdo);
        }
        return $this->news;
    }

    public function categories(): CategoryService
    {
        if ($this->categories === null) {
            $this->categories = new CategoryService($this->pdo);
        }
        return $this->categories;
    }

    public function tags(): TagService
    {
        if ($this->tags === null) {
            $this->tags = new TagService($this->pdo);
        }
        return $this->tags;
    }

    public function ads(): AdService
    {
        if ($this->ads === null) {
            $this->ads = new AdService($this->pdo);
        }
        return $this->ads;
    }
}
