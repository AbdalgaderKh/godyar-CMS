<?php
declare(strict_types=1);

// godyar/news.php — عرض خبر مفرد (مسار /news/{id أو slug})

// تحميل bootstrap
$bootstrapPaths = [
    __DIR__ . '/includes/bootstrap.php',
    __DIR__ . '/godyar/includes/bootstrap.php',
    __DIR__ . '/../includes/bootstrap.php',
];

$bootstrapLoaded = false;
foreach ($bootstrapPaths as $path) {
    if (is_file($path)) {
        require_once $path;
        $bootstrapLoaded = true;
        break;
    }
}

if (!$bootstrapLoaded) {
    http_response_code(500);
    echo 'تعذر تحميل ملف التهيئة.';
    exit;
}

$pdo = gdy_pdo_safe();

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * فحص وجود عمود في جدول
 */
if (!function_exists('gdy_column_exists')) {
    function gdy_column_exists(PDO $pdo, string $table, string $column): bool
    {
        try {
            if (function_exists('db_column_exists')) {
                return db_column_exists($pdo, $table, $column);
            }
            // Fallback (آمن طالما أن أسماء الجداول/الأعمدة ثابتة ضمن الكود)
            $safeTable = str_replace('`', '', $table);
            $q = $pdo->quote($column);
            $stmt = $pdo->query("SHOW COLUMNS FROM `" . $safeTable . "` LIKE " . $q);
            return (bool)($stmt && $stmt->fetch(PDO::FETCH_ASSOC));
        } catch (Throwable $e) {
            @error_log('[Schema] column_exists error: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * بناء رابط صورة مع دعم المسارات النسبية والكاملة
 */
if (!function_exists('gdy_build_image_url')) {
    function gdy_build_image_url(string $baseUrl, ?string $path, string $default = ''): string
    {
        $path = trim((string)$path);
        if ($path === '') {
            return $default;
        }

        // رابط كامل
        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }

        // مسار يبدأ بـ /
        if ($path[0] === '/') {
            return rtrim($baseUrl, '/') . $path;
        }

        // مسار نسبي داخل uploads/news
        return rtrim($baseUrl, '/') . '/uploads/news/' . ltrim($path, '/');
    }
}

$baseUrl = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') : '';

$newsIdOrSlug = $_GET['id'] ?? null;
$article      = null;
$notFound     = false;

if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo 'تعذر الاتصال بقاعدة البيانات.';
    exit;
}

if ($newsIdOrSlug === null || $newsIdOrSlug === '') {
    http_response_code(404);
    $notFound = true;
} else {
    $isNumericId = ctype_digit((string)$newsIdOrSlug);
    try {
        if ($isNumericId) {
            $stmt = $pdo->prepare("
                SELECT 
                    n.*,
                    c.name AS category_name,
                    c.slug AS category_slug
                FROM news n
                LEFT JOIN categories c ON n.category_id = c.id
                WHERE n.id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => (int)$newsIdOrSlug]);
        } else {
            $stmt = $pdo->prepare("
                SELECT 
                    n.*,
                    c.name AS category_name,
                    c.slug AS category_slug
                FROM news n
                LEFT JOIN categories c ON n.category_id = c.id
                WHERE n.slug = :slug
                LIMIT 1
            ");
            $stmt->execute([':slug' => (string)$newsIdOrSlug]);
        }
        $article = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (empty($article) || (isset($article['status']) && $article['status'] !== 'published')) {
            http_response_code(404);
            $notFound = true;
        }
    } catch (Throwable $e) {
        @error_log('[news.php] fetch article error: ' . $e->getMessage());
        http_response_code(500);
        $notFound = true;
    }
}

// تجهيز بعض المتغيرات للعرض
$title       = $article['title']       ?? '';
$body        = $article['content']     ?? ($article['body'] ?? '');
$createdAt   = $article['created_at']  ?? ($article['published_at'] ?? null);
$views       = isset($article['views']) ? (int)$article['views'] : null;
$catName     = $article['category_name'] ?? null;
$catSlug     = $article['category_slug'] ?? null;
$catId       = isset($article['category_id']) ? (int)$article['category_id'] : null;
$videoUrl    = isset($article['video_url']) ? trim((string)$article['video_url']) : '';
$shareUrl    = rtrim($baseUrl, '/') . '/news/id/' . (int)($article['id'] ?? 0);

// مصفوفة مقالات ذات صلة
$relatedArticles = [];

// وقت القراءة التقريبي
$readingTime = null;
if (!empty($body)) {
    $plain = trim(strip_tags((string)$body));
    if ($plain !== '') {
        $chars       = mb_strlen($plain, 'UTF-8');
        $readingTime = max(1, (int)ceil($chars / 800)); // تقريبًا 800 حرف في الدقيقة
    }
}

// كاتب الرأي (إن وجد opinion_author_id)
$opinionAuthor          = null;
$opinionAuthorUrl       = null;
$opinionAuthorName      = null;
$opinionAuthorPageTitle = null;
$opinionAuthorAvatar    = null;
$opinionAuthorEmail     = null;
$opinionAuthorFacebook  = null;
$opinionAuthorWebsite   = null;
$opinionAuthorTwitter   = null;

if ($pdo instanceof PDO
    && !$notFound
    && !empty($article)
    && gdy_column_exists($pdo, 'news', 'opinion_author_id')
    && !empty($article['opinion_author_id'])
) {
    try {
        $stmtAuthor = $pdo->prepare("
            SELECT 
                id,
                name,
                slug,
                avatar,
                page_title,
                email,
                social_facebook,
                social_website,
                social_twitter
            FROM opinion_authors
            WHERE id = :id AND is_active = 1
            LIMIT 1
        ");
        $stmtAuthor->execute([':id' => (int)$article['opinion_author_id']]);
        $opinionAuthor = $stmtAuthor->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($opinionAuthor) {
            $opinionAuthorName      = (string)($opinionAuthor['name'] ?? '');
            $opinionAuthorPageTitle = (string)($opinionAuthor['page_title'] ?? '');
            $opinionAuthorEmail     = trim((string)($opinionAuthor['email'] ?? ''));
            $opinionAuthorFacebook  = trim((string)($opinionAuthor['social_facebook'] ?? ''));
            $opinionAuthorWebsite   = trim((string)($opinionAuthor['social_website'] ?? ''));
            $opinionAuthorTwitter   = trim((string)($opinionAuthor['social_twitter'] ?? ''));

            $avatarRaw = trim((string)($opinionAuthor['avatar'] ?? ''));
            if ($avatarRaw !== '') {
                if (preg_match('~^https?://~i', $avatarRaw)) {
                    $opinionAuthorAvatar = $avatarRaw;
                } else {
                    $opinionAuthorAvatar = rtrim($baseUrl, '/') . '/' . ltrim($avatarRaw, '/');
                }
            }
            if (!$opinionAuthorAvatar) {
                $opinionAuthorAvatar = rtrim($baseUrl, '/') . '/assets/images/author-placeholder.png';
            }

            // رابط صفحة الكاتب
            $authorSlug = (string)($opinionAuthor['slug'] ?? '');
            if ($authorSlug !== '') {
                $opinionAuthorUrl = rtrim($baseUrl, '/') . '/opinion_author.php?slug=' . rawurlencode($authorSlug);
            } else {
                $opinionAuthorUrl = rtrim($baseUrl, '/') . '/opinion_author.php?id=' . (int)$opinionAuthor['id'];
            }
        }
    } catch (Throwable $e) {
        @error_log('[news.php] opinion author error: ' . $e->getMessage());
    }
}

// مقالات ذات صلة (من نفس التصنيف إن وجد)
if ($pdo instanceof PDO && !$notFound && $catId) {
    try {
        $stmtRel = $pdo->prepare("
            SELECT 
                n.id,
                n.title,
                n.slug,
                n.image,
                n.featured_image,
                n.created_at,
                n.views
            FROM news n
            WHERE n.status = 'published'
              AND n.deleted_at IS NULL
              AND n.id <> :id
              AND n.category_id = :cat
            ORDER BY COALESCE(n.publish_at, n.published_at, n.created_at) DESC
            LIMIT 6
        ");
        $stmtRel->execute([
            ':id'  => (int)$article['id'],
            ':cat' => $catId,
        ]);
        $relatedArticles = $stmtRel->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        @error_log('[news.php] related articles error: ' . $e->getMessage());
    }
}

// إعداد العنوان والوصف للصفحة
$siteTitle = $GLOBALS['site_settings']['site.name'] ?? ($GLOBALS['site_settings']['site_name'] ?? 'Godyar News');
$pageTitle = $title !== '' ? $title . ' - ' . $siteTitle : $siteTitle;
$pageDesc  = !empty($article['excerpt'] ?? '') ? (string)$article['excerpt'] : mb_substr(strip_tags((string)$body), 0, 160, 'UTF-8');

// تهيئة الهيدر الموحد
$headerFile = __DIR__ . '/frontend/views/partials/header.php';
if (is_file($headerFile)) {
    $page_title  = $pageTitle;
    $page_desc   = $pageDesc;
    $page_image  = '';
    if (!empty($article['featured_image'])) {
        $page_image = gdy_build_image_url($baseUrl, $article['featured_image']);
    } elseif (!empty($article['image'])) {
        $page_image = gdy_build_image_url($baseUrl, $article['image']);
    }
    require $headerFile;
} else {
    ?>
    <!doctype html>
    <html lang="ar" dir="rtl">
    <head>
<script>window.GODYAR_CANONICAL_URL = window.location.href;</script>

        <meta charset="utf-8">
        <title><?= h($pageTitle) ?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css" rel="stylesheet">
        </head>
    <body class="bg-dark text-light">
    <?php
}

// لو لم يتم العثور على الخبر
if ($notFound): ?>
    <main class="py-5">
      <div class="container">
        <div class="alert alert-warning text-center">
          <h1 class="h4 mb-2">عذراً، لم يتم العثور على الخبر المطلوب</h1>
          <p class="mb-3">قد يكون تم حذفه أو تغيير رابط الوصول إليه.</p>
          <a href="<?= h($baseUrl ?: '/') ?>" class="btn btn-primary">العودة إلى الصفحة الرئيسية</a>
        </div>
      </div>
    </main>
<?php
    require __DIR__ . '/frontend/views/partials/footer.php';
    exit;
endif;
?>

<style>
  .gdy-article-page {
    margin-top: 1.5rem;
    margin-bottom: 2rem;
  }

  .gdy-article-main-card {
    border-radius: 1.4rem;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    background: #ffffff;
    box-shadow: 0 18px 40px rgba(15,23,42,0.06);
  }

  .gdy-article-image-wrap {
    position: relative;
    height: 260px;
    overflow: hidden;
  }
  .gdy-article-image-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .gdy-article-body-wrap {
    padding: 1.5rem 1.6rem 1.8rem;
  }

  .gdy-article-title {
    font-size: clamp(1.85rem, 2.4vw, 2.45rem);
    line-height: 1.25;
    font-weight: 900;
    color: #000 !important;
    letter-spacing: -0.02em;
    margin-bottom: .55rem;
  }

  .gdy-article-meta {
    display: flex;
    flex-wrap: wrap;
    gap: .9rem;
    font-size: .95rem;
    color: #475569;
    font-weight: 600;
    margin-bottom: 1rem;
  }

  .gdy-article-meta span i {
    margin-left: 4px;
  }

  /* بطاقة معلومات الكاتب (تحت العنوان مباشرة) */
  .gdy-opinion-author-box {
    display: flex;
    align-items: center;
    gap: 0.9rem;
    padding: .8rem 1rem;
    margin-bottom: 1rem;
    border-radius: 1.1rem;
    background: radial-gradient(circle at top left, #eff6ff, #e5f5ff);
    border: 1px solid rgba(148,163,184,0.6);
    box-shadow: 0 14px 32px rgba(15,23,42,0.10);
  }

  .gdy-opinion-author-avatar {
    width: 56px;
    height: 56px;
    border-radius: 999px;
    overflow: hidden;
    flex-shrink: 0;
    border: 2px solid rgba(15,23,42,0.9);
    box-shadow:
      0 0 0 3px rgba(255,255,255,0.95),
      0 14px 28px rgba(15,23,42,0.35);
  }

  .gdy-opinion-author-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .gdy-opinion-author-main {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
  }

  .gdy-opinion-author-name {
    font-size: .95rem;
    font-weight: 800;
    color: #000 !important;
  }
  .gdy-opinion-author-name a {
    color: inherit;
    text-decoration: none;
  }
  .gdy-opinion-author-name a:hover {
    text-decoration: underline;
  }

  .gdy-opinion-author-meta {
    font-size: .8rem;
    color: #64748b;
  }

  .gdy-opinion-author-links {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    flex-wrap: wrap;
    margin-top: .15rem;
  }
  .gdy-opinion-author-links a {
    width: 26px;
    height: 26px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: .75rem;
    background: #f9fafb;
    border: 1px solid #d1d5db;
    color: #000 !important;
    text-decoration: none;
    transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease, background .16s ease;
  }
  .gdy-opinion-author-links a:hover {
    transform: translateY(-1px);
    border-color: #0ea5e9;
    box-shadow: 0 10px 24px rgba(15,23,42,0.18);
    background: #eff6ff;
  }

  .gdy-share-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: .75rem;
    padding: .75rem 1rem;
    border-radius: 999px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    font-size: .82rem;
    color: #374151;
    margin-bottom: 1.2rem;
  }

  .gdy-share-btns {
    display: flex;
    flex-wrap: wrap;
    gap: .4rem;
  }

  .gdy-share-btns a,
  .gdy-share-btns button {
    border-radius: 999px;
    padding: .25rem .7rem;
    font-size: .78rem;
    border: 0;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    text-decoration: none;
  }

  .gdy-share-fb {
    background: #1877f2;
    color: #fff;
  }
  .gdy-share-x {
    background: #020617;
    color: #e5e7eb;
  }
  .gdy-share-wa {
    background: #22c55e;
    color: #022c22;
  }
  .gdy-share-copy {
    background: #e5e7eb;
    color: #111827;
  }

  .gdy-share-btns a i,
  .gdy-share-btns button i {
    font-size: .75rem;
  }

  .gdy-article-body {
    font-size: 1.06rem;
    line-height: 2.05;
    color: #000 !important;
    word-break: break-word;
  }

  .gdy-article-body img {
    max-width: 100%;
    height: auto;
  }

  .gdy-article-body p { margin-bottom: 1.1rem; }
  .gdy-article-body h2 { font-size: 1.25rem; margin: 1.6rem 0 .7rem; font-weight: 800; }
  .gdy-article-body h3 { font-size: 1.1rem; margin: 1.3rem 0 .6rem; font-weight: 800; }
  .gdy-article-body a { color: #000 !important; text-decoration: underline; text-underline-offset: 3px; }
  .gdy-article-body blockquote { border-inline-start: 4px solid rgba(15,23,42,.25); padding: .4rem .9rem; margin: 1.2rem 0; color: #334155; background: rgba(241,245,249,.7); border-radius: .8rem; }

  .gdy-related-title {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: .6rem;
  }

  .gdy-related-item {
    display: block;
    padding: .55rem .6rem;
    border-radius: .7rem;
    text-decoration: none;
    color: #000 !important;
    font-size: .86rem;
  }
  .gdy-related-item:hover {
    background: #f3f4f6;
  }
  .gdy-related-item small {
    display: block;
    font-size: .72rem;
    color: #6b7280;
  }

  @media (max-width: 768px) {
    .gdy-article-image-wrap {
      height: 210px;
    }
    .gdy-article-body-wrap {
      padding: 1.2rem 1.1rem 1.5rem;
    }
    .gdy-share-bar {
      flex-direction: column;
      align-items: flex-start;
    }
    .gdy-opinion-author-box {
      padding: .7rem .75rem;
    }
  }
</style>

<main class="gdy-article-page">
  <div class="container">
    <div class="row g-3">
      <div class="col-lg-8">
        <article class="gdy-article-main-card">
          <?php
          $mainImg = '';
          if (!empty($article['featured_image'])) {
              $mainImg = gdy_build_image_url($baseUrl, $article['featured_image']);
          } elseif (!empty($article['image'])) {
              $mainImg = gdy_build_image_url($baseUrl, $article['image']);
          }
          ?>
          <?php if ($mainImg !== ''): ?>
            <div class="gdy-article-image-wrap">
              <img src="<?= h($mainImg) ?>"
                   alt="<?= h($title) ?>">
            </div>
          <?php endif; ?>

          <div class="gdy-article-body-wrap">
            <h1 class="gdy-article-title"><?= h($title) ?></h1>

            <div class="gdy-article-meta">
              <?php if ($catName): ?>
                <span>
                  <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  <?= h($catName) ?>
                </span>
              <?php endif; ?>

              <?php if ($createdAt): ?>
                <span>
                  <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  <?= h(date('Y-m-d', strtotime((string)$createdAt))) ?>
                </span>
              <?php endif; ?>

              <?php if (!empty($readingTime)): ?>
                <span>
                  <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  قراءة تقريبية <?= (int)$readingTime ?> دقيقة
                </span>
              <?php endif; ?>

              <?php if (!empty($views)): ?>
                <span>
                  <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  <?= number_format($views) ?> مشاهدة
                </span>
              <?php endif; ?>
            </div>

            <?php if (!empty($opinionAuthorName)): ?>
              <section class="gdy-opinion-author-box">
                <div class="gdy-opinion-author-avatar">
                  <?php if (!empty($opinionAuthorUrl)): ?>
                    <a href="<?= h($opinionAuthorUrl) ?>">
                      <img src="<?= h($opinionAuthorAvatar) ?>" alt="<?= h($opinionAuthorName) ?>">
                    </a>
                  <?php else: ?>
                    <img src="<?= h($opinionAuthorAvatar) ?>" alt="<?= h($opinionAuthorName) ?>">
                  <?php endif; ?>
                </div>

                <div class="gdy-opinion-author-main">
                  <div class="gdy-opinion-author-name">
                    <?php if (!empty($opinionAuthorUrl)): ?>
                      <a href="<?= h($opinionAuthorUrl) ?>">
                        <?= h($opinionAuthorName) ?>
                      </a>
                    <?php else: ?>
                      <?= h($opinionAuthorName) ?>
                    <?php endif; ?>
                  </div>

                  <?php if (!empty($opinionAuthorPageTitle)): ?>
                    <div class="gdy-opinion-author-meta">
                      <?= h($opinionAuthorPageTitle) ?>
                    </div>
                  <?php endif; ?>

                  <div class="gdy-opinion-author-links">
                    <?php if (!empty($opinionAuthorEmail)): ?>
                      <a href="mailto:<?= h($opinionAuthorEmail) ?>" title="البريد الإلكتروني">
                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                      </a>
                    <?php endif; ?>

                    <?php if (!empty($opinionAuthorWebsite)): ?>
                      <a href="<?= h($opinionAuthorWebsite) ?>" target="_blank" rel="noopener" title="الموقع الشخصي">
                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#globe"></use></svg>
                      </a>
                    <?php endif; ?>

                    <?php if (!empty($opinionAuthorTwitter)): ?>
                      <a href="<?= h($opinionAuthorTwitter) ?>" target="_blank" rel="noopener" title="حساب X / تويتر">
                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#x"></use></svg>
                      </a>
                    <?php endif; ?>

                    <?php if (!empty($opinionAuthorFacebook)): ?>
                      <a href="<?= h($opinionAuthorFacebook) ?>" target="_blank" rel="noopener" title="فيسبوك">
                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#facebook"></use></svg>
                      </a>
                    <?php endif; ?>
                  </div>
                </div>
              </section>
            <?php endif; ?>

            <div class="gdy-share-bar">
              <div>
                <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                <span>شارك الخبر</span>
              </div>
              <div class="gdy-share-btns">
              <button type="button" class="gdy-share-native" aria-label="مشاركة">
                <span class="gdy-ico" aria-hidden="true">⤴</span>
                <span>مشاركة</span>
              </button>
              <button type="button" class="gdy-share-copy" data-copy-url="<?= h($canonicalUrl) ?>" aria-label="نسخ الرابط">
                <span class="gdy-ico" aria-hidden="true">⧉</span>
                <span>نسخ الرابط</span>
              </button>
            </div>
            </div>

            <div class="gdy-article-body">
              <?php
              // المحتوى كما هو من لوحة التحكم
              echo $body;
              ?>
            </div>
          </div>
        </article>
      </div>

      <div class="col-lg-4">
        <?php
        // هنا يمكن وضع سايدبار: أكثر قراءة، تصنيفات، إلخ
        if (!empty($relatedArticles)): ?>
          <section class="mt-3 mt-lg-0">
            <h2 class="gdy-related-title">مقالات ذات صلة</h2>
            <div class="list-group">
              <?php foreach ($relatedArticles as $rel): ?>
                <?php
                $relTitle = (string)($rel['title'] ?? '');
                $relUrl   = rtrim($baseUrl, '/') . '/news/id/' . (int)($rel['id'] ?? 0);
                $relDate  = !empty($rel['created_at']) ? date('Y-m-d', strtotime((string)$rel['created_at'])) : '';
                ?>
                <a href="<?= h($relUrl) ?>" class="gdy-related-item">
                  <?= h($relTitle) ?>
                  <?php if ($relDate !== ''): ?>
                    <small><?= h($relDate) ?></small>
                  <?php endif; ?>
                </a>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<?php
require __DIR__ . '/frontend/views/partials/footer.php';
?>
