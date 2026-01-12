Godyar CMS v10 - Patch (Compatibility Fixes)
==========================================

هذا التحديث يعالج تحذيرات التوافق التالية في Webhint:
1) Safari: إضافة -webkit-backdrop-filter للأزرار ذات blur.
2) ترتيب backdrop-filter بعد -webkit-backdrop-filter في الهيدر.
3) text-size-adjust: استبدالها بخصائص vendor prefixes لتجنب تحذيرات Firefox/Safari.

الملفات المتأثرة:
- frontend/views/home_modern.php
  - .hm-featured-play
  - .hm-featured-thumb__play
- frontend/views/partials/header.php
  - .site-header (ترتيب backdrop-filter)
- assets/css/pwa.css
  - html { -webkit-text-size-adjust / -moz-text-size-adjust }

طريقة التركيب:
1) ارفع محتويات هذا الباتش إلى جذر الموقع مع الاستبدال.
2) امسح كاش المتصفح (Hard Refresh).
3) إذا لديك Service Worker: قم بعمل Unregister ثم Refresh.

ملاحظة:
تحذير meta[name=theme-color] في Firefox طبيعي (Firefox لا يعتمدها في بعض البيئات) ولا يؤثر على الموقع.
تحذير CSP unsafe-eval: هذا غالباً علامة حماية جيدة. لا ننصح بإضافة unsafe-eval إلا إذا كانت هناك ميزة مكسورة وتأكدت أي ملف JS يحتاجها.

