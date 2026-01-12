<?php declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
// admin/elections/edit.php — تعديل تغطية انتخابية

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/_elections_lib.php';

// محاولة تحميل auth.php إن وجد (بدون كسر السكربت إن لم يوجد)
$authFile = __DIR__ . '/../../includes/auth.php';
if (file_exists($authFile)) {
    require_once $authFile;
}

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

/**
 * حماية لوحة التحكم:
 * - لو يوجد كلاس \Godyar\Auth نستعمله بمرونة
 * - لو لا، نرجع إلى الجلسات (admin_id أو user_role)
 */
$authorized = false;

if (class_exists('\Godyar\Auth')) {
    // نحاول استخدام isLoggedIn أو check إن وجدت
    if (method_exists('\Godyar\Auth', 'isLoggedIn') && \Godyar\Auth::isLoggedIn()) {
        $authorized = true;
    } elseif (method_exists('\Godyar\Auth', 'check') && \Godyar\Auth::check()) {
        // يمكن إضافة فحص role هنا إن أردت
        $authorized = true;
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

$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    die(__('t_f1ef308d2e', 'لا يوجد اتصال بقاعدة البيانات'));
}

// التأكد من وجود/ترقية جداول الانتخابات
gdy_elections_ensure_schema($pdo);

$currentPage = 'elections';
$pageTitle   = __('t_c948642bb2', 'تعديل تغطية انتخابية');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$errors        = [];
$title         = '';
$slug          = '';
$description   = '';
$status        = 'hidden';
$totalSeats    = null;
$majoritySeats = null;

// جلب البيانات الحالية
try {
    $stmt = $pdo->prepare("SELECT * FROM elections WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        header('Location: index.php?notfound=1');
        exit;
    }
    $title         = (string)($row['title'] ?? '');
    $slug          = (string)($row['slug'] ?? '');
    $description   = (string)($row['description'] ?? '');
    $status        = (string)($row['status'] ?? 'hidden');
    $totalSeats    = isset($row['total_seats']) ? (int)$row['total_seats'] : null;
    $majoritySeats = isset($row['majority_seats']) ? (int)$row['majority_seats'] : null;
} catch (Throwable $e) {
    @error_log('[Godyar Elections] fetch single error: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!function_exists('verify_csrf_token') || !verify_csrf_token($csrf)) {
        $errors[] = __('t_0f296c4fe0', 'فشل التحقق الأمني، يرجى إعادة المحاولة.');
    } else {
        $title       = trim((string)($_POST['title'] ?? ''));
        $slug        = trim((string)($_POST['slug'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $status      = (string)($_POST['status'] ?? 'hidden');

        // الحقول الجديدة: إجمالي المقاعد + الأغلبية
        $totalSeatsRaw    = trim((string)($_POST['total_seats'] ?? ''));
        $majoritySeatsRaw = trim((string)($_POST['majority_seats'] ?? ''));

        $totalSeats    = ($totalSeatsRaw !== '')    ? max(0, (int)$totalSeatsRaw)    : null;
        $majoritySeats = ($majoritySeatsRaw !== '') ? max(0, (int)$majoritySeatsRaw) : null;

        if ($title === '') {
            $errors[] = __('t_4e609efafe', 'الرجاء إدخال عنوان للتغطية الانتخابية.');
        }

        // توليد / تنظيف الـ slug
        if ($slug === '' && $title !== '') {
            $slug = gdy_elections_slugify($title);
        } elseif ($slug !== '') {
            $slug = gdy_elections_slugify($slug);
        }

        if (!in_array($status, ['visible','hidden','archived'], true)) {
            $status = 'hidden';
        }

        // تحقق بسيط: من الأفضل أن لا تكون مقاعد الأغلبية أكبر من الإجمالي
        if ($totalSeats !== null && $majoritySeats !== null && $majoritySeats > $totalSeats) {
            $errors[] = __('t_afc2d768c2', 'عدد مقاعد الأغلبية لا يمكن أن يتجاوز إجمالي المقاعد.');
        }

        if (!$errors) {
            try {
                // التأكد من عدم تكرار slug مع عناصر أخرى
                $baseSlug = $slug;
                $i = 1;
                while ($slug !== '') {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM elections WHERE slug = :slug AND id <> :id");
                    $stmt->execute([':slug' => $slug, ':id' => $id]);
                    $cnt = (int)$stmt->fetchColumn();
                    if ($cnt === 0) {
                        break;
                    }
                    $slug = $baseSlug . '-' . $i;
                    $i++;
                }

                $stmt = $pdo->prepare("
                    UPDATE elections
                    SET title          = :title,
                        slug           = :slug,
                        description    = :description,
                        status         = :status,
                        total_seats    = :total_seats,
                        majority_seats = :majority_seats,
                        updated_at     = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':title'          => $title,
                    ':slug'           => $slug,
                    ':description'    => $description,
                    ':status'         => $status,
                    ':total_seats'    => $totalSeats,
                    ':majority_seats' => $majoritySeats,
                    ':id'             => $id,
                ]);

                header('Location: index.php?updated=1');
                exit;
            } catch (Throwable $e) {
                $errors[] = __('t_838511a30f', 'حدث خطأ أثناء التحديث، يرجى المحاولة لاحقاً.');
                @error_log('[Godyar Elections] update error: ' . $e->getMessage());
            }
        }
    }
}

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}
$currentPage = 'elections';
$pageTitle   = __('t_b9af904113', 'الانتخابات');
$pageSubtitle= __('t_c948642bb2', 'تعديل تغطية انتخابية');
$adminBase = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/admin'), '/');
$breadcrumbs = [__('t_3aa8578699', 'الرئيسية') => $adminBase.'/index.php', __('t_b9af904113', 'الانتخابات') => null];
$pageActionsHtml = __('t_21f17dfb3c', '<a href="index.php" class="btn btn-gdy btn-outline"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> رجوع</a>');
require_once __DIR__ . '/../layout/app_start.php';
$csrf = generate_csrf_token();
?>
<main class="main-content gdy-elections-edit admin-content  py-4 ">
  <style>
    .gdy-elections-edit {
      background-color: #f3f4f6;
      min-height: 100vh;
    }
    @media (min-width: 992px) {
      .gdy-elections-edit {
        margin-right: 250px; /* عدّلها حسب عرض القائمة الجانبية عندك */
      }
    }
    .gdy-elections-edit .page-inner {
      padding: 1.5rem 0.75rem;
    }
    @media (min-width: 768px) {
      .gdy-elections-edit .page-inner {
        padding: 2rem 1.5rem;
      }
    }
    .gdy-elections-edit .card {
      border-radius: 0.85rem;
      border: 1px solid rgba(148, 163, 184, 0.35);
      box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
    }
    .gdy-elections-edit .card-header {
      background: linear-gradient(90deg, rgba(59,130,246,0.04), rgba(56,189,248,0.02));
      border-bottom-color: rgba(148, 163, 184, 0.3);
    }
    .gdy-elections-edit .form-label {
      font-size: 0.85rem;
      font-weight: 500;
    }
    .gdy-elections-edit .form-text {
      font-size: 0.75rem;
    }
    .gdy-elections-edit .badge-soft-muted {
      background-color: rgba(148,163,184,0.16);
      color: #4b5563;
      border-radius: 9999px;
      padding: 0.12rem 0.5rem;
      font-size: 0.75rem;
    }
  </style>

  <div class="page-inner ">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <div>
        <h1 class="h4 mb-1"><?= h(__('t_c948642bb2', 'تعديل تغطية انتخابية')) ?></h1>
        <p class="mb-0 small text-muted">
          <?= h(__('t_626349504b', 'تعديل البيانات الأساسية للتغطية الانتخابية، حالة العرض، وعدد المقاعد/الأغلبية.')) ?>
        </p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <?php if ($slug !== ''): ?>
          <a href="/elections.php?election=<?= rawurlencode($slug) ?>"
             target="_blank"
             class="btn btn-sm btn-outline-info">
            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <?= h(__('t_9c30b1d339', 'عرض التغطية على الموقع')) ?>
          </a>
        <?php endif; ?>
        <a href="index.php" class="btn btn-sm btn-outline-secondary">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_b6a95f6cdd', 'رجوع للقائمة')) ?>
        </a>
      </div>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach ($errors as $e): ?>
            <li><?= h($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="row g-3">
      <!-- النموذج الرئيسي -->
      <div class="col-lg-8">
        <div class="card shadow-sm">
          <div class="card-header">
            <span class="fw-semibold small"><?= h(__('t_abc48cf5b4', 'بيانات التغطية الانتخابية')) ?></span>
          </div>
          <div class="card-body">
            <form method="post">
              <?php if (function_exists('csrf_field')): ?>
                <?= csrf_field('csrf_token') ?>
              <?php else: ?>
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">
              <?php endif; ?>

              <div class="mb-3">
                <label class="form-label"><?= h(__('t_6a10895a03', 'عنوان التغطية الانتخابية')) ?></label>
                <input type="text"
                       name="title"
                       class="form-control form-control-sm"
                       required
                       value="<?= h($title) ?>">
                <div class="form-text">
                  <?= h(__('t_1e24b2e0f7', 'مثال: الانتخابات العامة 2025 – المجلس التشريعي.')) ?>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label"><?= h(__('t_0781965540', 'الرابط (Slug)')) ?></label>
                <input type="text"
                       name="slug"
                       class="form-control form-control-sm"
                       value="<?= h($slug) ?>">
                <div class="form-text">
                  <?= h(__('t_519640d9e0', 'يُستخدم في عنوان الرابط:')) ?> <code>/elections.php?election=slug</code> <?= h(__('t_33e28f5d57', '–
                  اتركه فارغاً ليتم توليده تلقائياً من العنوان.')) ?>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label"><?= h(__('t_28ed3bee25', 'وصف / ملاحظات داخلية')) ?></label>
                <textarea name="description"
                          rows="3"
                          class="form-control form-control-sm"><?= h($description) ?></textarea>
                <div class="form-text">
                  <?= h(__('t_f0e07b81f0', 'وصف قصير عن نوع الانتخابات، النطاق الجغرافي، أو أي ملاحظات تساعد فريق التحرير.')) ?>
                </div>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-md-4">
                  <label class="form-label"><?= h(__('t_1253eb5642', 'الحالة')) ?></label>
                  <select name="status" class="form-select form-select-sm">
                    <option value="visible"  <?= $status === 'visible'  ? 'selected' : '' ?>><?= h(__('t_2973521e00', 'ظاهر على الموقع')) ?></option>
                    <option value="hidden"   <?= $status === 'hidden'   ? 'selected' : '' ?>><?= h(__('t_dabfd20743', 'مخفي (للتحضير)')) ?></option>
                    <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>><?= h(__('t_ddfda53ee7', 'أرشيف (منتهية)')) ?></option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label"><?= h(__('t_2b007aa4a7', 'إجمالي المقاعد / الدوائر')) ?></label>
                  <input type="number"
                         name="total_seats"
                         min="0"
                         class="form-control form-control-sm"
                         value="<?= $totalSeats !== null ? (int)$totalSeats : '' ?>">
                  <div class="form-text">
                    <?= h(__('t_b429fca82c', 'عدد المقاعد أو الدوائر الكلي في هذه التغطية (اختياري ولكن يُفضّل تعبئته).')) ?>
                  </div>
                </div>
                <div class="col-md-4">
                  <label class="form-label"><?= h(__('t_99e286602e', 'مقاعد الأغلبية')) ?></label>
                  <input type="number"
                         name="majority_seats"
                         min="0"
                         class="form-control form-control-sm"
                         value="<?= $majoritySeats !== null ? (int)$majoritySeats : '' ?>">
                  <div class="form-text">
                    <?= h(__('t_07fe670437', 'الحد الأدنى من المقاعد المطلوبة لتشكيل أغلبية (إن وجد).')) ?>
                  </div>
                </div>
              </div>

              <button type="submit" class="btn btn-primary">
                <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_02f31ae27c', 'حفظ التغييرات')) ?>
              </button>
              <a href="index.php" class="btn btn-link text-muted">
                <?= h(__('t_7b0171f92b', 'إلغاء والرجوع للقائمة')) ?>
              </a>
            </form>
          </div>
        </div>
      </div>

      <!-- كرت جانبي لمحة سريعة -->
      <div class="col-lg-4">
        <div class="card shadow-sm mb-3">
          <div class="card-header">
            <span class="fw-semibold small"><?= h(__('t_d92dfacf66', 'لمحة سريعة عن التغطية')) ?></span>
          </div>
          <div class="card-body small">
            <p class="mb-1">
              <span class="text-muted"><?= h(__('t_0cce68b871', 'المعرّف الداخلي (ID):')) ?></span>
              <span class="badge-soft-muted"><?= (int)$id ?></span>
            </p>
            <p class="mb-1">
              <span class="text-muted"><?= h(__('t_477cd86663', 'حالة العرض الحالية:')) ?></span>
              <span class="badge-soft-muted">
                <?php
                  $stLabel = [
                    'visible'  => __('t_2fdd9abad6', 'ظاهرة على الموقع'),
                    'hidden'   => __('t_ad0a598276', 'مخفية'),
                    'archived' => __('t_c220a1c484', 'أرشيف')
                  ];
                  echo h($stLabel[$status] ?? $status);
                ?>
              </span>
            </p>
            <p class="mb-1">
              <span class="text-muted"><?= h(__('t_d509038ed1', 'إجمالي المقاعد / الدوائر:')) ?></span>
              <strong><?= $totalSeats !== null ? (int)$totalSeats : __('t_cd09c30d57', 'غير محدد') ?></strong>
            </p>
            <p class="mb-0">
              <span class="text-muted"><?= h(__('t_f3fe7e116c', 'مقاعد الأغلبية:')) ?></span>
              <strong><?= $majoritySeats !== null ? (int)$majoritySeats : __('t_cd09c30d57', 'غير محدد') ?></strong>
            </p>
            <hr>
            <p class="text-muted mb-1">
              <?= h(__('t_d6595bb629', 'بعد حفظ التغييرات يمكنك إدارة:')) ?>
            </p>
            <ul class="small mb-0 ps-3">
              <li><?= h(__('t_b9ea4fb2f2', 'الولايات / المناطق من صفحة')) ?> <a href="regions.php?election_id=<?= (int)$id ?>"><?= h(__('t_099b8cbd63', 'الولايات')) ?></a></li>
              <li><?= h(__('t_ae19d650d1', 'الأحزاب من صفحة')) ?> <a href="parties.php?election_id=<?= (int)$id ?>"><?= h(__('t_ccaed8db11', 'الأحزاب')) ?></a></li>
              <li><?= h(__('t_ba99692b30', 'النتائج من صفحة')) ?> <a href="results.php?election_id=<?= (int)$id ?>"><?= h(__('t_5f115f7184', 'النتائج')) ?></a></li>
            </ul>
          </div>
        </div>
      </div>

    </div> <!-- /.row -->
  </div> <!-- /.page-inner -->
</main>

<?php require_once __DIR__ . '/../layout/app_end.php'; ?>
