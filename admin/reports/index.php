<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
// admin/reports/index.php

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';

use Godyar\Auth;

$currentPage = 'reports';
$pageTitle   = __('t_95bc86fefd', 'التقارير والإحصائيات');

if (!Auth::isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$pdo = gdy_pdo_safe();

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// إحصائيات بسيطة (يمكن تطويرها لاحقاً)
$stats = [
    'users'       => 124,
    'news'        => 45,
    'pages'       => 12,
    'media'       => 287,
    'ads_active'  => 8,
    'contacts_new'=> 5,
    'comments'    => 156,
    'categories'  => 23,
    'team'        => 9,
    'slider'      => 6,
    'opinion_authors' => 15,
    'reports'     => 12,
];

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>

<div class="admin-content gdy-page">
  <div class="container-fluid py-4">

    <!-- رأس الصفحة -->
    <div class="admin-content gdy-page-header mb-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <h1 class="h4 mb-1 text-white fw-bold"><?= h(__('t_95bc86fefd', 'التقارير والإحصائيات')) ?></h1>
          <p class="mb-0" style="color:#e5e7eb;">
            <?= h(__('t_8f22802321', 'نظرة عامة على أداء الموقع والمحتوى والتفاعل')) ?>
          </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <button class="btn btn-outline-light btn-sm" id="refreshStats">
            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_061401dc3f', 'تحديث')) ?>
          </button>
          <button class="btn btn-primary btn-sm" id="exportReport">
            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_2204f96079', 'تصدير تقرير')) ?>
          </button>
        </div>
      </div>
    </div>

    <!-- شريط الفلاتر أعلى التقارير -->
    <div class="reports-filters mb-4">
      <div class="row g-2 align-items-center">
        <div class="col-md-4">
          <label for="reportRange" class="form-label form-label-sm mb-1">
            <?= h(__('t_5614285d89', 'نطاق التقارير')) ?>
          </label>
          <select id="reportRange" class="form-select form-select-sm">
            <option value="today"><?= h(__('t_57702288e2', 'اليوم')) ?></option>
            <option value="week"><?= h(__('t_6218ca9330', 'آخر 7 أيام')) ?></option>
            <option value="month" selected><?= h(__('t_625082ab10', 'آخر 30 يوم')) ?></option>
            <option value="custom"><?= h(__('t_7518b95c73', 'نطاق مخصص')) ?></option>
          </select>
        </div>
        <div class="col-md-4">
          <div class="form-check mt-3 mt-md-4">
            <input class="form-check-input" type="checkbox" value="1" id="autoRefresh">
            <label class="form-check-label small" for="autoRefresh">
              <?= h(__('t_adf5a7ad8b', 'تفعيل التحديث التلقائي كل 5 دقائق')) ?>
            </label>
          </div>
        </div>
        <div class="col-md-4 text-md-end mt-2 mt-md-4">
          <span class="small text-muted">
            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <?= h(__('t_46d91e6900', 'آخر تحديث تقريبي: الآن')) ?>
          </span>
        </div>
      </div>
    </div>

    <!-- الصف الأول: المستخدمين، الأخبار، التقارير -->
    <div class="row g-4 mb-4">
      <!-- بطاقة المستخدمين -->
      <div class="col-md-4">
        <div class="report-card user-card">
          <div class="card-header">
            <div class="card-title-section">
              <h3 class="card-title"><?= h(__('t_c51ea4d1fc', 'المستخدمين')) ?></h3>
              <div class="card-badge"><?= (int)$stats['users'] ?></div>
            </div>
            <div class="card-icon">
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </div>
          </div>
          <div class="card-body">
            <p class="card-description"><?= h(__('t_4559972ed6', 'إجمالي عدد المستخدمين في النظام')) ?></p>
          </div>
          <div class="card-footer">
            <a href="../users/" class="card-link">
              <span><?= h(__('t_796c8c5b15', 'عرض المستخدمين')) ?></span>
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </a>
          </div>
        </div>
      </div>

      <!-- بطاقة الأخبار -->
      <div class="col-md-4">
        <div class="report-card news-card">
          <div class="card-header">
            <div class="card-title-section">
              <h3 class="card-title"><?= h(__('t_4f9d357332', 'مقالات الأخبار')) ?></h3>
              <div class="card-badge"><?= (int)$stats['news'] ?></div>
            </div>
            <div class="card-icon">
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg>
            </div>
          </div>
          <div class="card-body">
            <p class="card-description"><?= h(__('t_bbf26228e9', 'إجمالي المقالات المنشورة')) ?></p>
          </div>
          <div class="card-footer">
            <a href="../news/" class="card-link">
              <span><?= h(__('t_e06a9f8f17', 'إدارة الأخبار')) ?></span>
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </a>
          </div>
        </div>
      </div>

      <!-- بطاقة التقارير -->
      <div class="col-md-4">
        <div class="report-card reports-card">
          <div class="card-header">
            <div class="card-title-section">
              <h3 class="card-title"><?= h(__('t_4d4e102c5e', 'التقارير')) ?></h3>
              <div class="card-badge"><?= (int)$stats['reports'] ?></div>
            </div>
            <div class="card-icon">
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </div>
          </div>
          <div class="card-body">
            <p class="card-description"><?= h(__('t_16f1778bee', 'مؤشرات الأداء والإحصائيات التفصيلية')) ?></p>
          </div>
          <div class="card-footer">
            <a href="../reports/" class="card-link">
              <span><?= h(__('t_f741547777', 'عرض التقارير')) ?></span>
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- الصف الثاني: إدارة المحتوى، الأقسام، الصفحات -->
    <div class="row g-4 mb-4">
      <!-- بطاقة إدارة المحتوى -->
      <div class="col-md-4">
        <div class="report-card content-card">
          <div class="card-header">
            <div class="card-title-section">
              <h3 class="card-title"><?= h(__('t_c6dac40d6a', 'إدارة المحتوى')) ?></h3>
              <div class="card-badge"><?= (int)$stats['news'] + $stats['pages'] ?></div>
            </div>
            <div class="card-icon">
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg>
            </div>
          </div>
          <div class="card-body">
            <p class="card-description"><?= h(__('t_67286c3c09', 'الأخبار والمقالات والتصنيفات والوسوم')) ?></p>
          </div>
          <div class="card-footer">
            <a href="../news/" class="card-link">
              <span><?= h(__('t_c6dac40d6a', 'إدارة المحتوى')) ?></span>
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </a>
          </div>
        </div>
      </div>

      <!-- بطاقة الأقسام -->
      <div class="col-md-4">
        <div class="report-card categories-card">
          <div class="card-header">
            <div class="card-title-section">
              <h3 class="card-title"><?= h(__('t_c6386f9c0e', 'الأقسام')) ?></h3>
              <div class="card-badge"><?= (int)$stats['categories'] ?></div>
            </div>
            <div class="card-icon">
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </div>
          </div>
          <div class="card-body">
            <p class="card-description"><?= h(__('t_a369c932e0', 'إدارة الأقسام والتصنيفات الرئيسية')) ?></p>
          </div>
          <div class="card-footer">
            <a href="../categories/" class="card-link">
              <span><?= h(__('t_6a61e53eba', 'عرض الأقسام')) ?></span>
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </a>
          </div>
        </div>
      </div>

      <!-- بطاقة الصفحات الثابتة -->
      <div class="col-md-4">
        <div class="report-card pages-card">
          <div class="card-header">
            <div class="card-title-section">
              <h3 class="card-title"><?= h(__('t_0046fa59f3', 'الصفحات الثابتة')) ?></h3>
              <div class="card-badge"><?= (int)$stats['pages'] ?></div>
            </div>
            <div class="card-icon">
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </div>
          </div>
          <div class="card-body">
            <p class="card-description"><?= h(__('t_dfb8990881', 'من نحن، اتصل بنا، الخدمات، السياسات')) ?></p>
          </div>
          <div class="card-footer">
            <a href="../pages/" class="card-link">
              <span><?= h(__('t_01688690f2', 'عرض الصفحات')) ?></span>
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- الصف الثالث: الوسائط، السلايدر، كتّاب الرأي -->
    <div class="row g-4 mb-4">
      <!-- بطاقة مكتبة الوسائط -->
      <div class="col-md-4">
        <div class="report-card media-card">
          <div class="card-header">
            <div class="card-title-section">
              <h3 class="card-title"><?= h(__('t_06dd6988d0', 'مكتبة الوسائط')) ?></h3>
              <div class="card-badge"><?= (int)$stats['media'] ?></div>
            </div>
            <div class="card-icon">
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </div>
          </div>
          <div class="card-body">
            <p class="card-description"><?= h(__('t_50442947a8', 'الصور والفيديو والملفات المرفوعة')) ?></p>
          </div>
          <div class="card-footer">
            <a href="../media/" class="card-link">
              <span><?= h(__('t_0eecb21bce', 'عرض الوسائط')) ?></span>
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </a>
          </div>
        </div>
      </div>

      <!-- بطاقة السلايدر -->
      <div class="col-md-4">
        <div class="report-card slider-card">
          <div class="card-header">
            <div class="card-title-section">
              <h3 class="card-title"><?= h(__('t_58a041f8da', 'السلايدر')) ?></h3>
              <div class="card-badge"><?= (int)$stats['slider'] ?></div>
            </div>
            <div class="card-icon">
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </div>
          </div>
          <div class="card-body">
            <p class="card-description"><?= h(__('t_36f5e77f85', 'إدارة شرائح العرض في الصفحة الرئيسية')) ?></p>
          </div>
          <div class="card-footer">
            <a href="../slider/" class="card-link">
              <span><?= h(__('t_eafc27904f', 'إدارة السلايدر')) ?></span>
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </a>
          </div>
        </div>
      </div>

      <!-- بطاقة كتّاب الرأي -->
      <div class="col-md-4">
        <div class="report-card opinion-card">
          <div class="card-header">
            <div class="card-title-section">
              <h3 class="card-title"><?= h(__('t_4a173870d1', 'كتّاب الرأي')) ?></h3>
              <div class="card-badge"><?= (int)$stats['opinion_authors'] ?></div>
            </div>
            <div class="card-icon">
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </div>
          </div>
          <div class="card-body">
            <p class="card-description"><?= h(__('t_7ca83f265e', 'إدارة أعمدة وكتّاب الرأي وصفحاتهم')) ?></p>
          </div>
          <div class="card-footer">
            <a href="../opinion_authors/" class="card-link">
              <span><?= h(__('t_6996ffff9c', 'عرض الكتّاب')) ?></span>
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- الصف الرابع: فريق العمل، الإعلانات، التواصل -->
    <div class="row g-4">
      <!-- بطاقة فريق العمل -->
      <div class="col-md-4">
        <div class="report-card team-card">
          <div class="card-header">
            <div class="card-title-section">
              <h3 class="card-title"><?= h(__('t_cd54bc26ba', 'فريق العمل')) ?></h3>
              <div class="card-badge"><?= (int)$stats['team'] ?></div>
            </div>
            <div class="card-icon">
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg>
            </div>
          </div>
          <div class="card-body">
            <p class="card-description"><?= h(__('t_ef3eaa6abd', 'إدارة أعضاء فريق التحرير والإدارة')) ?></p>
          </div>
          <div class="card-footer">
            <a href="../team/" class="card-link">
              <span><?= h(__('t_233ca8e8ec', 'عرض الفريق')) ?></span>
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </a>
          </div>
        </div>
      </div>

      <!-- بطاقة الإعلانات -->
      <div class="col-md-4">
        <div class="report-card ads-card">
          <div class="card-header">
            <div class="card-title-section">
              <h3 class="card-title"><?= h(__('t_5750d13d2c', 'الإعلانات')) ?></h3>
              <div class="card-badge"><?= (int)$stats['ads_active'] ?></div>
            </div>
            <div class="card-icon">
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </div>
          </div>
          <div class="card-body">
            <p class="card-description"><?= h(__('t_a7fbb72cc2', 'إدارة البانرات ومواقع الظهور والحملات')) ?></p>
          </div>
          <div class="card-footer">
            <a href="../ads/" class="card-link">
              <span><?= h(__('t_3d3316a8ed', 'إدارة الإعلانات')) ?></span>
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </a>
          </div>
        </div>
      </div>

      <!-- بطاقة التواصل -->
      <div class="col-md-4">
        <div class="report-card contact-card">
          <div class="card-header">
            <div class="card-title-section">
              <h3 class="card-title"><?= h(__('t_cab8942d73', 'رسائل التواصل')) ?></h3>
              <div class="card-badge"><?= (int)$stats['contacts_new'] ?></div>
            </div>
            <div class="card-icon">
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </div>
          </div>
          <div class="card-body">
            <p class="card-description"><?= h(__('t_676df33996', 'إدارة الرسائل الواردة من نموذج اتصل بنا')) ?></p>
          </div>
          <div class="card-footer">
            <a href="../contact/" class="card-link">
              <span><?= h(__('t_e601e3e5a4', 'عرض الرسائل')) ?></span>
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </a>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<style>
:root {
  --primary-color: #38bdf8;
  --success-color: #22c55e;
  --warning-color: #f59e0b;
  --info-color: #0ea5e9;
  --danger-color: #ef4444;
  --purple-color: #8b5cf6;
  --teal-color: #14b8a6;
  --orange-color: #f97316;
  --indigo-color: #6366f1;
  --pink-color: #ec4899;
  --cyan-color: #06b6d4;
  --emerald-color: #10b981;
}

/* التصميم الموحد للعرض - منع التمرير الأفقي + عدم التداخل مع السايدبار */
html, body {
  overflow-x: hidden;
}

@media (min-width: 992px) {
  .admin-content {
    margin-right: 260px !important; /* نفس عرض القائمة الجانبية */
  }
}

/* خلفية عامة للصفحة وتنسيق النص */
.admin-content.gdy-page {
  background: radial-gradient(circle at top left, #020617 0%, #020617 45%, #020617 100%);
  min-height: 100vh;
  color: #e5e7eb;
  font-family: "Cairo", system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

/* تقليص عرض المحتوى وتوسيطه */
.admin-content.gdy-page .container-fluid {
  max-width: 1200px;
  margin: 0 auto;
}

/* شريط الفلاتر للتقارير */
.reports-filters {
  background: radial-gradient(circle at top, rgba(15,23,42,0.95), rgba(15,23,42,0.98));
  border: 1px solid rgba(31,41,55,0.8);
  border-radius: 16px;
  padding: 12px 16px 10px;
  backdrop-filter: blur(10px);
}

.reports-filters .form-select,
.reports-filters .form-check-input {
  background-color: rgba(15,23,42,0.9);
  border-color: rgba(148,163,184,0.4);
  color: #e5e7eb;
  border-radius: .6rem;
  font-size: .85rem;
}

.reports-filters .form-select:focus,
.reports-filters .form-check-input:focus {
  border-color: #0ea5e9;
  box-shadow: 0 0 0 0.15rem rgba(14,165,233,0.35);
}

.reports-filters .form-check-label {
  color: #e5e7eb;
}

/* تصميم البطاقات الأساسي */
.report-card {
  background: radial-gradient(circle at top left, rgba(15, 23, 42, 0.95), rgba(15, 23, 42, 0.98));
  border: 1px solid rgba(31, 41, 55, 0.8);
  border-radius: 16px;
  padding: 0;
  color: #e5e7eb;
  position: relative;
  overflow: hidden;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  height: 100%;
  backdrop-filter: blur(10px);
  min-height: 200px;
  display: flex;
  flex-direction: column;
}

.report-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, var(--card-color), transparent);
  opacity: 0.9;
}

.report-card:hover {
  transform: translateY(-8px) scale(1.02);
  border-color: rgba(255, 255, 255, 0.4);
  box-shadow: 0 20px 40px rgba(15, 23, 42, 0.8);
}

/* ألوان البطاقات */
.user-card { --card-color: var(--primary-color); }
.news-card { --card-color: var(--success-color); }
.reports-card { --card-color: var(--warning-color); }
.content-card { --card-color: var(--info-color); }
.categories-card { --card-color: var(--purple-color); }
.pages-card { --card-color: var(--teal-color); }
.media-card { --card-color: var(--orange-color); }
.slider-card { --card-color: var(--indigo-color); }
.opinion-card { --card-color: var(--pink-color); }
.team-card { --card-color: var(--cyan-color); }
.ads-card { --card-color: var(--emerald-color); }
.contact-card { --card-color: var(--danger-color); }

/* رأس البطاقة */
.report-card .card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 20px 20px 0;
  margin-bottom: 15px;
  flex-shrink: 0;
}

.card-title-section {
  flex: 1;
  min-width: 0;
}

.card-title {
  font-size: 1.2rem;
  font-weight: 700;
  margin: 0 0 10px 0;
  color: #e5e7eb;
  line-height: 1.3;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.card-badge {
  background: var(--card-color);
  color: white;
  padding: 8px 14px;
  border-radius: 20px;
  font-size: 1.2rem;
  font-weight: 800;
  display: inline-block;
  box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
  min-width: 50px;
  text-align: center;
}

.card-icon {
  width: 55px;
  height: 55px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
  background: rgba(255, 255, 255, 0.1);
  color: var(--card-color);
  margin-left: 15px;
  flex-shrink: 0;
  transition: all 0.3s ease;
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.report-card:hover .card-icon {
  transform: scale(1.15) rotate(8deg);
  background: var(--card-color);
  color: white;
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
}

/* جسم البطاقة */
.report-card .card-body {
  padding: 0 20px 15px;
  flex: 1;
  display: flex;
  align-items: center;
}

.card-description {
  font-size: 0.9rem;
  color: #9ca3af;
  line-height: 1.5;
  margin: 0;
  opacity: 0.9;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* تذييل البطاقة */
.report-card .card-footer {
  padding: 15px 20px;
  border-top: 1px solid rgba(31, 41, 55, 0.8);
  background: rgba(2, 6, 23, 0.5);
  border-radius: 0 0 16px 16px;
  flex-shrink: 0;
}

.card-link {
  display: flex;
  align-items: center;
  justify-content: space-between;
  color: var(--card-color);
  text-decoration: none;
  font-size: 0.85rem;
  font-weight: 600;
  transition: all 0.3s ease;
  padding: 6px 0;
}

.card-link:hover {
  color: #e5e7eb;
  transform: translateX(-5px);
}

.card-link i {
  transition: transform 0.3s ease;
  font-size: 0.8rem;
}

.card-link:hover i {
  transform: translateX(-4px);
}

/* رأس الصفحة */
.gdy-page-header {
  background: radial-gradient(circle at top, rgba(15, 23, 42, 0.95), rgba(15, 23, 42, 0.98));
  border: 1px solid rgba(31, 41, 55, 0.8);
  border-radius: 16px;
  padding: 20px;
  backdrop-filter: blur(10px);
  margin-bottom: 20px;
}

/* أزرار */
.btn {
  border-radius: 12px;
  font-weight: 500;
  transition: all 0.3s ease;
  border: 1px solid transparent;
  font-size: 0.85rem;
  padding: 8px 16px;
}

.btn-primary {
  background: var(--primary-color);
  border-color: var(--primary-color);
}

.btn-primary:hover {
  background: #0ea5e9;
  border-color: #0ea5e9;
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(56, 189, 248, 0.3);
}

.btn-outline-light {
  border-color: rgba(255, 255, 255, 0.2);
  color: #e5e7eb;
  background: rgba(255, 255, 255, 0.05);
}

.btn-outline-light:hover {
  background: rgba(255, 255, 255, 0.1);
  border-color: rgba(255, 255, 255, 0.3);
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(255, 255, 255, 0.1);
}

/* تأثيرات الحركة */
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(25px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes pulseGlow {
  0%, 100% {
    box-shadow: 0 0 20px rgba(56, 189, 248, 0.3);
  }
  50% {
    box-shadow: 0 0 30px rgba(56, 189, 248, 0.6);
  }
}

.report-card {
  animation: fadeInUp 0.6s ease-out;
}

.report-card:hover {
  animation: pulseGlow 2s infinite;
}

/* توزيع التأخيرات للصفوف */
.row .col-md-4:nth-child(1) .report-card { animation-delay: 0.1s; }
.row .col-md-4:nth-child(2) .report-card { animation-delay: 0.2s; }
.row .col-md-4:nth-child(3) .report-card { animation-delay: 0.3s; }

/* تحسينات للشاشات الصغيرة */
@media (max-width: 992px) {
  .col-md-4 {
    margin-bottom: 20px;
  }

  .report-card {
    min-height: 180px;
  }

  .card-title {
    font-size: 1.1rem;
  }

  .card-badge {
    font-size: 1.1rem;
    padding: 6px 12px;
  }

  .card-icon {
    width: 50px;
    height: 50px;
    font-size: 1.2rem;
  }
}

@media (max-width: 768px) {
  .report-card .card-header {
    padding: 15px 15px 0;
  }

  .report-card .card-body {
    padding: 0 15px 10px;
  }

  .report-card .card-footer {
    padding: 12px 15px;
  }

  .card-title {
    font-size: 1rem;
  }

  .card-description {
    font-size: 0.85rem;
  }

  .reports-filters {
    padding: 10px 12px;
  }
}

@media (max-width: 576px) {
  .gdy-page-header .d-flex {
    flex-direction: column;
    gap: 15px;
    text-align: center;
  }

  .card-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .card-icon {
    margin-left: 0;
    margin-top: 10px;
    align-self: flex-start;
  }
}

/* تأثيرات خاصة للبطاقات */
.report-card {
  position: relative;
  overflow: hidden;
}

.report-card::after {
  content: '';
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
  opacity: 0;
  transition: opacity 0.4s ease;
  pointer-events: none;
}

.report-card:hover::after {
  opacity: 1;
}

/* تحسين التخطيط للشاشات الكبيرة */
@media (min-width: 1200px) {
  .report-card {
    min-height: 220px;
  }

  .card-title {
    font-size: 1.3rem;
  }

  .card-badge {
    font-size: 1.3rem;
    padding: 10px 16px;
  }

  .card-icon {
    width: 60px;
    height: 60px;
    font-size: 1.5rem;
  }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // تحديث الإحصائيات
  document.getElementById('refreshStats')?.addEventListener('click', function() {
    const btn = this;
    const originalHTML = btn.innerHTML;

    btn.innerHTML = '<svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> جاري التحديث...';
    btn.disabled = true;

    // تأثير اهتزاز للبطاقات
    document.querySelectorAll('.report-card').forEach(card => {
      card.style.animation = 'pulseGlow 0.5s ease-in-out';
      setTimeout(() => {
        card.style.animation = '';
      }, 500);
    });

    // محاكاة تحديث البيانات
    setTimeout(() => {
      btn.innerHTML = originalHTML;
      btn.disabled = false;
      showToast('تم تحديث البيانات بنجاح', 'success');
    }, 1500);
  });

  // تصدير التقرير
  document.getElementById('exportReport')?.addEventListener('click', function() {
    showToast('جاري تحضير التقرير للتحميل...', 'info');

    // تأثير تحميل للبطاقات
    const cards = document.querySelectorAll('.report-card');
    cards.forEach((card, index) => {
      setTimeout(() => {
        card.style.transform = 'scale(0.95)';
        card.style.opacity = '0.7';
        setTimeout(() => {
          card.style.transform = '';
          card.style.opacity = '';
        }, 300);
      }, index * 100);
    });

    // محاكاة تصدير التقرير
    setTimeout(() => {
      showToast('تم تصدير التقرير بنجاح', 'success');
    }, 2000);
  });

  // تغيير نطاق التقارير
  const rangeSelect = document.getElementById('reportRange');
  if (rangeSelect) {
    rangeSelect.addEventListener('change', function() {
      const v = this.value;
      let txt = 'تم تغيير نطاق التقارير إلى ';
      if (v === 'today') txt += 'اليوم';
      else if (v === 'week') txt += 'آخر 7 أيام';
      else if (v === 'month') txt += 'آخر 30 يوم';
      else txt += 'نطاق مخصص';
      showToast(txt, 'info');
    });
  }

  // تفعيل / إلغاء التحديث التلقائي (واجهة فقط)
  const autoRefresh = document.getElementById('autoRefresh');
  if (autoRefresh) {
    autoRefresh.addEventListener('change', function() {
      if (this.checked) {
        showToast('تم تفعيل التحديث التلقائي (واجهة فقط حالياً)', 'info');
      } else {
        showToast('تم إيقاف التحديث التلقائي', 'info');
      }
    });
  }

  // تأثيرات hover متقدمة للبطاقات
  const cards = document.querySelectorAll('.report-card');
  cards.forEach(card => {
    card.addEventListener('mouseenter', function() {
      this.style.zIndex = '10';
    });

    card.addEventListener('mouseleave', function() {
      this.style.zIndex = '';
    });
  });

  // وظيفة لعرض الرسائل
  function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      background: ${type === 'success' ? '#22c55e' : type === 'error' ? '#ef4444' : '#38bdf8'};
      color: white;
      padding: 12px 20px;
      border-radius: 12px;
      z-index: 10000;
      transform: translateX(100%);
      transition: transform 0.3s ease;
      box-shadow: 0 8px 25px rgba(0,0,0,0.3);
      font-weight: 500;
      max-width: 300px;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => toast.style.transform = 'translateX(0)', 100);

    setTimeout(() => {
      toast.style.transform = 'translateX(100%)';
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }
});
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
