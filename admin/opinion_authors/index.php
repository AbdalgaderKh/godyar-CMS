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
 

$pdo = gdy_pdo_safe();
$authors = [];

// التحقق من وجود جدول الكُتاب
$tableExists = false;
if ($pdo instanceof PDO) {
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'opinion_authors'");
        $tableExists = $check && $check->fetchColumn();
        
        if ($tableExists) {
            $stmt = $pdo->query("
                SELECT * FROM opinion_authors 
                ORDER BY display_order ASC, created_at DESC
            ");
            $authors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log(__('t_f3c0be0291', 'خطأ في جلب بيانات الكُتاب: ') . $e->getMessage());
    }
}

$currentPage = 'opinion_authors';
$pageTitle   = __('t_4a173870d1', 'كتّاب الرأي');
$pageSubtitle= __('t_63b4bd9eed', 'إدارة كتّاب الرأي');
$adminBase = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/admin'), '/');
$breadcrumbs = [__('t_3aa8578699', 'الرئيسية') => $adminBase.'/index.php', __('t_4a173870d1', 'كتّاب الرأي') => null];
$pageActionsHtml = __('t_12be96e697', '<a href="create.php" class="btn btn-gdy-primary"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> إضافة كاتب</a>');
require_once __DIR__ . '/../layout/app_start.php';
$csrf = generate_csrf_token();
?>

<style>
/*
  ✅ إصلاح التشوّه/الخروج خارج الشاشة:
  كان هناك override على .admin-content (margin:0 auto) يلغي margin-right
  الخاص بالسايدبار، فينزل المحتوى تحت القائمة الجانبية.
  الحل: لا نلمس .admin-content، ونضبط العرض عبر .gdy-admin-shell فقط.
*/
html, body{ overflow-x:hidden; }
.gdy-admin-shell{ max-width: 1200px; margin: 0 auto; }
.gdy-page-header{ margin-bottom: .75rem; }


.gdy-authors-grid {
    background: rgba(15, 23, 42, 0.8);
    backdrop-filter: blur(10px);
    border-radius: 1.5rem;
    border: 1px solid rgba(148, 163, 184, 0.2);
    padding: 2rem;
}

.gdy-author-card {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(30, 41, 59, 0.9));
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 1rem;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    height: 100%;
}

.gdy-author-card:hover {
    transform: translateY(-8px);
    border-color: #0ea5e9;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
}

.gdy-author-avatar {
    position: relative;
    overflow: hidden;
    background: #0f172a;
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}

.gdy-author-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.gdy-author-card:hover .gdy-author-avatar img {
    transform: scale(1.05);
}

.gdy-author-overlay {
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

.gdy-author-card:hover .gdy-author-overlay {
    opacity: 1;
}

.gdy-author-actions {
    display: flex;
    gap: 0.5rem;
    width: 100%;
    justify-content: center;
}

.gdy-author-btn {
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

.gdy-author-btn:hover {
    background: #0ea5e9;
    color: white;
    transform: scale(1.1);
}

.gdy-author-info {
    padding: 1.5rem;
    text-align: center;
}

.gdy-author-name {
    font-weight: 600;
    color: #e5e7eb;
    margin-bottom: 0.5rem;
    font-size: 1.1rem;
    line-height: 1.4;
}

.gdy-author-specialization {
    color: #0ea5e9;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.gdy-author-email {
    color: #94a3b8;
    font-size: 0.85rem;
    margin-bottom: 1rem;
}

.gdy-author-bio {
    color: #94a3b8;
    font-size: 0.85rem;
    line-height: 1.5;
    margin-bottom: 1rem;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.gdy-author-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.8rem;
    color: #64748b;
}

.gdy-author-status {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-weight: 500;
    font-size: 0.75rem;
}

.gdy-author-status.inactive {
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

<div class="admin-content  py-4">
    <!-- رأس الصفحة -->
    <div class="admin-content gdy-page-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div>
            <h1 class="h4 mb-1 text-white"><?= h(__('t_0e0c340c99', 'إدارة كُتاب الرأي')) ?></h1>
            <p class="mb-0" style="color:#e5e7eb;">
                <?= h(__('t_2e295a8ad7', 'إدارة كُتاب الأعمدة ومقالات الرأي في الموقع')) ?>
            </p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <?php if (!$tableExists): ?>
                <a href="create_table.php" class="btn btn-warning">
                    <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_b65c728df7', 'إنشاء الجدول')) ?>
                </a>
            <?php endif; ?>
            <a href="create.php" class="btn btn-primary">
                <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_7332c59999', 'إضافة كاتب جديد')) ?>
            </a>
        </div>
    </div>

    <?php if (!$tableExists): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <strong><?= h(__('t_b83c3996d9', 'تنبيه:')) ?></strong> <?= h(__('t_95f05efc73', 'جدول كُتاب الرأي غير موجود.')) ?> 
            <a href="create_table.php" class="alert-link"><?= h(__('t_98b74d89fa', 'انقر هنا لإنشاء الجدول')) ?></a>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- الإحصائيات -->
    <?php if ($tableExists && !empty($authors)): ?>
        <div class="gdy-stats">
            <div class="gdy-stat">
                <span class="gdy-stat-value"><?= count($authors) ?></span>
                <span class="gdy-stat-label"><?= h(__('t_07206b00d1', 'إجمالي الكُتاب')) ?></span>
            </div>
            <?php
            $activeAuthors = array_filter($authors, function($author) {
                return $author['is_active'] == 1;
            });
            $inactiveAuthors = array_filter($authors, function($author) {
                return $author['is_active'] == 0;
            });
            $totalArticles = array_sum(array_column($authors, 'articles_count'));
            ?>
            <div class="gdy-stat">
                <span class="gdy-stat-value"><?= count($activeAuthors) ?></span>
                <span class="gdy-stat-label"><?= h(__('t_0afacdf2c8', 'كتّاب نشطين')) ?></span>
            </div>
            <div class="gdy-stat">
                <span class="gdy-stat-value"><?= count($inactiveAuthors) ?></span>
                <span class="gdy-stat-label"><?= h(__('t_16579fc60a', 'كتّاب غير نشطين')) ?></span>
            </div>
            <div class="gdy-stat">
                <span class="gdy-stat-value"><?= $totalArticles ?></span>
                <span class="gdy-stat-label"><?= h(__('t_828a77c5d7', 'مقالات منشورة')) ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- شبكة الكُتاب -->
    <div class="gdy-authors-grid">
        <?php if (empty($authors)): ?>
            <div class="gdy-empty-state">
                <div class="gdy-empty-icon">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                </div>
                <h4 class="text-muted mb-3"><?= h(__('t_cab4c070f1', 'لا توجد كُتاب مسجلين')) ?></h4>
                <p class="text-muted mb-4"><?= h(__('t_77ef440b11', 'ابدأ بإضافة أول كاتب إلى النظام')) ?></p>
                <a href="create.php" class="btn btn-primary btn-lg">
                    <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_d88c3f927a', 'إضافة أول كاتب')) ?>
                </a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($authors as $index => $author): ?>
                    <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                        <div class="gdy-author-card">
                            <!-- صورة الكاتب -->
                            <div class="gdy-author-avatar">
                                <?php if (!empty($author['avatar'])): ?>
                                    <img src="<?= htmlspecialchars($author['avatar']) ?>" 
                                         alt="<?= htmlspecialchars($author['name']) ?>"
                                         data-img-error="hide-show-next-flex">
                                <?php endif; ?>
                                <div class="gdy-author-overlay">
                                    <div class="gdy-author-actions">
                                        <a href="edit.php?id=<?= $author['id'] ?>" 
                                           class="gdy-author-btn"
                                           title="<?= h(__('t_759fdc242e', 'تعديل')) ?>">
                                            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                        </a>
                                        <!-- ✅ زر كتابة مقال رأي لهذا الكاتب -->
                                        <a href="../news/create.php?opinion_author_id=<?= (int)$author['id'] ?>" 
                                           class="gdy-author-btn" 
                                           title="<?= h(__('t_a327aa077e', 'إضافة مقال رأي جديد لهذا الكاتب')) ?>">
                                            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                        </a>
                                        <button class="gdy-author-btn toggle-author" 
                                                data-id="<?= $author['id'] ?>"
                                                data-status="<?= $author['is_active'] ?>"
                                                title="<?= $author['is_active'] ? __('t_43ead21245', 'تعطيل') : __('t_8403358516', 'تفعيل') ?>">
                                            <svg class="gdy-icon $author['is_active'] ? 'eye' : 'eye-slash' ?>" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                        </button>
                                        <button class="gdy-author-btn delete-author" 
                                                data-id="<?= $author['id'] ?>"

                                                data-name="<?= htmlspecialchars($author['name']) ?>"
                                                title="<?= h(__('t_3b9854e1bb', 'حذف')) ?>">
                                            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                        </button>
                                    </div>
                                </div>
                                <?php if (empty($author['avatar'])): ?>
                                    <div class="text-muted">
                                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- معلومات الكاتب -->
                            <div class="gdy-author-info">
                                <div class="gdy-author-name">
                                    <?= htmlspecialchars($author['name']) ?>
                                </div>
                                
                                <?php if (!empty($author['specialization'])): ?>
                                    <div class="gdy-author-specialization">
                                        <?= htmlspecialchars($author['specialization']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($author['email'])): ?>
                                    <div class="gdy-author-email">
                                        <?= htmlspecialchars($author['email']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($author['bio'])): ?>
                                    <div class="gdy-author-bio">
                                        <?= htmlspecialchars($author['bio']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="gdy-author-meta">
                                    <span class="gdy-author-status <?= $author['is_active'] ? '' : 'inactive' ?>">
                                        <?= $author['is_active'] ? __('t_bad8af986e', '🟢 نشط') : __('t_9267eae6e2', '⚫ غير نشط') ?>
                                    </span>
                                    <span><?= $author['articles_count'] ?> مقال</span>
                                </div>
                                
                                <!-- وسائل التواصل الاجتماعي -->
                                <?php if (!empty($author['social_twitter']) || !empty($author['social_linkedin']) || !empty($author['social_website'])): ?>
                                    <div class="mt-3 d-flex justify-content-center gap-2">
                                        <?php if (!empty($author['social_twitter'])): ?>
                                            <a href="<?= htmlspecialchars($author['social_twitter']) ?>" 
                                               target="_blank" 
                                               class="text-info"
                                               title="<?= h(__('t_989510d6cd', 'تويتر')) ?>">
                                                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#x"></use></svg>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($author['social_linkedin'])): ?>
                                            <a href="<?= htmlspecialchars($author['social_linkin']) ?>" 
                                               target="_blank" 
                                               class="text-primary"
                                               title="<?= h(__('t_d0a32019c9', 'لينكدإن')) ?>">
                                                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($author['social_website'])): ?>
                                            <a href="<?= htmlspecialchars($author['social_website']) ?>" 
                                               target="_blank" 
                                               class="text-success"
                                               title="<?= h(__('t_d5b4c8ec57', 'موقع شخصي')) ?>">
                                                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#globe"></use></svg>
                                            </a>
                                        <?php endif; ?>
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
    // تبديل حالة الكاتب
    document.querySelectorAll('.toggle-author').forEach(btn => {
        btn.addEventListener('click', function() {
            const authorId = this.getAttribute('data-id');
            const currentStatus = this.getAttribute('data-status');
            const newStatus = currentStatus === '1' ? '0' : '1';
            
            fetch('toggle_author.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${authorId}&status=${newStatus}`
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
    
    // حذف الكاتب
    document.querySelectorAll('.delete-author').forEach(btn => {
        btn.addEventListener('click', function() {
            const authorId = this.getAttribute('data-id');
            const authorName = this.getAttribute('data-name');
            
            if (confirm(`هل أنت متأكد من حذف الكاتب "${authorName}"؟`)) {
                fetch('delete_author.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${authorId}`
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

<?php require_once __DIR__ . '/../layout/app_end.php'; ?>
