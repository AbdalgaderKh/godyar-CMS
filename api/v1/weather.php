<?php
declare(strict_types=1);

// api/v1/weather.php
// Endpoint آمن لجلب الطقس باستخدام إعدادات لوحة التحكم (OpenWeatherMap)

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_unavailable']);
    exit;
}

// Helpers
function gdy_norm_city(string $city): string {
    $city = trim($city);
    $city = preg_replace('/\s+/u', ' ', $city) ?? $city;
    return mb_substr($city, 0, 80, 'UTF-8');
}

function gdy_wind_dir(?float $deg): string {
    if ($deg === null) return '—';
    $deg = fmod(($deg + 360.0), 360.0);
    $dirs = [
        'شمال', 'شمال شرق', 'شرق', 'جنوب شرق',
        'جنوب', 'جنوب غرب', 'غرب', 'شمال غرب'
    ];
    $idx = (int)round($deg / 45.0) % 8;
    return __($dirs[$idx] ?? '—');
}

// قراءة إعدادات الطقس (سجل واحد)
$settings = [
    'api_key'         => '',
    'city'            => '',
    'country_code'    => '',
    'units'           => 'metric',
    'is_active'       => 0,
    'refresh_minutes' => 30,
];

try {
    $chk = $pdo->query("SHOW TABLES LIKE 'weather_settings'");
    if (!$chk || !$chk->fetchColumn()) {
        echo json_encode(['ok' => true, 'active' => false]);
        exit;
    }

    $stmt = $pdo->query("SELECT api_key, city, country_code, units, is_active, refresh_minutes FROM weather_settings ORDER BY id ASC LIMIT 1");
    $row  = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    if (is_array($row) && $row) {
        $settings = array_merge($settings, $row);
    }
} catch (Throwable $e) {
    @error_log('[api.weather] settings read error: ' . $e->getMessage());
    echo json_encode(['ok' => true, 'active' => false]);
    exit;
}

$isActive = (int)($settings['is_active'] ?? 0) === 1;
if (!$isActive) {
    echo json_encode(['ok' => true, 'active' => false]);
    exit;
}

$apiKey = trim((string)($settings['api_key'] ?? ''));
if ($apiKey === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'missing_api_key']);
    exit;
}

$units = (string)($settings['units'] ?? 'metric');
if (!in_array($units, ['metric', 'imperial'], true)) {
    $units = 'metric';
}

$defaultCity = gdy_norm_city((string)($settings['city'] ?? ''));
$defaultCC   = strtoupper(trim((string)($settings['country_code'] ?? '')));

$city = isset($_GET['city']) ? gdy_norm_city((string)$_GET['city']) : $defaultCity;
$cc   = isset($_GET['cc']) ? strtoupper(trim((string)$_GET['cc'])) : $defaultCC;

if ($city === '') {
    echo json_encode(['ok' => true, 'active' => false]);
    exit;
}

// كاش بسيط على مستوى السيرفر لتقليل طلبات OpenWeather
$refreshMin = (int)($settings['refresh_minutes'] ?? 30);
if ($refreshMin < 5) $refreshMin = 5;
if ($refreshMin > 1440) $refreshMin = 1440;

$cacheDir = __DIR__ . '/../../cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
$cacheKey = hash('sha256', mb_strtolower($city, 'UTF-8') . '|' . $cc . '|' . $units);
$cacheFile = rtrim($cacheDir, '/\\') . '/weather_' . $cacheKey . '.json';

// إن كان الكاش حديثاً، نعيده
if (is_file($cacheFile)) {
    $age = time() - (int)@filemtime($cacheFile);
    if ($age >= 0 && $age < ($refreshMin * 60)) {
        $cached = @file_get_contents($cacheFile);
        if ($cached !== false && $cached !== '') {
            echo $cached;
            exit;
        }
    }
}

// بناء رابط OpenWeatherMap
$q = $city;
if ($cc !== '') {
    $q .= ',' . $cc;
}

$url = 'https://api.openweathermap.org/data/2.5/weather?q=' . rawurlencode($q)
    . '&appid=' . rawurlencode($apiKey)
    . '&units=' . rawurlencode($units)
    . '&lang=ar';

$resp = null;
$httpCode = 0;

try {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $ctx = stream_context_create([
            'http' => ['timeout' => 12],
        ]);
        $resp = @file_get_contents($url, false, $ctx);
        $httpCode = 200;
    }
} catch (Throwable $e) {
    @error_log('[api.weather] request error: ' . $e->getMessage());
}

if (!$resp) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'upstream_no_response']);
    exit;
}

$data = json_decode((string)$resp, true);
if (!is_array($data)) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'upstream_invalid_json']);
    exit;
}

// OpenWeather قد يرجع cod كـ int أو string
$cod = $data['cod'] ?? 200;
if ((string)$cod !== '200') {
    $msg = (string)($data['message'] ?? 'upstream_error');
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'city_not_found', 'message' => $msg]);
    exit;
}

$temp = $data['main']['temp'] ?? null;
$windSpeed = $data['wind']['speed'] ?? null;
$windDeg   = $data['wind']['deg'] ?? null;

$dt        = (int)($data['dt'] ?? time());
$tzOffset  = (int)($data['timezone'] ?? 0);
$localTs   = $dt + $tzOffset;
$localTime = gmdate('H:i', $localTs);

$out = [
    'ok'     => true,
    'active' => true,
    'city'   => (string)($data['name'] ?? $city),
    'units'  => $units,
    'temp'   => is_numeric($temp) ? (float)$temp : null,
    'wind_speed' => is_numeric($windSpeed) ? (float)$windSpeed : null,
    'wind_deg'   => is_numeric($windDeg) ? (float)$windDeg : null,
    'wind_dir'   => gdy_wind_dir(is_numeric($windDeg) ? (float)$windDeg : null),
    'time'       => $localTime,
];

$json = json_encode($out, JSON_UNESCAPED_UNICODE);
if ($json === false) {
    $json = '{"ok":false,"error":"json_encode_failed"}';
}

@file_put_contents($cacheFile, $json);

echo $json;
