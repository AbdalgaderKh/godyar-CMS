/* admin/assets/js/saved-filters.js
 * Saved Filters client helper
 * - robust with both legacy (data = array) and new (data = {filters, default_id,...}) responses
 */
(function () {
  function apiUrl(action, pageKey) {
    var u = new URL((window.GDY_ADMIN_BASE || '/admin') + '/api/saved_filters.php', window.location.origin);
    u.searchParams.set('action', action);
    if (pageKey) u.searchParams.set('page_key', pageKey);
    return u.toString();
  }

  function post(url, body) {
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body
    }).then(function (r) { return r.json(); });
  }

  function encodeForm(obj) {
    var parts = [];
    Object.keys(obj || {}).forEach(function (k) {
      parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(obj[k] == null ? '' : String(obj[k])));
    });
    return parts.join('&');
  }

  function normalizeListResponse(json) {
    if (!json) return { filters: [], supports_default: false, default_id: null };
    if (Array.isArray(json.data)) {
      return { filters: json.data, supports_default: false, default_id: null };
    }
    if (json.data && typeof json.data === 'object' && Array.isArray(json.data.filters)) {
      return json.data;
    }
    return { filters: [], supports_default: false, default_id: null };
  }

  window.GdySavedFilters = {
    list: function (pageKey) {
      return fetch(apiUrl('list', pageKey))
        .then(function (r) { return r.json(); })
        .then(function (json) {
          if (json && json.ok) return normalizeListResponse(json);
          return { filters: [], supports_default: false, default_id: null };
        });
    },

    create: function (pageKey, name, querystring, csrfToken, makeDefault) {
      return post(apiUrl('create', pageKey), encodeForm({
        csrf_token: csrfToken || '',
        page_key: pageKey || '',
        name: name || '',
        querystring: querystring || '',
        make_default: makeDefault ? '1' : '0'
      }));
    },

    del: function (pageKey, id, csrfToken) {
      return post(apiUrl('delete', pageKey), encodeForm({
        csrf_token: csrfToken || '',
        page_key: pageKey || '',
        id: id || 0
      }));
    },

    setDefault: function (pageKey, id, csrfToken) {
      return post(apiUrl('set_default', pageKey), encodeForm({
        csrf_token: csrfToken || '',
        page_key: pageKey || '',
        id: id || 0
      }));
    }
  };
})();