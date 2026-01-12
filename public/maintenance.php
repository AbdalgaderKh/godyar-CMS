<?php
// godyar/public/maintenance.php
// صفحة صيانة تظهر للزوار عند تفعيل maintenance.flag

// نحاول قراءة البيانات إن لم تكن متوفرة
if (!isset($info) || !is_array($info)) {
    $flag = defined('GODYAR_ROOT')
        ? GODYAR_ROOT . '/storage/maintenance.flag'
        : __DIR__ . '/../storage/maintenance.flag';

    if (is_file($flag)) {
        $raw  = @file_get_contents($flag);
        $data = @json_decode($raw, true);
        if (is_array($data)) {
            $info = $data;
        } else {
            $info = [];
        }
    } else {
        $info = [];
    }
}

$reason = trim((string)($info['reason'] ?? 'نقوم حالياً ببعض أعمال الصيانة والتحديث. سنعود للعمل في أقرب وقت ممكن.'));
$until  = trim((string)($info['until']  ?? ''));
$time   = trim((string)($info['time']   ?? ''));
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  
    <?php require ROOT_PATH . '/frontend/views/partials/theme_head.php'; ?>
<meta charset="UTF-8">
  <title>الموقع في وضع الصيانة</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root {
      --bg: #020617;
      --card-bg: rgba(15,23,42,0.9);
      --accent: #38bdf8;
      --accent-soft: rgba(56,189,248,0.3);
      --text-main: #e5e7eb;
      --text-muted: #9ca3af;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      min-height: 100vh;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background:
        radial-gradient(circle at top left, #0ea5e9 0, transparent 55%),
        radial-gradient(circle at bottom right, #6366f1 0, transparent 55%),
        var(--bg);
      color: var(--text-main);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .maintenance-wrapper {
      max-width: 560px;
      width: 100%;
      background: var(--card-bg);
      border-radius: 24px;
      border: 1px solid rgba(148,163,184,0.4);
      box-shadow:
        0 20px 40px rgba(15,23,42,0.9),
        0 0 0 1px rgba(15,23,42,0.9);
      padding: 28px 24px 24px;
      backdrop-filter: blur(14px);
    }

    .maintenance-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      padding: 5px 10px;
      border-radius: 999px;
      background: rgba(15,23,42,0.9);
      border: 1px solid rgba(148,163,184,0.5);
      margin-bottom: 16px;
    }

    .maintenance-badge-dot {
      width: 10px;
      height: 10px;
      border-radius: 999px;
      background: #fbbf24;
    }

    h1 {
      font-size: 26px;
      margin-bottom: 8px;
    }

    .subtitle {
      font-size: 14px;
      color: var(--text-muted);
      margin-bottom: 20px;
      line-height: 1.6;
    }

    .notice {
      padding: 14px 14px;
      border-radius: 16px;
      background: radial-gradient(circle at top right, rgba(56,189,248,0.18), transparent 60%);
      border: 1px solid rgba(148,163,184,0.4);
      font-size: 14px;
      line-height: 1.7;
      margin-bottom: 18px;
    }

    .pill-row {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-bottom: 18px;
      font-size: 13px;
    }

    .pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 5px 10px;
      border-radius: 999px;
      background: rgba(15,23,42,0.9);
      border: 1px solid rgba(148,163,184,0.5);
      color: var(--text-muted);
    }

    .pill span {
      color: var(--text-main);
      font-weight: 500;
    }

    .footer-note {
      font-size: 12px;
      color: var(--text-muted);
      border-top: 1px dashed rgba(148,163,184,0.6);
      padding-top: 10px;
      margin-top: 4px;
      display: flex;
      justify-content: space-between;
      gap: 10px;
      flex-wrap: wrap;
    }

    .logo-mark {
      font-weight: 600;
      letter-spacing: 0.04em;
      color: var(--accent);
    }

    .hint {
      font-size: 12px;
      color: var(--text-muted);
      margin-top: 10px;
      line-height: 1.6;
    }

    @media (max-width: 480px) {
      .maintenance-wrapper {
        padding: 22px 18px 18px;
        border-radius: 18px;
      }
      h1 {
        font-size: 22px;
      }
    }
  </style>
</head>
<body>
  <div class="maintenance-wrapper">
    <div class="maintenance-badge">
      <div class="maintenance-badge-dot"></div>
      <div>الموقع في وضع الصيانة</div>
    </div>

    <h1>سنعود للعمل قريباً 👋</h1>
    <p class="subtitle">
      نقوم حالياً بإجراء تحديثات وتحسينات على النظام لضمان تجربة أفضل وأسرع لكم.
    </p>

    <div class="notice">
      <?= nl2br(htmlspecialchars($reason, ENT_QUOTES, 'UTF-8')) ?>
    </div>

    <div class="pill-row">
      <?php if ($until !== ''): ?>
        <div class="pill">
          ⏱ التقدير:
          <span><?= htmlspecialchars($until, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      <?php endif; ?>

      <?php if ($time !== ''): ?>
        <div class="pill">
          🛠 بدء الصيانة:
          <span><?= htmlspecialchars($time, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      <?php endif; ?>
    </div>

    <p class="hint">
      إن كانت لديك حاجة عاجلة، يمكنك التواصل مع إدارة الموقع عبر قنوات التواصل المتاحة في صفحة "اتصل بنا" عند عودة الموقع للعمل.
    </p>

    <div class="footer-note">
      <span>نعتذر عن الإزعاج، وشكراً لصبركم.</span>
      <span class="logo-mark">Godyar News</span>
    </div>
  </div>
</body>
</html>
