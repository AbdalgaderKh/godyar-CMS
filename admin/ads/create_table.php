<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

use Godyar\Auth;

$currentPage = 'ads';
$pageTitle   = __('t_ac6f627f56', 'إنشاء جدول الإعلانات');

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// التحقق من تسجيل الدخول
try {
    if (class_exists(Auth::class) && method_exists(Auth::class, 'isLoggedIn')) {
        if (!Auth::isLoggedIn()) {
            header('Location: ../login.php');
            exit;
        }
    } else {
        if (empty($_SESSION['user']) || (($_SESSION['user']['role'] ?? '') === 'guest')) {
            header('Location: ../login.php');
            exit;
        }
    }
} catch (Throwable $e) {
    @error_log('[Godyar Ads Create Table] Auth error: ' . $e->getMessage());
    header('Location: ../login.php');
    exit;
}

$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    die(__('t_acc3fac25f', '❌ لا يوجد اتصال بقاعدة البيانات.'));
}

$success = false;
$error = null;
$tableExists = false;

// التحقق من وجود الجدول
try {
    $check = $pdo->query("SHOW TABLES LIKE 'ads'");
    $tableExists = $check && $check->fetchColumn();
} catch (Exception $e) {
    $error = __('t_0fa2aa3ee6', 'خطأ في التحقق من الجداول: ') . $e->getMessage();
}

// إنشاء الجدول إذا لم يكن موجوداً
if (!$tableExists && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_table'])) {
    try {
        // بداية transaction
        $pdo->beginTransaction();

        // إنشاء جدول الإعلانات
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS `ads` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT NULL,
            `location` VARCHAR(100) NOT NULL COMMENT 'موضع ظهور الإعلان',
            `image_url` VARCHAR(500) NULL,
            `target_url` VARCHAR(500) NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
            `starts_at` DATETIME NULL,
            `ends_at` DATETIME NULL,
            `max_clicks` INT UNSIGNED NOT NULL DEFAULT 0,
            `max_views` INT UNSIGNED NOT NULL DEFAULT 0,
            `click_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `view_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_location` (`location`),
            INDEX `idx_active` (`is_active`),
            INDEX `idx_featured` (`is_featured`),
            INDEX `idx_dates` (`starts_at`, `ends_at`),
            INDEX `idx_created` (`created_at`),
            INDEX `idx_status` (`is_active`, `starts_at`, `ends_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول إعلانات الموقع';
        SQL;

        $pdo->exec($sql);

        // إضافة بعض البيانات التجريبية
        $sampleData = [
            [
                'title' => __('t_d5adb5cd26', 'ترحيب في الموقع'),
                'description' => __('t_caf9f412bb', 'إعلان ترحيبي يظهر لأول مرة في الموقع'),
                'location' => 'header_top',
                'image_url' => __('t_fb5f99ab91', 'https://via.placeholder.com/728x90/0f172a/0ea5e9?text=مرحبا+بكم+في+موقعنا'),
                'target_url' => 'https://example.com/welcome',
                'is_active' => 1,
                'is_featured' => 1,
                'starts_at' => date('Y-m-d H:i:s'),
                'ends_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                'max_clicks' => 1000,
                'max_views' => 10000
            ],
            [
                'title' => __('t_af62398d81', 'عرض خاص'),
                'description' => __('t_940ff671d0', 'عرض محدود لفترة قصيرة'),
                'location' => 'sidebar_top',
                'image_url' => __('t_ba2aec4415', 'https://via.placeholder.com/300x250/1e293b/f59e0b?text=عرض+خاص+لمدة+محدودة'),
                'target_url' => 'https://example.com/special-offer',
                'is_active' => 1,
                'is_featured' => 0,
                'starts_at' => date('Y-m-d H:i:s'),
                'ends_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
                'max_clicks' => 500,
                'max_views' => 5000
            ],
            [
                'title' => __('t_aec2404d9a', 'إعلان تجريبي'),
                'description' => __('t_9a173dd746', 'إعلان غير نشط للتجربة'),
                'location' => 'footer_bottom',
                'image_url' => __('t_55d64d751c', 'https://via.placeholder.com/468x60/334155/94a3b8?text=إعلان+تجريبي'),
                'target_url' => 'https://example.com/test',
                'is_active' => 0,
                'is_featured' => 0,
                'starts_at' => date('Y-m-d H:i:s', strtotime('-10 days')),
                'ends_at' => date('Y-m-d H:i:s', strtotime('+5 days')),
                'max_clicks' => 100,
                'max_views' => 1000
            ]
        ];

        $insertSql = "
            INSERT INTO `ads` 
            (title, description, location, image_url, target_url, is_active, is_featured, starts_at, ends_at, max_clicks, max_views, created_at, updated_at)
            VALUES 
            (:title, :description, :location, :image_url, :target_url, :is_active, :is_featured, :starts_at, :ends_at, :max_clicks, :max_views, NOW(), NOW())
        ";

        $stmt = $pdo->prepare($insertSql);
        foreach ($sampleData as $data) {
            $stmt->execute($data);
        }

        // تأكيد العملية
        $pdo->commit();
        $success = true;
        $tableExists = true;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $error = __('t_065b0c24e1', 'خطأ في إنشاء الجدول: ') . $e->getMessage();
        @error_log('[Godyar Ads] Table creation error: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>

<style>
.creation-card {
    background: rgba(15, 23, 42, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 1.5rem;
    border: 1px solid rgba(148, 163, 184, 0.2);
}

.feature-list {
    list-style: none;
    padding: 0;
}

.feature-list li {
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(148, 163, 184, 0.1);
}

.feature-list li:last-child {
    border-bottom: none;
}

.feature-list .badge {
    font-size: 0.7rem;
    margin-right: 0.5rem;
}

.table-structure {
    background: rgba(30, 41, 59, 0.6);
    border-radius: 0.75rem;
    padding: 1rem;
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
}

.code-line {
    padding: 0.25rem 0;
    border-left: 3px solid transparent;
    transition: all 0.3s ease;
}

.code-line:hover {
    border-left-color: #0ea5e9;
    background: rgba(14, 165, 233, 0.1);
}
</style>

<div class="admin-content container-fluid py-4">
    <!-- رأس الصفحة -->
    <div class="gdy-page-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div>
            <h1 class="h4 text-white mb-1"><?= h(__('t_ac6f627f56', 'إنشاء جدول الإعلانات')) ?></h1>
            <p class="text-muted mb-0">
                <?= h(__('t_9fe8203d6d', 'إعداد قاعدة بيانات نظام الإعلانات')) ?>
            </p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="index.php" class="btn btn-outline-light">
                <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_4143dddc4c', 'العودة للإعلانات')) ?>
            </a>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                    <strong><?= h(__('t_38f1d57b76', 'تم بنجاح!')) ?></strong> <?= h(__('t_194518b3c3', 'تم إنشاء جدول الإعلانات وإضافة بيانات تجريبية.')) ?>
                    <div class="mt-2">
                        <a href="index.php" class="btn btn-sm btn-success me-2"><?= h(__('t_6304a4d00d', 'عرض الإعلانات')) ?></a>
                        <a href="create.php" class="btn btn-sm btn-outline-success"><?= h(__('t_289196f7a2', 'إضافة إعلان جديد')) ?></a>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                    <strong><?= h(__('t_5f1154f94b', 'خطأ:')) ?></strong> <?= h($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($tableExists && !$success): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                    <strong><?= h(__('t_ad0dcb7f3a', 'معلومة:')) ?></strong> <?= h(__('t_09d28e81fb', 'جدول الإعلانات موجود مسبقاً في قاعدة البيانات.')) ?>
                    <div class="mt-2">
                        <a href="index.php" class="btn btn-sm btn-info"><?= h(__('t_6304a4d00d', 'عرض الإعلانات')) ?></a>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="creation-card card shadow-sm">
                <div class="card-body p-4">
                    <?php if (!$tableExists): ?>
                        <!-- حالة عدم وجود الجدول -->
                        <div class="text-center mb-4">
                            <div class="mb-4">
                                <svg class="gdy-icon text-warning mb-3" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                <h3 class="text-white"><?= h(__('t_82a2e9c562', 'جدول الإعلانات غير موجود')) ?></h3>
                                <p class="text-muted"><?= h(__('t_464951158a', 'يجب إنشاء جدول الإعلانات في قاعدة البيانات لبدء استخدام النظام.')) ?></p>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="card h-100 bg-dark border-secondary">
                                    <div class="card-header">
                                        <h6 class="card-title mb-0 text-white">
                                            <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_2eed2c3977', 'المميزات المتوفرة')) ?>
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <ul class="feature-list text-light">
                                            <li>
                                                <span class="badge bg-primary">✓</span>
                                                <?= h(__('t_e5e01f78c0', 'إدارة إعلانات متعددة')) ?>
                                            </li>
                                            <li>
                                                <span class="badge bg-primary">✓</span>
                                                <?= h(__('t_bf0c7c5555', 'مواضع ظهور مختلفة')) ?>
                                            </li>
                                            <li>
                                                <span class="badge bg-primary">✓</span>
                                                <?= h(__('t_2cd6b0ec1c', 'إحصائيات النقرات والمشاهدات')) ?>
                                            </li>
                                            <li>
                                                <span class="badge bg-primary">✓</span>
                                                <?= h(__('t_a00bce3bd3', 'فترات زمنية محددة')) ?>
                                            </li>
                                            <li>
                                                <span class="badge bg-primary">✓</span>
                                                <?= h(__('t_a54bcc26df', 'إعلانات مميزة')) ?>
                                            </li>
                                            <li>
                                                <span class="badge bg-primary">✓</span>
                                                <?= h(__('t_05400057b3', 'حدود قصوى للنقرات')) ?>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card h-100 bg-dark border-secondary">
                                    <div class="card-header">
                                        <h6 class="card-title mb-0 text-white">
                                            <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_aacca2e364', 'تفاصيل الجدول')) ?>
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-structure small">
                                            <div class="code-line"><span class="text-info">Table:</span> <span class="text-warning">ads</span></div>
                                            <div class="code-line"><span class="text-success">✓</span> id (Primary Key)</div>
                                            <div class="code-line"><span class="text-success">✓</span> title & description</div>
                                            <div class="code-line"><span class="text-success">✓</span> location & image_url</div>
                                            <div class="code-line"><span class="text-success">✓</span> target_url & is_active</div>
                                            <div class="code-line"><span class="text-success">✓</span> starts_at & ends_at</div>
                                            <div class="code-line"><span class="text-success">✓</span> click_count & view_count</div>
                                            <div class="code-line"><span class="text-success">✓</span> created_at & updated_at</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-4 pt-4 border-top border-secondary">
                            <form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                                <p class="text-muted mb-3">
                                    <?= h(__('t_b0df462684', 'سيتم إنشاء الجدول مع بيانات تجريبية للبدء فوراً.')) ?>
                                </p>
                                <button type="submit" name="create_table" class="btn btn-primary btn-lg">
                                    <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                    <?= h(__('t_ac6f627f56', 'إنشاء جدول الإعلانات')) ?>
                                </button>
                                <div class="form-text mt-2">
                                    <small><?= h(__('t_afb55b06db', 'هذه العملية آمنة ولا تؤثر على الجداول الأخرى.')) ?></small>
                                </div>
                            </form>
                        </div>

                    <?php else: ?>
                        <!-- حالة وجود الجدول -->
                        <div class="text-center py-4">
                            <div class="mb-4">
                                <svg class="gdy-icon text-success mb-3" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                <h3 class="text-white"><?= h(__('t_47b9a1019c', 'النظام جاهز للاستخدام')) ?></h3>
                                <p class="text-muted"><?= h(__('t_9ea24d0763', 'جدول الإعلانات موجود ومهيأ في قاعدة البيانات.')) ?></p>
                            </div>

                            <div class="row g-3 justify-content-center">
                                <div class="col-md-4">
                                    <div class="card bg-dark border-success">
                                        <div class="card-body text-center">
                                            <svg class="gdy-icon text-info mb-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                            <h6 class="text-white"><?= h(__('t_6304a4d00d', 'عرض الإعلانات')) ?></h6>
                                            <a href="index.php" class="btn btn-sm btn-outline-info w-100"><?= h(__('t_bf981a1299', 'استعراض')) ?></a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-dark border-primary">
                                        <div class="card-body text-center">
                                            <svg class="gdy-icon text-primary mb-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                            <h6 class="text-white"><?= h(__('t_2d64a37cdd', 'إعلان جديد')) ?></h6>
                                            <a href="create.php" class="btn btn-sm btn-outline-primary w-100"><?= h(__('t_b9508aa2a9', 'إضافة')) ?></a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-dark border-warning">
                                        <div class="card-body text-center">
                                            <svg class="gdy-icon text-warning mb-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                            <h6 class="text-white"><?= h(__('t_84b1e0c6ed', 'الإحصائيات')) ?></h6>
                                            <a href="index.php" class="btn btn-sm btn-outline-warning w-100"><?= h(__('t_6e63a5f0af', 'عرض')) ?></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- معلومات إضافية -->
            <div class="card shadow-sm mt-4 bg-dark border-secondary">
                <div class="card-header">
                    <h6 class="card-title mb-0 text-white">
                        <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_88d313a15e', 'معلومات تقنية')) ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-3 text-center small">
                        <div class="col-md-3">
                            <div class="text-muted"><?= h(__('t_fa8b328b33', 'نوع الجدول')) ?></div>
                            <div class="text-white">InnoDB</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted"><?= h(__('t_b6a4dbf865', 'ترميز الأحرف')) ?></div>
                            <div class="text-white">utf8mb4</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted"><?= h(__('t_2e8aae4084', 'عدد الحقول')) ?></div>
                            <div class="text-white"><?= h(__('t_51c3a573a2', '15 حقل')) ?></div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted"><?= h(__('t_c165189dcb', 'الفهارس')) ?></div>
                            <div class="text-white"><?= h(__('t_3f32d97d93', '6 فهارس')) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // تأكيد إنشاء الجدول
    const createForm = document.querySelector('form');
    if (createForm) {
        createForm.addEventListener('submit', function(e) {
            if (!confirm('هل أنت متأكد من إنشاء جدول الإعلانات؟ سيتم إضافة بيانات تجريبية تلقائياً.')) {
                e.preventDefault();
            }
        });
    }
    
    console.log('صفحة إنشاء جدول الإعلانات جاهزة');
});
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>