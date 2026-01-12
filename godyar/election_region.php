<?php
declare(strict_types=1);

// election_region.php — صفحة معلومات ولاية + نتائجها في تغطية انتخابية معينة

require_once __DIR__ . '/../includes/bootstrap.php';

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
 *    - code    : map_code للولاية (KHARTOUM, RED_SEA, ...)
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
 *    (يمكنك لاحقًا تعديل الأرقام والنصوص حسب بياناتك الدقيقة)
 */
$statesMeta = [
    'KHARTOUM' => [
        'name'      => 'الخرطوم',
        'capital'   => 'الخرطوم',
        'cities'    => 'الخرطوم، بحري، أمدرمان',
        'area_km2'  => 22142,
        'pop_2008'  => 7000000,
        'info'      => 'تُعد ولاية الخرطوم المركز الإداري والسياسي والاقتصادي للسودان، وتضم العاصمة القومية بثلاث مدن رئيسية: الخرطوم والخرطوم بحري وأمدرمان.',
        'wiki'      => 'https://ar.wikipedia.org/wiki/%D9%88%D9%84%D8%A7%D9%8A%D8%A9_%D8%A7%D9%84%D8%AE%D8%B1%D8%B7%D9%88%D9%85',
    ],
    'RED_SEA' => [
        'name'      => 'البحر الأحمر',
        'capital'   => 'بورتسودان',
        'cities'    => 'بورتسودان، سواكن، طوكر',
        'area_km2'  => 212800,
        'pop_2008'  => 1400000,
        'info'      => 'تقع ولاية البحر الأحمر في شرق السودان على ساحل البحر الأحمر، وتُعتبر بوابة السودان البحرية الرئيسية.',
        'wiki'      => 'https://ar.wikipedia.org/wiki/%D9%88%D9%84%D8%A7%D9%8A%D8%A9_%D8%A7%D9%84%D8%A8%D8%AD%D8%B1_%D8%A7%D9%84%D8%A3%D8%AD%D9%85%D8%B1',
    ],
    'RIVER_NILE' => [
        'name'      => 'نهر النيل',
        'capital'   => 'الدامر',
        'cities'    => 'الدامر، عطبرة، بربر، أبو حمد',
        'area_km2'  => 122608,
        'pop_2008'  => 1100000,
        'info'      => 'ولاية نهر النيل من ولايات شمال السودان، تمتد على ضفاف نهر النيل الرئيسي وتضم عدداً من المدن التاريخية.',
        'wiki'      => 'https://ar.wikipedia.org/wiki/%D9%88%D9%84%D8%A7%D9%8A%D8%A9_%D9%86%D9%87%D8%B1_%D8%A7%D9%84%D9%86%D9%8A%D9%84',
    ],
    'NORTH' => [
        'name'      => 'الشمالية',
        'capital'   => 'دنقلا',
        'cities'    => 'دنقلا، كرمة، وادي حلفا',
        'area_km2'  => 348765,
        'pop_2008'  => 700000,
        'info'      => 'تقع الولاية الشمالية في أقصى شمال السودان، وتطل على نهر النيل وتمتاز بالآثار النوبية القديمة.',
        'wiki'      => 'https://ar.wikipedia.org/wiki/%D8%A7%D9%84%D9%88%D9%84%D8%A7%D9%8A%D8%A9_%D8%A7%D9%84%D8%B4%D9%85%D8%A7%D9%84%D9%8A%D8%A9',
    ],
    'GEZIRA' => [
        'name'      => 'الجزيرة',
        'capital'   => 'ود مدني',
        'cities'    => 'ود مدني، المناقل، الحصاحيصا، رفاعة',
        'area_km2'  => 22736,
        'pop_2008'  => 3500000,
        'info'      => 'تُعد ولاية الجزيرة من أكثر ولايات السودان كثافة سكانية، وتشتهر بمشروع الجزيرة الزراعي.',
        'wiki'      => 'https://ar.wikipedia.org/wiki/%D9%88%D9%84%D8%A7%D9%8A%D8%A9_%D8%A7%D9%84%D8%AC%D8%B2%D9%8A%D8%B1%D8%A9',
    ],
    'WHITE_NILE' => [
        'name'      => 'النيل الأبيض',
        'capital'   => 'الربك',
        'cities'    => 'كوستي، ربك، الدويم',
        'area_km2'  => 39701,
        'pop_2008'  => 1700000,
        'info'      => 'تقع ولاية النيل الأبيض في وسط السودان على ضفاف النيل الأبيض، ولها دور مهم في الزراعة والرعي.',
        'wiki'      => 'https://ar.wikipedia.org/wiki/%D9%88%D9%84%D8%A7%D9%8A%D8%A9_%D8%A7%D9%84%D9%86%D9%8A%D9%84_%D8%A7%D9%84%D8%A3%D8%A8%D9%8A%D8%B6',
    ],
    'BLUE_NILE' => [
        'name'      => 'النيل الأزرق',
        'capital'   => 'الدمازين',
        'cities'    => 'الدمازين، الروصيرص',
        'area_km2'  => 45844,
        'pop_2008'  => 800000,
        'info'      => 'تقع ولاية النيل الأزرق في جنوب شرق السودان، وتشتهر بمشروعات الري والسدود وعلى رأسها سد الروصيرص.',
        'wiki'      => 'https://ar.wikipedia.org/wiki/%D9%88%D9%84%D8%A7%D9%8A%D8%A9_%D8%A7%D9%84%D9%86%D9%8A%D9%84_%D8%A7%D9%84%D8%A3%D8%B2%D8%B1%D9%82',
    ],
    'KASSALA' => [
        'name'      => 'كسلا',
        'capital'   => 'كسلا',
        'cities'    => 'كسلا، حلفا الجديدة',
        'area_km2'  => 36710,
        'pop_2008'  => 1800000,
        'info'      => 'تقع ولاية كسلا شرق السودان على الحدود مع إريتريا، وتشتهر بجبل التاكا وطبيعتها الخضراء في موسم الأمطار.',
        'wiki'      => 'https://ar.wikipedia.org/wiki/%D9%88%D9%84%D8%A7%D9%8A%D8%A9_%D9%83%D8%B3%D9%84%D8%A7',
    ],
    'GEDARIF' => [
        'name'      => 'القضارف',
        'capital'   => 'القضارف',
        'cities'    => 'القضارف، الحواتة، دوكة',
        'area_km2'  => 75263,
        'pop_2008'  => 1400000,
        'info'      => 'ولاية القضارف من أهم ولايات السودان الزراعية، تشتهر بإنتاج المحاصيل النقدية كالسمسم والذرة.',
        'wiki'      => 'https://ar.wikipedia.org/wiki/%D9%88%D9%84%D8%A7%D9%8A%D8%A9_%D8%A7%D9%84%D9%82%D8%B6%D8%A7%D8%B1%D9%81',
    ],
    'SENNAR' => [
        'name'      => 'سنار',
        'capital'   => 'سنجة',
        'cities'    => 'سنجة، سنار، الدالي والمزموم',
        'area_km2'  => 37965,
        'pop_2008'  => 1300000,
        'info'      => 'تقع ولاية سنار في وسط السودان، وتعتبر جزءاً من منطقة السهول الزراعية على النيل الأزرق.',
        'wiki'      => 'https://ar.wikipedia.org/wiki/%D9%88%D9%84%D8%A7%D9%8A%D8%A9_%D8%B3%D9%86%D8%A7%D8%B1',
    ],
    'NORTH_KORDOFAN' => [
        'name'      => 'شمال كردفان',
        'capital'   => 'الأبيض',
        'cities'    => 'الأبيض، الرهد، أم روابة',
        'area_km2'  => 185302,
        'pop_2008'  => 2700000,
        'info'      => 'ولاية شمال كردفان تقع في وسط غرب السودان، وتشتهر بإنتاج الصمغ العربي والثروة الحيوانية.',
        'wiki'      => 'https://ar.wikipedia.org/wiki/%D9%88%D9%84%D8%A7%D9%8A%D8%A9_%D8%B4%D9%85%D8%A7%D9%84_%D9%83%D8%B1%D8%AF%D9%81%D8%A7%D9%86',
    ],
    'SOUTH_KORDOFAN' => [
        'name'      => 'جنوب كردفان',
        'capital'   => 'كادوقلي',
        'cities'    => 'كادوقلي، الدلنج، أبو جبيهة',
        'area_km2'  => 158355,
        'pop_2008'  => 1400000,
        'info'      => 'جنوب كردفان ولاية ذات تنوع إثني وبيئي، تقع في المنطقة الانتقالية بين السافانا الغنية والسهول الجافة.',
        'wiki'      => 'https://ar.wikipedia.org/wiki/%D9%88%D9%84%D8%A7%D9%8A%D8%A9_%D8%AC%D9%86%D9%88%D8%A8_%D9%83%D8%B1%D8%AF%D9%81%D8%A7%D9%86',
    ],
    'WEST_KORDOFAN' => [
        'name'      => 'غرب كردفان',
        'capital'   => 'الفولة',
        'cities'    => 'الفولة، النهود، بابنوسة',
        'area_km2'  => 111373,
        'pop_2008'  => 1400000,
        'info'      => 'تقع ولاية غرب كردفان في غرب السودان، وتضم حقول نفطية ومناطق رعي واسعة.',
        'wiki'      => 'https://ar.wikipedia.org/wiki/%D9%88%D9%84%D8%A7%D9%8A%D8%A9_%D8%BA%D8%B1%D8%A8_%D9%83%D8%B1%D8%AF%D9%81%D8%A7%D9%86',
    ],
    'NORTH_DARFUR' => [
        'name'      => 'شمال دارفور',
        'capital'   => 'الفاشر',
        'cities'    => 'الفاشر، مليط، كتم، كبكابية',
        'area_km2'  => 296420,
        'pop_2008'  => 2200000,
        'info'      => 'أكبر ولايات دارفور من حيث المساحة، وتضم أراضي صحراوية وشبه صحراوية.',
        'wiki'      => 'https://ar.wikipedia.org/wiki/%D9%88%D9%84%D8%A7%D9%8A%D8%A9_%D8%B4%D9%85%D8%A7%D9%84_%D8%AF%D8%A7%D8%B1%D9%81%D9%88%D8%B1',
    ],
    'SOUTH_DARFUR' => [
        'name'      => 'جنوب دارفور',
        'capital'   => 'نيالا',
        'cities'    => 'نيالا، عد الفرسان، برام',
        'area_km2'  => 127300,
        'pop_2008'  => 4900000,
        'info'      => 'تُعتبر من أكثر ولايات دارفور كثافة سكانية، وتشتهر بالزراعة والرعي.',
        'wiki'      => 'https://ar.wikipedia.org/wiki/%D9%88%D9%84%D8%A7%D9%8A%D8%A9_%D8%AC%D9%86%D9%88%D8%A8_%D8%AF%D8%A7%D8%B1%D9%81%D9%88%D8%B1',
    ],
    'WEST_DARFUR' => [
        'name'      => 'غرب دارفور',
        'capital'   => 'الجنينة',
        'cities'    => 'الجنينة، فوربرنقا',
        'area_km2'  => 79460,
        'pop_2008'  => 1300000,
        'info'      => 'تقع في أقصى غرب السودان على الحدود مع تشاد، وتضم أراضي جبلية وسهولاً زراعية.',
        'wiki'      => 'https://ar.wikipedia.org/wiki/%D9%88%D9%84%D8%A7%D9%8A%D8%A9_%D8%BA%D8%B1%D8%A8_%D8%AF%D8%A7%D8%B1%D9%81%D9%88%D8%B1',
    ],
    'CENTRAL_DARFUR' => [
        'name'      => 'وسط دارفور',
        'capital'   => 'زالنجي',
        'cities'    => 'زالنجي، نيرتتي',
        'area_km2'  => 42380,
        'pop_2008'  => 1000000,
        'info'      => 'ولاية مستحدثة في إقليم دارفور، وتضم مناطق جبل مرة وبعض المناطق الزراعية.',
        'wiki'      => 'https://ar.wikipedia.org/wiki/%D9%88%D8%B3%D8%B7_%D8%AF%D8%A7%D8%B1%D9%81%D9%88%D8%B1',
    ],
    'EAST_DARFUR' => [
        'name'      => 'شرق دارفور',
        'capital'   => 'الضعين',
        'cities'    => 'الضعين، أبو كارنكا',
        'area_km2'  => 52500,
        'pop_2008'  => 1100000,
        'info'      => 'ولاية شرق دارفور من الولايات المستحدثة، وتضم مناطق رعي واسعة وبعض حقول النفط.',
        'wiki'      => 'https://ar.wikipedia.org/wiki/%D8%B4%D8%B1%D9%82_%D8%AF%D8%A7%D8%B1%D9%81%D9%88%D8%B1',
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
 * 5) جلب نتائج الأحزاب في هذه الولاية (فقط إذا كان لها id حقيقي)
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

// اسم الولاية المعروض
$stateName = $meta['name']
    ?? ($region['name_ar'] ?: ($region['name_en'] ?: $mapCode));

$pageTitle = 'ولاية ' . $stateName;

// ==================== الهيدر ====================
require __DIR__ . '/../frontend/templates/header.php';
?>
<link rel="stylesheet" href="/assets/css/elections.css">

<div class="gdy-elections-page container my-4">

  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="/index.php">الرئيسية</a></li>
      <li class="breadcrumb-item">
        <a href="/godyar/elections.php?election=<?= h($currentElection['slug']) ?>">
          نتائج الانتخابات – <?= h($currentElection['title']) ?>
        </a>
      </li>
      <li class="breadcrumb-item active" aria-current="page">
        <?= h($stateName) ?>
      </li>
    </ol>
  </nav>

  <div class="row g-4">
    <!-- بطاقة معلومات الولاية -->
    <div class="col-lg-4">
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-light">
          <h1 class="h5 mb-0"><?= h($stateName) ?></h1>
          <small class="text-muted d-block mt-1">
            تغطية: <?= h($currentElection['title']) ?>
          </small>
        </div>
        <div class="card-body small">

          <dl class="row mb-0">
            <dt class="col-5 text-muted">عاصمة الولاية</dt>
            <dd class="col-7"><?= h($meta['capital'] ?? 'غير محددة') ?></dd>

            <dt class="col-5 text-muted">أهم المدن</dt>
            <dd class="col-7"><?= h($meta['cities'] ?? '—') ?></dd>

            <dt class="col-5 text-muted">المساحة (كم²)</dt>
            <dd class="col-7">
              <?= isset($meta['area_km2']) && $meta['area_km2']
                    ? number_format((float)$meta['area_km2'], 0)
                    : '—' ?>
            </dd>

            <dt class="col-5 text-muted">عدد السكان (تعداد تقريبي)</dt>
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
        <div class="card shadow-sm">
          <div class="card-header bg-light">
            <span class="fw-semibold small">معلومات عامة عن الولاية</span>
          </div>
          <div class="card-body small text-muted">
            <?= h($meta['info']) ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- النتائج الانتخابية للولاية -->
    <div class="col-lg-8">
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
          <span class="fw-semibold small">نتائج الانتخابات في هذه الولاية</span>
          <small class="text-muted">
            إجمالي المقاعد / الدوائر: <?= $constituenciesCount ?: '—' ?>
          </small>
        </div>
        <div class="card-body p-0">
          <?php if ($partyResults): ?>
            <div class="table-responsive">
              <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                  <tr>
                    <th>الحزب</th>
                    <th class="text-center">المقاعد المحسومة</th>
                    <th class="text-center">المقاعد المتقدمة</th>
                    <th class="text-center">إجمالي المقاعد</th>
                    <th class="text-center">الأصوات</th>
                    <th class="text-center">النسبة %</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($partyResults as $row):
                    $totalSeatsParty = (int)$row['seats_won'] + (int)$row['seats_leading'];
                    $color = $row['color_hex'] ?: '#64748b';
                  ?>
                    <tr>
                      <td>
                        <div class="d-flex align-items-center gap-2">
                          <span class="gdy-el-party-dot" style="background: <?= h($color) ?>"></span>
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
                      <td class="text-center"><strong><?= $totalSeatsParty ?></strong></td>
                      <td class="text-center">
                        <?= $row['votes'] !== null ? number_format((float)$row['votes']) : '—' ?>
                      </td>
                      <td class="text-center">
                        <?= $row['vote_percent'] !== null ? number_format((float)$row['vote_percent'], 2) . '%' : '—' ?>
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

      <!-- Placeholder للدوائر والمرشحين (يمكن ربطه بجداول إضافية لاحقاً) -->
      <div class="card shadow-sm">
        <div class="card-header bg-light">
          <span class="fw-semibold small">الدوائر والمرشحين في هذه الولاية</span>
        </div>
        <div class="card-body small text-muted">
          يمكن لاحقًا ربط هذه البطاقة بجداول
          <code>election_constituencies</code> و
          <code>election_candidates</code>
          لعرض تفاصيل كل دائرة والمرشحين فيها.
        </div>
      </div>
    </div>
  </div>
</div>

<?php
require __DIR__ . '/../frontend/templates/footer.php';
