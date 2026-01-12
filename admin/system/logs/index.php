<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_admin_guard.php';
// admin/system/logs/index.php

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/auth.php';

use Godyar\Auth;

$currentPage = 'system_logs';
$pageTitle   = 'سجلات النظام';

if (!Auth::isLoggedIn()) {
    header('Location: ../../login.php');
    exit;
}

$pdo = gdy_pdo_safe();
if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

/* ==========================
   فلاتر + ترقيم صفحات
========================== */
$q          = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$actionF    = isset($_GET['action']) ? trim((string)$_GET['action']) : '';
$entityF    = isset($_GET['entity']) ? trim((string)$_GET['entity']) : '';
$userF      = isset($_GET['user']) ? trim((string)$_GET['user']) : '';
$dateFrom   = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
$dateTo     = isset($_GET['to']) ? trim((string)$_GET['to']) : '';

$page       = max(1, (int)($_GET['page'] ?? 1));
$perPageRaw = (int)($_GET['per_page'] ?? 25);
$allowedPP  = [25, 50, 100];
$perPage    = in_array($perPageRaw, $allowedPP, true) ? $perPageRaw : 25;
$offset     = ($page - 1) * $perPage;

$logs       = [];
$total      = 0;
$actions    = [];
$entities   = [];

if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'admin_logs'");
        if ($stmt && $stmt->fetchColumn()) {

            // قوائم سريعة للفلاتر (آخر 200 سجل فقط لتخفيف الحمل)
            try {
                $actions = $pdo->query("SELECT DISTINCT action FROM admin_logs ORDER BY action ASC LIMIT 200")->fetchAll(PDO::FETCH_COLUMN) ?: [];
            } catch (Throwable $e) { $actions = []; }

            try {
                $entities = $pdo->query("SELECT DISTINCT entity_type FROM admin_logs WHERE entity_type IS NOT NULL AND entity_type <> '' ORDER BY entity_type ASC LIMIT 200")->fetchAll(PDO::FETCH_COLUMN) ?: [];
            } catch (Throwable $e) { $entities = []; }

            // بناء WHERE ديناميكي
            $where  = [];
            $params = [];

            if ($q !== '') {
                $where[] = "(al.action LIKE :q OR al.entity_type LIKE :q OR al.details LIKE :q OR al.ip LIKE :q OR al.ip_address LIKE :q OR al.user_agent LIKE :q OR u.username LIKE :q OR u.name LIKE :q)";
                $params[':q'] = '%' . $q . '%';
            }

            if ($actionF !== '') {
                $where[] = "al.action = :action";
                $params[':action'] = $actionF;
            }

            if ($entityF !== '') {
                $where[] = "al.entity_type = :entity";
                $params[':entity'] = $entityF;
            }

            if ($userF !== '') {
                // لو رقم → user_id، غير كذا → بحث بالاسم/username
                if (ctype_digit($userF)) {
                    $where[] = "al.user_id = :user_id";
                    $params[':user_id'] = (int)$userF;
                } else {
                    $where[] = "(u.username LIKE :user_txt OR u.name LIKE :user_txt)";
                    $params[':user_txt'] = '%' . $userF . '%';
                }
            }

            // تواريخ (YYYY-MM-DD)
            if ($dateFrom !== '') {
                $where[] = "al.created_at >= :from";
                $params[':from'] = $dateFrom . " 00:00:00";
            }
            if ($dateTo !== '') {
                $where[] = "al.created_at <= :to";
                $params[':to'] = $dateTo . " 23:59:59";
            }

            $whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

            // العدد الكلي
            $countSql = "
                SELECT COUNT(*)
                FROM admin_logs al
                LEFT JOIN users u ON u.id = al.user_id
                $whereSql
            ";
            $c = $pdo->prepare($countSql);
            $c->execute($params);
            $total = (int)($c->fetchColumn() ?: 0);

            // السجلات
            $sql = "
                SELECT al.*, u.username, u.name
                FROM admin_logs al
                LEFT JOIN users u ON u.id = al.user_id
                $whereSql
                ORDER BY al.id DESC
                LIMIT :limit OFFSET :offset
            ";
            $stmt2 = $pdo->prepare($sql);

            foreach ($params as $k => $v) {
                $stmt2->bindValue($k, $v);
            }
            $stmt2->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt2->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt2->execute();
            $logs  = $stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (Throwable $e) {
        @error_log('[Godyar System Logs] ' . $e->getMessage());
    }
}

$totalPages = max(1, (int)ceil($total / max(1, $perPage)));
if ($page > $totalPages) { $page = $totalPages; }

function build_query(array $extra = []): string {
    $query = array_merge($_GET, $extra);
    foreach ($query as $k => $v) {
        if ($v === '' || $v === null) { unset($query[$k]); }
    }
    return '?' . http_build_query($query);
}

require_once __DIR__ . '/../../layout/header.php';
require_once __DIR__ . '/../../layout/sidebar.php';
?>
<style>
:root{
  --gdy-shell-max: 1200px;
  --gdy-gap: 16px;
  --gdy-sidebar-w: 0px;
}

/* ✅ منع أي تمدد أفقي */
html, body{ overflow-x:hidden; background:#020617; }

/* ✅ التصميم الموحد للعرض: حاوية داخلية لا تخرج من الشاشة */
.admin-content{
  width: 100%;
  max-width: var(--gdy-shell-max);
  margin: 0 auto;
  padding-left: var(--gdy-gap);
  padding-right: calc(var(--gdy-gap) + var(--gdy-sidebar-w)); /* RTL: sidebar غالباً يمين */
}

/* في الشاشات الصغيرة: لا نضيف إزاحة للسايدبار */
@media (max-width: 992px){
  .admin-content{ padding-right: var(--gdy-gap); }
}

/* بطاقات زجاجية */
.gdy-card{
  background: rgba(15,23,42,.92);
  border: 1px solid rgba(148,163,184,.18);
  border-radius: 16px;
  box-shadow: 0 18px 40px rgba(0,0,0,.35);
  backdrop-filter: blur(10px);
}
.gdy-card .card-body{ color:#e5e7eb; }

/* شريط الفلاتر */
.gdy-filter-bar{
  background: rgba(15,23,42,.75);
  border: 1px solid rgba(148,163,184,.18);
  border-radius: 16px;
  padding: 14px;
  margin-bottom: 14px;
}
.gdy-filter-bar .form-control,
.gdy-filter-bar .form-select{
  background: rgba(2,6,23,.9);
  color:#e5e7eb;
  border-color: rgba(148,163,184,.35);
}
.gdy-filter-bar .form-control:focus,
.gdy-filter-bar .form-select:focus{
  border-color: #0ea5e9;
  box-shadow: 0 0 0 3px rgba(14,165,233,.22);
}
.gdy-stat{
  display:flex; gap:10px; flex-wrap:wrap; margin-top:10px;
}
.gdy-pill{
  background: rgba(2,6,23,.65);
  border: 1px solid rgba(148,163,184,.22);
  color:#e5e7eb;
  border-radius: 999px;
  padding: 8px 12px;
  font-size:.8rem;
}
.gdy-pill b{ color:#38bdf8; }

/* جدول */
.table-responsive{ overflow:auto; }
.gdy-table{
  color:#e5e7eb;
  margin:0;
  min-width: 980px;
}
.gdy-table thead th{
  position: sticky;
  top: 0;
  background:#020617;
  z-index: 2;
  color:#e5e7eb;
  border-bottom: 1px solid rgba(148,163,184,.25);
  font-size: .8rem;
}
.gdy-table td{
  border-color: rgba(148,163,184,.16);
  vertical-align: middle;
  font-size: .82rem;
}
.gdy-table tr:hover td{ background: rgba(2,6,23,.45); }

.gdy-truncate{
  max-width: 420px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.gdy-code{
  background: rgba(2,6,23,.6);
  border: 1px solid rgba(148,163,184,.18);
  border-radius: 10px;
  padding: 4px 8px;
  display:inline-block;
  font-size:.78rem;
  color:#e2e8f0;
}
.gdy-entity{ color:#93c5fd; }

.gdy-btn-icon{
  width: 36px; height: 36px;
  border-radius: 10px;
  display:inline-flex; align-items:center; justify-content:center;
}

/* ترقيم الصفحات */
.gdy-pagination{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  flex-wrap:wrap;
  margin-top: 14px;
}
.gdy-page-mini{ color:#94a3b8; font-size:.85rem; }

/* مودال */
.gdy-modal .modal-content{
  background: rgba(15,23,42,.96);
  border: 1px solid rgba(148,163,184,.22);
  border-radius: 18px;
  color:#e5e7eb;
  backdrop-filter: blur(16px);
}
.gdy-modal .modal-header{ border-bottom: 1px solid rgba(148,163,184,.18); }
.gdy-modal .modal-footer{ border-top: 1px solid rgba(148,163,184,.18); }
.gdy-modal pre{
  background: rgba(2,6,23,.75);
  border: 1px solid rgba(148,163,184,.18);
  border-radius: 14px;
  padding: 12px;
  color:#e5e7eb;
  max-height: 40vh;
  overflow:auto;
}
</style>

<div class="admin-content py-4">
  <div class="gdy-page-header mb-3">
    <h1 class="h4 mb-1 text-white">سجلات النظام</h1>
    <p class="mb-0" style="color:#e5e7eb;">
      عرض العمليات والأحداث المسجّلة في <code>admin_logs</code> مع فلاتر وبحث ومعاينة تفصيلية.
    </p>
  </div>

  <div class="gdy-filter-bar">
    <form method="get" class="row g-2 align-items-end">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

      <div class="col-12 col-md-4">
        <label class="form-label small mb-1" style="color:#e5e7eb;">بحث عام</label>
        <input type="text" name="q" value="<?= h($q) ?>" class="form-control" placeholder="ابحث (action / entity / details / ip / user)...">
      </div>

      <div class="col-6 col-md-2">
        <label class="form-label small mb-1" style="color:#e5e7eb;">الإجراء</label>
        <select name="action" class="form-select">
          <option value="">الكل</option>
          <?php foreach ($actions as $a): ?>
            <option value="<?= h((string)$a) ?>" <?= $actionF === (string)$a ? 'selected' : '' ?>>
              <?= h((string)$a) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-6 col-md-2">
        <label class="form-label small mb-1" style="color:#e5e7eb;">الكيان</label>
        <select name="entity" class="form-select">
          <option value="">الكل</option>
          <?php foreach ($entities as $en): ?>
            <option value="<?= h((string)$en) ?>" <?= $entityF === (string)$en ? 'selected' : '' ?>>
              <?= h((string)$en) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-2">
        <label class="form-label small mb-1" style="color:#e5e7eb;">المستخدم</label>
        <input type="text" name="user" value="<?= h($userF) ?>" class="form-control" placeholder="ID أو اسم...">
      </div>

      <div class="col-6 col-md-1">
        <label class="form-label small mb-1" style="color:#e5e7eb;">من</label>
        <input type="date" name="from" value="<?= h($dateFrom) ?>" class="form-control">
      </div>

      <div class="col-6 col-md-1">
        <label class="form-label small mb-1" style="color:#e5e7eb;">إلى</label>
        <input type="date" name="to" value="<?= h($dateTo) ?>" class="form-control">
      </div>

      <div class="col-6 col-md-2">
        <label class="form-label small mb-1" style="color:#e5e7eb;">عدد السجلات</label>
        <select name="per_page" class="form-select">
          <?php foreach ([25,50,100] as $pp): ?>
            <option value="<?= (int)$pp ?>" <?= $perPage === (int)$pp ? 'selected' : '' ?>><?= (int)$pp ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-6 col-md-2 d-grid">
        <button type="submit" class="btn btn-primary">
          <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> تطبيق
        </button>
      </div>

      <div class="col-12 col-md-2 d-grid">
        <a href="index.php" class="btn btn-outline-light">
          <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> إعادة تعيين
        </a>
      </div>
    </form>

    <div class="gdy-stat">
      <span class="gdy-pill">الإجمالي: <b><?= (int)$total ?></b></span>
      <span class="gdy-pill">المعروض: <b><?= (int)count($logs) ?></b></span>
      <span class="gdy-pill">الصفحة: <b><?= (int)$page ?></b> / <b><?= (int)$totalPages ?></b></span>
    </div>
  </div>

  <div class="card gdy-card">
    <div class="card-body p-0">
      <?php if (empty($logs)): ?>
        <div class="p-3" style="color:#9ca3af;">
          لا توجد سجلات مطابقة للفلاتر الحالية، أو جدول <code>admin_logs</code> غير جاهز بعد.
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle gdy-table">
            <thead>
              <tr>
                <th style="width:64px;">#</th>
                <th style="width:150px;">التاريخ</th>
                <th style="width:140px;">المستخدم</th>
                <th style="width:170px;">الإجراء</th>
                <th style="width:180px;">الكيان</th>
                <th style="width:150px;">IP</th>
                <th>تفاصيل مختصرة</th>
                <th style="width:70px;">عرض</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($logs as $log): ?>
                <?php
                  $id = (int)($log['id'] ?? 0);
                  $uLabel = (string)($log['name'] ?? $log['username'] ?? '');
                  if ($uLabel === '') {
                    $uid = (string)($log['user_id'] ?? '');
                    $uLabel = $uid !== '' ? ('#' . $uid) : '-';
                  }
                  $ip = (string)($log['ip'] ?? '');
                  if ($ip === '') { $ip = (string)($log['ip_address'] ?? ''); }
                  if ($ip === '') { $ip = '-'; }

                  $details = (string)($log['details'] ?? '');
                  $detailsTrim = trim($details);
                  if ($detailsTrim === '') { $detailsTrim = '-'; }
                  $detailsShort = $detailsTrim;
                  if ($detailsTrim !== '-' && mb_strlen($detailsTrim, 'UTF-8') > 120) {
                    $detailsShort = mb_substr($detailsTrim, 0, 120, 'UTF-8') . '...';
                  }

                  $entityType = (string)($log['entity_type'] ?? '-');
                  $entityId   = (string)($log['entity_id'] ?? '-');
                  $action     = (string)($log['action'] ?? '-');
                  $ua         = (string)($log['user_agent'] ?? '');
                ?>
                <tr>
                  <td><span class="gdy-code"><?= $id ?></span></td>
                  <td><small><?= h($log['created_at'] ?? '-') ?></small></td>
                  <td><small><?= h($uLabel) ?></small></td>
                  <td><code class="small"><?= h($action) ?></code></td>
                  <td>
                    <small class="gdy-entity"><?= h($entityType) ?></small>
                    <small>#<?= h($entityId) ?></small>
                  </td>
                  <td><small><?= h($ip) ?></small></td>
                  <td class="text-start">
                    <span class="gdy-truncate" title="<?= h($detailsTrim) ?>">
                      <?= h($detailsShort) ?>
                    </span>
                  </td>
                  <td>
                    <button
                      type="button"
                      class="btn btn-outline-info btn-sm gdy-btn-icon js-log-view"
                      title="عرض التفاصيل"
                      data-bs-toggle="modal"
                      data-bs-target="#logModal"
                      data-id="<?= (int)$id ?>"
                      data-created="<?= h((string)($log['created_at'] ?? '-')) ?>"
                      data-user="<?= h($uLabel) ?>"
                      data-action="<?= h($action) ?>"
                      data-entity="<?= h($entityType) ?>"
                      data-entityid="<?= h($entityId) ?>"
                      data-ip="<?= h($ip) ?>"
                      data-ua="<?= h($ua) ?>"
                      data-details="<?= h($details) ?>"
                    >
                      <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="px-3 pb-3">
          <div class="gdy-pagination">
            <div class="gdy-page-mini">
              عرض <?= (int)count($logs) ?> من <?= (int)$total ?>.
            </div>

            <nav aria-label="ترقيم الصفحات">
              <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                  <a class="page-link" href="<?= h(build_query(['page' => max(1, $page - 1)])) ?>">السابق</a>
                </li>

                <?php
                  $start = max(1, $page - 2);
                  $end   = min($totalPages, $page + 2);

                  if ($start > 1) {
                    echo '<li class="page-item"><a class="page-link" href="' . h(build_query(['page' => 1])) . '">1</a></li>';
                    if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                  }

                  for ($p = $start; $p <= $end; $p++) {
                    $active = $p === $page ? 'active' : '';
                    echo '<li class="page-item ' . $active . '"><a class="page-link" href="' . h(build_query(['page' => $p])) . '">' . (int)$p . '</a></li>';
                  }

                  if ($end < $totalPages) {
                    if ($end < $totalPages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                    echo '<li class="page-item"><a class="page-link" href="' . h(build_query(['page' => $totalPages])) . '">' . (int)$totalPages . '</a></li>';
                  }
                ?>

                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                  <a class="page-link" href="<?= h(build_query(['page' => min($totalPages, $page + 1)])) ?>">التالي</a>
                </li>
              </ul>
            </nav>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- مودال عرض السجل -->
<div class="modal fade gdy-modal" id="logModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <svg class="gdy-icon me-2 text-info" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          تفاصيل السجل <span class="badge bg-secondary" id="m_id">#</span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <div class="gdy-pill w-100">التاريخ: <b id="m_created">-</b></div>
          </div>
          <div class="col-md-6">
            <div class="gdy-pill w-100">المستخدم: <b id="m_user">-</b></div>
          </div>
          <div class="col-md-6">
            <div class="gdy-pill w-100">الإجراء: <b id="m_action">-</b></div>
          </div>
          <div class="col-md-6">
            <div class="gdy-pill w-100">الكيان: <b id="m_entity">-</b> <span class="text-muted">#</span><b id="m_entityid">-</b></div>
          </div>
          <div class="col-md-6">
            <div class="gdy-pill w-100">IP: <b id="m_ip">-</b></div>
          </div>
          <div class="col-md-6">
            <div class="gdy-pill w-100">User-Agent:</div>
          </div>
          <div class="col-12">
            <pre class="mb-0" id="m_ua">-</pre>
          </div>
          <div class="col-12">
            <div class="gdy-pill w-100">Details:</div>
          </div>
          <div class="col-12">
            <pre class="mb-0" id="m_details">-</pre>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-light" id="m_copy">
          <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> نسخ التفاصيل
        </button>
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
          <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> إغلاق
        </button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // ✅ محاولة حساب عرض السايدبار (لو موجودة ومثبتة) لمنع التداخل
  function setSidebarW(){
    try{
      var sb = document.querySelector('.admin-sidebar, .sidebar, .gdy-sidebar, aside');
      if (!sb) return document.documentElement.style.setProperty('--gdy-sidebar-w', '0px');
      var cs = window.getComputedStyle(sb);
      var isFixed = cs.position === 'fixed' || cs.position === 'sticky';
      var w = (isFixed ? sb.getBoundingClientRect().width : 0);
      document.documentElement.style.setProperty('--gdy-sidebar-w', Math.max(0, Math.round(w)) + 'px');
    }catch(e){}
  }
  setSidebarW();
  window.addEventListener('resize', setSidebarW);

  // مودال التفاصيل
  var m = {
    id: document.getElementById('m_id'),
    created: document.getElementById('m_created'),
    user: document.getElementById('m_user'),
    action: document.getElementById('m_action'),
    entity: document.getElementById('m_entity'),
    entityid: document.getElementById('m_entityid'),
    ip: document.getElementById('m_ip'),
    ua: document.getElementById('m_ua'),
    details: document.getElementById('m_details'),
    copy: document.getElementById('m_copy')
  };

  var lastPayload = '';
  document.querySelectorAll('.js-log-view').forEach(function(btn){
    btn.addEventListener('click', function(){
      var d = this.dataset || {};
      m.id.textContent = '#' + (d.id || '');
      m.created.textContent = d.created || '-';
      m.user.textContent = d.user || '-';
      m.action.textContent = d.action || '-';
      m.entity.textContent = d.entity || '-';
      m.entityid.textContent = d.entityid || '-';
      m.ip.textContent = d.ip || '-';
      m.ua.textContent = d.ua || '-';
      m.details.textContent = d.details || '-';

      lastPayload =
        'ID: ' + (d.id || '') + '\n' +
        'Created: ' + (d.created || '') + '\n' +
        'User: ' + (d.user || '') + '\n' +
        'Action: ' + (d.action || '') + '\n' +
        'Entity: ' + (d.entity || '') + ' #' + (d.entityid || '') + '\n' +
        'IP: ' + (d.ip || '') + '\n' +
        'User-Agent: ' + (d.ua || '') + '\n' +
        'Details:\n' + (d.details || '');
    });
  });

  if (m.copy) {
    m.copy.addEventListener('click', function(){
      if (!lastPayload) return;
      navigator.clipboard.writeText(lastPayload).then(() => {
        var old = m.copy.innerHTML;
        m.copy.innerHTML = '<svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> تم النسخ';
        m.copy.classList.remove('btn-outline-light');
        m.copy.classList.add('btn-success');
        setTimeout(() => {
          m.copy.innerHTML = old;
          m.copy.classList.remove('btn-success');
          m.copy.classList.add('btn-outline-light');
        }, 1500);
      });
    });
  }
});
</script>

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>
