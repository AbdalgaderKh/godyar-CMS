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

$currentPage = 'sliders';
$pageTitle   = __('t_eafc27904f', 'إدارة السلايدر');

$pdo = gdy_pdo_safe();
$sliders = [];

// التحقق من وجود جدول السلايدر
$tableExists = false;
if ($pdo instanceof PDO) {
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'sliders'");
        $tableExists = $check && $check->fetchColumn();
        
        if ($tableExists) {
            $stmt = $pdo->query("
                SELECT * FROM sliders 
                ORDER BY display_order ASC, created_at DESC
            ");
            $sliders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log(__('t_5e39a0ab76', 'خطأ في جلب بيانات السلايدر: ') . $e->getMessage());
    }
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>

<style>
.gdy-sliders-grid {
    background: rgba(15, 23, 42, 0.8);
    backdrop-filter: blur(10px);
    border-radius: 1.5rem;
    border: 1px solid rgba(148, 163, 184, 0.2);
    padding: 2rem;
}

.gdy-slider-card {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(30, 41, 59, 0.9));
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 1rem;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    height: 100%;
}

.gdy-slider-card:hover {
    transform: translateY(-8px);
    border-color: #0ea5e9;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
}

.gdy-slider-image {
    position: relative;
    overflow: hidden;
    background: #0f172a;
    aspect-ratio: 16/9;
    display: flex;
    align-items: center;
    justify-content: center;
}

.gdy-slider-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.gdy-slider-card:hover .gdy-slider-image img {
    transform: scale(1.05);
}

.gdy-slider-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(180deg, transparent 40%, rgba(15, 23, 42, 0.95) 100%);
    opacity: 0;
    transition: opacity 0.3s ease;
    display: flex;
    align-items: flex-end;
    padding: 1rem;
}

.gdy-slider-card:hover .gdy-slider-overlay {
    opacity: 1;
}

.gdy-slider-actions {
    display: flex;
    gap: 0.5rem;
    width: 100%;
    justify-content: center;
}

.gdy-slider-btn {
    background: rgba(255, 255, 255, 0.9);
    border: none;
    border-radius: 0.5rem;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1e293b;
    text-decoration: none;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.gdy-slider-btn:hover {
    background: #0ea5e9;
    color: white;
    transform: scale(1.1);
}

.gdy-slider-info {
    padding: 1.5rem;
}

.gdy-slider-title {
    font-weight: 600;
    color: #e5e7eb;
    margin-bottom: 0.5rem;
    font-size: 1.1rem;
    line-height: 1.4;
}

.gdy-slider-desc {
    color: #94a3b8;
    font-size: 0.9rem;
    line-height: 1.5;
    margin-bottom: 1rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.gdy-slider-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.8rem;
    color: #64748b;
}

.gdy-slider-status {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-weight: 500;
    font-size: 0.75rem;
}

.gdy-slider-status.inactive {
    background: rgba(100, 116, 139, 0.2);
    color: #64748b;
}

.gdy-empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #94a3b8;
}

.gdy-empty-icon {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    opacity: 0.5;
}

.gdy-stats {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 2rem;
}

.gdy-stat {
    background: rgba(30, 41, 59, 0.6);
    padding: 1rem 1.5rem;
    border-radius: 1rem;
    border: 1px solid rgba(148, 163, 184, 0.2);
    text-align: center;
    flex: 1;
    min-width: 150px;
}

.gdy-stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #0ea5e9;
    display: block;
    line-height: 1;
}

.gdy-stat-label {
    font-size: 0.85rem;
    color: #94a3b8;
    display: block;
    margin-top: 0.5rem;
}
</style>

<div class="admin-content container-fluid py-4">
    <!-- رأس الصفحة -->
    <div class="admin-content gdy-page-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div>
            <h1 class="h4 mb-1 text-white"><?= h(__('t_eafc27904f', 'إدارة السلايدر')) ?></h1>
            <p class="mb-0" style="color:#e5e7eb;">
                <?= h(__('t_278b35125c', 'إدارة شرائح العرض الرئيسية في الموقع')) ?>
            </p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <?php if (!$tableExists): ?>
                <a href="create_table.php" class="btn btn-warning">
                    <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_b65c728df7', 'إنشاء الجدول')) ?>
                </a>
            <?php endif; ?>
            <a href="create.php" class="btn btn-primary">
                <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_5d1adeeb8d', 'إضافة شريحة جديدة')) ?>
            </a>
        </div>
    </div>

    <?php if (!$tableExists): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <strong><?= h(__('t_b83c3996d9', 'تنبيه:')) ?></strong> <?= h(__('t_3b529cf8d7', 'جدول السلايدر غير موجود.')) ?> 
            <a href="create_table.php" class="alert-link"><?= h(__('t_98b74d89fa', 'انقر هنا لإنشاء الجدول')) ?></a>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- الإحصائيات -->
    <?php if ($tableExists && !empty($sliders)): ?>
        <div class="gdy-stats">
            <div class="gdy-stat">
                <span class="gdy-stat-value"><?= count($sliders) ?></span>
                <span class="gdy-stat-label"><?= h(__('t_2c31e07b5a', 'إجمالي الشرائح')) ?></span>
            </div>
            <?php
            $activeSliders = array_filter($sliders, function($slider) {
                return $slider['is_active'] == 1;
            });
            $inactiveSliders = array_filter($sliders, function($slider) {
                return $slider['is_active'] == 0;
            });
            ?>
            <div class="gdy-stat">
                <span class="gdy-stat-value"><?= count($activeSliders) ?></span>
                <span class="gdy-stat-label"><?= h(__('t_2df70bad69', 'شرائح نشطة')) ?></span>
            </div>
            <div class="gdy-stat">
                <span class="gdy-stat-value"><?= count($inactiveSliders) ?></span>
                <span class="gdy-stat-label"><?= h(__('t_d8a00827ab', 'شرائح غير نشطة')) ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- شبكة السلايدر -->
    <div class="gdy-sliders-grid">
        <?php if (empty($sliders)): ?>
            <div class="gdy-empty-state">
                <div class="gdy-empty-icon">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                </div>
                <h4 class="text-muted mb-3"><?= h(__('t_95b00f88dc', 'لا توجد شرائح عرض')) ?></h4>
                <p class="text-muted mb-4"><?= h(__('t_1d7ff935eb', 'ابدأ بإضافة أول شريحة عرض إلى السلايدر')) ?></p>
                <a href="create.php" class="btn btn-primary btn-lg">
                    <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_dc654e4e56', 'إضافة أول شريحة')) ?>
                </a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($sliders as $index => $slider): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="gdy-slider-card">
                            <!-- صورة السلايدر -->
                            <div class="gdy-slider-image">
                                <img src="<?= htmlspecialchars($slider['image_path']) ?>" 
                                     alt="<?= htmlspecialchars($slider['title']) ?>"
                                     data-img-error="hide-show-next-flex">
                                <div class="gdy-slider-overlay">
                                    <div class="gdy-slider-actions">
                                        <a href="edit.php?id=<?= $slider['id'] ?>" 
                                           class="gdy-slider-btn"
                                           title="<?= h(__('t_759fdc242e', 'تعديل')) ?>">
                                            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                        </a>
                                        <button class="gdy-slider-btn toggle-slider" 
                                                data-id="<?= $slider['id'] ?>"
                                                data-status="<?= $slider['is_active'] ?>"
                                                title="<?= $slider['is_active'] ? __('t_43ead21245', 'تعطيل') : __('t_8403358516', 'تفعيل') ?>">
                                            <svg class="gdy-icon $slider['is_active'] ? 'eye' : 'eye-slash' ?>" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                        </button>
                                        <button class="gdy-slider-btn delete-slider" 
                                                data-id="<?= $slider['id'] ?>"
                                                data-title="<?= htmlspecialchars($slider['title']) ?>"
                                                title="<?= h(__('t_3b9854e1bb', 'حذف')) ?>">
                                            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- معلومات السلايدر -->
                            <div class="gdy-slider-info">
                                <div class="gdy-slider-title">
                                    <?= htmlspecialchars($slider['title']) ?>
                                </div>
                                
                                <?php if (!empty($slider['description'])): ?>
                                    <div class="gdy-slider-desc">
                                        <?= htmlspecialchars($slider['description']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="gdy-slider-meta">
                                    <span class="gdy-slider-status <?= $slider['is_active'] ? '' : 'inactive' ?>">
                                        <?= $slider['is_active'] ? __('t_bad8af986e', '🟢 نشط') : __('t_9267eae6e2', '⚫ غير نشط') ?>
                                    </span>
                                    <span>ترتيب: <?= $slider['display_order'] ?></span>
                                </div>
                                
                                <?php if (!empty($slider['button_text']) && !empty($slider['button_url'])): ?>
                                    <div class="mt-3">
                                        <a href="<?= htmlspecialchars($slider['button_url']) ?>" 
                                           target="_blank" 
                                           class="btn btn-sm btn-outline-primary w-100">
                                            <?= htmlspecialchars($slider['button_text']) ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // تبديل حالة السلايدر
    document.querySelectorAll('.toggle-slider').forEach(btn => {
        btn.addEventListener('click', function() {
            const sliderId = this.getAttribute('data-id');
            const currentStatus = this.getAttribute('data-status');
            const newStatus = currentStatus === '1' ? '0' : '1';
            
            fetch('toggle_slider.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${sliderId}&status=${newStatus}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('حدث خطأ أثناء تحديث الحالة');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('حدث خطأ في الاتصال');
            });
        });
    });
    
    // حذف السلايدر
    document.querySelectorAll('.delete-slider').forEach(btn => {
        btn.addEventListener('click', function() {
            const sliderId = this.getAttribute('data-id');
            const sliderTitle = this.getAttribute('data-title');
            
            if (confirm(`هل أنت متأكد من حذف الشريحة "${sliderTitle}"؟`)) {
                fetch('delete_slider.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${sliderId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('حدث خطأ أثناء الحذف');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('حدث خطأ في الاتصال');
                });
            }
        });
    });
});
</script>

<?php
require_once __DIR__ . '/../layout/footer.php';
?>