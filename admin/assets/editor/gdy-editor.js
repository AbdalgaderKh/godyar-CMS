/* GDY WYSIWYG Editor (safe embeds) */
(function () {
  'use strict';

  function esc(s) {
    return String(s || '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function absUrl(url) {
    if (!url) return '';
    url = String(url).trim();
    if (/^https?:\/\//i.test(url)) return url;
    if (url.startsWith('//')) return location.protocol + url;
    if (url.startsWith('/')) return location.origin + url;
    var base = location.origin + location.pathname.replace(/\/[^\/]*$/, '/');
    return base + url;
  }

  function getExt(file) {
    var name = (file && (file.name || file.filename || file.title)) || '';
    var m = String(name).toLowerCase().match(/\.([a-z0-9]+)$/);
    return m ? m[1] : '';
  }

  function humanSize(bytes) {
    bytes = Number(bytes || 0);
    if (!bytes || bytes < 0) return '';
    var units = ['B','KB','MB','GB','TB'];
    var i = 0;
    while (bytes >= 1024 && i < units.length - 1) { bytes /= 1024; i++; }
    return (Math.round(bytes * 10) / 10) + ' ' + units[i];
  }

  // ===== WYSIWYG =====
  function createToolbar(btns) {
    var tb = document.createElement('div');
    tb.className = 'gdy-wysiwyg-toolbar';
    btns.forEach(function (b) {
      var tag = (b.tag || 'button').toLowerCase();
      var el = document.createElement(tag);
      el.className = 'gdy-wysiwyg-btn' + (b.className ? (' ' + b.className) : '') + (b.primary ? ' is-primary' : '');
      if (b.title) el.title = b.title;
      if (b.action) el.dataset.action = b.action;

      // Attributes (for inputs etc.)
      if (b.attrs) {
        Object.keys(b.attrs).forEach(function (k) {
          try { el.setAttribute(k, b.attrs[k]); } catch (e) {}
        });
      }

      if (tag === 'button') {
        el.type = 'button';
        el.innerHTML = b.html || '';
      } else if (tag === 'input') {
        // keep input as-is (type from attrs)
        if (b.html) el.value = b.html;
      } else {
        el.innerHTML = b.html || '';
      }

      tb.appendChild(el);
    });
    return tb;
  }

  function placeCaretAtEnd(el) {
    el.focus();
    if (typeof window.getSelection !== 'undefined' && typeof document.createRange !== 'undefined') {
      var range = document.createRange();
      range.selectNodeContents(el);
      range.collapse(false);
      var sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(range);
    }
  }

  function exec(cmd, val) {
    try {
      document.execCommand(cmd, false, val);
    } catch (e) {}
  }

  function insertHTML(html) {
    // Use execCommand when possible
    try {
      exec('insertHTML', html);
      return;
    } catch (e) {}
    // fallback
    var sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) return;
    var range = sel.getRangeAt(0);
    range.deleteContents();
    var temp = document.createElement('div');
    temp.innerHTML = html;
    var frag = document.createDocumentFragment();
    var node, lastNode;
    while ((node = temp.firstChild)) {
      lastNode = frag.appendChild(node);
    }
    range.insertNode(frag);
    if (lastNode) {
      range = range.cloneRange();
      range.setStartAfter(lastNode);
      range.collapse(true);
      sel.removeAllRanges();
      sel.addRange(range);
    }
  }

  function buildAttachCard(payload) {
    var url = absUrl(payload.url || payload.path || payload.file_url || '');
    var name = payload.name || payload.filename || payload.title || (url.split('/').pop() || 'ملف');
    var size = payload.size || payload.bytes || '';
    var ext = getExt({name:name}) || (url.split('?')[0].split('#')[0].match(/\.([a-z0-9]+)$/i)||[])[1] || '';
    ext = String(ext || '').toLowerCase();
    var meta = [];
    if (ext) meta.push(ext.toUpperCase());
    if (size) meta.push(humanSize(size));
    var metaText = meta.length ? ('<div class="gdy-attach-meta">' + esc(meta.join(' • ')) + '</div>') : '';

    // NOTE: do NOT store iframe/object in DB (avoid WAF). Preview is done in frontend via scripts.
    return (
      '<div class="gdy-attach-card" data-file-url="' + esc(url) + '" data-auto-embed="1">' +
        '<div class="gdy-attach-header">' +
          '<div class="gdy-attach-title">📎 ' + esc(name) + '</div>' +
          '<div class="gdy-attach-actions">' +
            '<a class="gdy-attach-btn" href="' + esc(url) + '" target="_blank" rel="noopener">فتح</a>' +
            '<a class="gdy-attach-btn" href="' + esc(url) + '" download>تحميل</a>' +
          '</div>' +
        '</div>' +
        metaText +
        '<div class="gdy-attach-preview"></div>' +
      '</div>'
    );
  }

  function makeEditor(textarea) {
    if (textarea.dataset.gdyMounted === '1') return;
    textarea.dataset.gdyMounted = '1';

    var wrapper = document.createElement('div');
    wrapper.className = 'gdy-wysiwyg';

    var toolbar = createToolbar([
      { html: '<b>B</b>', title: 'عريض', action: 'bold' },
      { html: '<i>I</i>', title: 'مائل', action: 'italic' },
      { html: '<u>U</u>', title: 'تسطير', action: 'underline' },
      { html: '<s>S</s>', title: 'يتوسطه خط', action: 'strikeThrough' },
      { tag: 'input', className: 'gdy-wysiwyg-color', attrs: { type: 'color', value: '#e5e7eb' }, title: 'تلوين النص', action: 'textColor' },
      { tag: 'input', className: 'gdy-wysiwyg-hicolor', attrs: { type: 'color', value: '#fde047' }, title: 'تمييز النص', action: 'highlightColor' },
      { html: '🖍️ تمييز', title: 'تمييز/تلوين خلفية النص', action: 'highlight' },
      { html: '💡 شرح', title: 'إضافة شرح للكلمة المختارة', action: 'annotate' },
      { html: '🖼️ إطار', title: 'إضافة/إزالة إطار للصورة المختارة', action: 'imgFrame' },
      { html: '↦ صورة', title: 'محاذاة الصورة يمين', action: 'imgAlignRight' },
      { html: '↔︎ صورة', title: 'محاذاة الصورة وسط', action: 'imgAlignCenter' },
      { html: '↤ صورة', title: 'محاذاة الصورة يسار', action: 'imgAlignLeft' },
      { html: '✂️ قص', title: 'قص/اقتصاص الصورة المختارة', action: 'imgCrop' },
      { html: '📏 حجم', title: 'تغيير حجم الصورة (عرض/ارتفاع)', action: 'imgResize' },
      { html: '📝 تسمية', title: 'إضافة/تعديل وصف (Caption) أسفل الصورة', action: 'imgCaption' },
      { html: '▦ جدول', title: 'إدراج جدول', action: 'table' },
      { html: '&lt;/&gt; كود', title: 'إدراج صندوق كود', action: 'codeBlock' },
      { html: 'H2', title: 'عنوان H2', action: 'h2' },
      { html: 'H3', title: 'عنوان H3', action: 'h3' },
      { html: '❝ اقتباس', title: 'اقتباس', action: 'blockquote' },
      { html: '• قائمة', title: 'قائمة نقطية', action: 'ul' },
      { html: '1) قائمة', title: 'قائمة رقمية', action: 'ol' },
      { html: '⟷ محاذاة', title: 'تبديل المحاذاة (يمين/وسط/يسار)', action: 'cycleAlign' },
      { html: '─ خط', title: 'خط فاصل', action: 'hr' },
      { html: '↩', title: 'تراجع', action: 'undo' },
      { html: '↪', title: 'إعادة', action: 'redo' },
      { html: '🧹 مسح التنسيق', title: 'مسح التنسيق وإزالة الخلفية', action: 'clearFormat' },
      { html: '🔗 رابط', title: 'إدراج رابط', action: 'link' },
      { html: '❌ إزالة رابط', title: 'إزالة الرابط', action: 'unlink' },
      { html: '📎 ملف', title: 'إدراج ملف من المكتبة', action: 'media', primary: true },
      { html: '&lt;/&gt; HTML', title: 'عرض/تعديل HTML', action: 'toggleHtml' }
    ]);

    var editor = document.createElement('div');
    editor.className = 'gdy-wysiwyg-editor';
    editor.contentEditable = 'true';
    editor.innerHTML = textarea.value || '';

    var htmlArea = document.createElement('textarea');
    htmlArea.className = 'form-control form-control-sm mt-2 gdy-wysiwyg-html d-none';
    htmlArea.rows = 10;
    htmlArea.value = textarea.value || '';

    // Selection save/restore (needed for color picker / tooltip)
    var __savedRange = null;
    function saveRange() {
      try {
        var sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) return;
        var range = sel.getRangeAt(0);
        // ensure selection is inside editor
        var container = range.commonAncestorContainer;
        var el = (container && container.nodeType === 1) ? container : (container ? container.parentElement : null);
        if (!el || !wrapper.contains(el)) return;
        __savedRange = range.cloneRange();
      } catch (e) {}
    }
    function restoreRange() {
      try {
        if (!__savedRange) return;
        var sel = window.getSelection();
        if (!sel) return;
        sel.removeAllRanges();
        sel.addRange(__savedRange);
      } catch (e) {}
    }

    
    // Track selected image reliably (clicking IMG doesn't always create a selection range)
    var __selectedImg = null;
    function setSelectedImage(img) {
      try {
        if (__selectedImg && __selectedImg !== img) __selectedImg.classList.remove('gdy-img-selected');
        __selectedImg = img || null;
        if (__selectedImg) __selectedImg.classList.add('gdy-img-selected');
      } catch (e) {}
    }

    // When user clicks an image, mark it as selected so image-tools work consistently.
    editor.addEventListener('click', function (ev) {
      try {
        var t = ev.target;
        var img = null;
        if (t && t.tagName === 'IMG') img = t;
        else if (t && t.closest) img = t.closest('img');
        if (img && wrapper.contains(img)) {
          setSelectedImage(img);
          // Make sure editor is focused
          editor.focus();
          saveRange();
        } else {
          setSelectedImage(null);
        }
      } catch (e) {}
    });
editor.addEventListener('mouseup', saveRange);
    editor.addEventListener('keyup', saveRange);
    editor.addEventListener('blur', saveRange);

    // Helpers: clear formatting / align cycle
    var __alignCycle = ['justifyRight','justifyCenter','justifyLeft','justifyFull'];
    function cycleAlign() {
      var i = parseInt(wrapper.dataset.alignCycle || '0', 10);
      var cmd = __alignCycle[i % __alignCycle.length];
      exec(cmd);
      wrapper.dataset.alignCycle = String((i + 1) % __alignCycle.length);
    }

    function clearFormatting() {
      exec('removeFormat');
      exec('unlink');
      try {
        var sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) return;
        var range = sel.getRangeAt(0);
        var node = range.commonAncestorContainer;
        var container = (node.nodeType === 1) ? node : node.parentElement;
        if (!container || !wrapper.contains(container)) return;
        var els = container.querySelectorAll('*');
        els.forEach(function (el) {
          if (!wrapper.contains(el)) return;
          el.removeAttribute('style');
          el.removeAttribute('bgcolor');
        });
      } catch (e) {}
    }

    function getSelectedImage() {
      try {
        if (__selectedImg && document.contains(__selectedImg) && wrapper.contains(__selectedImg)) return __selectedImg;
      } catch (e0) {}
      try {
        var sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) return null;
        var node = sel.anchorNode;
        var el = (node && node.nodeType === 1) ? node : (node ? node.parentElement : null);
        if (!el) return null;
        var img = null;
        if (el.tagName === 'IMG') img = el;
        else img = el.closest ? el.closest('img') : null;
        if (img && wrapper.contains(img)) return img;

        // Sometimes selection is a range that directly selects an IMG node
        var r = sel.getRangeAt(0);
        if (r && r.startContainer && r.startContainer.nodeType === 1) {
          var sc = r.startContainer;
          if (sc.tagName === 'IMG') return sc;
        }
      } catch (e) {}
      return null;
    }

    function toggleImageFrame(img) {
      if (!img) return;
      var framed = img.getAttribute('data-gdy-frame') === '1';
      if (framed) {
        var old = img.getAttribute('data-gdy-old-style') || '';
        img.removeAttribute('data-gdy-frame');
        img.removeAttribute('data-gdy-old-style');
        if (old) img.setAttribute('style', old);
        else img.removeAttribute('style');
      } else {
        var oldStyle = img.getAttribute('style') || '';
        img.setAttribute('data-gdy-frame', '1');
        img.setAttribute('data-gdy-old-style', oldStyle);
        img.style.border = '2px solid rgba(148,163,184,.7)';
        img.style.borderRadius = '14px';
        img.style.padding = '2px';
        img.style.boxShadow = '0 10px 22px rgba(2,6,23,.35)';
        img.style.background = 'rgba(2,6,23,.08)';
      }
    }


    function getHighlightColor() {
      return wrapper.dataset.hlColor || '#fde047';
    }

    function applyHighlight(color) {
      var c = color || getHighlightColor();
      try { document.execCommand('styleWithCSS', false, true); } catch (e) {}
      try { document.execCommand('hiliteColor', false, c); }
      catch (e2) { exec('backColor', c); }
    }

    function insertTable() {
      var r = parseInt(prompt('عدد الصفوف؟', '2') || '0', 10);
      var c = parseInt(prompt('عدد الأعمدة؟', '2') || '0', 10);
      if (!r || r < 1 || !c || c < 1) return;
      r = Math.min(20, r);
      c = Math.min(12, c);
      var html = '<table class="gdy-table"><tbody>';
      for (var i = 0; i < r; i++) {
        html += '<tr>';
        for (var j = 0; j < c; j++) html += '<td>&nbsp;</td>';
        html += '</tr>';
      }
      html += '</tbody></table>';
      insertHTML(html);
    }

    function insertCodeBlock() {
      var sel = window.getSelection();
      var text = '';
      try { if (sel) text = sel.toString(); } catch (e) {}
      if (!text) {
        text = prompt('اكتب الكود:', '');
        if (!text) return;
      }
      // Escape for HTML
      var escText = text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
      insertHTML('<pre class="gdy-code"><code>' + escText + '</code></pre>');
    }

    function applyImageAlign(img, mode) {
      if (!img) return;
      img.classList.remove('gdy-img-left', 'gdy-img-center', 'gdy-img-right');
      if (mode === 'left') img.classList.add('gdy-img-left');
      else if (mode === 'center') img.classList.add('gdy-img-center');
      else img.classList.add('gdy-img-right');
    }

    function toggleImageCrop(img) {
      if (!img) return;
      var crop = img.closest ? img.closest('.gdy-img-crop') : null;
      if (crop && wrapper.contains(crop)) {
        // unwrap
        crop.parentNode.insertBefore(img, crop);
        crop.parentNode.removeChild(crop);
        return;
      }
      var h = parseInt(prompt('ارتفاع القص (px):', '320') || '0', 10);
      if (!h || h < 60) return;
      h = Math.min(1200, h);
      var box = document.createElement('div');
      box.className = 'gdy-img-crop';
      box.style.height = h + 'px';
      img.parentNode.insertBefore(box, img);
      box.appendChild(img);
      img.classList.add('gdy-img-cropped');
    }
    function resizeImage(img) {
      if (!img) return;
      // Ask for width/height (supports px or %). Leave empty to keep current.
      var curW = (img.style && img.style.width) ? img.style.width : '';
      var curH = (img.style && img.style.height) ? img.style.height : '';
      var w = prompt('العرض (مثال: 600px أو 80%) — اتركه فارغًا للإبقاء كما هو، واكتب 0 لإزالة العرض:', curW);
      if (w === null) return;
      var h = prompt('الارتفاع (مثال: 320px) — اتركه فارغًا للإبقاء كما هو، واكتب 0 لإزالة الارتفاع:', curH);
      if (h === null) return;

      w = String(w).trim();
      h = String(h).trim();

      // Remove props if user entered 0
      if (w === '0') img.style.removeProperty('width');
      else if (w) img.style.width = w;

      if (h === '0') img.style.removeProperty('height');
      else if (h) img.style.height = h;

      // Keep images responsive
      if (!img.style.maxWidth) img.style.maxWidth = '100%';
      img.setAttribute('data-gdy-resize', '1');
    }

    function editImageCaption(img) {
      if (!img) return;
      var fig = img.closest ? img.closest('figure.gdy-figure') : null;
      if (!fig || !wrapper.contains(fig)) {
        // Wrap image in a figure
        fig = document.createElement('figure');
        fig.className = 'gdy-figure';
        // preserve alignment/crop wrappers by inserting figure in place
        var parent = img.parentNode;
        var next = img.nextSibling;
        parent.insertBefore(fig, next);
        fig.appendChild(img);
      }

      var cap = fig.querySelector('figcaption');
      var cur = cap ? (cap.textContent || '').trim() : '';
      var txt = prompt('اكتب وصف الصورة (Caption) — اتركه فارغًا لإزالة الوصف:', cur);
      if (txt === null) return;
      txt = String(txt).trim();

      if (!txt) {
        if (cap) cap.remove();
        // If figure contains only the image, unwrap it
        if (fig.children.length === 1 && fig.children[0].tagName === 'IMG') {
          var p = fig.parentNode;
          p.insertBefore(img, fig);
          fig.remove();
        }
        return;
      }

      if (!cap) {
        cap = document.createElement('figcaption');
        cap.className = 'gdy-figcaption';
        // Inline style so it looks good even on the frontend without extra CSS
        cap.setAttribute('style', 'text-align:center;font-size:.9em;color:#6b7280;margin-top:6px;');
        fig.appendChild(cap);
      }
      cap.textContent = txt;
    }



function syncToTextarea() {
      if (!htmlArea.classList.contains('d-none')) {
        textarea.value = htmlArea.value;
      } else {
        textarea.value = editor.innerHTML;
      }
    }

    function syncFromTextarea() {
      editor.innerHTML = textarea.value || '';
      htmlArea.value = textarea.value || '';
    }

    // Insert wrapper before textarea
    textarea.style.display = 'none';
    textarea.parentNode.insertBefore(wrapper, textarea);
    wrapper.appendChild(toolbar);
    wrapper.appendChild(editor);
    wrapper.appendChild(htmlArea);

    // Events
    editor.addEventListener('input', function () {
      textarea.value = editor.innerHTML;
      htmlArea.value = editor.innerHTML;
    });

    htmlArea.addEventListener('input', function () {
      textarea.value = htmlArea.value;
    });

    // Toolbar actions
    // Color picker uses input event
    toolbar.addEventListener('input', function (e) {
      var el = e.target.closest('.gdy-wysiwyg-btn');
      if (!el) return;
      var action = el.dataset.action || '';
      if (action === 'textColor') {
        // restore selection because focusing color input may lose it
        restoreRange();
        var color = el.value || '#000000';
        try { document.execCommand('styleWithCSS', false, true); } catch (e2) {}
        exec('foreColor', color);
        syncToTextarea();
        // keep range
        saveRange();
        return;
      }

      if (action === 'highlightColor') {
        restoreRange();
        var hl = el.value || '#fde047';
        wrapper.dataset.hlColor = hl;
        try { document.execCommand('styleWithCSS', false, true); } catch (e3) {}
        // Some browsers support hiliteColor; Safari often uses backColor
        try { document.execCommand('hiliteColor', false, hl); }
        catch (e4) { exec('backColor', hl); }
        syncToTextarea();
        saveRange();
        return;
      }  });

    toolbar.addEventListener('click', function (e) {
      var btn = e.target.closest('.gdy-wysiwyg-btn');
      if (!btn) return;
      var action = btn.dataset.action;

      if (action === 'toggleHtml') {
        var showing = !htmlArea.classList.contains('d-none');
        if (showing) {
          // HTML -> WYSIWYG
          editor.innerHTML = htmlArea.value;
          htmlArea.classList.add('d-none');
          editor.classList.remove('d-none');
          placeCaretAtEnd(editor);
          wrapper.classList.remove('is-html');
          // keep previously selected image if any

        } else {
          // WYSIWYG -> HTML
          htmlArea.value = editor.innerHTML;
          editor.classList.add('d-none');
          htmlArea.classList.remove('d-none');
          htmlArea.focus();
          wrapper.classList.add('is-html');
          setSelectedImage(null);
        }
        syncToTextarea();
        return;
      }

      // Restore selection (for actions after changing focus)
      restoreRange();

      // Ensure focus
      if (htmlArea.classList.contains('d-none')) editor.focus();

      
      if (action === 'highlight') { applyHighlight(getHighlightColor()); syncToTextarea(); return; }
      if (action === 'table') { insertTable(); syncToTextarea(); return; }
      if (action === 'codeBlock') { insertCodeBlock(); syncToTextarea(); return; }

            if (action === 'imgResize' || action === 'imgCaption') {
        var img2 = getSelectedImage();
        if (!img2) { alert('حدد صورة أولاً.'); return; }
        if (action === 'imgResize') { resizeImage(img2); syncToTextarea(); return; }
        if (action === 'imgCaption') { editImageCaption(img2); syncToTextarea(); return; }
      }

if (action === 'imgAlignRight' || action === 'imgAlignCenter' || action === 'imgAlignLeft' || action === 'imgCrop') {
        var img = getSelectedImage();
        if (!img) { alert('حدد صورة أولاً.'); return; }
        if (action === 'imgCrop') { toggleImageCrop(img); syncToTextarea(); return; }
        if (action === 'imgAlignRight') applyImageAlign(img, 'right');
        if (action === 'imgAlignCenter') applyImageAlign(img, 'center');
        if (action === 'imgAlignLeft') applyImageAlign(img, 'left');
        syncToTextarea();
        return;
      }

if (action === 'h2') { exec('formatBlock', '<h2>'); return; }
      if (action === 'h3') { exec('formatBlock', '<h3>'); return; }
      if (action === 'ul') { exec('insertUnorderedList'); return; }
      if (action === 'ol') { exec('insertOrderedList'); return; }

      if (action === 'blockquote') { exec('formatBlock', '<blockquote>'); syncToTextarea(); return; }
      if (action === 'hr') { insertHTML('<hr>'); syncToTextarea(); return; }
      if (action === 'cycleAlign') { cycleAlign(); syncToTextarea(); return; }
      if (action === 'clearFormat') { clearFormatting(); syncToTextarea(); return; }
      if (action === 'unlink') { exec('unlink'); syncToTextarea(); return; }

      if (action === 'annotate') {
        restoreRange();
        var selA = window.getSelection();
        if (!selA || selA.rangeCount === 0 || selA.isCollapsed) {
          alert('حدد كلمة أو نصًا أولاً لإضافة شرح.');
          return;
        }
        var pickedText = selA.toString();
        var tip = prompt('اكتب الشرح للنص المختار:', '');
        if (!tip) return;

        try {
          var rangeA = selA.getRangeAt(0);
          var abbr = document.createElement('abbr');
          abbr.setAttribute('title', tip);
          abbr.appendChild(rangeA.extractContents());
          rangeA.insertNode(abbr);
          // move caret after abbr
          rangeA.setStartAfter(abbr);
          rangeA.collapse(true);
          selA.removeAllRanges();
          selA.addRange(rangeA);
        } catch (eA) {
          // fallback
          insertHTML('<abbr title="' + esc(tip) + '">' + esc(pickedText) + '</abbr>');
        }
        syncToTextarea();
        saveRange();
        return;
      }

      if (action === 'imgFrame') {
        restoreRange();
        var img = getSelectedImage();
        if (!img) {
          alert('اختر صورة داخل المحتوى ثم اضغط "إطار".');
          return;
        }
        toggleImageFrame(img);
        syncToTextarea();
        saveRange();
        return;
      }


      if (action === 'link') {
        var url = prompt('أدخل الرابط:');
        if (!url) return;
        exec('createLink', url);
        return;
      }

      if (action === 'media') {
        // Open media picker in a popup; expects picker.php to call window.opener.godyarSelectMedia(...)
        window.__gdyActiveEditor = { editor: editor, htmlArea: htmlArea, textarea: textarea };
        var w = Math.min(980, window.screen.width - 40);
        var h = Math.min(720, window.screen.height - 80);
        var left = Math.max(10, (window.screen.width - w) / 2);
        var top = Math.max(10, (window.screen.height - h) / 2);
        window.open('/admin/media/picker.php?field=content', 'gdy_media_picker', 'width=' + w + ',height=' + h + ',left=' + left + ',top=' + top);
        return;
      }

      exec(action);
      syncToTextarea();
    });

    // Hook on form submit
    var form = textarea.closest('form');
    if (form) {
      form.addEventListener('submit', function () {
        // sanitize: remove any iframes/objects to avoid WAF blocks
        var html = textarea.value || '';
        html = html.replace(/<\s*(iframe|object|embed)\b[^>]*>[\s\S]*?<\s*\/\s*\1\s*>/gi, '');
        textarea.value = html;
      });
    }

    // Define callback for media picker
    if (!window.godyarSelectMedia) {
      window.godyarSelectMedia = function (field, payload) {
        // payload could be first arg
        if (payload === undefined && typeof field === 'object') {
          payload = field;
        }
        payload = payload || {};
        var active = window.__gdyActiveEditor;
        if (!active || !active.editor) return;

        // Ensure wysiwyg mode
        if (!active.htmlArea.classList.contains('d-none')) {
          active.htmlArea.classList.add('d-none');
          active.editor.classList.remove('d-none');
        }
        active.editor.focus();

        var htmlCard = buildAttachCard(payload);
        insertHTML(htmlCard);

        // Sync
        active.textarea.value = active.editor.innerHTML;
        active.htmlArea.value = active.editor.innerHTML;
      };
    }

    // initial sync
    syncFromTextarea();
  }

  function boot() {
    var areas = document.querySelectorAll('textarea[data-gdy-editor="1"]');
    areas.forEach(makeEditor);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
