<?php

require_once __DIR__ . '/../_admin_guard.php';
if (!isset($pageTitle)) {
    $pageTitle = __('t_a06ee671f4', 'لوحة التحكم');
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars((string)(function_exists('current_lang') ? current_lang() : (string)($_SESSION['lang'] ?? 'ar')), ENT_QUOTES, 'UTF-8') ?>" dir="<?= ((function_exists('current_lang') ? current_lang() : (string)($_SESSION['lang'] ?? 'ar')) === 'ar' ? 'rtl' : 'ltr') ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — Godyar Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css">
  <style>
    :root {
      --godyar-bg: #04151f;
      --godyar-bg-soft: rgba(4, 21, 31, 0.94);
      --godyar-accent: #1abc9c;
      --godyar-accent-soft: rgba(26, 188, 156, 0.25);
      --godyar-text: #f9fafb;
      --godyar-muted: #9ca3af;
    }
    [data-theme="dark"] {
      --godyar-bg: #020617;
      --godyar-bg-soft: rgba(15,23,42,0.96);
    }
    body {
      min-height: 100vh;
      margin: 0;
      background: radial-gradient(circle at top left, #14b8a6 0, #020617 45%, #020617 100%);
      color: var(--godyar-text);
      font-family: "Tajawal", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }
    .admin-shell {
      min-height: 100vh;
      background: linear-gradient(135deg, rgba(15,23,42,0.96), rgba(8,47,73,0.98));
    }
    /* جعل الشريط الجانبي ولوحة المحتوى تتمد بشكل كامل بدون فراغات جانبية */
    #adminSidebar {
      height: 100vh;
      position: sticky;
      top: 0;
    }
    .admin-content {
      width: 100%;
      padding: 1.5rem 1.75rem;
    }
    .admin-content.container-fluid {
      max-width: 100%;
    }
    @media (max-width: 991.98px) {
      .admin-shell {
        grid-template-columns: 1fr;
      }
      #adminSidebar {
        position: static;
        height: auto;
      }
      .admin-content {
        padding: 1rem;
      }
    }
    
.glass-card, .gdy-card {
      background: rgba(15,23,42,0.92);
      border-radius: 1.1rem;
      border: 1px solid rgba(148,163,184,0.28);
      box-shadow:
        0 18px 45px rgba(15,23,42,0.9),
        0 0 0 1px rgba(15,23,42,0.8);
      color: var(--godyar-text);
    }
    .glass-card .card-header, .gdy-card .card-header {
      background: transparent;
      border-bottom: 1px solid rgba(148,163,184,0.25);
      color: var(--godyar-text);
    }
    .glass-card .card-title,
    .gdy-card .card-title,
    .glass-card h1,
    .gdy-card h1,
    .glass-card h2,
    .gdy-card h2,
    .glass-card h3,
    .gdy-card h3 {
      color: var(--godyar-text);
    }
    .glass-card .text-muted {
      color: var(--godyar-muted) !important;
    }
    .table {
      color: var(--godyar-text);
    }
    .table thead.table-light {
      background: rgba(15,23,42,0.9);
      color: #e5e7eb;
    }
    .table-striped tbody tr:nth-of-type(odd) {
      background-color: rgba(15,23,42,0.85);
    }
    .table-hover tbody tr:hover {
      background-color: rgba(15,23,42,0.95);
    }
    .btn-primary {
      background: var(--godyar-accent);
      border-color: var(--godyar-accent);
    }
    .btn-outline-light {
      border-color: rgba(148,163,184,0.8);
      color: #e5e7eb;
    }
    .btn-outline-light:hover {
      background: rgba(148,163,184,0.12);
      color: #f9fafb;
    }
    .bg-soft-primary {
      background: rgba(56,189,248,0.12);
    }

/* Godyar admin page header (shared across all index pages) */
.gdy-page-header {
  background: radial-gradient(circle at top right, rgba(56,189,248,0.22), rgba(15,23,42,0.98) 45%, rgba(15,23,42,1) 100%);
  border-radius: 0.9rem;
  padding: 0.9rem 1.25rem;
  border: 1px solid rgba(148,163,184,0.45);
  box-shadow: 0 12px 32px rgba(15,23,42,0.9);
}
.gdy-page-header h1,
.gdy-page-header .page-title {
  color: #f9fafb;
  margin-bottom: 0.25rem;
}
.gdy-page-header p {
  color: #cbd5f5;
  font-size: 0.8rem;
  margin-bottom: 0;
}
.gdy-page-header .btn {
  border-radius: 999px;
  font-size: 0.78rem;
  padding-inline: 0.9rem;
  box-shadow: 0 4px 12px rgba(15,23,42,0.8);
  border: none;
}
.gdy-page-header .btn-outline-light {
  border: 1px solid rgba(148,163,184,0.7);
  background: rgba(15,23,42,0.8);
  color: #e5e7eb;
}
.gdy-page-header .btn-outline-light:hover {
  background: rgba(148,163,184,0.18);
  color: #f9fafb;
}
    .bg-soft-secondary {
      background: rgba(148,163,184,0.16);
    }
  </style>
</head>
<body>
<div class="admin-shell">
