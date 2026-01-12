<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../plugins/loader.php';

/**
 * Admin Layout — Premium Sidebar + Link Cards
 * Drop-in: /admin/includes/admin_layout.php
 * Usage:
 *   require __DIR__.'/admin_layout.php';
 *   render_page(__('t_6dc6588082', 'العنوان'), '/admin/dashboard', function(){ ... });
 */

// جلسة
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

/* =========================
   CSRF (Admin)
   ========================= */
// NOTE:
// The admin guard defines generate_csrf_token()/verify_csrf_token() and verify_csrf()
// using the session key "_csrf_token".
// Some older admin layout code used a separate "admin_csrf_token", which causes
// all POST requests to fail with: "CSRF validation failed".
// We unify everything to the guard's token.

if (!function_exists('admin_generate_csrf_token')) {
    function admin_generate_csrf_token(): string {
        if (function_exists('generate_csrf_token')) {
            return (string)generate_csrf_token();
        }
        if (session_status() === PHP_SESSION_NONE) @session_start();
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['_csrf_token'];
    }
}

if (!function_exists('admin_verify_csrf_token')) {
    function admin_verify_csrf_token(string $token): bool {
        if (function_exists('verify_csrf_token')) {
            return (bool)verify_csrf_token($token);
        }
        if (session_status() === PHP_SESSION_NONE) @session_start();
        return isset($_SESSION['_csrf_token']) && hash_equals((string)$_SESSION['_csrf_token'], (string)$token);
    }
}

/**
 * حقل CSRF للنماذج (Admin)
 */
if (!function_exists('csrf_field')) {
    function csrf_field(): void {
        // Must match verify_csrf() in admin/_admin_guard.php (field name: csrf_token)
        // and the shared token generator.
        $t = function_exists('csrf_token') ? (string)csrf_token() : admin_generate_csrf_token();
        echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '">';
    }
}

/* =========================
   Flash messages
   ========================= */
if (!function_exists('admin_add_flash_message')) {
    function admin_add_flash_message(string $type, string $message): void {
        if (session_status() === PHP_SESSION_NONE) @session_start();
        // PHP 7.3 and older do not support ??= (null coalescing assignment)
        if (!isset($_SESSION['admin_flash_messages']) || !is_array($_SESSION['admin_flash_messages'])) {
            $_SESSION['admin_flash_messages'] = [];
        }
        $_SESSION['admin_flash_messages'][] = [
            'type' => $type,
            'message' => $message,
            'timestamp' => time(),
        ];
    }
}

if (!function_exists('admin_display_flash_messages')) {
    function admin_display_flash_messages(): void {
        if (session_status() === PHP_SESSION_NONE) @session_start();
        if (empty($_SESSION['admin_flash_messages'])) return;

        $types = [
            'success' => __('t_04c0c5b50c', 'نجاح'),
            'error' => __('t_dc5b8b3a79', 'خطأ'),
            'warning' => __('t_b34a41530b', 'تحذير'),
            'info' => __('t_bfecd1ea74', 'معلومة'),
        ];

        foreach ($_SESSION['admin_flash_messages'] as $msg) {
            $type  = preg_replace('/[^a-z]/i', '', (string)($msg['type'] ?? 'info'));
            $title = $types[$type] ?? $type;
            $text  = htmlspecialchars((string)($msg['message'] ?? ''), ENT_QUOTES, 'UTF-8');

            echo "<div class='alert alert-{$type} d-flex align-items-start justify-content-between gap-2' role='alert'>"
               . "<div><strong>{$title}:</strong> {$text}</div>"
               . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>"
               . "</div>";
        }
        unset($_SESSION['admin_flash_messages']);
    }
}

/* =========================
   Component helper
   ========================= */
if (!function_exists('admin_render_component')) {
    function admin_render_component(string $name, array $data = []): void {
        $file = __DIR__ . "/components/{$name}.php";
        if (is_file($file)) {
            extract($data, EXTR_SKIP);
            include $file;
        }
    }
}

/* =========================
   Escaper
   ========================= */
if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

/* =========================
   Base URLs
   ========================= */
$__base = defined('GODYAR_BASE_URL') ? rtrim((string)GODYAR_BASE_URL, '/') : '';
$SITE_BASE  = $__base;
$ADMIN_BASE = $__base . '/admin';

/* =========================
   Menu
   ========================= */
$MENU_GROUPS = [
  [
    'label' => __('home'),
    'items' => [
      ['icon'=>'gauge-high','text'=>__('dashboard'),'href'=>"$ADMIN_BASE/"],
    ]
  ],
  [
    'label' => __('content'),
    'items' => [
      ['icon'=>'newspaper','text'=>__('news'),'href'=>"$ADMIN_BASE/news", 'perm'=>'posts.view'],
      ['icon'=>'circle-plus','text'=>__('add_news'),'href'=>"$ADMIN_BASE/news/create", 'perm'=>'posts.create'],
      ['icon'=>'tags','text'=>__('categories'),'href'=>"$ADMIN_BASE/categories", 'perm'=>'categories.view'],
      ['icon'=>'hashtag','text'=>__('tags'),'href'=>"$ADMIN_BASE/tags", 'perm'=>'categories.view'],
      ['icon'=>'images','text'=>__('media_library'),'href'=>"$ADMIN_BASE/media", 'perm'=>'posts.edit'],
      ['icon'=>'sliders','text'=>__('slider'),'href'=>"$ADMIN_BASE/sliders", 'perm'=>'posts.edit'],
      ['icon'=>'bullhorn','text'=>__('ads'),'href'=>"$ADMIN_BASE/ads/index.php", 'perm'=>'ads.manage'],
      ['icon'=>'book','text'=>__('glossary'),'href'=>"$ADMIN_BASE/glossary/index.php", 'perm'=>'glossary.manage'],
      ['icon'=>'pen-fancy','text'=>__('opinion_writers'),'href'=>"$ADMIN_BASE/opinion_authors/index.php", 'perm'=>'opinion_authors.manage'],
    ]
  ],
  [
    'label' => __('administration'),
    'items' => [
      ['icon'=>'users','text'=>__('team'),'href'=>"$ADMIN_BASE/team/index.php", 'perm'=>'team.manage'],
      ['icon'=>'envelope','text'=>__('contact_messages'),'href'=>"$ADMIN_BASE/contact/index.php", 'perm'=>'contact.manage'],
      ['icon'=>'user-gear','text'=>__('users'),'href'=>"$ADMIN_BASE/users", 'perm'=>'manage_users'],
      ['icon'=>'user-shield','text'=>__('roles'),'href'=>"$ADMIN_BASE/roles/index.php", 'perm'=>'manage_roles'],
      ['icon'=>'share-nodes','text'=>__('social_media'),'href'=>"$ADMIN_BASE/social", 'perm'=>'settings.manage'],
      ['icon'=>'database','text'=>__('database'),'href'=>"$ADMIN_BASE/db", 'perm'=>'settings.manage'],
      ['icon'=>'broom','text'=>__('maintenance'),'href'=>"$ADMIN_BASE/maintenance", 'perm'=>'settings.manage'],
      ['icon'=>'heart-pulse','text'=>__('system_health'),'href'=>"$ADMIN_BASE/system/health", 'perm'=>'manage_security'],
      ['icon'=>'chart-line','text'=>__('analytics'),'href'=>"$ADMIN_BASE/reports", 'perm'=>'logs.view'],
      ['icon'=>'puzzle-piece','text'=>__('plugins'),'href'=>"$ADMIN_BASE/plugins", 'perm'=>'manage_plugins'],
      ['icon'=>'gear','text'=>__('settings'),'href'=>"$ADMIN_BASE/settings", 'perm'=>'manage_settings'],
      ['icon'=>'bolt','text'=>__('cache_settings'),'href'=>"$ADMIN_BASE/settings/cache.php", 'perm'=>'manage_settings'],
    ]
  ],
];

$EXTRA = [
  ['icon'=>'house','text'=>__('view_site'),'href'=>"$SITE_BASE/", 'target'=>'_blank'],
  ['icon'=>'right-from-bracket','text'=>__('logout'),'href'=>"$ADMIN_BASE/logout"],
];

/**
 * Main render
 */
function render_page(string $title, string $activeHref, callable $contentCb): void {
    global $MENU_GROUPS, $EXTRA, $ADMIN_BASE;

    if (session_status() === PHP_SESSION_NONE) @session_start();

    $csrfToken = admin_generate_csrf_token();

    $u = $_SESSION['user'] ?? ['name'=>'Administrator','role'=>'admin'];
    $uName = (string)($u['name'] ?? $u['username'] ?? $u['email'] ?? 'Administrator');
    $uRole = (string)($u['role'] ?? 'admin');
    $uInitial = mb_substr($uName, 0, 1, 'UTF-8') ?: 'A';

    ?>
<!doctype html>
<html lang="<?= htmlspecialchars((string)(function_exists('current_lang') ? current_lang() : (string)($_SESSION['lang'] ?? 'ar')), ENT_QUOTES, 'UTF-8') ?>" dir="<?= ((function_exists('current_lang') ? current_lang() : (string)($_SESSION['lang'] ?? 'ar')) === 'ar' ? 'rtl' : 'ltr') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= h($csrfToken) ?>">
  <title><?= h($title) ?> — Godyar Admin</title>

  <link href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css" rel="stylesheet">
  <style>
  :root{
    --bg:#0b0a19;
    --panel:#121028;
    --panel2:#171433;
    --text:#eef2ff;
    --muted:#bfb9de;
    --brand:#7c4dff;
    --brand2:#b388ff;
    --green:#22c55e;
    --stroke:#ffffff26;
    --sw:300px;
    --sw-mini:96px;
  }
  *,*::before,*::after{ box-sizing:border-box; }
  html,body{ height:100%; }
  body{
    margin:0;
    min-height:100vh;
    background:radial-gradient(circle at top, #020617, #020617 55%, #020617 100%);
    font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
    color:var(--text);
    display:flex;
    flex-direction:row-reverse;
    overflow-x:hidden;
  }
  .sidebar{
    width:var(--sw);
    min-height:100vh;
    background:linear-gradient(160deg,#020617,#111827 40%,#0f172a 100%);
    border-left:1px solid var(--stroke);
    padding:1rem 0.75rem;
    position:sticky;
    top:0;
    overflow-y:auto;
    flex-shrink:0;
  }
  .sidebar.mini{ width:var(--sw-mini); }
  .main{
    flex:1;
    min-width:0;
    min-height:100vh;
    display:flex;
    flex-direction:column;
    overflow-x:hidden;
  }
  .header{
    position:sticky; top:0; z-index:20;
    padding:0.75rem 1.25rem;
    display:flex; align-items:center; justify-content:space-between; gap:0.75rem;
    background:linear-gradient(120deg,#020617,#020617 40%,#0b1120 100%);
    border-bottom:1px solid var(--stroke);
  }
  .content{
    flex:1;
    padding:1.25rem 1.5rem;
    overflow-x:auto;
  }
  .footer{
    padding:0.75rem 1.5rem 1rem;
    font-size:0.85rem;
    color:var(--muted);
    border-top:1px solid var(--stroke);
    display:flex; align-items:center; justify-content:space-between; gap:0.5rem;
  }
  .sb-brand{ display:flex; align-items:center; gap:0.6rem; margin-bottom:0.8rem; padding:0.35rem 0.5rem; }
  .sb-logo{
    width:32px;height:32px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;
    background:radial-gradient(circle at 20% 0,#4f46e5,#7c3aed);
    box-shadow:0 0 0 1px rgba(148,163,184,.4);
    font-weight:700;font-size:0.95rem;
  }
  .sb-brand-title{ font-size:0.95rem; font-weight:600; }
  .sb-brand-sub{ font-size:0.75rem; color:var(--muted); }
  .sb-search{ margin-bottom:0.75rem; }
  .sb-search-input{
    width:100%; border-radius:999px;
    border:1px solid rgba(148,163,184,.35);
    background:rgba(15,23,42,.9);
    color:var(--text);
    font-size:0.8rem;
    padding-inline:0.9rem;
    padding-block:0.35rem;
  }
  .sb-search-input::placeholder{ color:rgba(148,163,184,.8); }
  .sb-menu{ list-style:none; margin:0; padding:0; }
  .sb-group{ margin-bottom:0.75rem; }
  .sb-title{
    font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;
    color:rgba(148,163,184,.9);
    margin-bottom:0.3rem;
    padding-inline:0.4rem;
  }
  .sb-group ul{ list-style:none; margin:0; padding:0; }
  .sb-item{
    display:flex; align-items:center; gap:0.6rem;
    padding:0.4rem 0.6rem;
    border-radius:10px;
    text-decoration:none;
    color:var(--muted);
    font-size:0.82rem;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }
  .sb-item-icon{
    width:28px;height:28px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;
    background:rgba(15,23,42,.9);
    border:1px solid rgba(148,163,184,.3);
    font-size:0.85rem;
  }
  .sb-item span{ flex:1; }
  .sb-item:hover{ background:rgba(37,99,235,.18); color:var(--text); }
  .sb-item.active{
    background:linear-gradient(120deg,#4f46e5,#7c3aed);
    color:#f9fafb;
  }
  .sb-item.active .sb-item-icon{ background:rgba(15,23,42,.95); }
  .sb-extra{ margin-top:0.75rem; padding-top:0.5rem; border-top:1px dashed rgba(148,163,184,.4); }
  .header-left{ display:flex; align-items:center; gap:0.75rem; }
  .header-title{ font-size:0.95rem; font-weight:600; }
  .header-right{ display:flex; align-items:center; gap:0.5rem; }
  .btn-icon{
    border:none;border-radius:999px;width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;
    font-size:0.9rem;background:rgba(15,23,42,.9);color:var(--muted);
  }
  .btn-icon:hover{ background:rgba(30,64,175,.9); color:#f9fafb; }
  .user-pill{
    display:flex; align-items:center; gap:0.5rem;
    padding:0.2rem 0.6rem;border-radius:999px;
    background:rgba(15,23,42,.95);
    border:1px solid rgba(148,163,184,.4);
  }
  .user-avatar{
    width:28px;height:28px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;
    background:linear-gradient(135deg,#22c55e,#16a34a);
    font-size:0.85rem;font-weight:600;
  }
  .user-meta{ display:flex; flex-direction:column; }
  .user-name{ font-size:0.8rem; font-weight:500; }
  .user-role{ font-size:0.7rem; color:var(--muted); }
  .footer a{ color:rgba(226,232,240,.85); text-decoration:none; margin-inline:8px; }
  .footer a:hover{ color:#fff; text-decoration:underline; }

  @media (max-width:991.98px){
    body{ display:block; }
    .sidebar{
      position:fixed;
      inset-block-start:0;
      inset-inline-end:0;
      height:100vh;
      max-width:80vw;
      width:260px;
      transform:translateX(100%);
      transition:transform .25s ease-out;
      z-index:1040;
      box-shadow:0 0 0 9999px rgba(15,23,42,.65);
    }
    .sidebar.open{ transform:translateX(0); }
    .header{ position:sticky; top:0; }
  }
    .admin-sidebar__pill{display:inline-flex;align-items:center;justify-content:center;min-width:42px;height:30px;padding:0 10px;border-radius:999px;border:1px solid rgba(255,255,255,0.18);color:#fff;text-decoration:none;font-weight:700;font-size:12px;opacity:.85}
    .admin-sidebar__pill:hover{opacity:1}
    .admin-sidebar__pill.is-active{background:rgba(255,255,255,0.12);opacity:1}

  </style>

  <?php if (function_exists('do_action')) { do_action('admin_head'); } ?>
</head>

<body>

<aside id="sidebar" class="sidebar" aria-label="<?= h(__('t_ba8b234fb4', 'القائمة الجانبية')) ?>">
  <div class="sb-brand">
    <div class="sb-logo">G</div>
    <div>
      <div class="sb-brand-title"><?= h(__("admin_panel")) ?></div>
      <div class="sb-brand-sub"><?= h(__("admin_panel_subtitle")) ?></div>
    </div>
  </div>

  <div class="sb-search">
    <input id="globalSearch" type="search" class="sb-search-input"
           placeholder="<?= h(__("quick_menu_search")) ?>"
           oninput="window.__filterMenu && window.__filterMenu(this.value)">
  </div>

  <ul class="sb-menu">
    <?php foreach ($MENU_GROUPS as $group): ?>
      <li class="sb-group">
        <div class="sb-title"><?= h($group['label']) ?></div>
        <ul>
          <?php foreach ($group['items'] as $item):
            $perm = (string)($item['perm'] ?? '');
            if ($perm !== '') {
              $ok = true;
              if (class_exists('Godyar\\Auth') && method_exists('Godyar\\Auth','hasPermission')) {
                try { $ok = \Godyar\Auth::hasPermission($perm); } catch (\Throwable $e) { $ok = false; }
              }
              if (!$ok) continue;
            }
            $href = (string)($item['href'] ?? '#');
            $icon = (string)($item['icon'] ?? 'circle');
            $text = (string)($item['text'] ?? '');
            $isActive = ($activeHref === $href);
          ?>
            <li>
              <a href="<?= h($href) ?>" class="sb-item<?= $isActive ? ' active' : '' ?>">
                <span class="sb-item-icon"><svg class="gdy-icon h($icon) ?>" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></span>
                <span><?= h($text) ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </li>
    <?php endforeach; ?>

    <li class="sb-group sb-extra">
      <ul>
        <?php foreach ($EXTRA as $item):
          $href   = (string)($item['href']   ?? '#');
          $icon   = (string)($item['icon']   ?? 'circle');
          $text   = (string)($item['text']   ?? '');
          $target = (string)($item['target'] ?? '');
          $targetAttr = $target !== '' ? ' target="' . h($target) . '" rel="noopener noreferrer"' : '';
        ?>
          <li>
            <a href="<?= h($href) ?>" class="sb-item"<?= $targetAttr ?>>
              <span class="sb-item-icon"><svg class="gdy-icon h($icon) ?>" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></span>
              <span><?= h($text) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </li>
  </ul>
</aside>

<div class="main" id="mainContent">
  <header class="header">
    <div class="header-left">
      <button id="btnToggleSB" class="btn-icon d-inline-flex d-lg-none" type="button"
              aria-label="<?= h(__('t_42ed435ec8', 'تبديل القائمة الجانبية')) ?>" aria-expanded="false">
        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
      </button>
      <div class="header-title"><?= h($title) ?></div>
    </div>

    <div class="header-right">
      <button id="btnMiniSB" class="btn-icon d-none d-lg-inline-flex" type="button"
              aria-label="<?= h(__('t_76b1868c94', 'تصغير / تكبير القائمة الجانبية')) ?>" aria-expanded="true">
        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
      </button>

      <div class="user-pill">
        <div class="user-avatar"><?= h($uInitial) ?></div>
        <div class="user-meta">
          <div class="user-name"><?= h($uName) ?></div>
          <div class="user-role"><?= h($uRole) ?></div>
        </div>
      </div>
    </div>
  </header>

  <main class="content">
    <?php admin_display_flash_messages(); ?>
    <?php $contentCb(); ?>
  </main>

  <footer class="footer">
    <div>© <?= date('Y') ?> Godyar Pro — <?= h(__("all_rights_reserved")) ?></div>
    <div>
      <a href="<?= h($ADMIN_BASE) ?>/settings/branding"><?= h(__("branding")) ?></a>
      <a href="<?= h($ADMIN_BASE) ?>/settings/theme"><?= h(__("theme")) ?></a>
      <a href="<?= h($ADMIN_BASE) ?>/reports"><?= h(__("reports")) ?></a>
    </div>
  </footer>
</div>

<script>
(function(){
  const sb = document.getElementById('sidebar');
  const btnMini = document.getElementById('btnMiniSB');
  const btnToggle = document.getElementById('btnToggleSB');
  const globalSearch = document.getElementById('globalSearch');

  // mini state
  const KEY_MINI = 'gdy_sb_mini';
  const getMini = () => localStorage.getItem(KEY_MINI) === '1';
  const setMini = (v) => localStorage.setItem(KEY_MINI, v ? '1' : '0');

  function applyMini(){
    const mini = getMini();
    if (mini) {
      sb.classList.add('mini');
      document.documentElement.style.setProperty('--sw', 'var(--sw-mini)');
    } else {
      sb.classList.remove('mini');
      document.documentElement.style.setProperty('--sw', '300px');
    }
    btnMini && btnMini.setAttribute('aria-expanded', (!mini).toString());
  }

  btnMini && btnMini.addEventListener('click', () => {
    setMini(!getMini());
    applyMini();
  });

  // mobile open/close
  btnToggle && btnToggle.addEventListener('click', () => {
    const isOpen = sb.classList.toggle('open');
    btnToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  });

  document.addEventListener('click', (e) => {
    if (window.innerWidth < 992 && sb.classList.contains('open')) {
      const t = e.target;
      if (!sb.contains(t) && btnToggle && !btnToggle.contains(t)) {
        sb.classList.remove('open');
        btnToggle.setAttribute('aria-expanded', 'false');
      }
    }
  });

  // debounce
  function debounce(fn, wait){
    let t;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(null, args), wait);
    };
  }

  const filterMenu = debounce(function(q){
    q = (q || '').trim().toLowerCase();
    document.querySelectorAll('.sb-group').forEach(group => {
      let any = false;
      group.querySelectorAll('a.sb-item').forEach(a => {
        const li = a.closest('li');
        const text = (a.textContent || '').toLowerCase();
        const show = (!q || text.includes(q));
        if (li) li.style.display = show ? '' : 'none';
        if (show) any = true;
      });
      const title = group.querySelector('.sb-title');
      if (title) title.style.display = any ? '' : 'none';
    });
  }, 200);

  window.__filterMenu = filterMenu;

  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
      e.preventDefault();
      globalSearch && globalSearch.focus();
    }
    if (e.key === 'Escape' && window.innerWidth < 992 && sb.classList.contains('open')) {
      sb.classList.remove('open');
      btnToggle && btnToggle.setAttribute('aria-expanded', 'false');
    }
  });

  applyMini();
})();
</script>

<script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<?php if (function_exists('do_action')) { do_action('admin_footer'); } ?>
</body>
</html>
<?php
} // end render_page
