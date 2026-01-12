<?php
// UTF-8 no BOM
require_once __DIR__ . '/../../includes/bootstrap.php';
if (session_status() === PHP_SESSION_NONE) { @session_start(); }

// Load settings (class or PDO fallback)
$settings = [];
try {
  if (class_exists('Settings')) { $settings = Settings::getAll(); }
  elseif (isset($pdo)) {
    $st = $pdo->query("SELECT `key`,`value` FROM settings");
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) { $settings[$row['key']] = $row['value']; }
  }
} catch (Throwable $e) { error_log(get_class($e).": ".$e->getMessage()); }

$site_name    = $settings['site_name'] ?? ($settings['site_title'] ?? 'Godyar');
$main_menu    = json_decode($settings['menu_main']   ?? '[]', true) ?: [];
$footer_links = json_decode($settings['menu_footer'] ?? '[]', true) ?: [];
$social_links = json_decode($settings['social_links']?? '[]', true) ?: [];
$footer_about = $settings['footer_about'] ?? '';

function view_include($path){
  if (file_exists($path)) { require $path; }
  else { echo "<p style='padding:16px;border:1px dashed #ddd;border-radius:8px'>View not found: ".htmlspecialchars($path)."</p>"; }
}
$slug=$_GET['slug']??null;view_include(__DIR__ . '/../views/author.php');
