<?php
declare(strict_types=1);

// election_region.php — صفحة معلومات ولاية + نتائجها في تغطية انتخابية معينة

require_once __DIR__ . '/includes/bootstrap.php';

$pdo = gdy_pdo_safe();
if (!($pdo instanceof PDO)) {
    http_response_code(500);
    exit('تعذّر الاتصال بقاعدة البيانات.');
}

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * 1) استقبال باراميترات GET
 *    - election: slug التغطية
 *    - code    : map_code للولاية (KHARTOUM, RIVER_NILE, ...)
 */
$electionSlug = isset($_GET['election']) ? trim((string)$_GET['election']) : '';
$mapCodeRaw   = isset($_GET['code']) ? (string)$_GET['code'] : '';

$mapCode = strtoupper(preg_replace('/[^A-Z_]/', '', $mapCodeRaw));

if ($electionSlug === '' || $mapCode === '') {
    http_response_code(404);
    exit('رابط غير مكتمل.');
}

/**
 * 2) جلب بيانات التغطية الانتخابية
 */
$currentElection = null;
try {
    $stmt = $pdo->prepare("
        SELECT id, title, slug, status, total_seats, majority_seats, description
        FROM elections
        WHERE slug = :slug
        LIMIT 1
    ");
    $stmt->execute([':slug' => $electionSlug]);
    $currentElection = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    @error_log('[Election Region] fetch election error: ' . $e->getMessage());
}

if (!$currentElection) {
    http_response_code(404);
    exit('لم يتم العثور على التغطية الانتخابية المطلوبة.');
}

$electionId = (int)$currentElection['id'];

/**
 * 3) ميتاداتا ثابتة لكل ولاية
 */
$statesMeta = [
    'KHARTOUM' => [
        'name'      => 'الخرطوم',
        'capital'   => 'الخرطوم',
        'cities'    => 'الخرطوم، بحري، أمدرمان',
        'area_km2'  => 22142,
        'pop_2008'  => 7000000,
        'info'      => 'تُعد ولاية الخرطوم المركز الإداري والسياسي والاقتصادي للسودان، وتضم العاصمة القومية بثلاث مدن رئيسية: الخرطوم والخرطوم بحري وأمدرمان.',
        'wiki'      => '',
    ],
    'RED_SEA' => [
        'name'      => 'البحر الأحمر',
        'capital'   => 'بورتسودان',
        'cities'    => 'بورتسودان، سواكن، طوكر',
        'area_km2'  => 212800,
        'pop_2008'  => 1400000,
        'info'      => 'تقع ولاية البحر الأحمر في شرق السودان على ساحل البحر الأحمر، وتُعتبر بوابة السودان البحرية الرئيسية.',
        'wiki'      => '',
    ],
    'RIVER_NILE' => [
        'name'      => 'نهر النيل',
        'capital'   => 'الدامر',
        'cities'    => 'الدامر، عطبرة، بربر، أبو حمد',
        'area_km2'  => 122608,
        'pop_2008'  => 1100000,
        'info'      => 'ولاية نهر النيل من ولايات شمال السودان، تمتد على ضفاف نهر النيل الرئيسي وتضم عدداً من المدن التاريخية.',
        'wiki'      => '',
    ],
    'NORTH' => [
        'name'      => 'الشمالية',
        'capital'   => 'دنقلا',
        'cities'    => 'دنقلا، كرمة، وادي حلفا',
        'area_km2'  => 348765,
        'pop_2008'  => 700000,
        'info'      => 'تقع الولاية الشمالية في أقصى شمال السودان، وتطل على نهر النيل وتمتاز بالآثار النوبية القديمة.',
        'wiki'      => '',
    ],
    'GEZIRA' => [
        'name'      => 'الجزيرة',
        'capital'   => 'ود مدني',
        'cities'    => 'ود مدني، المناقل، الحصاحيصا، رفاعة',
        'area_km2'  => 22736,
        'pop_2008'  => 3500000,
        'info'      => 'تُعد ولاية الجزيرة من أكثر ولايات السودان كثافة سكانية، وتشتهر بمشروع الجزيرة الزراعي.',
        'wiki'      => '',
    ],
    'WHITE_NILE' => [
        'name'      => 'النيل الأبيض',
        'capital'   => 'الربك',
        'cities'    => 'كوستي، ربك، الدويم',
        'area_km2'  => 39701,
        'pop_2008'  => 1700000,
        'info'      => 'تقع ولاية النيل الأبيض في وسط السودان على ضفاف النيل الأبيض، ولها دور مهم في الزراعة والرعي.',
        'wiki'      => '',
    ],
    'BLUE_NILE' => [
        'name'      => 'النيل الأزرق',
        'capital'   => 'الدمازين',
        'cities'    => 'الدمازين، الروصيرص',
        'area_km2'  => 45844,
        'pop_2008'  => 800000,
        'info'      => 'تقع ولاية النيل الأزرق في جنوب شرق السودان، وتشتهر بمشروعات الري والسدود وعلى رأسها سد الروصيرص.',
        'wiki'      => '',
    ],
    'KASSALA' => [
        'name'      => 'كسلا',
        'capital'   => 'كسلا',
        'cities'    => 'كسلا، حلفا الجديدة',
        'area_km2'  => 36710,
        'pop_2008'  => 1800000,
        'info'      => 'تقع ولاية كسلا شرق السودان على الحدود مع إريتريا، وتشتهر بجبل التاكا وطبيعتها الخضراء في موسم الأمطار.',
        'wiki'      => '',
    ],
    'GEDARIF' => [
        'name'      => 'القضارف',
        'capital'   => 'القضارف',
        'cities'    => 'القضارف، الحواتة، دوكة',
        'area_km2'  => 75263,
        'pop_2008'  => 1400000,
        'info'      => 'ولاية القضارف من أهم ولايات السودان الزراعية، تشتهر بإنتاج المحاصيل النقدية كالسمسم والذرة.',
        'wiki'      => '',
    ],
    'SENNAR' => [
        'name'      => 'سنار',
        'capital'   => 'سنجة',
        'cities'    => 'سنجة، سنار، الدالي والمزموم',
        'area_km2'  => 37965,
        'pop_2008'  => 1300000,
        'info'      => 'تقع ولاية سنار في وسط السودان، وتعتبر جزءاً من منطقة السهول الزراعية على النيل الأزرق.',
        'wiki'      => '',
    ],
    'NORTH_KORDOFAN' => [
        'name'      => 'شمال كردفان',
        'capital'   => 'الأبيض',
        'cities'    => 'الأبيض، الرهد، أم روابة',
        'area_km2'  => 185302,
        'pop_2008'  => 2700000,
        'info'      => 'ولاية شمال كردفان تقع في وسط غرب السودان، وتشتهر بإنتاج الصمغ العربي والثروة الحيوانية.',
        'wiki'      => '',
    ],
    'SOUTH_KORDOFAN' => [
        'name'      => 'جنوب كردفان',
        'capital'   => 'كادوقلي',
        'cities'    => 'كادوقلي، الدلنج، أبو جبيهة',
        'area_km2'  => 158355,
        'pop_2008'  => 1400000,
        'info'      => 'جنوب كردفان ولاية ذات تنوع إثني وبيئي، تقع في المنطقة الانتقالية بين السافانا الغنية والسهول الجافة.',
        'wiki'      => '',
    ],
    'WEST_KORDOFAN' => [
        'name'      => 'غرب كردفان',
        'capital'   => 'الفولة',
        'cities'    => 'الفولة، النهود، بابنوسة',
        'area_km2'  => 111373,
        'pop_2008'  => 1400000,
        'info'      => 'تقع ولاية غرب كردفان في غرب السودان، وتضم حقول نفطية ومناطق رعي واسعة.',
        'wiki'      => '',
    ],
    'NORTH_DARFUR' => [
        'name'      => 'شمال دارفور',
        'capital'   => 'الفاشر',
        'cities'    => 'الفاشر، مليط، كتم، كبكابية',
        'area_km2'  => 296420,
        'pop_2008'  => 2200000,
        'info'      => 'أكبر ولايات دارفور من حيث المساحة، وتضم أراضي صحراوية وشبه صحراوية.',
        'wiki'      => '',
    ],
    'SOUTH_DARFUR' => [
        'name'      => 'جنوب دارفور',
        'capital'   => 'نيالا',
        'cities'    => 'نيالا، عد الفرسان، برام',
        'area_km2'  => 127300,
        'pop_2008'  => 4900000,
        'info'      => 'تُعتبر من أكثر ولايات دارفور كثافة سكانية، وتشتهر بالزراعة والرعي.',
        'wiki'      => '',
    ],
    'WEST_DARFUR' => [
        'name'      => 'غرب دارفور',
        'capital'   => 'الجنينة',
        'cities'    => 'الجنينة، فوربرنقا',
        'area_km2'  => 79460,
        'pop_2008'  => 1300000,
        'info'      => 'تقع في أقصى غرب السودان على الحدود مع تشاد، وتضم أراضي جبلية وسهولاً زراعية.',
        'wiki'      => '',
    ],
    'CENTRAL_DARFUR' => [
        'name'      => 'وسط دارفور',
        'capital'   => 'زالنجي',
        'cities'    => 'زالنجي، نيرتتي',
        'area_km2'  => 42380,
        'pop_2008'  => 1000000,
        'info'      => 'ولاية مستحدثة في إقليم دارفور، وتضم مناطق جبل مرة وبعض المناطق الزراعية.',
        'wiki'      => '',
    ],
    'EAST_DARFUR' => [
        'name'      => 'شرق دارفور',
        'capital'   => 'الضعين',
        'cities'    => 'الضعين، أبو كارنكا',
        'area_km2'  => 52500,
        'pop_2008'  => 1100000,
        'info'      => 'ولاية شرق دارفور من الولايات المستحدثة، وتضم مناطق رعي واسعة وبعض حقول النفط.',
        'wiki'      => '',
    ],
];

$meta = $statesMeta[$mapCode] ?? null;

/**
 * 4) جلب سجل الولاية من جدول election_regions
 */
$region = null;
try {
    $stmt = $pdo->prepare("
        SELECT id, election_id, name_ar, name_en, slug, map_code, total_seats
        FROM election_regions
        WHERE map_code = :code
          AND election_id = ?
        LIMIT 1
    ");
    $stmt->execute([
        ':code' => $mapCode,
        ':eid'  => $electionId,
    ]);
    $region = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    @error_log('[Election Region] fetch region error: ' . $e->getMessage());
}

// لو ما في سطر في قاعدة البيانات، نعمل كائن افتراضي من الميتاداتا فقط
if (!$region) {
    $region = [
        'id'          => 0,
        'election_id' => $electionId,
        'name_ar'     => $meta['name'] ?? $mapCode,
        'name_en'     => null,
        'slug'        => strtolower($mapCode),
        'map_code'    => $mapCode,
        'total_seats' => null,
    ];
}

/**
 * 5) جلب نتائج الأحزاب في هذه الولاية
 */
$partyResults       = [];
$totalSeatsWon      = 0;
$totalSeatsLeading  = 0;

if ((int)$region['id'] > 0) {
    try {
        $sqlResults = "
            SELECT rr.*,
                   p.short_name,
                   p.full_name,
                   p.color_hex
            FROM election_results_regions rr
            JOIN election_parties p
              ON p.id = rr.party_id
            WHERE rr.election_id = ?
              AND rr.region_id   = :rid
            ORDER BY (rr.seats_won + rr.seats_leading) DESC,
                     rr.votes DESC
        ";
        $stmt = $pdo->prepare($sqlResults);
        $stmt->execute([
            ':eid' => $electionId,
            ':rid' => (int)$region['id'],
        ]);
        $partyResults = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($partyResults as $pr) {
            $totalSeatsWon     += (int)$pr['seats_won'];
            $totalSeatsLeading += (int)$pr['seats_leading'];
        }
    } catch (Throwable $e) {
        @error_log('[Election Region] fetch party results error: ' . $e->getMessage());
    }
}

$constituenciesCount = (int)($region['total_seats'] ?? 0);
if ($constituenciesCount <= 0) {
    $constituenciesCount = $totalSeatsWon + $totalSeatsLeading;
}

// الحزب/التحالف المتصدر في الولاية (أول صف في الترتيب)
$leadingParty = $partyResults[0] ?? null;

// =====================
// 6) جلب الدوائر و المرشحين (اختياري)
// =====================
$constituencies = [];
$candidatesByConstituency = [];

if ((int)$region['id'] > 0) {
    // نحاول قراءة جدول الدوائر، لو مش موجود أو فيه مشكلة → نقفل بهدوء
    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM election_constituencies
            WHERE election_id = ?
              AND region_id   = :rid
            ORDER BY id ASC
        ");
        $stmt->execute([
            ':eid' => $electionId,
            ':rid' => (int)$region['id'],
        ]);
        $constituencies = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        @error_log('[Election Region] fetch constituencies error: ' . $e->getMessage());
        $constituencies = [];
    }

    // لو في دوائر، نحاول جلب المرشحين من جدول election_candidates
    if ($constituencies) {
        $constIds = array_column($constituencies, 'id');
        $constIds = array_filter(array_map('intval', $constIds));

        if ($constIds) {
            $placeholders = implode(',', array_fill(0, count($constIds), '?'));
            $sqlCand = "
                SELECT c.*,
                       p.short_name,
                       p.full_name,
                       p.color_hex
                FROM election_candidates c
                LEFT JOIN election_parties p
                  ON p.id = c.party_id
                WHERE c.election_id = ?
                  AND c.constituency_id IN ($placeholders)
                ORDER BY c.constituency_id ASC,
                         c.votes DESC
            ";

            try {
                $stmt = $pdo->prepare($sqlCand);
                $params = array_merge([$electionId], $constIds);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                foreach ($rows as $row) {
                    $cid = (int)($row['constituency_id'] ?? 0);
                    if (!$cid) continue;
                    if (!isset($candidatesByConstituency[$cid])) {
                        $candidatesByConstituency[$cid] = [];
                    }
                    $candidatesByConstituency[$cid][] = $row;
                }
            } catch (Throwable $e) {
                @error_log('[Election Region] fetch candidates error: ' . $e->getMessage());
                $candidatesByConstituency = [];
            }
        }
    }
}

// اسم الولاية المعروض
$stateName = $meta['name']
    ?? ($region['name_ar'] ?: ($region['name_en'] ?: $mapCode));

$pageTitle = 'ولاية ' . $stateName;

// ==================== الهيدر ====================
require __DIR__ . '/frontend/templates/header.php';
?>
<link rel="stylesheet" href="/assets/css/elections.css">

<style>
  .gdy-region-page {
    opacity: 0;
    transform: translateY(6px);
    transition: opacity .3s ease-out, transform .3s ease-out;
  }
  .gdy-region-page.is-loaded {
    opacity: 1;
    transform: translateY(0);
  }

  .gdy-region-hero {
    background: radial-gradient(circle at top left, rgba(37, 99, 235, 0.16), #020617 90%);
    border-radius: 1.25rem;
    border: 1px solid rgba(148, 163, 184, .35);
    box-shadow: 0 22px 40px rgba(15, 23, 42, .75);
    color: #e5e7eb;
    overflow: hidden;
    position: relative;
  }
  .gdy-region-hero::before {
    content: "";
    position: absolute;
    inset-inline-end: -40px;
    top: -40px;
    width: 180px;
    height: 180px;
    background: radial-gradient(circle, rgba(56,189,248,.4), transparent 70%);
    opacity: .4;
  }
  .gdy-region-hero .badge {
    border-radius: 999px;
  }
  .gdy-region-pill {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    border-radius: 999px;
    padding: .25rem .7rem;
    font-size: .78rem;
    background: rgba(15,23,42,.7);
    border: 1px solid rgba(148,163,184,.6);
    color: #e5e7eb;
  }
  .gdy-region-dot {
    width: .6rem;
    height: .6rem;
    border-radius: 999px;
    display: inline-block;
    box-shadow: 0 0 0 1px rgba(15,23,42,.5);
  }

  .gdy-card-soft {
    border-radius: 1rem;
    border: 1px solid rgba(148,163,184,.25);
    box-shadow: 0 10px 30px rgba(15,23,42,.35);
    overflow: hidden;
  }
  .gdy-card-soft .card-header {
    background: linear-gradient(to left, #020617, #0f172a);
    color: #e5e7eb;
    border-bottom-color: rgba(15,23,42,.6);
  }

  .gdy-region-stat-pill {
    display: inline-flex;
    flex-direction: column;
    align-items: flex-start;
    padding: .45rem .7rem;
    border-radius: .9rem;
    background: rgba(15,23,42,.85);
    border: 1px solid rgba(148,163,184,.4);
    min-width: 120px;
  }
  .gdy-region-stat-label {
    font-size: .68rem;
    color: #94a3b8;
  }
  .gdy-region-stat-value {
    font-size: .95rem;
    font-weight: 600;
    color: #e5e7eb;
  }

  .gdy-region-chip {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: .2rem .55rem;
    font-size: .72rem;
    background: #0f172a;
    color: #e5e7eb;
    margin: 0 .15rem .25rem;
  }

  .gdy-region-table tr:hover {
    background-color: rgba(15,23,42,.9);
    color: #e5e7eb;
  }
  .gdy-region-table thead {
    background: linear-gradient(to left, #020617, #0f172a);
    color: #e5e7eb;
  }
  .gdy-region-table .gdy-row-leading {
    background: radial-gradient(circle at top right, rgba(56,189,248,.22), rgba(15,23,42,.96)) !important;
  }

  .gdy-progress-shell {
    position: relative;
    background: #020617;
    border-radius: 999px;
    height: .45rem;
    overflow: hidden;
  }
  .gdy-progress-bar {
    position: absolute;
    inset-block: 0;
    inset-inline-start: 0;
    border-radius: inherit;
    transform-origin: left center;
    transform: scaleX(0);
    transition: transform .45s ease-out;
  }
  .gdy-region-page.is-loaded .gdy-progress-bar {
    transform: scaleX(1);
  }

  .gdy-fade-in {
    opacity: 0;
    transform: translateY(6px);
    transition: opacity .25s ease-out .1s, transform .25s ease-out .1s;
  }
  .gdy-region-page.is-loaded .gdy-fade-in {
    opacity: 1;
    transform: translateY(0);
  }

  .gdy-pill-status {
    border-radius: 999px;
    padding: .15rem .6rem;
    font-size: .7rem;
  }

  @media (max-width: 767.98px) {
    .gdy-region-hero {
      border-radius: .9rem;
    }
  }
</style>

<div class="gdy-region-page container my-4">

  <nav aria-label="breadcrumb" class="mb-3 gdy-fade-in">
    <ol class="breadcrumb mb-1">
      <li class="breadcrumb-item"><a href="/index.php">الرئيسية</a></li>
      <li class="breadcrumb-item">
        <a href="/elections.php?election=<?= h($currentElection['slug']) ?>">
          نتائج الانتخابات – <?= h($currentElection['title']) ?>
        </a>
      </li>
      <li class="breadcrumb-item active" aria-current="page">
        <?= h($stateName) ?>
      </li>
    </ol>
    <div class="d-flex flex-wrap gap-2 mt-1">
      <a href="/elections.php?election=<?= h($currentElection['slug']) ?>" class="btn btn-sm btn-outline-secondary">
        ← العودة لخريطة الولايات
      </a>
    </div>
  </nav>

  <!-- هيرو الولاية -->
  <div class="gdy-region-hero mb-4 p-3 p-md-4 gdy-fade-in">
    <div class="row align-items-center g-3">
      <div class="col-md-7">
        <div class="mb-1">
          <span class="badge bg-info-subtle text-info-emphasis gdy-pill-status">
            تغطية انتخابية
          </span>
        </div>
        <h1 class="h4 mb-1">
          ولاية <?= h($stateName) ?>
        </h1>
        <p class="mb-2 small text-slate-300">
          ضمن تغطية: <strong><?= h($currentElection['title']) ?></strong>
        </p>

        <div class="d-flex flex-wrap gap-2 mt-2">
          <div class="gdy-region-stat-pill">
            <span class="gdy-region-stat-label">إجمالي الدوائر / المقاعد</span>
            <span class="gdy-region-stat-value">
              <?= $constituenciesCount ?: '—' ?>
            </span>
          </div>
          <div class="gdy-region-stat-pill">
            <span class="gdy-region-stat-label">المقاعد المحسومة</span>
            <span class="gdy-region-stat-value">
              <?= $totalSeatsWon ?>
              <?php if ($totalSeatsLeading > 0): ?>
                <span class="text-warning small">
                  (+<?= $totalSeatsLeading ?> متقدمة)
                </span>
              <?php endif; ?>
            </span>
          </div>
          <div class="gdy-region-stat-pill">
            <span class="gdy-region-stat-label">عدد الأحزاب المشاركة</span>
            <span class="gdy-region-stat-value">
              <?= $partyResults ? count($partyResults) : '—' ?>
            </span>
          </div>
        </div>
      </div>

      <div class="col-md-5 d-flex flex-column align-items-md-end gap-2">
        <?php if ($leadingParty): ?>
          <?php
            $lpColor   = $leadingParty['color_hex'] ?: '#22c55e';
            $lpShort   = $leadingParty['short_name'] ?: ($leadingParty['full_name'] ?? 'حزب متصدر');
            $lpSeats   = (int)$leadingParty['seats_won'] + (int)$leadingParty['seats_leading'];
            $shareSeat = ($constituenciesCount > 0 && $lpSeats > 0)
              ? round($lpSeats / max(1, $constituenciesCount) * 100)
              : null;
          ?>
          <div class="border rounded-4 px-3 py-2 bg-black bg-opacity-30 w-100 w-md-auto">
            <div class="small text-muted mb-1">الحزب المتصدر في الولاية</div>
            <div class="d-flex align-items-center justify-content-between gap-2">
              <div class="d-flex align-items-center gap-2">
                <span class="gdy-region-dot" style="background: <?= h($lpColor) ?>;"></span>
                <div>
                  <div class="fw-semibold small"><?= h($lpShort) ?></div>
                  <div class="small text-muted">
                    مقاعد محسومة: <?= (int)$leadingParty['seats_won'] ?>
                    <?php if ((int)$leadingParty['seats_leading'] > 0): ?>
                      – متقدمة: <?= (int)$leadingParty['seats_leading'] ?>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <?php if ($shareSeat !== null): ?>
                <div class="text-end">
                  <div class="small text-muted mb-1">حصة المقاعد</div>
                  <div class="fw-semibold"><?= $shareSeat ?>%</div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- أزرار المشاركة -->
        <div class="d-flex flex-wrap gap-2 mt-2 justify-content-md-end">
          <button type="button" class="btn btn-sm btn-outline-light gdy-r-share" data-share="whatsapp">
            واتساب
          </button>
          <button type="button" class="btn btn-sm btn-outline-light gdy-r-share" data-share="x">
            X (تويتر)
          </button>
          <button type="button" class="btn btn-sm btn-outline-light gdy-r-share" data-share="facebook">
            فيسبوك
          </button>
          <button type="button" class="btn btn-sm btn-outline-light gdy-r-share" data-share="copy">
            نسخ الرابط
          </button>
        </div>
        <span class="small text-success mt-1 d-none" id="gdy-r-share-copied">
          تم نسخ رابط صفحة الولاية ✔
        </span>
      </div>
    </div>
  </div>

  <div class="row g-4 gdy-fade-in">
    <!-- بطاقة معلومات الولاية -->
    <div class="col-lg-4">
      <div class="card gdy-card-soft mb-3 h-100">
        <div class="card-header bg-light d-flex align-items-center justify-content-between">
          <span class="fw-semibold small">معلومات الولاية</span>
          <span class="badge bg-secondary-subtle text-secondary-emphasis gdy-pill-status">
            <?= h($stateName) ?>
          </span>
        </div>
        <div class="card-body small">

          <dl class="row mb-2">
            <dt class="col-5 text-muted">عاصمة الولاية</dt>
            <dd class="col-7"><?= h($meta['capital'] ?? 'غير محددة') ?></dd>

            <dt class="col-5 text-muted">أهم المدن</dt>
            <dd class="col-7">
              <?php if (!empty($meta['cities'])): ?>
                <?php
                  $cities = array_map('trim', explode('،', $meta['cities']));
                ?>
                <?php foreach ($cities as $city): ?>
                  <?php if ($city === '') continue; ?>
                  <span class="gdy-region-chip"><?= h($city) ?></span>
                <?php endforeach; ?>
              <?php else: ?>
                —
              <?php endif; ?>
            </dd>

            <dt class="col-5 text-muted">المساحة (كم²)</dt>
            <dd class="col-7">
              <?= isset($meta['area_km2']) && $meta['area_km2']
                    ? number_format((float)$meta['area_km2'], 0)
                    : '—' ?>
            </dd>

            <dt class="col-5 text-muted">عدد السكان (تقريبي)</dt>
            <dd class="col-7">
              <?= isset($meta['pop_2008']) && $meta['pop_2008']
                    ? number_format((float)$meta['pop_2008'], 0)
                    : '—' ?>
            </dd>

            <dt class="col-5 text-muted">عدد الدوائر / المقاعد</dt>
            <dd class="col-7">
              <?= $constituenciesCount ? (int)$constituenciesCount : '—' ?>
            </dd>

            <dt class="col-5 text-muted">عدد الأحزاب المشاركة</dt>
            <dd class="col-7">
              <?= $partyResults ? count($partyResults) : '—' ?>
            </dd>
          </dl>

          <?php if (!empty($meta['wiki'])): ?>
            <hr>
            <a href="<?= h($meta['wiki']) ?>" target="_blank" rel="noopener"
               class="btn btn-sm btn-outline-secondary w-100">
              المزيد عن الولاية في ويكيبيديا
            </a>
          <?php endif; ?>

        </div>
      </div>

      <?php if (!empty($meta['info'])): ?>
        <div class="card gdy-card-soft">
          <div class="card-header bg-light">
            <span class="fw-semibold small">لمحة عن الولاية</span>
          </div>
          <div class="card-body small text-muted">
            <?= nl2br(h($meta['info'])) ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- النتائج الانتخابية للولاية + الدوائر -->
    <div class="col-lg-8">
      <div class="card gdy-card-soft mb-3">
        <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center gap-2">
          <div>
            <span class="fw-semibold small">النتائج الانتخابية في الولاية</span>
            <span class="small text-muted ms-2">
              إجمالي الدوائر / المقاعد: <?= $constituenciesCount ?: '—' ?>
            </span>
          </div>
          <div class="d-flex align-items-center gap-2">
            <label for="gdy-region-sort" class="small text-muted mb-0">
              ترتيب الأحزاب حسب:
            </label>
            <select id="gdy-region-sort" class="form-select form-select-sm">
              <option value="seats">المقاعد</option>
              <option value="votes">الأصوات</option>
            </select>
          </div>
        </div>
        <div class="card-body p-0">
          <?php if ($partyResults): ?>
            <div class="table-responsive">
              <table class="table table-sm table-hover mb-0 align-middle text-nowrap gdy-region-table" id="gdy-region-table">
                <thead>
                  <tr>
                    <th>الحزب</th>
                    <th class="text-center">المقاعد المحسومة</th>
                    <th class="text-center">المقاعد المتقدمة</th>
                    <th class="text-center">إجمالي المقاعد</th>
                    <th style="min-width:140px;">حصة المقاعد (شريط)</th>
                    <th class="text-center">الأصوات</th>
                    <th class="text-center">النسبة %</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($partyResults as $idx => $row):
                    $totalSeatsParty = (int)$row['seats_won'] + (int)$row['seats_leading'];
                    $color = $row['color_hex'] ?: '#38bdf8';
                    $votes = $row['votes'] !== null ? (float)$row['votes'] : null;
                    $votePercent = $row['vote_percent'] !== null ? (float)$row['vote_percent'] : null;
                    $seatShare = ($constituenciesCount > 0 && $totalSeatsParty > 0)
                      ? max(3, min(100, round($totalSeatsParty / max(1, $constituenciesCount) * 100)))
                      : 0;
                    $rowClasses = [];
                    if ($idx === 0) {
                        $rowClasses[] = 'gdy-row-leading';
                    }
                  ?>
                    <tr
                      class="<?= implode(' ', $rowClasses) ?>"
                      data-seats="<?= $totalSeatsParty ?>"
                      data-votes="<?= $votes !== null ? $votes : 0 ?>"
                    >
                      <td>
                        <div class="d-flex align-items-center gap-2">
                          <span class="gdy-region-dot" style="background: <?= h($color) ?>;"></span>
                          <div>
                            <div class="fw-semibold small">
                              <?= h($row['short_name'] ?? $row['full_name'] ?? 'حزب') ?>
                            </div>
                            <?php if (!empty($row['full_name']) && $row['full_name'] !== $row['short_name']): ?>
                              <div class="small text-muted"><?= h($row['full_name']) ?></div>
                            <?php endif; ?>
                          </div>
                        </div>
                      </td>
                      <td class="text-center"><?= (int)$row['seats_won'] ?></td>
                      <td class="text-center"><?= (int)$row['seats_leading'] ?></td>
                      <td class="text-center">
                        <strong><?= $totalSeatsParty ?></strong>
                      </td>
                      <td>
                        <div class="gdy-progress-shell">
                          <div class="gdy-progress-bar"
                               style="background: <?= h($color) ?>; width: <?= $seatShare ?>%;"></div>
                        </div>
                        <div class="small text-muted mt-1">
                          <?= $seatShare ? $seatShare . '%' : 'لا مقاعد بعد' ?>
                        </div>
                      </td>
                      <td class="text-center">
                        <?= $votes !== null ? number_format($votes) : '—' ?>
                      </td>
                      <td class="text-center">
                        <?= $votePercent !== null ? number_format($votePercent, 2) . '%' : '—' ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="p-3 small text-muted">
              لا توجد نتائج انتخابية مدخلة لهذه الولاية في هذه التغطية حتى الآن.
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- الدوائر والمرشحين في هذه الولاية -->
      <div class="card gdy-card-soft">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
          <span class="fw-semibold small">الدوائر والمرشحين في هذه الولاية</span>
          <?php if ($constituencies): ?>
            <span class="small text-muted">
              عدد الدوائر: <?= count($constituencies) ?>
            </span>
          <?php endif; ?>
        </div>
        <div class="card-body small">
          <?php if ($constituencies): ?>
            <div class="accordion" id="gdy-const-accordion">
              <?php foreach ($constituencies as $i => $c):
                $cid   = (int)($c['id'] ?? 0);
                $cName = $c['name_ar'] ?? $c['name_en'] ?? $c['name'] ?? ('دائرة #' . ($i + 1));
                $cCode = $c['code'] ?? $c['slug'] ?? null;
                $cSeats = $c['total_seats'] ?? $c['seats'] ?? 1;
                $regVoters = $c['voters_registered'] ?? $c['registered_voters'] ?? null;
                $turnout   = $c['voters_turnout'] ?? $c['turnout_percent'] ?? null;

                $candList = $candidatesByConstituency[$cid] ?? [];

                // استنتاج المرشح الفائز (إن وُجد)
                $winnerName = null;
                if ($candList) {
                    $winner = null;
                    foreach ($candList as $candRow) {
                        $isWinnerFlag = isset($candRow['is_winner']) && (string)$candRow['is_winner'] === '1';
                        if ($winner === null || $isWinnerFlag) {
                            $winner = $candRow;
                            if ($isWinnerFlag) {
                                break;
                            }
                        }
                    }
                    if ($winner) {
                        $winnerName = $winner['candidate_name'] ?? $winner['name'] ?? null;
                    }
                }

                $collapseId = 'gdy-const-collapse-' . $cid;
                $headingId  = 'gdy-const-heading-' . $cid;
              ?>
                <div class="accordion-item mb-1 border-0">
                  <h2 class="accordion-header" id="<?= h($headingId) ?>">
                    <button class="accordion-button collapsed py-2 px-3 small"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#<?= h($collapseId) ?>"
                            aria-expanded="false"
                            aria-controls="<?= h($collapseId) ?>">
                      <div class="w-100 d-flex flex-column flex-md-row justify-content-between gap-2">
                        <div>
                          <span class="fw-semibold"><?= h($cName) ?></span>
                          <?php if ($cCode): ?>
                            <span class="badge bg-dark-subtle text-light ms-2">
                              كود: <?= h($cCode) ?>
                            </span>
                          <?php endif; ?>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                          <span class="gdy-region-pill">
                            <span class="gdy-region-dot" style="background:#0ea5e9;"></span>
                            المقاعد: <?= (int)$cSeats ?>
                          </span>
                          <?php if ($regVoters): ?>
                            <span class="gdy-region-pill">
                              الناخبون المسجلون:
                              <?= number_format((float)$regVoters) ?>
                            </span>
                          <?php endif; ?>
                          <?php if ($turnout): ?>
                            <span class="gdy-region-pill">
                              نسبة الإقبال:
                              <?= is_numeric($turnout) ? number_format((float)$turnout, 1) . '%' : h((string)$turnout) ?>
                            </span>
                          <?php endif; ?>
                          <?php if ($winnerName): ?>
                            <span class="gdy-region-pill">
                              الفائز (مبدئيًا):
                              <strong><?= h($winnerName) ?></strong>
                            </span>
                          <?php endif; ?>
                        </div>
                      </div>
                    </button>
                  </h2>
                  <div id="<?= h($collapseId) ?>"
                       class="accordion-collapse collapse"
                       aria-labelledby="<?= h($headingId) ?>"
                       data-bs-parent="#gdy-const-accordion">
                    <div class="accordion-body pt-2 pb-2 px-3">
                      <?php if ($candList): ?>
                        <div class="table-responsive">
                          <table class="table table-sm table-hover mb-0 align-middle">
                            <thead class="table-light">
                              <tr>
                                <th>المرشح</th>
                                <th>الحزب</th>
                                <th class="text-center">الأصوات</th>
                                <th class="text-center">النسبة %</th>
                                <th class="text-center">الحالة</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php foreach ($candList as $candRow):
                                $candName = $candRow['candidate_name'] ?? $candRow['name'] ?? 'مرشح';
                                $partyShort = $candRow['short_name'] ?? $candRow['full_name'] ?? 'مستقل';
                                $pColor = $candRow['color_hex'] ?? '#64748b';
                                $candVotes = $candRow['votes'] ?? null;
                                $candPct   = $candRow['vote_percent'] ?? null;
                                $isWinner  = isset($candRow['is_winner']) && (string)$candRow['is_winner'] === '1';
                              ?>
                                <tr>
                                  <td><?= h($candName) ?></td>
                                  <td>
                                    <span class="gdy-region-dot me-1" style="background: <?= h($pColor) ?>;"></span>
                                    <?= h($partyShort) ?>
                                  </td>
                                  <td class="text-center">
                                    <?= $candVotes !== null ? number_format((float)$candVotes) : '—' ?>
                                  </td>
                                  <td class="text-center">
                                    <?= $candPct !== null ? number_format((float)$candPct, 2) . '%' : '—' ?>
                                  </td>
                                  <td class="text-center">
                                    <?php if ($isWinner): ?>
                                      <span class="badge bg-success-subtle text-success">
                                        فائز
                                      </span>
                                    <?php else: ?>
                                      <span class="badge bg-secondary-subtle text-secondary">
                                        —
                                      </span>
                                    <?php endif; ?>
                                  </td>
                                </tr>
                              <?php endforeach; ?>
                            </tbody>
                          </table>
                        </div>
                      <?php else: ?>
                        <div class="small text-muted">
                          لا توجد بيانات مرشحين مُسجلة لهذه الدائرة حتى الآن.
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="small text-muted">
              لا توجد بيانات تفصيلية للدوائر والمرشحين لهذه الولاية بعد.<br>
              عند تجهيز الجداول <code>election_constituencies</code> و
              <code>election_candidates</code> وإدخال البيانات،
              ستظهر هنا خريطة الدوائر وقوائم المرشحين تلقائيًا.
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const pageRoot = document.querySelector('.gdy-region-page');
  if (pageRoot) {
    requestAnimationFrame(function () {
      pageRoot.classList.add('is-loaded');
    });
  }

  // ========================
  // 1) ترتيب جدول الأحزاب (مقاعد / أصوات)
  // ========================
  const sortSelect = document.getElementById('gdy-region-sort');
  const tableBody  = document.querySelector('#gdy-region-table tbody');

  function applySort() {
    if (!sortSelect || !tableBody) return;

    const mode = sortSelect.value; // seats | votes
    const rows = Array.from(tableBody.querySelectorAll('tr'));

    rows.sort(function (a, b) {
      const aSeats = parseInt(a.getAttribute('data-seats') || '0', 10);
      const bSeats = parseInt(b.getAttribute('data-seats') || '0', 10);
      const aVotes = parseFloat(a.getAttribute('data-votes') || '0');
      const bVotes = parseFloat(b.getAttribute('data-votes') || '0');

      if (mode === 'votes') {
        if (bVotes !== aVotes) {
          return bVotes - aVotes;
        }
        return bSeats - aSeats;
      } else {
        if (bSeats !== aSeats) {
          return bSeats - aSeats;
        }
        return bVotes - aVotes;
      }
    });

    rows.forEach(function (r) { tableBody.appendChild(r); });
  }

  if (sortSelect) {
    sortSelect.addEventListener('change', applySort);
  }

  // ========================
  // 2) مشاركة صفحة الولاية
  // ========================
  const shareButtons = document.querySelectorAll('.gdy-r-share');
  const copiedLabel  = document.getElementById('gdy-r-share-copied');

  if (shareButtons.length) {
    const pageUrl   = window.location.href;
    const pageTitle = document.title || 'نتائج الانتخابات في الولاية';

    shareButtons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        const type = btn.getAttribute('data-share');

        if (type === 'whatsapp') {
          const waUrl = 'https://wa.me/?text=' +
            encodeURIComponent(pageTitle + '\n' + pageUrl);
          window.open(waUrl, '_blank', 'noopener');
        } else if (type === 'x') {
          const xUrl = 'https://twitter.com/intent/tweet?text=' +
            encodeURIComponent(pageTitle) +
            '&url=' + encodeURIComponent(pageUrl);
          window.open(xUrl, '_blank', 'noopener');
        } else if (type === 'facebook') {
          const fbUrl = 'https://www.facebook.com/sharer/sharer.php?u=' +
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
