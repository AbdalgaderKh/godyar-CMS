<?php
// /godyar/admin/weather_settings.php
declare(strict_types=1);

require_once __DIR__ . '/_admin_guard.php';
require_once __DIR__ . '/../includes/bootstrap.php';

// إعداد صفحة لوحة التحكم
$currentPage = 'weather_settings';
$pageTitle   = __('t_cdefdef2cf', 'إعدادات الطقس');

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

// دالة هروب بسيطة
if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    die(__('t_829736ebca', 'تعذّر الاتصال بقاعدة البيانات. تأكد من إعداد ملف includes/bootstrap.php بشكل صحيح.'));
}

/**
 * 🔧 فحص وجود عمود في جدول
 */
if (!function_exists('gdy_column_exists')) {
    function gdy_column_exists(PDO $pdo, string $table, string $column): bool
    {
        try {
            if (function_exists('db_column_exists')) {
                return db_column_exists($pdo, $table, $column);
            }
            // Fallback (آمن طالما أن أسماء الجداول/الأعمدة ثابتة ضمن الكود)
            $safeTable = str_replace('`', '', $table);
            $q = $pdo->quote($column);
            $stmt = $pdo->query("SHOW COLUMNS FROM `" . $safeTable . "` LIKE " . $q);
            return (bool)($stmt && $stmt->fetch(PDO::FETCH_ASSOC));
        } catch (Throwable $e) {
            @error_log('[Schema] column_exists error: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * 🔧 التأكد من أن جدول weather_settings يحتوي الأعمدة اللازمة
 */
if (!function_exists('gdy_ensure_weather_columns')) {
    function gdy_ensure_weather_columns(PDO $pdo): void
    {
        $table = 'weather_settings';

        $columnsSql = [
            'api_key' => "
                ALTER TABLE `weather_settings`
                ADD COLUMN `api_key` VARCHAR(255) NOT NULL DEFAULT '' AFTER `id`
            ",
            'city' => "
                ALTER TABLE `weather_settings`
                ADD COLUMN `city` VARCHAR(190) NOT NULL DEFAULT '' AFTER `api_key`
            ",
            // عمود ربط المدينة من جدول منفصل
            'location_id' => "
                ALTER TABLE `weather_settings`
                ADD COLUMN `location_id` INT UNSIGNED NULL AFTER `city`
            ",
            'country_code' => "
                ALTER TABLE `weather_settings`
                ADD COLUMN `country_code` VARCHAR(10) DEFAULT '' AFTER `location_id`
            ",
            'units' => "
                ALTER TABLE `weather_settings`
                ADD COLUMN `units` ENUM('metric','imperial') NOT NULL DEFAULT 'metric' AFTER `country_code`
            ",
            'is_active' => "
                ALTER TABLE `weather_settings`
                ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `units`
            ",
            'refresh_minutes' => "
                ALTER TABLE `weather_settings`
                ADD COLUMN `refresh_minutes` INT NOT NULL DEFAULT 30 AFTER `is_active`
            ",
            'created_at' => "
                ALTER TABLE `weather_settings`
                ADD COLUMN `created_at` DATETIME NULL AFTER `refresh_minutes`
            ",
            'updated_at' => "
                ALTER TABLE `weather_settings`
                ADD COLUMN `updated_at` DATETIME NULL AFTER `created_at`
            ",
        ];

        foreach ($columnsSql as $col => $sql) {
            if (!gdy_column_exists($pdo, $table, $col)) {
                try {
                    $pdo->exec($sql);
                } catch (Throwable $e) {
                    @error_log("[Weather Settings] ALTER TABLE add column `$col` error: " . $e->getMessage());
                }
            }
        }
    }
}

$errors      = [];
$success     = '';
$locSuccess  = '';
$locErrors   = [];

$current = [
    'id'              => null,
    'api_key'         => '',
    'city'            => '',
    'location_id'     => null,
    'country_code'    => '',
    'units'           => 'metric',
    'is_active'       => 0,
    'refresh_minutes' => 30,
];

// ✅ إنشاء جدول إعدادات الطقس (سجل واحد)
try {
    $createSql = "
        CREATE TABLE IF NOT EXISTS weather_settings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            api_key VARCHAR(255) NOT NULL,
            city VARCHAR(190) NOT NULL,
            location_id INT UNSIGNED NULL,
            country_code VARCHAR(10) DEFAULT '',
            units ENUM('metric','imperial') NOT NULL DEFAULT 'metric',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            refresh_minutes INT NOT NULL DEFAULT 30,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    ";
    $pdo->exec($createSql);

    gdy_ensure_weather_columns($pdo);
} catch (Throwable $e) {
    @error_log('[Weather Settings] Initial CREATE/ensure error: ' . $e->getMessage());
}

// ✅ إنشاء جدول المدن/الدول
try {
    $createLocSql = "
        CREATE TABLE IF NOT EXISTS weather_locations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            country_name  VARCHAR(190) NOT NULL,
            country_code  VARCHAR(10)  NOT NULL,
            city_name     VARCHAR(190) NOT NULL,
            is_active     TINYINT(1)   NOT NULL DEFAULT 1,
            created_at    DATETIME NULL,
            updated_at    DATETIME NULL,
            INDEX idx_country_code (country_code),
            INDEX idx_city_name (city_name)
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    ";
    $pdo->exec($createLocSql);
} catch (Throwable $e) {
    @error_log('[Weather Settings] weather_locations create error: ' . $e->getMessage());
}

// قراءة السجل الحالي من weather_settings
try {
    $stmt = $pdo->query("SELECT * FROM weather_settings ORDER BY id ASC LIMIT 1");
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $current = array_merge($current, $row);
    }
} catch (Throwable $e) {
    @error_log('[Weather Settings] Select error: ' . $e->getMessage());
}

// جلب قائمة المدن/الدول
$locations = [];
try {
    $stmt = $pdo->query("
        SELECT id, country_name, country_code, city_name, is_active
        FROM weather_locations
        ORDER BY country_name ASC, city_name ASC
    ");
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    @error_log('[Weather Settings] locations select error: ' . $e->getMessage());
}

// إحصائيات بسيطة للمدن
$locTotal = count($locations);
$locActiveCount = 0;
foreach ($locations as $l) {
    if ((int)($l['is_active'] ?? 0) === 1) $locActiveCount++;
}

// توليد CSRF
$csrfToken = function_exists('generate_csrf_token')
    ? generate_csrf_token()
    : bin2hex(random_bytes(16));

/* ================== معالجة POST ================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? 'settings';
    $csrf     = $_POST['csrf_token'] ?? '';

    if (function_exists('validate_csrf_token') && !validate_csrf_token($csrf)) {
        if ($formType === 'location' || $formType === 'location_action') {
            $locErrors[] = __('t_fbbc004136', 'رمز الحماية (CSRF) غير صالح، يرجى إعادة المحاولة.');
        } else {
            $errors[] = __('t_fbbc004136', 'رمز الحماية (CSRF) غير صالح، يرجى إعادة المحاولة.');
        }
    } else {

        /* ---------- A) عمليات على المدن (تفعيل/تعطيل/حذف) ---------- */
        if ($formType === 'location_action') {
            $action = trim((string)($_POST['action'] ?? ''));
            $locId  = (int)($_POST['location_row_id'] ?? 0);

            if ($locId <= 0) {
                $locErrors[] = __('t_1fe7281687', 'معرّف المدينة غير صالح.');
            } else {
                try {
                    if ($action === 'toggle') {
                        $stmt = $pdo->prepare("SELECT is_active FROM weather_locations WHERE id = :id LIMIT 1");
                        $stmt->execute([':id' => $locId]);
                        $isActive = (int)($stmt->fetchColumn() ?? 0);

                        $newVal = $isActive === 1 ? 0 : 1;
                        $upd = $pdo->prepare("UPDATE weather_locations SET is_active = :v, updated_at = NOW() WHERE id = :id LIMIT 1");
                        $upd->execute([':v' => $newVal, ':id' => $locId]);

                        $locSuccess = $newVal ? __('t_b7441f4a2e', 'تم تفعيل المدينة.') : __('t_873311b6bc', 'تم تعطيل المدينة.');
                    } elseif ($action === 'delete') {

                        // لو كانت المدينة مستخدمة في الإعدادات الحالية، نفصل الربط قبل الحذف
                        $curLoc = (int)($current['location_id'] ?? 0);
                        if ($curLoc === $locId && (int)($current['id'] ?? 0) > 0) {
                            $pdo->prepare("UPDATE weather_settings SET location_id = NULL, updated_at = NOW() WHERE id = :id")
                                ->execute([':id' => (int)($current['id'] ?? 0)]);
                            $current['location_id'] = null;
                        }

                        $del = $pdo->prepare("DELETE FROM weather_locations WHERE id = :id LIMIT 1");
                        $del->execute([':id' => $locId]);

                        $locSuccess = __('t_b5f5083ed6', 'تم حذف المدينة بنجاح.');
                    } else {
                        $locErrors[] = __('t_00aec96f11', 'عملية غير معروفة.');
                    }
                } catch (Throwable $e) {
                    $locErrors[] = __('t_a8fad26626', 'حدث خطأ أثناء تنفيذ العملية: ') . $e->getMessage();
                }
            }

            // إعادة تحميل liste المدن بعد أي تغيير
            try {
                $stmt = $pdo->query("
                    SELECT id, country_name, country_code, city_name, is_active
                    FROM weather_locations
                    ORDER BY country_name ASC, city_name ASC
                ");
                $locations = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $locTotal = count($locations);
                $locActiveCount = 0;
                foreach ($locations as $l) {
                    if ((int)($l['is_active'] ?? 0) === 1) $locActiveCount++;
                }
            } catch (Throwable $e) {
                @error_log('[Weather Settings] locations reload error: ' . $e->getMessage());
            }

        /* ---------- 1) فورم إضافة مدينة/دولة ---------- */
        } elseif ($formType === 'location') {
            $locCountryName = trim($_POST['loc_country_name'] ?? '');
            $locCountryCode = strtoupper(trim($_POST['loc_country_code'] ?? ''));
            $locCityName    = trim($_POST['loc_city_name'] ?? '');
            $locActive      = isset($_POST['loc_is_active']) ? 1 : 0;

            if ($locCountryName === '') $locErrors[] = __('t_b87e672957', 'يرجى إدخال اسم الدولة.');
            if ($locCountryCode === '') $locErrors[] = __('t_d577993bd6', 'يرجى إدخال رمز الدولة (مثل SA, EG, SD).');
            if ($locCityName === '') $locErrors[] = __('t_ca6f2a845b', 'يرجى إدخال اسم المدينة.');

            if ($locCountryCode !== '' && strlen($locCountryCode) > 10) {
                $locErrors[] = __('t_41260ffb7f', 'رمز الدولة طويل جداً.');
            }

            if (!$locErrors) {
                try {
                    // منع التكرار (city + country_code)
                    $chk = $pdo->prepare("
                        SELECT id FROM weather_locations
                        WHERE country_code = :cc AND city_name = :cn
                        LIMIT 1
                    ");
                    $chk->execute([
                        ':cc' => $locCountryCode,
                        ':cn' => $locCityName,
                    ]);
                    $existsId = (int)($chk->fetchColumn() ?: 0);

                    if ($existsId > 0) {
                        $locErrors[] = __('t_0905c6d433', 'هذه المدينة موجودة مسبقاً بنفس رمز الدولة.');
                    } else {
                        $insertLoc = $pdo->prepare("
                            INSERT INTO weather_locations
                                (country_name, country_code, city_name, is_active, created_at, updated_at)
                            VALUES
                                (:country_name, :country_code, :city_name, :is_active, NOW(), NOW())
                        ");
                        $insertLoc->execute([
                            ':country_name' => $locCountryName,
                            ':country_code' => $locCountryCode,
                            ':city_name'    => $locCityName,
                            ':is_active'    => $locActive,
                        ]);

                        $locSuccess = __('t_c12fd0946e', 'تم إضافة المدينة/الدولة إلى قاعدة البيانات بنجاح.');

                        // إعادة تحميل liste المدن
                        $stmt = $pdo->query("
                            SELECT id, country_name, country_code, city_name, is_active
                            FROM weather_locations
                            ORDER BY country_name ASC, city_name ASC
                        ");
                        $locations = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                        $locTotal = count($locations);
                        $locActiveCount = 0;
                        foreach ($locations as $l) {
                            if ((int)($l['is_active'] ?? 0) === 1) $locActiveCount++;
                        }
                    }

                } catch (Throwable $e) {
                    $locErrors[] = __('t_d9b9559597', 'حدث خطأ أثناء حفظ المدينة/الدولة: ') . $e->getMessage();
                }
            }

        /* ---------- 2) فورم إعدادات الطقس ---------- */
        } else {
            $apiKey     = trim($_POST['api_key'] ?? '');
            $cityManual = trim($_POST['city'] ?? '');
            $country    = strtoupper(trim($_POST['country_code'] ?? ''));
            $units      = $_POST['units'] ?? 'metric';
            $isActive   = isset($_POST['is_active']) ? 1 : 0;
            $refresh    = (int)($_POST['refresh_minutes'] ?? 30);
            $locId      = (int)($_POST['location_id'] ?? 0);

            if ($refresh < 5) $refresh = 5;
            if ($refresh > 1440) $refresh = 1440;

            if ($apiKey === '') $errors[] = __('t_6a66053eb1', 'يرجى إدخال مفتاح واجهة الطقس (API key).');

            if ($locId <= 0 && $cityManual === '') {
                $errors[] = __('t_e12fdf2c91', 'يرجى اختيار مدينة من القائمة أو إدخال مدينة يدويًا.');
            }

            if (!in_array($units, ['metric', 'imperial'], true)) {
                $units = 'metric';
            }

            // لو اختار مدينة من جدول weather_locations، نقرأ بياناتها
            $finalCity = $cityManual;
            $finalCode = $country;

            if ($locId > 0) {
                try {
                    $stmtLoc = $pdo->prepare("
                        SELECT city_name, country_code
                        FROM weather_locations
                        WHERE id = :id AND is_active = 1
                        LIMIT 1
                    ");
                    $stmtLoc->execute([':id' => $locId]);
                    $locRow = $stmtLoc->fetch(PDO::FETCH_ASSOC);
                    if ($locRow) {
                        $finalCity = (string)$locRow['city_name'];
                        $finalCode = (string)$locRow['country_code'];
                    } else {
                        $locId = 0;
                    }
                } catch (Throwable $e) {
                    @error_log('[Weather Settings] read location error: ' . $e->getMessage());
                }
            }

            if ($finalCity === '') {
                $errors[] = __('t_fc79e19baf', 'تعذّر تحديد المدينة. يرجى اختيار مدينة صحيحة أو إدخالها يدويًا.');
            }

            if (!$errors) {
                try {
                    gdy_ensure_weather_columns($pdo);

                    $stmt       = $pdo->query("SELECT id FROM weather_settings ORDER BY id ASC LIMIT 1");
                    $existingId = (int)($stmt->fetchColumn() ?: 0);

                    if ($existingId > 0) {
                        $update = $pdo->prepare("
                            UPDATE weather_settings
                            SET api_key         = :api_key,
                                city            = :city,
                                location_id     = :location_id,
                                country_code    = :country_code,
                                units           = :units,
                                is_active       = :is_active,
                                refresh_minutes = :refresh_minutes,
                                updated_at      = NOW()
                            WHERE id = :id
                        ");
                        $update->execute([
                            ':api_key'         => $apiKey,
                            ':city'            => $finalCity,
                            ':location_id'     => $locId ?: null,
                            ':country_code'    => $finalCode,
                            ':units'           => $units,
                            ':is_active'       => $isActive,
                            ':refresh_minutes' => $refresh,
                            ':id'              => $existingId,
                        ]);
                        $current['id'] = $existingId;
                    } else {
                        $insert = $pdo->prepare("
                            INSERT INTO weather_settings
                                (api_key, city, location_id, country_code, units, is_active, refresh_minutes, created_at, updated_at)
                            VALUES
                                (:api_key, :city, :location_id, :country_code, :units, :is_active, :refresh_minutes, NOW(), NOW())
                        ");
                        $insert->execute([
                            ':api_key'         => $apiKey,
                            ':city'            => $finalCity,
                            ':location_id'     => $locId ?: null,
                            ':country_code'    => $finalCode,
                            ':units'           => $units,
                            ':is_active'       => $isActive,
                            ':refresh_minutes' => $refresh,
                        ]);
                        $current['id'] = (int)$pdo->lastInsertId();
                    }

                    $current['api_key']         = $apiKey;
                    $current['city']            = $finalCity;
                    $current['location_id']     = $locId ?: null;
                    $current['country_code']    = $finalCode;
                    $current['units']           = $units;
                    $current['is_active']       = $isActive;
                    $current['refresh_minutes'] = $refresh;

                    $success = __('t_29cad3ae69', 'تم حفظ إعدادات الطقس بنجاح.');
                } catch (Throwable $e) {
                    $msg      = $e->getMessage();
                    $errors[] = __('t_0ac43c887f', 'حدث خطأ أثناء حفظ الإعدادات في قاعدة البيانات: ') . $msg;
                    @error_log('[Weather Settings] Save error: ' . $msg);
                }
            }
        }
    }
}

// تضمين الترويسة ولوحة جانبية
require_once __DIR__ . '/layout/header.php';
require_once __DIR__ . '/layout/sidebar.php';
?>
<style>
/* التصميم الموحد للعرض - منع التمرير الأفقي + عدم التداخل مع السايدبار */
html, body { overflow-x: hidden; }

/* لو كانت القائمة الجانبية ثابتة بعرض ~260px */
@media (min-width: 992px) {
  .admin-content { margin-right: 260px !important; }
}

/* تقليص عرض المحتوى وتوسيطه */
.admin-content.gdy-page .container-fluid {
  max-width: 1200px;
  margin: 0 auto;
  padding: 1.5rem 1rem 2rem;
}

/* خلفية وألوان عامة */
.admin-content.gdy-page {
  background: linear-gradient(135deg, #0f172a 0%, #020617 100%);
  min-height: 100vh;
  color: #e5e7eb;
  font-family: "Cairo", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

/* هيدر */
.gdy-header{
  background: linear-gradient(135deg, #0ea5e9, #0369a1);
  color:#fff;
  padding: 1.25rem 1.5rem;
  border-radius: 1rem;
  box-shadow: 0 10px 30px rgba(15, 23, 42, .55);
  display:flex;
  flex-wrap:wrap;
  align-items:center;
  justify-content:space-between;
  gap: .75rem;
  margin-bottom: 1rem;
}
.gdy-header h1{ margin:0; font-size:1.15rem; font-weight:800; }
.gdy-header p{ margin:.25rem 0 0; opacity:.92; font-size:.9rem; }
.gdy-actions{ display:flex; gap:.5rem; flex-wrap:wrap; }

.gdy-card{
  background: rgba(15, 23, 42, 0.9);
  border: 1px solid rgba(148,163,184,.35);
  border-radius: 1rem;
  box-shadow: 0 15px 45px rgba(15,23,42,.85);
  overflow:hidden;
  height:100%;
}
.gdy-card-header{
  padding: .9rem 1.1rem;
  border-bottom: 1px solid rgba(148,163,184,.2);
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:.75rem;
  flex-wrap:wrap;
}
.gdy-card-header h2{
  margin:0;
  font-size: .95rem;
  display:flex;
  align-items:center;
  gap:.5rem;
}
.gdy-card-header h2 i{ color:#38bdf8; }
.gdy-card-body{ padding: 1.1rem; }

.form-control, .form-select{
  border-radius: .75rem;
  border-color: rgba(148,163,184,.35);
  background: rgba(2,6,23,.55);
  color:#e5e7eb;
}
.form-control:focus, .form-select:focus{
  border-color: #0ea5e9;
  box-shadow: 0 0 0 .15rem rgba(14,165,233,.28);
  background: rgba(2,6,23,.75);
  color:#e5e7eb;
}
.form-text{ color:#9ca3af !important; }

.gdy-badge{
  border-radius: 999px;
  padding: .25rem .6rem;
  font-size: .75rem;
  border: 1px solid rgba(148,163,184,.35);
  background: rgba(2,6,23,.55);
  color:#e5e7eb;
}

.gdy-divider{
  height:1px;
  background: rgba(148,163,184,.18);
  margin: .9rem 0;
}

/* ملخص سريع */
.gdy-summary{
  display:flex;
  flex-wrap:wrap;
  gap:.5rem;
  align-items:center;
}
.gdy-summary .pill{
  display:inline-flex;
  gap:.45rem;
  align-items:center;
  padding: .35rem .65rem;
  border-radius: 999px;
  border: 1px solid rgba(148,163,184,.25);
  background: rgba(2,6,23,.45);
  font-size:.78rem;
  color:#e5e7eb;
}

/* جدول المدن */
.table-responsive{
  border: 1px solid rgba(148,163,184,.22);
  border-radius: .85rem;
  overflow: hidden;
  background: rgba(2,6,23,.35);
}
.table.table-dark{
  --bs-table-bg: rgba(2,6,23,.0);
  --bs-table-striped-bg: rgba(148,163,184,.06);
  margin:0;
}
.table thead th{ color:#cbd5e1; font-weight:700; font-size:.78rem; border-bottom: 1px solid rgba(148,163,184,.2); }
.table tbody td{ color:#e5e7eb; font-size:.8rem; border-top: 1px solid rgba(148,163,184,.15); }

/* أزرار صغيرة */
.btn{ border-radius: .75rem; }
.btn-outline-light{ border-color: rgba(255,255,255,.25); }
.btn-outline-light:hover{ border-color: rgba(255,255,255,.35); }

.gdy-mini-actions{
  display:flex;
  gap:.35rem;
  flex-wrap:wrap;
  justify-content:flex-end;
}
.gdy-mini-actions form{ display:inline; }

/* صندوق اختبار الطقس */
.gdy-test-box{
  border: 1px dashed rgba(148,163,184,.35);
  border-radius: .85rem;
  padding: .85rem;
  background: rgba(2,6,23,.35);
}
.gdy-test-result{
  margin-top: .75rem;
  padding: .75rem;
  border-radius: .75rem;
  background: rgba(2,6,23,.5);
  border: 1px solid rgba(148,163,184,.18);
  font-size: .85rem;
  display:none;
}
.gdy-test-result.ok{ border-color: rgba(34,197,94,.35); }
.gdy-test-result.err{ border-color: rgba(239,68,68,.35); }

@media (max-width: 767.98px) {
  .gdy-card-body{ padding: .95rem; }
}
</style>

<div class="admin-content gdy-page">
  <div class="container-fluid">

    <div class="gdy-header">
      <div>
        <h1><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_cdefdef2cf', 'إعدادات الطقس')) ?></h1>
        <p class="mb-0"><?= h(__('t_8a8238d6f6', 'تهيئة ويدجيت الطقس لواجهة الموقع مع قاعدة بيانات مدن + اختبار سريع.')) ?></p>
      </div>
      <div class="gdy-actions">
        <a href="index.php" class="btn btn-outline-light btn-sm">
          <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_2f09126266', 'العودة للوحة التحكم')) ?>
        </a>
      </div>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
        <ul class="mb-0">
          <?php foreach ($errors as $e): ?>
            <li><?= h($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success">
        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h($success) ?>
      </div>
    <?php endif; ?>

    <?php if ($locErrors): ?>
      <div class="alert alert-danger">
        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
        <ul class="mb-0">
          <?php foreach ($locErrors as $e): ?>
            <li><?= h($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($locSuccess): ?>
      <div class="alert alert-success">
        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h($locSuccess) ?>
      </div>
    <?php endif; ?>

    <div class="row g-4">
      <!-- إعدادات الطقس -->
      <div class="col-lg-8">
        <div class="gdy-card">
          <div class="gdy-card-header">
            <h2><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_8af012a71b', 'إعدادات الاتصال وعرض الطقس')) ?></h2>
            <div class="gdy-summary">
              <span class="pill"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> الحالة: <?= (int)($current['is_active'] ?? 0) === 1 ? __('t_4759637ebc', 'مفعّل') : __('t_4c64abcbc3', 'غير مفعّل') ?></span>
              <span class="pill"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> المدينة: <?= h((string)($current['city'] ?? '—')) ?></span>
              <span class="pill"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> تحديث: <?= (int)($current['refresh_minutes'] ?? 30) ?> دقيقة</span>
            </div>
          </div>

          <div class="gdy-card-body">
            <form method="post" action="" id="weather-settings-form" autocomplete="off">
              <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
              <input type="hidden" name="form_type" value="settings">

              <div class="mb-3">
                <label class="form-label"><?= h(__('t_10c8be0aac', 'مفتاح واجهة الطقس (API key)')) ?></label>
                <div class="input-group">
                  <input type="password" name="api_key" id="api_key" class="form-control" required value="<?= h((string)($current['api_key'] ?? '')) ?>">
                  <button class="btn btn-outline-light" type="button" id="toggle-key" title="<?= h(__('t_13404a383b', 'إظهار/إخفاء')) ?>">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  </button>
                </div>
                <div class="form-text">
                  <?= h(__('t_decf2cd0a7', 'يدعم اختبار الاتصال الافتراضي')) ?> <b>OpenWeatherMap</b><?= h(__('t_9c0b02b5ac', '. إن كنت تستخدم مزوداً آخر، يكفي حفظ البيانات بدون الاختبار.')) ?>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label"><?= h(__('t_5f67dc9b82', 'اختر مدينة من قاعدة البيانات')) ?></label>
                <select name="location_id" id="location_id" class="form-select">
                  <option value="0"><?= h(__('t_a6fd07d5a3', '— اختر مدينة (اختياري) —')) ?></option>
                  <?php foreach ($locations as $loc): ?>
                    <?php
                      $sel = ((int)($current['location_id'] ?? 0) === (int)$loc['id']) ? 'selected' : '';
                      $label = $loc['country_name'] . ' - ' . $loc['city_name'] . ' (' . $loc['country_code'] . ')';
                      if (!(int)$loc['is_active']) { $label .= __('t_4140f443ba', ' [معطّلة]'); }
                    ?>
                    <option
                      value="<?= (int)$loc['id'] ?>"
                      <?= $sel ?>
                      data-city="<?= h($loc['city_name']) ?>"
                      data-cc="<?= h($loc['country_code']) ?>"
                      data-active="<?= (int)$loc['is_active'] ?>"
                    ><?= h($label) ?></option>
                  <?php endforeach; ?>
                </select>
                <div class="form-text">
                  <?= h(__('t_20020f964a', 'عند اختيار مدينة')) ?> <b><?= h(__('t_641298ecec', 'مفعّلة')) ?></b> <?= h(__('t_1643088d14', 'سيتم تعبئة المدينة/رمز الدولة تلقائياً وتعطيل الإدخال اليدوي.')) ?>
                </div>
              </div>

              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label"><?= h(__('t_4b0c82b2e6', 'المدينة (يدويًا)')) ?></label>
                  <input type="text" name="city" id="city" class="form-control" value="<?= h((string)($current['city'] ?? '')) ?>">
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label"><?= h(__('t_008a29c298', 'رمز الدولة (يدويًا)')) ?></label>
                  <input type="text" name="country_code" id="country_code" class="form-control" placeholder="SA, EG, SD ..." value="<?= h((string)($current['country_code'] ?? '')) ?>">
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label d-block"><?= h(__('t_561b69c07c', 'وحدة القياس')) ?></label>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio" name="units" id="units_metric" value="metric" <?= ((string)($current['units'] ?? 'metric')) === 'metric' ? 'checked' : '' ?>>
                  <label class="form-check-label" for="units_metric"><?= h(__('t_7d1ed929bf', 'مئوية (°C)')) ?></label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio" name="units" id="units_imperial" value="imperial" <?= ((string)($current['units'] ?? '')) === 'imperial' ? 'checked' : '' ?>>
                  <label class="form-check-label" for="units_imperial"><?= h(__('t_c44a730cb7', 'فهرنهايت (°F)')) ?></label>
                </div>
              </div>

              <div class="row align-items-center mb-3">
                <div class="col-md-6 mb-3 mb-md-0">
                  <label class="form-label"><?= h(__('t_927c173ba4', 'دقائق تحديث البيانات')) ?></label>
                  <input type="number" min="5" max="1440" step="5" name="refresh_minutes" class="form-control" value="<?= (int)($current['refresh_minutes'] ?? 30) ?>">
                  <div class="form-text"><?= h(__('t_45fe2bdded', 'الحد الأدنى 5 دقائق، والحد الأقصى 1440 دقيقة (يوم).')) ?></div>
                </div>
                <div class="col-md-6">
                  <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" <?= (int)($current['is_active'] ?? 0) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_active"><?= h(__('t_c42fa2a10b', 'تفعيل ويدجيت الطقس في واجهة الموقع')) ?></label>
                  </div>
                </div>
              </div>

              <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary">
                  <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_32be3bade9', 'حفظ الإعدادات')) ?>
                </button>

                <button type="button" class="btn btn-outline-light" id="test-weather">
                  <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_f09cc50204', 'اختبار الاتصال')) ?>
                </button>
              </div>

              <div class="gdy-test-box mt-3">
                <div class="small text-muted">
                  <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  <?= h(__('t_df8eebffd0', 'اختبار الاتصال يجرب جلب طقس المدينة الحالية باستخدام OpenWeatherMap (من المتصفح).')) ?>
                </div>
                <div id="test-result" class="gdy-test-result"></div>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- إدارة المدن/الدول -->
      <div class="col-lg-4">
        <div class="gdy-card mb-4">
          <div class="gdy-card-header">
            <h2><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_e547f05ec6', 'إضافة مدينة/دولة')) ?></h2>
            <span class="gdy-badge"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= (int)$locActiveCount ?>/<?= (int)$locTotal ?> مفعّلة</span>
          </div>
          <div class="gdy-card-body">
            <form method="post" action="" autocomplete="off">
              <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
              <input type="hidden" name="form_type" value="location">

              <div class="mb-3">
                <label class="form-label"><?= h(__('t_fa3fdcf7d8', 'اسم الدولة')) ?></label>
                <input type="text" name="loc_country_name" class="form-control" placeholder="<?= h(__('t_f49e8c5a71', 'السعودية، السودان، مصر...')) ?>" required>
              </div>

              <div class="mb-3">
                <label class="form-label"><?= h(__('t_767ac60906', 'رمز الدولة')) ?></label>
                <input type="text" name="loc_country_code" class="form-control" placeholder="SA, SD, EG ..." required>
                <div class="form-text"><?= h(__('t_7616956b92', 'يفضل 2–3 أحرف (مثال: SA).')) ?></div>
              </div>

              <div class="mb-3">
                <label class="form-label"><?= h(__('t_cfb670c977', 'اسم المدينة')) ?></label>
                <input type="text" name="loc_city_name" class="form-control" placeholder="<?= h(__('t_70baf178c1', 'الرياض، الخرطوم، القاهرة...')) ?>" required>
              </div>

              <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="loc_is_active" id="loc_is_active" value="1" checked>
                <label class="form-check-label" for="loc_is_active"><?= h(__('t_5d28eae667', 'مفعّلة للاختيار في الإعدادات')) ?></label>
              </div>

              <button type="submit" class="btn btn-outline-light btn-sm w-100">
                <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_6b97427299', 'إضافة المدينة')) ?>
              </button>
            </form>

            <div class="gdy-divider"></div>

            <div class="mb-2 d-flex align-items-center justify-content-between">
              <div class="small text-muted">
                <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_ce980312f5', 'المدن المسجّلة')) ?>
              </div>
              <span class="gdy-badge"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= (int)$locTotal ?></span>
            </div>

            <div class="mb-2">
              <input type="text" id="locations-search" class="form-control form-control-sm" placeholder="<?= h(__('t_e7ad92e879', 'بحث سريع داخل القائمة...')) ?>">
            </div>

            <div class="table-responsive" style="max-height:260px; overflow:auto;">
              <table class="table table-sm table-dark align-middle mb-0" id="locations-table">
                <thead>
                  <tr>
                    <th><?= h(__('t_55a3d09c71', 'الدولة')) ?></th>
                    <th><?= h(__('t_a213cd1841', 'المدينة')) ?></th>
                    <th><?= h(__('t_a7dae77bed', 'رمز')) ?></th>
                    <th class="text-end"><?= h(__('t_155e7129d1', 'إجراء')) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!$locations): ?>
                    <tr>
                      <td colspan="4" class="text-center text-muted"><?= h(__('t_21490c8eea', 'لا توجد مدن مسجّلة بعد.')) ?></td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($locations as $loc): ?>
                      <tr data-row="<?= (int)$loc['id'] ?>">
                        <td><?= h($loc['country_name']) ?></td>
                        <td>
                          <?= h($loc['city_name']) ?>
                          <?php if ((int)$loc['is_active'] === 1): ?>
                            <span class="badge bg-success ms-1"><?= h(__('t_641298ecec', 'مفعّلة')) ?></span>
                          <?php else: ?>
                            <span class="badge bg-secondary ms-1"><?= h(__('t_2fab10b091', 'معطّلة')) ?></span>
                          <?php endif; ?>
                        </td>
                        <td><?= h($loc['country_code']) ?></td>
                        <td class="text-end">
                          <div class="gdy-mini-actions">
                            <form method="post" action="">
                              <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                              <input type="hidden" name="form_type" value="location_action">
                              <input type="hidden" name="action" value="toggle">
                              <input type="hidden" name="location_row_id" value="<?= (int)$loc['id'] ?>">
                              <button type="submit" class="btn btn-sm btn-outline-light" title="<?= h(__('t_47388f3138', 'تفعيل/تعطيل')) ?>">
                                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                              </button>
                            </form>

                            <form method="post" action="" data-confirm='حذف هذه المدينة نهائياً؟'>
                              <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                              <input type="hidden" name="form_type" value="location_action">
                              <input type="hidden" name="action" value="delete">
                              <input type="hidden" name="location_row_id" value="<?= (int)$loc['id'] ?>">
                              <button type="submit" class="btn btn-sm btn-outline-danger" title="<?= h(__('t_3b9854e1bb', 'حذف')) ?>">
                                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                              </button>
                            </form>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

            <div class="small text-muted mt-2">
              <?= h(__('t_b3d39c0c59', 'تلميح: يمكنك')) ?> <b><?= h(__('t_47388f3138', 'تفعيل/تعطيل')) ?></b> <?= h(__('t_eb00a8de42', 'المدن دون حذفها، وستختفي المدن المعطّلة من الاختيار عند الحفظ.')) ?>
            </div>

          </div>
        </div>

        <div class="gdy-card">
          <div class="gdy-card-header">
            <h2><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_d5d2cdc290', 'تحسينات سريعة')) ?></h2>
          </div>
          <div class="gdy-card-body">
            <ul class="small text-muted mb-0">
              <li><?= h(__('t_b08da4f49b', 'عند اختيار مدينة من القائمة سيتم تعبئة المدينة/رمز الدولة تلقائياً.')) ?></li>
              <li><?= h(__('t_de50a8f561', 'زر اختبار الاتصال يساعدك على التأكد من صحة المفتاح والمدينة قبل الحفظ.')) ?></li>
              <li><?= h(__('t_0e779a651b', 'بحث سريع داخل جدول المدن لتسهيل الوصول.')) ?></li>
              <li><?= h(__('t_3a1df3448e', 'زر تفعيل/تعطيل أو حذف للمدن من نفس الصفحة.')) ?></li>
            </ul>
          </div>
        </div>
      </div>

    </div>

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // إظهار/إخفاء API key
  const apiKeyInput = document.getElementById('api_key');
  const toggleKeyBtn = document.getElementById('toggle-key');
  if (apiKeyInput && toggleKeyBtn) {
    toggleKeyBtn.addEventListener('click', function() {
      const isPass = apiKeyInput.getAttribute('type') === 'password';
      apiKeyInput.setAttribute('type', isPass ? 'text' : 'password');
      toggleKeyBtn.innerHTML = isPass ? '<svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>' : '<svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>';
    });
  }

  // اختيار مدينة من القائمة => تعبئة الحقول اليدوية + تعطيلها
  const locationSel = document.getElementById('location_id');
  const cityInput = document.getElementById('city');
  const ccInput = document.getElementById('country_code');

  function applyLocationToFields() {
    if (!locationSel || !cityInput || !ccInput) return;
    const opt = locationSel.options[locationSel.selectedIndex];
    const locId = parseInt(locationSel.value || '0', 10);

    if (locId > 0 && opt) {
      const isActive = (opt.getAttribute('data-active') || '0') === '1';
      const city = opt.getAttribute('data-city') || '';
      const cc = opt.getAttribute('data-cc') || '';

      if (isActive) {
        cityInput.value = city;
        ccInput.value = cc;
        cityInput.setAttribute('disabled', 'disabled');
        ccInput.setAttribute('disabled', 'disabled');
        cityInput.setAttribute('readonly', 'readonly');
        ccInput.setAttribute('readonly', 'readonly');
      } else {
        // إن كانت المدينة معطلة: لا نعطل الإدخال اليدوي
        cityInput.removeAttribute('disabled');
        ccInput.removeAttribute('disabled');
        cityInput.removeAttribute('readonly');
        ccInput.removeAttribute('readonly');
      }
    } else {
      cityInput.removeAttribute('disabled');
      ccInput.removeAttribute('disabled');
      cityInput.removeAttribute('readonly');
      ccInput.removeAttribute('readonly');
    }
  }
  if (locationSel) {
    locationSel.addEventListener('change', applyLocationToFields);
    applyLocationToFields();
  }

  // بحث داخل جدول المدن
  const searchInput = document.getElementById('locations-search');
  const table = document.getElementById('locations-table');
  if (searchInput && table) {
    searchInput.addEventListener('input', function() {
      const q = (this.value || '').toLowerCase().trim();
      const rows = table.querySelectorAll('tbody tr');
      rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(q) ? '' : 'none';
      });
    });
  }

  // اختبار الطقس (OpenWeatherMap) من المتصفح
  const testBtn = document.getElementById('test-weather');
  const resultBox = document.getElementById('test-result');

  function getUnitsValue() {
    const i = document.getElementById('units_imperial');
    if (i && i.checked) return 'imperial';
    return 'metric';
  }

  function showResult(type, html) {
    if (!resultBox) return;
    resultBox.classList.remove('ok', 'err');
    resultBox.classList.add(type === 'ok' ? 'ok' : 'err');
    resultBox.innerHTML = html;
    resultBox.style.display = 'block';
  }

  if (testBtn && apiKeyInput && cityInput && ccInput) {
    testBtn.addEventListener('click', async function() {
      const key = (apiKeyInput.value || '').trim();
      const city = (cityInput.value || '').trim();
      const cc = (ccInput.value || '').trim();
      const units = getUnitsValue();

      if (!key) {
        showResult('err', '<svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> يرجى إدخال API key أولاً.');
        return;
      }
      if (!city) {
        showResult('err', '<svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> يرجى تحديد/إدخال المدينة أولاً.');
        return;
      }

      testBtn.disabled = true;
      const oldHtml = testBtn.innerHTML;
      testBtn.innerHTML = '<svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> جاري الاختبار...';

      try {
        const q = cc ? encodeURIComponent(city + ',' + cc) : encodeURIComponent(city);
        const url = `https://api.openweathermap.org/data/2.5/weather?q=${q}&appid=${encodeURIComponent(key)}&units=${encodeURIComponent(units)}`;

        const res = await fetch(url, { method: 'GET' });
        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
          const msg = data && data.message ? data.message : 'تعذر جلب البيانات';
          showResult('err', `<div><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> فشل الاختبار: <b>${msg}</b></div>
            <div class="small text-muted mt-1">تأكد من صحة المفتاح واسم المدينة ورمز الدولة.</div>`);
        } else {
          const name = data.name || city;
          const temp = (data.main && typeof data.main.temp !== 'undefined') ? data.main.temp : '';
          const desc = (data.weather && data.weather[0] && data.weather[0].description) ? data.weather[0].description : '';
          const hum  = (data.main && typeof data.main.humidity !== 'undefined') ? data.main.humidity : '';
          const unitLabel = units === 'metric' ? '°C' : '°F';

          showResult('ok', `<div><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> نجح الاختبار!</div>
            <div class="mt-2">
              <div><b>المدينة:</b> ${name}</div>
              <div><b>الحرارة:</b> ${temp} ${unitLabel}</div>
              <div><b>الوصف:</b> ${desc}</div>
              <div><b>الرطوبة:</b> ${hum}%</div>
            </div>`);
        }
      } catch (e) {
        showResult('err', `<svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> فشل الاختبار بسبب خطأ بالشبكة أو CORS.`);
      } finally {
        testBtn.disabled = false;
        testBtn.innerHTML = oldHtml;
      }
    });
  }
});
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
