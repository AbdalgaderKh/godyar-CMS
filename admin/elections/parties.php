<?php declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
// admin/elections/parties.php — إدارة الأحزاب الخاصة بتغطية انتخابية معينة

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

try {
    if ($electionId > 0) {
        $stmt = $pdo->prepare("
            SELECT id, title, slug, status, total_seats, majority_seats
            FROM elections
            WHERE id = :id
        ");
        $stmt->execute([':id' => $electionId]);
        $currentElection = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if (!$currentElection) {
        $stmt = $pdo->query("
            SELECT id, title, slug, status, total_seats, majority_seats
            FROM elections
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $currentElection = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $electionId = $currentElection ? (int)$currentElection['id'] : 0;
    }
} catch (Throwable $e) {
    @error_log('[elections parties] fetch election error: ' . $e->getMessage());
}

if (!$currentElection) {
    http_response_code(404);
    exit(__('t_dd911d2eef', 'لا توجد تغطيات انتخابية مسجلة بعد.'));
}

// ================== 2) معالجة POST ==================
$flashSuccess = '';
$flashError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf   = $_POST['csrf_token'] ?? '';

    if (!function_exists('verify_csrf_token') || !verify_csrf_token($csrf)) {
        $flashError = __('t_0f296c4fe0', 'فشل التحقق الأمني، يرجى إعادة المحاولة.');
    } else {
        if ($action === 'save_party') {
            $partyId    = (int)($_POST['party_id'] ?? 0);
            $code       = trim((string)($_POST['code'] ?? ''));
            $shortName  = trim((string)($_POST['short_name'] ?? ''));
            $fullName   = trim((string)($_POST['full_name'] ?? ''));
            $colorHex   = trim((string)($_POST['color_hex'] ?? ''));
            $logoPath   = trim((string)($_POST['logo_path'] ?? ''));
            $sortOrder  = (int)($_POST['sort_order'] ?? 0);

            if ($shortName === '') {
                $flashError = __('t_b332ca4adf', 'اسم الحزب المختصر مطلوب.');
            } else {
                try {
                    if ($partyId > 0) {
                        $stmt = $pdo->prepare("
                            UPDATE election_parties
                               SET code       = :code,
                                   short_name = :short_name,
                                   full_name  = :full_name,
                                   color_hex  = :color_hex,
                                   logo_path  = :logo_path,
                                   sort_order = :sort_order,
                                   updated_at = NOW()
                             WHERE id = :id
                               AND election_id = :eid
                        ");
                        $stmt->execute([
                            ':code'       => $code !== '' ? $code : null,
                            ':short_name' => $shortName,
                            ':full_name'  => $fullName !== '' ? $fullName : null,
                            ':color_hex'  => $colorHex !== '' ? $colorHex : null,
                            ':logo_path'  => $logoPath !== '' ? $logoPath : null,
                            ':sort_order' => $sortOrder,
                            ':id'         => $partyId,
                            ':eid'        => $electionId,
                        ]);

                        $flashSuccess = __('t_cd5eee7d37', 'تم تحديث بيانات الحزب بنجاح.');
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO election_parties
                                (election_id, code, short_name, full_name, color_hex, logo_path, sort_order, created_at, updated_at)
                            VALUES
                                (:election_id, :code, :short_name, :full_name, :color_hex, :logo_path, :sort_order, NOW(), NOW())
                        ");
                        $stmt->execute([
                            ':election_id'=> $electionId,
                            ':code'       => $code !== '' ? $code : null,
                            ':short_name' => $shortName,
                            ':full_name'  => $fullName !== '' ? $fullName : null,
                            ':color_hex'  => $colorHex !== '' ? $colorHex : null,
                            ':logo_path'  => $logoPath !== '' ? $logoPath : null,
                            ':sort_order' => $sortOrder,
                        ]);

                        $flashSuccess = __('t_295ffa67e8', 'تم إضافة الحزب بنجاح.');
                    }

                    safe_redirect('parties.php?election_id=' . $electionId . '&saved=1');
                } catch (Throwable $e) {
                    @error_log('[elections parties] save_party error: ' . $e->getMessage());
                    $flashError = __('t_d2fe36c431', 'حدث خطأ أثناء حفظ بيانات الحزب.');
                }
            }
        } elseif ($action === 'delete_party') {
            $partyId = (int)($_POST['party_id'] ?? 0);
            if ($partyId > 0) {
                try {
                    $stmt = $pdo->prepare("
                        DELETE FROM election_parties
                        WHERE id = :id
                          AND election_id = :eid
                    ");
                    $stmt->execute([
                        ':id'  => $partyId,
                        ':eid' => $electionId,
                    ]);
                    $flashSuccess = __('t_ca0e49f28d', 'تم حذف الحزب بنجاح.');
                } catch (Throwable $e) {
                    @error_log('[elections parties] delete_party error: ' . $e->getMessage());
                    $flashError = __('t_25ab8fd945', 'تعذر حذف الحزب، ربما مرتبط بنتائج انتخابية.');
                }
            }
        }
    }
}

if (isset($_GET['saved']) && !$flashSuccess && !$flashError) {
    $flashSuccess = __('t_7f524cfe48', 'تم حفظ التغييرات بنجاح.');
}

// ================== 3) جلب قائمة الأحزاب ==================
$parties = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, code, short_name, full_name, color_hex, logo_path, sort_order, created_at, updated_at
        FROM election_parties
        WHERE election_id = :eid
        ORDER BY sort_order ASC, id ASC
    ");
    $stmt->execute([':eid' => $electionId]);
    $parties = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    @error_log('[elections parties] fetch list error: ' . $e->getMessage());
}

// ================== 4) لو طلب تعديل حزب ==================
$editParty = null;
$editId = (int)($_GET['id'] ?? 0);
if ($editId > 0 && $parties) {
    foreach ($parties as $p) {
        if ((int)$p['id'] === $editId) {
            $editParty = $p;
            break;
        }
    }
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

/* على الشاشات الصغيرة: عرض كامل */
@media (max-width: 991.98px) {
    .admin-elections-main {
        margin-right: 0;
    }
}

/* على الشاشات الكبيرة: إزاحة عن القائمة الجانبية */
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
        <h1 class="h4 mb-1"><?= h(__('t_c683d6024a', 'إدارة أحزاب التغطية الانتخابية')) ?></h1>
        <p class="text-muted small mb-0">
          <?= h(__('t_6e1a488764', 'التغطية:')) ?> <strong><?= h($currentElection['title']) ?></strong>
          <span class="text-muted"> (ID: <?= (int)$currentElection['id'] ?>)</span>
        </p>
      </div>
      <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          <?= h(__('t_6e51c4765e', 'رجوع لقائمة التغطيات')) ?>
        </a>
        <a href="regions.php?election_id=<?= (int)$currentElection['id'] ?>" class="btn btn-outline-secondary btn-sm">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          <?= h(__('t_97493e2ddc', 'إدارة الولايات / المناطق')) ?>
        </a>
        <a href="results.php?election_id=<?= (int)$currentElection['id'] ?>" class="btn btn-outline-success btn-sm">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          <?= h(__('t_0b51fa0ab1', 'إدارة النتائج')) ?>
        </a>
      </div>
    </div>

    <?php if ($flashSuccess): ?>
      <div class="alert alert-success py-2"><?= h($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
      <div class="alert alert-danger py-2"><?= h($flashError) ?></div>
    <?php endif; ?>

    <!-- فورم إضافة/تعديل حزب -->
    <div class="card mb-3">
      <div class="card-header">
        <strong class="small">
          <?= $editParty ? __('t_fdfdfd9863', 'تعديل حزب') : __('t_77db93f78a', 'إضافة حزب جديد') ?>
        </strong>
      </div>
      <div class="card-body">
        <form method="post" class="row g-3">
          <?php if (function_exists('csrf_field')): ?>
            <?= csrf_field('csrf_token') ?>
          <?php else: ?>
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">
          <?php endif; ?>

          <input type="hidden" name="action" value="save_party">
          <input type="hidden" name="party_id" value="<?= $editParty ? (int)$editParty['id'] : 0 ?>">

          <div class="col-md-3">
            <label class="form-label small"><?= h(__('t_5eecb44d3b', 'الكود (اختياري)')) ?></label>
            <input type="text" name="code" class="form-control form-control-sm"
                   value="<?= h($editParty['code'] ?? '') ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label small"><?= h(__('t_9941065d4c', 'الاسم المختصر')) ?> <span class="text-danger">*</span></label>
            <input type="text" name="short_name" class="form-control form-control-sm"
                   value="<?= h($editParty['short_name'] ?? '') ?>" required>
          </div>

          <div class="col-md-5">
            <label class="form-label small"><?= h(__('t_68d407e649', 'الاسم الكامل (اختياري)')) ?></label>
            <input type="text" name="full_name" class="form-control form-control-sm"
                   value="<?= h($editParty['full_name'] ?? '') ?>">
          </div>

          <div class="col-md-3">
            <label class="form-label small"><?= h(__('t_bab17f2f9a', 'لون الحزب (HEX)')) ?></label>
            <input type="text" name="color_hex" class="form-control form-control-sm"
                   placeholder="#2563eb"
                   value="<?= h($editParty['color_hex'] ?? '') ?>">
          </div>

          <div class="col-md-5">
            <label class="form-label small"><?= h(__('t_bb97a86375', 'مسار شعار الحزب (اختياري)')) ?></label>
            <input type="text" name="logo_path" class="form-control form-control-sm"
                   placeholder="/uploads/parties/logo.png"
                   value="<?= h($editParty['logo_path'] ?? '') ?>">
          </div>

          <div class="col-md-2">
            <label class="form-label small"><?= h(__('t_2fcc9e97b9', 'ترتيب العرض')) ?></label>
            <input type="number" name="sort_order" class="form-control form-control-sm"
                   value="<?= h((string)($editParty['sort_order'] ?? 0)) ?>">
          </div>

          <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary btn-sm w-100">
              <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              <?= h(__('t_871a087a1d', 'حفظ')) ?>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- جدول الأحزاب -->
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="small fw-semibold"><?= h(__('t_be2cdee15b', 'قائمة الأحزاب المسجلة')) ?></span>
        <span class="small text-muted">إجمالي: <?= count($parties) ?></span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th><?= h(__('t_2e8b171b46', 'الاسم')) ?></th>
              <th class="text-center"><?= h(__('t_4851a7c099', 'اللون')) ?></th>
              <th><?= h(__('t_1f41be3824', 'الكود')) ?></th>
              <th class="text-center"><?= h(__('t_ddda59289a', 'الترتيب')) ?></th>
              <th class="text-center"><?= h(__('t_d4ef3a02e7', 'تاريخ الإنشاء')) ?></th>
              <th class="text-center"><?= h(__('t_4041e7805b', 'آخر تحديث')) ?></th>
              <th class="text-center"><?= h(__('t_901efe9b1c', 'إجراءات')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($parties): ?>
              <?php foreach ($parties as $p): ?>
                <tr>
                  <td><?= (int)$p['id'] ?></td>
                  <td>
                    <div class="fw-semibold small"><?= h($p['short_name']) ?></div>
                    <?php if (!empty($p['full_name'])): ?>
                      <div class="small text-muted"><?= h($p['full_name']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="text-center">
                    <?php $color = $p['color_hex'] ?: '#6b7280'; ?>
                    <span class="d-inline-block rounded-circle"
                          style="width:14px;height:14px;background:<?= h($color) ?>;"></span>
                    <div class="small text-muted"><?= h($p['color_hex'] ?? '') ?></div>
                  </td>
                  <td class="small">
                    <?= $p['code'] ? '<code>' . h($p['code']) . '</code>' : '—' ?>
                  </td>
                  <td class="text-center small">
                    <?= (int)$p['sort_order'] ?>
                  </td>
                  <td class="text-center small">
                    <?= h($p['created_at']) ?>
                  </td>
                  <td class="text-center small">
                    <?= $p['updated_at'] ? h($p['updated_at']) : '—' ?>
                  </td>
                  <td class="text-center">
                    <div class="d-flex flex-wrap justify-content-center gap-1">
                      <a href="parties.php?election_id=<?= (int)$electionId ?>&id=<?= (int)$p['id'] ?>"
                         class="btn btn-outline-primary btn-sm">
                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                      </a>

                      <form method="post" data-confirm='حذف هذا الحزب؟ قد يؤثر على النتائج.'>
                        <?php if (function_exists('csrf_field')): ?>
                          <?= csrf_field('csrf_token') ?>
                        <?php else: ?>
                          <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">
                        <?php endif; ?>
                        <input type="hidden" name="action" value="delete_party">
                        <input type="hidden" name="party_id" value="<?= (int)$p['id'] ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm">
                          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="8" class="text-center small text-muted py-3">
                  <?= h(__('t_a0112e2743', 'لا توجد أحزاب مسجلة لهذه التغطية بعد.')) ?>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</main>

<?php require __DIR__ . '/../layout/footer.php';
