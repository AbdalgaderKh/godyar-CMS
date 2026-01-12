<?php
declare(strict_types=1);

/**
 * maintenance.php
 * حراسة وضع الصيانة للواجهة الأمامية
 *
 * يعتمد على:
 *  - وجود ملف: GODYAR_ROOT . '/storage/maintenance.flag'
 *  - صفحة صيانة front-end:
 *      - GODYAR_ROOT . '/public/maintenance.php'  (لو موجودة)
 *      - أو GODYAR_ROOT . '/maintenance.php'      (بديل)
 *
 * المبدأ:
 *  - لو هناك maintenance.flag => الزوار العاديين يشاهدون صفحة الصيانة
 *  - لوحة التحكم /admin مستثناة
 *  - المستخدم الذي دوره admin أو superadmin مستثنى (يستطيع الدخول لمعاينة الموقع)
 */

if (!function_exists('godyar_is_admin_request')) {
    /**
     * هل الطلب الحالي يتبع لوحة التحكم؟
     */
    function godyar_is_admin_request(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        // just in case
        $uri = strtolower($uri);

        // لو مشروعك داخل /godyar/ فغالباً لوحة التحكم /godyar/admin
        if (strpos($uri, '/godyar/admin') !== false) {
            return true;
        }

        // احتياط لو المسار مختلف قليلاً
        if (strpos($uri, '/admin/') !== false || substr($uri, -6) === '/admin') {
            return true;
        }

        return false;
    }
}

if (!function_exists('godyar_current_user_role')) {
    function godyar_current_user_role(): string
    {
        if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
            return 'guest';
        }
        return (string)($_SESSION['user']['role'] ?? 'guest');
    }
}

if (!function_exists('godyar_maintenance_guard')) {
    /**
     * تنفيذ حراسة الصيانة للواجهة الأمامية
     */
    function godyar_maintenance_guard(): void
    {
        // لا نطبق على CLI
        if (PHP_SAPI === 'cli') {
            return;
        }

        // لا نطبق على لوحة التحكم
        if (godyar_is_admin_request()) {
            return;
        }

        // نسمح للمشرفين بتجاوز وضع الصيانة
        $role = godyar_current_user_role();
        if (in_array($role, ['admin', 'superadmin'], true)) {
            return;
        }

        // ملف العلم
        $flagFile = GODYAR_ROOT . '/storage/maintenance.flag';
        if (!is_file($flagFile)) {
            return; // لا يوجد وضع صيانة
        }

        // تحديد مسار صفحة الصيانة
        $maintenance1 = GODYAR_ROOT . '/public/maintenance.php';
        $maintenance2 = GODYAR_ROOT . '/maintenance.php';

        // نرسل كود 503 لمحركات البحث
        if (!headers_sent()) {
            header('HTTP/1.1 503 Service Unavailable');
            header('Retry-After: 3600'); // ساعة
        }

        if (is_file($maintenance1)) {
            require $maintenance1;
        } elseif (is_file($maintenance2)) {
            require $maintenance2;
        } else {
            // صفحة بسيطة احتياطية
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>الموقع في وضع الصيانة</title>';
            echo '<style>body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#020617;color:#e5e7eb;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}';
            echo '.box{background:#0f172a;border-radius:1rem;padding:2rem;border:1px solid #1f2937;max-width:420px;text-align:center}h1{font-size:1.4rem;margin-bottom:.5rem}p{color:#9ca3af;font-size:.9rem}</style>';
            echo '</head><body><div class="box"><h1>الموقع في وضع الصيانة</h1><p>نقوم حالياً ببعض التحديثات الفنية. يُرجى المحاولة لاحقاً.</p></div></body></html>';
        }

        exit;
    }
}

// تشغيل الحراسة فور تحميل الملف
godyar_maintenance_guard();
