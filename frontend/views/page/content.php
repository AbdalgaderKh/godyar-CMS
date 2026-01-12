<?php
// /godyar/frontend/views/page/content.php

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// الهيدر الموحد (قد يتم حقنه مسبقاً عبر TemplateEngine)
if (!defined('GDY_TPL_WRAPPED')) {
    require __DIR__ . '/../partials/header.php';
}

// نتوقع وجود المتغيرات: $page, $pageNotFound, $baseUrl, $themeClass, ...
$slug = $page['slug'] ?? '';

$contactErrors  = $contactErrors  ?? [];
$contactSuccess = $contactSuccess ?? null;
$contactOld     = $contactOld     ?? [];
?>

<?php if ($slug === 'contact'): ?>

  <style>
    .gdy-contact-wrapper{
      background:#fff;
      border-radius:18px;
      border:1px solid #d5e3f0;
      box-shadow:0 12px 25px rgba(15,23,42,0.08);
      padding:24px 24px 26px;
      font-size:.9rem;
      color:#111827;
    }
    .gdy-contact-title{
      font-size:1.4rem;
      font-weight:700;
      text-align:right;
      margin-bottom:6px;
    }
    .gdy-contact-sub{
      font-size:.9rem;
      color:#6b7280;
      margin-bottom:16px;
    }
    .gdy-contact-grid{
      display:grid;
      grid-template-columns:minmax(0,1.4fr) minmax(0,1.6fr);
      gap:22px;
      margin-top:10px;
      align-items:flex-start;
    }
    @media (max-width:900px){
      .gdy-contact-grid{
        grid-template-columns:minmax(0,1fr);
      }
    }
    .gdy-contact-info{
      border-inline-end:1px solid #e5e7eb;
      padding-inline-end:18px;
    }
    @media (max-width:900px){
      .gdy-contact-info{
        border-inline-end:none;
        border-bottom:1px solid #e5e7eb;
        padding-inline-end:0;
        padding-bottom:16px;
        margin-bottom:10px;
      }
    }
    .gdy-contact-social{
      display:flex;
      gap:10px;
      margin:10px 0 18px;
      font-size:1rem;
    }
    .gdy-contact-social a{
      width:30px;
      height:30px;
      border-radius:999px;
      border:1px solid #d1d5db;
      display:flex;
      align-items:center;
      justify-content:center;
      color:#374151;
    }
    .gdy-contact-social a:hover{
      border-color:var(--primary, #0ea5e9);
      color:var(--primary, #0ea5e9);
      transform:translateY(-1px);
    }
    .gdy-contact-block{
      margin-bottom:14px;
    }
    .gdy-contact-block-title{
      font-weight:600;
      margin-bottom:4px;
      display:flex;
      align-items:center;
      gap:6px;
      color:#111827;
    }
    .gdy-contact-block-title i{
      color:#ef4444;
    }
    .gdy-contact-block p{
      margin:0;
      color:#4b5563;
      font-size:.87rem;
      line-height:1.7;
    }
    .gdy-contact-block a{
      color:#0ea5e9;
      word-break:break-all;
    }
    .gdy-contact-form label{
      font-size:.8rem;
      font-weight:600;
      color:#374151;
      margin-bottom:4px;
    }
    .gdy-contact-form .form-control{
      border-radius:10px;
      border:1px solid #d1d5db;
      font-size:.87rem;
    }
    .gdy-contact-form .form-control:focus{
      border-color:var(--primary, #0ea5e9);
      box-shadow:0 0 0 2px rgba(14,165,233,.25);
    }
    .gdy-contact-form .required{
      color:#ef4444;
    }
    .gdy-contact-submit{
      margin-top:10px;
      text-align:left;
    }
    .gdy-contact-submit button{
      min-width:110px;
      border-radius:999px;
      border:none;
      padding:7px 20px;
      background:#e11d48;
      color:#fff;
      font-weight:600;
      font-size:.9rem;
      cursor:pointer;
    }
    .gdy-contact-submit button:hover{
      filter:brightness(1.05);
      box-shadow:0 6px 15px rgba(190,18,60,.35);
    }

    .gdy-alert{
      border-radius:12px;
      padding:10px 12px;
      font-size:.85rem;
      margin-bottom:12px;
    }
    .gdy-alert-success{
      background:#ecfdf3;
      border:1px solid #22c55e;
      color:#166534;
    }
    .gdy-alert-error{
      background:#fef2f2;
      border:1px solid #ef4444;
      color:#b91c1c;
    }
    .gdy-alert-error ul{
      margin:0;
      padding-right:18px;
    }
  </style>

  <div class="gdy-contact-wrapper fade-in">
    <div class="d-flex justify-content-between align-items-baseline mb-2">
      <div>
        <h1 class="gdy-contact-title">اتصل بنا</h1>
        <p class="gdy-contact-sub">
          قم بالتواصل معنا من خلال وسائل التواصل الاجتماعي، أو قم بإرسال رسالة عبر النموذج التالي.
        </p>
      </div>
    </div>

    <!-- رسائل النجاح / الخطأ -->
    <?php if (!empty($contactSuccess)): ?>
      <div class="gdy-alert gdy-alert-success">
        <?= h($contactSuccess) ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($contactErrors)): ?>
      <div class="gdy-alert gdy-alert-error">
        <strong>حدثت الأخطاء التالية:</strong>
        <ul>
          <?php foreach ($contactErrors as $err): ?>
            <li><?= h($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="gdy-contact-grid">
      <!-- عمود المعلومات -->
      <div class="gdy-contact-info">
        <div class="gdy-contact-social">
          <a href="#" aria-label="YouTube"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#youtube"></use></svg></a>
          <a href="#" aria-label="Instagram"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#instagram"></use></svg></a>
          <a href="#" aria-label="LinkedIn"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></a>
          <a href="#" aria-label="Twitter"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#x"></use></svg></a>
          <a href="#" aria-label="Facebook"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#facebook"></use></svg></a>
        </div>

        <div class="gdy-contact-block">
          <div class="gdy-contact-block-title">
            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <span>العنوان</span>
          </div>
          <p>
            يمكنك هنا وضع عنوان شركتك أو مقر موقعك، مثال:<br>
            Building 3, twofour54 P.O.Box 77866, Abu Dhabi, United Arab Emirates
          </p>
        </div>

        <div class="gdy-contact-block">
          <div class="gdy-contact-block-title">
            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <span>اتصل معنا عبر</span>
          </div>
          <p>
            الهاتف: +971 2 491 4988<br>
            الفاكس: +971 2 491 4828
          </p>
        </div>

        <div class="gdy-contact-block">
          <div class="gdy-contact-block-title">
            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <span>أرسل بريدك الإلكتروني إلى</span>
          </div>
          <p>
            <a href="mailto:info@example.com">info@example.com</a>
          </p>
        </div>
      </div>

      <!-- عمود النموذج -->
      <div>
        <form class="gdy-contact-form" method="post" action="<?= h($baseUrl) ?>
          <?php if (function_exists('csrf_field')) { csrf_field(); } ?>/contact.php">
          <div class="row g-2 mb-2">
            <div class="col-md-6">
              <label for="contact_name">
                الاسم <span class="required">*</span>
              </label>
              <input
                type="text"
                name="name"
                id="contact_name"
                class="form-control"
                required
                value="<?= h($contactOld['name'] ?? '') ?>"
              >
            </div>
            <div class="col-md-6">
              <label for="contact_email">
                البريد الإلكتروني <span class="required">*</span>
              </label>
              <input
                type="email"
                name="email"
                id="contact_email"
                class="form-control"
                required
                value="<?= h($contactOld['email'] ?? '') ?>"
              >
            </div>
          </div>

          <div class="mb-2">
            <label for="contact_subject">
              الموضوع (اختياري)
            </label>
            <input
              type="text"
              name="subject"
              id="contact_subject"
              class="form-control"
              value="<?= h($contactOld['subject'] ?? '') ?>"
            >
          </div>

          <div class="mb-2">
            <label for="contact_message">
              النص <span class="required">*</span>
            </label>
            <textarea
              name="message"
              id="contact_message"
              rows="8"
              class="form-control"
              required
            ><?= h($contactOld['message'] ?? '') ?></textarea>
          </div>

          <div class="gdy-contact-submit">
            <button type="submit">
              أرسل
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

<?php else: ?>

  <div class="fade-in">
    <div class="hero-card" style="margin-bottom:18px;">
      <div class="hero-badge">
        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
        <span><?= $pageNotFound ? 'صفحة غير موجودة' : 'صفحة ثابتة' ?></span>
      </div>
      <h1 class="hero-title"><?= h($page['title'] ?? '') ?></h1>
      <div class="hero-meta">
        <span>
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          <?= h(date('Y-m-d', strtotime($page['created_at'] ?? 'now'))) ?>
        </span>
        <?php if (!empty($page['updated_at']) && $page['updated_at'] !== $page['created_at']): ?>
          <span>
            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            آخر تحديث: <?= h(date('Y-m-d', strtotime($page['updated_at']))) ?>
          </span>
        <?php endif; ?>
      </div>
    </div>

    <div class="gdy-page-content" style="
        background:#ffffff;
        border-radius:18px;
        border:1px solid #d5e3f0;
        box-shadow:0 12px 25px rgba(15,23,42,0.08);
        padding:20px 18px;
        font-size:.92rem;
        color:#0f172a;
    ">
      <?php echo $page['content'] ?? ''; ?>
    </div>
  </div>

<?php endif; ?>

<?php
// الفوتر الموحد (قد يتم حقنه مسبقاً عبر TemplateEngine)
if (!defined('GDY_TPL_WRAPPED')) {
    require __DIR__ . '/../partials/footer.php';
}
