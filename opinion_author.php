<?php
declare(strict_types=1);

// godyar/opinion_author.php — صفحة كاتب الرأي + قائمة مقالاته / صفحة جميع كتّاب الرأي

require_once __DIR__ . '/includes/bootstrap.php';

$pdo = gdy_pdo_safe();

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * فحص وجود عمود في جدول
 */
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

/**
 * بناء رابط الخبر
 * ✅ وضع الـ ID: /news/id/{id} هو الرابط الأساسي
 * و/ news/{slug} يبقى للروابط القديمة ويتم تحويله للـ id عبر app.php
 */
function gdy_build_news_url(string $baseUrl, array $row): string
{
    $id   = isset($row['id']) ? (int)$row['id'] : 0;
    $slug = isset($row['slug']) ? trim((string)$row['slug']) : '';

    $prefix = rtrim($baseUrl, '/');
    if ($prefix !== '') {
        $prefix .= '/';
    }

    // ✅ نُفضل دائماً رابط الـ ID
    if ($id > 0) {
        return $prefix . 'news/id/' . $id;
    }

    // fallback للروابط القديمة (وسيتم تحويله للـ ID إذا كانت خريطة السلاگ متوفرة)
    if ($slug !== '') {
        return $prefix . 'news/' . rawurlencode($slug);
    }

    return $prefix;
}

/**
 * بناء رابط الصورة (يدعم روابط كاملة / نسبية)
 */
function gdy_build_image_url(string $baseUrl, ?string $path): ?string
{
    $path = trim((string)$path);
    if ($path === '') {
        return null;
    }

    if (preg_match('~^https?://~i', $path)) {
        return $path;
    }

    if ($path[0] === '/') {
        return rtrim($baseUrl, '/') . $path;
    }

    return rtrim($baseUrl, '/') . '/uploads/news/' . ltrim($path, '/');
}

/**
 * تقصير نص (للوصف / المقتطف)
 */
function gdy_str_limit(string $text, int $limit = 180): string
{
    $text = trim($text);
    if (mb_strlen($text, 'UTF-8') <= $limit) {
        return $text;
    }
    return mb_substr($text, 0, $limit, 'UTF-8') . '…';
}

// قراءة باراميترات الرابط
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';

// التحقق من الاتصال
if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo 'تعذر الاتصال بقاعدة البيانات.';
    exit;
}

$baseUrl = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') : '';

/**
 * نمطان:
 * 1) /opinion_author.php         → صفحة جميع كتّاب الرأي
 * 2) /opinion_author.php?slug=.. → صفحة كاتب محدد + مقالاته
 */
$mode   = 'single';
if ($slug === '' && $id <= 0) {
    $mode = 'list';
}

$author   = null;
$articles = [];

if ($mode === 'single') {
    // قراءة الكاتب من opinion_authors
    try {
        if ($slug !== '') {
            $stmt = $pdo->prepare("
                SELECT *
                FROM opinion_authors
                WHERE slug = :slug AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([':slug' => $slug]);
            $author = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } elseif ($id > 0) {
            $stmt = $pdo->prepare("
                SELECT *
                FROM opinion_authors
                WHERE id = :id AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([':id' => $id]);
            $author = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    } catch (Throwable $e) {
        @error_log('[opinion_author] fetch author error: ' . $e->getMessage());
        $author = null;
    }

    if (!$author) {
        http_response_code(404);

        $pageTitle       = 'الكاتب غير موجود';
        $pageDescription = 'لم يتم العثور على كاتب الرأي المطلوب.';

        require __DIR__ . '/frontend/views/partials/header.php';
        ?>
        <section class="py-5">
          <div class="container">
            <div class="alert alert-warning text-center" role="alert">
              <h1 class="h4 mb-2">عذراً، كاتب الرأي غير موجود</h1>
              <p class="mb-3">قد يكون تم حذفه أو إيقاف تفعيله.</p>
              <a href="<?= h($baseUrl ?: '/') ?>" class="btn btn-primary">
                العودة إلى الصفحة الرئيسية
              </a>
            </div>
          </div>
        </section>
        <?php
        require __DIR__ . '/frontend/views/partials/footer.php';
        exit;
    }

    // تجهيز متغيرات العرض
    $authorId       = (int)($author['id'] ?? 0);
    $authorName     = (string)($author['name'] ?? '');
    $pageTitleRaw   = (string)($author['page_title'] ?? '');
    $specialization = (string)($author['specialization'] ?? '');
    $bio            = (string)($author['bio'] ?? '');
    $email          = (string)($author['email'] ?? '');
    $website        = (string)($author['social_website'] ?? '');
    $twitter        = (string)($author['social_twitter'] ?? '');
    $facebook       = (string)($author['social_facebook'] ?? '');
    $avatarRaw      = (string)($author['avatar'] ?? '');

    $siteName = $GLOBALS['site_settings']['site.name'] ?? ($GLOBALS['site_settings']['site_name'] ?? 'Godyar News');

    $authorTitle = $pageTitleRaw !== ''
        ? $pageTitleRaw
        : ('مقالات ' . $authorName);

    $pageTitle       = $authorTitle . ' - كُتّاب الرأي';
    $pageDescription = 'قراءة أحدث مقالات الرأي بقلم ' . $authorName . ' على ' . $siteName;

    // تجهيز الصورة الرمزية
    $authorImageDefault = rtrim($baseUrl, '/') . '/assets/images/default-avatar.svg';
    $authorAvatar       = $authorImageDefault;

    if ($avatarRaw !== '') {
        if (preg_match('~^https?://~i', $avatarRaw)) {
            $authorAvatar = $avatarRaw;
        } else {
            $img = gdy_build_image_url($baseUrl, $avatarRaw);
            if ($img) {
                $authorAvatar = $img;
            }
        }
    }

    /**
     * قراءة المقالات الخاصة بالكاتب من news (استعلام مبسّط يعتمد على opinion_author_id)
     */
    $articles = [];
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id,
                title,
                slug,
                image,
                featured_image,
                excerpt,
                created_at,
                views
            FROM news
            WHERE opinion_author_id = :aid
              AND (status IS NULL OR status = 'published')
            ORDER BY id DESC
            LIMIT 60
        ");
        $stmt->execute([':aid' => $authorId]);
        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        @error_log('[opinion_author] fetch simple articles error: ' . $e->getMessage());
        $articles = [];
    }

} else {
    // وضع قائمة جميع كتّاب الرأي
    $pageTitle       = 'كتّاب الرأي';
    $pageDescription = 'استعراض جميع كتّاب الرأي النشطين في الموقع.';

    $authors = [];
    try {
        // إخفاء الكاتب "هيئة التحرير" من صفحة جميع كتّاب الرأي (القائمة فقط)
        $excludedName = 'هيئة التحرير';
        $stmt = $pdo->prepare("
            SELECT *
            FROM opinion_authors
            WHERE is_active = 1
              AND TRIM(name) <> :excluded
            ORDER BY display_order DESC, updated_at DESC, id DESC
        ");
        $stmt->execute([':excluded' => $excludedName]);
        $authors = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        @error_log('[opinion_author] list authors error: ' . $e->getMessage());
        $authors = [];
    }
}

// تهيئة الهيدر
require __DIR__ . '/frontend/views/partials/header.php';
?>
<style>
  .oa-shell {
    margin-top: 1.5rem;
    margin-bottom: 1.5rem;
  }
<?php if ($mode === 'single'): ?>
  .oa-author-card {
    display: flex;
    flex-wrap: wrap;
    gap: 1.25rem;
    padding: 1.5rem 1.6rem;
    border-radius: 1.4rem;
    background: radial-gradient(circle at top, rgba(255,255,255,0.96), rgba(var(--primary-rgb),0.06));
    border: 1px solid rgba(var(--primary-rgb),0.22);
    box-shadow:
      0 18px 40px rgba(15,23,42,0.10),
      0 14px 34px rgba(var(--primary-rgb),0.10),
      0 0 0 1px rgba(255,255,255,0.9) inset;
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(18px);
  }
  .oa-author-card::before {
    content: '';
    position: absolute;
    inset: -40%;
    background:
      radial-gradient(circle at top right, rgba(var(--primary-rgb),0.20), transparent 55%),
      radial-gradient(circle at bottom left, rgba(var(--primary-rgb),0.12), transparent 55%);
    opacity: .95;
    pointer-events: none;
    mix-blend-mode: screen;
  }
  .oa-author-avatar-wrap {
    position: relative;
    z-index: 1;
    flex: 0 0 130px;
    max-width: 130px;
    align-self: center;
  }
  .oa-author-avatar {
    width: 130px;
    height: 130px;
    border-radius: 999px;
    object-fit: cover;
    border: 3px solid rgba(var(--primary-rgb),0.78);
    box-shadow:
      0 0 0 4px rgba(255,255,255,0.95),
      0 22px 45px rgba(var(--primary-rgb),0.22);
    background: radial-gradient(circle at 20% 0%, #e5e7eb, #9ca3af);
  }
  .oa-author-meta {
    position: relative;
    z-index: 1;
    flex: 1 1 240px;
  }
  .oa-author-label {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .25rem .9rem;
    border-radius: 999px;
    background: rgba(var(--primary-rgb),0.92);
    color: var(--on-primary, #ffffff);
    font-size: .78rem;
    margin-bottom: .5rem;
    box-shadow: 0 10px 22px rgba(var(--primary-rgb),0.18);
  }
  .oa-author-label i {
    font-size: .78rem;
  }
  .oa-author-name {
    font-size: 1.6rem;
    font-weight: 800;
    color: #020617;
    margin-bottom: .25rem;
    text-shadow: 0 1px 0 rgba(255,255,255,0.9);
  }
  .oa-author-spec {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .25rem .7rem;
    border-radius: .8rem;
    background: rgba(var(--primary-rgb),0.06);
    border: 1px solid rgba(var(--primary-rgb),0.20);
    font-size: .84rem;
    color: #334155;
    margin-bottom: .6rem;
  }
  .oa-author-spec::before {
    content: '';
    width: 7px;
    height: 7px;
    border-radius: 999px;
    background: var(--primary);
    box-shadow: 0 0 0 4px rgba(var(--primary-rgb),0.16);
  }
  .oa-author-bio {
    font-size: .9rem;
    color: #111827;
    line-height: 1.8;
    max-width: 56rem;
  }
  .oa-author-meta-footer {
    display: flex;
    flex-wrap: wrap;
    gap: .6rem 1.2rem;
    align-items: center;
    margin-top: .8rem;
    font-size: .8rem;
    color: #4b5563;
  }
  .oa-author-meta-footer span i {
    color: var(--primary);
  }
  .oa-author-social {
    display: inline-flex;
    gap: .35rem;
    align-items: center;
  }
  .oa-author-social a {
    display: inline-flex;
    width: 28px;
    height: 28px;
    border-radius: 999px;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(var(--primary-rgb),0.22);
    background: rgba(255,255,255,0.92);
    color: var(--primary);
    font-size: .75rem;
    text-decoration: none;
    transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease, background .16s ease;
  }
  .oa-author-social a:hover {
    transform: translateY(-1px);
    border-color: rgba(var(--primary-rgb),0.65);
    box-shadow: 0 10px 22px rgba(var(--primary-rgb),0.18);
    background: rgba(var(--primary-rgb),0.08);
  }
<?php else: ?>
  .oa-authors-header {
    margin-bottom: 1.3rem;
  }
  .oa-authors-title {
    font-size: 1.3rem;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: .2rem;
  }
  .oa-authors-sub {
    font-size: .86rem;
    color: #6b7280;
  }
  .oa-authors-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
    gap: 1rem;
  }
  .oa-author-card-small {
    background: #ffffff;
    border-radius: 1.2rem;
    border: 1px solid #e5e7eb;
    padding: 1rem;
    display: flex;
    gap: .9rem;
    align-items: flex-start;
    box-shadow: 0 8px 20px rgba(15,23,42,0.05);
    transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
    text-decoration: none;
    color: inherit;
  }
  .oa-author-card-small:hover {
    transform: translateY(-3px);
    box-shadow: 0 18px 40px rgba(15,23,42,0.12);
    border-color: rgba(var(--primary-rgb),0.55);
  }
  .oa-author-avatar-small-wrap {
    flex: 0 0 64px;
    max-width: 64px;
  }
  .oa-author-avatar-small {
    width: 64px;
    height: 64px;
    border-radius: 999px;
    object-fit: cover;
    border: 2px solid rgba(var(--primary-rgb),0.78);
  }
  .oa-author-small-name {
    font-size: .98rem;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: .15rem;
  }
  .oa-author-small-spec {
    font-size: .8rem;
    color: #6b7280;
    margin-bottom: .3rem;
  }
  .oa-author-small-meta {
    font-size: .75rem;
    color: #9ca3af;
  }
<?php endif; ?>

  .oa-articles-header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 1rem;
    margin: 1.8rem 0 .9rem;
  }
  .oa-articles-title {
    font-size: 1.1rem;
    font-weight: 800;
    color: #0f172a;
  }
  .oa-articles-sub {
    font-size: .8rem;
    color: #6b7280;
  }
  .oa-articles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 1rem;
  }
  .oa-article-card {
    background: #ffffff;
    border-radius: 1rem;
    border: 1px solid #e5e7eb;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    min-height: 100%;
    box-shadow: 0 8px 22px rgba(15,23,42,0.05);
    transition:
      transform .18s ease,
      box-shadow .18s ease,
      border-color .18s ease;
  }
  .oa-article-card:hover {
    transform: translateY(-3px);
    border-color: rgba(var(--primary-rgb),0.55);
    box-shadow: 0 18px 40px rgba(15,23,42,0.12);
  }
  .oa-article-thumb {
    position: relative;
    padding-top: 58%;
    overflow: hidden;
    background: #e5e7eb;
  }
  .oa-article-thumb img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
  .oa-article-badge {
    position: absolute;
    inset-inline-start: .7rem;
    top: .7rem;
    padding: .15rem .6rem;
    border-radius: 999px;
    background: rgba(15,23,42,0.9);
    color: #f9fafb;
    font-size: .7rem;
  }
  .oa-article-body {
    padding: .85rem .95rem 1rem;
    display: flex;
    flex-direction: column;
    gap: .3rem;
  }
  .oa-article-title {
    font-size: .98rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 .1rem;
  }
  .oa-article-title a {
    color: inherit;
    text-decoration: none;
  }
  .oa-article-title a:hover {
    text-decoration: underline;
  }
  .oa-article-meta {
    display: flex;
    flex-wrap: wrap;
    gap: .45rem 1rem;
    font-size: .75rem;
    color: #6b7280;
  }
  .oa-article-excerpt {
    font-size: .86rem;
    color: #374151;
  }
  .oa-empty {
    padding: 1rem 1.2rem;
    border-radius: 1rem;
    background: #f9fafb;
    border: 1px dashed #d1d5db;
    color: #6b7280;
    font-size: .9rem;
  }

  @media (max-width: 768px) {
<?php if ($mode === 'single'): ?>
    .oa-author-card {
      padding: 1.2rem 1rem;
    }
    .oa-author-avatar-wrap {
      flex: 0 0 100px;
      max-width: 100px;
    }
    .oa-author-avatar {
      width: 100px;
      height: 100px;
    }
    .oa-author-name {
      font-size: 1.3rem;
    }
<?php endif; ?>
  }
</style>

<section class="oa-shell">
  <div class="container">
<?php if ($mode === 'single'): ?>
    <div class="oa-author-card mb-3">
      <div class="oa-author-avatar-wrap">
        <img src="<?= h($authorAvatar) ?>"
             alt="<?= h($authorName) ?>"
             class="oa-author-avatar">
      </div>
      <div class="oa-author-meta">
        <div class="oa-author-label">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          <span>كاتب رأي</span>
        </div>
        <h1 class="oa-author-name">
          <?= h($authorName) ?>
        </h1>
        <?php if ($specialization !== ''): ?>
          <div class="oa-author-spec">
            <?= h($specialization) ?>
          </div>
        <?php endif; ?>

        <?php if ($bio !== ''): ?>
          <div class="oa-author-bio">
            <?= nl2br(h($bio)) ?>
          </div>
        <?php endif; ?>

        <div class="oa-author-meta-footer mt-2">
          <?php if ($email !== ''): ?>
            <span><svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h($email) ?></span>
          <?php endif; ?>

          <?php if ($website !== '' || $twitter !== '' || $facebook !== ''): ?>
            <div class="oa-author-social">
              <?php if ($website !== ''): ?>
                <a href="<?= h($website) ?>" target="_blank" rel="noopener" title="الموقع الشخصي">
                  <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#globe"></use></svg>
                </a>
              <?php endif; ?>
              <?php if ($twitter !== ''): ?>
                <a href="<?= h($twitter) ?>" target="_blank" rel="noopener" title="حساب X / تويتر">
                  <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#x"></use></svg>
                </a>
              <?php endif; ?>
              <?php if ($facebook !== ''): ?>
                <a href="<?= h($facebook) ?>" target="_blank" rel="noopener" title="فيسبوك">
                  <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#facebook"></use></svg>
                </a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="oa-articles-header">
      <h2 class="oa-articles-title">
        أحدث مقالات <?= h($authorName) ?>
      </h2>
      <div class="oa-articles-sub">
        يتم عرض المقالات المرتبطة بهذا الكاتب في الموقع.
      </div>
    </div>

    <?php if (!empty($articles)): ?>
      <div class="oa-articles-grid">
        <?php foreach ($articles as $row): ?>
          <?php
          $title = (string)($row['title'] ?? '');

          // استخدام created_at كتاريخ عرض بسيط
          $rawDate = !empty($row['created_at'] ?? '') ? (string)$row['created_at'] : '';
          $date    = $rawDate !== '' ? date('Y-m-d', strtotime($rawDate)) : '';

          $views   = isset($row['views']) ? (int)$row['views'] : 0;
          $excerpt = (string)($row['excerpt'] ?? '');

          $img = (string)($row['featured_image'] ?? '');
          if ($img === '') {
              $img = (string)($row['image'] ?? '');
          }
          $imgUrl = gdy_build_image_url($baseUrl, $img) ?: (rtrim($baseUrl, '/') . '/assets/images/placeholder-thumb.jpg');

          $url = gdy_build_news_url($baseUrl, $row);
          ?>
          <article class="oa-article-card">
            <a href="<?= h($url) ?>" class="oa-article-thumb">
              <span class="oa-article-badge">مقال رأي</span>
              <img src="<?= h($imgUrl) ?>" alt="<?= h($title) ?>">
            </a>
            <div class="oa-article-body">
              <h3 class="oa-article-title">
                <a href="<?= h($url) ?>">
                  <?= h(gdy_str_limit($title, 110)) ?>
                </a>
              </h3>
              <div class="oa-article-meta">
                <?php if ($date !== ''): ?>
                  <span><svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h($date) ?></span>
                <?php endif; ?>
                <?php if ($views > 0): ?>
                  <span><svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= number_format($views) ?> مشاهدة</span>
                <?php endif; ?>
              </div>
              <?php if ($excerpt !== ''): ?>
                <div class="oa-article-excerpt">
                  <?= h(gdy_str_limit($excerpt, 190)) ?>
                </div>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="oa-empty">
        لا توجد مقالات مرتبطة بهذا الكاتب حالياً.
      </div>
    <?php endif; ?>

<?php else: // list mode ?>
    <div class="oa-authors-header">
      <h1 class="oa-authors-title">كتّاب الرأي</h1>
      <div class="oa-authors-sub">
        استعرض جميع كتّاب الرأي النشطين، واختر الكاتب لعرض ملفه ومقالاته.
      </div>
    </div>

    <?php if (!empty($authors)): ?>
      <div class="oa-authors-grid">
        <?php foreach ($authors as $a): ?>
          <?php
          $aName   = (string)($a['name'] ?? '');
          $aSpec   = (string)($a['specialization'] ?? '');
          $aSlug   = (string)($a['slug'] ?? '');
          $aAvatar = (string)($a['avatar'] ?? '');
          $aTitle  = (string)($a['page_title'] ?? '');

          $authorUrl = $aSlug !== ''
              ? (rtrim($baseUrl, '/') . '/opinion_author.php?slug=' . rawurlencode($aSlug))
              : (rtrim($baseUrl, '/') . '/opinion_author.php?id=' . (int)($a['id'] ?? 0));

          $avatarUrl = rtrim($baseUrl, '/') . '/assets/images/default-avatar.svg';
          if ($aAvatar !== '') {
              if (preg_match('~^https?://~i', $aAvatar)) {
                  $avatarUrl = $aAvatar;
              } else {
                  $img = gdy_build_image_url($baseUrl, $aAvatar);
                  if ($img) {
                      $avatarUrl = $img;
                  }
              }
          }
          ?>
          <a href="<?= h($authorUrl) ?>" class="oa-author-card-small">
            <div class="oa-author-avatar-small-wrap">
              <img src="<?= h($avatarUrl) ?>" alt="<?= h($aName) ?>" class="oa-author-avatar-small">
            </div>
            <div>
              <div class="oa-author-small-name">
                <?= h($aName) ?>
              </div>
              <?php if ($aSpec !== ''): ?>
                <div class="oa-author-small-spec">
                  <?= h($aSpec) ?>
                </div>
              <?php elseif ($aTitle !== ''): ?>
                <div class="oa-author-small-spec">
                  <?= h($aTitle) ?>
                </div>
              <?php endif; ?>
              <div class="oa-author-small-meta">
                كاتب رأي
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="oa-empty">
        لا توجد بيانات لكتّاب الرأي حالياً.
      </div>
    <?php endif; ?>
<?php endif; ?>
  </div>
</section>

<?php
require __DIR__ . '/frontend/views/partials/footer.php';
