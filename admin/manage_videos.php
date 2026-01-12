<?php
// /godyar/admin/manage_videos.php
declare(strict_types=1);


require_once __DIR__ . '/_admin_guard.php';
require_once __DIR__ . '/../includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

// إعداد بيانات الصفحة
$currentPage = 'videos';
$pageTitle   = __('t_c930ea3a42', 'إدارة الفيديوهات المميزة');

// دالة هروب بسيطة
if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    die(__('t_d1569354af', 'تعذّر الاتصال بقاعدة البيانات.'));
}

$errors  = [];
$success = '';
$editing = null;
$videos  = [];
$tableMissing = false;

// ========================
// حذف فيديو
// ========================
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM featured_videos WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $success = __('t_a0aac81546', 'تم حذف الفيديو بنجاح.');
        } catch (Throwable $e) {
            $errors[] = __('t_efb6890f77', 'تعذّر حذف الفيديو، يرجى المحاولة لاحقاً.');
            @error_log('[Manage Videos] Delete error: ' . $e->getMessage());
        }
    }
}

// ========================
// تحميل بيانات فيديو للتعديل
// ========================
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    if ($id > 0) {
        try {
            $stmt    = $pdo->prepare("SELECT * FROM featured_videos WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $editing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            @error_log('[Manage Videos] Load edit error: ' . $e->getMessage());
        }
    }
}

// ========================
// حفظ / تحديث فيديو
// ========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (function_exists('validate_csrf_token') && !validate_csrf_token($csrf)) {
        $errors[] = __('t_fbbc004136', 'رمز الحماية (CSRF) غير صالح، يرجى إعادة المحاولة.');
    } else {
        $id          = (int)($_POST['id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $url         = trim($_POST['url'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isActive    = isset($_POST['is_active']) ? 1 : 0;

        // التحقق من المدخلات
        if ($title === '') {
            $errors[] = __('t_38d6011714', 'يرجى إدخال عنوان الفيديو.');
        }

        if ($url === '') {
            $errors[] = __('t_7ee97cc87f', 'يرجى إدخال رابط الفيديو.');
        } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
            $errors[] = __('t_0ab6a291ed', 'يرجى إدخال رابط صحيح يبدأ بـ http أو https.');
        }

        if (!$errors) {
            try {
                if ($id > 0) {
                    // تحديث
                    $stmt = $pdo->prepare("
                        UPDATE featured_videos
                        SET title       = :title,
                            video_url   = :video_url,
                            description = :description,
                            is_active   = :is_active,
                            updated_at  = NOW()
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':title'       => $title,
                        ':video_url'   => $url,          // نخزن الرابط الخام
                        ':description' => $description,
                        ':is_active'   => $isActive,
                        ':id'          => $id,
                    ]);
                    $success = __('t_0f4f44d63c', 'تم تحديث الفيديو بنجاح.');
                } else {
                    // إضافة
                    $stmt = $pdo->prepare("
                        INSERT INTO featured_videos
                            (title, video_url, description, is_active, created_by, created_at, updated_at)
                        VALUES
                            (:title, :video_url, :description, :is_active, :created_by, NOW(), NOW())
                    ");
                    $stmt->execute([
                        ':title'       => $title,
                        ':video_url'   => $url,
                        ':description' => $description,
                        ':is_active'   => $isActive,
                        ':created_by'  => (int)($_SESSION['user']['id'] ?? 0),
                    ]);
                    $success = __('t_b8238932d4', 'تمت إضافة الفيديو بنجاح.');
                }

                $editing = null;
            } catch (Throwable $e) {
                $msg      = $e->getMessage();
                $errors[] = __('t_a7e651d555', 'حدث خطأ أثناء حفظ البيانات في قاعدة البيانات: ') . $msg;
                @error_log('[Manage Videos] Save error: ' . $msg);
            }
        }
    }
}

// ========================
// تحميل جميع الفيديوهات
// ========================
try {
    $stmt   = $pdo->query("SELECT * FROM featured_videos ORDER BY created_at DESC, id DESC");
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    @error_log('[Manage Videos] Fetch error: ' . $e->getMessage());
    $tableMissing = true;
}

// CSRF
$csrfToken = function_exists('generate_csrf_token')
    ? generate_csrf_token()
    : bin2hex(random_bytes(16));

// تضمين ترويسة ولوحة جانبية
require_once __DIR__ . '/layout/header.php';
require_once __DIR__ . '/layout/sidebar.php';
?>
<?php if (!empty($tableMissing)): ?>
  <div class="alert alert-warning" style="margin:12px 18px;border-radius:10px;">
    تنبيه: جدول <code>featured_videos</code> غير موجود. 
    <a href="/admin/create_featured_videos_table.php" style="font-weight:700;">انقر هنا لإنشاء الجدول</a>.
  </div>
<?php endif; ?>

<style>
:root{
    /* نضغط عرض محتوى الصفحة بحيث يكون مريح بجانب السايدبار */
    --gdy-shell-max: min(880px, 100vw - 360px);
}

/* منع التمرير الأفقي وتوحيد الخلفية والنص */
html, body{
    overflow-x:hidden;
    background:#020617;
    color:#e5e7eb;
}

/* تقليل عرض المحتوى وتوسيطه */
.admin-content{
    max-width: var(--gdy-shell-max);
    width:100%;
    margin:0 auto;
}

/* تقليل البادينغ العمودي الافتراضي */
.admin-content.container-fluid.py-4{
    padding-top:0.75rem !important;
    padding-bottom:1rem !important;
}

/* رأس الصفحة */
.gdy-page-header{
    margin-bottom:0.75rem;
}

/* كروت الفيديو + الجدول بنفس التصميم الناعم */
.video-card {
    border-radius: 1.1rem;
    background: radial-gradient(circle at top, #020617 0, #020617 45%, #020617 100%);
    border: 1px solid rgba(148,163,184,0.25);
    box-shadow: 0 16px 40px rgba(15,23,42,0.45);
    transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
}
.video-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 24px 60px rgba(15,23,42,0.65);
    border-color:#0ea5e9;
}

/* شارة الحالة */
.video-badge-active {
    background: rgba(16,185,129,.08);
    color: #4ade80;
    border-radius: 999px;
    padding: .15rem .7rem;
    font-size: .75rem;
    border:1px solid rgba(34,197,94,0.3);
}
.video-badge-inactive {
    background: rgba(239,68,68,.08);
    color: #f87171;
    border-radius: 999px;
    padding: .15rem .7rem;
    font-size: .75rem;
    border:1px solid rgba(248,113,113,0.3);
}

/* تحسين الجدول */
.table-dark{
    --bs-table-bg: rgba(15,23,42,0.95);
    --bs-table-striped-bg: rgba(15,23,42,0.9);
    --bs-table-striped-color: #e5e7eb;
    --bs-table-border-color: rgba(30,64,175,0.35);
}
.table thead th{
    background:#020617;
    border-bottom-color:rgba(148,163,184,0.4);
    font-size:0.8rem;
    color:#9ca3af;
}

/* استجابة أفضل مع الشاشات الصغيرة */
@media (max-width: 992px){
    :root{
        --gdy-shell-max: 100vw; /* في الشاشات الصغيرة، المحتوى يأخذ العرض كاملاً */
    }
}
</style>

<div class="admin-content container-fluid py-4">
    <div class="gdy-page-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-1 text-white"><?= h(__('t_c930ea3a42', 'إدارة الفيديوهات المميزة')) ?></h1>
            <p class="text-muted mb-0">
                <?= h(__('t_8dbe1cbde5', 'يمكنك هنا إضافة/تعديل فيديوهات من المنصات المختلفة (YouTube، Facebook، TikTok، Instagram، Snapchat، وغيرها)
                ليتم عرضها في واجهة الموقع مع خيار المشاهدة داخل الموقع أو الانتقال للمنصة الأصلية.')) ?>
            </p>
        </div>
        <a href="index.php" class="btn btn-outline-light btn-sm">
            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_2f09126266', 'العودة للوحة التحكم')) ?>
        </a>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <?= h(__('t_4e7e8d83c3', 'حدثت الأخطاء التالية:')) ?>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $e): ?>
                    <li><?= h($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h($success) ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- نموذج إضافة / تعديل -->
        <div class="col-lg-4">
            <div class="card video-card border-0">
                <div class="card-body">
                    <h2 class="h6 mb-3">
                        <svg class="gdy-icon me-2 text-info" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                        <?= $editing ? __('t_3dc5805e67', 'تعديل الفيديو') : __('t_ae2be6f43c', 'إضافة فيديو جديد') ?>
                    </h2>

                    <form method="post" action="">
                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                        <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">

                        <div class="mb-3">
                            <label class="form-label"><?= h(__('t_91d18bfdaf', 'عنوان الفيديو')) ?></label>
                            <input type="text"
                                   name="title"
                                   class="form-control"
                                   required
                                   value="<?= h($editing['title'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                <?= h(__('t_b16a72514e', 'رابط الفيديو (YouTube / Facebook / TikTok / Instagram / Snapchat / Vimeo / Dailymotion)')) ?>
                            </label>
                            <input type="url"
                                   name="url"
                                   id="video_url"
                                   class="form-control"
                                   required
                                   value="<?= h($editing['video_url'] ?? '') ?>"
                                   placeholder="<?= h(__('t_85dbfef47a', 'مثال: https://www.youtube.com/watch?v=XXXX أو https://www.tiktok.com/... أو https://fb.watch/...')) ?>">
                            <div class="form-text text-muted">
                                <?= h(__('t_3522381271', '✅ يدعم أغلب منصات الفيديو الشهيرة.')) ?><br>
                                <?= h(__('t_d181fc0889', '⚠ بعض المنصات مثل Instagram و Snapchat قد لا تسمح بالتشغيل داخل الموقع،
                                وفي هذه الحالة سيتم فتح الفيديو في تبويب جديد على المنصة الأصلية.')) ?>
                                <br>
                                <?= h(__('t_138a029459', 'لاختبار الرابط:')) ?>
                                <a href="#" id="testVideoLink" target="_blank" rel="noopener noreferrer"><?= h(__('t_7b0fb866e4', 'افتح الرابط في نافذة جديدة')) ?></a>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?= h(__('t_81edd198f5', 'وصف مختصر')) ?></label>
                            <textarea name="description"
                                      class="form-control"
                                      rows="3"><?= h($editing['description'] ?? '') ?></textarea>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input"
                                   type="checkbox"
                                   name="is_active"
                                   id="is_active"
                                   value="1"
                                <?= !isset($editing['is_active']) || (int)$editing['is_active'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_active">
                                <?= h(__('t_67be8c29d6', 'تفعيل عرض هذا الفيديو في الواجهة')) ?>
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                            <?= $editing ? __('t_35f75fe13d', 'تحديث الفيديو') : __('t_417b6442fa', 'حفظ الفيديو') ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- قائمة الفيديوهات -->
        <div class="col-lg-8">
            <div class="card video-card border-0">
                <div class="card-body">
                    <h2 class="h6 mb-3">
                        <svg class="gdy-icon me-2 text-info" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                        <?= h(__('t_569c2cfc5d', 'قائمة الفيديوهات')) ?>
                    </h2>

                    <?php if (!$videos): ?>
                        <p class="text-muted mb-0"><?= h(__('t_939b14dffe', 'لا توجد فيديوهات مضافة بعد.')) ?></p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-striped align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th><?= h(__('t_6dc6588082', 'العنوان')) ?></th>
                                    <th><?= h(__('t_1253eb5642', 'الحالة')) ?></th>
                                    <th><?= h(__('t_8456f22b47', 'التاريخ')) ?></th>
                                    <th class="text-center"><?= h(__('t_901efe9b1c', 'إجراءات')) ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($videos as $index => $v): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td>
                                            <div class="fw-semibold mb-1"><?= h($v['title'] ?? '') ?></div>
                                            <div class="small text-muted text-truncate" style="max-width: 260px;">
                                                <?= h($v['video_url'] ?? '') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ((int)($v['is_active'] ?? 0) === 1): ?>
                                                <span class="video-badge-active"><?= h(__('t_918499f2af', 'مفعل')) ?></span>
                                            <?php else: ?>
                                                <span class="video-badge-inactive"><?= h(__('t_60dfc10f77', 'غير مفعل')) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted">
                                            <?php if (!empty($v['created_at'])): ?>
                                                <?= h($v['created_at']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="?edit=<?= (int)$v['id'] ?>" class="btn btn-sm btn-outline-info me-1">
                                                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                            </a>
                                            <a href="?delete=<?= (int)$v['id'] ?>"
                                               class="btn btn-sm btn-outline-danger"
                                               data-confirm='هل أنت متأكد من حذف هذا الفيديو؟'>
                                                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
// دعم اختبار الرابط والتحقق البسيط من صحة الـ URL
document.addEventListener('DOMContentLoaded', function() {
    const videoUrlInput = document.getElementById('video_url');
    const testLink = document.getElementById('testVideoLink');
    const form = document.querySelector('form');

    if (videoUrlInput && testLink) {
        // تحديث رابط الاختبار
        function syncTestLink() {
            const url = videoUrlInput.value.trim();
            testLink.href = url || '#';
        }

        syncTestLink();
        videoUrlInput.addEventListener('input', syncTestLink);
    }

    if (form && videoUrlInput) {
        form.addEventListener('submit', function(e) {
            const url = videoUrlInput.value.trim();
            if (!url) {
                alert('يرجى إدخال رابط الفيديو.');
                videoUrlInput.focus();
                e.preventDefault();
                return;
            }

            // تحقق بسيط أن الرابط يبدأ بـ http أو https
            if (!/^https?:\/\/.+/i.test(url)) {
                alert('يرجى إدخال رابط صحيح يبدأ بـ http أو https.');
                videoUrlInput.focus();
                e.preventDefault();
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
