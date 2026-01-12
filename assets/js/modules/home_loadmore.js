/* Home Load More (Godyar CMS)
 * Adds "تحميل المزيد" buttons for Latest + Category sections on the home page.
 * Requires: /api/v1/home_loadmore.php
 */
(function () {
  'use strict';

  function $(sel, root) { return (root || document).querySelector(sel); }
  function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  const base = (window.GDY_BASE || '').replace(/\/$/, '');
  const API_URL = base + '/api/v1/home_loadmore.php';

  function esc(s) {
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function resolveUrl(path) {
    if (!path) return '';
    if (/^https?:\/\//i.test(path)) return path;
    if (path.startsWith('/')) return base + path;
    return base + '/' + path;
  }

  function newsUrl(id) {
    // Keep consistent with server helper: /news/id/{id}
    return base + '/news/id/' + encodeURIComponent(String(id));
  }

  function defaultThumb() {
    return base + '/assets/images/placeholder-thumb.jpg';
  }

  function cardHTML(item, badgeText) {
    const id = item && item.id ? item.id : 0;
    const title = (item && item.title) ? item.title : '';
    const excerpt = (item && item.excerpt) ? String(item.excerpt).trim() : '';
    const img = item && item.image ? resolveUrl(item.image) : defaultThumb();
    const url = newsUrl(id);
    const date = item && item.date ? item.date : '';

    return `
      <article class="hm-card">
        <a href="${esc(url)}" class="hm-card-thumb">
          <span class="hm-card-badge">${esc(badgeText || 'خبر')}</span>
          <img src="${esc(img)}" alt="${esc(title)}" loading="lazy">
        </a>
        <div class="hm-card-body">
          <h3 class="hm-card-title">
            <a href="${esc(url)}">${esc(title.length > 90 ? title.slice(0, 90) + '…' : title)}</a>
          </h3>
          ${excerpt ? `<p class="hm-card-excerpt">${esc(excerpt)}</p>` : ''}
          ${date ? `<div class="hm-card-meta"><span class="hm-date">${esc(date)}</span></div>` : ''}
        </div>
      </article>
    `;
  }

  async function fetchJSON(url) {
    const r = await fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    return await r.json();
  }

  function getSectionBadge(btn) {
    // Try to read the section title text
    const section = btn.closest('.hm-section');
    if (!section) return 'خبر';
    const t = section.querySelector('.hm-section-title');
    if (!t) return 'خبر';
    const text = (t.textContent || '').trim();
    return text || 'خبر';
  }

  async function onLoadMoreClick(btn) {
    const type = btn.getAttribute('data-loadmore') || '';
    const targetId = btn.getAttribute('data-target') || '';
    const grid = targetId ? document.getElementById(targetId) : null;
    if (!grid) return;

    const limit = parseInt(btn.getAttribute('data-limit') || '8', 10) || 8;
    const offset = parseInt(btn.getAttribute('data-offset') || '0', 10) || 0;

    btn.disabled = true;
    const oldText = btn.textContent;
    btn.textContent = '...';

    try {
      let url = API_URL + '?type=' + encodeURIComponent(type) + '&offset=' + encodeURIComponent(String(offset)) + '&limit=' + encodeURIComponent(String(limit));

      if (type === 'latest') {
        const period = btn.getAttribute('data-period') || 'today';
        url += '&period=' + encodeURIComponent(period);
      } else if (type === 'category') {
        const cid = btn.getAttribute('data-category-id') || '';
        url += '&cid=' + encodeURIComponent(cid);
      } else {
        throw new Error('Invalid type');
      }

      const data = await fetchJSON(url);

      if (!data || !data.ok || !Array.isArray(data.items)) {
        throw new Error('Bad response');
      }

      const badge = (type === 'latest') ? 'خبر' : getSectionBadge(btn);

      const frag = document.createDocumentFragment();
      const tmp = document.createElement('div');

      data.items.forEach(function (it) {
        tmp.innerHTML = cardHTML(it, badge);
        // move children to fragment
        while (tmp.firstElementChild) frag.appendChild(tmp.firstElementChild);
      });

      grid.appendChild(frag);

      // Update offset
      const nextOffset = (typeof data.next_offset === 'number') ? data.next_offset : (offset + data.items.length);
      btn.setAttribute('data-offset', String(nextOffset));

      // Hide if no more
      if (!data.has_more || data.items.length === 0) {
        btn.closest('.hm-loadmore-wrap')?.remove();
      }
    } catch (e) {
      // Restore button so user can retry
      console.error('[home_loadmore] failed', e);
      btn.disabled = false;
      btn.textContent = oldText || 'تحميل المزيد';
      return;
    }

    btn.disabled = false;
    btn.textContent = oldText || 'تحميل المزيد';
  }

  function init() {
    const buttons = $all('.hm-loadmore-btn[data-loadmore][data-target]');
    if (!buttons.length) return;

    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        onLoadMoreClick(btn);
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
