<?php
declare(strict_types=1);

// election_constituency.php — صفحة تفاصيل دائرة انتخابية واحدة

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
 *    - cid      : ID الدائرة (من جدول election_constituencies)
 */
$electionSlug = isset($_GET['election']) ? trim((string)$_GET['election']) : '';
$cid          = isset($_GET['cid']) ? (int)$_GET['cid'] : 0;

if ($electionSlug === '' || $cid <= 0) {
    http_response_code(404);
    exit('رابط غير مكتمل.');
}

/**
 * 2) جلب بيانات التغطية الانتخابية
 */
$currentElection = null;
try {
    $stmt = $pdo->prepare("
        SELECT id, title, slug
        FROM elections
        WHERE slug = :slug
        LIMIT 1
    ");
    $stmt->execute([':slug' => $electionSlug]);
    $currentElection = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    @error_log('[Election Const] fetch election error: ' . $e->getMessage());
}

if (!$currentElection) {
    http_response_code(404);
    exit('لم يتم العثور على التغطية الانتخابية المطلوبة.');
}

$electionId = (int)$currentElection['id'];

/**
 * 3) جلب الدائرة + الولاية + أسماء الأحزاب
 */
$constituency = null;

try {
    $sql = "
        SELECT
            c.id           AS cid,
            c.code         AS c_code,
            c.name_ar      AS c_name_ar,
            c.name_en      AS c_name_en,
            c.seat_number  AS c_seat_number,
            c.total_voters AS c_total_voters,
            c.status       AS c_status,
            r.id           AS region_id,
            r.name_ar      AS region_name_ar,
            r.name_en      AS region_name_en,
            r.map_code     AS region_map_code
        FROM election_constituencies c
        JOIN election_regions r
          ON r.id = c.region_id
        WHERE c.id = :cid
          AND c.election_id = ?
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':cid' => $cid,
        ':eid' => $electionId,
    ]);
    $constituency = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    @error_log('[Election Const] fetch constituency error: ' . $e->getMessage());
}

if (!$constituency) {
    http_response_code(404);
    exit('لم يتم العثور على الدائرة المطلوبة لهذه التغطية.');
}

/**
 * 4) جلب المرشحين في هذه الدائرة
 */
$candidates = [];
try {
    $sqlCand = "
        SELECT
            cand.id        AS id,
            cand.full_name AS name,
            cand.votes     AS votes,
            cand.is_winner AS is_winner,
            p.short_name   AS party_short_name,
            p.color_hex    AS party_color_hex
        FROM election_candidates cand
        LEFT JOIN election_parties p
          ON p.id = cand.party_id
        WHERE cand.election_id = ?
          AND cand.constituency_id = :cid
        ORDER BY cand.is_winner DESC, cand.votes DESC
    ";
    $stmt = $pdo->prepare($sqlCand);
    $stmt->execute([
        ':eid' => $electionId,
        ':cid' => (int)$constituency['cid'],
    ]);
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    @error_log('[Election Const] fetch candidates error: ' . $e->getMessage());
}

// اسم الدائرة والولاية
$constName = $constituency['c_name_ar'] ?: ($constituency['c_name_en'] ?: ('دائرة #' . $constituency['cid']));
$regionName = $constituency['region_name_ar'] ?: ($constituency['region_name_en'] ?: 'الولاية');

$pageTitle = 'الدائرة: ' . $constName;

// ==================== الهيدر ====================
require __DIR__ . '/frontend/templates/header.php';
?>
<link rel="stylesheet" href="/assets/css/elections.css">

<style>
  .gdy-const-page-header {
      border-bottom: 1px solid #e5e7eb;
      padding-bottom: .5rem;
      margin-bottom: 1rem;
  }
  .gdy-const-page-badge {
      border-radius: 999px;
      padding: 0.1rem 0.5rem;
      font-size: 0.75rem;
  }
  .gdy-const-winner-pill {
      background-color: #16a34a;
      color: #fff;
      border-radius: 999px;
      padding: 0.1rem 0.5rem;
      font-size: 0.75rem;
      margin-inline-start: .35rem;
  }
</style>

<div class="container my-4 gdy-elections-page">

  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="/index.php">الرئيسية</a></li>
      <li class="breadcrumb-item">
        <a href="/elections.php?election=<?= h($currentElection['slug']) ?>">
          نتائج الانتخابات – <?= h($currentElection['title']) ?>
        </a>
      </li>
      <li class="breadcrumb-item">
        <a href="/election_region.php?election=<?= h($currentElection['slug']) ?>&code=<?= h($constituency['region_map_code']) ?>">
          <?= h($regionName) ?>
        </a>
      </li>
      <li class="breadcrumb-item active" aria-current="page">
        <?= h($constName) ?>
      </li>
    </ol>
  </nav>

  <div class="gdy-const-page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
    <div>
      <h1 class="h5 mb-1">
        الدائرة الانتخابية: <?= h($constName) ?>
      </h1>
      <div class="small text-muted">
        ضمن ولاية <?= h($regionName) ?> – تغطية: <?= h($currentElection['title']) ?>
      </div>
    </div>
    <div class="text-md-end small">
      <?php if (!empty($constituency['c_code'])): ?>
        <span class="badge bg-secondary gdy-const-page-badge">
          رمز الدائرة: <?= h($constituency['c_code']) ?>
        </span>
      <?php endif; ?>
      <?php if (!empty($constituency['c_status'])): ?>
        <span class="badge bg-light text-muted border gdy-const-page-badge">
          الحالة: <?= h($constituency['c_status']) ?>
        </span>
      <?php endif; ?>
    </div>
  </div>

  <div class="row g-4">
    <!-- معلومات الدائرة -->
    <div class="col-lg-4">
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-light">
          <span class="fw-semibold small">معلومات الدائرة</span>
        </div>
        <div class="card-body small">
          <dl class="row mb-0">
            <dt class="col-5 text-muted">اسم الدائرة</dt>
            <dd class="col-7"><?= h($constName) ?></dd>

            <dt class="col-5 text-muted">الولاية</dt>
            <dd class="col-7"><?= h($regionName) ?></dd>

            <dt class="col-5 text-muted">رقم المقعد</dt>
            <dd class="col-7">
              <?= $constituency['c_seat_number'] !== null ? (int)$constituency['c_seat_number'] : '—' ?>
            </dd>

            <dt class="col-5 text-muted">عدد الناخبين</dt>
            <dd class="col-7">
              <?= $constituency['c_total_voters'] !== null
                    ? number_format((int)$constituency['c_total_voters'])
                    : '—' ?>
            </dd>

            <dt class="col-5 text-muted">رمز الدائرة</dt>
            <dd class="col-7">
              <?= !empty($constituency['c_code']) ? h($constituency['c_code']) : '—' ?>
            </dd>

            <dt class="col-5 text-muted">حالة النتيجة</dt>
            <dd class="col-7">
              <?= !empty($constituency['c_status']) ? h($constituency['c_status']) : '—' ?>
            </dd>
          </dl>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header bg-light">
          <span class="fw-semibold small">ملاحظات</span>
        </div>
        <div class="card-body small text-muted">
          يمكن لاحقًا ربط هذه الصفحة بجداول مراكز الاقتراع أو جولات فرز إضافية،
          وإظهار خط زمني لعملية الفرز في الدائرة.
        </div>
      </div>
    </div>

    <!-- المرشحون ونتائجهم -->
    <div class="col-lg-8">
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
          <span class="fw-semibold small">المرشحون في هذه الدائرة</span>
          <small class="text-muted">
            مرتّبة حسب الفائز ثم عدد الأصوات.
          </small>
        </div>
        <div class="card-body p-0">
          <?php if ($candidates): ?>
            <div class="table-responsive">
              <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                  <tr>
                    <th>المرشح</th>
                    <th class="text-center">الحزب</th>
                    <th class="text-center">الأصوات</th>
                    <th class="text-center">النتيجة</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($candidates as $cand): 
                    $isWinner = ((int)$cand['is_winner'] === 1);
                  ?>
                    <tr>
                      <td>
                        <div class="fw-semibold small">
                          <?= h($cand['name']) ?>
                          <?php if ($isWinner): ?>
                            <span class="gdy-const-winner-pill">فائز</span>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td class="text-center">
                        <?= !empty($cand['party_short_name']) ? h($cand['party_short_name']) : '—' ?>
                      </td>
                      <td class="text-center">
                        <?= $cand['votes'] !== null ? number_format((int)$cand['votes']) : '—' ?>
                      </td>
                      <td class="text-center">
                        <?php if ($isWinner): ?>
                          <span class="badge bg-success">مقعد محسوم</span>
                        <?php else: ?>
                          <span class="badge bg-secondary">غير فائز</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="p-3 small text-muted">
              لم يتم تسجيل مرشحين أو نتائج في هذه الدائرة حتى الآن.
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header bg-light">
          <span class="fw-semibold small">الدوائر المرتبطة</span>
        </div>
        <div class="card-body small text-muted">
          يمكن لاحقًا إضافة قائمة بالدوائر المجاورة أو دوائر نفس المدينة
          لسهولة التنقل بين النتائج المحلية.
        </div>
      </div>
    </div>
  </div>
</div>

<?php
require __DIR__ . '/frontend/templates/footer.php';
