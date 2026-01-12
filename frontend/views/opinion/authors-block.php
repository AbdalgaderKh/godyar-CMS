<?php
declare(strict_types=1);

/**
 * بلوك كُتّاب الرأي + آخر مقالاتهم
 * يعتمد على:
 *  - جدول news  (به الحقل opinion_author_id)
 *  - جدول opinion_authors (avatar, page_title, name, social_facebook, email, social_website, social_twitter)
 */

$pdo = gdy_pdo_safe();
$limit = isset($limit) && (int)$limit > 0 ? (int)$limit : 4;

$items = [];

if ($pdo instanceof PDO) {
    try {
        $sql = "
            SELECT 
                n.id,
                n.title,
                n.slug,
                n.excerpt,
                n.content,
                n.image,
                n.published_at,
                oa.id              AS opinion_author_id,
                oa.name            AS author_name,
                oa.page_title      AS author_page_title,
                oa.avatar          AS author_avatar,
                oa.social_website  AS author_website,
                oa.social_twitter  AS author_twitter,
                oa.social_facebook AS author_facebook,
                oa.email           AS author_email
            FROM news n
            INNER JOIN opinion_authors oa 
                ON n.opinion_author_id = oa.id
            WHERE 
                n.status = 'published'
            ORDER BY n.published_at DESC
            LIMIT :limit
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Opinion block error: ' . $e->getMessage());
        $items = [];
    }
}

// دالة مساعدة للتقصير
if (!function_exists('gdy_trim_text')) {
    function gdy_trim_text(string $text, int $length = 180): string {
        $text = strip_tags($text);
        if (function_exists('mb_strlen')) {
            if (mb_strlen($text, 'UTF-8') <= $length) {
                return $text;
            }
            return mb_substr($text, 0, $length, 'UTF-8') . '...';
        }
        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, $length) . '...';
    }
}
?>

<?php if (!empty($items)) { ?>
<style>
    .opinion-block {
        background: linear-gradient(135deg, rgba(var(--primary-rgb), .07), rgba(255,255,255,.96));
        border-radius: 1rem;
        padding: 1.5rem;
        margin-bottom: 2rem;
        border: 1px solid rgba(var(--primary-rgb), .18);
        color: #0f172a;
        direction: rtl;
        font-family: "Cairo", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }
    .opinion-block-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
    }
    .opinion-block-title {
        display: flex;
        align-items: center;
        gap: .5rem;
        font-size: 1.1rem;
        font-weight: 700;
    }
    .opinion-block-title i {
        color: var(--primary);
    }
    .opinion-items {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 1.25rem;
    }
    .opinion-card {
        background: rgba(255,255,255,.92);
        border-radius: .9rem;
        border: 1px solid rgba(var(--primary-rgb), .16);
        padding: 1rem;
        display: flex;
        flex-direction: column;
        gap: .75rem;
        transition: all .2s ease;
    }
    .opinion-card:hover {
        border-color: rgba(var(--primary-rgb), .55);
        box-shadow: 0 14px 30px rgba(15,23,42,.10);
        transform: translateY(-2px);
    }
    .opinion-author {
        display: grid;
        grid-template-columns: 64px 1fr;
        gap: .75rem;
        align-items: center;
    }
    .opinion-author-avatar {
        width: 64px;
        height: 64px;
        border-radius: 999px;
        overflow: hidden;
        border: 2px solid rgba(var(--primary-rgb), .55);
        background: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .opinion-author-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .opinion-author-info {
        display: flex;
        flex-direction: column;
        gap: .15rem;
    }
    .opinion-author-page {
        font-size: .8rem;
        color: var(--primary-dark);
        font-weight: 800;
    }
    .opinion-author-name {
        font-size: .9rem;
        font-weight: 700;
    }
    .opinion-author-social {
        display: flex;
        flex-wrap: wrap;
        gap: .4rem;
        margin-top: .2rem;
    }
    .opinion-author-social a {
        width: 24px;
        height: 24px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: .8rem;
        text-decoration: none;
        background: rgba(var(--primary-rgb), .08);
        color: var(--primary-dark);
        border: 1px solid rgba(var(--primary-rgb), .22);
        transition: all .2s ease;
    }
    .opinion-author-social a:hover {
        background: var(--primary);
        border-color: var(--primary);
        color: #ffffff;
    }
    .opinion-article-title {
        margin-top: .5rem;
        font-size: .95rem;
        font-weight: 700;
    }
    .opinion-article-title a {
        color: #0f172a;
        text-decoration: none;
    }
    .opinion-article-title a:hover {
        color: var(--primary-dark);
    }
    .opinion-article-text {
        font-size: .85rem;
        color: #334155;
        line-height: 1.6;
    }
    .opinion-article-meta {
        font-size: .75rem;
        color: #64748b;
        margin-top: .3rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .opinion-article-meta i {
        margin-left: .25rem;
    }
    @media (max-width: 576px) {
        .opinion-block {
            padding: 1.1rem;
        }
    }
</style>

<div class="opinion-block">
    <div class="opinion-block-header">
        <div class="opinion-block-title">
            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <span>آراء وكتاب</span>
        </div>
    </div>

    <div class="opinion-items">
        <?php foreach ($items as $row) { ?>
            <article class="opinion-card">
                <div class="opinion-author">
                    <div class="opinion-author-avatar">
                        <?php if (!empty($row['author_avatar'])) { ?>
                            <img src="<?= htmlspecialchars($row['author_avatar'], ENT_QUOTES, 'UTF-8') ?>"
                                 alt="<?= htmlspecialchars($row['author_name'], ENT_QUOTES, 'UTF-8') ?>">
                        <?php } else { ?>
                            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg>
                        <?php } ?>
                    </div>
                    <div class="opinion-author-info">
                        <?php if (!empty($row['author_page_title'])) { ?>
                            <div class="opinion-author-page">
                                <?= htmlspecialchars($row['author_page_title'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php } ?>
                        <div class="opinion-author-name">
                            <?= htmlspecialchars($row['author_name'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div class="opinion-author-social">
                            <?php if (!empty($row['author_facebook'])) { ?>
                                <a href="<?= htmlspecialchars($row['author_facebook'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" title="فيسبوك">
                                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#facebook"></use></svg>
                                </a>
                            <?php } ?>
                            <?php if (!empty($row['author_email'])) { ?>
                                <a href="mailto:<?= htmlspecialchars($row['author_email'], ENT_QUOTES, 'UTF-8') ?>" title="البريد الإلكتروني">
                                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                </a>
                            <?php } ?>
                            <?php if (!empty($row['author_website'])) { ?>
                                <a href="<?= htmlspecialchars($row['author_website'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" title="الموقع الشخصي">
                                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#globe"></use></svg>
                                </a>
                            <?php } ?>
                            <?php if (!empty($row['author_twitter'])) { ?>
                                <a href="<?= htmlspecialchars($row['author_twitter'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" title="تويتر">
                                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#x"></use></svg>
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <div class="opinion-article-title">
                    <?php
                    $slug = !empty($row['slug']) ? $row['slug'] : ('news-' . (int)$row['id']);
                    $url  = '/news/id/' . (int)$id;
                    ?>
                    <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </div>

                <div class="opinion-article-text">
                    <?php
                    $text = $row['excerpt'] ?: $row['content'];
                    echo htmlspecialchars(gdy_trim_text((string)$text, 220), ENT_QUOTES, 'UTF-8');
                    ?>
                </div>

                <div class="opinion-article-meta">
                    <span>
                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                        <?= htmlspecialchars(date('Y-m-d', strtotime((string)$row['published_at'])), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <span>
                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                        قراءة المقال
                    </span>
                </div>
            </article>
        <?php } ?>
    </div>
</div>
<?php } ?>
