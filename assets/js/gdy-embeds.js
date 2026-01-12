(function () {
  "use strict";

  function getBaseUrl() {
    // Prefer explicit base from body data attr or global var
    var b = (window.GDY_BASE_URL || "").trim();
    if (b) return b.replace(/\/+$/, "");
    var body = document.body;
    if (body && body.dataset && body.dataset.baseUrl) {
      b = (body.dataset.baseUrl || "").trim();
      if (b) return b.replace(/\/+$/, "");
    }
    return "";
  }

  function absUrl(url) {
    if (!url) return "";
    url = url.trim();
    if (/^https?:\/\//i.test(url)) return url;
    var base = getBaseUrl();
    if (!base) return url; // fall back; may still work if relative
    if (url.startsWith("/")) return base + url;
    return base + "/" + url;
  }

  function ext(url) {
    try {
      var u = url.split("?")[0].split("#")[0];
      var m = u.match(/\.([a-z0-9]+)$/i);
      return m ? m[1].toLowerCase() : "";
    } catch (e) { return ""; }
  }

  function isPdf(url) { return ext(url) === "pdf"; }
  function isOffice(url) {
    var e = ext(url);
    return ["doc","docx","xls","xlsx","ppt","pptx"].indexOf(e) !== -1;
  }

  function el(tag, attrs, children) {
    var node = document.createElement(tag);
    if (attrs) {
      Object.keys(attrs).forEach(function (k) {
        if (k === "class") node.className = attrs[k];
        else if (k === "html") node.innerHTML = attrs[k];
        else node.setAttribute(k, attrs[k]);
      });
    }
    if (children && children.length) children.forEach(function (c) { node.appendChild(c); });
    return node;
  }

  function humanFileName(url) {
    try {
      var clean = url.split("?")[0].split("#")[0];
      var parts = clean.split("/");
      return decodeURIComponent(parts[parts.length - 1] || "ملف");
    } catch (e) {
      return "ملف";
    }
  }

  function buildCard(url, autoEmbed) {
    var abs = absUrl(url);
    var name = humanFileName(abs);

    var openA = el("a", { href: abs, target: "_blank", rel: "noopener", class: "gdy-attach-btn" , "data-action":"open"}, [document.createTextNode("فتح")]);
    var dlA   = el("a", { href: abs, class: "gdy-attach-btn", download: "", "data-action":"download"}, [document.createTextNode("تحميل")]);
    var pvB   = el("button", { type:"button", class: "gdy-attach-btn gdy-attach-preview-btn", "data-action":"preview" }, [document.createTextNode("معاينة")]);

    var header = el("div", { class:"gdy-attach-header" }, [
      el("div", { class:"gdy-attach-title", html: "📎 " + escapeHtml(name) }),
      el("div", { class:"gdy-attach-actions" }, [openA, dlA, pvB])
    ]);

    var preview = el("div", { class:"gdy-attach-preview" }, []);
    var card = el("div", { class:"gdy-attach-card", "data-file-url": abs, "data-auto-embed": autoEmbed ? "1":"0" }, [header, preview]);

    pvB.addEventListener("click", function () { renderPreview(card); });

    if (autoEmbed) {
      // Defer to allow layout
      setTimeout(function(){ renderPreview(card); }, 30);
    }

    return card;
  }

  function escapeHtml(s) {
    return (s || "").replace(/[&<>"']/g, function (c) {
      return ({ "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;" })[c];
    });
  }

  function officeIframe(url) {
    var src = "https://view.officeapps.live.com/op/embed.aspx?src=" + encodeURIComponent(url);
    return el("iframe", {
      src: src,
      loading: "lazy",
      referrerpolicy: "no-referrer-when-downgrade",
      class: "gdy-embed-frame",
      allowfullscreen: "true",
      title: "Office Preview"
    }, []);
  }

  function pdfIframe(url) {
    return el("iframe", {
      src: url,
      loading: "lazy",
      class: "gdy-embed-frame",
      title: "PDF Preview"
    }, []);
  }

  function renderPreview(card) {
    var url = card.getAttribute("data-file-url") || card.getAttribute("data-url") || "";
    url = url.trim();
    if (!url) return;

    var host = card.querySelector(".gdy-attach-preview");
    if (!host) return;
    host.innerHTML = "";

    // Explain requirement for Office previews if not public
    if (isOffice(url)) {
      host.appendChild(officeIframe(url));
      host.appendChild(el("div", { class:"gdy-embed-note", html:"ملاحظة: معاينة Word/Excel تتطلب رابط ملف عام يمكن الوصول له من الإنترنت." }));
      return;
    }

    if (isPdf(url)) {
      host.appendChild(pdfIframe(url));
      return;
    }

    host.appendChild(el("div", { class:"gdy-embed-note", html:"لا توجد معاينة لهذا النوع من الملفات. استخدم فتح/تحميل." }));
  }

  function convertExistingCards(scope) {
    // Support several attribute names from different versions
    var cards = scope.querySelectorAll(".gdy-attach-card, .gdy-attachment, .gdy-file-card");
    cards.forEach(function (card) {
      var url = card.getAttribute("data-file-url") || card.getAttribute("data-url") || card.getAttribute("data-src") || "";
      if (!url) {
        // try find first link
        var a = card.querySelector("a[href]");
        if (a) url = a.getAttribute("href") || "";
      }
      url = (url || "").trim();
      if (!url) return;

      // Ensure preview container exists
      if (!card.querySelector(".gdy-attach-preview")) {
        card.appendChild(el("div", { class:"gdy-attach-preview" }, []));
      }

      // Wire preview button if exists
      var btn = card.querySelector("[data-action='preview'], .gdy-attach-preview-btn");
      if (btn) btn.addEventListener("click", function(){ renderPreview(card); });

      // Auto embed if requested
      var auto = card.getAttribute("data-auto-embed") === "1";
      if (auto) setTimeout(function(){ renderPreview(card); }, 30);
    });
  }

  function convertPlainLinks(scope) {
    // Turn plain links to PDF/Office inside article into cards
    var links = Array.prototype.slice.call(scope.querySelectorAll("a[href]"));
    links.forEach(function (a) {
      // Skip if already inside a card
      if (a.closest(".gdy-attach-card, .gdy-attachment, .gdy-file-card")) return;

      var href = (a.getAttribute("href") || "").trim();
      if (!href) return;

      var abs = absUrl(href);
      if (!(isPdf(abs) || isOffice(abs))) return;

      // Build card and replace the link (keep text as title if meaningful)
      var card = buildCard(abs, true);
      // If anchor text is nicer than filename, show it
      var t = (a.textContent || "").trim();
      if (t && t.length >= 3 && t.length <= 120) {
        var title = card.querySelector(".gdy-attach-title");
        if (title) title.innerHTML = "📎 " + escapeHtml(t);
      }

      var p = a.parentNode;
      if (!p) return;

      // If link is alone in a paragraph, replace the whole paragraph; else replace just the link
      if (p.tagName && p.tagName.toLowerCase() === "p" && p.textContent.trim() === a.textContent.trim()) {
        p.parentNode.replaceChild(card, p);
      } else {
        p.replaceChild(card, a);
      }
    });
  }

  function boot() {
    // The article content is inside .article-body in your template
    var scope = document.querySelector(".article-body") || document;
    convertExistingCards(scope);
    convertPlainLinks(scope);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();