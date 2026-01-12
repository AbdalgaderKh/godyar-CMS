<?php
// /godyar/frontend/views/trending.php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

$pdo = gdy_pdo_safe();

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// تحميل الإعدادات والمتغيرات المطلوبة للهيدر والفوتر
$settings = [];
try {
    if ($pdo instanceof PDO) {
        $stmt = $pdo->query("SELECT `key`, `value` FROM settings");
        foreach ($stmt as $row) {
            $settings[$row['key']] = $row['value'];
        }
    }
} catch (Throwable $e) {
    @error_log('[Trending] settings load error: ' . $e->getMessage());
}

function setting(array $settings, string $key, $default = ''): string {
    return isset($settings[$key]) && $settings[$key] !== ''
        ? (string)$settings[$key]
        : (string)$default;
}

// قيم افتراضية لو ما في إعدادات
$siteName     = setting($settings, 'site_name', 'Godyar News');
$siteTagline  = setting($settings, 'site_tagline', 'منصة إخبارية متكاملة');
$siteLogo     = setting($settings, 'site_logo', '');
$primaryColor = setting($settings, 'primary_color', '#0ea5e9');

// حساب لون داكن من اللون الأساسي
$primaryHex = ltrim($primaryColor, '#');
if (strlen($primaryHex) === 6) {
    $r = max(0, hexdec(substr($primaryHex, 0, 2)) - 30);
    $g = max(0, hexdec(substr($primaryHex, 2, 2)) - 30);
    $b = max(0, hexdec(substr($primaryHex, 4, 2)) - 30);
    $primaryDark = sprintf('#%02x%02x%02x', $r, $g, $b);
} else {
    $primaryDark = '#0369a1';
}

// ثيم الواجهة
$frontendTheme = setting($settings, 'frontend_theme', 'default');
$themeClass = 'theme-default';
if ($frontendTheme === 'theme-ocean') {
    $themeClass = 'theme-ocean';
} elseif ($frontendTheme === 'theme-sunset') {
    $themeClass = 'theme-sunset';
}

// حالة تسجيل الدخول
$isLoggedIn = !empty($_SESSION['user']) && !empty($_SESSION['user']['role']) && $_SESSION['user']['role'] !== 'guest';
$isAdmin    = $isLoggedIn && ($_SESSION['user']['role'] === 'admin');

// تحميل أقسام الهيدر
$headerCategories = [];
try {
    if ($pdo instanceof PDO) {
        $stmt = $pdo->query("SELECT id, name, slug FROM categories ORDER BY id ASC LIMIT 6");
        $headerCategories = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {
    @error_log('[Trending] categories load error: ' . $e->getMessage());
    $headerCategories = [];
}

// إعدادات ميزات الواجهة
$showCarbonBadge = setting($settings, 'show_carbon_badge', '1') === '1';
$carbonBadgeText = setting($settings, 'carbon_badge_text', 'نلتزم بالمساعدة على تقليل انبعاث الكربون في بنيتنا التقنية.');

// روابط أساسية
$baseUrl = base_url();

// تحميل الأخبار الشائعة (الأكثر مشاهدة)
$trendingNews = [];
try {
    if ($pdo instanceof PDO) {
        $sql = "SELECT id, title, excerpt, featured_image, published_at, views
                FROM news 
                WHERE status = 'published' 
                ORDER BY views DESC, published_at DESC 
                LIMIT 20";
        $stmt = $pdo->query($sql);
        $trendingNews = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {
    @error_log('[Trending] trendingNews load error: ' . $e->getMessage());
    $trendingNews = [];
}

$newsUrl = function(array $row) use ($baseUrl): string {
    $slug = isset($row['slug']) ? (string)$row['slug'] : (string)($row['id'] ?? '');
    $slug = trim($slug);
    if ($slug === '') {
        $slug = (string)($row['id'] ?? '');
    }
    return $baseUrl . '/news/id/' . (int)($row['id'] ?? 0);
};

// تحميل الهيدر
// قد يتم تضمين الهيدر مسبقاً عبر TemplateEngine
if (!defined('GDY_TPL_WRAPPED')) {
    require_once __DIR__ . '/partials/header.php';
}
?>

<section aria-label="الأخبار الأكثر تداولاً">
    <div class="section-header">
        <div>
            <div class="section-title">الأخبار الأكثر تداولاً</div>
            <div class="section-sub">أكثر الأخبار مشاهدة وقراءة من قبل الزوار</div>
        </div>
        <a href="<?= h($baseUrl) ?>" class="section-sub">
            العودة للرئيسية
        </a>
    </div>

    <?php if (!empty($trendingNews)): ?>
        <div class="news-grid">
            <?php foreach ($trendingNews as $row): ?>
                <article class="news-card fade-in">
                    <?php if (!empty($row['featured_image'])): ?>
                        <a href="<?= h($newsUrl($row)) ?>" class="news-thumb">
                            <img src="<?= h($row['featured_image']) ?>" alt="<?= h($row['title']) ?>">
                        </a>
                    <?php endif; ?>
                    <div class="news-body">
                        <a href="<?= h($newsUrl($row)) ?>">
                            <h2 class="news-title">
                                <?php
                                    $t = (string)$row['title'];
                                    $cut = mb_substr($t, 0, 90, 'UTF-8');
                                    echo h($cut) . (mb_strlen($t, 'UTF-8') > 90 ? '…' : '');
                                ?>
                            </h2>
                        </a>
                        <?php if (!empty($row['excerpt'])): ?>
                            <p class="news-excerpt">
                                <?php
                                    $ex = (string)$row['excerpt'];
                                    $cut = mb_substr($ex, 0, 120, 'UTF-8');
                                    echo h($cut) . (mb_strlen($ex, 'UTF-8') > 120 ? '…' : '');
                                ?>
                            </p>
                        <?php endif; ?>
                        <div class="news-meta">
                            <span>
                                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                <?= !empty($row['published_at']) ? h(date('Y-m-d', strtotime($row['published_at']))) : '' ?>
                            </span>
                            <span>
                                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                <?= (int)($row['views'] ?? 0) ?> مشاهدة
                            </span>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="side-widget" style="text-align: center; padding: 40px 20px;">
            <div class="side-widget-title">
                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                <span>لا توجد أخبار شائعة بعد</span>
            </div>
            <p style="color: var(--text-muted); margin-top: 10px;">
                سيتم عرض الأخبار الأكثر مشاهدة هنا تلقائياً بعد وجود زيارات كافية.
            </p>
            <a href="<?= h($baseUrl) ?>" class="btn-primary" style="margin-top: 15px;">
                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                <span>العودة للرئيسية</span>
            </a>
        </div>
    <?php endif; ?>
</section>

<?php
// تحميل الفوتر
// قد يتم تضمين الفوتر مسبقاً عبر TemplateEngine
if (!defined('GDY_TPL_WRAPPED')) {
    require_once __DIR__ . '/partials/footer.php';
}
?>