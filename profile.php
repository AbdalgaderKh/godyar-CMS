<?php
declare(strict_types=1);

/**
 * صفحة الملف الشخصي (/profile)
 * الميزات:
 * - تحديث بيانات الحساب الأساسية + صورة شخصية + صورة غلاف
 * - روابط اجتماعية وموقع إلكتروني
 * - معلومات إضافية (الدولة/المدينة/الوظيفة/تاريخ الميلاد/النوع)
 * - إعدادات خصوصية بسيطة (إظهار البريد/الهاتف) + الاشتراك بالنشرة
 * - تغيير البريد / كلمة المرور
 */

require_once __DIR__ . '/includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

// تحميل HomeController حتى يعمل الهيدر/الفوتر (إعدادات الموقع)
$hc = __DIR__ . '/frontend/controllers/HomeController.php';
if (is_file($hc)) {
    require_once $hc;
}

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$baseUrl = function_exists('base_url') ? rtrim(base_url(), '/') : '';
$lang = function_exists('gdy_lang') ? (string)gdy_lang() : (isset($_GET['lang']) ? (string)$_GET['lang'] : 'ar');
$navBaseUrl = ($baseUrl !== '' ? $baseUrl : '') . '/' . trim($lang, '/');
if ($baseUrl === '') { $navBaseUrl = '/' . trim($lang, '/'); }


// يجب أن يكون المستخدم مسجلاً
$currentUser = $_SESSION['user'] ?? null;
if (!is_array($currentUser) || empty($currentUser['id'])) {
    $next = '/profile';
    header('Location: ' . rtrim($navBaseUrl, '/') . '/login?next=' . rawurlencode($next));
    exit;
}

/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();

$uid = (int)$currentUser['id'];
$success = '';
$error = '';

// CSRF
$csrfToken = function_exists('generate_csrf_token') ? generate_csrf_token() : (string)($_SESSION['csrf_token'] ?? '');

/* =========================
   DB helpers
   ========================= */

/**
 * تجهيز جدول user_profiles لو غير موجود + إضافة الأعمدة المفقودة.
 * (مفيد لاستضافات Shared Hosting بدون نظام migrations)
 */
function ensure_user_profiles_table(PDO $pdo): bool {
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS user_profiles (\n"
            . "  user_id INT NOT NULL PRIMARY KEY,\n"
            . "  phone VARCHAR(50) NULL,\n"
            . "  address VARCHAR(255) NULL,\n"
            . "  bio TEXT NULL,\n"
            . "  avatar VARCHAR(255) NULL,\n"
            . "  cover VARCHAR(255) NULL,\n"
            . "  full_name VARCHAR(120) NULL,\n"
            . "  job_title VARCHAR(120) NULL,\n"
            . "  country VARCHAR(80) NULL,\n"
            . "  city VARCHAR(80) NULL,\n"
            . "  gender VARCHAR(20) NULL,\n"
            . "  birthdate DATE NULL,\n"
            . "  website VARCHAR(255) NULL,\n"
            . "  facebook VARCHAR(255) NULL,\n"
            . "  x_url VARCHAR(255) NULL,\n"
            . "  instagram VARCHAR(255) NULL,\n"
            . "  youtube VARCHAR(255) NULL,\n"
            . "  telegram VARCHAR(255) NULL,\n"
            . "  whatsapp VARCHAR(80) NULL,\n"
            . "  show_email TINYINT(1) NOT NULL DEFAULT 0,\n"
            . "  show_phone TINYINT(1) NOT NULL DEFAULT 0,\n"
            . "  newsletter TINYINT(1) NOT NULL DEFAULT 1,\n"
            . "  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // إضافة أعمدة مفقودة (لو الجدول موجود سابقاً بنسخة قديمة)
        $addCols = [
            'cover'      => "VARCHAR(255) NULL AFTER avatar",
            'full_name'  => "VARCHAR(120) NULL AFTER cover",
            'job_title'  => "VARCHAR(120) NULL AFTER full_name",
            'country'    => "VARCHAR(80) NULL AFTER job_title",
            'city'       => "VARCHAR(80) NULL AFTER country",
            'gender'     => "VARCHAR(20) NULL AFTER city",
            'birthdate'  => "DATE NULL AFTER gender",
            'website'    => "VARCHAR(255) NULL AFTER birthdate",
            'facebook'   => "VARCHAR(255) NULL AFTER website",
            'x_url'      => "VARCHAR(255) NULL AFTER facebook",
            'instagram'  => "VARCHAR(255) NULL AFTER x_url",
            'youtube'    => "VARCHAR(255) NULL AFTER instagram",
            'telegram'   => "VARCHAR(255) NULL AFTER youtube",
            'whatsapp'   => "VARCHAR(80) NULL AFTER telegram",
            'show_email' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER whatsapp",
            'show_phone' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER show_email",
            'newsletter' => "TINYINT(1) NOT NULL DEFAULT 1 AFTER show_phone",
        ];

        if (function_exists('db_column_exists')) {
            foreach ($addCols as $c => $ddl) {
                if (!db_column_exists($pdo, 'user_profiles', $c)) {
                    $pdo->exec("ALTER TABLE user_profiles ADD COLUMN {$c} {$ddl}");
                }
            }
        }

        return true;
    } catch (Throwable $e) {
        @error_log('[profile] ensure_user_profiles_table failed: ' . $e->getMessage());
        return false;
    }
}

function load_user_profile(PDO $pdo, int $uid): array {
    $out = [
        'phone' => '',
        'address' => '',
        'bio' => '',
        'avatar' => '',
        'cover' => '',
        'full_name' => '',
        'job_title' => '',
        'country' => '',
        'city' => '',
        'gender' => '',
        'birthdate' => '',
        'website' => '',
        'facebook' => '',
        'x_url' => '',
        'instagram' => '',
        'youtube' => '',
        'telegram' => '',
        'whatsapp' => '',
        'show_email' => 0,
        'show_phone' => 0,
        'newsletter' => 1,
    ];

    // 1) من users إن كانت الأعمدة موجودة (للتوافق)
    try {
        $cols = function_exists('db_table_columns') ? db_table_columns($pdo, 'users') : [];
        if (!empty($cols)) {
            $select = ['id','email','username'];
            foreach (['phone','address','bio','avatar','cover','full_name','job_title','country','city','gender','birthdate','website'] as $c) {
                if (in_array($c, $cols, true)) $select[] = $c;
            }
            $sql = 'SELECT ' . implode(',', array_map(fn($c)=>"`{$c}`", $select)) . ' FROM users WHERE id = :id LIMIT 1';
            $st = $pdo->prepare($sql);
            $st->execute([':id' => $uid]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            foreach ($out as $k => $v) {
                if (array_key_exists($k, $row) && $row[$k] !== null) {
                    $out[$k] = is_numeric($v) ? (int)$row[$k] : (string)$row[$k];
                }
            }
        }
    } catch (Throwable $e) {
        // ignore
    }

    // 2) من user_profiles (يكمل / يطغى على الفارغ)
    try {
        if (ensure_user_profiles_table($pdo)) {
            $st = $pdo->prepare('SELECT * FROM user_profiles WHERE user_id = :id LIMIT 1');
            $st->execute([':id' => $uid]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            foreach ($out as $k => $v) {
                if (isset($row[$k]) && $row[$k] !== null) {
                    if (is_int($v)) $out[$k] = (int)$row[$k];
                    else $out[$k] = (string)$row[$k];
                }
            }
        }
    } catch (Throwable $e) {
        // ignore
    }

    // birthdate كـ string آمن
    if (!empty($out['birthdate'])) {
        $out['birthdate'] = substr((string)$out['birthdate'], 0, 10);
    }

    return $out;
}

function verify_password_compat(string $plain, string $stored): bool {
    if ($stored === '') return false;
    if (password_verify($plain, $stored)) return true;
    if (md5($plain) === $stored) return true;
    if (sha1($plain) === $stored) return true;
    if ($plain === $stored) return true;
    return false;
}

/* =========================
   Load user
   ========================= */

$userRow = [];
$profile = [
    'phone'=>'','address'=>'','bio'=>'','avatar'=>'','cover'=>'',
    'full_name'=>'','job_title'=>'','country'=>'','city'=>'','gender'=>'','birthdate'=>'',
    'website'=>'','facebook'=>'','x_url'=>'','instagram'=>'','youtube'=>'','telegram'=>'','whatsapp'=>'',
    'show_email'=>0,'show_phone'=>0,'newsletter'=>1
];

$stats = [
    'bookmarks' => 0,
    'comments'  => 0,
];

if ($pdo instanceof PDO) {
    try {
        $st = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $st->execute([':id' => $uid]);
        $userRow = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $profile = load_user_profile($pdo, $uid);

        // إحصائيات بسيطة
        try {
            $chk = $pdo->query("SHOW TABLES LIKE 'user_bookmarks'");
            if ($chk && $chk->fetchColumn()) {
                $st2 = $pdo->prepare("SELECT COUNT(*) FROM user_bookmarks WHERE user_id = :u");
                $st2->execute([':u' => $uid]);
                $stats['bookmarks'] = (int)$st2->fetchColumn();
            }
        } catch (Throwable $e) { /* ignore */ }

        try {
            $chk = $pdo->query("SHOW TABLES LIKE 'news_comments'");
            if ($chk && $chk->fetchColumn()) {
                $st2 = $pdo->prepare("SELECT COUNT(*) FROM news_comments WHERE user_id = :u");
                $st2->execute([':u' => $uid]);
                $stats['comments'] = (int)$st2->fetchColumn();
            }
        } catch (Throwable $e) { /* ignore */ }

    } catch (Throwable $e) {
        $userRow = [];
    }
}

/* =========================
   POST handlers
   ========================= */

function clean_url(string $url): string {
    $url = trim($url);
    if ($url === '') return '';
    // السماح بإدخال بدون scheme: example.com
    if (!preg_match('~^https?://~i', $url)) {
        $url = 'https://' . $url;
    }
    return $url;
}

function is_valid_url_or_empty(string $url): bool {
    if ($url === '') return true;
    return (bool)filter_var($url, FILTER_VALIDATE_URL);
}

function parse_birthdate(string $v): string {
    $v = trim($v);
    if ($v === '') return '';
    // expected Y-m-d
    $dt = DateTime::createFromFormat('Y-m-d', $v);
    if (!$dt) return '';
    // sanity range
    $y = (int)$dt->format('Y');
    if ($y < 1900 || $y > (int)date('Y')) return '';
    return $dt->format('Y-m-d');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    if (function_exists('validate_csrf_token') && !validate_csrf_token($postedToken)) {
        $error = 'انتهت صلاحية الجلسة أو حدث خطأ في التحقق. حدّث الصفحة وحاول مرة أخرى.';
    } elseif (!$pdo instanceof PDO) {
        $error = 'لا يمكن الاتصال بقاعدة البيانات حالياً.';
    } else {
        $action = (string)($_POST['action'] ?? '');

        try {
            // ===== تحديث البريد =====
            if ($action === 'update_email') {
                $newEmail = strtolower(trim((string)($_POST['email'] ?? '')));
                if ($newEmail === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('يرجى إدخال بريد إلكتروني صحيح.');
                }
                $st = $pdo->prepare('SELECT id FROM users WHERE email = :e AND id <> :id LIMIT 1');
                $st->execute([':e' => $newEmail, ':id' => $uid]);
                if ($st->fetchColumn()) {
                    throw new RuntimeException('هذا البريد مستخدم بالفعل.');
                }
                $upd = $pdo->prepare('UPDATE users SET email = :e WHERE id = :id');
                $upd->execute([':e' => $newEmail, ':id' => $uid]);

                // تحديث الجلسة
                $currentUser['email'] = $newEmail;
                if (function_exists('auth_set_user_session')) {
                    auth_set_user_session(array_merge($currentUser, ['id'=>$uid]));
                } else {
                    $_SESSION['user']['email'] = $newEmail;
                    $_SESSION['user_email'] = $newEmail;
                }

                $success = 'تم تحديث البريد الإلكتروني بنجاح.';
            }

            // ===== تحديث كلمة المرور =====
            elseif ($action === 'update_password') {
                $current = (string)($_POST['current_password'] ?? '');
                $new1    = (string)($_POST['new_password'] ?? '');
                $new2    = (string)($_POST['confirm_password'] ?? '');

                if ($current === '' || $new1 === '' || $new2 === '') {
                    throw new RuntimeException('يرجى تعبئة جميع حقول كلمة المرور.');
                }
                if ($new1 !== $new2) {
                    throw new RuntimeException('كلمتا المرور غير متطابقتين.');
                }
                if (mb_strlen($new1) < 8) {
                    throw new RuntimeException('كلمة المرور يجب ألا تقل عن 8 أحرف.');
                }
                $stored = (string)($userRow['password_hash'] ?? ($userRow['password'] ?? ''));
                if (!verify_password_compat($current, $stored)) {
                    throw new RuntimeException('كلمة المرور الحالية غير صحيحة.');
                }

                $newHash = password_hash($new1, PASSWORD_DEFAULT);
                $cols = function_exists('db_table_columns') ? db_table_columns($pdo, 'users') : [];
                if (in_array('password_hash', $cols, true)) {
                    $upd = $pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
                    $upd->execute([':h' => $newHash, ':id' => $uid]);
                } else {
                    $upd = $pdo->prepare('UPDATE users SET password = :h WHERE id = :id');
                    $upd->execute([':h' => $newHash, ':id' => $uid]);
                }
                $success = 'تم تغيير كلمة المرور بنجاح.';
            }

            // ===== تحديث بيانات الملف الشخصي =====
            elseif ($action === 'update_profile') {
                $displayName = trim((string)($_POST['display_name'] ?? ''));

                $phone   = trim((string)($_POST['phone'] ?? ''));
                $address = trim((string)($_POST['address'] ?? ''));
                $bio     = trim((string)($_POST['bio'] ?? ''));

                // حقول جديدة
                $fullName = trim((string)($_POST['full_name'] ?? ''));
                $jobTitle = trim((string)($_POST['job_title'] ?? ''));
                $country  = trim((string)($_POST['country'] ?? ''));
                $city     = trim((string)($_POST['city'] ?? ''));
                $gender   = trim((string)($_POST['gender'] ?? ''));
                $birthdate= parse_birthdate((string)($_POST['birthdate'] ?? ''));

                $website   = clean_url((string)($_POST['website'] ?? ''));
                $facebook  = clean_url((string)($_POST['facebook'] ?? ''));
                $xUrl      = clean_url((string)($_POST['x_url'] ?? ''));
                $instagram = clean_url((string)($_POST['instagram'] ?? ''));
                $youtube   = clean_url((string)($_POST['youtube'] ?? ''));
                $telegram  = trim((string)($_POST['telegram'] ?? '')); // قد يكون @username أو رابط
                $whatsapp  = trim((string)($_POST['whatsapp'] ?? '')); // رقم أو رابط wa.me

                $showEmail = isset($_POST['show_email']) ? 1 : 0;
                $showPhone = isset($_POST['show_phone']) ? 1 : 0;
                $newsletter= isset($_POST['newsletter']) ? 1 : 0;

                // تحقق من اسم الظهور (اختياري)
                if ($displayName !== '') {
                    if (mb_strlen($displayName, 'UTF-8') < 2 || mb_strlen($displayName, 'UTF-8') > 50) {
                        throw new RuntimeException('اسم الظهور يجب أن يكون بين 2 و 50 حرف.');
                    }
                    // نسمح بالحروف (بما فيها العربية) + الأرقام + العلامات (مثل التشكيل) + بعض الرموز الشائعة.
                    // الهدف: تجنب XSS مع عدم كسر أسماء عربية تحتوي على تشكيل أو فاصلة عليا.
                    if (!preg_match('/^[\p{L}\p{M}\p{N}._\-\'’]+(?:\s+[\p{L}\p{M}\p{N}._\-\'’]+)*$/u', $displayName)) {
                        throw new RuntimeException('اسم الظهور يحتوي على رموز غير مسموحة. المسموح: حروف/أرقام/مسافات و . _ - \'');
                    }
                }

                // تحقق من الحقول الجديدة
                if ($fullName !== '' && (mb_strlen($fullName, 'UTF-8') < 2 || mb_strlen($fullName, 'UTF-8') > 120)) {
                    throw new RuntimeException('الاسم الكامل يجب أن يكون بين 2 و 120 حرف.');
                }
                if ($jobTitle !== '' && (mb_strlen($jobTitle, 'UTF-8') < 2 || mb_strlen($jobTitle, 'UTF-8') > 120)) {
                    throw new RuntimeException('المسمى الوظيفي يجب أن يكون بين 2 و 120 حرف.');
                }
                if (!in_array($gender, ['', 'male', 'female', 'other'], true)) {
                    $gender = '';
                }

                foreach (['الموقع الإلكتروني'=>$website,'فيسبوك'=>$facebook,'X'=>$xUrl,'إنستغرام'=>$instagram,'يوتيوب'=>$youtube] as $label => $u) {
                    if (!is_valid_url_or_empty($u)) {
                        throw new RuntimeException("حقل {$label} يحتوي على رابط غير صحيح.");
                    }
                }
                // telegram/whatsapp: نتحقق فقط من الطول
                if (mb_strlen($telegram,'UTF-8') > 255) $telegram = mb_substr($telegram,0,255,'UTF-8');
                if (mb_strlen($whatsapp,'UTF-8') > 80) $whatsapp = mb_substr($whatsapp,0,80,'UTF-8');

                // رفع الصورة الشخصية (اختياري)
                $avatarPath = (string)($profile['avatar'] ?? '');
                $removeAvatar = isset($_POST['remove_avatar']);
                if ($removeAvatar) $avatarPath = '';

                if (!empty($_FILES['avatar']['name']) && is_array($_FILES['avatar'])) {
                    $f = $_FILES['avatar'];
                    if (($f['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                        throw new RuntimeException('حدث خطأ أثناء رفع صورة الملف الشخصي.');
                    }
                    if (($f['size'] ?? 0) > 2 * 1024 * 1024) {
                        throw new RuntimeException('حجم صورة الملف الشخصي كبير (الحد الأقصى 2MB).');
                    }
                    $tmp = (string)($f['tmp_name'] ?? '');
                    $info = @getimagesize($tmp);
                    if (!$info) {
                        throw new RuntimeException('الملف المرفوع ليس صورة صحيحة.');
                    }
                    $mime = (string)($info['mime'] ?? '');
                    $extMap = [
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/gif'  => 'gif',
                        'image/webp' => 'webp',
                    ];
                    if (!isset($extMap[$mime])) {
                        throw new RuntimeException('صيغة صورة الملف الشخصي غير مدعومة.');
                    }

                    $dir = __DIR__ . '/uploads/avatars';
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0755, true);
                    }
                    $name = 'user_' . $uid . '_' . date('Ymd_His') . '.' . $extMap[$mime];
                    $dest = $dir . '/' . $name;
                    if (!@move_uploaded_file($tmp, $dest)) {
                        throw new RuntimeException('تعذر حفظ صورة الملف الشخصي.');
                    }
                    $avatarPath = 'uploads/avatars/' . $name;
                }

                // رفع صورة الغلاف (اختياري)
                $coverPath = (string)($profile['cover'] ?? '');
                $removeCover = isset($_POST['remove_cover']);
                if ($removeCover) $coverPath = '';

                if (!empty($_FILES['cover']['name']) && is_array($_FILES['cover'])) {
                    $f = $_FILES['cover'];
                    if (($f['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                        throw new RuntimeException('حدث خطأ أثناء رفع صورة الغلاف.');
                    }
                    if (($f['size'] ?? 0) > 4 * 1024 * 1024) {
                        throw new RuntimeException('حجم صورة الغلاف كبير (الحد الأقصى 4MB).');
                    }
                    $tmp = (string)($f['tmp_name'] ?? '');
                    $info = @getimagesize($tmp);
                    if (!$info) {
                        throw new RuntimeException('ملف الغلاف ليس صورة صحيحة.');
                    }
                    $mime = (string)($info['mime'] ?? '');
                    $extMap = [
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp',
                    ];
                    if (!isset($extMap[$mime])) {
                        throw new RuntimeException('صيغة صورة الغلاف غير مدعومة (JPG/PNG/WEBP فقط).');
                    }

                    $dir = __DIR__ . '/uploads/covers';
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0755, true);
                    }
                    $name = 'cover_' . $uid . '_' . date('Ymd_His') . '.' . $extMap[$mime];
                    $dest = $dir . '/' . $name;
                    if (!@move_uploaded_file($tmp, $dest)) {
                        throw new RuntimeException('تعذر حفظ صورة الغلاف.');
                    }
                    $coverPath = 'uploads/covers/' . $name;
                }

                // تحديث users إن كانت الأعمدة موجودة
                $cols = function_exists('db_table_columns') ? db_table_columns($pdo, 'users') : [];
                $set = [];
                $bind = [':id' => $uid];
                foreach ([
                    'display_name'=>$displayName,
                    'phone'=>$phone,
                    'address'=>$address,
                    'bio'=>$bio,
                    'avatar'=>$avatarPath,
                    'cover'=>$coverPath,
                    'full_name'=>$fullName,
                    'job_title'=>$jobTitle,
                    'country'=>$country,
                    'city'=>$city,
                    'gender'=>$gender,
                    'birthdate'=>$birthdate !== '' ? $birthdate : null,
                    'website'=>$website,
                ] as $col => $val) {
                    if (in_array($col, $cols, true)) {
                        $set[] = "`{$col}` = :{$col}";
                        $bind[":{$col}"] = $val;
                    }
                }
                if (!empty($set)) {
                    $sql = 'UPDATE users SET ' . implode(', ', $set) . ' WHERE id = :id';
                    $st = $pdo->prepare($sql);
                    $st->execute($bind);
                }

                // upsert في user_profiles (تخزين كل الحقول الجديدة)
                if (ensure_user_profiles_table($pdo)) {
                    $st = $pdo->prepare(
                        'INSERT INTO user_profiles (user_id, phone, address, bio, avatar, cover, full_name, job_title, country, city, gender, birthdate, website, facebook, x_url, instagram, youtube, telegram, whatsapp, show_email, show_phone, newsletter) '
                        . 'VALUES (:id,:p,:a,:b,:v,:c,:fn,:jt,:co,:ci,:g,:bd,:w,:fb,:x,:ig,:yt,:tg,:wa,:se,:sp,:nl) '
                        . 'ON DUPLICATE KEY UPDATE '
                        . 'phone=VALUES(phone), address=VALUES(address), bio=VALUES(bio), avatar=VALUES(avatar), cover=VALUES(cover), '
                        . 'full_name=VALUES(full_name), job_title=VALUES(job_title), country=VALUES(country), city=VALUES(city), gender=VALUES(gender), birthdate=VALUES(birthdate), '
                        . 'website=VALUES(website), facebook=VALUES(facebook), x_url=VALUES(x_url), instagram=VALUES(instagram), youtube=VALUES(youtube), telegram=VALUES(telegram), whatsapp=VALUES(whatsapp), '
                        . 'show_email=VALUES(show_email), show_phone=VALUES(show_phone), newsletter=VALUES(newsletter)'
                    );
                    $st->execute([
                        ':id' => $uid,
                        ':p'  => $phone !== '' ? $phone : null,
                        ':a'  => $address !== '' ? $address : null,
                        ':b'  => $bio !== '' ? $bio : null,
                        ':v'  => $avatarPath !== '' ? $avatarPath : null,
                        ':c'  => $coverPath !== '' ? $coverPath : null,
                        ':fn' => $fullName !== '' ? $fullName : null,
                        ':jt' => $jobTitle !== '' ? $jobTitle : null,
                        ':co' => $country !== '' ? $country : null,
                        ':ci' => $city !== '' ? $city : null,
                        ':g'  => $gender !== '' ? $gender : null,
                        ':bd' => $birthdate !== '' ? $birthdate : null,
                        ':w'  => $website !== '' ? $website : null,
                        ':fb' => $facebook !== '' ? $facebook : null,
                        ':x'  => $xUrl !== '' ? $xUrl : null,
                        ':ig' => $instagram !== '' ? $instagram : null,
                        ':yt' => $youtube !== '' ? $youtube : null,
                        ':tg' => $telegram !== '' ? $telegram : null,
                        ':wa' => $whatsapp !== '' ? $whatsapp : null,
                        ':se' => $showEmail,
                        ':sp' => $showPhone,
                        ':nl' => $newsletter,
                    ]);
                }

                // تحديث الجلسة للـ avatar و display_name (فقط)
                $currentUser['avatar'] = $avatarPath ?: null;
                if ($displayName !== '') {
                    $currentUser['display_name'] = $displayName;
                }

                if (function_exists('auth_set_user_session')) {
                    auth_set_user_session(array_merge($currentUser, ['id'=>$uid]));
                } else {
                    $_SESSION['user']['avatar'] = $avatarPath ?: null;
                }

                $success = 'تم تحديث بيانات الملف الشخصي بنجاح.';
            }

        } catch (Throwable $e) {
            $error = $e->getMessage() ?: 'حدث خطأ أثناء حفظ البيانات.';
        }

        // إعادة التحميل بعد التحديث لعرض القيم الحديثة
        try {
            $st = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
            $st->execute([':id' => $uid]);
            $userRow = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $profile = load_user_profile($pdo, $uid);
        } catch (Throwable $e) {
            // ignore
        }
    }
}

/* =========================
   Render
   ========================= */
require __DIR__ . '/frontend/views/partials/header.php';

$displayNameUi = (string)($userRow['display_name'] ?? ($_SESSION['user']['display_name'] ?? $_SESSION['user']['username'] ?? ''));
$usernameUi    = (string)($userRow['username'] ?? ($_SESSION['user']['username'] ?? ''));
$emailUi       = (string)($userRow['email'] ?? ($_SESSION['user']['email'] ?? ''));

$roleUi        = (string)($userRow['role'] ?? 'user');
$roleLabelMap  = ['admin'=>'مدير','editor'=>'محرر','writer'=>'كاتب','author'=>'مؤلف','user'=>'مستخدم'];
$roleLabel     = $roleLabelMap[$roleUi] ?? $roleUi;

$avatarUi = '';
if (!empty($profile['avatar'])) $avatarUi = $baseUrl . '/' . ltrim((string)$profile['avatar'], '/');
elseif (!empty($_SESSION['user']['avatar'])) $avatarUi = $baseUrl . '/' . ltrim((string)$_SESSION['user']['avatar'], '/');

$coverUi = '';
if (!empty($profile['cover'])) $coverUi = $baseUrl . '/' . ltrim((string)$profile['cover'], '/');

?>
<style>
/* تصميم خفيف ومتوافق مع RTL */
.gdy-profile-wrap{max-width:1100px;margin:0 auto;}
.gdy-profile-hero{position:relative;border-radius:22px;overflow:hidden;border:1px solid rgba(148,163,184,.25);background:rgba(15,23,42,.35);box-shadow:0 18px 55px rgba(0,0,0,.25);}
.gdy-profile-hero .cover{height:170px;background:radial-gradient(1000px 300px at 20% 20%, rgba(56,189,248,.35), transparent 60%), radial-gradient(900px 300px at 80% 30%, rgba(168,85,247,.28), transparent 55%), linear-gradient(135deg, rgba(2,6,23,.75), rgba(15,23,42,.35));background-size:cover;background-position:center;}
.gdy-profile-hero .cover.has-img{filter:saturate(1.05) contrast(1.05);}
.gdy-profile-hero .hero-body{display:flex;gap:18px;align-items:flex-end;padding:0 18px 16px 18px;transform:translateY(-44px);}
.gdy-avatar{width:92px;height:92px;border-radius:50%;object-fit:cover;border:3px solid rgba(255,255,255,.75);background:#0b1220;box-shadow:0 14px 30px rgba(0,0,0,.35);}
.gdy-avatar-fallback{width:92px;height:92px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:rgba(2,6,23,.65);border:3px solid rgba(255,255,255,.55);box-shadow:0 14px 30px rgba(0,0,0,.35);}
.gdy-hero-meta{display:flex;flex-direction:column;gap:6px;min-width:0;}
.gdy-hero-name{font-size:20px;font-weight:800;margin:0;color:#fff;text-shadow:0 10px 25px rgba(0,0,0,.35);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:680px;}
.gdy-hero-sub{display:flex;flex-wrap:wrap;gap:8px;align-items:center;color:rgba(226,232,240,.9);font-size:13px}
.gdy-chip{padding:5px 10px;border-radius:999px;border:1px solid rgba(148,163,184,.35);background:rgba(2,6,23,.35);backdrop-filter:blur(10px);}
.gdy-chip strong{font-weight:800}
.gdy-grid{display:grid;grid-template-columns:1.25fr .75fr;gap:16px;margin-top:14px;}
@media (max-width: 980px){.gdy-grid{grid-template-columns:1fr}.gdy-hero-name{max-width:100%}}
.gdy-card{background:rgba(255,255,255,.94);border:1px solid rgba(148,163,184,.35);border-radius:18px;padding:14px;}
.gdy-card h3{margin:0 0 12px 0;font-weight:800;}
.gdy-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
@media (max-width: 720px){.gdy-row{grid-template-columns:1fr}}
.gdy-input,.gdy-textarea{width:100%;padding:10px 12px;border:1px solid rgba(148,163,184,.55);border-radius:12px;background:#fff;outline:none}
.gdy-textarea{resize:vertical}
.gdy-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}
.gdy-btn{padding:10px 14px;border:none;border-radius:12px;background:var(--primary);color:#0b1120;font-weight:800;cursor:pointer}
.gdy-btn.secondary{background:#0f172a;color:#fff}
.gdy-btn.danger{background:#ef4444;color:#fff}
.gdy-muted{font-size:12px;color:#64748b;margin-top:6px}
.gdy-check{display:flex;gap:10px;align-items:center;padding:10px 12px;border:1px solid rgba(148,163,184,.35);border-radius:12px;background:#f8fafc;}
.gdy-check input{width:18px;height:18px}
</style>

<main class="container" style="padding: 18px 16px;">
  <div class="gdy-profile-wrap">
    <div class="gdy-profile-hero">
      <div class="cover <?= $coverUi ? 'has-img' : '' ?>" style="<?= $coverUi ? 'background-image:url('.h($coverUi).')' : '' ?>"></div>
      <div class="hero-body">
        <?php if ($avatarUi): ?>
          <img class="gdy-avatar" src="<?= h($avatarUi) ?>" alt="avatar" onerror="this.style.display='none'">
        <?php else: ?>
          <div class="gdy-avatar-fallback" aria-hidden="true"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg></div>
        <?php endif; ?>

        <div class="gdy-hero-meta">
          <h1 class="gdy-hero-name"><?= h($displayNameUi ?: $usernameUi ?: 'حسابي') ?></h1>
          <div class="gdy-hero-sub">
            <?php if ($emailUi): ?><span class="gdy-chip"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h($emailUi) ?></span><?php endif; ?>
            <?php if ($usernameUi): ?><span class="gdy-chip"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg> <?= h($usernameUi) ?></span><?php endif; ?>
            <span class="gdy-chip"><strong><?= h($roleLabel) ?></strong></span>
            <span class="gdy-chip"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> محفوظات: <strong><?= (int)$stats['bookmarks'] ?></strong></span>
            <span class="gdy-chip"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> تعليقات: <strong><?= (int)$stats['comments'] ?></strong></span>
            <a class="gdy-chip" href="<?= h($baseUrl) ?>/my" style="text-decoration:none;color:inherit"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> صفحة "لك"</a>
          </div>
        </div>
      </div>
    </div>

    <?php if ($success): ?>
      <div class="alert" style="margin-top:12px;background:#dcfce7;border:1px solid #86efac;padding:10px 12px;border-radius:12px;">
        <?= h($success) ?>
      </div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert" style="margin-top:12px;background:#fee2e2;border:1px solid #fca5a5;padding:10px 12px;border-radius:12px;">
        <?= h($error) ?>
      </div>
    <?php endif; ?>

    <div class="gdy-grid">
      <!-- بيانات الملف الشخصي -->
      <section class="gdy-card">
        <h3>الملف الشخصي</h3>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
          <input type="hidden" name="action" value="update_profile">

          <div style="margin-bottom:12px;">
            <label style="display:block;font-weight:700;margin-bottom:6px;">اسم الظهور في الموقع</label>
            <input class="gdy-input" name="display_name" value="<?= h((string)($userRow['display_name'] ?? ($_SESSION['user']['display_name'] ?? $_SESSION['user']['username'] ?? ''))) ?>">
            <div class="gdy-muted">يظهر هذا الاسم بجوار تعليقاتك وفي صفحات الموقع.</div>
          </div>

          <div class="gdy-row">
            <div>
              <label style="display:block;font-weight:700;margin-bottom:6px;">الاسم الكامل</label>
              <input class="gdy-input" name="full_name" value="<?= h($profile['full_name'] ?? '') ?>" placeholder="مثال: محمد أحمد">
            </div>
            <div>
              <label style="display:block;font-weight:700;margin-bottom:6px;">المسمى الوظيفي</label>
              <input class="gdy-input" name="job_title" value="<?= h($profile['job_title'] ?? '') ?>" placeholder="مثال: صحفي / كاتب">
            </div>
          </div>

          <div class="gdy-row" style="margin-top:12px;">
            <div>
              <label style="display:block;font-weight:700;margin-bottom:6px;">الهاتف</label>
              <input class="gdy-input" name="phone" value="<?= h($profile['phone'] ?? '') ?>">
            </div>
            <div>
              <label style="display:block;font-weight:700;margin-bottom:6px;">العنوان</label>
              <input class="gdy-input" name="address" value="<?= h($profile['address'] ?? '') ?>">
            </div>
          </div>

          <div class="gdy-row" style="margin-top:12px;">
            <div>
              <label style="display:block;font-weight:700;margin-bottom:6px;">الدولة</label>
              <input class="gdy-input" name="country" value="<?= h($profile['country'] ?? '') ?>" placeholder="مثال: السعودية">
            </div>
            <div>
              <label style="display:block;font-weight:700;margin-bottom:6px;">المدينة</label>
              <input class="gdy-input" name="city" value="<?= h($profile['city'] ?? '') ?>" placeholder="مثال: الرياض">
            </div>
          </div>

          <div class="gdy-row" style="margin-top:12px;">
            <div>
              <label style="display:block;font-weight:700;margin-bottom:6px;">النوع</label>
              <select class="gdy-input" name="gender">
                <?php $g = (string)($profile['gender'] ?? ''); ?>
                <option value="" <?= $g===''?'selected':'' ?>>غير محدد</option>
                <option value="male" <?= $g==='male'?'selected':'' ?>>ذكر</option>
                <option value="female" <?= $g==='female'?'selected':'' ?>>أنثى</option>
                <option value="other" <?= $g==='other'?'selected':'' ?>>آخر</option>
              </select>
            </div>
            <div>
              <label style="display:block;font-weight:700;margin-bottom:6px;">تاريخ الميلاد</label>
              <input class="gdy-input" type="date" name="birthdate" value="<?= h((string)($profile['birthdate'] ?? '')) ?>">
              <div class="gdy-muted">اختياري — لا يُعرض إلا إذا فعّلت ذلك في الخصوصية لاحقاً.</div>
            </div>
          </div>

          <div style="margin-top:12px;">
            <label style="display:block;font-weight:700;margin-bottom:6px;">نبذة</label>
            <textarea class="gdy-textarea" name="bio" rows="4" placeholder="اكتب نبذة قصيرة..."><?= h($profile['bio'] ?? '') ?></textarea>
          </div>

          <hr style="border:none;border-top:1px solid rgba(148,163,184,.35);margin:14px 0">

          <h3 style="margin:0 0 10px 0;font-size:16px;">روابط التواصل</h3>

          <div class="gdy-row">
            <div>
              <label style="display:block;font-weight:700;margin-bottom:6px;">الموقع الإلكتروني</label>
              <input class="gdy-input" name="website" value="<?= h($profile['website'] ?? '') ?>" placeholder="example.com">
            </div>
            <div>
              <label style="display:block;font-weight:700;margin-bottom:6px;">فيسبوك</label>
              <input class="gdy-input" name="facebook" value="<?= h($profile['facebook'] ?? '') ?>" placeholder="facebook.com/...">
            </div>
          </div>

          <div class="gdy-row" style="margin-top:12px;">
            <div>
              <label style="display:block;font-weight:700;margin-bottom:6px;">X</label>
              <input class="gdy-input" name="x_url" value="<?= h($profile['x_url'] ?? '') ?>" placeholder="x.com/...">
            </div>
            <div>
              <label style="display:block;font-weight:700;margin-bottom:6px;">إنستغرام</label>
              <input class="gdy-input" name="instagram" value="<?= h($profile['instagram'] ?? '') ?>" placeholder="instagram.com/...">
            </div>
          </div>

          <div class="gdy-row" style="margin-top:12px;">
            <div>
              <label style="display:block;font-weight:700;margin-bottom:6px;">يوتيوب</label>
              <input class="gdy-input" name="youtube" value="<?= h($profile['youtube'] ?? '') ?>" placeholder="youtube.com/...">
            </div>
            <div>
              <label style="display:block;font-weight:700;margin-bottom:6px;">تيليجرام</label>
              <input class="gdy-input" name="telegram" value="<?= h($profile['telegram'] ?? '') ?>" placeholder="@username أو رابط">
            </div>
          </div>

          <div style="margin-top:12px;">
            <label style="display:block;font-weight:700;margin-bottom:6px;">واتساب</label>
            <input class="gdy-input" name="whatsapp" value="<?= h($profile['whatsapp'] ?? '') ?>" placeholder="رقم أو wa.me/...">
          </div>

          <hr style="border:none;border-top:1px solid rgba(148,163,184,.35);margin:14px 0">

          <h3 style="margin:0 0 10px 0;font-size:16px;">الصور</h3>

          <div class="gdy-row">
            <div>
              <label style="display:block;font-weight:700;margin-bottom:6px;">صورة الملف الشخصي</label>
              <input class="gdy-input" type="file" name="avatar" accept="image/png,image/jpeg,image/gif,image/webp" style="background:#f8fafc;">
              <div class="gdy-muted">الحد الأقصى 2MB — صيغ: JPG, PNG, WEBP, GIF</div>
              <?php if (!empty($profile['avatar'])): ?>
                <div class="gdy-check" style="margin-top:8px;">
                  <input type="checkbox" id="rm_avatar" name="remove_avatar" value="1">
                  <label for="rm_avatar">حذف الصورة الحالية</label>
                </div>
              <?php endif; ?>
            </div>
            <div>
              <label style="display:block;font-weight:700;margin-bottom:6px;">صورة الغلاف</label>
              <input class="gdy-input" type="file" name="cover" accept="image/png,image/jpeg,image/webp" style="background:#f8fafc;">
              <div class="gdy-muted">الحد الأقصى 4MB — صيغ: JPG, PNG, WEBP</div>
              <?php if (!empty($profile['cover'])): ?>
                <div class="gdy-check" style="margin-top:8px;">
                  <input type="checkbox" id="rm_cover" name="remove_cover" value="1">
                  <label for="rm_cover">حذف الغلاف الحالي</label>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <hr style="border:none;border-top:1px solid rgba(148,163,184,.35);margin:14px 0">

          <h3 style="margin:0 0 10px 0;font-size:16px;">الخصوصية والإعدادات</h3>

          <div class="gdy-row">
            <div class="gdy-check">
              <input type="checkbox" id="show_email" name="show_email" value="1" <?= !empty($profile['show_email']) ? 'checked' : '' ?>>
              <label for="show_email">السماح بإظهار البريد في صفحات الكاتب/التعليقات</label>
            </div>
            <div class="gdy-check">
              <input type="checkbox" id="show_phone" name="show_phone" value="1" <?= !empty($profile['show_phone']) ? 'checked' : '' ?>>
              <label for="show_phone">السماح بإظهار الهاتف</label>
            </div>
          </div>
          <div class="gdy-check" style="margin-top:12px;">
            <input type="checkbox" id="newsletter" name="newsletter" value="1" <?= !empty($profile['newsletter']) ? 'checked' : '' ?>>
            <label for="newsletter">الاشتراك في النشرة البريدية (اختياري)</label>
          </div>

          <div class="gdy-actions">
            <button type="submit" class="gdy-btn">حفظ التغييرات</button>
          </div>
        </form>
      </section>

      <!-- البريد + كلمة المرور -->
      <div style="display:flex;flex-direction:column;gap:16px;">
        <section class="gdy-card">
          <h3>تغيير البريد الإلكتروني</h3>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="action" value="update_email">
            <label style="display:block;font-weight:700;margin-bottom:6px;">البريد الإلكتروني</label>
            <input type="email" class="gdy-input" name="email" value="<?= h($emailUi) ?>">
            <div class="gdy-actions">
              <button type="submit" class="gdy-btn secondary">تحديث البريد</button>
            </div>
          </form>
        </section>

        <section class="gdy-card">
          <h3>تغيير كلمة المرور</h3>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="action" value="update_password">

            <div style="display:grid;grid-template-columns:1fr;gap:12px;">
              <div>
                <label style="display:block;font-weight:700;margin-bottom:6px;">كلمة المرور الحالية</label>
                <input type="password" class="gdy-input" name="current_password">
              </div>
              <div class="gdy-row">
                <div>
                  <label style="display:block;font-weight:700;margin-bottom:6px;">كلمة المرور الجديدة</label>
                  <input type="password" class="gdy-input" name="new_password">
                </div>
                <div>
                  <label style="display:block;font-weight:700;margin-bottom:6px;">تأكيد كلمة المرور</label>
                  <input type="password" class="gdy-input" name="confirm_password">
                </div>
              </div>
              <div class="gdy-muted">الحد الأدنى 8 أحرف.</div>
            </div>

            <div class="gdy-actions">
              <button type="submit" class="gdy-btn danger">تغيير كلمة المرور</button>
            </div>
          </form>
        </section>
      </div>
    </div>
  </div>
</main>

<?php require __DIR__ . '/frontend/views/partials/footer.php'; ?>
