<?php declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
// admin/elections/region_results.php — نتائج ولاية/منطقة معينة في تغطية انتخابية

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/_elections_lib.php';

// محاولة تحميل نظام الصلاحيات إن وجد
$authFile = __DIR__ . '/../../includes/auth.php';
if (file_exists($authFile)) {
    require_once $authFile;
}

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

/**
 * حماية لوحة التحكم
 */
$authorized = false;

if (class_exists('\Godyar\Auth')) {
    if (method_exists('\Godyar\Auth', 'check') && \Godyar\Auth::check()) {
        if (method_exists('\Godyar\Auth', 'userIsAdmin')) {
            $authorized = \Godyar\Auth::userIsAdmin();
        } elseif (method_exists('\Godyar\Auth', 'user')) {
            $user = \Godyar\Auth::user();
            if (is_array($user) && isset($user['role']) &&
                in_array($user['role'], ['admin', 'superadmin'], true)
            ) {
                $authorized = true;
            } else {
                $authorized = true;
            }
        } else {
            $authorized = true;
        }
    }
} else {
    if (!empty($_SESSION['admin_id'])) {
        $authorized = true;
    } elseif (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        $authorized = true;
    }
}

if (!$authorized) {
    $loginUrl = function_exists('base_url')
        ? base_url('/admin/login')
        : '/admin/login';

    header('Location: ' . $loginUrl);
    exit;
}

/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();
if (!($pdo instanceof PDO)) {
    http_response_code(500);
    exit(__('t_ed202270ee', 'قاعدة البيانات غير متاحة حالياً.'));
}

gdy_elections_ensure_schema($pdo);

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// ================== 1) التغطية + الولاية ==================
$electionId = (int)($_GET['election_id'] ?? 0);
$regionId   = (int)($_GET['region_id'] ?? 0);

if ($electionId <= 0 || $regionId <= 0) {
    http_response_code(400);
    exit(__('t_3d998fa2ef', 'رابط غير مكتمل.'));
}

$currentElection = null;
$currentRegion   = null;

try {
    $stmt = $pdo->prepare("SELECT * FROM elections WHERE id = :id");
    $stmt->execute([':id' => $electionId]);
    $currentElection = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    @error_log('[region_results] fetch election error: ' . $e->getMessage());
}

if (!$currentElection) {
    http_response_code(404);
    exit(__('t_a481d65fe8', 'لم يتم العثور على التغطية المطلوبة.'));
}

try {
    $stmt = $pdo->prepare("
        SELECT id, election_id, name_ar, name_en, slug, map_code, total_seats
        FROM election_regions
        WHERE id = :id
          AND election_id = :eid
    ");
    $stmt->execute([
        ':id'  => $regionId,
        ':eid' => $electionId,
    ]);
    $currentRegion = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    @error_log('[region_results] fetch region error: ' . $e->getMessage());
}

if (!$currentRegion) {
    http_response_code(404);
    exit(__('t_51019e4b16', 'لم يتم العثور على الولاية / المنطقة المطلوبة.'));
}

// ================== 2) جلب أحزاب التغطية ==================
$parties = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, short_name, full_name, color_hex
        FROM election_parties
        WHERE election_id = :eid
        ORDER BY sort_order ASC, id ASC
    ");
    $stmt->execute([':eid' => $electionId]);
    $parties = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    @error_log('[region_results] fetch parties error: ' . $e->getMessage());
}

// ================== 3) معالجة POST (حفظ نتائج الولاية) ==================
$flashSuccess = '';
$flashError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!function_exists('verify_csrf_token') || !verify_csrf_token($csrf)) {
        $flashError = __('t_0f296c4fe0', 'فشل التحقق الأمني، يرجى إعادة المحاولة.');
    } else {
        $resultsData = $_POST['results'] ?? [];
        if (!is_array($resultsData)) {
            $resultsData = [];
        }

        try {
            $pdo->beginTransaction();

            $stmtDel = $pdo->prepare("
                DELETE FROM election_results_regions
                WHERE election_id = :eid
                  AND region_id   = :rid
            ");
            $stmtDel->execute([
                ':eid' => $electionId,
                ':rid' => $regionId,
            ]);

            $stmtIns = $pdo->prepare("
                INSERT INTO election_results_regions
                    (election_id, region_id, party_id, seats_won, seats_leading, votes, vote_percent, last_updated)
                VALUES
                    (:eid, :rid, :pid, :sw, :sl, :v, :vp, NOW())
            ");

            foreach ($resultsData as $partyIdStr => $row) {
                $partyId = (int)$partyIdStr;
                if ($partyId <= 0) {
                    continue;
                }

                $sw  = isset($row['seats_won']) ? max(0, (int)$row['seats_won']) : 0;
                $sl  = isset($row['seats_leading']) ? max(0, (int)$row['seats_leading']) : 0;
                $v   = isset($row['votes']) ? (int)$row['votes'] : 0;
                $vp  = isset($row['vote_percent']) && $row['vote_percent'] !== ''
                       ? (float)$row['vote_percent'] : null;

                if ($sw === 0 && $sl === 0 && $v === 0 && $vp === null) {
                    continue;
                }

                $stmtIns->execute([
                    ':eid' => $electionId,
                    ':rid' => $regionId,
                    ':pid' => $partyId,
                    ':sw'  => $sw,
                    ':sl'  => $sl,
                    ':v'   => $v > 0 ? $v : null,
                    ':vp'  => $vp !== null ? $vp : null,
                ]);
            }

            $pdo->commit();
            $flashSuccess = __('t_2446c4c208', 'تم حفظ نتائج هذه الولاية بنجاح.');
            safe_redirect('region_results.php?election_id=' . $electionId . '&region_id=' . $regionId . '&saved=1');
        } catch (Throwable $e) {
            $pdo->rollBack();
            @error_log('[region_results] save results error: ' . $e->getMessage());
            $flashError = __('t_069772f0fe', 'حدث خطأ أثناء حفظ النتائج.');
        }
    }
}

if (isset($_GET['saved']) && !$flashSuccess && !$flashError) {
    $flashSuccess = __('t_2446c4c208', 'تم حفظ نتائج هذه الولاية بنجاح.');
}

// ================== 4) جلب النتائج الحالية ==================
$results = [];
try {
    $stmt = $pdo->prepare("
        SELECT party_id, seats_won, seats_leading, votes, vote_percent
        FROM election_results_regions
        WHERE election_id = :eid
          AND region_id   = :rid
    ");
    $stmt->execute([
        ':eid' => $electionId,
        ':rid' => $regionId,
    ]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as $r) {
        $results[(int)$r['party_id']] = $r;
    }
} catch (Throwable $e) {
    @error_log('[region_results] fetch results error: ' . $e->getMessage());
}

$totalSeats = (int)($currentRegion['total_seats'] ?? 0);
$totalWon   = 0;
$totalLead  = 0;
foreach ($results as $r) {
    $totalWon  += (int)$r['seats_won'];
    $totalLead += (int)$r['seats_leading'];
}

$stateName = $currentRegion['name_ar'] ?: ($currentRegion['name_en'] ?: ('Region #' . (int)$currentRegion['id']));

$currentPageSlug = 'elections';
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<style>
.admin-elections-main {
    min-height: 100vh;
    background-color: #f8fafc;
}
.admin-elections-wrapper {
    padding: 1rem 0 2rem;
}
@media (max-width: 991.98px) {
    .admin-elections-main {
        margin-right: 0;
    }
}
@media (min-width: 992px) {
    .admin-elections-main {
        margin-right: 260px;
    }
}
.admin-elections-main .table-responsive {
    overflow-x: auto;
}
.admin-elections-main .container-fluid {
    padding-left: .75rem;
    padding-right: .75rem;
}
</style>

<main class="main-content admin-elections-main admin-content container-fluid py-4 gdy-admin-page">
  <div class="container-fluid admin-elections-wrapper">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
      <div>
        <h1 class="h4 mb-1"><?= h(__('t_d12e41c587', 'نتائج الولاية / المنطقة')) ?></h1>
        <p class="text-muted small mb-0">
          <?= h(__('t_6e1a488764', 'التغطية:')) ?>
          <strong><?= h($currentElection['title']) ?></strong>
          <?= h(__('t_cf78356788', '&mdash;
          الولاية / المنطقة:')) ?>
          <strong><?= h($stateName) ?></strong>
          <?php if (!empty($currentRegion['map_code'])): ?>
            <span class="text-muted">(map_code: <?= h($currentRegion['map_code']) ?>)</span>
          <?php endif; ?>
        </p>
      </div>
      <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
        <a href="regions.php?election_id=<?= (int)$electionId ?>" class="btn btn-outline-secondary btn-sm">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          <?= h(__('t_735a187184', 'رجوع لقائمة الولايات')) ?>
        </a>
        <a href="results.php?election_id=<?= (int)$electionId ?>" class="btn btn-outline-success btn-sm">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          <?= h(__('t_1767d49bf2', 'ملخص النتائج العامة')) ?>
        </a>
      </div>
    </div>

    <?php if ($flashSuccess): ?>
      <div class="alert alert-success py-2"><?= h($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
      <div class="alert alert-danger py-2"><?= h($flashError) ?></div>
    <?php endif; ?>

    <?php if (!$parties): ?>
      <div class="alert alert-warning">
        <?= h(__('t_e2479cb041', 'لا توجد أحزاب مسجلة لهذه التغطية بعد.
        الرجاء إضافة الأحزاب من صفحة')) ?>
        <a href="parties.php?election_id=<?= (int)$electionId ?>"><?= h(__('t_e36f3293e2', 'إدارة الأحزاب')) ?></a>.
      </div>
    <?php else: ?>

      <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
          <span class="small fw-semibold"><?= h(__('t_51b489f477', 'نتائج الأحزاب في هذه الولاية')) ?></span>
          <span class="small text-muted">
            المقاعد المحسومة: <?= (int)$totalWon ?>،
            المتقدمة: <?= (int)$totalLead ?>،
            إجمالي المقاعد / الدوائر لهذه الولاية: <?= $totalSeats ?: __('t_cd09c30d57', 'غير محدد') ?>
          </span>
        </div>

        <form method="post">
          <?php if (function_exists('csrf_field')): ?>
            <?= csrf_field('csrf_token') ?>
          <?php else: ?>
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">
          <?php endif; ?>

          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th><?= h(__('t_d39c72b61b', 'الحزب')) ?></th>
                    <th class="text-center"><?= h(__('t_933b6e05e1', 'المقاعد المحسومة')) ?></th>
                    <th class="text-center"><?= h(__('t_2522329c43', 'المقاعد المتقدمة')) ?></th>
                    <th class="text-center"><?= h(__('t_48f4d66ad7', 'الأصوات')) ?></th>
                    <th class="text-center"><?= h(__('t_5b32e8d2bf', 'النسبة %')) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($parties as $p):
                    $pid = (int)$p['id'];
                    $row = $results[$pid] ?? [
                        'seats_won'     => 0,
                        'seats_leading' => 0,
                        'votes'         => null,
                        'vote_percent'  => null,
                    ];
                    $color = $p['color_hex'] ?: '#2563eb';
                  ?>
                    <tr>
                      <td>
                        <div class="d-flex align-items-center gap-2">
                          <span class="d-inline-block rounded-circle"
                                style="width:14px;height:14px;background:<?= h($color) ?>;"></span>
                          <div>
                            <div class="fw-semibold small"><?= h($p['short_name']) ?></div>
                            <?php if (!empty($p['full_name'])): ?>
                              <div class="small text-muted"><?= h($p['full_name']) ?></div>
                            <?php endif; ?>
                          </div>
                        </div>
                      </td>
                      <td class="text-center" style="max-width:80px;">
                        <input type="number" class="form-control form-control-sm text-center"
                               name="results[<?= $pid ?>][seats_won]"
                               value="<?= (int)$row['seats_won'] ?>">
                      </td>
                      <td class="text-center" style="max-width:80px;">
                        <input type="number" class="form-control form-control-sm text-center"
                               name="results[<?= $pid ?>][seats_leading]"
                               value="<?= (int)$row['seats_leading'] ?>">
                      </td>
                      <td class="text-center" style="max-width:120px;">
                        <input type="number" class="form-control form-control-sm text-center"
                               name="results[<?= $pid ?>][votes]"
                               value="<?= $row['votes'] !== null ? (int)$row['votes'] : '' ?>">
                      </td>
                      <td class="text-center" style="max-width:90px;">
                        <input type="number" step="0.01" class="form-control form-control-sm text-center"
                               name="results[<?= $pid ?>][vote_percent]"
                               value="<?= $row['vote_percent'] !== null ? (float)$row['vote_percent'] : '' ?>">
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="card-footer d-flex justify-content-end">
            <button type="submit" class="btn btn-primary btn-sm">
              <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              <?= h(__('t_24b2983e9d', 'حفظ نتائج هذه الولاية')) ?>
            </button>
          </div>
        </form>
      </div>

      <div class="alert alert-info mt-3 small mb-0">
        <?= h(__('t_5cf5b8ea27', 'يمكن لاحقًا ربط هذه الصفحة بجداول')) ?>
        <code>election_constituencies</code> <?= h(__('t_99d151ef66', 'و')) ?>
        <code>election_candidates</code>
        <?= h(__('t_d37d25d70b', 'لعرض تفاصيل كل دائرة والمرشحين فيها.')) ?>
      </div>

    <?php endif; ?>

  </div>
</main>

<?php require __DIR__ . '/../layout/footer.php';
