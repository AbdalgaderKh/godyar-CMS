<?php
/**
 * Frontend News Attachments Embed (Modal preview + Download)
 * ---------------------------------------------------------
 * Usage:
 *   require_once __DIR__ . '/news_attachments_embed.php';
 *   gdy_render_news_attachments_embed($pdo, (int)$newsId, ['title' => 'مرفقات الخبر']);
 *
 * Requirements:
 *   - $pdo is a PDO connection
 *   - Table: news_attachments (id, news_id, original_name, file_path, mime_type, file_size, created_at)
 *   - file_path is a web-accessible path (e.g. "uploads/news/attachments/xxxx.pdf")
 */

if (!function_exists('gdy_guess_mime_from_ext')) {
  function gdy_guess_mime_from_ext(string $path): string {
    $ext = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?? $path, PATHINFO_EXTENSION));
    // PHP 7.4 compatibility: avoid "match" (PHP 8+)
    switch ($ext) {
      case 'pdf':
        return 'application/pdf';
      case 'png':
        return 'image/png';
      case 'jpg':
      case 'jpeg':
        return 'image/jpeg';
      case 'gif':
        return 'image/gif';
      case 'webp':
        return 'image/webp';
      case 'txt':
        return 'text/plain';
      case 'rtf':
        return 'application/rtf';
      case 'doc':
        return 'application/msword';
      case 'docx':
        return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
      case 'xls':
        return 'application/vnd.ms-excel';
      case 'xlsx':
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
      case 'ppt':
        return 'application/vnd.ms-powerpoint';
      case 'pptx':
        return 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
      case 'zip':
        return 'application/zip';
      case 'rar':
        return 'application/vnd.rar';
      default:
        return 'application/octet-stream';
    }
  }
}

// PHP 7.4 compatibility: str_contains/str_starts_with/str_ends_with (file can be used standalone)
if (!function_exists('str_contains')) {
  function str_contains(string $haystack, string $needle): bool {
    return $needle === '' || strpos($haystack, $needle) !== false;
  }
}
if (!function_exists('str_starts_with')) {
  function str_starts_with(string $haystack, string $needle): bool {
    if ($needle === '') return true;
    return substr($haystack, 0, strlen($needle)) === $needle;
  }
}
if (!function_exists('str_ends_with')) {
  function str_ends_with(string $haystack, string $needle): bool {
    if ($needle === '') return true;
    return substr($haystack, -strlen($needle)) === $needle;
  }
}

if (!function_exists('gdy_h')) {
  function gdy_h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('gdy_attachment_icon')) {
  function gdy_attachment_icon(string $mime, string $name): string {
    $mime = strtolower($mime ?: gdy_guess_mime_from_ext($name));
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if (str_contains($mime, 'pdf') || $ext === 'pdf') return '📄';
    if (str_starts_with($mime, 'image/') || in_array($ext, ['png','jpg','jpeg','gif','webp'], true)) return '🖼️';
    if (str_contains($mime, 'word') || in_array($ext, ['doc','docx'], true)) return '📝';
    if (str_contains($mime, 'excel') || in_array($ext, ['xls','xlsx'], true)) return '📊';
    if (str_contains($mime, 'powerpoint') || in_array($ext, ['ppt','pptx'], true)) return '📽️';
    if (str_contains($mime, 'zip') || in_array($ext, ['zip','rar'], true)) return '🗜️';
    if (str_contains($mime, 'text') || in_array($ext, ['txt','rtf'], true)) return '📃';
    return '📎';
  }
}

if (!function_exists('gdy_is_previewable_in_iframe')) {
  function gdy_is_previewable_in_iframe(string $mime, string $path): bool {
    $mime = strtolower($mime ?: gdy_guess_mime_from_ext($path));
    $ext = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?? $path, PATHINFO_EXTENSION));

    if ($ext === 'pdf' || str_contains($mime, 'pdf')) return true;
    if (str_starts_with($mime, 'image/') || in_array($ext, ['png','jpg','jpeg','gif','webp'], true)) return true;
    if (str_contains($mime, 'text') || in_array($ext, ['txt','rtf'], true)) return true;

    // Word/Excel غالباً لا تُعرض داخل iframe في المتصفح مباشرة
    return false;
  }
}

if (!function_exists('gdy_fetch_news_attachments')) {
  function gdy_fetch_news_attachments(PDO $pdo, int $newsId): array {
    try {
      $stmt = $pdo->prepare("
        SELECT id, news_id, original_name, file_path, mime_type, file_size, created_at
        FROM news_attachments
        WHERE news_id = ?
        ORDER BY id DESC
      ");
      $stmt->execute([$newsId]);
      return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
      // إذا الجدول غير موجود أو أي خطأ، ارجع قائمة فارغة بدل كسر الصفحة
      return [];
    }
  }
}

/**
 * Render attachments list with Modal preview.
 *
 * Options:
 *  - title (string)
 *  - base_url (string) : prefix to file_path if needed (default "")
 */
if (!function_exists('gdy_render_news_attachments_embed')) {
  function gdy_render_news_attachments_embed(PDO $pdo, int $newsId, array $opts = []): void {
    $title = $opts['title'] ?? 'المرفقات';
    $baseUrl = rtrim((string)($opts['base_url'] ?? ''), '/');
    if ($baseUrl !== '') $baseUrl .= '/';

    $rows = gdy_fetch_news_attachments($pdo, $newsId);
    if (!$rows) return;

    $uid = 'gdyAtt' . bin2hex(random_bytes(4));
    ?>
    <style>
      .gdy-att-wrap{margin-top:16px;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;background:#fff}
      .gdy-att-head{padding:12px 14px;background:#f9fafb;border-bottom:1px solid #e5e7eb;font-weight:700}
      .gdy-att-list{list-style:none;margin:0;padding:0}
      .gdy-att-item{display:flex;gap:10px;align-items:center;justify-content:space-between;padding:10px 14px;border-top:1px solid #f1f5f9}
      .gdy-att-item:first-child{border-top:0}
      .gdy-att-left{display:flex;gap:10px;align-items:center;min-width:0}
      .gdy-att-icon{font-size:20px;line-height:1}
      .gdy-att-name{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width: 520px}
      .gdy-att-actions{display:flex;gap:8px;flex-wrap:wrap}
      .gdy-btn{appearance:none;border:1px solid #d1d5db;background:#fff;border-radius:10px;padding:6px 10px;cursor:pointer;font-size:14px;text-decoration:none;color:inherit}
      .gdy-btn:hover{background:#f3f4f6}
      .gdy-btn-primary{border-color:#c7d2fe;background:#eef2ff}
      .gdy-btn-primary:hover{background:#e0e7ff}
      .gdy-modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.55);z-index:9999;padding:18px}
      .gdy-modal[aria-hidden="false"]{display:flex}
      .gdy-modal-card{width:min(980px, 100%);max-height: min(82vh, 920px);background:#fff;border-radius:16px;overflow:hidden;display:flex;flex-direction:column}
      .gdy-modal-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 14px;border-bottom:1px solid #e5e7eb;background:#f9fafb}
      .gdy-modal-title{font-weight:700;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
      .gdy-modal-body{padding:0;flex:1;min-height:320px}
      .gdy-preview{width:100%;height:100%;border:0;display:block}
      .gdy-img-preview{max-width:100%;max-height:100%;display:block;margin:auto}
      .gdy-fallback{padding:18px}
      .gdy-close{border:1px solid #d1d5db;background:#fff;border-radius:10px;padding:6px 10px;cursor:pointer}
      @media (max-width: 640px){
        .gdy-att-name{max-width: 220px}
        .gdy-modal-card{max-height: 88vh}
      }
    </style>

    <div class="gdy-att-wrap" id="<?php echo gdy_h($uid); ?>">
      <div class="gdy-att-head"><?php echo gdy_h($title); ?></div>
      <ul class="gdy-att-list">
        <?php foreach ($rows as $r):
          $name = (string)($r['original_name'] ?? 'attachment');
          $path = (string)($r['file_path'] ?? '');
          $mime = (string)($r['mime_type'] ?? '');
          $url  = $baseUrl . ltrim($path, '/');
          $icon = gdy_attachment_icon($mime, $name);
          $previewable = gdy_is_previewable_in_iframe($mime, $url);
        ?>
          <li class="gdy-att-item">
            <div class="gdy-att-left">
              <div class="gdy-att-icon"><?php echo $icon; ?></div>
              <div class="gdy-att-name" title="<?php echo gdy_h($name); ?>"><?php echo gdy_h($name); ?></div>
            </div>
            <div class="gdy-att-actions">
              <button
                type="button"
                class="gdy-btn gdy-btn-primary"
                data-gdy-preview="1"
                data-url="<?php echo gdy_h($url); ?>"
                data-name="<?php echo gdy_h($name); ?>"
                data-mime="<?php echo gdy_h($mime ?: gdy_guess_mime_from_ext($url)); ?>"
                data-previewable="<?php echo $previewable ? '1' : '0'; ?>"
              >👁️ مشاهدة</button>

              <a class="gdy-btn" href="<?php echo gdy_h($url); ?>" download>⬇️ حفظ</a>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="gdy-modal" id="<?php echo gdy_h($uid); ?>Modal" aria-hidden="true" role="dialog" aria-modal="true">
      <div class="gdy-modal-card">
        <div class="gdy-modal-head">
          <div class="gdy-modal-title" id="<?php echo gdy_h($uid); ?>ModalTitle">معاينة</div>
          <div style="display:flex;gap:8px;align-items:center">
            <a class="gdy-btn" id="<?php echo gdy_h($uid); ?>ModalDownload" href="#" download>⬇️ حفظ</a>
            <button type="button" class="gdy-close" data-gdy-close="1">✖ إغلاق</button>
          </div>
        </div>
        <div class="gdy-modal-body" id="<?php echo gdy_h($uid); ?>ModalBody"></div>
      </div>
    </div>

    <script>
      (function(){
        const wrap = document.getElementById(<?php echo json_encode($uid); ?>);
        const modal = document.getElementById(<?php echo json_encode($uid . 'Modal'); ?>);
        const titleEl = document.getElementById(<?php echo json_encode($uid . 'ModalTitle'); ?>);
        const bodyEl  = document.getElementById(<?php echo json_encode($uid . 'ModalBody'); ?>);
        const dlEl    = document.getElementById(<?php echo json_encode($uid . 'ModalDownload'); ?>);

        function closeModal(){
          modal.setAttribute('aria-hidden','true');
          bodyEl.innerHTML = '';
          document.body.style.overflow = '';
        }

        function openModal(name, url, mime, previewable){
          titleEl.textContent = name || 'معاينة';
          dlEl.href = url;
          if (name) dlEl.setAttribute('download', name);

          bodyEl.innerHTML = '';
          document.body.style.overflow = 'hidden';

          if (!previewable) {
            bodyEl.innerHTML =
              '<div class="gdy-fallback">' +
              '<p>هذا النوع من الملفات غالباً لا يدعم العرض داخل المتصفح.</p>' +
              '<p>استخدم زر <strong>حفظ</strong> لتنزيل الملف.</p>' +
              '</div>';
          } else if ((mime || '').startsWith('image/')) {
            const img = document.createElement('img');
            img.className = 'gdy-img-preview';
            img.src = url;
            img.alt = name || 'image';
            bodyEl.appendChild(img);
          } else {
            const iframe = document.createElement('iframe');
            iframe.className = 'gdy-preview';
            iframe.src = url;
            iframe.title = name || 'preview';
            bodyEl.appendChild(iframe);
          }

          modal.setAttribute('aria-hidden','false');
        }

        wrap.addEventListener('click', function(e){
          const btn = e.target.closest('[data-gdy-preview="1"]');
          if (!btn) return;
          const url = btn.getAttribute('data-url') || '';
          const name = btn.getAttribute('data-name') || 'attachment';
          const mime = btn.getAttribute('data-mime') || '';
          const previewable = btn.getAttribute('data-previewable') === '1';
          openModal(name, url, mime, previewable);
        });

        modal.addEventListener('click', function(e){
          if (e.target === modal) closeModal();
          const closeBtn = e.target.closest('[data-gdy-close="1"]');
          if (closeBtn) closeModal();
        });

        document.addEventListener('keydown', function(e){
          if (e.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') closeModal();
        });
      })();
    </script>
    <?php
  }
}
