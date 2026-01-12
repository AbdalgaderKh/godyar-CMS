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

/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo 'DB not available';
    exit;
}

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$target = (string)($_GET['target'] ?? ($_GET['field'] ?? 'content'));
$target = in_array($target, ['content','featured'], true) ? $target : 'content';
$perPage = 24;
$offset = ($page - 1) * $perPage;

$tableExists = false;
try {
    $check = $pdo->query("SHOW TABLES LIKE 'media'");
    $tableExists = (bool)($check && $check->fetchColumn());
} catch (Throwable $e) {
    $tableExists = false;
}

$items = [];
$total = 0;

if ($tableExists) {
    $where = " WHERE 1=1 ";
    $params = [];
    if ($q !== '') {
        $where .= " AND (file_name LIKE :q OR file_path LIKE :q) ";
        $params[':q'] = '%' . $q . '%';
    }
    // Images only for picker
    $where .= " AND (file_type LIKE 'image/%' OR file_type LIKE 'application/octet-stream') ";

    $countSql = "SELECT COUNT(*) FROM media {$where}";
    $countStmt = $pdo->prepare($countSql);
    foreach ($params as $k => $v) $countStmt->bindValue($k, $v, PDO::PARAM_STR);
    $countStmt->execute();
    $total = (int)$countStmt->fetchColumn();

    $sql = "SELECT id, file_name, file_path, file_type, file_size, created_at
            FROM media {$where}
            ORDER BY id DESC
            LIMIT :lim OFFSET :off";
    $st = $pdo->prepare($sql);
    foreach ($params as $k => $v) $st->bindValue($k, $v, PDO::PARAM_STR);
    $st->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $st->bindValue(':off', $offset, PDO::PARAM_INT);
    $st->execute();
    $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function h2(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function guess_alt(string $filename): string {
    $name = preg_replace('~\.[a-z0-9]{2,5}$~i', '', $filename);
    $name = str_replace(['_', '-', '+'], ' ', (string)$name);
    $name = preg_replace('~\s+~u', ' ', (string)$name);
    return trim((string)$name);
}

$csrf = function_exists('csrf_token') ? (string)csrf_token() : '';
$totalPages = ($perPage > 0) ? (int)ceil($total / $perPage) : 1;
?><!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= h2(__('t_06dd6988d0','مكتبة الوسائط')) ?></title>
  <style>
    :root{ --bg:#0b1220; --card:#0f172a; --muted:#94a3b8; --bd:rgba(148,163,184,.25); --acc:#38bdf8; }
    html,body{height:100%}
    body{margin:0;background:var(--bg);color:#e5e7eb;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial; overflow:hidden}
    .top{display:flex;gap:.6rem;align-items:center;padding:.75rem .9rem;border-bottom:1px solid var(--bd);background:rgba(2,6,23,.55)}
    .top input{flex:1;background:rgba(2,6,23,.55);border:1px solid var(--bd);color:#e5e7eb;border-radius:.7rem;padding:.55rem .7rem;outline:none}
    .top button,.top a{background:rgba(15,23,42,.7);border:1px solid var(--bd);color:#e5e7eb;border-radius:.7rem;padding:.55rem .75rem;text-decoration:none;cursor:pointer}
    .top button:hover,.top a:hover{border-color:var(--acc)}
    .wrap{display:grid;grid-template-columns:1fr 320px;gap:.9rem;height:calc(100% - 58px);padding:.9rem}
    .grid{overflow:auto;border:1px solid var(--bd);border-radius:1rem;background:rgba(15,23,42,.55);padding:.8rem}
    .items{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.65rem}
    @media (max-width: 820px){ .wrap{grid-template-columns:1fr} .side{display:none} .items{grid-template-columns:repeat(3,minmax(0,1fr));} }
    .item{border:1px solid var(--bd);border-radius:.9rem;overflow:hidden;background:rgba(2,6,23,.35);cursor:pointer}
    .thumb{aspect-ratio: 1/1; display:flex;align-items:center;justify-content:center;background:rgba(2,6,23,.35)}
    .thumb img{width:100%;height:100%;object-fit:cover;display:block}
    .meta{padding:.5rem .55rem;font-size:.78rem;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .item.selected{border-color:var(--acc); box-shadow:0 0 0 2px rgba(56,189,248,.15) inset}
    .side{border:1px solid var(--bd);border-radius:1rem;background:rgba(15,23,42,.55);padding:.9rem;overflow:auto}
    .p{color:var(--muted);font-size:.85rem;margin:.2rem 0 .6rem}
    .side img{width:100%;max-height:220px;object-fit:contain;border-radius:.9rem;border:1px solid var(--bd);background:rgba(2,6,23,.35)}
    label{display:block;color:var(--muted);font-size:.82rem;margin-top:.75rem;margin-bottom:.35rem}
    input[type="text"]{width:100%;background:rgba(2,6,23,.55);border:1px solid var(--bd);color:#e5e7eb;border-radius:.7rem;padding:.55rem .7rem;outline:none}
    .btns{display:flex;gap:.5rem;margin-top:.8rem;flex-wrap:wrap}
    .btn{flex:1;background:rgba(15,23,42,.8);border:1px solid var(--bd);color:#e5e7eb;border-radius:.8rem;padding:.6rem .75rem;cursor:pointer}
    .btn.primary{border-color:rgba(56,189,248,.6)}
    .btn.danger{border-color:rgba(239,68,68,.6)}
    .btn:hover{border-color:var(--acc)}
    .pager{display:flex;gap:.4rem;align-items:center;justify-content:center;margin-top:.75rem;color:var(--muted);font-size:.8rem}
    .pager a{color:#e5e7eb;text-decoration:none;border:1px solid var(--bd);padding:.25rem .55rem;border-radius:.6rem}
    .empty{padding:1.25rem;color:var(--muted);text-align:center}
  </style>
</head>
<body>
  <div class="top">
    <input id="q" value="<?= h2($q) ?>" placeholder="<?= h2(__('t_7f7c3191be','ابحث باسم الملف أو الرابط...')) ?>">
    <button id="btn-search"><?= h2(__('t_2d711b09bd','بحث')) ?></button>
    <a href="<?= h2((string)base_url()) ?>/admin/media/upload.php" target="_blank" rel="noopener"><?= h2(__('t_7dfb9782c8','رفع جديد')) ?></a>
  </div>

  <div class="wrap">
    <div class="grid">
      <?php if (!$tableExists): ?>
        <div class="empty"><?= h2(__('t_4f86cc0b6b','جدول media غير موجود.')) ?></div>
      <?php elseif (!$items): ?>
        <div class="empty"><?= h2(__('t_44f01f5c1e','لا توجد ملفات مطابقة.')) ?></div>
      <?php else: ?>
        <div class="items" id="items">
          <?php foreach ($items as $it):
            $url = (string)($it['file_path'] ?? '');
            $name = (string)($it['file_name'] ?? '');
            $alt = guess_alt($name);
          ?>
          <div class="item" tabindex="0"
               data-id="<?= (int)$it['id'] ?>"
               data-url="<?= h2($url) ?>"
               data-name="<?= h2($name) ?>"
               data-alt="<?= h2($alt) ?>">
            <div class="thumb">
              <img src="<?= h2($url) ?>" alt="<?= h2($alt) ?>" loading="lazy" decoding="async">
            </div>
            <div class="meta"><?= h2($name) ?></div>
          </div>
          <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
          <div class="pager">
            <?php if ($page > 1): ?>
              <a href="?q=<?= urlencode($q) ?>&page=<?= $page-1 ?>">&lsaquo;</a>
            <?php endif; ?>
            <span><?= (int)$page ?> / <?= (int)$totalPages ?></span>
            <?php if ($page < $totalPages): ?>
              <a href="?q=<?= urlencode($q) ?>&page=<?= $page+1 ?>">&rsaquo;</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <div class="side">
      <div class="p"><?= h2(__('t_2b4fef6b07','اختر صورة ثم اضغط إدراج لإضافتها داخل محرر الخبر.')) ?></div>
      <img id="preview" src="" alt="">
      <label><?= h2(__('t_5717a0810e','Alt النص البديل')) ?></label>
      <input id="alt" type="text" value="">
      <label><?= h2(__('t_9a9d5f3e5d','الرابط')) ?></label>
      <input id="url" type="text" value="" readonly>
      <div class="btns">
        <button class="btn primary" id="insert" disabled><?= h2(__('t_5f6aa9be6f','إدراج')) ?></button>
        <button class="btn" id="copy" disabled><?= h2(__('t_9abf6d7e92','نسخ الرابط')) ?></button>
        <button class="btn danger" id="del" disabled><?= h2(__('t_0d7ea7b7c0','حذف')) ?></button>
      </div>
      <div class="p" style="margin-top:.65rem">
        <small><?= h2(__('t_0f5e5f2c48','ملاحظة: الحذف يزيل الملف من السيرفر وقاعدة البيانات.')) ?></small>
      </div>
    </div>
  </div>

<script>
(function(){
  const q = document.getElementById('q');
  const btnSearch = document.getElementById('btn-search');

  function goSearch(){
    const v = (q.value||'').trim();
    const u = new URL(window.location.href);
    u.searchParams.set('q', v);
    u.searchParams.delete('page');
    window.location.href = u.toString();
  }
  btnSearch && btnSearch.addEventListener('click', goSearch);
  q && q.addEventListener('keydown', function(e){ if(e.key==='Enter'){ e.preventDefault(); goSearch(); } });

  let selected = null;
  const items = document.querySelectorAll('.item');
  const preview = document.getElementById('preview');
  const alt = document.getElementById('alt');
  const url = document.getElementById('url');
  const insert = document.getElementById('insert');
  const copy = document.getElementById('copy');
  const del = document.getElementById('del');

  function selectItem(el){
    items.forEach(i=>i.classList.remove('selected'));
    selected = el;
    if(!el) return;
    el.classList.add('selected');
    const u = el.dataset.url || '';
    const a = el.dataset.alt || '';
    preview.src = u;
    preview.alt = a;
    url.value = u;
    alt.value = a;
    insert.disabled = false;
    copy.disabled = false;
    del.disabled = false;
  }

  items.forEach(function(el){
    el.addEventListener('click', function(){ selectItem(el); });
    el.addEventListener('keydown', function(e){ if(e.key==='Enter'){ selectItem(el); } });
  });

  copy && copy.addEventListener('click', async function(){
    if(!selected) return;
    try{
      await navigator.clipboard.writeText(url.value);
      copy.textContent = 'تم النسخ';
      setTimeout(()=>copy.textContent='نسخ الرابط', 900);
    }catch(e){
      url.focus(); url.select();
      document.execCommand('copy');
    }
  });

  
  insert && insert.addEventListener('click', function(){
    if(!selected) return;
    const t = <?= json_encode($target) ?>;
    const payloadA = {
      type: 'gdy_media_selected',
      target: t,
      url: (url.value||'').trim(),
      alt: (alt.value||'').trim(),
      title: (selected.dataset.name||'')
    };
    const payloadB = {
      type: 'GDY_MEDIA_PICK',
      action: t,
      id: (selected.dataset.id||''),
      url: payloadA.url,
      alt: payloadA.alt,
      title: payloadA.title
    };

    // 1) Popup mode (editor): window.opener.godyarSelectMedia(...)
    try {
      if (window.opener && !window.opener.closed && typeof window.opener.godyarSelectMedia === 'function') {
        window.opener.godyarSelectMedia(t, { url: payloadA.url, alt: payloadA.alt, title: payloadA.title, target: t });
        window.close();
        return;
      }
    } catch(e){}

    // 2) Iframe/modal mode (create/edit): postMessage to parent
    try {
      window.parent && window.parent.postMessage(payloadA, '*');
      window.parent && window.parent.postMessage(payloadB, '*');
    } catch(e){}
  });
del && del.addEventListener('click', async function(){
    if(!selected) return;
    if(!confirm('حذف الملف نهائياً؟')) return;
    try{
      const resp = await fetch('delete.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          id: selected.dataset.id || '',
          csrf_token: '<?= h2($csrf) ?>'
        })
      });
      const data = await resp.json().catch(()=>null);
      if(!resp.ok || !data || !data.ok){
        alert((data && data.error) ? data.error : 'فشل الحذف');
        return;
      }
      selected.remove();
      selectItem(null);
      alert('تم الحذف');
    }catch(e){
      alert('فشل الحذف');
    }
  });
})();
</script>
</body>
</html>
