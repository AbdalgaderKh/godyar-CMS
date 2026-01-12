(function () {
  function boot() {
    const el = document.querySelector('.js-wysiwyg');
    if (!el) return;

    // لو عندك نظام Plugins/Hooks
    if (window.AdminHooks && typeof window.AdminHooks.applyFilters === 'function') {
      const chosen = window.AdminHooks.applyFilters('editor:choose', el.dataset.editor || 'auto', el);
      if (chosen && typeof window.AdminHooks.doAction === 'function') {
        window.AdminHooks.doAction('editor:init:' + chosen, el);
        return;
      }
    }

    // fallback: المحرر الحالي (أو لا شيء)
    if (window.GdyEditor && typeof window.GdyEditor.init === 'function') {
      window.GdyEditor.init(el);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
