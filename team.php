<?php
declare(strict_types=1);

// godyar/team.php — صفحة فريق العمل بتصميم موحّد مع الواجهة

require_once __DIR__ . '/includes/bootstrap.php';

$pdo = gdy_pdo_safe();

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// دالة لتقصير النص (السيرة الذاتية) لطول مناسب
if (!function_exists('short_text')) {
    function short_text(string $text, int $length = 200): string {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text, 'UTF-8') <= $length) {
                return $text;
            }
            return mb_substr($text, 0, $length, 'UTF-8') . '…';
        }

        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, $length) . '…';
    }
}

$team = [];
if ($pdo instanceof PDO) {
    try {
        $sql = "
            SELECT
                id,
                name,
                role      AS position,    -- المنصب
                email,
                bio,
                photo_url AS photo        -- رابط الصورة
            FROM team_members
            WHERE status = 'active'      -- الأعضاء النشطين فقط
            ORDER BY sort_order ASC, id ASC
        ";
        $stmt = $pdo->query($sql);
        $team = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        @error_log('[Front team] ' . $e->getMessage());
    }
}

// ميتا الصفحة
$pageTitle       = $pageTitle       ?? 'فريق العمل';
$pageDescription = $pageDescription ?? 'تعرف على فريق التحرير وفريق العمل في الموقع.';

$baseUrl = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') : '';

// الهيدر الموحد
require __DIR__ . '/frontend/views/partials/header.php';
?>

<style>
  /* خلفية خاصة لقسم الفريق – فاتحة */
  .team-section-shell {
    background: radial-gradient(circle at top, #f9fafb, #e5edf5);
    border-radius: 22px;
    padding: 18px 16px 20px;
    box-shadow: 0 18px 40px rgba(15,23,42,0.08);
    border: 1px solid #d1d5db;
    position: relative;
    overflow: hidden;
  }
  .team-section-shell::before {
    content: "";
    position: absolute;
    width: 220px;
    height: 220px;
    border-radius: 999px;
    background: radial-gradient(circle at center, rgba(56,189,248,0.16), transparent 60%);
    top: -80px;
    left: -60px;
    opacity: .7;
    pointer-events: none;
  }
  .team-section-shell::after {
    content: "";
    position: absolute;
    width: 260px;
    height: 260px;
    border-radius: 999px;
    background: radial-gradient(circle at center, rgba(129,140,248,0.14), transparent 65%);
    bottom: -120px;
    right: -80px;
    opacity: .7;
    pointer-events: none;
  }

  .team-section-title {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 4px 12px;
    border-radius: 999px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    color: #0f172a;
    font-size: .78rem;
    margin-bottom: 6px;
    position: relative;
    z-index: 1;
  }
  .team-section-title i {
    color: #0ea5e9;
    font-size: .8rem;
  }
  .team-section-shell h1 {
    color: #0f172a;
  }
  .team-section-shell p {
    color: #4b5563;
  }

  /* شبكة بطاقات الفريق: 4 بطاقات في الصف على الشاشات العريضة */
  .team-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    justify-content: flex-start;
    position: relative;
    z-index: 1;
  }
  .team-card-col {
    flex: 0 0 25%;
    max-width: 25%;
  }

  /* تجاوب مع الشاشات الأصغر */
  @media (max-width: 1200px) {
    .team-card-col { flex: 0 0 25%; max-width: 25%; }
  }
  @media (max-width: 992px) {
    .team-card-col { flex: 0 0 33.333%; max-width: 33.333%; }
  }
  @media (max-width: 768px) {
    .team-card-col { flex: 0 0 50%; max-width: 50%; }
  }
  @media (max-width: 480px) {
    .team-card-col { flex: 0 0 100%; max-width: 100%; }
  }

  /* أنيميشن خفيف للبطاقات */
  @keyframes floatCard {
    0%   { transform: translateY(0); }
    50%  { transform: translateY(-3px); }
    100% { transform: translateY(0); }
  }

  /* البطاقة مع إطار داكن متوهج */
  .team-card {
    position: relative;
    border-radius: 18px;
    overflow: hidden;
    background: #ffffff;
    color: #0f172a;
    /* إطار داكن + ظل */
    border: 1px solid rgba(15,23,42,0.45);
    box-shadow:
      0 0 0 1px rgba(15,23,42,0.35),
      0 12px 30px rgba(15,23,42,0.18);
    display: flex;
    flex-direction: column;
    height: 100%;
    transition:
      transform .22s ease,
      box-shadow .22s ease,
      border-color .22s ease,
      filter .22s ease;
    animation: floatCard 6s ease-in-out infinite;
    animation-play-state: paused;
  }

  .team-card:hover {
    transform: translateY(-8px) rotate3d(1, -1, 0, 4deg);
    /* توهج داكن قوي عند المرور */
    box-shadow:
      0 0 0 1px rgba(15,23,42,0.9),
      0 0 30px rgba(15,23,42,0.75),
      0 20px 55px rgba(15,23,42,0.35);
    border-color: rgba(15,23,42,0.95);
    filter: drop-shadow(0 0 14px rgba(15,23,42,0.8));
    animation-play-state: running;
  }

  /* إطار متوهج داكن يدور حول البطاقة */
  .team-card::before {
    content: "";
    position: absolute;
    inset: -1px;
    border-radius: 20px;
    padding: 1px;
    background: conic-gradient(
      from 180deg,
      rgba(15,23,42,0.0),
      rgba(15,23,42,0.9),
      rgba(15,23,42,0.0),
      rgba(30,64,175,0.85),
      rgba(15,23,42,0.0)
    );
    opacity: 0;
    pointer-events: none;
    transition: opacity .25s ease;
    mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
    -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
    mask-composite: exclude;
    -webkit-mask-composite: xor;
  }

  @keyframes spinBorder {
    to { transform: rotate(360deg); }
  }

  .team-card:hover::before {
    opacity: 1;
    animation: spinBorder 4s linear infinite;
  }

  /* شارة رقم العضو */
  .team-card-index {
    position: absolute;
    top: 6px;
    right: 8px;
    z-index: 3;
    padding: 2px 8px;
    border-radius: 999px;
    background: rgba(255,255,255,0.95);
    border: 1px solid #e5e7eb;
    font-size: .68rem;
    color: #374151;
    display: inline-flex;
    align-items: center;
    gap: 4px;
  }
  .team-card-index i {
    color: #f59e0b;
    font-size: .7rem;
  }

  .team-card-img-wrapper {
    position: relative;
    width: 100%;
    aspect-ratio: 4 / 3;
    overflow: hidden;
    background: linear-gradient(135deg, #e5f3ff, #f9fafb);
  }
  .team-card-img-wrapper img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transform: scale(1.04);
    transition: transform .45s ease, filter .45s ease;
    filter: grayscale(10%) contrast(1.03);
  }
  .team-card:hover .team-card-img-wrapper img {
    transform: scale(1.12);
    filter: grayscale(0%) contrast(1.06);
  }

  .team-card-img-wrapper::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(248,250,252,0.92), transparent 45%);
    pointer-events: none;
    mix-blend-mode: multiply;
  }

  .team-card-body {
    background: #f9fafb;
    padding: 10px 10px 12px;
    display: flex;
    flex-direction: column;
    text-align: center;
    position: relative;
  }

  .team-name {
    font-size: .9rem;
    font-weight: 800;
    margin-bottom: 3px;
    color: #111827;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .team-position {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 2px 9px;
    border-radius: 999px;
    background: rgba(219,234,254,0.9);
    border: 1px solid #bfdbfe;
    font-size: .78rem;
    color: #1d4ed8;
    margin-bottom: 4px;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .team-position i {
    font-size: .7rem;
    opacity: .9;
  }

  .team-email {
    font-size: .78rem;
    color: #0ea5e9;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .team-email a {
    color: inherit;
    text-decoration: none;
  }
  .team-email a:hover {
    text-decoration: underline;
  }

  .team-card-body::before {
    content: "";
    width: 40%;
    height: 1px;
    margin: 5px auto 6px;
    background: linear-gradient(to right, transparent, rgba(148,163,184,0.9), transparent);
    opacity: .35;
  }

  .team-bio {
    font-size: .76rem;
    color: #4b5563;
    margin: 0;
    margin-top: 4px;
    line-height: 1.6;
  }

  .team-footer-tag {
    margin-top: 6px;
    font-size: .7rem;
    color: #6b7280;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    opacity: .9;
  }
  .team-footer-tag i {
    font-size: .7rem;
    color: #16a34a;
  }

  /* تأخير بسيط في الأنيميشن لكل بطاقة */
  .team-card-col:nth-child(1) .team-card { animation-delay: .1s; }
  .team-card-col:nth-child(2) .team-card { animation-delay: .3s; }
  .team-card-col:nth-child(3) .team-card { animation-delay: .5s; }
  .team-card-col:nth-child(4) .team-card { animation-delay: .7s; }
  .team-card-col:nth-child(5) .team-card { animation-delay: .9s; }
</style>

<div class="row g-4">
  <div class="col-12 col-lg-9">
    <div class="team-section-shell">
      <div class="team-section-title">
        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
        <span>فريق العمل التحريري والتقني</span>
      </div>
      <h1 class="h4 mb-2">فريق العمل</h1>
      <p class="mb-3">
        يضم هذا الفريق المحررين، المراسلين، وفريق العمليات التقنية المسؤول عن تشغيل المنصة الإخبارية وتطويرها.
      </p>

      <div class="team-grid">
        <?php if (!empty($team)): ?>
          <?php $i = 0; ?>
          <?php foreach ($team as $m): ?>
            <?php
              $i++;
              $name     = $m['name']      ?? '';
              $position = $m['position']  ?? '';
              $email    = $m['email']     ?? '';
              $photo    = $m['photo']     ?? '';
              $bioFull  = $m['bio']       ?? '';
              $bioShort = short_text($bioFull, 200);
            ?>
            <div class="team-card-col">
              <div class="team-card">
                <div class="team-card-index">
                  <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  <span>#<?= (int)$i ?></span>
                </div>

                <div class="team-card-img-wrapper">
                  <?php if (!empty($photo)): ?>
                    <img src="<?= h($photo) ?>" alt="<?= h($name) ?>">
                  <?php else: ?>
                    <div class="d-flex align-items-center justify-content-center" style="width:100%;height:100%;">
                      <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg>
                    </div>
                  <?php endif; ?>
                </div>

                <div class="team-card-body">
                  <?php if ($name): ?>
                    <div class="team-name"><?= h($name) ?></div>
                  <?php endif; ?>

                  <?php if ($position): ?>
                    <div class="team-position">
                      <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                      <span><?= h($position) ?></span>
                    </div>
                  <?php endif; ?>

                  <?php if ($email): ?>
                    <div class="team-email">
                      <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                      <a href="mailto:<?= h($email) ?>"><?= h($email) ?></a>
                    </div>
                  <?php endif; ?>

                  <?php if ($bioShort): ?>
                    <p class="team-bio"><?= nl2br(h($bioShort)) ?></p>
                  <?php endif; ?>

                  <div class="team-footer-tag">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                    <span>عضو ضمن فريق Godyar</span>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="alert alert-info mb-0 w-100">
            لا توجد بيانات لفريق العمل حتى الآن.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-3">
    <?php
    // الشريط الجانبي الموحد
    require __DIR__ . '/frontend/views/partials/sidebar.php';
    ?>
  </div>
</div>

<?php
// فوتر موحد
require __DIR__ . '/frontend/views/partials/footer.php';
