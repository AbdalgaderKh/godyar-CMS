<?php declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
// admin/elections/results.php — إدارة النتائج العامة للتغطية (ملخص الأحزاب)

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

// ================== 1) تحديد التغطية ==================
$electionId = (int)($_GET['election_id'] ?? 0);
$currentElection = null;
$allElections    = [];

try {
    $stmt = $pdo->query("
        SELECT id, title, slug, status, total_seats, majority_seats
        FROM elections
        ORDER BY created_at DESC
    ");
    $allElections = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if ($electionId > 0) {
        foreach ($allElections as $el) {
            if ((int)$el['id'] === $electionId) {
                $currentElection = $el;
                break;
            }
        }
    }

    if (!$currentElection && $allElections) {
        $currentElection = $allElections[0];
        $electionId      = (int)$currentElection['id'];
    }
} catch (Throwable $e) {
    @error_log('[elections results] fetch elections error: ' . $e->getMessage());
}

if (!$currentElection) {
    http_response_code(404);
    exit(__('t_dd911d2eef', 'لا توجد تغطيات انتخابية مسجلة بعد.'));
}

// ================== 2) جلب الأحزاب لهذه التغطية ==================
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
    @error_log('[elections results] fetch parties error: ' . $e->getMessage());
}

// ================== 3) معالجة POST لحفظ الملخص ==================
$flashSuccess = '';
$flashError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!function_exists('verify_csrf_token') || !verify_csrf_token($csrf)) {
        $flashError = __('t_0f296c4fe0', 'فشل التحقق الأمني، يرجى إعادة المحاولة.');
    } else {
        $summaryData = $_POST['summary'] ?? [];
        if (!is_array($summaryData)) {
            $summaryData = [];
        }

        try {
            $pdo->beginTransaction();

            foreach ($summaryData as $partyIdStr => $row) {
                $partyId = (int)$partyIdStr;
                if ($partyId <= 0) {
                    continue;
                }

                $seatsWon     = isset($row['seats_won']) ? max(0, (int)$row['seats_won']) : 0;
                $seatsLeading = isset($row['seats_leading']) ? max(0, (int)$row['seats_leading']) : 0;
                $votes        = isset($row['votes']) ? (int)$row['votes'] : 0;
                $votePercent  = isset($row['vote_percent']) && $row['vote_percent'] !== ''
                                ? (float)$row['vote_percent']
                                : null;

                $stmt = $pdo->prepare("
                    UPDATE election_results_summary
                       SET seats_won     = :sw,
                           seats_leading = :sl,
                           votes         = :v,
                           vote_percent  = :vp,
                           last_updated  = NOW()
                     WHERE election_id = :eid
                       AND party_id    = :pid
                ");
                $stmt->execute([
                    ':sw'  => $seatsWon,
                    ':sl'  => $seatsLeading,
                    ':v'   => $votes > 0 ? $votes : null,
                    ':vp'  => $votePercent !== null ? $votePercent : null,
                    ':eid' => $electionId,
                    ':pid' => $partyId,
                ]);

                if ($stmt->rowCount() === 0) {
                    $stmtIns = $pdo->prepare("
                        INSERT INTO election_results_summary
                            (election_id, party_id, seats_won, seats_leading, votes, vote_percent, last_updated)
                        VALUES
                            (:eid, :pid, :sw, :sl, :v, :vp, NOW())
                    ");
                    $stmtIns->execute([
                        ':eid' => $electionId,
                        ':pid' => $partyId,
                        ':sw'  => $seatsWon,
                        ':sl'  => $seatsLeading,
                        ':v'   => $votes > 0 ? $votes : null,
                        ':vp'  => $votePercent !== null ? $votePercent : null,
                    ]);
                }
            }

            $pdo->commit();
            $flashSuccess = __('t_d6ab1d76f6', 'تم حفظ ملخص النتائج بنجاح.');
            safe_redirect('results.php?election_id=' . $electionId . '&saved=1');
        } catch (Throwable $e) {
            $pdo->rollBack();
            @error_log('[elections results] save summary error: ' . $e->getMessage());
            $flashError = __('t_a749e6d977', 'حدث خطأ أثناء حفظ ملخص النتائج.');
        }
    }
}

if (isset($_GET['saved']) && !$flashSuccess && !$flashError) {
    $flashSuccess = __('t_d6ab1d76f6', 'تم حفظ ملخص النتائج بنجاح.');
}

// ================== 4) جلب الملخص الحالي من الجدول ==================
$summary = [];
try {
    $stmt = $pdo->prepare("
        SELECT party_id, seats_won, seats_leading, votes, vote_percent
        FROM election_results_summary
        WHERE election_id = :eid
    ");
    $stmt->execute([':eid' => $electionId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as $r) {
        $summary[(int)$r['party_id']] = $r;
    }
} catch (Throwable $e) {
    @error_log('[elections results] fetch summary error: ' . $e->getMessage());
}

// تجميع بعض الأرقام
$totalWon    = 0;
$totalLead   = 0;
$totalVotes  = 0;
foreach ($summary as $row) {
    $totalWon   += (int)$row['seats_won'];
    $totalLead  += (int)$row['seats_leading'];
    $totalVotes += (int)($row['votes'] ?? 0);
}

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
        <h1 class="h4 mb-1"><?= h(__('t_a31fd1be81', 'إدارة نتائج التغطية الانتخابية')) ?></h1>
        <p class="text-muted small mb-0">
          <?= h(__('t_6e1a488764', 'التغطية:')) ?> <strong><?= h($currentElection['title']) ?></strong>
          <span class="text-muted">(ID: <?= (int)$currentElection['id'] ?>)</span>
        </p>
      </div>
      <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          <?= h(__('t_6e51c4765e', 'رجوع لقائمة التغطيات')) ?>
        </a>
        <a href="parties.php?election_id=<?= (int)$currentElection['id'] ?>" class="btn btn-outline-secondary btn-sm">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          <?= h(__('t_e36f3293e2', 'إدارة الأحزاب')) ?>
        </a>
        <a href="regions.php?election_id=<?= (int)$currentElection['id'] ?>" class="btn btn-outline-secondary btn-sm">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          <?= h(__('t_97493e2ddc', 'إدارة الولايات / المناطق')) ?>
        </a>
      </div>
    </div>

    <!-- اختيار التغطية -->
    <form method="get" class="card mb-3">
      <div class="card-body row g-2 align-items-end">
        <div class="col-md-6">
          <label class="form-label small mb-1"><?= h(__('t_e321f02241', 'اختيار تغطية أخرى')) ?></label>
          <select name="election_id" class="form-select form-select-sm">
            <?php foreach ($allElections as $el): ?>
              <option value="<?= (int)$el['id'] ?>" <?= ((int)$el['id'] === $electionId ? 'selected' : '') ?>>
                <?= h($el['title']) ?> (ID: <?= (int)$el['id'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn btn-outline-primary btn-sm">
            <?= h(__('t_489a829b57', 'تغيير التغطية')) ?>
          </button>
        </div>
      </div>
    </form>

    <?php if ($flashSuccess): ?>
      <div class="alert alert-success py-2"><?= h($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
      <div class="alert alert-danger py-2"><?= h($flashError) ?></div>
    <?php endif; ?>

    <?php if (!$parties): ?>
      <div class="alert alert-warning">
        <?= h(__('t_a9d44ef3a7', 'لا توجد أحزاب مسجلة لهذه التغطية بعد.
        الرجاء إضافة الأحزاب أولاً من صفحة')) ?>
        <a href="parties.php?election_id=<?= (int)$electionId ?>"><?= h(__('t_e36f3293e2', 'إدارة الأحزاب')) ?></a>.
      </div>
    <?php else: ?>

      <form method="post" class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
          <span class="small fw-semibold"><?= h(__('t_137e8c8e93', 'ملخص النتائج حسب الحزب')) ?></span>
          <span class="small text-muted">
            المقاعد المحسومة: <?= (int)$totalWon ?>،
            المتقدمة: <?= (int)$totalLead ?>،
            مجموع الأصوات (إن وُجد): <?= $totalVotes ? number_format($totalVotes) : '—' ?>
          </span>
        </div>

        <div class="card-body p-0">
          <?php if (function_exists('csrf_field')): ?>
            <?= csrf_field('csrf_token') ?>
          <?php else: ?>
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">
          <?php endif; ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th><?= h(__('t_d39c72b61b', 'الحزب')) ?></th>
                  <th class="text-center"><?= h(__('t_933b6e05e1', 'المقاعد المحسومة')) ?></th>
                  <th class="text-center"><?= h(__('t_2522329c43', 'المقاعد المتقدمة')) ?></th>
                  <th class="text-center"><?= h(__('t_68a9656c1a', 'إجمالي المقاعد')) ?></th>
                  <th class="text-center"><?= h(__('t_48f4d66ad7', 'الأصوات')) ?></th>
                  <th class="text-center"><?= h(__('t_5b32e8d2bf', 'النسبة %')) ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($parties as $p):
                  $pid  = (int)$p['id'];
                  $rowS = $summary[$pid] ?? [
                      'seats_won'     => 0,
                      'seats_leading' => 0,
                      'votes'         => null,
                      'vote_percent'  => null,
                  ];
                  $color = $p['color_hex'] ?: '#2563eb';
                  $totalSeatsParty = (int)$rowS['seats_won'] + (int)$rowS['seats_leading'];
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
                             name="summary[<?= $pid ?>][seats_won]"
                             value="<?= (int)$rowS['seats_won'] ?>">
                    </td>
                    <td class="text-center" style="max-width:80px;">
                      <input type="number" class="form-control form-control-sm text-center"
                             name="summary[<?= $pid ?>][seats_leading]"
                             value="<?= (int)$rowS['seats_leading'] ?>">
                    </td>
                    <td class="text-center small">
                      <strong><?= $totalSeatsParty ?></strong>
                    </td>
                    <td class="text-center" style="max-width:120px;">
                      <input type="number" class="form-control form-control-sm text-center"
                             name="summary[<?= $pid ?>][votes]"
                             value="<?= $rowS['votes'] !== null ? (int)$rowS['votes'] : '' ?>">
                    </td>
                    <td class="text-center" style="max-width:90px;">
                      <input type="number" step="0.01" class="form-control form-control-sm text-center"
                             name="summary[<?= $pid ?>][vote_percent]"
                             value="<?= $rowS['vote_percent'] !== null ? (float)$rowS['vote_percent'] : '' ?>">
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
            <?= h(__('t_3e28a0143a', 'حفظ ملخص النتائج')) ?>
          </button>
        </div>
      </form>

      <div class="alert alert-info mt-3 small mb-0">
        <?= h(__('t_e098350e1c', 'لإدارة النتائج التفصيلية لكل ولاية / منطقة، استخدم صفحة')) ?>
        <a href="regions.php?election_id=<?= (int)$electionId ?>"><?= h(__('t_1c91f80267', 'إدارة الولايات')) ?></a>
        <?= h(__('t_2b93414174', 'ثم صفحة')) ?> <code>region_results.php</code> <?= h(__('t_96ad5375ee', 'لكل ولاية.')) ?>
      </div>

    <?php endif; ?>

  </div>
</main>

<?php require __DIR__ . '/../layout/footer.php';
