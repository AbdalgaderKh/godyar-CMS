/* Godyar — Category Page UX (grid/list + Load More) */
(function () {
  'use strict';

  function fadeInImages(scope) {
    const images = (scope || document).querySelectorAll('#gdy-category-page .news-thumb img');
    images.forEach(function (img) {
      try {
        img.style.opacity = img.complete ? '1' : '0';
        if (!img.complete) {
          img.addEventListener('load', function () { img.style.opacity = '1'; }, { once: true });
          img.addEventListener('error', function () { img.style.opacity = '1'; }, { once: true });
        }
      } catch (e) {}
    });
  }

  function setupViewToggle() {
    const newsGrid = document.getElementById('newsGrid');
    if (!newsGrid) return;

    const viewBtns = document.querySelectorAll('[data-view]');
    if (!viewBtns.length) return;

    const KEY = 'gdy_cat_view';
    const savedView = localStorage.getItem(KEY) || 'grid';

    function setView(view) {
      newsGrid.classList.toggle('list-view', view === 'list');
      viewBtns.forEach(function (btn) {
        btn.classList.toggle('active', btn.getAttribute('data-view') === view);
        btn.setAttribute('aria-pressed', btn.getAttribute('data-view') === view ? 'true' : 'false');
      });
      localStorage.setItem(KEY, view);
    }

    setView(savedView);

    viewBtns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        const view = btn.getAttribute('data-view') || 'grid';
        setView(view);
      });
    });
  }

  async function fetchLoadMore(btn, newsGrid, statusEl) {
    const endpoint = btn.dataset.endpoint || '';
    const catId = btn.dataset.categoryId || '';
    const sort = btn.dataset.sort || 'latest';
    const period = btn.dataset.period || 'all';
    const totalPages = parseInt(btn.dataset.totalPages || '1', 10);
    const currentPage = parseInt(btn.dataset.currentPage || '1', 10);
    const nextPage = currentPage + 1;

    if (!endpoint || !catId) {
      statusEl.textContent = 'إعدادات غير مكتملة.';
      return;
    }
    if (nextPage > totalPages) {
      btn.remove();
      statusEl.textContent = 'تم عرض كل الأخبار.';
      return;
    }

    const url = new URL(endpoint, window.location.origin);
    url.searchParams.set('category_id', catId);
    url.searchParams.set('page', String(nextPage));
    url.searchParams.set('per_page', '8');
    url.searchParams.set('sort', sort);
    url.searchParams.set('period', period);

    btn.disabled = true;
    statusEl.textContent = 'جارٍ تحميل المزيد...';

    try {
      const res = await fetch(url.toString(), {
        method: 'GET',
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
      });

      if (!res.ok) throw new Error('HTTP ' + res.status);

      const data = await res.json();
      const html = (data && data.html) ? String(data.html) : '';

      if (html.trim()) {
        newsGrid.insertAdjacentHTML('beforeend', html);
        fadeInImages(newsGrid);
      }

      btn.dataset.currentPage = String(nextPage);

      const hasMore = (typeof data.has_more === 'boolean') ? data.has_more : (nextPage < totalPages);
      if (!hasMore || nextPage >= totalPages) {
        btn.remove();
        statusEl.textContent = 'تم عرض كل الأخبار.';
      } else {
        btn.disabled = false;
        statusEl.textContent = '';
      }
    } catch (err) {
      console.error(err);
      btn.disabled = false;
      statusEl.textContent = 'تعذر تحميل المزيد. حاول مرة أخرى.';
    }
  }

  function setupLoadMore() {
    const btn = document.getElementById('gdyCategoryLoadMore');
    const newsGrid = document.getElementById('newsGrid');
    const statusEl = document.getElementById('gdyLoadMoreStatus');

    if (!btn || !newsGrid || !statusEl) return;

    btn.addEventListener('click', function () {
      fetchLoadMore(btn, newsGrid, statusEl);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    fadeInImages(document);
    setupViewToggle();
    setupLoadMore();
  });
})();
