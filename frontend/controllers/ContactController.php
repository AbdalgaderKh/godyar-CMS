<?php
declare(strict_types=1);

// /godyar/frontend/controllers/ContactController.php

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/TemplateEngine.php';
require_once __DIR__ . '/../../includes/site_settings.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$pdo = gdy_pdo_safe();

// Schema-tolerant helper: check if column exists (cached)
if (!function_exists('gdy_has_column')) {
    function gdy_has_column(PDO $pdo, string $table, string $column): bool {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return (bool)$cache[$key];
        }
        try {
            $db = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
            if ($db === '') {
                return $cache[$key] = false;
            }
            $stmt = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
            $stmt->execute([$db, $table, $column]);
            return $cache[$key] = (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return $cache[$key] = false;
        }
    }
}
// تحميل الإعدادات والخيارات الأمامية
$settings        = gdy_load_settings($pdo);
$frontendOptions = gdy_prepare_frontend_options($settings);
extract($frontendOptions, EXTR_OVERWRITE);

// حالة المستخدم
$user       = $_SESSION['user'] ?? null;
$isLoggedIn = !empty($user);
$isAdmin    = $isLoggedIn && (($user['role'] ?? '') === 'admin');

// تحميل تصنيفات الهيدر
$headerCategories = [];
try {
    if ($pdo instanceof PDO) {
        // Prevent undefined variable + ensure ORDER BY is always valid.
        // Prefer explicit ordering columns if they exist, otherwise fallback to latest.
        $orderBy = 'id DESC';
        if (function_exists('gdy_has_column')) {
            if (gdy_has_column($pdo, 'categories', 'sort_order')) {
                $orderBy = 'sort_order ASC, id DESC';
            } elseif (gdy_has_column($pdo, 'categories', 'position')) {
                $orderBy = 'position ASC, id DESC';
            } elseif (gdy_has_column($pdo, 'categories', 'created_at')) {
                $orderBy = 'created_at DESC, id DESC';
            }
        }
        $stmt = $pdo->query("SELECT id, name, slug FROM categories ORDER BY {$orderBy} LIMIT 8");
        $headerCategories = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {
    @error_log('[Contact] categories error: ' . $e->getMessage());
}

if (function_exists('base_url')) {
    $baseUrl = rtrim(base_url(), '/');
} else {
    $baseUrl = '/godyar';
}

/**
 * معالجة إرسال النموذج (POST)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim((string)($_POST['name'] ?? ''));
    $email   = trim((string)($_POST['email'] ?? ''));
    $subject = trim((string)($_POST['subject'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));

    $errors = [];

    if ($name === '') {
        $errors[] = 'الاسم مطلوب.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'البريد الإلكتروني غير صالح.';
    }
    if ($message === '') {
        $errors[] = 'نص الرسالة مطلوب.';
    }

    if ($errors) {
        $_SESSION['contact_errors'] = $errors;
        $_SESSION['contact_old'] = [
            'name'    => $name,
            'email'   => $email,
            'subject' => $subject,
            'message' => $message,
        ];

        header('Location: ' . $baseUrl . '/contact.php');
        exit;
    }

    // تخزين الرسالة في قاعدة البيانات
    if ($pdo instanceof PDO) {
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS contact_messages (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(190) NOT NULL,
                    email VARCHAR(190) NOT NULL,
                    subject VARCHAR(255) NULL,
                    message TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $stmt = $pdo->prepare("
                INSERT INTO contact_messages (name, email, subject, message)
                VALUES (:name, :email, :subject, :message)
            ");
            $stmt->execute([
                ':name'    => $name,
                ':email'   => $email,
                ':subject' => $subject,
                ':message' => $message,
            ]);
        } catch (Throwable $e) {
            @error_log('[Contact] DB error: ' . $e->getMessage());
        }
    }

    /**
     * إرسال بريد للإدارة
     * - الإيميل يُؤخذ من إعدادات الموقع contact_email
     * - لو غير موجود، يمكنك تعديله هنا إلى إيميل ثابت
     */
    try {
        // الحصول على بريد الإدارة من جدول settings
        $adminEmail = '';
        if (function_exists('gdy_setting')) {
            $adminEmail = gdy_setting($settings, 'contact_email', '');
        } else {
            $adminEmail = (string)($settings['contact_email'] ?? '');
        }

        // لو فيه إيميل إدارة صحيح → نحاول الإرسال
        if ($adminEmail && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            // عنوان البريد (مع دعم العربية)
            $mailSubject = 'رسالة جديدة من نموذج اتصل بنا - ' . ($siteName ?? 'Godyar');
            $encodedSubject = '=?UTF-8?B?' . base64_encode($mailSubject) . '?=';

            // محتوى الرسالة
            $bodyLines = [
                "تم استلام رسالة جديدة من نموذج اتصل بنا في الموقع:",
                "",
                "الاسم: {$name}",
                "البريد الإلكتروني: {$email}",
                "الموضوع: " . ($subject !== '' ? $subject : 'بدون موضوع'),
                "",
                "نص الرسالة:",
                $message,
                "",
                "----------------------------",
                "تاريخ الإرسال: " . date('Y-m-d H:i:s'),
                "عنوان الموقع: " . ($baseUrl ?: ''),
            ];
            $body = implode("\n", $bodyLines);

            // من الأفضل أن يكون الـ From من نفس الدومين (لتقليل نسبة السبام)
            // يمكنك تعديل no-reply@oddoarabic.com إلى إيميل حقيقي على نفس الدومين
            $fromEmail = 'no-reply@oddoarabic.com';
            $fromName  = $siteName ?? 'Godyar';

            $headers  = 'From: ' . sprintf('"%s" <%s>', '=?UTF-8?B?' . base64_encode($fromName) . '?=', $fromEmail) . "\r\n";
            $headers .= 'Reply-To: ' . $email . "\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $headers .= "MIME-Version: 1.0\r\n";

            // نحاول الإرسال (ولو فشل لا نوقف المستخدم)
            @mail($adminEmail, $encodedSubject, $body, $headers);
        }
    } catch (Throwable $e) {
        @error_log('[Contact] mail error: ' . $e->getMessage());
    }

    // رسالة نجاح وإرجاع لصفحة اتصل بنا
    $_SESSION['contact_success'] = 'تم إرسال رسالتك بنجاح، سنقوم بالرد عليك في أقرب وقت ممكن.';
    header('Location: ' . $baseUrl . '/contact.php');
    exit;
}

/**
 * عرض الصفحة (GET)
 */

// رسائل من الجلسة
$contactErrors  = $_SESSION['contact_errors']  ?? [];
$contactOld     = $_SESSION['contact_old']     ?? [];
$contactSuccess = $_SESSION['contact_success'] ?? null;

unset($_SESSION['contact_errors'], $_SESSION['contact_old'], $_SESSION['contact_success']);

// تمثيل "صفحة ثابتة" حتى نعيد استخدام نفس القالب page/content.php
$page = [
    'slug'       => 'contact',
    'title'      => 'اتصل بنا',
    'content'    => '',
    'created_at' => date('Y-m-d'),
    'updated_at' => null,
];

$pageNotFound = false;

// تجهيز بيانات القالب
$templateData = [
    // إعدادات الهوية
    'siteName'        => $siteName,
    'siteTagline'     => $siteTagline,
    'siteLogo'        => $siteLogo,
    'primaryColor'    => $primaryColor,
    'primaryDark'     => $primaryDark,
    'baseUrl'         => $baseUrl,
    'themeClass'      => $themeClass,

    // نصوص الواجهة
    'searchPlaceholder'        => $searchPlaceholder,
    'homeLatestTitle'          => $homeLatestTitle,
    'homeFeaturedTitle'        => $homeFeaturedTitle,
    'homeTabsTitle'            => $homeTabsTitle,
    'homeMostReadTitle'        => $homeMostReadTitle,
    'homeMostCommentedTitle'   => $homeMostCommentedTitle,
    'homeRecommendedTitle'     => $homeRecommendedTitle,
    'carbonBadgeText'          => $carbonBadgeText,
    'showCarbonBadge'          => $showCarbonBadge,

    // المستخدم
    'isLoggedIn'      => $isLoggedIn,
    'isAdmin'         => $isAdmin,

    // التصنيفات للهيدر
    'headerCategories'=> $headerCategories,

    // بيانات الصفحة الثابتة
    'page'            => $page,
    'pageNotFound'    => $pageNotFound,

    // رسائل الاتصال
    'contactErrors'   => $contactErrors,
    'contactOld'      => $contactOld,
    'contactSuccess'  => $contactSuccess,
];

// عرض القالب
$template = new TemplateEngine();
$template->render(__DIR__ . '/../views/page/content.php', $templateData);
