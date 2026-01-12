<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
// admin/elections/create.php — إضافة تغطية انتخابية جديدة

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/_elections_lib.php';
require_once __DIR__ . '/../../includes/auth.php';

use Godyar\Auth;

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

if (!Auth::isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    die(__('t_f1ef308d2e', 'لا يوجد اتصال بقاعدة البيانات'));
}

gdy_elections_ensure_schema($pdo);

$currentPage = 'elections';
$pageTitle   = __('t_091722bca3', 'إضافة تغطية انتخابية');

$errors = [];
$title  = '';
$slug   = '';
$description = '';
$status = 'hidden';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!function_exists('verify_csrf_token') || !verify_csrf_token($csrf)) {
        $errors[] = __('t_0f296c4fe0', 'فشل التحقق الأمني، يرجى إعادة المحاولة.');
    } else {
        $title = trim((string)($_POST['title'] ?? ''));
        $slug  = trim((string)($_POST['slug'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $status = (string)($_POST['status'] ?? 'hidden');

        if ($title === '') {
            $errors[] = __('t_4e609efafe', 'الرجاء إدخال عنوان للتغطية الانتخابية.');
        }

        if ($slug === '' && $title !== '') {
            $slug = gdy_elections_slugify($title);
        } elseif ($slug !== '') {
            $slug = gdy_elections_slugify($slug);
        }

        if (!in_array($status, ['visible','hidden','archived'], true)) {
            $status = 'hidden';
        }

        if (!$errors) {
            // التأكد من عدم تكرار slug
            try {
                $baseSlug = $slug;
                $i = 1;
                while (true) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM elections WHERE slug = :slug");
                    $stmt->execute([':slug' => $slug]);
                    $cnt = (int)$stmt->fetchColumn();
                    if ($cnt === 0) {
                        break;
                    }
                    $slug = $baseSlug . '-' . $i;
                    $i++;
                }

                $stmt = $pdo->prepare("
                    INSERT INTO elections (title, slug, description, status, created_at, updated_at)
                    VALUES (:title, :slug, :description, :status, NOW(), NOW())
                ");
                $stmt->execute([
                    ':title'       => $title,
                    ':slug'        => $slug,
                    ':description' => $description,
                    ':status'      => $status,
                ]);

                header('Location: index.php?created=1');
                exit;
            } catch (Throwable $e) {
                $errors[] = __('t_3dc686372e', 'حدث خطأ أثناء الحفظ، يرجى المحاولة لاحقاً.');
                @error_log('[Godyar Elections] create error: ' . $e->getMessage());
            }
        }
    }
}

$currentPage = 'elections';
$pageTitle   = __('t_b9af904113', 'الانتخابات');
$pageSubtitle= __('t_f34c8ad151', 'إضافة تغطية انتخابية جديدة');
$adminBase = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/admin'), '/');
$breadcrumbs = [__('t_3aa8578699', 'الرئيسية') => $adminBase.'/index.php', __('t_b9af904113', 'الانتخابات') => null];
$pageActionsHtml = __('t_5863f7280a', '<a href="index.php" class="btn btn-gdy-outline"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> رجوع</a>');
require_once __DIR__ . '/../layout/app_start.php';
$csrf = generate_csrf_token();
?>
<div class="admin-content  py-4 ">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h4 mb-1"><?= h(__('t_3b33450e32', 'تغطية انتخابية جديدة')) ?></h1>
      <p class="mb-0 small text-muted"><?= h(__('t_e19fe64f2e', 'إضافة قسم مخصص لتغطية انتخابات معينة يمكن إظهاره أو إخفاؤه من القائمة.')) ?></p>
    </div>
    <a href="index.php" class="btn btn-sm btn-outline-secondary">
      <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_b6a95f6cdd', 'رجوع للقائمة')) ?>
    </a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $e): ?>
          <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="post">
        <?php csrf_field(); ?>
        <div class="mb-3">
          <label class="form-label"><?= h(__('t_6a10895a03', 'عنوان التغطية الانتخابية')) ?></label>
          <input type="text" name="title" class="form-control" required
                 value="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">
          <div class="form-text"><?= h(__('t_385e0294c1', 'مثال: انتخابات برلمان 2025 - السودان')) ?></div>
        </div>

        <div class="mb-3">
          <label class="form-label"><?= h(__('t_0781965540', 'الرابط (Slug)')) ?></label>
          <input type="text" name="slug" class="form-control"
                 value="<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>">
          <div class="form-text"><?= h(__('t_f5284920c8', 'يمكن تركه فارغاً ليتم توليده تلقائياً من العنوان.')) ?></div>
        </div>

        <div class="mb-3">
          <label class="form-label"><?= h(__('t_28ed3bee25', 'وصف / ملاحظات داخلية')) ?></label>
          <textarea name="description" rows="3" class="form-control"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></textarea>
          <div class="form-text"><?= h(__('t_40fe0982af', 'وصف داخلي للتغطية أو ملاحظات لفريق التحرير (لا يظهر للزوار).')) ?></div>
        </div>

        <div class="mb-3">
          <label class="form-label"><?= h(__('t_03a577f69a', 'الحالة المبدئية')) ?></label>
          <select name="status" class="form-select">
            <option value="visible" <?= $status === 'visible' ? 'selected' : '' ?>><?= h(__('t_2973521e00', 'ظاهر على الموقع')) ?></option>
            <option value="hidden" <?= $status === 'hidden' ? 'selected' : '' ?>><?= h(__('t_a39aacaa71', 'مخفي')) ?></option>
            <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>><?= h(__('t_c220a1c484', 'أرشيف')) ?></option>
          </select>
        </div>

        <button type="submit" class="btn btn-primary">
          <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_c4e40e27fb', 'حفظ التغطية')) ?>
        </button>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../layout/app_end.php'; ?>
