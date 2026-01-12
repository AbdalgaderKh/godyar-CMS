<?php
// admin/layout/sidebar.php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// قاعدة الروابط
$siteBase  = function_exists('base_url') ? rtrim(base_url(), '/') : '';
$adminBase = $siteBase . '/admin';

// الصفحة الحالية (تُمرَّر من index.php)
$currentPage = $currentPage ?? 'dashboard';

// إحصاءات مبسطة (يمكن استبدالها بقيم ديناميكية)
$quickStats = $quickStats ?? [
    'posts'    => 124,
    'users'    => 45,
    'comments' => 287,
];

// إشعارات مبسطة
$notifications = $notifications ?? [
    'reports'  => 2,
    'comments' => 5,
    'contact'  => 3,
];

// بيانات المستخدم من الجلسة
$userName   = $_SESSION['user']['name']   ?? ($_SESSION['user']['email'] ?? __('t_ead53a737a', 'مشرف النظام'));
$userRole   = $_SESSION['user']['role']   ?? 'admin';
$userAvatar = $_SESSION['user']['avatar'] ?? null;



// تحميل Auth عند الحاجة
if (!class_exists(\Godyar\Auth::class)) {
    @require_once __DIR__ . '/../../includes/auth.php';
}

if (class_exists(\Godyar\Auth::class) && \Godyar\Auth::isWriter()) {
    // Sidebar مبسط للكاتب (إخفاء باقي الخصائص)
    ?>
    <aside class="admin-sidebar" id="adminSidebar" role="navigation" aria-label="<?= h(__('t_b5192351b2', 'القائمة الجانبية للوحة التحكم')) ?>">
      <div class="admin-sidebar__card">
        <div class="admin-sidebar__brand">
          <a class="admin-sidebar__brand-link" href="<?= h($adminBase) ?>/news/index.php">
            <span class="admin-sidebar__brand-text"><?= h(__('t_2303d38e34', 'لوحة الكاتب')) ?></span>
          </a>
        </div>

        <ul class="admin-sidebar__nav" role="list">
          <li class="admin-sidebar__item">
            <div class="admin-sidebar__link-card <?= $currentPage === 'posts' ? 'is-active' : '' ?>">
              <a href="<?= h($adminBase) ?>/news/index.php" class="admin-sidebar__link">
                <div class="admin-sidebar__link-main">
                  <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg></div>
                  <div class="admin-sidebar__link-text">
                    <div class="admin-sidebar__link-title"><?= h(__('t_de42e4f966', 'مقالاتي')) ?></div>
                    <div class="admin-sidebar__link-desc"><?= h(__('t_e43a9422c5', 'عرض وتعديل مقالاتي')) ?></div>
                  </div>
                </div>
              </a>
            </div>
          </li>

          <li class="admin-sidebar__item">
            <div class="admin-sidebar__link-card">
              <a href="<?= h($adminBase) ?>/news/create.php" class="admin-sidebar__link">
                <div class="admin-sidebar__link-main">
                  <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></div>
                  <div class="admin-sidebar__link-text">
                    <div class="admin-sidebar__link-title"><?= h(__('t_ae41371b05', 'إضافة مقال')) ?></div>
                    <div class="admin-sidebar__link-desc"><?= h(__('t_aa08c5e144', 'إنشاء مقال جديد')) ?></div>
                  </div>
                </div>
              </a>
            </div>
          </li>

          <li class="admin-sidebar__item">
            <div class="admin-sidebar__link-card">
              <a href="<?= h($adminBase) ?>/logout.php" class="admin-sidebar__link">
                <div class="admin-sidebar__link-main">
                  <div class="admin-sidebar__link-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#logout"></use></svg></div>
                  <div class="admin-sidebar__link-text">
                    <div class="admin-sidebar__link-title"><?= h(__('t_5c4e4796c0', 'تسجيل الخروج')) ?></div>
                    <div class="admin-sidebar__link-desc"><?= h(__('t_0df506fdfa', 'إنهاء الجلسة')) ?></div>
                  </div>
                </div>
              </a>
            </div>
          </li>
        </ul>
      </div>
    </aside>
    <?php
    return;
}

?>
<!-- لاحظ: أزلت كلاس col-md-3 col-lg-2 -->
<aside class="admin-sidebar" id="adminSidebar" role="navigation" aria-label="<?= h(__('t_b5192351b2', 'القائمة الجانبية للوحة التحكم')) ?>">
  <div class="admin-sidebar__card">

    <!-- رأس السايدبار -->
    <header class="admin-sidebar__header">
      <div class="admin-sidebar__brand">
        <div class="admin-sidebar__logo">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg>
        </div>
        <div class="admin-sidebar__brand-text">
          <div class="admin-sidebar__title">Godyar News</div>
          <div class="admin-sidebar__subtitle"><?= h(__('t_a06ee671f4', 'لوحة التحكم')) ?></div>
        </div>
      </div>

      <!-- زر إظهار/إخفاء في الجوال -->
      <button class="admin-sidebar__toggle" id="sidebarToggle" type="button" aria-label="<?= h(__('t_c21bebe724', 'إظهار/إخفاء القائمة')) ?>">
        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
      </button>
    </header>

    <!-- مربع البحث -->
    <div class="admin-sidebar__search-wrapper">
      <div class="admin-sidebar__search">
        <input
          type="text"
          id="sidebarSearch"
          class="admin-sidebar__search-input"
          placeholder="<?= h(__('t_2cac671915', 'ابحث في القوائم...')) ?>"
          autocomplete="off"
          aria-label="<?= h(__('t_8b39c16358', 'بحث في عناصر القائمة')) ?>"
        >
        <svg class="gdy-icon admin-sidebar__search-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#search"></use></svg>
        <div class="admin-sidebar__search-results" id="searchResults" role="listbox" aria-label="<?= h(__('t_8dea9c0652', 'نتائج البحث')) ?>"></div>
      </div>
    </div>

    <!-- إحصاءات سريعة -->
    <div class="admin-sidebar__quick">
      <div class="admin-sidebar__quick-item">
        <div class="admin-sidebar__quick-icon">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg>
        </div>
        <div class="admin-sidebar__quick-info">
          <div class="admin-sidebar__quick-number"><?= (int)$quickStats['posts'] ?></div>
          <div class="admin-sidebar__quick-label"><?= h(__('t_9e940078a1', 'مقالة')) ?></div>
        </div>
      </div>
      <div class="admin-sidebar__quick-item">
        <div class="admin-sidebar__quick-icon">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
        </div>
        <div class="admin-sidebar__quick-info">
          <div class="admin-sidebar__quick-number"><?= (int)$quickStats['users'] ?></div>
          <div class="admin-sidebar__quick-label"><?= h(__('t_f1beebf31c', 'مستخدم')) ?></div>
        </div>
      </div>
      <div class="admin-sidebar__quick-item">
        <div class="admin-sidebar__quick-icon">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
        </div>
        <div class="admin-sidebar__quick-info">
          <div class="admin-sidebar__quick-number"><?= (int)$quickStats['comments'] ?></div>
          <div class="admin-sidebar__quick-label"><?= h(__('t_0215afbb03', 'تعليق')) ?></div>
        </div>
      </div>
    </div>

    <!-- محتوى القائمة -->
    <nav class="admin-sidebar__body">

      <!-- نظرة عامة -->
      <section class="admin-sidebar__section">
        <button class="admin-sidebar__section-header" type="button" data-section="overview">
          <span><?= h(__('t_22daf17224', 'نظرة عامة')) ?></span>
          <svg class="gdy-icon admin-sidebar__section-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#chevron-down"></use></svg>
        </button>
        <ul class="admin-sidebar__list" id="section-overview">
          <li class="admin-sidebar__item">
            <div class="admin-sidebar__link-card <?= $currentPage === 'dashboard' ? 'is-active' : '' ?>"
                 data-search="الرئيسية لوحة التحكم نظرة عامة">
              <a href="<?= h($adminBase) ?>/index.php" class="admin-sidebar__link">
                <div class="admin-sidebar__link-main">
                  <div class="admin-sidebar__link-icon">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  </div>
                  <div class="admin-sidebar__link-text">
                    <div class="admin-sidebar__link-label"><?= h(__('t_3aa8578699', 'الرئيسية')) ?></div>
                    <div class="admin-sidebar__link-sub"><?= h(__('t_eb65a9a9db', 'نظرة عامة على أداء النظام')) ?></div>
                  </div>
                </div>
                <div class="admin-sidebar__link-meta">
                  <?php if (!empty($notifications['reports'])): ?>
                    <span class="admin-sidebar__badge"><?= (int)$notifications['reports'] ?></span>
                  <?php endif; ?>
                  <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                </div>
              </a>
            </div>
          </li>

          <li class="admin-sidebar__item">
            <div class="admin-sidebar__link-card <?= $currentPage === 'reports' ? 'is-active' : '' ?>"
                 data-search="التقارير الاحصائيات مؤشرات الأداء">
              <a href="<?= h($adminBase) ?>/reports/index.php" class="admin-sidebar__link">
                <div class="admin-sidebar__link-main">
                  <div class="admin-sidebar__link-icon">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  </div>
                  <div class="admin-sidebar__link-text">
                    <div class="admin-sidebar__link-label"><?= h(__('t_4d4e102c5e', 'التقارير')) ?></div>
                    <div class="admin-sidebar__link-sub"><?= h(__('t_9858619f9c', 'لوحات تحكم ومؤشرات أداء')) ?></div>
                  </div>
                </div>
                <div class="admin-sidebar__link-meta">
                  <span class="admin-sidebar__badge admin-sidebar__badge--pill"><?= h(__('t_c590a35c2d', 'جديد')) ?></span>
                  <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                </div>
              </a>
            </div>
          </li>
        </ul>
      </section>

      <!-- المحتوى -->
      <section class="admin-sidebar__section">
        <button class="admin-sidebar__section-header" type="button" data-section="content">
          <span><?= h(__('t_9f3797ed99', 'المحتوى')) ?></span>
          <svg class="gdy-icon admin-sidebar__section-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#chevron-down"></use></svg>
        </button>
        <ul class="admin-sidebar__list" id="section-content">

          <li class="admin-sidebar__item">
            <div class="admin-sidebar__link-card <?= $currentPage === 'posts' ? 'is-active' : '' ?>"
                 data-search="إدارة المحتوى الأخبار المقالات الوسوم">
              <a href="<?= h($adminBase) ?>/news/index.php" class="admin-sidebar__link">
                <div class="admin-sidebar__link-main">
                  <div class="admin-sidebar__link-icon">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg>
                  </div>
                  <div class="admin-sidebar__link-text">
                    <div class="admin-sidebar__link-label"><?= h(__('t_c6dac40d6a', 'إدارة المحتوى')) ?></div>
                    <div class="admin-sidebar__link-sub"><?= h(__('t_67286c3c09', 'الأخبار والمقالات والتصنيفات والوسوم')) ?></div>
                  </div>
                </div>
                <div class="admin-sidebar__link-meta">
                  <span class="admin-sidebar__badge"><?= (int)$quickStats['posts'] ?></span>
                  <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                </div>
              </a>
            </div>
          </li>

          <li class="admin-sidebar__item">
            <div class="admin-sidebar__link-card <?= $currentPage === 'polls' ? 'is-active' : '' ?>"
                 data-search="استطلاع داخل المقال إدارة الاستطلاعات poll votes options">
              <a href="<?= h($adminBase) ?>/news/polls.php" class="admin-sidebar__link">
                <div class="admin-sidebar__link-main">
                  <div class="admin-sidebar__link-icon">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  </div>
                  <div class="admin-sidebar__link-text">
                    <div class="admin-sidebar__link-label">إدارة الاستطلاعات</div>
                    <div class="admin-sidebar__link-sub">إنشاء/تعديل استطلاع لكل مقال</div>
                  </div>
                </div>
                <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              </a>
            </div>
          </li>

          <li class="admin-sidebar__item">
            <div class="admin-sidebar__link-card <?= $currentPage === 'questions' ? 'is-active' : '' ?>"
                 data-search="اسأل الكاتب أسئلة وأجوبة إدارة الأسئلة">
              <a href="<?= h($adminBase) ?>/news/questions.php" class="admin-sidebar__link">
                <div class="admin-sidebar__link-main">
                  <div class="admin-sidebar__link-icon">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  </div>
                  <div class="admin-sidebar__link-text">
                    <div class="admin-sidebar__link-label">اسأل الكاتب</div>
                    <div class="admin-sidebar__link-sub">مراجعة الأسئلة والردود</div>
                  </div>
                </div>
                <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              </a>
            </div>
          </li>
          <li class="admin-sidebar__item">
            <div class="admin-sidebar__link-card <?= $currentPage === 'categories' ? 'is-active' : '' ?>"
                 data-search="الأقسام التصنيفات إدارة الأقسام">
              <a href="<?= h($adminBase) ?>/categories/index.php" class="admin-sidebar__link">
                <div class="admin-sidebar__link-main">
                  <div class="admin-sidebar__link-icon">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  </div>
                  <div class="admin-sidebar__link-text">
                    <div class="admin-sidebar__link-label"><?= h(__('t_c6386f9c0e', 'الأقسام')) ?></div>
                    <div class="admin-sidebar__link-sub"><?= h(__('t_a369c932e0', 'إدارة الأقسام والتصنيفات الرئيسية')) ?></div>
                  </div>
                </div>
                <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              </a>
            </div>
          </li>\n\n</li>

<li class="admin-sidebar__item">
  <div class="admin-sidebar__link-card <?= $currentPage === 'elections' ? 'is-active' : '' ?>"
       data-search="الانتخابات نتائج الانتخابات تغطية انتخابية أرشيف الانتخابات">
    <a href="<?= h($adminBase) ?>/elections/index.php" class="admin-sidebar__link">
      <div class="admin-sidebar__link-main">
        <div class="admin-sidebar__link-icon">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
        </div>
        <div class="admin-sidebar__link-text">
          <div class="admin-sidebar__link-label"><?= h(__('t_b9af904113', 'الانتخابات')) ?></div>
          <div class="admin-sidebar__link-sub"><?= h(__('t_01e75f784e', 'إدارة التغطيات الانتخابية (إظهار/إخفاء/أرشفة)')) ?></div>
        </div>
      </div>
      <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
    </a>
  </div>
</li>


          <li class="admin-sidebar__item">
            <div class="admin-sidebar__link-card <?= $currentPage === 'pages' ? 'is-active' : '' ?>"
                 data-search="الصفحات الثابتة من نحن اتصل بنا">
              <a href="<?= h($adminBase) ?>/pages/index.php" class="admin-sidebar__link">
                <div class="admin-sidebar__link-main">
                  <div class="admin-sidebar__link-icon">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  </div>
                  <div class="admin-sidebar__link-text">
                    <div class="admin-sidebar__link-label"><?= h(__('t_0046fa59f3', 'الصفحات الثابتة')) ?></div>
                    <div class="admin-sidebar__link-sub"><?= h(__('t_5fd50a4fd6', 'من نحن، اتصل بنا، الخدمات والسياسات')) ?></div>
                  </div>
                </div>
                <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              </a>
            </div>
          </li>

          <li class="admin-sidebar__item">
            <div class="admin-sidebar__link-card <?= $currentPage === 'media' ? 'is-active' : '' ?>"
                 data-search="مكتبة الوسائط الصور الفيديو الملفات">
              <a href="<?= h($adminBase) ?>/media/index.php" class="admin-sidebar__link">
                <div class="admin-sidebar__link-main">
                  <div class="admin-sidebar__link-icon">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  </div>
                  <div class="admin-sidebar__link-text">
                    <div class="admin-sidebar__link-label"><?= h(__('t_06dd6988d0', 'مكتبة الوسائط')) ?></div>
                    <div class="admin-sidebar__link-sub"><?= h(__('t_50442947a8', 'الصور والفيديو والملفات المرفوعة')) ?></div>
                  </div>
                </div>
                <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              </a>
            </div>
          </li>

          <li class="admin-sidebar__item">
            <div class="admin-sidebar__link-card <?= $currentPage === 'slider' ? 'is-active' : '' ?>"
                 data-search="السلايدر شرائح العرض الرئيسية">
              <a href="<?= h($adminBase) ?>/slider/index.php" class="admin-sidebar__link">
                <div class="admin-sidebar__link-main">
                  <div class="admin-sidebar__link-icon">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  </div>
                  <div class="admin-sidebar__link-text">
                    <div class="admin-sidebar__link-label"><?= h(__('t_58a041f8da', 'السلايدر')) ?></div>
                    <div class="admin-sidebar__link-sub"><?= h(__('t_59a13b7c65', 'شرائح العرض في الصفحة الرئيسية')) ?></div>
                  </div>
                </div>
                <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              </a>
            </div>
          </li>

          <li class="admin-sidebar__item">
            <div class="admin-sidebar__link-card <?= $currentPage === 'videos' ? 'is-active' : '' ?>"
                 data-search="الفيديوهات المميزة مقاطع الفيديو">
              <a href="<?= h($adminBase) ?>/manage_videos.php" class="admin-sidebar__link">
                <div class="admin-sidebar__link-main">
                  <div class="admin-sidebar__link-icon">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  </div>
                  <div class="admin-sidebar__link-text">
                    <div class="admin-sidebar__link-label"><?= h(__('t_8dd4b9c7f3', 'الفيديوهات المميزة')) ?></div>
                    <div class="admin-sidebar__link-sub"><?= h(__('t_ef45c07e19', 'إدارة مقاطع الفيديو المميزة')) ?></div>
                  </div>
                </div>
                <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              </a>
            </div>
          </li>

          <li class="admin-sidebar__item">
            <div class="admin-sidebar__link-card <?= $currentPage === 'opinion_authors' ? 'is-active' : '' ?>"
                 data-search="كتّاب الرأي أعمدة الرأي مقالات رأي">
              <a href="<?= h($adminBase) ?>/opinion_authors/index.php" class="admin-sidebar__link">
                <div class="admin-sidebar__link-main">
                  <div class="admin-sidebar__link-icon">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  </div>
                  <div class="admin-sidebar__link-text">
                    <div class="admin-sidebar__link-label"><?= h(__('t_4a173870d1', 'كتّاب الرأي')) ?></div>
                    <div class="admin-sidebar__link-sub"><?= h(__('t_acf979fd60', 'إدارة كتّاب وأعمدة الرأي')) ?></div>
                  </div>
                </div>
                <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              </a>
            </div>
          </li>
        </ul>
      </section>

      <!-- المستخدمون وفريق العمل -->
      <section class="admin-sidebar__section">
        <button class="admin-sidebar__section-header" type="button" data-section="users">
          <span><?= h(__('t_849cd8703b', 'المستخدمون وفريق العمل')) ?></span>
          <svg class="gdy-icon admin-sidebar__section-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#chevron-down"></use></svg>
        </button>
        <ul class="admin-sidebar__list" id="section-users">
          <li class="admin-sidebar__item">
            <div class="admin-sidebar__link-card <?= $currentPage === 'users' ? 'is-active' : '' ?>"
                 data-search="المستخدمون الأعضاء حسابات تسجيل الدخول الصلاحيات">
              <a href="<?= h($adminBase) ?>/users/index.php" class="admin-sidebar__link">
                <div class="admin-sidebar__link-main">
                  <div class="admin-sidebar__link-icon">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  </div>
                  <div class="admin-sidebar__link-text">
                    <div class="admin-sidebar__link-label"><?= h(__('t_39d3073371', 'المستخدمون')) ?></div>
                    <div class="admin-sidebar__link-sub"><?= h(__('t_3ed68c57ce', 'إدارة حسابات المستخدمين والصلاحيات')) ?></div>
                  </div>
                </div>
                <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              </a>
            </div>
          </li>

          <li class="admin-sidebar__item">
            <div class="admin-sidebar__link-card <?= $currentPage === 'team' ? 'is-active' : '' ?>"
                 data-search="فريق العمل هيئة التحرير طاقم الموقع">
              <a href="<?= h($adminBase) ?>/team/index.php" class="admin-sidebar__link">
                <div class="admin-sidebar__link-main">
                  <div class="admin-sidebar__link-icon">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg>
                  </div>
                  <div class="admin-sidebar__link-text">
                    <div class="admin-sidebar__link-label"><?= h(__('t_cd54bc26ba', 'فريق العمل')) ?></div>
                    <div class="admin-sidebar__link-sub"><?= h(__('t_ef3eaa6abd', 'إدارة أعضاء فريق التحرير والإدارة')) ?></div>
                  </div>
                </div>
                <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              </a>
            </div>
          </li>
        </ul>
      </section>

      <!-- الإعلانات والتواصل -->
      <section class="admin-sidebar__section">
        <button class="admin-sidebar__section-header" type="button" data-section="marketing">
          <span><?= h(__('t_be948b3b75', 'الإعلانات والتواصل')) ?></span>
          <svg class="gdy-icon admin-sidebar__section-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#chevron-down"></use></svg>
        </button>
        <ul class="admin-sidebar__list" id="section-marketing">
          <li class="admin-sidebar__item">
            <div class="admin-sidebar__link-card <?= $currentPage === 'ads' ? 'is-active' : '' ?>"
                 data-search="الإعلانات البانرات حملات إعلانية">
              <a href="<?= h($adminBase) ?>/ads/index.php" class="admin-sidebar__link">
                <div class="admin-sidebar__link-main">
                  <div class="admin-sidebar__link-icon">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  </div>
                  <div class="admin-sidebar__link-text">
                    <div class="admin-sidebar__link-label"><?= h(__('t_5750d13d2c', 'الإعلانات')) ?></div>
                    <div class="admin-sidebar__link-sub"><?= h(__('t_aa106ae03d', 'إدارة البانرات والحملات الإعلانية')) ?></div>
                  </div>
                </div>
                <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              </a>
            </div>
          </li>

          <li class="admin-sidebar__item">
            <div class="admin-sidebar__link-card <?= $currentPage === 'contact' ? 'is-active' : '' ?>"
                 data-search="رسائل التواصل اتصل بنا رسائل الزوار">
              <a href="<?= h($adminBase) ?>/contact/index.php" class="admin-sidebar__link">
                <div class="admin-sidebar__link-main">
                  <div class="admin-sidebar__link-icon">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  </div>
                  <div class="admin-sidebar__link-text">
                    <div class="admin-sidebar__link-label"><?= h(__('t_cab8942d73', 'رسائل التواصل')) ?></div>
                    <div class="admin-sidebar__link-sub"><?= h(__('t_676df33996', 'إدارة الرسائل الواردة من نموذج اتصل بنا')) ?></div>
                  </div>
                </div>
                <div class="admin-sidebar__link-meta">
                  <?php if (!empty($notifications['contact'])): ?>
                    <span class="admin-sidebar__badge"><?= (int)$notifications['contact'] ?></span>
                  <?php endif; ?>
                  <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                </div>
              </a>
            </div>
          </li>
        </ul>
      </section>

      <!-- روابط سريعة -->
      <section class="admin-sidebar__section admin-sidebar__section--quick">
        <button class="admin-sidebar__section-header" type="button" data-section="shortcuts">
          <span><?= h(__('t_f77df0e146', 'روابط سريعة')) ?></span>
          <svg class="gdy-icon admin-sidebar__section-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#chevron-down"></use></svg>
        </button>
        <ul class="admin-sidebar__list" id="section-shortcuts">
          <li class="admin-sidebar__item">
            <div class="admin-sidebar__link-card" data-search="إضافة خبر جديد مقال منشور create post">
              <a href="<?= h($adminBase) ?>/news/create.php" class="admin-sidebar__link">
                <div class="admin-sidebar__link-main">
                  <div class="admin-sidebar__link-icon">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  </div>
                  <div class="admin-sidebar__link-text">
                    <div class="admin-sidebar__link-label"><?= h(__('t_0d1f6ecf66', 'إضافة خبر جديد')) ?></div>
                    <div class="admin-sidebar__link-sub"><?= h(__('t_8df5af2c8e', 'إنشاء خبر أو مقال جديد بسرعة')) ?></div>
                  </div>
                </div>
                <span class="admin-sidebar__badge admin-sidebar__badge--pill">+1</span>
              </a>
            </div>
          </li>

          <li class="admin-sidebar__item">
            <div class="admin-sidebar__link-card" data-search="إدارة التصنيفات أقسام الموقع">
              <a href="<?= h($adminBase) ?>/categories/index.php" class="admin-sidebar__link">
                <div class="admin-sidebar__link-main">
                  <div class="admin-sidebar__link-icon">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  </div>
                  <div class="admin-sidebar__link-text">
                    <div class="admin-sidebar__link-label"><?= h(__('t_0a83b235e0', 'إدارة الأقسام')) ?></div>
                    <div class="admin-sidebar__link-sub"><?= h(__('t_f928c160bf', 'الأقسام الرئيسية والفرعية للمحتوى')) ?></div>
                  </div>
                </div>
                <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              </a>
            </div>
          </li>

          <li class="admin-sidebar__item">
            <div class="admin-sidebar__link-card" data-search="مكتبة الوسائط رفع صورة سريعة">
              <a href="<?= h($adminBase) ?>/media/upload.php" class="admin-sidebar__link">
                <div class="admin-sidebar__link-main">
                  <div class="admin-sidebar__link-icon">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  </div>
                  <div class="admin-sidebar__link-text">
                    <div class="admin-sidebar__link-label"><?= h(__('t_f8d557e5cd', 'رفع وسائط')) ?></div>
                    <div class="admin-sidebar__link-sub"><?= h(__('t_5f5b0f52d9', 'رفع صورة أو ملف لاستخدامه في الأخبار')) ?></div>
                  </div>
                </div>
                <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              </a>
            </div>
          </li>
        </ul>
      </section>

      <!-- الإعدادات -->
      <section class="admin-sidebar__section">
        <button class="admin-sidebar__section-header" type="button" data-section="settings">
          <span><?= h(__('t_1f60020959', 'الإعدادات')) ?></span>
          <svg class="gdy-icon admin-sidebar__section-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#chevron-down"></use></svg>
        </button>
        <ul class="admin-sidebar__list" id="section-settings">
          <li class="admin-sidebar__item">
            <div class="admin-sidebar__link-card <?= $currentPage === 'settings' ? 'is-active' : '' ?>"
                 data-search="الإعدادات العامة اسم الموقع الشعار اللغة المنطقة الزمنية">
              <a href="<?= h($adminBase) ?>/settings/index.php" class="admin-sidebar__link">
                <div class="admin-sidebar__link-main">
                  <div class="admin-sidebar__link-icon">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  </div>
                  <div class="admin-sidebar__link-text">
                    <div class="admin-sidebar__link-label"><?= h(__('t_46ce4c91ac', 'الإعدادات العامة')) ?></div>
                    <div class="admin-sidebar__link-sub"><?= h(__('t_22f6a1b54b', 'اسم الموقع، الشعار، اللغة، المنطقة الزمنية')) ?></div>
                  </div>
                </div>
                <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              </a>
            </div>
          </li>

          <li class="admin-sidebar__item">
            <div class="admin-sidebar__link-card <?= $currentPage === 'weather' ? 'is-active' : '' ?>"
                 data-search="إعدادات الطقس حالة الطقس API">
              <a href="<?= h($adminBase) ?>/weather_settings.php" class="admin-sidebar__link">
                <div class="admin-sidebar__link-main">
                  <div class="admin-sidebar__link-icon">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  </div>
                  <div class="admin-sidebar__link-text">
                    <div class="admin-sidebar__link-label"><?= h(__('t_cdefdef2cf', 'إعدادات الطقس')) ?></div>
                    <div class="admin-sidebar__link-sub"><?= h(__('t_143e81be2c', 'إدارة خدمة الطقس وربط API')) ?></div>
                  </div>
                </div>
                <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              </a>
            </div>
          </li>
        </ul>
      </section>

      <!-- النظام والصيانة -->
      <section class="admin-sidebar__section">
        <button class="admin-sidebar__section-header" type="button" data-section="system">
          <span><?= h(__('t_435013dbc1', 'النظام والصيانة')) ?></span>
          <svg class="gdy-icon admin-sidebar__section-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#chevron-down"></use></svg>
        </button>
        <ul class="admin-sidebar__list" id="section-system">
          <li class="admin-sidebar__item">
            <div class="admin-sidebar__link-card <?= $currentPage === 'system_health' ? 'is-active' : '' ?>"
                 data-search="صحة النظام فحص النظام PHP قاعدة البيانات الكاش">
              <a href="<?= h($adminBase) ?>/system/health/index.php" class="admin-sidebar__link">
                <div class="admin-sidebar__link-main">
                  <div class="admin-sidebar__link-icon">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  </div>
                  <div class="admin-sidebar__link-text">
                    <div class="admin-sidebar__link-label"><?= h(__('t_63163058e0', 'صحة النظام')) ?></div>
                    <div class="admin-sidebar__link-sub"><?= h(__('t_e2bee95ee0', 'فحص إعدادات الخادم وقاعدة البيانات')) ?></div>
                  </div>
                </div>
                <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              </a>
            </div>
          </li>

          <li class="admin-sidebar__item">
            <div class="admin-sidebar__link-card <?= $currentPage === 'system_logs' ? 'is-active' : '' ?>"
                 data-search="سجلات النظام admin_logs العمليات الإدارية">
              <a href="<?= h($adminBase) ?>/system/logs/index.php" class="admin-sidebar__link">
                <div class="admin-sidebar__link-main">
                  <div class="admin-sidebar__link-icon">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  </div>
                  <div class="admin-sidebar__link-text">
                    <div class="admin-sidebar__link-label"><?= h(__('t_b872dc8c01', 'سجلات النظام')) ?></div>
                    <div class="admin-sidebar__link-sub"><?= h(__('t_b7e04759f6', 'آخر العمليات والأحداث في لوحة التحكم')) ?></div>
                  </div>
                </div>
                <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              </a>
            </div>
          </li>

          <li class="admin-sidebar__item">
            <div class="admin-sidebar__link-card <?= $currentPage === 'system_cache' ? 'is-active' : '' ?>"
                 data-search="الكاش مسح الكاش تسريع الموقع">
              <a href="<?= h($adminBase) ?>/system/cache/index.php" class="admin-sidebar__link">
                <div class="admin-sidebar__link-main">
                  <div class="admin-sidebar__link-icon">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  </div>
                  <div class="admin-sidebar__link-text">
                    <div class="admin-sidebar__link-label"><?= h(__('t_736b931c7c', 'إدارة الكاش')) ?></div>
                    <div class="admin-sidebar__link-sub"><?= h(__('t_a83e409c10', 'عرض حالة الكاش ومسح الملفات المؤقتة')) ?></div>
                  </div>
                </div>
                <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              </a>
            </div>
          </li>

          <li class="admin-sidebar__item">
            <div class="admin-sidebar__link-card <?= $currentPage === 'maintenance' ? 'is-active' : '' ?>"
                 data-search="وضع الصيانة إيقاف الموقع الصيانة">
              <a href="<?= h($adminBase) ?>/maintenance/index.php" class="admin-sidebar__link">
                <div class="admin-sidebar__link-main">
                  <div class="admin-sidebar__link-icon">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  </div>
                  <div class="admin-sidebar__link-text">
                    <div class="admin-sidebar__link-label"><?= h(__('t_f96c99c4d8', 'وضع الصيانة')) ?></div>
                    <div class="admin-sidebar__link-sub"><?= h(__('t_70cf2cbbb8', 'تفعيل/إلغاء صفحة الصيانة للزوار')) ?></div>
                  </div>
                </div>
                <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              </a>
            </div>
          </li>

          <li class="admin-sidebar__item">
            <div class="admin-sidebar__link-card <?= $currentPage === 'plugins' ? 'is-active' : '' ?>"
                 data-search="الإضافات البرمجية plugins مكونات إضافية">
              <a href="<?= h($adminBase) ?>/plugins/index.php" class="admin-sidebar__link">
                <div class="admin-sidebar__link-main">
                  <div class="admin-sidebar__link-icon">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  </div>
                  <div class="admin-sidebar__link-text">
                    <div class="admin-sidebar__link-label"><?= h(__('t_3be2bf6b96', 'الإضافات البرمجية')) ?></div>
                    <div class="admin-sidebar__link-sub"><?= h(__('t_e41f4eba3b', 'تفعيل وتعطيل مكونات النظام')) ?></div>
                  </div>
                </div>
                <svg class="gdy-icon admin-sidebar__link-arrow" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              </a>
            </div>
          </li>
        </ul>
      </section>

    </nav>

    <!-- تذييل السايدبار -->
    <footer class="admin-sidebar__footer">
      <div class="admin-sidebar__user">
        <div class="admin-sidebar__user-avatar">
          <?php if ($userAvatar): ?>
            <img src="<?= h($userAvatar) ?>" alt="<?= h(__('t_ee37e3b03b', 'صورة المستخدم')) ?>" />
          <?php else: ?>
            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg>
          <?php endif; ?>
        </div>
        <div class="admin-sidebar__user-info">
          <div class="admin-sidebar__user-name"><?= h($userName) ?></div>
          <div class="admin-sidebar__user-role"><?= h($userRole) ?></div>
        </div>
      </div>
      <div class="admin-sidebar__footer-actions">
        <a href="<?= h($siteBase) ?>/" class="admin-sidebar__action-btn" title="<?= h(__('t_03b57332e5', 'الموقع الرئيسي')) ?>" aria-label="<?= h(__('t_8a0d450cfd', 'الانتقال للموقع الرئيسي')) ?>" target="_blank" rel="noopener">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#home"></use></svg>
        </a>
        <button class="admin-sidebar__action-btn" id="darkModeToggle" type="button" title="<?= h(__('t_ccf95e3f4d', 'الوضع الليلي')) ?>" aria-label="<?= h(__('t_53144e5e01', 'تبديل الوضع الليلي')) ?>">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#moon"></use></svg>
        </button>
        <a href="<?= h($adminBase) ?>/logout.php" class="admin-sidebar__action-btn admin-sidebar__action-btn--danger" title="<?= h(__('t_5c4e4796c0', 'تسجيل الخروج')) ?>" aria-label="<?= h(__('t_5c4e4796c0', 'تسجيل الخروج')) ?>">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#logout"></use></svg>
        </a>
      </div>
    </footer>

  </div>
</aside>

<style>
:root {
  --gdy-sidebar-bg: radial-gradient(circle at top, #020617 0, #020617 55%);
  --gdy-sidebar-border: #1f2937;
  --gdy-sidebar-card-bg: rgba(15, 23, 42, 0.98);
  --gdy-sidebar-text: #e5e7eb;
  --gdy-sidebar-muted: #9ca3af;
  --gdy-sidebar-accent: #0ea5e9;
  --gdy-sidebar-accent-soft: #38bdf8;
  --gdy-sidebar-success: #22c55e;
  --gdy-sidebar-danger: #ef4444;
}


.admin-sidebar__section--quick .admin-sidebar__link-card {
  background: radial-gradient(circle at top left, rgba(45,212,191,.12), rgba(15,23,42,.98));
}
.admin-sidebar__section--quick .admin-sidebar__link-icon {
  background: radial-gradient(circle at top left, rgba(45,212,191,.25), rgba(15,23,42,1));
}
/* == أهم نقطة: السايدبار ثابتة بعرض 260px == */
.admin-sidebar {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  width: 260px !important;
  max-width: 260px !important;
  min-width: 260px !important;
  background: var(--gdy-sidebar-bg);
  color: var(--gdy-sidebar-text);
  box-shadow: 0 0 25px rgba(15, 23, 42, 0.9);
  z-index: 1040;
}

.admin-sidebar__card {
  height: 100%;
  display: flex;
  flex-direction: column;
  background: var(--gdy-sidebar-card-bg);
  border-inline-start: 1px solid var(--gdy-sidebar-border);
}

/* محتوى لوحة التحكم يأخذ هامش يمين يساوي عرض السايدبار */
@media (min-width: 992px) {
  .admin-content {
    margin-right: 260px !important;
    width: calc(100% - 260px) !important;
    max-width: calc(100% - 260px) !important;
    box-sizing: border-box;
    overflow-x: hidden;
  }
}
@media (max-width: 991.98px) {
  .admin-content {
    margin-right: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box;
    overflow-x: hidden;
  }
}

/* باقي التنسيقات كما هي (مختصرة قليلاً) */
.admin-sidebar__header {
  display:flex;align-items:center;justify-content:space-between;
  padding:.85rem .9rem;
  border-bottom:1px solid var(--gdy-sidebar-border);
}
.admin-sidebar__brand {display:flex;align-items:center;gap:.55rem;}
.admin-sidebar__logo {
  width:38px;height:38px;border-radius:14px;
  background:radial-gradient(circle at top,var(--gdy-sidebar-accent-soft),var(--gdy-sidebar-accent));
  display:flex;align-items:center;justify-content:center;
  color:#0b1120;box-shadow:0 0 18px rgba(56,189,248,.6);
}
.admin-sidebar__brand-text{text-align:right;}
.admin-sidebar__title{font-size:.95rem;font-weight:600;}
.admin-sidebar__subtitle{font-size:.75rem;color:var(--gdy-sidebar-muted);}
.admin-sidebar__toggle{display:none;width:34px;height:34px;border-radius:12px;border:1px solid var(--gdy-sidebar-border);background:#020617;color:var(--gdy-sidebar-text);justify-content:center;align-items:center;}

.admin-sidebar__search-wrapper{padding:.5rem .75rem .3rem;}
.admin-sidebar__search{position:relative;}
.admin-sidebar__search-input{
  width:100%;padding:.5rem .75rem .5rem 2.1rem;
  border-radius:999px;border:1px solid var(--gdy-sidebar-border);
  background:#020617;color:var(--gdy-sidebar-text);font-size:.8rem;
}
.admin-sidebar__search-input::placeholder{color:var(--gdy-sidebar-muted);}
.admin-sidebar__search-icon{
  position:absolute;left:.55rem;top:50%;transform:translateY(-50%);
  font-size:.8rem;color:var(--gdy-sidebar-muted);
}
.admin-sidebar__search-results{
  display:none;position:absolute;top:110%;left:0;right:0;
  background:#020617;border-radius:12px;
  border:1px solid var(--gdy-sidebar-border);
  box-shadow:0 18px 40px rgba(15,23,42,.95);
  max-height:260px;overflow-y:auto;z-index:1200;
}
.admin-sidebar__search-result-item{padding:.45rem .7rem;font-size:.8rem;color:var(--gdy-sidebar-text);text-decoration:none;display:block;border-bottom:1px solid rgba(31,41,55,.9);}
.admin-sidebar__search-result-item:last-child{border-bottom:0;}
.admin-sidebar__search-result-item:hover{background:#0b1120;}

.admin-sidebar__quick{
  padding:.3rem .7rem .45rem;
  display:grid;grid-template-columns:repeat(3,1fr);gap:.35rem;
}
.admin-sidebar__quick-item{
  display:flex;align-items:center;gap:.35rem;
  border-radius:12px;padding:.25rem .35rem;
  border:1px solid rgba(31,41,55,.95);
  background:radial-gradient(circle at top,rgba(56,189,248,.15),rgba(15,23,42,.98));
}
.admin-sidebar__quick-icon{
  width:26px;height:26px;border-radius:10px;
  display:flex;align-items:center;justify-content:center;
  background:#020617;color:var(--gdy-sidebar-accent);font-size:.8rem;
}
.admin-sidebar__quick-info{text-align:right;}
.admin-sidebar__quick-number{font-size:.9rem;font-weight:600;}
.admin-sidebar__quick-label{font-size:.7rem;color:var(--gdy-sidebar-muted);}

.admin-sidebar__body{flex:1;overflow-y:auto;padding:.4rem 0;}
.admin-sidebar__section{margin-bottom:.35rem;}
.admin-sidebar__section-header{
  width:100%;border:0;background:transparent;color:var(--gdy-sidebar-muted);
  font-size:.75rem;text-align:right;padding:.3rem .85rem;
  display:flex;align-items:center;justify-content:space-between;
  text-transform:uppercase;letter-spacing:.04em;cursor:pointer;
}
.admin-sidebar__section-arrow{font-size:.7rem;transition:transform .2s ease;}
.admin-sidebar__section-header.is-collapsed .admin-sidebar__section-arrow{transform:rotate(-90deg);}
.admin-sidebar__list{list-style:none;margin:0;padding:0 .3rem .1rem;}
.admin-sidebar__list.is-collapsed{display:none;}

.admin-sidebar__item{margin:.12rem 0;}
.admin-sidebar__link-card{
  border-radius:14px;
  background:radial-gradient(circle at top left,rgba(56,189,248,.06),rgba(15,23,42,.98));
  border:1px solid rgba(15,23,42,1);overflow:hidden;transition:all .18s ease;
}
.admin-sidebar__link-card.is-active{
  border-color:var(--gdy-sidebar-accent-soft);
  background:#020617;
  box-shadow:0 0 0 1px rgba(56,189,248,.6);
}
.admin-sidebar__link{
  display:flex;align-items:center;justify-content:space-between;
  padding:.6rem .6rem;color:var(--gdy-sidebar-text);
  text-decoration:none;font-size:.82rem;
}
.admin-sidebar__link-main{display:flex;align-items:center;gap:.5rem;}
.admin-sidebar__link-icon{
  width:30px;height:30px;border-radius:11px;
  background:radial-gradient(circle at top,rgba(56,189,248,.28),rgba(15,23,42,1));
  display:flex;align-items:center;justify-content:center;
  color:var(--gdy-sidebar-accent-soft);font-size:.85rem;
}
.admin-sidebar__link-text{text-align:right;}
.admin-sidebar__link-label{font-size:.82rem;font-weight:500;}
.admin-sidebar__link-sub{font-size:.72rem;color:var(--gdy-sidebar-muted);}
.admin-sidebar__link-meta{display:flex;align-items:center;gap:.4rem;}
.admin-sidebar__badge{
  border-radius:999px;border:1px solid rgba(56,189,248,.7);
  padding:.05rem .45rem;font-size:.68rem;
  color:var(--gdy-sidebar-accent-soft);background:rgba(56,189,248,.08);
}
.admin-sidebar__badge--pill{
  border-color:var(--gdy-sidebar-success);color:#bbf7d0;
  background:rgba(34,197,94,.1);
}
.admin-sidebar__link-arrow{font-size:.72rem;color:var(--gdy-sidebar-muted);}

/* التذييل */
.admin-sidebar__footer{
  border-top:1px solid var(--gdy-sidebar-border);
  padding:.55rem .75rem .7rem;background:#020617;
}
.admin-sidebar__user{display:flex;align-items:center;gap:.55rem;margin-bottom:.4rem;}
.admin-sidebar__user-avatar{
  width:32px;height:32px;border-radius:999px;
  background:radial-gradient(circle at top,var(--gdy-sidebar-accent-soft),var(--gdy-sidebar-accent));
  display:flex;align-items:center;justify-content:center;color:#0b1120;overflow:hidden;
}
.admin-sidebar__user-avatar img{width:100%;height:100%;object-fit:cover;}
.admin-sidebar__user-info{text-align:right;}
.admin-sidebar__user-name{font-size:.8rem;font-weight:500;}
.admin-sidebar__user-role{font-size:.72rem;color:var(--gdy-sidebar-muted);}
.admin-sidebar__footer-actions{display:flex;gap:.35rem;}
.admin-sidebar__action-btn{
  width:32px;height:32px;border-radius:11px;
  border:1px solid var(--gdy-sidebar-border);
  background:#020617;color:var(--gdy-sidebar-text);
  display:flex;align-items:center;justify-content:center;
  font-size:.82rem;text-decoration:none;
}
.admin-sidebar__action-btn--danger{
  border-color:rgba(239,68,68,.85);color:#fecaca;
}

/* سكرول */
.admin-sidebar__body::-webkit-scrollbar{width:4px;}
.admin-sidebar__body::-webkit-scrollbar-thumb{background:rgba(55,65,81,1);border-radius:2px;}

/* الجوال: السايدبار تنزلق من اليمين */
@media (max-width: 991.98px) {
  .admin-sidebar {
    transform: translateX(100%);
    transition: transform .25s ease;
  }
  .admin-sidebar.is-open {
    transform: translateX(0);
  }
  .admin-sidebar__toggle {
    display:flex;
  }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const sidebar       = document.getElementById('adminSidebar');
  const toggleBtn     = document.getElementById('sidebarToggle');
  const searchInput   = document.getElementById('sidebarSearch');
  const searchResults = document.getElementById('searchResults');
  const darkToggle    = document.getElementById('darkModeToggle');
  const sectionHeaders = document.querySelectorAll('.admin-sidebar__section-header');
  const linkCards      = document.querySelectorAll('.admin-sidebar__link-card');

  // إظهار/إخفاء في الجوال
  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', function () {
      sidebar.classList.toggle('is-open');
    });
  }

  // طي/فتح الأقسام
  sectionHeaders.forEach(function (btn) {
    btn.addEventListener('click', function () {
      const key = btn.getAttribute('data-section');
      if (!key) return;
      const list = document.getElementById('section-' + key);
      if (!list) return;
      list.classList.toggle('is-collapsed');
      btn.classList.toggle('is-collapsed');
    });
  });

  // البحث
  if (searchInput && searchResults) {
    const allCards = Array.from(linkCards);

    searchInput.addEventListener('input', function () {
      const q = this.value.trim().toLowerCase();
      searchResults.innerHTML = '';

      if (!q) {
        searchResults.style.display = 'none';
        allCards.forEach(card => card.style.display = '');
        return;
      }

      allCards.forEach(card => (card.style.display = 'none'));
      const matches = [];
      allCards.forEach(card => {
        const data = (card.getAttribute('data-search') || '').toLowerCase();
        if (data.indexOf(q) !== -1) matches.push(card);
      });

      if (matches.length) {
        searchResults.style.display = 'block';
        matches.forEach(card => {
          const link = card.querySelector('a');
          if (!link) return;
          const labelEl = card.querySelector('.admin-sidebar__link-label');
          const label = labelEl ? labelEl.textContent.trim() : link.textContent.trim();

          const a = document.createElement('a');
          a.href = link.getAttribute('href');
          a.className = 'admin-sidebar__search-result-item';
          a.textContent = label;
          searchResults.appendChild(a);
        });
      } else {
        searchResults.style.display = 'block';
        const div = document.createElement('div');
        div.className = 'admin-sidebar__search-result-item';
        div.textContent = 'لا توجد نتائج مطابقة';
        searchResults.appendChild(div);
      }
    });

    document.addEventListener('click', function (e) {
      if (!searchResults.contains(e.target) && e.target !== searchInput) {
        searchResults.style.display = 'none';
      }
    });
  }

  // تبديل الوضع الليلي (يمكنك استخدام الكلاس godyar-dark في CSS العام)
  if (darkToggle) {
    darkToggle.addEventListener('click', function () {
      document.body.classList.toggle('godyar-dark');
    });
  }
});
</script>
