/**
 * Godyar PWA helper (no inline handlers)
 * - Service Worker registration + update UX
 * - Install prompt banner (Chrome/Edge/Android)
 * - iOS Add-to-Home-Screen tip (Safari)
 */
(function () {
  "use strict";

  const ls = {
    get(key) {
      try { return localStorage.getItem(key); } catch (e) { return null; }
    },
    set(key, val) {
      try { localStorage.setItem(key, val); } catch (e) {}
    }
  };

  const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
  const isStandalone = (function () {
    const iosStandalone = (navigator.standalone === true);
    const displayStandalone = window.matchMedia && window.matchMedia("(display-mode: standalone)").matches;
    return iosStandalone || displayStandalone;
  })();

  // ---------------------------
  // Install prompt (Chromium)
  // ---------------------------
  let deferredPrompt = null;

  window.addEventListener("beforeinstallprompt", function (e) {
    // Prevent default mini-infobar
    e.preventDefault();
    deferredPrompt = e;

    // Don't show if already dismissed recently
    if (ls.get("gdy_install_dismissed") === "1") return;

    showInstallBanner();
  });

  window.addEventListener("appinstalled", function () {
    deferredPrompt = null;
    ls.set("gdy_install_dismissed", "1");
    const b = document.getElementById("gdyInstallBanner");
    if (b) b.remove();
  });

  function showInstallBanner() {
    if (isStandalone) return;
    if (document.getElementById("gdyInstallBanner")) return;

    const banner = document.createElement("div");
    banner.id = "gdyInstallBanner";
    banner.className = "gdy-install-banner";
    banner.setAttribute("role", "dialog");
    banner.setAttribute("aria-label", "تثبيت التطبيق");

    banner.innerHTML = [
      '<div class="gdy-install-banner__inner">',
        '<div class="gdy-install-banner__text">',
          '<div class="gdy-install-banner__title">📱 ثبّت غديار كتطبيق</div>',
          '<div class="gdy-install-banner__desc">تجربة أسرع + دعم عدم الاتصال</div>',
        '</div>',
        '<div class="gdy-install-banner__actions">',
          '<button type="button" id="gdyInstallNow" class="gdy-install-banner__btn">تثبيت</button>',
          '<button type="button" id="gdyInstallDismiss" class="gdy-install-banner__close" aria-label="إغلاق">×</button>',
        '</div>',
      '</div>'
    ].join("");

    document.body.appendChild(banner);

    const installBtn = document.getElementById("gdyInstallNow");
    const closeBtn = document.getElementById("gdyInstallDismiss");

    if (installBtn) {
      installBtn.addEventListener("click", function () {
        if (!deferredPrompt) return;
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(function () {
          deferredPrompt = null;
        });
      });
    }

    if (closeBtn) {
      closeBtn.addEventListener("click", function () {
        ls.set("gdy_install_dismissed", "1");
        banner.remove();
      });
    }
  }

  // ---------------------------
  // iOS A2HS tip (Safari)
  // ---------------------------
  function showIosTip() {
    if (!isIOS || isStandalone) return;
    if (ls.get("gdy_ios_a2hs_dismissed") === "1") return;
    if (document.getElementById("gdyIosA2hsTip")) return;

    const tip = document.createElement("div");
    tip.id = "gdyIosA2hsTip";
    tip.className = "gdy-ios-a2hs";
    tip.setAttribute("role", "dialog");
    tip.setAttribute("aria-label", "إضافة إلى الشاشة الرئيسية");

    const shareSvg = '<svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 3l4 4h-3v7h-2V7H8l4-4zm-7 9h2v7h10v-7h2v9H5v-9z"/></svg>';

    tip.innerHTML = [
      '<div class="gdy-ios-a2hs__inner">',
        '<div class="gdy-ios-a2hs__txt">',
          '<strong>ثبّت الموقع كتطبيق</strong>',
          '<div>اضغط زر المشاركة ', shareSvg, ' ثم اختر <b>إضافة إلى الشاشة الرئيسية</b>.</div>',
        '</div>',
        '<button type="button" class="gdy-ios-a2hs__close" aria-label="إغلاق">×</button>',
      '</div>'
    ].join("");

    document.body.appendChild(tip);

    const close = tip.querySelector(".gdy-ios-a2hs__close");
    if (close) {
      close.addEventListener("click", function () {
        ls.set("gdy_ios_a2hs_dismissed", "1");
        tip.remove();
      });
    }
  }

  // ---------------------------
  // Service Worker + Updates
  // ---------------------------
  function registerSW() {
    if (!("serviceWorker" in navigator)) return;

    // Prefer root SW for full scope
    navigator.serviceWorker.register((window.GDY_SW_URL||"/sw.js"), {scope:"/"}).then(function (reg) {
      // Listen for updates
      reg.addEventListener("updatefound", function () {
        const newWorker = reg.installing;
        if (!newWorker) return;
        newWorker.addEventListener("statechange", function () {
          if (newWorker.state === "installed" && navigator.serviceWorker.controller) {
            // New version is ready
            showUpdateBanner(reg);
          }
        });
      });
    }).catch(function () {
      // ignore
    });

    // If controller changes, reload once (after user accepted)
    navigator.serviceWorker.addEventListener("controllerchange", function () {
      // We reload only if user already accepted update banner
      if (ls.get("gdy_sw_reload") === "1") {
        ls.set("gdy_sw_reload", "0");
        window.location.reload();
      }
    });
  }

  function showUpdateBanner(reg) {
    if (document.getElementById("gdyUpdateBanner")) return;

    const banner = document.createElement("div");
    banner.id = "gdyUpdateBanner";
    banner.className = "gdy-update-banner";
    banner.setAttribute("role", "dialog");
    banner.setAttribute("aria-label", "تحديث متوفر");

    banner.innerHTML = [
      '<div class="gdy-update-banner__inner">',
        '<div class="gdy-update-banner__text">✅ يوجد تحديث جديد للتطبيق</div>',
        '<div class="gdy-update-banner__actions">',
          '<button type="button" id="gdyUpdateNow" class="gdy-update-banner__btn">تحديث الآن</button>',
          '<button type="button" id="gdyUpdateLater" class="gdy-update-banner__close" aria-label="لاحقًا">لاحقًا</button>',
        '</div>',
      '</div>'
    ].join("");

    document.body.appendChild(banner);

    const btnNow = document.getElementById("gdyUpdateNow");
    const btnLater = document.getElementById("gdyUpdateLater");

    if (btnNow) {
      btnNow.addEventListener("click", function () {
        try {
          if (reg && reg.waiting) {
            ls.set("gdy_sw_reload", "1");
            reg.waiting.postMessage({ action: "skipWaiting" });
          } else {
            // fallback
            window.location.reload();
          }
        } catch (e) {
          window.location.reload();
        }
      });
    }

    if (btnLater) {
      btnLater.addEventListener("click", function () {
        banner.remove();
      });
    }
  }

  // ---------------------------
  // Init
  // ---------------------------
  function init() {
    try { showIosTip(); } catch (e) {}
    try { registerSW(); } catch (e) {}
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
