<?php
declare(strict_types=1);

/**
 * Frontend snippet: عرض مرفقات الخبر داخل الصفحة + زر حفظ.
 *
 * (Modal) زر __('t_d097eb12c5', "مشاهدة") يفتح معاينة داخل نافذة منبثقة تحتوي:
 * - إطار (iframe) لملفات PDF و TXT/RTF
 * - صورة للصور
 * - رسالة + زر حفظ للأنواع غير القابلة للعرض عادة
 *
 * الاستخدام داخل صفحة الخبر (بعد توفر PDO و $newsId):
 *   require_once __DIR__ . '/frontend/news_attachments_embed.php';
 *   gdy_render_news_attachments_embed($pdo, (int)$newsId);
 */

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

function gdy_att_icon(string $filename): string {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    // PHP 7.4 compatibility: avoid "match" (PHP 8+)
    switch ($ext) {
        case 'pdf':
            return '📄';
        case 'doc':
        case 'docx':
            return '📝';
        case 'xls':
        case 'xlsx':
            return '📊';
        case 'ppt':
        case 'pptx':
            return '📽️';
        case 'zip':
        case 'rar':
        case '7z':
            return '🗜️';
        case 'png':
        case 'jpg':
        case 'jpeg':
        case 'gif':
        case 'webp':
            return '🖼️';
        case 'txt':
        case 'rtf':
            return '📃';
        default:
            return '📎';
    }
}

function gdy_att_preview_meta(string $filename): array {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return [
        'ext' => $ext,
        'pdf' => $ext === 'pdf',
        'img' => in_array($ext, ['png','jpg','jpeg','gif','webp'], true),
        'txt' => in_array($ext, ['txt','rtf'], true),
    ];
}

/**
 * @param array $options
 *   - base_url: (string) إذا كان موقع الواجهة الأمامية ليس على نفس الجذر. الافتراضي '/'
 *   - title: (string) عنوان صندوق المرفقات
 */
function gdy_render_news_attachments_embed(PDO $pdo, int $newsId, array $options = []): void {
    if ($newsId <= 0) return;

    $baseUrl = (string)($options['base_url'] ?? '/');
    $baseUrl = $baseUrl === '' ? '/' : $baseUrl;
    $title   = (string)($options['title'] ?? __('t_a2737af54c', 'المرفقات'));

    // إذا جدول المرفقات غير موجود، لا نعرض شيئاً
    try {
        $exists = $pdo->query("SHOW TABLES LIKE 'news_attachments'")->fetchColumn();
        if (!$exists) return;
    } catch (Throwable $e) {
        return;
    }

    $stmt = $pdo->prepare("SELECT id, original_name, file_path, mime_type, file_size
                           FROM news_attachments
                           WHERE news_id = :nid
                           ORDER BY id DESC");
    $stmt->execute([':nid' => $newsId]);
    $atts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (!$atts) return;

    // معرف فريد لمنع تعارض CSS/JS لو استُخدم المكون أكثر من مرة
    $uid = 'gdyAtt' . $newsId . '_' . substr(md5((string)$newsId . '|' . (string)count($atts)), 0, 6);

    // CSS بسيط بدون الاعتماد على Bootstrap
    echo "\n<style>\n";
    echo ".{$uid}-box{border:1px solid rgba(0,0,0,.12);border-radius:14px;padding:14px;margin:16px 0;background:#fff;max-width:100%;overflow:hidden}\n";
    echo ".{$uid}-title{font-weight:700;margin:0 0 10px;font-size:16px}\n";
    echo ".{$uid}-item{border:1px solid rgba(0,0,0,.10);border-radius:12px;padding:10px 10px;background:rgba(0,0,0,.02);margin:10px 0}\n";
    echo ".{$uid}-row{display:flex;gap:10px;align-items:center;justify-content:space-between}\n";
    echo ".{$uid}-name{display:flex;align-items:center;gap:8px;min-width:0}\n";
    echo ".{$uid}-name span.fn{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:70vw;display:inline-block}\n";
    echo ".{$uid}-actions{display:flex;gap:8px;flex-shrink:0}\n";
    echo ".{$uid}-btn{border:1px solid rgba(0,0,0,.18);border-radius:10px;padding:6px 10px;font-size:13px;cursor:pointer;background:#fff;text-decoration:none;color:#111;display:inline-flex;align-items:center;gap:6px}\n";
    echo ".{$uid}-btn:hover{background:rgba(0,0,0,.04)}\n";
    echo ".{$uid}-muted{color:#666;font-size:13px}\n";

    // Modal styles
    echo ".{$uid}-modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.55);padding:16px;z-index:99999}\n";
    echo ".{$uid}-modal.open{display:flex}\n";
    echo ".{$uid}-dialog{width:min(980px, 100%);max-height:92vh;background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.35);overflow:hidden;display:flex;flex-direction:column}\n";
    echo ".{$uid}-header{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 12px;border-bottom:1px solid rgba(0,0,0,.10)}\n";
    echo ".{$uid}-hname{font-weight:700;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}\n";
    echo ".{$uid}-close{border:1px solid rgba(0,0,0,.18);border-radius:12px;background:#fff;cursor:pointer;padding:6px 10px;font-size:13px}\n";
    echo ".{$uid}-close:hover{background:rgba(0,0,0,.04)}\n";
    echo ".{$uid}-body{padding:12px;overflow:auto}\n";
    echo ".{$uid}-frame{width:100%;height:72vh;min-height:420px;border:1px solid rgba(0,0,0,.10);border-radius:12px;background:#f7f7f7}\n";
    echo ".{$uid}-img{max-width:100%;height:auto;border:1px solid rgba(0,0,0,.10);border-radius:12px;display:block;margin:0 auto}\n";
    echo ".{$uid}-footer{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;padding:12px;border-top:1px solid rgba(0,0,0,.10)}\n";
    echo "</style>\n";

    echo '<div class="' . h($uid) . '-box">';
    echo '<div class="' . h($uid) . '-title">' . h($title) . '</div>';

    foreach ($atts as $i => $att) {
        $name = (string)($att['original_name'] ?? '');
        $path = (string)($att['file_path'] ?? '');

        // حماية بسيطة: لا نعرض مسارات غريبة
        $trimPath = ltrim($path, '/');
        if ($trimPath === '' || !(str_starts_with($trimPath, 'uploads/') || str_starts_with($trimPath, 'storage/') || str_starts_with($trimPath, 'public/'))) {
            continue;
        }

        $url  = rtrim($baseUrl, '/') . '/' . $trimPath;
        $meta = gdy_att_preview_meta($name);

        // data-* لتغذية المودال
        $data = [
            'data-url'  => $url,
            'data-name' => $name,
            'data-ext'  => (string)$meta['ext'],
            'data-pdf'  => $meta['pdf'] ? '1' : '0',
            'data-img'  => $meta['img'] ? '1' : '0',
            'data-txt'  => $meta['txt'] ? '1' : '0',
        ];
        $dataAttr = '';
        foreach ($data as $k => $v) {
            $dataAttr .= ' ' . h($k) . '="' . h($v) . '"';
        }

        echo '<div class="' . h($uid) . '-item">';
        echo '  <div class="' . h($uid) . '-row">';
        echo '    <div class="' . h($uid) . '-name">';
        echo '      <span aria-hidden="true">' . h(gdy_att_icon($name)) . '</span>';
        echo '      <span class="fn">' . h($name) . '</span>';
        echo '    </div>';
        echo '    <div class="' . h($uid) . '-actions">';
        echo '      <button type="button" class="' . h($uid) . '-btn"' . $dataAttr . ' data-action="open-attachment-modal" data-uid="' . h($uid) . '">👁️ مشاهدة</button>');
        echo '      <a class="' . h($uid) . '-btn" href="' . h($url) . __('t_8bdd445e75', '" download>⬇️ حفظ</a>');
        echo '    </div>';
        echo '  </div>';
        echo '</div>';
    }

    echo '</div>';

    // Modal template (واحد فقط)
    echo '<div class="' . h($uid) . '-modal" id="' . h($uid) . '_modal" role="dialog" aria-modal="true" aria-hidden="true">';
    echo '  <div class="' . h($uid) . '-dialog" data-stop-prop="1">';
    echo '    <div class="' . h($uid) . '-header">';
    echo '      <div class="' . h($uid) . '-hname" id="' . h($uid) . __('t_49a1343a70', '_m_name">المرفق</div>');
    echo '      <button type="button" class="' . h($uid) . '-close" data-action="open-attachment-modal" data-uid="' . h($uid) . '">✖ إغلاق</button>');
    echo '    </div>';
    echo '    <div class="' . h($uid) . '-body" id="' . h($uid) . '_m_body"></div>';
    echo '    <div class="' . h($uid) . '-footer">';
    echo '      <a class="' . h($uid) . '-btn" id="' . h($uid) . __('t_135cf0959f', '_m_download" href="#" download>⬇️ حفظ</a>');
    echo '      <button type="button" class="' . h($uid) . '-btn" data-action="open-attachment-modal" data-uid="' . h($uid) . '">تم</button>');
    echo '    </div>';
    echo '  </div>';
    echo '</div>';

    // JS: فتح/إغلاق مودال + إنشاء معاينة
    echo "\n<script>\n";
    echo "(function(){\n";
    echo "  var modal = document.getElementById('{$uid}_modal');\n";
    echo "  var mName = document.getElementById('{$uid}_m_name');\n";
    echo "  var mBody = document.getElementById('{$uid}_m_body');\n";
    echo "  var mDl   = document.getElementById('{$uid}_m_download');\n";
    echo "  function esc(s){return String(s||'');}\n";
    echo "  function setBody(html){ mBody.innerHTML = html; }\n";

    echo "  window.{$uid}_openModal = function(btn){\n";
    echo "    if(!btn) return;\n";
    echo "    var url = btn.getAttribute('data-url') || '';\n";
    echo __('t_8141ecd9d4', "    var name = btn.getAttribute('data-name') || __('t_f81e85e0e7', 'المرفق');\n");
    echo "    var isPdf = btn.getAttribute('data-pdf') === '1';\n";
    echo "    var isImg = btn.getAttribute('data-img') === '1';\n";
    echo "    var isTxt = btn.getAttribute('data-txt') === '1';\n";

    echo "    mName.textContent = name;\n";
    echo "    mDl.href = url;\n";

    echo "    if(isPdf || isTxt){\n";
    echo "      var h = '<iframe class=\"{$uid}-frame\" src=\"'+esc(url)+'\" loading=\"lazy\"></iframe>';\n";
    echo "      setBody(h);\n";
    echo "    } else if(isImg){\n";
    echo "      var h = '<img class=\"{$uid}-img\" src=\"'+esc(url)+'\" alt=\"'+esc(name)+'\" loading=\"lazy\">';\n";
    echo "      setBody(h);\n";
    echo "    } else {\n";
    echo __('t_27326aaa96', "      var h = __('t_8e99dbc664', '<div class=\"{$uid}-muted\" style=\"padding:6px 2px\">هذا النوع لا يُعرض عادة داخل المتصفح. يمكنك تنزيله عبر زر <b>حفظ</b>.</div>');\n");
    echo "      setBody(h);\n";
    echo "    }\n";

    echo "    modal.classList.add('open');\n";
    echo "    modal.setAttribute('aria-hidden','false');\n";
    echo "  };\n";

    echo "  window.{$uid}_closeModal = function(){\n";
    echo "    modal.classList.remove('open');\n";
    echo "    modal.setAttribute('aria-hidden','true');\n";
    echo __('t_3b48f0747c', "    // تفريغ المحتوى لإيقاف تشغيل PDF/iframe\n");
    echo "    setBody('');\n";
    echo "  };\n";

    echo __('t_7c42207f8f', "  // إغلاق عند الضغط خارج الصندوق\n");
    echo "  modal.addEventListener('click', function(){ window.{$uid}_closeModal(); });\n";

    echo __('t_ab7e1e846c', "  // إغلاق عند ESC\n");
    echo "  document.addEventListener('keydown', function(e){\n";
    echo "    if(e.key === 'Escape' && modal.classList.contains('open')) window.{$uid}_closeModal();\n";
    echo "  });\n";

    echo "})();\n";
    echo "</script>\n";
}
