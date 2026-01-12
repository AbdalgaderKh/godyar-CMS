<?php declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
// admin/elections/regions.php — إدارة ولايات/مناطق التغطية

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

// ================== 1) التغطية ==================
$electionId = (int)($_GET['election_id'] ?? 0);
$currentElection = null;
try {
    if ($electionId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM elections WHERE id = :id");
        $stmt->execute([':id' => $electionId]);
        $currentElection = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    if (!$currentElection) {
        $stmt = $pdo->query("SELECT * FROM elections ORDER BY created_at DESC LIMIT 1");
        $currentElection = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $electionId = $currentElection ? (int)$currentElection['id'] : 0;
    }
} catch (Throwable $e) {
    @error_log('[elections regions] fetch election error: ' . $e->getMessage());
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
        if ($action === 'save_region') {
            $regionId   = (int)($_POST['region_id'] ?? 0);
            $nameAr     = trim((string)($_POST['name_ar'] ?? ''));
            $nameEn     = trim((string)($_POST['name_en'] ?? ''));
            $slug       = trim((string)($_POST['slug'] ?? ''));
            $mapCode    = trim((string)($_POST['map_code'] ?? ''));
            $totalSeats = (int)($_POST['total_seats'] ?? 0);
            $sortOrder  = (int)($_POST['sort_order'] ?? 0);

            if ($nameAr === '' && $nameEn === '') {
                $flashError = __('t_15966b5f49', 'اسم الولاية / المنطقة مطلوب بالعربية أو الإنجليزية.');
            } else {
                if ($slug === '') {
                    $slug = gdy_elections_slugify($nameAr !== '' ? $nameAr : $nameEn);
                }
                if ($mapCode !== '') {
                    $mapCode = strtoupper(preg_replace('/[^A-Z_]/', '', $mapCode));
                }

                try {
                    if ($regionId > 0) {
                        $stmt = $pdo->prepare("
                            UPDATE election_regions
                               SET name_ar     = :name_ar,
                                   name_en     = :name_en,
                                   slug        = :slug,
                                   map_code    = :map_code,
                                   total_seats = :total_seats,
                                   sort_order  = :sort_order,
                                   updated_at  = NOW()
                             WHERE id = :id
                               AND election_id = :eid
                        ");
                        $stmt->execute([
                            ':name_ar'     => $nameAr !== '' ? $nameAr : null,
                            ':name_en'     => $nameEn !== '' ? $nameEn : null,
                            ':slug'        => $slug,
                            ':map_code'    => $mapCode !== '' ? $mapCode : null,
                            ':total_seats' => $totalSeats > 0 ? $totalSeats : 0,
                            ':sort_order'  => $sortOrder,
                            ':id'          => $regionId,
                            ':eid'         => $electionId,
                        ]);
                        $flashSuccess = __('t_284b09bac5', 'تم تحديث بيانات الولاية / المنطقة.');
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO election_regions
                                (election_id, parent_id, name_ar, name_en, slug, map_code, total_seats, sort_order, created_at, updated_at)
                            VALUES
                                (:election_id, NULL, :name_ar, :name_en, :slug, :map_code, :total_seats, :sort_order, NOW(), NOW())
                        ");
                        $stmt->execute([
                            ':election_id' => $electionId,
                            ':name_ar'     => $nameAr !== '' ? $nameAr : null,
                            ':name_en'     => $nameEn !== '' ? $nameEn : null,
                            ':slug'        => $slug,
                            ':map_code'    => $mapCode !== '' ? $mapCode : null,
                            ':total_seats' => $totalSeats > 0 ? $totalSeats : 0,
                            ':sort_order'  => $sortOrder,
                        ]);
                        $flashSuccess = __('t_419d92dce9', 'تم إضافة الولاية / المنطقة.');
                    }

                    safe_redirect('regions.php?election_id=' . $electionId . '&saved=1');
                } catch (Throwable $e) {
                    @error_log('[elections regions] save_region error: ' . $e->getMessage());
                    $flashError = __('t_cb5e6b9f21', 'حدث خطأ أثناء حفظ بيانات الولاية / المنطقة.');
                }
            }

        } elseif ($action === 'delete_region') {
            $regionId = (int)($_POST['region_id'] ?? 0);
            if ($regionId > 0) {
                try {
                    $stmt = $pdo->prepare("
                        DELETE FROM election_regions
                        WHERE id = :id
                          AND election_id = :eid
                    ");
                    $stmt->execute([
                        ':id'  => $regionId,
                        ':eid' => $electionId,
                    ]);
                    $flashSuccess = __('t_d1dc412a92', 'تم حذف الولاية / المنطقة.');
                } catch (Throwable $e) {
                    @error_log('[elections regions] delete_region error: ' . $e->getMessage());
                    $flashError = __('t_04d075984c', 'تعذر حذف الولاية / المنطقة، ربما لها نتائج مرتبطة.');
                }
            }
        }
    }
}

if (isset($_GET['saved']) && !$flashSuccess && !$flashError) {
    $flashSuccess = __('t_7f524cfe48', 'تم حفظ التغييرات بنجاح.');
}

// ================== 3) جلب الولايات ==================
$regions = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, name_ar, name_en, slug, map_code, total_seats, sort_order, created_at, updated_at
        FROM election_regions
        WHERE election_id = :eid
        ORDER BY sort_order ASC, id ASC
    ");
    $stmt->execute([':eid' => $electionId]);
    $regions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    @error_log('[elections regions] fetch list error: ' . $e->getMessage());
}

// ولاية للتعديل؟
$editRegion = null;
$editId = (int)($_GET['id'] ?? 0);
if ($editId > 0 && $regions) {
    foreach ($regions as $r) {
        if ((int)$r['id'] === $editId) {
            $editRegion = $r;
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
        <h1 class="h4 mb-1"><?= h(__('t_97493e2ddc', 'إدارة الولايات / المناطق')) ?></h1>
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
        <a href="results.php?election_id=<?= (int)$currentElection['id'] ?>" class="btn btn-outline-success btn-sm">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          <?= h(__('t_53ffc98339', 'إدارة النتائج العامة')) ?>
        </a>
      </div>
    </div>

    <?php if ($flashSuccess): ?>
      <div class="alert alert-success py-2"><?= h($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
      <div class="alert alert-danger py-2"><?= h($flashError) ?></div>
    <?php endif; ?>

    <!-- فورم إضافة/تعديل ولاية -->
    <div class="card mb-3">
      <div class="card-header">
        <strong class="small"><?= $editRegion ? __('t_9b12b41f32', 'تعديل ولاية / منطقة') : __('t_765bccc9a7', 'إضافة ولاية / منطقة') ?></strong>
      </div>
      <div class="card-body">
        <form method="post" class="row g-3">
          <?php if (function_exists('csrf_field')): ?>
            <?= csrf_field('csrf_token') ?>
          <?php else: ?>
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">
          <?php endif; ?>
          <input type="hidden" name="action" value="save_region">
          <input type="hidden" name="region_id" value="<?= $editRegion ? (int)$editRegion['id'] : 0 ?>">

          <div class="col-md-4">
            <label class="form-label small"><?= h(__('t_4c994b13d7', 'اسم الولاية بالعربية')) ?></label>
            <input type="text" name="name_ar" class="form-control form-control-sm"
                   value="<?= h($editRegion['name_ar'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label small"><?= h(__('t_b2240bc40c', 'اسم الولاية بالإنجليزية')) ?></label>
            <input type="text" name="name_en" class="form-control form-control-sm"
                   value="<?= h($editRegion['name_en'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label small">
              <?= h(__('t_936bda7cb8', 'المعرف (slug)')) ?>
              <span class="text-muted"><?= h(__('t_653a350888', '(يُستخدم في الروابط، يملأ تلقائيًا إذا تُرك فارغًا)')) ?></span>
            </label>
            <input type="text" name="slug" class="form-control form-control-sm"
                   value="<?= h($editRegion['slug'] ?? '') ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label small">
              map_code
              <span class="text-muted"><?= h(__('t_c3b4b94e38', '(مثل: KHARTOUM, RED_SEA، مطابق لـ ID في خريطة SVG)')) ?></span>
            </label>
            <input type="text" name="map_code" class="form-control form-control-sm"
                   value="<?= h($editRegion['map_code'] ?? '') ?>">
          </div>

          <div class="col-md-3">
            <label class="form-label small"><?= h(__('t_cf4f202226', 'عدد المقاعد / الدوائر')) ?></label>
            <input type="number" name="total_seats" class="form-control form-control-sm"
                   value="<?= h((string)($editRegion['total_seats'] ?? 0)) ?>">
          </div>

          <div class="col-md-3">
            <label class="form-label small"><?= h(__('t_2fcc9e97b9', 'ترتيب العرض')) ?></label>
            <input type="number" name="sort_order" class="form-control form-control-sm"
                   value="<?= h((string)($editRegion['sort_order'] ?? 0)) ?>">
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

    <!-- جدول الولايات -->
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="small fw-semibold"><?= h(__('t_33bfe1f47d', 'الولايات / المناطق المسجلة')) ?></span>
        <span class="small text-muted">إجمالي: <?= count($regions) ?></span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th><?= h(__('t_2e8b171b46', 'الاسم')) ?></th>
              <th>slug</th>
              <th>map_code</th>
              <th class="text-center"><?= h(__('t_91bfba79c3', 'المقاعد')) ?></th>
              <th class="text-center"><?= h(__('t_ddda59289a', 'الترتيب')) ?></th>
              <th class="text-center"><?= h(__('t_901efe9b1c', 'إجراءات')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($regions): ?>
              <?php foreach ($regions as $r): ?>
                <tr>
                  <td><?= (int)$r['id'] ?></td>
                  <td>
                    <div class="fw-semibold small"><?= h($r['name_ar'] ?: $r['name_en'] ?: '—') ?></div>
                    <?php if (!empty($r['name_en']) && $r['name_ar']): ?>
                      <div class="small text-muted"><?= h($r['name_en']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="small"><code><?= h($r['slug']) ?></code></td>
                  <td class="small"><code><?= h($r['map_code']) ?></code></td>
                  <td class="text-center small"><?= (int)($r['total_seats'] ?? 0) ?></td>
                  <td class="text-center small"><?= (int)($r['sort_order'] ?? 0) ?></td>
                  <td class="text-center">
                    <div class="d-flex flex-wrap justify-content-center gap-1">
                      <a href="regions.php?election_id=<?= (int)$electionId ?>&id=<?= (int)$r['id'] ?>"
                         class="btn btn-outline-primary btn-sm"
                         title="<?= h(__('t_759fdc242e', 'تعديل')) ?>">
                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                      </a>
                      <a href="region_results.php?election_id=<?= (int)$electionId ?>&region_id=<?= (int)$r['id'] ?>"
                         class="btn btn-outline-success btn-sm"
                         title="<?= h(__('t_129a4c31a0', 'نتائج هذه الولاية')) ?>">
                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                      </a>
                      <form method="post"
                            data-confirm='حذف هذه الولاية / المنطقة؟ قد يحذف النتائج المرتبطة بها.'>
                        <?php if (function_exists('csrf_field')): ?>
                          <?= csrf_field('csrf_token') ?>
                        <?php else: ?>
                          <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">
                        <?php endif; ?>
                        <input type="hidden" name="action" value="delete_region">
                        <input type="hidden" name="region_id" value="<?= (int)$r['id'] ?>">
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
                <td colspan="7" class="text-center small text-muted py-3">
                  <?= h(__('t_a6c9c3084d', 'لا توجد ولايات / مناطق مسجلة لهذه التغطية حتى الآن.')) ?>
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
