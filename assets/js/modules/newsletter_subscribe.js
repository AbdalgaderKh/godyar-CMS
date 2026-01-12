/* Newsletter subscribe (AJAX) */
(function () {
  function qs(sel, root) { return (root || document).querySelector(sel); }

  document.addEventListener('DOMContentLoaded', function () {
    var form = qs('[data-newsletter-form]');
    if (!form) return;

    var msg = qs('[data-newsletter-msg]', form.parentElement || document);
    var input = qs('input[name="newsletter_email"]', form);

    function setMsg(text, ok) {
      if (!msg) return;
      msg.textContent = text || '';
      msg.style.color = ok ? 'var(--text-strong, #111)' : 'var(--danger, #b91c1c)';
    }

    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      var email = (input && input.value) ? input.value.trim() : '';
      if (!email) { setMsg('أدخل بريدك الإلكتروني', false); return; }

      // Basic email validation
      var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!re.test(email)) { setMsg('البريد الإلكتروني غير صحيح', false); return; }

      setMsg('جاري الاشتراك...', true);

      try {
        var res = await fetch(form.getAttribute('action') || '/api/newsletter/subscribe', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ newsletter_email: email })
        });

        var data = null;
        try { data = await res.json(); } catch (_) {}

        if (!res.ok || !data || !data.ok) {
          setMsg((data && data.message) ? data.message : 'تعذر الاشتراك الآن، حاول لاحقًا.', false);
          return;
        }

        setMsg(data.message || 'تم الاشتراك بنجاح ✅', true);
        if (input) input.value = '';
      } catch (err) {
        setMsg('تعذر الاتصال بالخادم. حاول لاحقًا.', false);
      }
    });
  });
})();
