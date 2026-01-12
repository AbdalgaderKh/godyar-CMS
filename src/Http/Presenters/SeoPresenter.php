<?php
declare(strict_types=1);

namespace App\Http\Presenters;

final class SeoPresenter
{
    /** @var string */
    private string $basePrefix;

    public function __construct(string $basePrefix = '')
    {
        $this->basePrefix = rtrim($basePrefix, '/');
    }

    /**
     * @param array<string,mixed> $tag
     * @return array<string,mixed>
     */
    public function tag(array $tag): array
    {
        $name = (string)($tag['name'] ?? '');
        $desc = (string)($tag['description'] ?? '');
        if ($desc === '') {
            $desc = $name !== '' ? ('الأخبار المرتبطة بالوسم ' . $name) : 'الأخبار المرتبطة بالوسم.';
        }

        $slug = (string)($tag['slug'] ?? '');
        $url = $slug !== '' ? ($this->basePrefix . '/tag/' . rawurlencode($slug)) : '';

        return [
            'title' => $name !== '' ? ('الوسم: ' . $name) : 'وسم',
            'description' => $desc,
            'url' => $url,
            'type' => 'website',
            'rss'  => ($slug !== '' ? ($this->basePrefix . '/rss/tag/' . rawurlencode($slug) . '.xml') : ''),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function archive(?int $year = null, ?int $month = null): array
    {
        $title = 'الأرشيف';
        if ($year) {
            $title .= ' - ' . $year;
            if ($month) {
                $title .= '-' . str_pad((string)$month, 2, '0', STR_PAD_LEFT);
            }
        }
        $url = $this->basePrefix . '/archive';
        return [
            'title' => $title,
            'description' => 'أرشيف الأخبار حسب الفترات الزمنية.',
            'url' => $url,
            'type' => 'website',
            'rss'  => '',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function search(string $q): array
    {
        $q = trim($q);
        $title = $q !== '' ? ('نتائج البحث: ' . $q) : 'البحث';
        $desc = $q !== '' ? ('نتائج البحث عن ' . $q) : 'ابحث في محتوى الموقع.';
        $url = $this->basePrefix . '/search' . ($q !== '' ? ('?q=' . rawurlencode($q)) : '');
        return [
            'title' => $title,
            'description' => $desc,
            'url' => $url,
            'type' => 'website',
            'rss'  => '',
        ];
    }
}
