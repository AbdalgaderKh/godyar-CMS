<?php
declare(strict_types=1);
/**
 * admin/includes/saved_filters_ui.php
 * Reusable Saved Filters UI.
 *
 * Usage in list pages:
 *   $savedFiltersPageKey = 'news'; // unique key per page
 *   require_once __DIR__ . '/../includes/saved_filters_ui.php';
 *   echo gdy_saved_filters_ui($savedFiltersPageKey);
 *
 * Requires:
 * - /admin/api/saved_filters.php
 * - /admin/assets/js/saved-filters.js
 */

if (!function_exists('gdy_saved_filters_ui')) {
  function gdy_saved_filters_ui(string $pageKey): string {
    $csrf = function_exists('csrf_token') ? (string)csrf_token() : '';
    $pk = htmlspecialchars($pageKey, ENT_QUOTES, 'UTF-8');

    return '
<div class="gdy-card card mb-3">
  <div class="card-body d-flex flex-column flex-lg-row align-items-start align-items-lg-center gap-2">
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <span class="badge text-bg-dark" style="border:1px solid rgba(148,163,184,.25);">
        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> ' . h(function_exists('__')?__('t_admin_saved_filters','Saved Filters'):'Saved Filters') . '
      </span>
      <select class="form-select form-select-sm" id="gdySavedFiltersSelect" style="min-width:240px;max-width:420px;"></select>
      <button type="button" class="btn btn-sm btn-gdy btn-gdy-outline" id="gdySavedFiltersApply">
        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> ' . h(function_exists('__')?__('t_admin_apply','تطبيق'):'تطبيق') . '
      </button>
      <button type="button" class="btn btn-sm btn-gdy btn-gdy-outline" id="gdySavedFiltersSave">
        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> ' . h(function_exists('__')?__('t_admin_save_current','حفظ الحالي'):'حفظ الحالي') . '
      </button>
      <button type="button" class="btn btn-sm btn-gdy btn-gdy-outline" id="gdySavedFiltersDefault" style="display:none;">
        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> ' . h(function_exists('__')?__('t_admin_make_default','جعله افتراضي'):'جعله افتراضي') . '
      </button>
      <button type="button" class="btn btn-sm btn-gdy btn-gdy-outline" id="gdySavedFiltersShare">
        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> ' . h(function_exists('__')?__('t_admin_copy_link','نسخ الرابط'):'نسخ الرابط') . '
      </button>
      <button type="button" class="btn btn-sm btn-gdy btn-gdy-outline text-danger" id="gdySavedFiltersDelete">
        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> ' . h(function_exists('__')?__('t_admin_delete','حذف'):'حذف') . '
      </button>
    </div>

    <div class="ms-lg-auto text-muted small">
      ' . h(function_exists('__')?__('t_admin_saved_filters_hint','احفظ مجموعة فلاتر باسم واستدعها لاحقاً.'):'احفظ مجموعة فلاتر باسم واستدعها لاحقاً.') . '
    </div>

    <input type="hidden" id="gdySavedFiltersPageKey" value="' . $pk . '">
    <input type="hidden" id="gdySavedFiltersCsrf" value="' . htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') . '">
  </div>
</div>
<script>
(function(){
  if (!window.GdySavedFilters) return;

  var pageKey = document.getElementById("gdySavedFiltersPageKey").value;
  var csrf = document.getElementById("gdySavedFiltersCsrf").value;

  var sel = document.getElementById("gdySavedFiltersSelect");
  var btnApply = document.getElementById("gdySavedFiltersApply");
  var btnSave = document.getElementById("gdySavedFiltersSave");
  var btnDel = document.getElementById("gdySavedFiltersDelete");
  var btnDefault = document.getElementById("gdySavedFiltersDefault");
  var btnShare = document.getElementById("gdySavedFiltersShare");

  var state = { supportsDefault:false, defaultId:null, filters:[] };

  function currentQueryString(){
    var qs = window.location.search || "";
    if (qs.startsWith("?")) qs = qs.slice(1);
    // remove empty
    return qs;
  }

  function buildUrlFromQs(qs){
    var u = new URL(window.location.href);
    u.search = qs ? ("?" + qs) : "";
    return u.toString();
  }

  function copyToClipboard(text){
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(text).then(function(){ return true; }).catch(function(){ return false; });
    }
    try {
      window.prompt("انسخ الرابط:", text);
      return Promise.resolve(true);
    } catch (e) {
      return Promise.resolve(false);
    }
  }

  function refresh(){
    window.GdySavedFilters.list(pageKey).then(function(res){
      state = res || state;
      var list = state.filters || [];

      sel.innerHTML = "";
      var opt0 = document.createElement("option");
      opt0.value = "";
      opt0.textContent = "— ' . h(function_exists('__')?__('t_admin_choose_filter','اختر فلتر محفوظ'):'اختر فلتر محفوظ') . ' —";
      sel.appendChild(opt0);

      list.forEach(function(f){
        var opt = document.createElement("option");
        opt.value = String(f.id);
        var star = (state.supportsDefault && Number(f.is_default||0) === 1) ? "⭐ " : "";
        opt.textContent = star + (f.name || ("#"+f.id));
        opt.dataset.qs = f.querystring || "";
        sel.appendChild(opt);
      });

      // default support
      if (state.supportsDefault) {
        btnDefault.style.display = "";
      } else {
        btnDefault.style.display = "none";
      }

      // Auto apply default filter once per session, only if page has no query params
      try {
        var params = new URLSearchParams(window.location.search || "");
        var hasParams = false;
        params.forEach(function(v,k){
          if (k && v != null && v !== "") hasParams = true;
        });

        var key = "gdy_default_filter_applied_" + pageKey;
        if (!hasParams && state.supportsDefault && state.defaultId && !sessionStorage.getItem(key)) {
          var df = list.find(function(x){ return Number(x.id) === Number(state.defaultId); });
          if (df && df.querystring) {
            sessionStorage.setItem(key, "1");
            window.location.href = buildUrlFromQs(df.querystring);
          }
        }
      } catch (e) {}
    });
  }

  function applySelected(){
    var o = sel.options[sel.selectedIndex];
    if (!o) return;
    var qs = o.dataset.qs || "";
    if (!qs) return;
    window.location.href = buildUrlFromQs(qs);
  }

  btnApply.addEventListener("click", applySelected);

  btnSave.addEventListener("click", function(){
    var name = window.prompt("' . h(function_exists('__')?__('t_admin_filter_name_prompt','اسم الفلتر:'):'اسم الفلتر:') . '");
    if (!name) return;
    var qs = currentQueryString();
    if (!qs) {
      alert("' . h(function_exists('__')?__('t_admin_no_filters_to_save','لا توجد فلاتر في الرابط الحالي لحفظها.'):'لا توجد فلاتر في الرابط الحالي لحفظها.') . '");
      return;
    }

    var makeDefault = false;
    if (state.supportsDefault) {
      makeDefault = window.confirm("' . h(function_exists('__')?__('t_admin_make_default_confirm','هل تريد جعله الفلتر الافتراضي لهذه الصفحة؟'):'هل تريد جعله الفلتر الافتراضي لهذه الصفحة؟') . '");
    }

    window.GdySavedFilters.create(pageKey, name, qs, csrf, makeDefault).then(function(resp){
      if (resp && resp.ok) refresh();
      else alert((resp && resp.error) ? resp.error : "error");
    });
  });

  btnDel.addEventListener("click", function(){
    var id = Number(sel.value || 0);
    if (!id) return;
    if (!window.confirm("' . h(function_exists('__')?__('t_admin_delete_confirm','حذف هذا الفلتر؟'):'حذف هذا الفلتر؟') . '")) return;

    window.GdySavedFilters.del(pageKey, id, csrf).then(function(resp){
      if (resp && resp.ok) refresh();
      else alert((resp && resp.error) ? resp.error : "error");
    });
  });

  btnDefault.addEventListener("click", function(){
    if (!state.supportsDefault) {
      alert("Default not supported. Run migration to add is_default column.");
      return;
    }
    var id = Number(sel.value || 0);
    if (!id) return;
    window.GdySavedFilters.setDefault(pageKey, id, csrf).then(function(resp){
      if (resp && resp.ok) refresh();
      else alert((resp && resp.error) ? resp.error : "error");
    });
  });

  btnShare.addEventListener("click", function(){
    var o = sel.options[sel.selectedIndex];
    var qs = "";
    if (o && o.dataset && o.dataset.qs) qs = o.dataset.qs;
    if (!qs) qs = currentQueryString();
    var url = buildUrlFromQs(qs);
    copyToClipboard(url).then(function(ok){
      if (ok) {
        try { var orig = btnShare.getAttribute("data-orig") || btnShare.innerHTML; btnShare.setAttribute("data-orig", orig); btnShare.innerHTML = "<svg class=\"gdy-icon\" aria-hidden=\"true\" focusable=\"false\"><use href=\"/assets/icons/gdy-icons.svg#check\"></use></svg> تم النسخ"; setTimeout(function(){ btnShare.innerHTML = orig; }, 1200);} catch (e) {}
      }
    });
  });

  refresh();
})();
</script>
';
  }
}