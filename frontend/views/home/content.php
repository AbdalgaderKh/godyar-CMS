<?php
// /godyar/frontend/views/home/content.php
$pdo = $pdo ?? (function_exists("gdy_pdo_safe") ? gdy_pdo_safe() : null);
if (!function_exists("nf")) {
  function nf(array $n, string $field): string {
    global $pdo;
    if ($pdo instanceof PDO && function_exists("gdy_news_field")) {
      return (string)gdy_news_field($pdo, $n, $field);
    }
    return (string)($n[$field] ?? "");
  }
}



// حساب الأخبار الرئيسية
$mainNews = $latestNews[0] ?? null;

// دالة مساعدة لتحويل رابط فيديو إلى رابط embed (يوتيوب + تيك توك + فيسبوك + فيميو + ديلي موشن)
// مع ملاحظة: إنستغرام و سناب شات غالباً يمنعون الإظهار داخل iframe، لذلك نرجّع null لهم
if (!function_exists('gdy_youtube_embed_url')) {
    function gdy_youtube_embed_url(string $url): ?string {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $lower = strtolower($url);

        // Instagram / Snapchat → لا نستخدم iframe
        if (strpos($lower, 'instagram.com') !== false ||
            strpos($lower, 'snapchat.com')  !== false) {
            return null;
        }

        // TikTok
        if (preg_match('~tiktok\.com/.*/video/([0-9]+)~i', $url, $m)) {
            return 'https://www.tiktok.com/embed/v2/' . $m[1];
        }

        // Facebook / fb.watch
        if (preg_match('~(facebook\.com|fb\.watch)~i', $url)) {
            $encoded = rawurlencode($url);
            return 'https://www.facebook.com/plugins/video.php?href=' . $encoded . '&show_text=0&width=700';
        }

        // Vimeo
        if (preg_match('~vimeo\.com/([0-9]+)~i', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }

        // Dailymotion
        if (preg_match('~dailymotion\.com/video/([a-zA-Z0-9]+)~i', $url, $m)) {
            return 'https://www.dailymotion.com/embed/video/' . $m[1];
        }

        // YouTube: أنماط مختلفة
        // لو هو أصلاً embed
        if (preg_match('~youtube\.com/embed/([a-zA-Z0-9_-]{6,})~i', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }

        // watch?v=VIDEO_ID
        if (preg_match('~youtube\.com/watch\?v=([a-zA-Z0-9_-]{6,})~i', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }

        // youtu.be/VIDEO_ID
        if (preg_match('~youtu\.be/([a-zA-Z0-9_-]{6,})~i', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }

        // أي صيغة أخرى لا نغامر بها
        return null;
    }
}
?>

<!-- إعلان أعلى الصفحة -->
<?php if (!empty($headerAd) && strpos($headerAd, 'No active ad') === false): ?>
<div class="header-ad-container" style="margin-bottom: 2rem; text-align: center;">
    <?= $headerAd ?>
</div>
<?php endif; ?>

<!-- الميزات الجديدة المتميزة -->

<!-- 1. شريط الإشعارات الذكية -->
<?php if (!empty($smartNotifications)): ?>
<div class="smart-notifications" style="margin-bottom: 2rem;">
    <div class="notifications-container" style="
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 15px;
        padding: 1rem;
        color: white;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    ">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
            <?php foreach ($smartNotifications as $notification): ?>
            <div class="notification-item" style="
                display: flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.5rem 1rem;
                background: rgba(255,255,255,0.15);
                border-radius: 10px;
                backdrop-filter: blur(10px);
            ">
                <i class="fa <?= $notification['icon'] ?>" style="font-size: 1.1rem;"></i>
                <div>
                    <div style="font-weight: 600; font-size: 0.9rem;"><?= nf($notification,'title') ?></div>
                    <div style="font-size: 0.8rem; opacity: 0.9;"><?= $notification['message'] ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 2. بطاقة الإحصائيات الذكية -->
<div class="smart-stats" style="margin-bottom: 2rem;">
    <div class="stats-grid" style="
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    ">
        <div class="stat-card" style="
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 4px solid #10b981;
        ">
            <div style="font-size: 2rem; color: #10b981; margin-bottom: 0.5rem;">📊</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #1f2937;"><?= $smartStats['total_news'] ?? 0 ?></div>
            <div style="color: #6b7280; font-size: 0.9rem;">إجمالي الأخبار</div>
        </div>

        <div class="stat-card" style="
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 4px solid #3b82f6;
        ">
            <div style="font-size: 2rem; color: #3b82f6; margin-bottom: 0.5rem;">👁️</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #1f2937;"><?= number_format($smartStats['total_views'] ?? 0) ?></div>
            <div style="color: #6b7280; font-size: 0.9rem;">إجمالي المشاهدات</div>
        </div>

        <div class="stat-card" style="
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 4px solid #f59e0b;
        ">
            <div style="font-size: 2rem; color: #f59e0b; margin-bottom: 0.5rem;">📅</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #1f2937;"><?= $smartStats['today_news'] ?? 0 ?></div>
            <div style="color: #6b7280; font-size: 0.9rem;">أخبار اليوم</div>
        </div>

        <div class="stat-card" style="
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 4px solid #ef4444;
        ">
            <div style="font-size: 2rem; color: #ef4444; margin-bottom: 0.5rem;">⚡</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #1f2937;"><?= $smartStats['avg_views'] ?? 0 ?></div>
            <div style="color: #6b7280; font-size: 0.9rem;">متوسط المشاهدات</div>
        </div>
    </div>
</div>

<!-- 3. الأخبار العاجلة -->
<?php if (!empty($breakingNews)): ?>
<div class="breaking-news" style="margin-bottom: 2rem;">
    <div class="breaking-header" style="
        background: linear-gradient(135deg, #ff6b6b, #ee5a24);
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 10px 10px 0 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 700;
    ">
        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
        <span>أخبار عاجلة</span>
        <div class="live-indicator" style="
            background: white;
            color: #ee5a24;
            padding: 0.2rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-right: auto;
        ">
            🔴 بث مباشر
        </div>
    </div>
    <div class="breaking-content" style="
        background: white;
        border-radius: 0 0 10px 10px;
        padding: 1.5rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    ">
        <?php foreach ($breakingNews as $news): ?>
        <div class="breaking-item" style="
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 1rem;
        ">
            <div class="breaking-badge" style="
                background: #fee2e2;
                color: #dc2626;
                padding: 0.3rem 0.8rem;
                border-radius: 20px;
                font-size: 0.8rem;
                font-weight: 600;
                white-space: nowrap;
            ">
                عاجل
            </div>
            <a href="<?= h($newsUrl($news)) ?>" style="
                color: #1f2937;
                text-decoration: none;
                font-weight: 600;
                flex: 1;
            ">
                <?= h(nf($news,'title')) ?>
            </a>
            <div style="color: #6b7280; font-size: 0.8rem; white-space: nowrap;">
                <?= date('H:i', strtotime($news['created_at'])) ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="layout-main">
    <!-- بلوك الهيرو / الخبر الرئيسي -->
    <section class="hero-card fade-in" aria-label="خبر رئيسي">
        <?php if ($mainNews): ?>
            <div class="hero-badge">
                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                <span>خبر مميز</span>
            </div>
            <a href="<?= h($newsUrl($mainNews)) ?>">
                <h1 class="hero-title"><?= h(nf($mainNews,'title')) ?></h1>
            </a>
            <p class="hero-subtitle">
                <?php
                    $sub = (string)(nf($mainNews,'excerpt') ?? '');
                    if ($sub === '') {
                        $sub = 'تابع تفاصيل الخبر من خلال المنصة.';
                    }
                    $cut = mb_substr($sub, 0, 200, 'UTF-8');
                    echo h($cut) . (mb_strlen($sub, 'UTF-8') > 200 ? '…' : '');
                ?>
            </p>
            <div class="hero-meta">
                <span>
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                    <?= !empty($mainNews['published_at']) ? h(date('Y-m-d', strtotime($mainNews['published_at']))) : '' ?>
                </span>
                <span><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> محدث من لوحة التحكم</span>
            </div>
            <div class="hero-actions">
                <a href="<?= h($newsUrl($mainNews)) ?>" class="btn-primary">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                    <span>اقرأ التفاصيل</span>
                </a>
                <a href="<?= h($archiveUrl) ?>" class="btn-ghost">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg>
                    <span>جميع الأخبار</span>
                </a>
            </div>
        <?php else: ?>
            <div class="hero-badge">
                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                <span>لا توجد أخبار بعد</span>
            </div>
            <h1 class="hero-title">مرحباً بك في <?= h($siteName) ?></h1>
            <p class="hero-subtitle">
                قم بإضافة أول خبر من لوحة التحكم، وسيتم عرضه هنا تلقائياً بخلفية تركوازية خفيفة مع هيدر وفوتر داكنين.
            </p>
        <?php endif; ?>
    </section>

    <!-- صندوق جانبي: أهم الأخبار / الأكثر قراءة -->
    <aside class="side-widget fade-in" aria-label="أهم الأخبار">
        <!-- إعلان أعلى الشريط الجانبي -->
        <?php if (!empty($sidebarTopAd) && strpos($sidebarTopAd, 'No active ad') === false): ?>
        <div class="sidebar-ad-container" style="margin-bottom: 1.5rem;">
            <?= $sidebarTopAd ?>
        </div>
        <?php endif; ?>

        <div class="side-widget-title">
            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <span><?= h($homeFeaturedTitle) ?></span>
        </div>

        <?php if (!empty($featuredNews)): ?>
            <ul style="list-style:none;margin:0;padding:0;position:relative;z-index:1;">
                <?php foreach ($featuredNews as $row): ?>
                    <li style="margin-bottom:6px;">
                        <a href="<?= h($newsUrl($row)) ?>" style="display:flex;gap:8px;align-items:flex-start;">
                            <?php if (!empty($row['featured_image'])): ?>
                                <div style="width:52px;height:52px;border-radius:12px;overflow:hidden;flex-shrink:0;background:#e2e8f0;">
                                    <img src="<?= h($row['featured_image']) ?>" alt="<?= h(nf($row,'title')) ?>" style="width:100%;height:100%;object-fit:cover;">
                                </div>
                            <?php endif; ?>
                            <div style="flex:1;">
                                <div style="font-size:.8rem;font-weight:600;color:#0f172a;">
                                    <?php
                                        $t = (string)nf($row,'title');
                                        $cut = mb_substr($t, 0, 70, 'UTF-8');
                                        echo h($cut) . (mb_strlen($t, 'UTF-8') > 70 ? '…' : '');
                                    ?>
                                </div>
                                <div style="font-size:.7rem;color:#6b7280;">
                                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                    <?= !empty($row['published_at']) ? h(date('Y-m-d', strtotime($row['published_at']))) : '' ?>
                                </div>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="side-widget-empty">
                لا توجد أخبار مميزة بالوقت الحالي. سيتم عرض الأخبار ذات المشاهدات الأعلى هنا تلقائياً.
            </p>
        <?php endif; ?>

        <!-- 4. قسم الفيديوهات المميزة -->
        <?php if (!empty($featuredVideos)): ?>
        <div class="video-section" style="margin-top: 2rem;">
            <div class="side-widget-title">
                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                <span>فيديوهات مميزة</span>
            </div>

            <div class="videos-grid" style="display: grid; gap: 1rem;">
                <?php foreach ($featuredVideos as $video): ?>
                    <?php
                        // نحاول استخدام السكيمة الجديدة أولاً: video_url = embed, raw_url = الرابط الأصلي
                        $rawUrl   = $video['raw_url']   ?? '';
                        $videoUrl = $video['video_url'] ?? '';

                        $embedUrl = '';
                        if ($videoUrl && $rawUrl) {
                            $embedUrl = $videoUrl;
                        } elseif ($videoUrl) {
                            $embedUrl = gdy_youtube_embed_url($videoUrl) ?: $videoUrl;
                            if ($rawUrl === '') {
                                $rawUrl = $videoUrl;
                            }
                        } elseif ($rawUrl) {
                            $embedUrl = gdy_youtube_embed_url($rawUrl) ?: $rawUrl;
                        }

                        // المنصّة (قد تأتي من الـ Controller أو نخمّن من الرابط)
                        $platform = $video['platform'] ?? '';
                        if ($platform === '' && $rawUrl !== '') {
                            $lower = strtolower($rawUrl);
                            if (strpos($lower, 'tiktok.com') !== false) {
                                $platform = 'TikTok';
                            } elseif (strpos($lower, 'facebook.com') !== false || strpos($lower, 'fb.watch') !== false) {
                                $platform = 'Facebook';
                            } elseif (strpos($lower, 'instagram.com') !== false) {
                                $platform = 'Instagram';
                            } elseif (strpos($lower, 'snapchat.com') !== false) {
                                $platform = 'Snapchat';
                            } elseif (strpos($lower, 'vimeo.com') !== false) {
                                $platform = 'Vimeo';
                            } elseif (strpos($lower, 'dailymotion.com') !== false) {
                                $platform = 'Dailymotion';
                            } elseif (strpos($lower, 'youtube.com') !== false || strpos($lower, 'youtu.be') !== false) {
                                $platform = 'YouTube';
                            } else {
                                $platform = 'المصدر الأصلي';
                            }
                        } elseif ($platform === '') {
                            $platform = 'المصدر الأصلي';
                        }

                        $thumb    = !empty($video['thumbnail'])
                            ? $video['thumbnail']
                            : $baseUrl . '/assets/images/video-placeholder.jpg';
                        $title    = nf($video,'title') ?? '';
                        $duration = $video['duration'] ?? '';
                        $views    = (int)($video['views'] ?? 0);
                    ?>
                    <div class="video-card"
                         <?= $embedUrl ? 'data-embed="'.h($embedUrl).'"' : '' ?>
                         style="
                            background: white;
                            border-radius: 10px;
                            overflow: hidden;
                            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                         ">
                        <!-- الصورة -->
                        <div class="video-thumbnail"
                             style="position: relative; display: block; cursor: pointer;">
                            <img src="<?= h($thumb) ?>"
                                 alt="<?= h($title) ?>"
                                 style="width: 100%; height: 120px; object-fit: cover;"
                                 onerror="this.src='<?= h($baseUrl) ?>/assets/images/video-placeholder.jpg';this.onerror=null;">

                            <div class="video-overlay" style="
                                position: absolute;
                                top: 0;
                                left: 0;
                                right: 0;
                                bottom: 0;
                                background: rgba(0,0,0,0.3);
                                display: flex;
                                align-items: center;
                                justify-content: center;
                            ">
                                <div class="play-button" style="
                                    background: rgba(255,255,255,0.9);
                                    width: 40px;
                                    height: 40px;
                                    border-radius: 50%;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                ">
                                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                </div>
                            </div>

                            <?php if ($duration): ?>
                                <div class="video-duration" style="
                                    position: absolute;
                                    bottom: 8px;
                                    left: 8px;
                                    background: rgba(0,0,0,0.7);
                                    color: white;
                                    padding: 0.2rem 0.5rem;
                                    border-radius: 4px;
                                    font-size: 0.7rem;
                                ">
                                    <?= h($duration) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="video-info" style="padding: 1rem;">
                            <h4 style="
                                margin: 0 0 0.5rem 0;
                                font-size: 0.9rem;
                                color: #1f2937;
                                line-height: 1.4;
                            ">
                                <?= h($title) ?>
                            </h4>

                            <div style="
                                display: flex;
                                justify-content: space-between;
                                align-items: center;
                                font-size: 0.8rem;
                                color: #6b7280;
                                gap: .5rem;
                            ">
                                <span>
                                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                    <?= number_format($views) ?> مشاهدة
                                </span>

                                <div style="display:flex; flex-direction:column; align-items:flex-end; gap:4px;">
                                    <?php if (!empty($rawUrl)): ?>
                                        <a href="<?= h($rawUrl) ?>"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           style="
                                               background: #4b5563;
                                               color: #fff;
                                               padding: 0.2rem 0.6rem;
                                               border-radius: 6px;
                                               font-size: 0.75rem;
                                               text-decoration: none;
                                           ">
                                            مشاهدة على <?= h($platform) ?>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($embedUrl): ?>
                                        <!-- زر شاهد يفتح النافذة المنبثقة داخل الموقع -->
                                        <button type="button"
                                                class="watch-btn"
                                                style="
                                                    background: #dc2626;
                                                    color: white;
                                                    border: none;
                                                    padding: 0.3rem 0.8rem;
                                                    border-radius: 6px;
                                                    font-size: 0.8rem;
                                                    cursor: pointer;
                                                ">
                                            شاهد هنا
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- إعلان أسفل الشريط الجانبي -->
        <?php if (!empty($sidebarBottomAd) && strpos($sidebarBottomAd, 'No active ad') === false): ?>
        <div class="sidebar-ad-container" style="margin-top: 1.5rem;">
            <?= $sidebarBottomAd ?>
        </div>
        <?php endif; ?>
    </aside>
</div>

<!-- إعلان أعلى المحتوى -->
<?php if (!empty($contentTopAd) && strpos($contentTopAd, 'No active ad') === false): ?>
<div class="content-ad-container" style="margin: 2rem 0; text-align: center;">
    <?= $contentTopAd ?>
</div>
<?php endif; ?>

<!-- شبكة آخر الأخبار -->
<section aria-label="آخر الأخبار">
    <div class="section-header">
        <div>
            <div class="section-title"><?= h($homeLatestTitle) ?></div>
            <div class="section-sub">كل ما يُنشر من لوحة التحكم يظهر في هذه الشبكة تلقائياً.</div>
        </div>
        <a href="<?= h($archiveUrl) ?>" class="section-sub">
            عرض الأرشيف بالكامل
        </a>
    </div>

    <?php if (!empty($latestNews)): ?>
        <div class="news-grid">
            <?php foreach ($latestNews as $idx => $row): ?>
                <?php if ($idx === 0) continue; // استخدمناه في الهيرو ?>
                <article class="news-card fade-in">
                    <?php if (!empty($row['featured_image'])): ?>
                        <a href="<?= h($newsUrl($row)) ?>" class="news-thumb">
                            <img src="<?= h($row['featured_image']) ?>" alt="<?= h(nf($row,'title')) ?>">
                        </a>
                    <?php endif; ?>
                    <div class="news-body">
                        <a href="<?= h($newsUrl($row)) ?>">
                            <h2 class="news-title">
                                <?php
                                    $t = (string)nf($row,'title');
                                    $cut = mb_substr($t, 0, 90, 'UTF-8');
                                    echo h($cut) . (mb_strlen($t, 'UTF-8') > 90 ? '…' : '');
                                ?>
                            </h2>
                        </a>
                        <?php if (!empty(nf($row,'excerpt'))): ?>
                            <p class="news-excerpt">
                                <?php
                                    $ex = (string)nf($row,'excerpt');
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
        <p class="section-sub">
            لا توجد أخبار منشورة بعد. يمكنك إضافة الأخبار من لوحة التحكم &gt; إدارة المحتوى.
        </p>
    <?php endif; ?>
</section>

<!-- 5. قسم التوصيات الذكية -->
<?php if (!empty($smartRecommendations)): ?>
<section class="smart-recommendations" style="margin: 3rem 0;">
    <div class="section-header">
        <div>
            <div class="section-title">🎯 قد يعجبك</div>
            <div class="section-sub">أخبار مختارة خصيصاً لك بناءً على اهتماماتك</div>
        </div>
    </div>

    <div class="recommendations-grid" style="
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
    ">
        <?php foreach ($smartRecommendations as $news): ?>
        <div class="recommendation-card" style="
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        ">
            <?php if (!empty($news['featured_image'])): ?>
            <div class="recommendation-image" style="
                height: 160px;
                overflow: hidden;
            ">
                <img src="<?= h($news['featured_image']) ?>" alt="<?= h(nf($news,'title')) ?>" style="
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                    transition: transform 0.3s ease;
                ">
            </div>
            <?php endif; ?>
            <div class="recommendation-content" style="padding: 1.5rem;">
                <h3 style="
                    margin: 0 0 1rem 0;
                    font-size: 1.1rem;
                    color: #1f2937;
                    line-height: 1.4;
                ">
                    <a href="<?= h($newsUrl($news)) ?>" style="color: inherit; text-decoration: none;">
                        <?= h(mb_substr(nf($news,'title'), 0, 80)) . (mb_strlen(nf($news,'title')) > 80 ? '…' : '') ?>
                    </a>
                </h3>
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; color: #6b7280;">
                    <span><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= !empty($news['published_at']) ? h(date('Y-m-d', strtotime($news['published_at']))) : '' ?></span>
                    <span><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= number_format($news['views']) ?> مشاهدة</span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- إعلان أسفل المحتوى -->
<?php if (!empty($contentBottomAd) && strpos($contentBottomAd, 'No active ad') === false): ?>
<div class="content-ad-container" style="margin: 2rem 0; text-align: center;">
    <?= $contentBottomAd ?>
</div>
<?php endif; ?>

<!-- باقي الكود كما هو -->
<?php if ($enableMostRead || $enableMostCommented): ?>
    <!-- ... أقسام الأكثر قراءة / تعليقاً إن وجدت ... -->
<?php endif; ?>

<!-- إعلان الفوتر -->
<?php if (!empty($footerAd) && strpos($footerAd, 'No active ad') === false): ?>
<div class="footer-ad-container" style="margin-top: 3rem; text-align: center;">
    <?= $footerAd ?>
</div>
<?php endif; ?>

<!-- نافذة الفيديو المنبثقة -->
<div id="videoModal" class="video-modal">
    <div class="video-modal-backdrop"></div>
    <div class="video-modal-dialog">
        <button type="button" id="videoModalClose" class="video-modal-close" aria-label="إغلاق">
            ✕
        </button>
        <div class="video-modal-body">
            <div class="video-modal-iframe-wrapper">
                <iframe id="videoModalIframe"
                        src=""
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        allowfullscreen
                        referrerpolicy="strict-origin-when-cross-origin"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- تنسيقات CSS للميزات الجديدة -->
<style>
.smart-notifications .notification-item:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-2px);
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    transition: all 0.3s ease;
}

.breaking-item:hover {
    background: #f8fafc;
    transition: all 0.3s ease;
}

.video-card:hover {
    transform: translateY(-3px);
    transition: all 0.3s ease;
}

.video-card:hover .play-button {
    background: white;
    transform: scale(1.1);
    transition: all 0.3s ease;
}

.recommendation-card:hover {
    transform: translateY(-5px);
    transition: all 0.3s ease;
}

.recommendation-card:hover .recommendation-image img {
    transform: scale(1.05);
    transition: transform 0.3s ease;
}

/* نافذة الفيديو المنبثقة */
.video-modal {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    opacity: 0;
    visibility: hidden;
    transition: opacity .25s ease, visibility .25s ease;
}

.video-modal.is-visible {
    pointer-events: auto;
    opacity: 1;
    visibility: visible;
}

.video-modal-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.7);
}

.video-modal-dialog {
    position: relative;
    z-index: 1;
    background: #0b1120;
    border-radius: 16px;
    padding: 0;
    max-width: 900px;
    width: 90%;
    box-shadow: 0 25px 50px -12px rgba(15,23,42,0.75);
}

.video-modal-close {
    position: absolute;
    top: 8px;
    left: 10px;
    background: rgba(15,23,42,0.8);
    border: none;
    color: #e5e7eb;
    width: 32px;
    height: 32px;
    border-radius: 999px;
    cursor: pointer;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.video-modal-body {
    padding: 0;
}

.video-modal-iframe-wrapper {
    position: relative;
    width: 100%;
    padding-top: 56.25%; /* 16:9 */
    overflow: hidden;
    border-radius: 16px;
}

.video-modal-iframe-wrapper iframe {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
}

/* تنسيقات متجاوبة */
@media (max-width: 768px) {
    .smart-notifications .notifications-container > div {
        flex-direction: column;
    }

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .recommendations-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }

    .breaking-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}
</style>

<script>
// تفعيل نافذة الفيديو المنبثقة
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('videoModal');
    var iframe = document.getElementById('videoModalIframe');
    var closeBtn = document.getElementById('videoModalClose');

    function openVideoModal(embedUrl) {
        if (!embedUrl) return;
        var url = embedUrl.indexOf('?') === -1
            ? embedUrl + '?autoplay=1&rel=0'
            : embedUrl + '&autoplay=1&rel=0';

        iframe.src = url;
        modal.classList.add('is-visible');
        document.body.style.overflow = 'hidden';
    }

    function closeVideoModal() {
        modal.classList.remove('is-visible');
        iframe.src = '';
        document.body.style.overflow = '';
    }

    // ضغط على زر شاهد أو على الصورة
    document.querySelectorAll('.video-card .watch-btn, .video-card .video-thumbnail')
        .forEach(function (el) {
            el.addEventListener('click', function (e) {
                var card = this.closest('.video-card');
                if (!card) return;
                var embed = card.getAttribute('data-embed');
                if (!embed) return; // لا يوجد إطار، لا نفتح مودال
                e.preventDefault();
                openVideoModal(embed);
            });
        });

    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            closeVideoModal();
        });
    }

    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal || e.target.classList.contains('video-modal-backdrop')) {
                closeVideoModal();
            }
        });
    }

    // ESC يغلق النافذة
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' || e.key === 'Esc') {
            if (modal.classList.contains('is-visible')) {
                closeVideoModal();
            }
        }
    });
});
</script>
