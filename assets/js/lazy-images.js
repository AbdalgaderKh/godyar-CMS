(function () {
  "use strict";

  // 1) Add loading="lazy" + decoding="async" to images missing it (safe default)
  const imgs = Array.from(document.images || []);
  imgs.forEach((img, idx) => {
    // keep above-the-fold hero images eager
    if (idx < 2 || img.hasAttribute('data-eager')) return;

    if (!img.hasAttribute('loading')) img.setAttribute('loading', 'lazy');
    if (!img.hasAttribute('decoding')) img.setAttribute('decoding', 'async');

    // Provide an empty alt if missing to satisfy basic accessibility.
    // Prefer adding meaningful alt in templates for important content.
    if (!img.hasAttribute('alt')) {
      img.setAttribute('alt', '');
      img.classList.add('gdy-alt-missing');
    }
  });

  // 2) Optional: warn in console about missing alt (dev aid)
  try {
    const missing = document.querySelectorAll('img.gdy-alt-missing');
    if (missing.length) {
      // eslint-disable-next-line no-console
      console.warn('[Godyar] Images missing alt attribute:', missing.length);
    }
  } catch (e) {}
})();
