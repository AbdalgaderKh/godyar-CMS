<?php
declare(strict_types=1);

// godyar/elections.php — صفحة عرض نتائج الانتخابات مع خريطة السودان

require_once __DIR__ . '/includes/bootstrap.php';

// السماح باستخدام مكتبة الانتخابات المشتركة في صفحات الواجهة العامة
define('GDY_ALLOW_PUBLIC_ELECTIONS', true);
require_once __DIR__ . '/admin/elections/_elections_lib.php';

$pdo = gdy_pdo_safe();

if (!($pdo instanceof PDO)) {
    http_response_code(500);
    echo 'تعذّر الاتصال بقاعدة البيانات.';
    exit;
}

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// =====================
// 1) جلب كل التغطيات الظاهرة
// =====================
$allElections = [];
try {
    $stmt = $pdo->query("
        SELECT id, title, slug, status, total_seats, majority_seats, description
        FROM elections
        WHERE status = 'visible'
        ORDER BY created_at DESC
    ");
    $allElections = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    @error_log('[Elections] fetch all elections error: ' . $e->getMessage());
}

if (!$allElections) {
    require __DIR__ . '/frontend/templates/header.php';
    ?>
    <div class="gdy-elections-page">
      <div class="alert alert-info my-4">
        لا توجد تغطية انتخابية متاحة حاليًا.
      </div>
    </div>
    <?php
    require __DIR__ . '/frontend/templates/footer.php';
    exit;
}

// =====================
// 2) تحديد التغطية الحالية
// =====================

// دعم ?election= و ?slug= للروابط القديمة
$requestedSlug = '';
if (!empty($_GET['election'])) {
    $requestedSlug = trim((string)$_GET['election']);
} elseif (!empty($_GET['slug'])) {
    $requestedSlug = trim((string)$_GET['slug']);
}

$currentElection = null;

if ($requestedSlug !== '') {
    foreach ($allElections as $e) {
        if ((string)$e['slug'] === $requestedSlug) {
            $currentElection = $e;
            break;
        }
    }
}
if (!$currentElection) {
    $currentElection = $allElections[0];
    $requestedSlug   = (string)$currentElection['slug'];
}

$electionId    = (int)$currentElection['id'];
$totalSeats    = (int)($currentElection['total_seats'] ?? 0);
$majoritySeats = (int)($currentElection['majority_seats'] ?? 0);

// =====================
// 3) جلب ملخص الأحزاب
// =====================
$parties = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.short_name,
            p.full_name,
            p.color_hex,
            p.logo_path,
            COALESCE(s.seats_won, 0)      AS seats_won,
            COALESCE(s.seats_leading, 0)  AS seats_leading,
            COALESCE(s.votes, 0)          AS votes,
            s.vote_percent
        FROM election_parties p
        LEFT JOIN election_results_summary s
          ON s.party_id = p.id
         AND s.election_id = ?
        WHERE p.election_id = ?
        ORDER BY s.seats_won DESC,
                 s.seats_leading DESC,
                 p.sort_order ASC,
                 p.id ASC
    ");
    $stmt->execute([$electionId, $electionId]);
    $parties = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    @error_log('[Elections] fetch parties summary error: ' . $e->getMessage());
}

$totalWon     = 0;
$totalLeading = 0;
foreach ($parties as $p) {
    $totalWon     += (int)$p['seats_won'];
    $totalLeading += (int)$p['seats_leading'];
}

// الحزب المتصدر على مستوى البلد
$overallLeader = null;
foreach ($parties as $p) {
    $score = [
        (int)$p['seats_won'],
        (int)$p['seats_leading'],
        (int)$p['votes'],
    ];
    if ($overallLeader === null || $score > $overallLeader['_score']) {
        $overallLeader = $p;
        $overallLeader['_score'] = $score;
    }
}

// =====================
// 4) جلب بيانات المناطق/الولايات + نتائجها
// =====================
$regions           = [];
$regionsMapPayload = [];

// مسار المجلد الحالي في الـ URL (هنا جذر الموقع أو مجلد godyar)
$scriptDir = rtrim(dirname($_SERVER['PHP_SELF'] ?? ''), '/\\');

try {
    $stmt = $pdo->prepare("
        SELECT
            r.id           AS region_id,
            r.name_ar,
            r.name_en,
            r.slug,
            r.map_code,
            r.total_seats,
            r.sort_order,
            rr.party_id,
            rr.seats_won,
            rr.seats_leading,
            rr.votes,
            rr.vote_percent,
            p.short_name   AS party_short_name,
            p.color_hex    AS party_color_hex
        FROM election_regions r
        LEFT JOIN election_results_regions rr
          ON rr.region_id = r.id
         AND rr.election_id = ?
        LEFT JOIN election_parties p
          ON p.id = rr.party_id
        WHERE r.election_id = ?
        ORDER BY r.sort_order ASC, r.id ASC
    ");
    $stmt->execute([$electionId, $electionId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as $row) {
        $rid = (int)$row['region_id'];

        if (!isset($regions[$rid])) {
            $regions[$rid] = [
                'id'          => $rid,
                'name_ar'     => $row['name_ar'],
                'name_en'     => $row['name_en'],
                'slug'        => $row['slug'],
                'map_code'    => $row['map_code'],
                'total_seats' => (int)($row['total_seats'] ?? 0),
                'parties'     => [],
            ];
        }

        if ($row['party_id'] !== null) {
            $regions[$rid]['parties'][] = [
                'party_id'      => (int)$row['party_id'],
                'short_name'    => $row['party_short_name'],
                'color_hex'     => $row['party_color_hex'] ?: '#94a3b8',
                'seats_won'     => (int)($row['seats_won'] ?? 0),
                'seats_leading' => (int)($row['seats_leading'] ?? 0),
                'votes'         => (int)($row['votes'] ?? 0),
                'vote_percent'  => $row['vote_percent'] !== null ? (float)$row['vote_percent'] : null,
            ];
        }
    }

    foreach ($regions as $rid => &$region) {
        // حساب الحزب المتصدر في هذه الولاية
        $leader = null;

        foreach ($region['parties'] as $rp) {
            $score = [
                (int)$rp['seats_won'],
                (int)$rp['seats_leading'],
                (int)$rp['votes'],
            ];
            if ($leader === null || $score > $leader['_score']) {
                $leader = $rp;
                $leader['_score'] = $score;
            }
        }

        if ($leader !== null) {
            $region['leading_party_id']       = $leader['party_id'];
            $region['leading_party_short']    = $leader['short_name'];
            $region['leading_color']          = $leader['color_hex'];
            $region['leading_seats_won']      = $leader['seats_won'];
            $region['leading_seats_leading']  = $leader['seats_leading'];
            $region['leading_vote_percent']   = $leader['vote_percent'];
        } else {
            $region['leading_party_id']       = null;
            $region['leading_party_short']    = null;
            $region['leading_color']          = '#94a3b8';
            $region['leading_seats_won']      = 0;
            $region['leading_seats_leading']  = 0;
            $region['leading_vote_percent']   = null;
        }

        $rawCode = trim((string)($region['map_code'] ?? ''));
        $code    = strtoupper($rawCode);
        if ($code !== '') {
            // رابط صفحة تفاصيل الولاية
            // لو الملف داخل /godyar/ يصبح مثلاً /godyar/election_region.php
            $detailUrl = $scriptDir . '/election_region.php?election='
                       . rawurlencode($requestedSlug)
                       . '&code='
                       . rawurlencode($code);

            // نستخدم $code (حروف كبيرة) كمفتاح ثابت لكي يتطابق مع أكواد الخريطة والصفحة التفصيلية
            $regionsMapPayload[$code] = [
                'name'             => $region['name_ar'] ?: $region['name_en'],
                'map_code'         => $rawCode,
                'leading_party'    => $region['leading_party_short'],
                'leading_party_id' => $region['leading_party_id'],
                'leading_color'    => $region['leading_color'],
                'seats_won'        => $region['leading_seats_won'],
                'seats_leading'    => $region['leading_seats_leading'],
                'total_seats'      => $region['total_seats'],
                'vote_percent'     => $region['leading_vote_percent'],
                'detail_url'       => $detailUrl,
            ];
        }
    }
    unset($region);
} catch (Throwable $e) {
    @error_log('[Elections] fetch regions error: ' . $e->getMessage());
}

// =====================
// 5) الهيدر
// =====================
require __DIR__ . '/frontend/templates/header.php';
?>

<link rel="stylesheet" href="/assets/css/elections.css">

<style>
  /* Tooltip خريطة السودان (تحسين الشكل العام) */
  .gdy-elections-page .gdy-el-map-wrapper {
    position: relative;
  }
  .gdy-elections-page .gdy-el-map-tooltip {
    position: absolute;
    min-width: 230px;
    max-width: 280px;
    background: radial-gradient(circle at top left, rgba(37, 99, 235, 0.45), #020617 55%);
    border-radius: 14px;
    border: 1px solid rgba(148, 163, 184, 0.9);
    padding: 0.6rem 0.8rem;
    font-size: 0.78rem;
    color: #e5e7eb;
    pointer-events: none;
    z-index: 30;
    transform: translate(-50%, -115%);
    box-shadow: 0 18px 34px rgba(15, 23, 42, 0.95);
    opacity: 0;
    transition: opacity 0.12s ease-out;
  }
  .gdy-elections-page .gdy-el-map-tooltip::after {
    content: "";
    position: absolute;
    left: 50%;
    bottom: -7px;
    transform: translateX(-50%);
    border-width: 7px 7px 0 7px;
    border-style: solid;
    border-color: rgba(148, 163, 184, 0.9) transparent transparent transparent;
  }
  .gdy-elections-page .gdy-el-map-tooltip.is-visible {
    opacity: 1;
  }
  .gdy-elections-page .gdy-el-map-tooltip-title {
    font-weight: 600;
    margin-bottom: 0.15rem;
    color: #f9fafb;
    font-size: 0.82rem;
  }
  .gdy-elections-page .gdy-el-map-tooltip-body div + div {
    margin-top: 0.15rem;
  }
</style>

<div class="gdy-elections-page">

  <!-- كرت الهيرو / معلومات عامة -->
  <div class="gdy-el-hero-card card mb-4">
    <div class="card-body">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
          <h1 class="h4 mb-1">
            نتائج الانتخابات – <?= h($currentElection['title']) ?>
          </h1>
          <?php if (!empty($currentElection['description'])): ?>
            <p class="mb-0 small text-muted">
              <?= nl2br(h($currentElection['description'])) ?>
            </p>
          <?php endif; ?>

          <!-- شريط المشاركة -->
          <div class="gdy-el-share-row mt-2">
            <span class="small text-muted me-2">شارك التغطية:</span>
            <button type="button"
                    class="btn btn-sm gdy-el-share-btn"
                    data-share="whatsapp">واتساب</button>
            <button type="button"
                    class="btn btn-sm gdy-el-share-btn"
                    data-share="x">X (تويتر)</button>
            <button type="button"
                    class="btn btn-sm gdy-el-share-btn"
                    data-share="facebook">فيسبوك</button>
            <button type="button"
                    class="btn btn-sm gdy-el-share-btn"
                    data-share="copy">نسخ الرابط</button>
            <span class="small text-success ms-2 d-none" id="gdy-el-share-copied">
              تم نسخ الرابط ✔
            </span>
          </div>
        </div>

        <div class="d-flex flex-column flex-sm-row gap-2 align-items-sm-center">
          <!-- اختيار التغطية -->
          <?php if (count($allElections) > 1): ?>
            <form method="get" class="d-flex align-items-center gap-2">
              <label for="gdy-el-elections-select" class="small text-muted mb-0">
                اختر تغطية:
              </label>
              <select id="gdy-el-elections-select"
                      name="election"
                      class="form-select form-select-sm" js-auto-submit"
                     >
                <?php foreach ($allElections as $el): ?>
                  <option value="<?= h($el['slug']) ?>" <?= ((int)$el['id'] === $electionId ? 'selected' : '') ?>>
                    <?= h($el['title']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </form>
          <?php endif; ?>

          <!-- الحالة + أرقام عامة -->
          <div class="d-flex flex-wrap gap-2 justify-content-end">
            <?php /* status badge removed */ ?>

            <?php if ($totalSeats > 0): ?>
              <span class="gdy-el-metric-pill">
                إجمالي المقاعد / الدوائر: <strong><?= $totalSeats ?></strong>
                <?php if ($majoritySeats > 0): ?>
                  <span class="text-muted"> – الأغلبية: <?= $majoritySeats ?></span>
                <?php endif; ?>
              </span>
            <?php endif; ?>

            <?php if ($overallLeader): ?>
              <span class="gdy-el-metric-pill">
                <span class="gdy-el-party-dot"
                      style="background: <?= h($overallLeader['color_hex'] ?: '#22c55e') ?>"></span>
                المتصدر: <strong><?= h($overallLeader['short_name']) ?></strong>
              </span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- فلاتر عامة -->
  <div class="card mb-4">
    <div class="card-body py-3">
      <div class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label small text-muted mb-1">
            فلترة الولايات حسب الحزب المتصدر
          </label>
          <select id="gdy-el-party-filter" class="form-select form-select-sm">
            <option value="">كل الأحزاب</option>
            <?php foreach ($parties as $p): ?>
              <option value="<?= (int)$p['id'] ?>">
                <?= h($p['short_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label small text-muted mb-1">
            ترتيب جدول الأحزاب حسب المقاعد
          </label>
          <select id="gdy-el-party-sort" class="form-select form-select-sm">
            <option value="default">الترتيب الافتراضي</option>
            <option value="seats_desc">الأكثر مقاعدًا (تنازلي)</option>
            <option value="seats_asc">الأقل مقاعدًا (تصاعدي)</option>
          </select>
        </div>

        <div class="col-md-4">
          <p class="small text-muted mb-0">
            مرّر فوق أي ولاية أو منطقة على الخريطة لرؤية
            <strong>اسمها</strong> و<strong>الحزب المتصدر</strong>،
            مع <strong>المقاعد</strong> و<strong>الدوائر</strong> ونِسَب الأصوات.
            انقر على الولاية لعرض تفاصيلها الكاملة.
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- خريطة السودان + جدول الولايات -->
  <div class="row g-4 mb-4">
    <div class="col-lg-7">
      <div class="gdy-el-map-card card h-100">
        <div class="card-header">
          <h2 class="h6 mb-0">خريطة ولايات السودان</h2>
        </div>
        <div class="card-body">
          <div class="gdy-el-map-wrapper">
            <div id="gdy-el-map-tooltip" class="gdy-el-map-tooltip"></div>
            <?= gdy_elections_sudan_svg_map(); ?>
          </div>

          <?php if ($parties): ?>
            <div class="gdy-el-map-legend mt-3">
              <?php foreach ($parties as $p): ?>
                <?php $color = $p['color_hex'] ?: '#38bdf8'; ?>
                <span class="gdy-el-map-legend-item">
                  <span class="gdy-el-map-legend-swatch"
                        style="background: <?= h($color) ?>"></span>
                  <span class="small"><?= h($p['short_name']) ?></span>
                </span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="gdy-el-regions-card card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h2 class="h6 mb-0">توزيع النتائج حسب الولايات / المناطق</h2>
          <span class="small text-muted">
            إجمالي: <?= count($regions) ?>
          </span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
              <thead>
                <tr>
                  <th>الولاية / المنطقة</th>
                  <th class="text-center">المقاعد / الدوائر</th>
                  <th>الحزب المتصدر</th>
                </tr>
              </thead>
              <tbody id="gdy-el-regions-tbody">
                <?php foreach ($regions as $reg): ?>
                  <tr data-region-row="1"
                      data-party-id="<?= $reg['leading_party_id'] ? (int)$reg['leading_party_id'] : '' ?>">
                    <td><?= h($reg['name_ar'] ?: $reg['name_en']) ?></td>
                    <td class="text-center">
                      <span class="badge bg-dark-subtle text-light">
                        <?= (int)$reg['leading_seats_won'] ?> / <?= (int)$reg['total_seats'] ?>
                      </span>
                      <?php if ((int)$reg['leading_seats_leading'] > 0): ?>
                        <span class="badge bg-warning text-dark ms-1">
                          +<?= (int)$reg['leading_seats_leading'] ?> متقدم
                        </span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($reg['leading_party_short']): ?>
                        <span class="gdy-el-leading-badge">
                          <span class="gdy-el-party-dot"
                                style="background: <?= h($reg['leading_color']) ?>"></span>
                          <?= h($reg['leading_party_short']) ?>
                        </span>
                      <?php else: ?>
                        <span class="text-muted small">لا بيانات</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$regions): ?>
                  <tr>
                    <td colspan="3" class="text-center text-muted small py-3">
                      لا توجد بيانات ولايات أو مناطق لهذه التغطية بعد.
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- جدول الأحزاب -->
  <div class="gdy-el-parties-card card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h2 class="h6 mb-0">ملخص النتائج حسب الحزب</h2>
      <span class="small text-muted">
        المقاعد المحسومة: <?= $totalWon ?>
        <?php if ($totalLeading > 0): ?>
          – المتقدمة: <?= $totalLeading ?>
        <?php endif; ?>
      </span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle" id="gdy-el-parties-table">
          <thead>
            <tr>
              <th>الحزب</th>
              <th class="text-center">المقاعد المحسومة</th>
              <th class="text-center">المقاعد المتقدمة</th>
              <th class="text-center">إجمالي المقاعد</th>
              <th class="text-center">الأصوات</th>
              <th class="text-center">النسبة %</th>
            </tr>
          </thead>
          <tbody id="gdy-el-parties-tbody">
            <?php foreach ($parties as $p): ?>
              <?php
                $color           = $p['color_hex'] ?: '#38bdf8';
                $totalSeatsParty = (int)$p['seats_won'] + (int)$p['seats_leading'];
              ?>
              <tr data-party-row="1"
                  data-party-id="<?= (int)$p['id'] ?>"
                  data-total-seats="<?= $totalSeatsParty ?>">
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <span class="gdy-el-party-dot" style="background: <?= h($color) ?>"></span>
                    <div>
                      <div class="fw-semibold"><?= h($p['short_name']) ?></div>
                      <?php if (!empty($p['full_name'])): ?>
                        <div class="small text-muted"><?= h($p['full_name']) ?></div>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>
                <td class="text-center"><?= (int)$p['seats_won'] ?></td>
                <td class="text-center"><?= (int)$p['seats_leading'] ?></td>
                <td class="text-center"><strong><?= $totalSeatsParty ?></strong></td>
                <td class="text-center">
                  <?= $p['votes'] ? number_format((int)$p['votes']) : '—' ?>
                </td>
                <td class="text-center">
                  <?= $p['vote_percent'] !== null ? number_format((float)$p['vote_percent'], 2) . '%' : '—' ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$parties): ?>
              <tr>
                <td colspan="6" class="text-center text-muted small py-3">
                  لا توجد بيانات أحزاب لهذه التغطية بعد.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div><!-- /.gdy-elections-page -->

<script>
document.addEventListener('DOMContentLoaded', function () {
  // ==========================
  // 1) خريطة السودان: تلوين + تلميح + فتح صفحة الولاية
  // ==========================
  const regionsData = <?= json_encode($regionsMapPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> || {};
  const tooltipEl   = document.getElementById('gdy-el-map-tooltip');
  const regionBaseUrl = <?= json_encode($scriptDir . '/election_region.php?election=' . rawurlencode($requestedSlug) . '&code=') ?>;

  function positionTooltip(ev, rootEl) {
    if (!tooltipEl || !rootEl) return;
    const rect = rootEl.getBoundingClientRect();
    const x = ev.clientX - rect.left;
    const y = ev.clientY - rect.top;
    tooltipEl.style.left = x + 'px';
    tooltipEl.style.top  = y + 'px';
  }

  function setupSudanMap(rootEl) {
    if (!rootEl) return;

    const clickable = rootEl.querySelectorAll('[data-code], path[id], g[id]');
    if (!clickable.length) return;

    const hasTooltip = !!tooltipEl;

    clickable.forEach(function (el) {
      let rawCode = el.getAttribute('data-code') || el.getAttribute('id') || '';
      if (!rawCode) return;

      const keyUpper = rawCode.toUpperCase().replace(/[^A-Z0-9_]/g, '');
      const info =
        regionsData[keyUpper] ||
        regionsData[rawCode] ||
        regionsData[rawCode.toUpperCase()] ||
        {};

      const detailUrl =
        (info && info.detail_url) ||
        (regionBaseUrl + encodeURIComponent(keyUpper || rawCode));

      // تلوين الخريطة بالحزب المتصدر إن وُجد
      if (info && info.leading_color) {
        try {
          el.style.fill = info.leading_color;
        } catch (e) {
          // بعض العناصر قد لا تدعم fill
        }
      }

      // جعل المنطقة قابلة للنقر دائماً لفتح صفحة الولاية
      el.style.cursor = 'pointer';
      el.addEventListener('click', function () {
        if (detailUrl) {
          window.location.href = detailUrl;
        }
      });

      if (!hasTooltip) return;

      el.addEventListener('mouseenter', function (ev) {
        const name   = (info && info.name) || rawCode;
        const party  = (info && info.leading_party) || 'لا بيانات';
        const seatsW = (info && info.seats_won) || 0;
        const seatsL = (info && info.seats_leading) || 0;
        const total  = (info && info.total_seats) || 0;
        const pct    =
          (info && info.vote_percent !== null && info.vote_percent !== undefined)
            ? Number(info.vote_percent).toFixed(2) + '%'
            : null;

        let bodyHtml = '';
        bodyHtml += '<div>الحزب المتصدر: <strong>' + party + '</strong></div>';
        if (total) {
          bodyHtml += '<div>المقاعد / الدوائر المحسومة: <strong>' +
                      seatsW + '</strong> من ' + total + '</div>';
        }
        if (seatsL) {
          bodyHtml += '<div>المقاعد / الدوائر المتقدمة: <strong>' +
                      seatsL + '</strong></div>';
        }
        if (pct) {
          bodyHtml += '<div>نسبة الأصوات في آخر جولة: <strong>' +
                      pct + '</strong></div>';
        }
        if (detailUrl) {
          bodyHtml += '<div class="mt-1 small" style="opacity:0.8;">' +
                      'انقر لعرض تفاصيل الولاية: السكان، أهم المدن، الأحزاب، الدوائر…' +
                      '</div>';
        }

        tooltipEl.innerHTML =
          '<div class="gdy-el-map-tooltip-title">' + name + '</div>' +
          '<div class="gdy-el-map-tooltip-body">' + bodyHtml + '</div>';

        tooltipEl.classList.add('is-visible');
        positionTooltip(ev, rootEl);
      });

      el.addEventListener('mousemove', function (ev) {
        positionTooltip(ev, rootEl);
      });

      el.addEventListener('mouseleave', function () {
        tooltipEl.classList.remove('is-visible');
      });
    });
  }

  // نحاول إيجاد الخريطة:
  // 1) <svg id="gdy-election-map">
  // 2) أول <svg> داخل .gdy-el-map-wrapper
  // 3) أو <object>/<embed> يحتوي الـ SVG
  let container = document.querySelector('.gdy-el-map-wrapper');
  let mapEl = document.getElementById('gdy-election-map');

  if (!mapEl && container) {
    mapEl = container.querySelector('svg') || container.querySelector('object,embed');
  }

  if (mapEl) {
    const tag = mapEl.tagName.toLowerCase();

    if (tag === 'svg') {
      setupSudanMap(mapEl);
    } else if (tag === 'object' || tag === 'embed') {
      const attach = function () {
        try {
          const svgDoc  = mapEl.contentDocument;
          if (!svgDoc) return;
          const svgRoot = svgDoc.querySelector('svg') || svgDoc;
          setupSudanMap(svgRoot);
        } catch (e) {
          console.error('لا يمكن الوصول إلى محتوى خريطة السودان المضمَّنة:', e);
        }
      };

      if (mapEl.contentDocument) {
        attach();
      } else {
        mapEl.addEventListener('load', attach);
      }
    }
  }

  // ==========================
  // 2) فلتر الولايات حسب الحزب المتصدر
  // ==========================
  const partyFilterSelect = document.getElementById('gdy-el-party-filter');
  const regionsTbody      = document.getElementById('gdy-el-regions-tbody');

  function applyRegionFilter() {
    if (!regionsTbody || !partyFilterSelect) return;

    const selectedPartyId = partyFilterSelect.value;
    const rows = regionsTbody.querySelectorAll('tr[data-region-row="1"]');

    rows.forEach(function (row) {
      const rowPartyId = row.getAttribute('data-party-id') || '';
      if (!selectedPartyId) {
        row.style.display = '';
      } else {
        row.style.display = (rowPartyId === selectedPartyId ? '' : 'none');
      }
    });
  }
  if (partyFilterSelect) {
    partyFilterSelect.addEventListener('change', applyRegionFilter);
  }

  // ==========================
  // 3) ترتيب جدول الأحزاب
  // ==========================
  const sortSelect   = document.getElementById('gdy-el-party-sort');
  const partiesTbody = document.getElementById('gdy-el-parties-tbody');

  function applyPartySort() {
    if (!sortSelect || !partiesTbody) return;

    const mode = sortSelect.value;
    const rows = Array.from(partiesTbody.querySelectorAll('tr[data-party-row="1"]'));

    if (mode === 'default') {
      rows.forEach(function (r) { partiesTbody.appendChild(r); });
      return;
    }

    rows.sort(function (a, b) {
      const aSeats = parseInt(a.getAttribute('data-total-seats') || '0', 10);
      const bSeats = parseInt(b.getAttribute('data-total-seats') || '0', 10);

      if (mode === 'seats_desc') {
        return bSeats - aSeats;
      } else if (mode === 'seats_asc') {
        return aSeats - bSeats;
      }
      return 0;
    });

    rows.forEach(function (r) { partiesTbody.appendChild(r); });
  }
  if (sortSelect) {
    sortSelect.addEventListener('change', applyPartySort);
  }

  // ==========================
  // 4) أزرار المشاركة
  // ==========================
  const shareButtons = document.querySelectorAll('.gdy-el-share-btn');
  const copiedLabel  = document.getElementById('gdy-el-share-copied');

  if (shareButtons.length) {
    const pageUrl   = window.location.href;
    const pageTitle = document.title || 'نتائج الانتخابات';

    shareButtons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        const type = btn.getAttribute('data-share');

        if (type === 'whatsapp') {
          const waUrl = '#' +
            encodeURIComponent(pageTitle + '\n' + pageUrl);
          window.open(waUrl, '_blank', 'noopener');
        } else if (type === 'x') {
          const xUrl = '#' +
            encodeURIComponent(pageTitle) +
            '&url=' + encodeURIComponent(pageUrl);
          window.open(xUrl, '_blank', 'noopener');
        } else if (type === 'facebook') {
          const fbUrl = '#' +
            encodeURIComponent(pageUrl);
          window.open(fbUrl, '_blank', 'noopener');
        } else if (type === 'copy') {
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(pageUrl).then(function () {
              if (copiedLabel) {
                copiedLabel.classList.remove('d-none');
                setTimeout(function () {
                  copiedLabel.classList.add('d-none');
                }, 2200);
              }
            }).catch(function () {
              alert('تعذَّر نسخ الرابط، يمكنك نسخه يدويًا من شريط العنوان.');
            });
          } else {
            const tempInput = document.createElement('input');
            tempInput.value = pageUrl;
            document.body.appendChild(tempInput);
            tempInput.select();
            try {
              document.execCommand('copy');
              if (copiedLabel) {
                copiedLabel.classList.remove('d-none');
                setTimeout(function () {
                  copiedLabel.classList.add('d-none');
                }, 2200);
              }
            } catch (e) {
              alert('تعذَّر نسخ الرابط، يمكنك نسخه يدويًا من شريط العنوان.');
            }
            document.body.removeChild(tempInput);
          }
        }
      });
    });
  }
});
</script>

<?php
require __DIR__ . '/frontend/templates/footer.php';
?>
<script src=\"/assets/js/public-interactions.js\" defer></script>
