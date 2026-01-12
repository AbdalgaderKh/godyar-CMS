<!doctype html>
<html lang="ar" dir="rtl">
<head>
  
    <?php require ROOT_PATH . '/frontend/views/partials/theme_head.php'; ?>
<meta charset="utf-8">
  <title>الموقع تحت الصيانة — Godyar</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{
      --turquoise-soft: rgba(45,212,191,0.08);
      --turquoise-strong: #14b8a6;
      --bg-deep: #020617;
    }
    *{box-sizing:border-box;margin:0;padding:0;}
    body{
      min-height:100vh;
      display:flex;
      align-items:center;
      justify-content:center;
      font-family: system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;
      background:
        radial-gradient(circle at top left,rgba(45,212,191,.18),transparent 55%),
        radial-gradient(circle at bottom right,rgba(56,189,248,.16),transparent 55%),
        var(--bg-deep);
      color:#e5e7eb;
    }
    .shell{
      padding:16px;
      width:100%;
      max-width:480px;
    }
    .card{
      background:radial-gradient(circle at top left,var(--turquoise-soft),rgba(15,23,42,.97));
      border-radius:24px;
      padding:26px 24px 22px;
      box-shadow:0 26px 80px rgba(0,0,0,.65);
      border:1px solid rgba(148,163,184,.55);
      backdrop-filter:blur(18px);
      -webkit-backdrop-filter:blur(18px);
      text-align:center;
      position:relative;
      overflow:hidden;
    }
    .card::before{
      content:"";
      position:absolute;
      inset:-40%;
      background: radial-gradient(circle at 0 0,rgba(45,212,191,.18),transparent 55%);
      opacity:.8;
      pointer-events:none;
    }
    .badge{
      position:relative;
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:4px 10px;
      border-radius:999px;
      border:1px solid rgba(45,212,191,.7);
      background:rgba(15,23,42,.7);
      font-size:.75rem;
      color:#a5f3fc;
      margin-bottom:10px;
    }
    .pulse-dot{
      width:7px; height:7px;
      border-radius:999px;
      background:#22c55e;
      box-shadow:0 0 0 4px rgba(34,197,94,.4);
      animation:pulse 1.8s infinite;
    }
    @keyframes pulse{
      0%{transform:scale(1);opacity:1;}
      70%{transform:scale(1.8);opacity:0;}
      100%{transform:scale(1);opacity:0;}
    }
    h1{
      position:relative;
      font-size:1.5rem;
      margin-bottom:8px;
    }
    p{
      position:relative;
      font-size:.9rem;
      color:#cbd5f5;
      margin-bottom:4px;
    }
    .hint{
      font-size:.8rem;
      opacity:.8;
      margin-top:6px;
    }
  </style>
</head>
<body>
  <div class="shell">
    <div class="card">
      <div class="badge">
        <span class="pulse-dot"></span>
        <span>جوديـار — صيانة جارية</span>
      </div>
      <h1>الموقع تحت الصيانة مؤقتاً</h1>
      <p>نعمل حالياً على تحسين الأداء وتحديث لوحة التحكم والمحتوى.</p>
      <p>سنعود للعمل خلال وقت قصير بإذن الله 🤝</p>
      <p class="hint">إذا كنت من فريق الإدارة، يمكنك الدخول إلى لوحة التحكم بشكل طبيعي.</p>
    </div>
  </div>
</body>
</html>
