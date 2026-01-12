<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';

use Godyar\Auth;

if (!Auth::isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$currentPage = 'opinion_authors';
$pageTitle   = __('t_1844b3bc6d', 'إضافة كاتب رأي جديد');

$pdo = gdy_pdo_safe();

// هيلبر للهروب الآمن
if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// دالة بسيطة لتوليد slug من الاسم
if (!function_exists('godyar_slugify')) {
    function godyar_slugify(string $text): string {
        $text = trim($text);
        // استبدال المسافات والرموز بشرطة -
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        // إزالة الشرطات الزائدة
        $text = trim((string)$text, '-');
        // إلى أحرف صغيرة
        $text = mb_strtolower($text);
        // إزالة أي أحرف غير مسموحة
        $text = preg_replace('~[^-\w]+~', '', $text);
        if (empty($text)) {
            $text = 'author-' . time();
        }
        return $text;
    }
}

// قيم النموذج الافتراضية
$data = [
    'name'            => '',
    'slug'            => '',
    'page_title'      => '',
    'bio'             => '',
    'specialization'  => '',
    'avatar'          => '',
    'social_twitter'  => '',
    'social_website'  => '',
    'social_facebook' => '',
    'email'           => '',
    'is_active'       => 1,
    'display_order'   => 0,
];

$errors   = [];
$success  = null;
$authorId = null;

// التحقق من وجود جدول الكُتاب
$tableExists = false;
if ($pdo instanceof PDO) {
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'opinion_authors'");
        $tableExists = $check && $check->fetchColumn();
    } catch (Exception $e) {
        error_log(__('t_9871fe69e5', 'خطأ في التحقق من جدول كُتاب الرأي: ') . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // جمع البيانات مع التنظيف
    $data['name']           = trim((string)($_POST['name'] ?? ''));
    $data['slug']           = trim((string)($_POST['slug'] ?? ''));
    $data['page_title']     = trim((string)($_POST['page_title'] ?? ''));
    $data['bio']            = trim((string)($_POST['bio'] ?? ''));
    $data['specialization'] = trim((string)($_POST['specialization'] ?? ''));
    $data['avatar']         = trim((string)($_POST['avatar'] ?? ''));
    $data['social_twitter'] = trim((string)($_POST['social_twitter'] ?? ''));
    $data['social_website'] = trim((string)($_POST['social_website'] ?? ''));
    $data['social_facebook']= trim((string)($_POST['social_facebook'] ?? ''));
    $data['email']          = trim((string)($_POST['email'] ?? ''));
    $data['is_active']      = isset($_POST['is_active']) ? 1 : 0;
    $data['display_order']  = (int)($_POST['display_order'] ?? 0);

    // في حال لم يُكتب slug، نولّده من الاسم
    if ($data['slug'] === '' && $data['name'] !== '') {
        $data['slug'] = godyar_slugify($data['name']);
    }

    // في حال لم يُكتب اسم الصفحة، نولّده تلقائياً من الاسم
    if ($data['page_title'] === '' && $data['name'] !== '') {
        $data['page_title'] = __('t_e94800687a', 'مقالات ') . $data['name'];
    }


    // التحقق من الصحة
    if (empty($data['name'])) {
        $errors[] = __('t_4c5bf14294', 'اسم الكاتب مطلوب.');
    } elseif (mb_strlen($data['name']) > 255) {
        $errors[] = __('t_962655659e', 'اسم الكاتب يجب أن لا يتجاوز 255 حرفاً.');
    }

    if (mb_strlen($data['slug']) > 190) {
        $errors[] = __('t_d592e791ed', 'الرابط (slug) يجب أن لا يتجاوز 190 حرفاً.');
    }
    if (mb_strlen($data['page_title']) > 255) {
        $errors[] = __('t_0b5dad2a74', 'اسم الصفحة يجب أن لا يتجاوز 255 حرفاً.');
    }


    if (mb_strlen($data['bio']) > 1000) {
        $errors[] = __('t_33e6e5920e', 'السيرة الذاتية يجب أن لا تتجاوز 1000 حرف.');
    }

    if (mb_strlen($data['specialization']) > 255) {
        $errors[] = __('t_f32b08c9e3', 'التخصص يجب أن لا يتجاوز 255 حرف.');
    }

    if (!$errors && $pdo instanceof PDO && $tableExists) {
        try {
            // التحقق من عدم تكرار slug
            if ($data['slug'] !== '') {
                $checkSlug = $pdo->prepare("SELECT id FROM opinion_authors WHERE slug = ? LIMIT 1");
                $checkSlug->execute([$data['slug']]);
                if ($checkSlug->fetch()) {
                    $errors[] = __('t_2e2a90c5a2', 'الرابط (slug) مستخدم لكاتب آخر، يرجى تغييره.');
                }
            }

            if (!$errors) {
                // نستخدم display_order كقيمة لـ sort_order أيضاً
                $sortOrder   = $data['display_order'];
                $isActive    = $data['is_active'] ? 1 : 0;

                $sql = "INSERT INTO opinion_authors
                        (name, slug, page_title, bio, specialization, social_website, social_twitter, social_facebook, email, avatar, is_active, sort_order, display_order, articles_count, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $data['name'],
                    $data['slug'],
                    $data['page_title'],
                    $data['bio'],
                    $data['specialization'],
                    $data['social_website'],
                    $data['social_twitter'],
                    $data['social_facebook'],
                    $data['email'],
                    $data['avatar'],
                    $isActive,
                    $sortOrder,
                    $data['display_order'],
                ]);

                $authorId = $pdo->lastInsertId();
                $success  = __('t_7b131cd2f8', 'تم إضافة الكاتب بنجاح.');

                // إعادة توجيه حسب الزر
                if (isset($_POST['save_and_list'])) {
                    header('Location: index.php?success=created');
                    exit;
                } elseif (isset($_POST['save_and_edit']) && $authorId) {
                    header('Location: edit.php?id=' . $authorId . '&success=created');
                    exit;
                } else {
                    // إعادة ضبط النموذج للبقاء في الصفحة
                    $data = [
                        'name'            => '',
                        'slug'            => '',
                        'page_title'      => '',
                        'bio'             => '',
                        'specialization'  => '',
                        'avatar'          => '',
                        'social_twitter'  => '',
                        'social_website'  => '',
                        'is_active'       => 1,
                        'display_order'   => 0,
                    ];
                }
            }

        } catch (Throwable $e) {
            $errors[] = __('t_1c1d455744', 'تعذر حفظ بيانات الكاتب: ') . $e->getMessage();
            error_log('[Godyar Opinion Authors Create] ' . $e->getMessage());
        }
    } elseif (!$tableExists) {
        $errors[] = __('t_e6ef114d4a', 'جدول كُتاب الرأي غير موجود. يرجى التواصل مع المسؤول.');
    }
}

// Professional unified admin shell
$pageSubtitle = __('t_d757c4e308', 'إضافة كاتب رأي جديد وربطه بالمقالات');
$breadcrumbs = [
    __('t_3aa8578699', 'الرئيسية') => (function_exists('base_url') ? rtrim(base_url(),'/') : '') . '/admin/index.php',
    __('t_7175662e6e', 'كتاب الرأي') => (function_exists('base_url') ? rtrim(base_url(),'/') : '') . '/admin/opinion_authors/index.php',
    __('t_cca6ca220c', 'إضافة كاتب') => null,
];
$pageActionsHtml = __('t_acdad3d9f7', '<a href="index.php" class="btn btn-gdy btn-gdy-outline"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> العودة</a>');
require_once __DIR__ . '/../layout/app_start.php';
?>

<style>
:root{
    /* نضغط عرض محتوى صفحة كُتاب الرأي ليكون مريحاً بجانب السايدبار */
    --gdy-shell-max: min(880px, 100vw - 360px);
}

/* NOTE: global shell styles handled by admin-ui.css */
.admin-content.container-fluid.py-4{
    padding-top:0.75rem !important;
    padding-bottom:1rem !important;
}

/* ضبط هامش رأس الصفحة */
.gdy-page-header{
    margin-bottom:0.75rem;
}


.gdy-form-card {
    background: rgba(15, 23, 42, 0.8);
    backdrop-filter: blur(10px);
    border-radius: 1.5rem;
    border: 1px solid rgba(148, 163, 184, 0.2);
}
.gdy-form-sidebar {
    background: rgba(30, 41, 59, 0.6);
    border-radius: 1rem;
    border: 1px solid rgba(148, 163, 184, 0.2);
}
.char-count {
    font-size: 0.8rem;
    color: #64748b;
}
.char-count.warning {
    color: #f59e0b;
}
.char-count.danger {
    color: #ef4444;
}
.avatar-preview {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #334155;
    margin-bottom: 1rem;
}
</style>

<?php /* Header handled by app_start */ ?>

    <?php if (!$tableExists): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <strong><?= h(__('t_b83c3996d9', 'تنبيه:')) ?></strong> <?= h(__('t_95f05efc73', 'جدول كُتاب الرأي غير موجود.')) ?> 
            <a href="create_table.php" class="alert-link"><?= h(__('t_98b74d89fa', 'انقر هنا لإنشاء الجدول')) ?></a>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <?= h($success) ?>
            <div class="mt-2">
                <a href="index.php" class="btn btn-sm btn-outline-success me-2"><?= h(__('t_1eab5c6c10', 'عرض الكل')) ?></a>
                <?php if ($authorId): ?>
                    <a href="edit.php?id=<?= $authorId ?>" class="btn btn-sm btn-success"><?= h(__('t_12a0ab810c', 'تعديل البيانات')) ?></a>
                <?php endif; ?>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <strong><?= h(__('t_c83b645dc3', 'حدث خطأ:')) ?></strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $err): ?>
                    <li><?= h($err) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="post" id="authorForm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

        <div class="row g-4">
            <!-- العمود الرئيسي -->
            <div class="col-lg-8">
                <div class="gdy-form-card card shadow-sm">
                    <div class="card-body">
                        <!-- المعلومات الأساسية -->
                        <h5 class="card-title mb-4"><?= h(__('t_0f6bc67891', 'المعلومات الأساسية')) ?></h5>
                        
                        <!-- الاسم -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold"><?= h(__('t_b27a56a1d8', 'اسم الكاتب *')) ?></label>
                            <input type="text" name="name" class="form-control form-control-lg"
                                   value="<?= h($data['name']) ?>" 
                                   placeholder="<?= h(__('t_2c2da2cd5e', 'أدخل اسم الكاتب...')) ?>" 
                                   required
                                   maxlength="255">
                            <div class="char-count mt-1" id="nameCount">0/255</div>
                        </div>

                        <!-- slug -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold"><?= h(__('t_0781965540', 'الرابط (Slug)')) ?></label>
                            <input type="text" name="slug" class="form-control"
                                   value="<?= h($data['slug']) ?>" 
                                   placeholder="<?= h(__('t_1ff4d5864e', 'اتركه فارغاً ليُولّد آلياً من الاسم')) ?>">
                        </div>

                                                <!-- اسم صفحة الكاتب -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold"><?= h(__('t_054a8d7cb9', 'اسم صفحة الكاتب')) ?></label>
                            <input type="text" name="page_title" class="form-control"
                                   value="<?= h($data['page_title']) ?>" 
                                   placeholder="<?= h(__('t_7ac3494fc7', 'اتركه فارغاً ليُولّد آلياً مثل: مقالات اسم الكاتب')) ?>">
                            <div class="form-text"><?= h(__('t_cf9d61286d', 'يمكن استخدامه كعنوان رئيسي لصفحة الكاتب وفي إعدادات الـ SEO.')) ?></div>
                        </div>

<!-- التخصص -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold"><?= h(__('t_73cc87b77c', 'التخصص')) ?></label>
                            <input type="text" name="specialization" class="form-control"
                                   value="<?= h($data['specialization']) ?>" 
                                   placeholder="<?= h(__('t_2e7e431ee1', 'مثال: كاتب رأي سياسي، محلل اقتصادي...')) ?>"
                                   maxlength="255">
                            <div class="char-count mt-1" id="specializationCount">0/255</div>
                        </div>

                        <!-- السيرة الذاتية -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold"><?= h(__('t_07bdba3bb4', 'السيرة الذاتية')) ?></label>
                            <textarea name="bio" rows="5" class="form-control"
                                      placeholder="<?= h(__('t_481b769690', 'اكتب سيرة ذاتية مختصرة عن الكاتب...')) ?>"
                                      maxlength="1000"><?= h($data['bio']) ?></textarea>
                            <div class="char-count mt-1" id="bioCount">0/1000</div>
                        </div>

                        <!-- وسائل التواصل الاجتماعي -->
                        <h5 class="card-title mb-4"><?= h(__('t_1d59fa44bb', 'وسائل التواصل الاجتماعي')) ?></h5>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label"><?= h(__('t_989510d6cd', 'تويتر')) ?></label>
                                <input type="url" name="social_twitter" class="form-control"
                                       value="<?= h($data['social_twitter']) ?>" 
                                       placeholder="https://twitter.com/...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?= h(__('t_d5b4c8ec57', 'موقع شخصي')) ?></label>
                                <input type="url" name="social_website" class="form-control"
                                       value="<?= h($data['social_website']) ?>" 
                                       placeholder="https://example.com">
                            </div>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <label class="form-label"><?= h(__('t_efa3efaed6', 'فيسبوك')) ?></label>
                                <input type="url" name="social_facebook" class="form-control"
                                       value="<?= h($data['social_facebook']) ?>" 
                                       placeholder="https://facebook.com/...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?= h(__('t_2436aacc18', 'البريد الإلكتروني')) ?></label>
                                <input type="email" name="email" class="form-control"
                                       value="<?= h($data['email']) ?>" 
                                       placeholder="example@email.com">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- الشريط الجانبي -->
            <div class="col-lg-4">
                <div class="gdy-form-sidebar card shadow-sm">
                    <div class="card-body">
                        <!-- الصورة الشخصية -->
                        <div class="mb-4 text-center">
                            <label class="form-label fw-semibold"><?= h(__('t_d55b78f864', 'الصورة الشخصية')) ?></label>
                            <div id="avatarPreview" class="d-flex justify-content-center mb-3">
                                <img src="<?= !empty($data['avatar']) ? h($data['avatar']) : __('t_314796b748', 'https://via.placeholder.com/120x120/0f172a/64748b?text=صورة') ?>" 
                                     class="avatar-preview" 
                                     id="avatarImage"
                                     data-fallback-src="https://via.placeholder.com/120x120/0f172a/64748b?text=صورة">
                            </div>
                            <input type="url" name="avatar" class="form-control"
                                   value="<?= h($data['avatar']) ?>" 
                                   placeholder="<?= h(__('t_4c1f195836', 'رابط الصورة...')) ?>"
                                   id="avatarInput">
                            <div class="form-text mt-2"><?= h(__('t_9f92f83048', 'أدخل رابط الصورة أو اتركه فارغاً للصورة الافتراضية')) ?></div>
                        </div>

                        <!-- ترتيب العرض -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold"><?= h(__('t_2fcc9e97b9', 'ترتيب العرض')) ?></label>
                            <input type="number" name="display_order" class="form-control"
                                   value="<?= h($data['display_order']) ?>" 
                                   min="0" step="1">
                            <div class="form-text"><?= h(__('t_73765703c9', 'رقم أقل يعني ظهوراً أبكر')) ?></div>
                        </div>

                        <!-- الحالة -->
                        <div class="mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" 
                                       id="isActive" value="1" <?= $data['is_active'] ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="isActive">
                                    <?= h(__('t_cb1d45be00', '✅ كاتب نشط')) ?>
                                </label>
                            </div>
                        </div>

                        <!-- أزرار الحفظ -->
                        <div class="border-top pt-4">
                            <button type="submit" name="save" class="btn btn-primary w-100 mb-2">
                                <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                <?= h(__('t_20dc5ae016', 'حفظ الكاتب')) ?>
                            </button>
                            
                            <button type="submit" name="save_and_edit" class="btn btn-outline-primary w-100 mb-2">
                                <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                <?= h(__('t_dda4dd7c65', 'حفظ والمتابعة في التعديل')) ?>
                            </button>
                            
                            <button type="submit" name="save_and_list" class="btn btn-outline-light w-100">
                                <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                <?= h(__('t_e934ff7404', 'حفظ والعودة للقائمة')) ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- نصائح سريعة -->
                <div class="card shadow-sm mt-4">
                    <div class="card-header bg-dark text-light">
                        <h6 class="card-title mb-0">
                            <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_fb8f3b0519', 'نصائح سريعة')) ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong><?= h(__('t_0334979124', '👤 المعلومات:')) ?></strong>
                            <p class="small text-muted mb-0"><?= h(__('t_c9d2f9c7c4', 'املأ المعلومات الأساسية بدقة لتسهيل التعرف على الكاتب.')) ?></p>
                        </div>
                        <div class="mb-3">
                            <strong><?= h(__('t_84d00bb6b5', '📸 الصورة:')) ?></strong>
                            <p class="small text-muted mb-0"><?= h(__('t_06e92c18ba', 'استخدم صوراً شخصية واضحة وبجودة مناسبة.')) ?></p>
                        </div>
                        <div class="mb-3">
                            <strong><?= h(__('t_14df2374ce', '🔗 الروابط:')) ?></strong>
                            <p class="small text-muted mb-0"><?= h(__('t_fe8953c822', 'أضف روابط التواصل لزيادة التفاعل.')) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const nameInput           = document.querySelector('input[name="name"]');
    const specializationInput = document.querySelector('input[name="specialization"]');
    const bioInput            = document.querySelector('textarea[name="bio"]');
    const avatarInput         = document.getElementById('avatarInput');
    const avatarImage         = document.getElementById('avatarImage');
    const nameCount           = document.getElementById('nameCount');
    const specializationCount = document.getElementById('specializationCount');
    const bioCount            = document.getElementById('bioCount');
    
    // عداد الأحرف للاسم
    function updateNameCount() {
        const length = nameInput.value.length;
        nameCount.textContent = `${length}/255`;
        nameCount.className = `char-count mt-1 ${length > 200 ? 'warning' : ''} ${length > 250 ? 'danger' : ''}`;
    }
    
    // عداد الأحرف للتخصص
    function updateSpecializationCount() {
        const length = specializationInput.value.length;
        specializationCount.textContent = `${length}/255`;
        specializationCount.className = `char-count mt-1 ${length > 200 ? 'warning' : ''} ${length > 250 ? 'danger' : ''}`;
    }
    
    // عداد الأحرف للسيرة الذاتية
    function updateBioCount() {
        const length = bioInput.value.length;
        bioCount.textContent = `${length}/1000`;
        bioCount.className = `char-count mt-1 ${length > 800 ? 'warning' : ''} ${length > 950 ? 'danger' : ''}`;
    }
    
    // معاينة الصورة
    function updateAvatarPreview() {
        const avatarUrl = avatarInput.value.trim();
        if (avatarUrl) {
            avatarImage.src = avatarUrl;
        } else {
            avatarImage.src = 'https://via.placeholder.com/120x120/0f172a/64748b?text=صورة';
        }
    }
    
    nameInput.addEventListener('input', updateNameCount);
    specializationInput.addEventListener('input', updateSpecializationCount);
    bioInput.addEventListener('input', updateBioCount);
    avatarInput.addEventListener('input', updateAvatarPreview);
    
    // تهيئة أولية
    updateNameCount();
    updateSpecializationCount();
    updateBioCount();
    
    // منع الإرسال إذا كان الاسم فارغاً
    document.getElementById('authorForm').addEventListener('submit', function(e) {
        if (!nameInput.value.trim()) {
            e.preventDefault();
            nameInput.focus();
            alert('يرجى إدخال اسم الكاتب');
        }
    });
});
</script>

<?php require_once __DIR__ . '/../layout/app_end.php'; ?>
