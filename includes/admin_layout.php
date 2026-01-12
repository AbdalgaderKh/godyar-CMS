<?php
declare(strict_types=1);
/**
 * Shared Admin Layout
 */
require_once __DIR__.'/bootstrap.php';

$SITE_BASE  = '/godyar';
$ADMIN_BASE = '/godyar/admin';

$menu = [
  [ 'icon'=>'gauge','text'=>'لوحة التحكم','href'=> $ADMIN_BASE.'/dashboard' ],
  [ 'icon'=>'newspaper','text'=>'الأخبار','href'=> $ADMIN_BASE.'/news' ],
  [ 'icon'=>'rss','text'=>'مصادر RSS','href'=> $ADMIN_BASE.'/feeds' ],
  [ 'icon'=>'plus-circle','text'=>'إضافة خبر','href'=> $ADMIN_BASE.'/news/create' ],
  [ 'icon'=>'tags','text'=>'التصنيفات','href'=> $ADMIN_BASE.'/categories' ],
  [ 'icon'=>'hashtag','text'=>'الوسوم','href'=> $ADMIN_BASE.'/tags' ],
  [ 'icon'=>'images','text'=>'المكتبة','href'=> $ADMIN_BASE.'/media' ],
  [ 'icon'=>'sliders-h','text'=>'السلايدر','href'=> $ADMIN_BASE.'/sliders' ],
  [ 'icon'=>'ad','text'=>'الإعلانات','href'=> $ADMIN_BASE.'/ads' ],
  [ 'icon'=>'chart-line','text'=>'التحليلات','href'=> $ADMIN_BASE.'/reports' ],
  [ 'icon'=>'users','text'=>'فريق العمل','href'=> $ADMIN_BASE.'/team' ],
  [ 'icon'=>'user-group','text'=>'المستخدمون','href'=> $ADMIN_BASE.'/users' ],
  [ 'icon'=>'share-alt','text'=>'التواصل الاجتماعي','href'=> $ADMIN_BASE.'/social' ],
  [ 'icon'=>'envelope','text'=>'الرسائل','href'=> $ADMIN_BASE.'/inbox' ],
  [ 'icon'=>'database','text'=>'قاعدة البيانات','href'=> $ADMIN_BASE.'/db' ],
  [ 'icon'=>'broom','text'=>'الصيانة','href'=> $ADMIN_BASE.'/maintenance' ],
  [ 'icon'=>'heart-pulse','text'=>'حالة النظام','href'=> $ADMIN_BASE.'/system/health' ],
  [ 'icon'=>'sliders','text'=>'الإعدادات','href'=> $ADMIN_BASE.'/settings' ],
];

function render_page(string $title, string $activeHref, callable $contentCb): void {
  $u = $_SESSION['user'] ?? ['name'=>'Admin','role'=>'admin'];
  $uInitial = mb_substr($u['name']??'A',0,1,'UTF-8') ?: 'A';
  $currentPath = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '', '/');
  $ADMIN_BASE = '/godyar/admin';
  global $menu;
  ?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?></title>
<style>
html,body{margin:0;padding:0}
:root{
  --primary:#733EC0;--primary-2:#9d70e0;--ink:#e2e8f0;
  --sidebar:#1a1230dd;--header:#1c1434cc;--card:#241a40aa;
  --sidebar-width:300px;--header-height:64px;
}
*{box-sizing:border-box}
body{
  font-family:'Tajawal','Segoe UI',system-ui,sans-serif;
  background:
    radial-gradient(900px 480px at 110% -10%, rgba(115,62,192,.25), transparent 60%),
    radial-gradient(700px 420px at -10% 110%, rgba(157,112,224,.18), transparent 55%),
    #151026;
  color:var(--ink); min-height:100vh; overflow-x:hidden;
}
/* Sidebar */
.sidebar{position:fixed;top:0;right:0;bottom:0;margin:0!important;width:var(--sidebar-width);
  background:var(--sidebar);backdrop-filter:blur(14px);border-left:1px solid #ffffff22;z-index:1000;overflow-y:auto;
  box-shadow:0 0 60px rgba(0,0,0,.35), inset 1px 0 0 rgba(255,255,255,.06);}
.brand{display:flex;align-items:center;gap:.9rem;padding:1rem 1.25rem;border-bottom:1px solid #ffffff1f}
.brand-badge{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--primary),var(--primary-2));box-shadow:0 12px 26px rgba(115,62,192,.45)}
.brand .title{line-height:1}.brand .title .main{font-weight:800}.brand .title .sub{font-size:.8rem;color:#d9cdf6}
.nav-list{padding:10px 10px 14px}
.nav-item{display:flex;align-items:center;gap:.65rem;padding:.85rem 1rem;margin:.4rem .35rem;background:rgba(255,255,255,.04);border:1px solid #ffffff20;border-radius:14px;transition:.18s ease;position:relative;overflow:hidden}
.nav-item i{width:22px;text-align:center;color:#d1c4e9}.nav-item .badge{margin-right:auto;background:linear-gradient(135deg,var(--primary),var(--primary-2))}
.nav-item:hover{transform:translateY(-3px);box-shadow:0 14px 28px rgba(115,62,192,.28)}
.nav-item.active{background:linear-gradient(135deg, rgba(115,62,192,.28), rgba(157,112,224,.20));border-color:#a784ff66}
/* Main/header/footer */
.main{margin-right:var(--sidebar-width);min-height:100vh;display:flex;flex-direction:column}
.header{height:var(--header-height);background:var(--header);backdrop-filter:blur(10px);border-bottom:1px solid #ffffff1f;display:flex;align-items:center;justify-content:space-between;padding:0 1rem;position:sticky;top:0;z-index:900}
.user-chip{display:flex;align-items:center;gap:.65rem;padding:.35rem .7rem;border:1px solid #ffffff24;border-radius:999px;background:#ffffff12}
.user-avatar{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;background:linear-gradient(135deg,var(--primary),var(--primary-2))}
.header-actions .btn-icon{width:40px;height:40px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;border:1px solid #ffffff24;background:#ffffff14;color:#fff;transition:.18s ease}
.header-actions .btn-icon:hover{transform:translateY(-2px);box-shadow:0 10px 22px rgba(0,0,0,.28)}
.content{flex:1;padding:18px}
.card{background:var(--card);border:1px solid #ffffff22;border-radius:16px;box-shadow:0 14px 32px rgba(0,0,0,.28)}
.footer{margin-top:auto;border-top:1px solid #ffffff1e;background:#1b1430cc;backdrop-filter:blur(10px);padding:.9rem 1rem;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.footer .links a{color:#d9cdf6;margin-inline-start:12px}
/* Mobile */
@media (max-width: 992px){
  .sidebar{transform:translateX(100%);transition:transform .25s ease}
  .sidebar.open{transform:translateX(0)}
  .main{margin-right:0}
}
</style>
</head>
<body>
<aside class="sidebar" id="sidebar">
  <div class="brand">
    <div class="brand-badge"><svg class="gdy-icon text-white" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></div>
    <div class="title"><div class="main">Godyar Pro</div><div class="sub">لوحة التحكم</div></div>
  </div>
  <nav class="nav-list">
    <?php foreach ($menu as $it): $href=rtrim($it['href'],'/'); $active = ($href === $currentPath)?'active':''; ?>
      <a class="nav-item <?= $active ?>" href="<?= h($it['href']) ?>">
        <svg class="gdy-icon h($it['icon']) ?>" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><span><?= h($it['text']) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
</aside>

<div class="main">
  <header class="header">
    <div class="d-flex align-items-center gap-2">
      <button class="btn-icon btn btn-sm d-lg-none" id="toggleSidebar" title="القائمة"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></button>
      <div class="user-chip">
        <div class="user-avatar"><?= h($uInitial) ?></div>
        <div><div class="fw-semibold"><?= h($u['name'] ?? 'Admin') ?></div><div class="text-white-50 small"><?= h($u['role'] ?? 'admin') ?></div></div>
      </div>
    </div>
    <div class="header-actions d-flex align-items-center gap-2">
      <a class="btn-icon" href="<?= $ADMIN_BASE ?>/settings" title="الإعدادات"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></a>
      <a class="btn-icon" href="<?= $ADMIN_BASE ?>/inbox" title="الرسائل"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></a>
      <a class="btn-icon" href="<?= $ADMIN_BASE ?>/logout" title="خروج"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#logout"></use></svg></a>
    </div>
  </header>

  <main class="content">
    <?php $contentCb(); ?>
  </main>

  <footer class="footer">
    <div class="text-white-50">© <?= date('Y') ?> Godyar Pro — جميع الحقوق محفوظة</div>
    <div class="links">
      <a href="<?= $ADMIN_BASE ?>/settings">الإعدادات</a>
      <a href="<?= $ADMIN_BASE ?>/reports">التقارير</a>
    </div>
  </footer>
</div>

<script>
document.getElementById('toggleSidebar')?.addEventListener('click', ()=>{
  document.getElementById('sidebar')?.classList.toggle('open');
});
</script>
</body>
</html>
<?php } // render_page
