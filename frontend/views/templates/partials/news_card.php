<?php
// frontend/views/templates/partials/news_card.php

if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// base url
if (function_exists('base_url')) {
    $baseUrl = rtrim(base_url(), '/');
} else {
    $baseUrl = '';
}

// لو دالة بناء الصورة غير موجودة نعرّفها (نفس المستخدمة في الصفحات الأخرى)
if (!function_exists('build_image_url')) {
    function build_image_url(string $baseUrl, ?string $path): ?string
{
    $path = trim((string)$path);
    if ($path === '') {
        return null;
    }

    // رابط خارجي محفوظ في قاعدة البيانات
    if (preg_match('~^https?://~i', $path)) {
        return $path;
    }

    // baseUrl غالبًا يكون مثل: https://domain.com/ar أو https://domain.com/app/ar
    // صور/أصول لوحة التحكم عادة تكون من جذر التطبيق (بدون /ar)
    $rootBase = rtrim($baseUrl, '/');
    $rootBase = preg_replace('~/(ar|en)$~i', '', $rootBase);

    if ($path[0] === '/') {
        return $rootBase . $path;
    }

    return $rootBase . '/' . ltrim($path, '/');
}

// تجهيز البيانات
$id        = isset($row['id']) ? (int)$row['id'] : 0;
$slug      = isset($row['slug']) ? trim((string)$row['slug']) : '';
$title     = (string)($row['title'] ?? '');
$excerpt   = (string)($row['excerpt'] ?? '');
$dateRaw   = $row['created_at'] ?? ($row['published_at'] ?? null);
$date      = $dateRaw ? date('Y-m-d', strtotime((string)$dateRaw)) : '';
$views     = isset($row['views']) ? (int)$row['views'] : null;

// رابط الخبر
$newsUrl = '#';
if ($slug !== '') {
    $newsUrl = rtrim($baseUrl, '/') . '/news/id/' . (int)$id;
} elseif ($id > 0) {
    $newsUrl = rtrim($baseUrl, '/') . '/news/id/' . $id;
}

// تحضير الصورة:
// - لو featured_image موجود → نستخدمه كما هو إن كان URL كامل
// - وإلا نستخدم الحقول الأخرى ونبني الرابط
$imgRaw = $row['featured_image']
    ?? $row['image']
    ?? $row['thumbnail']
    ?? $row['photo']
    ?? $row['featured_image_orig']
    ?? $row['img']
    ?? null;

$img = null;
if (!empty($imgRaw)) {
    $imgRaw = trim((string)$imgRaw);
    if (preg_match('~^https?://~i', $imgRaw)) {
        // رابط كامل جاهز (مثلاً جاي من home.php)
        $img = $imgRaw;
    } else {
        $img = build_image_url($baseUrl, $imgRaw);
    }
}

// لو ما في مقتطف، نقتطع من العنوان
if ($excerpt === '' && $title !== '') {
    $excerpt = mb_substr($title, 0, 70, 'UTF-8');
    if (mb_strlen($title, 'UTF-8') > 70) {
        $excerpt .= '…';
    }
}

// كلاس إضافي للكرت (مثلاً للفيديو) إن وصل من الخارج
$extraClass = isset($cardExtraClass) ? (string)$cardExtraClass : '';
?>
<a href="<?= h($newsUrl) ?>" class="gdy-card <?= h($extraClass) ?>">
    <?php if (!empty($img)): ?>
        <div class="gdy-card-thumb">
            <img src="<?= h($img) ?>" alt="<?= h($title) ?>">
        </div>
    <?php endif; ?>

    <h3 class="gdy-card-title"><?= h($title) ?></h3>

    <?php if ($excerpt !== ''): ?>
        <p class="gdy-card-excerpt"><?= h($excerpt) ?></p>
    <?php endif; ?>

    <div class="gdy-card-meta">
        <?php if ($date): ?>
            <span>
                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                <?= h($date) ?>
            </span>
        <?php endif; ?>
        <?php if ($views !== null): ?>
            <span>
                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                <?= number_format($views) ?> مشاهدة
            </span>
        <?php endif; ?>
    </div>
</a>
