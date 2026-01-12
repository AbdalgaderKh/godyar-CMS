<?php
declare(strict_types=1);

/**
 * Godyar CMS Installer (RAW Clean)
 * - Shared-hosting friendly (MariaDB/MySQL)
 * - No demo content
 * - Ignores safe duplicate errors (re-runnable)
 * - Strips foreign keys automatically to avoid errno 150 on shared hosting
 */

$ROOT = realpath(__DIR__ . '/..');
if ($ROOT === false) { http_response_code(500); exit('Invalid root'); }

header('Content-Type: text/html; charset=utf-8');

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function csrf_token(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['csrf'];
}
function require_post_token(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { throw new RuntimeException('Invalid request method'); }
    $t = $_POST['csrf'] ?? '';
    if (!$t || !hash_equals($_SESSION['csrf'] ?? '', (string)$t)) { throw new RuntimeException('CSRF token mismatch'); }
}
function env_line(string $k, string $v): string {
    $v = str_replace(["\r","\n"], '', $v);
    // Quote if contains spaces or special chars
    if ($v === '' || preg_match('/\s|["\'\\\\]/', $v)) {
        $v = '"' . addcslashes($v, "\\\"") . '"';
    }
    return $k . '=' . $v;
}

function render(string $title, string $body): void {
    $css = <<<CSS
    :root{--bg:#f6f7fb;--card:#ffffff;--text:#0f172a;--muted:#64748b;--border:#e2e8f0;--primary:#2563eb;--primary2:#1d4ed8;--danger:#dc2626;--shadow:0 10px 30px rgba(2,6,23,.08);--focus:0 0 0 3px rgba(37,99,235,.25)}
    html[data-theme="dark"]{--bg:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#9ca3af;--border:#1f2937;--primary:#60a5fa;--primary2:#3b82f6;--danger:#f87171;--shadow:0 10px 30px rgba(0,0,0,.35);--focus:0 0 0 3px rgba(96,165,250,.35)}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial; background:#0b1220; color:#e5e7eb; margin:0}
    .wrap{max-width:980px;margin:40px auto;padding:0 16px}
    .card{background:#0f1a33;border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:20px;box-shadow:0 10px 30px rgba(0,0,0,.35)}
    h1{margin:0 0 8px;font-size:22px}
    .sub{opacity:.8;margin:0 0 16px}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    label{display:block;font-size:13px;opacity:.9;margin-bottom:6px}
    input{width:100%;padding:10px 12px;border-radius:10px;border:1px solid rgba(255,255,255,.12);background:#0b1220;color:#e5e7eb}
    .btn{display:inline-block;padding:10px 14px;border-radius:10px;background:var(--primary);color:#fff;text-decoration:none;border:0;cursor:pointer}
    .btn2{display:inline-block;padding:10px 14px;border-radius:10px;background:#111827;color:#e5e7eb;text-decoration:none;border:1px solid rgba(255,255,255,.12)}
    .err{background:#3b0a0a;border:1px solid rgba(255,255,255,.12);padding:10px 12px;border-radius:12px;margin:0 0 12px}
    .ok{background:#0b3b1c;border:1px solid rgba(255,255,255,.12);padding:10px 12px;border-radius:12px;margin:0 0 12px}
    .muted{opacity:.7;font-size:13px}
    .steps{display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap}
    .step{padding:6px 10px;border-radius:999px;border:1px solid rgba(255,255,255,.12);opacity:.6}
    .step.on{opacity:1;background:rgba(37,99,235,.15);border-color:rgba(37,99,235,.55)}
    @media (max-width:720px){.grid{grid-template-columns:1fr}}

    /* Dark-mode friendly inputs & tables */
    input,select,textarea{background:var(--card);color:var(--text)}
    input::placeholder{color:var(--muted)}
    .steps .step{border:1px solid var(--border);color:var(--muted);background:var(--card)}
    .steps .step.on{background:rgba(37,99,235,.12);color:var(--text);border-color:rgba(37,99,235,.35)}
    html[data-theme="dark"] .steps .step.on{background:rgba(96,165,250,.14);border-color:rgba(96,165,250,.35)}
    .note{background:rgba(37,99,235,.10);border-color:rgba(37,99,235,.25)}
    html[data-theme="dark"] .note{background:rgba(96,165,250,.10);border-color:rgba(96,165,250,.25)}
    .err{background:rgba(220,38,38,.10);border-color:rgba(220,38,38,.25)}
    html[data-theme="dark"] .err{background:rgba(248,113,113,.10);border-color:rgba(248,113,113,.25)}
    .hdr{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
    .theme-toggle{border:1px solid var(--border);background:var(--card);color:var(--text);border-radius:12px;padding:10px 12px;cursor:pointer;line-height:1}
    .theme-toggle:focus{outline:none;box-shadow:var(--focus)}
CSS;

    $step = (int)($_GET['step'] ?? 1);
    echo "<!doctype html><html lang='ar' dir='rtl'><head><meta charset='utf-8'>".
         "<meta name='viewport' content='width=device-width,initial-scale=1'>".
         "<title>".h($title)." - Godyar CMS Installer</title><style>{$css}</style><script>
(function(){
  try{
    var t = localStorage.getItem('gdy_installer_theme');
    if(!t){
      t = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
    }
    document.documentElement.dataset.theme = t;
  }catch(e){}
})();
function __gdySetInstallerTheme(t){
  try{ document.documentElement.dataset.theme = t; localStorage.setItem('gdy_installer_theme', t);}catch(e){}
}
</script></head><body><div class='wrap'><div class='card'>";
    echo "<div class='hdr'><div class='hdr-title'><h1>مثبّت Godyar CMS</h1><p class='sub'>حزمة خام نظيفة — بدون محتوى تجريبي</p></div>"."<button type='button' class='theme-toggle' id='themeToggle' aria-label='تبديل الوضع'>🌙</button></div>";
    echo "<div class='steps'>".
         "<div class='step ".($step===1?'on':'')."'>1) بدء</div>".
         "<div class='step ".($step===2?'on':'')."'>2) قاعدة البيانات</div>".
         "<div class='step ".($step===3?'on':'')."'>3) إنشاء الجداول</div>".
         "</div>";
    echo "<h2 style='margin:0 0 12px;font-size:18px'>".h($title)."</h2>";
    echo $body;
    echo "<hr style='border:0;border-top:1px solid rgba(255,255,255,.10);margin:18px 0'>";
    echo "<div class='muted'>بعد نجاح التثبيت، سيتم قفل مجلد /install تلقائيًا. يُفضل حذفه.</div>";
    echo "</div></div><script>
(function(){
  var btn = document.getElementById('themeToggle');
  if(!btn) return;
  function icon(){
    var t = document.documentElement.dataset.theme || 'light';
    btn.textContent = (t === 'dark') ? '☀️' : '🌙';
  }
  icon();
  btn.addEventListener('click', function(){
    var t = document.documentElement.dataset.theme || 'light';
    __gdySetInstallerTheme(t === 'dark' ? 'light' : 'dark');
    icon();
  });
})();
</script></body></html>";
    exit;
}

/**
 * Split SQL into statements safely:
 * - strips line comments (--, #) and block comments
 * - splits on semicolons outside strings
 */
function split_sql_statements(string $sql): array {
    $sql = preg_replace('~^\xEF\xBB\xBF~', '', $sql); // BOM
    $len = strlen($sql);
    $out = [];
    $buf = '';
    $inS = false; $inD = false; $inB = false;
    $inLine = false; $inBlock = false;

    for ($i=0; $i<$len; $i++) {
        $ch = $sql[$i];
        $next = ($i+1<$len) ? $sql[$i+1] : '';

        // End line comment
        if ($inLine) {
            if ($ch === "\n") { $inLine = false; }
            continue;
        }
        // End block comment
        if ($inBlock) {
            if ($ch === '*' && $next === '/') { $inBlock = false; $i++; }
            continue;
        }

        // Start comments (only if not in quotes)
        if (!$inS && !$inD && !$inB) {
            // -- comment (MySQL: must be followed by space or EOL)
            if ($ch === '-' && $next === '-') {
                $next2 = ($i+2<$len) ? $sql[$i+2] : '';
                if ($next2 === ' ' || $next2 === "\t" || $next2 === "\r" || $next2 === "\n" || $next2 === '') {
                    $inLine = true; $i++; continue;
                }
            }
            // # comment
            if ($ch === '#') { $inLine = true; continue; }
            // /* block */
            if ($ch === '/' && $next === '*') { $inBlock = true; $i++; continue; }
        }

        // Quote toggles (respect escaping)
        if (!$inD && !$inB && $ch === "'" ) {
            $escaped = ($i>0 && $sql[$i-1] === '\\');
            if (!$escaped) { $inS = !$inS; }
        } elseif (!$inS && !$inB && $ch === '"' ) {
            $escaped = ($i>0 && $sql[$i-1] === '\\');
            if (!$escaped) { $inD = !$inD; }
        } elseif (!$inS && !$inD && $ch === '`') {
            $inB = !$inB;
        }

        // Split on semicolon when not in quotes
        if (!$inS && !$inD && !$inB && $ch === ';') {
            $stmt = trim($buf);
            if ($stmt !== '') { $out[] = $stmt; }
            $buf = '';
            continue;
        }

        $buf .= $ch;
    }

    $stmt = trim($buf);
    if ($stmt !== '') { $out[] = $stmt; }
    return $out;
}

function normalize_sql(string $stmt): string {
    $s = trim($stmt);

    // Skip DELIMITER / stored procedures in installer context
    if (preg_match('/^\s*DELIMITER\b/i', $s)) return '';

    // MariaDB compatibility: remove IF NOT EXISTS from ADD COLUMN / CREATE INDEX
    $s = preg_replace('/\bADD\s+COLUMN\s+IF\s+NOT\s+EXISTS\b/i', 'ADD COLUMN', $s);
    $s = preg_replace('/\bCREATE\s+INDEX\s+IF\s+NOT\s+EXISTS\b/i', 'CREATE INDEX', $s);
    $s = preg_replace('/\bCREATE\s+UNIQUE\s+INDEX\s+IF\s+NOT\s+EXISTS\b/i', 'CREATE UNIQUE INDEX', $s);

    // Fix common typo: ")InnoDB" => ") ENGINE=InnoDB"
    $s = preg_replace('/\)\s*InnoDB\b/i', ') ENGINE=InnoDB', $s);

    // Drop FK-only ALTER statements (shared hosting)
    if (preg_match('/^\s*ALTER\s+TABLE\b/i', $s) && preg_match('/\bFOREIGN\s+KEY\b/i', $s)) {
        return '';
    }

    // Strip FK clauses inside CREATE TABLE
    if (preg_match('/^\s*CREATE\s+TABLE\b/i', $s) && preg_match('/\bFOREIGN\s+KEY\b/i', $s)) {
        $s = strip_fk_from_create_table($s);
    }

    return trim($s);
}

/**
 * Remove FOREIGN KEY clauses from a CREATE TABLE statement.
 * Works by splitting the column/constraint list by commas at top-level.
 */
function strip_fk_from_create_table(string $stmt): string {
    // Find first "(" after CREATE TABLE ...
    $pos = strpos($stmt, '(');
    if ($pos === false) return $stmt;

    // Find matching ")"
    $len = strlen($stmt);
    $depth = 0;
    $end = null;
    $inS=false;$inD=false;$inB=false;
    for ($i=$pos; $i<$len; $i++) {
        $ch = $stmt[$i];
        $next = ($i+1<$len) ? $stmt[$i+1] : '';
        if (!$inD && !$inB && $ch === "'" ) {
            $escaped = ($i>0 && $stmt[$i-1] === '\\');
            if (!$escaped) $inS = !$inS;
        } elseif (!$inS && !$inB && $ch === '"' ) {
            $escaped = ($i>0 && $stmt[$i-1] === '\\');
            if (!$escaped) $inD = !$inD;
        } elseif (!$inS && !$inD && $ch === '`') {
            $inB = !$inB;
        }

        if ($inS || $inD || $inB) continue;

        if ($ch === '(') $depth++;
        if ($ch === ')') {
            $depth--;
            if ($depth === 0) { $end = $i; break; }
        }
    }
    if ($end === null) return $stmt;

    $head = substr($stmt, 0, $pos+1);
    $body = substr($stmt, $pos+1, $end-$pos-1);
    $tail = substr($stmt, $end);

    // Split body by commas at top-level
    $parts = [];
    $buf = '';
    $depth = 0;
    $inS=false;$inD=false;$inB=false;
    $bLen = strlen($body);
    for ($i=0; $i<$bLen; $i++) {
        $ch = $body[$i];
        if (!$inD && !$inB && $ch === "'" ) {
            $escaped = ($i>0 && $body[$i-1] === '\\');
            if (!$escaped) $inS = !$inS;
        } elseif (!$inS && !$inB && $ch === '"' ) {
            $escaped = ($i>0 && $body[$i-1] === '\\');
            if (!$escaped) $inD = !$inD;
        } elseif (!$inS && !$inD && $ch === '`') {
            $inB = !$inB;
        }

        if (!$inS && !$inD && !$inB) {
            if ($ch === '(') $depth++;
            if ($ch === ')') $depth--;
            if ($ch === ',' && $depth === 0) {
                $p = trim($buf);
                if ($p !== '') $parts[] = $p;
                $buf = '';
                continue;
            }
        }
        $buf .= $ch;
    }
    $p = trim($buf);
    if ($p !== '') $parts[] = $p;

    $kept = [];
    foreach ($parts as $p) {
        if (preg_match('/\bFOREIGN\s+KEY\b/i', $p)) continue;
        if (preg_match('/^\s*CONSTRAINT\b/i', $p) && preg_match('/\bFOREIGN\s+KEY\b/i', $p)) continue;
        $kept[] = $p;
    }

    return rtrim($head) . "\n  " . implode(",\n  ", $kept) . "\n" . ltrim($tail);
}

/**
 * Execute a SQL file with:
 * - statement splitting
 * - FK stripping
 * - MariaDB compatibility normalization
 * - ignoring safe duplicate errors
 */
function run_sql_file(PDO $pdo, string $file): void {
    $sql = (string)file_get_contents($file);
    $stmts = split_sql_statements($sql);

    foreach ($stmts as $raw) {
        $stmt = normalize_sql($raw);
        if ($stmt === '') continue;

        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            $info = $e->errorInfo ?? null;
            $driverCode = is_array($info) ? (int)($info[1] ?? 0) : 0;
            $msg = $e->getMessage();

            // Ignore "safe" duplicates / re-run cases
            $safe = [1050, 1060, 1061, 1091]; // table exists, dup column, dup key, drop missing
            if (in_array($driverCode, $safe, true)) {
                continue;
            }

            // If it's a FK formation error, ignore (we do not require FK on shared hosting)
            if ($driverCode === 1005 && stripos($msg, 'foreign key') !== false) {
                continue;
            }

            // Provide context
            $short = mb_substr($stmt, 0, 800);
            throw new RuntimeException(
                "SQL error in " . basename($file) . " (code {$driverCode}): " . $msg . "\nStatement: " . $short,
                0,
                $e
            );
        }
    }
}

function run_sql_dir(PDO $pdo, string $dir, array $skipFiles = []): array {
    if (!is_dir($dir)) return [];
    $files = glob(rtrim($dir, '/\\') . '/*.sql');
    sort($files, SORT_STRING);
    $ran = [];
    foreach ($files as $f) {
        $base = basename($f);
        if (in_array($base, $skipFiles, true)) continue;
        // Skip content seeds for RAW clean package
        if (preg_match('/\bseed\b/i', $base)) continue;
        run_sql_file($pdo, $f);
        $ran[] = $base;
    }
    return $ran;
}

function can_write_env(string $root): bool {
    // Prefer writing .env in root
    if (file_exists($root . '/.env')) return is_writable($root . '/.env');
    return is_writable($root);
}

$lockFile = $ROOT . '/install/install.lock';
if (file_exists($lockFile)) {
    render('المثبت مقفول', '<div class="ok">تم تثبيت النظام مسبقًا. إذا أردت إعادة التثبيت: احذف install/install.lock وملف .env (إن وجد) ثم استخدم قاعدة بيانات جديدة.</div>');
}

$step = (int)($_GET['step'] ?? 1);
if ($step < 1 || $step > 3) $step = 1;

if ($step === 1) {
    $body = '<p>هذا المثبّت يقوم بإنشاء الجداول الأساسية وإنشاء حساب مدير.</p>';
    $body .= '<div class="row"><a class="btn" href="?step=2">ابدأ التثبيت</a></div>';
    render('بدء التثبيت', $body);
}

if ($step === 2) {
    $token = csrf_token();
    $body = '<form method="post" action="?step=3">';
    $body .= '<input type="hidden" name="csrf" value="'.h($token).'">';
    $body .= '<div class="grid">';
    $body .= '<div><label>رابط الموقع (APP_URL)</label><input name="APP_URL" value="'.h($_SERVER['REQUEST_SCHEME'].'://'.($_SERVER['HTTP_HOST'] ?? 'localhost')).'" required></div>';
    $body .= '<div><label>المنطقة الزمنية (TIMEZONE)</label><input name="TIMEZONE" value="Asia/Riyadh" required></div>';
    $body .= '<div><label>DB_HOST</label><input name="DB_HOST" value="localhost" required></div>';
    $body .= '<div><label>DB_NAME</label><input name="DB_NAME" value="" required></div>';
    $body .= '<div><label>DB_USER</label><input name="DB_USER" value="" required></div>';
    $body .= '<div><label>DB_PASS</label><input name="DB_PASS" type="password" value=""></div>';
    $body .= '<div><label>DB_PORT</label><input name="DB_PORT" value="3306" required></div>';
    $body .= '<div><label>Admin Email</label><input name="ADMIN_EMAIL" type="email" required></div>';
    $body .= '<div><label>Admin Username</label><input name="ADMIN_USERNAME" required></div>';
    $body .= '<div><label>Admin Password</label><input name="ADMIN_PASSWORD" type="password" required></div>';
    $body .= '</div>';
    $body .= '<div style="margin-top:14px" class="row"><button class="btn" type="submit">إنشاء الجداول وإكمال التثبيت</button><a class="btn2" href="?step=1">رجوع</a></div>';
    $body .= '<p class="muted" style="margin-top:10px">يفضل استخدام قاعدة بيانات جديدة وفارغة.</p>';
    $body .= '</form>';
    render('إعداد قاعدة البيانات', $body);
}

if ($step === 3) {
    try {
        require_post_token();

        $cfg = [
            'APP_URL' => trim((string)($_POST['APP_URL'] ?? '')),
            'TIMEZONE' => trim((string)($_POST['TIMEZONE'] ?? 'Asia/Riyadh')),
            'DB_HOST' => trim((string)($_POST['DB_HOST'] ?? 'localhost')),
            'DB_NAME' => trim((string)($_POST['DB_NAME'] ?? '')),
            'DB_USER' => trim((string)($_POST['DB_USER'] ?? '')),
            'DB_PASS' => (string)($_POST['DB_PASS'] ?? ''),
            'DB_PORT' => trim((string)($_POST['DB_PORT'] ?? '3306')),
            'ADMIN_EMAIL' => trim((string)($_POST['ADMIN_EMAIL'] ?? '')),
            'ADMIN_USERNAME' => trim((string)($_POST['ADMIN_USERNAME'] ?? '')),
            'ADMIN_PASSWORD' => (string)($_POST['ADMIN_PASSWORD'] ?? ''),
        ];

        foreach (['APP_URL','DB_NAME','DB_USER','DB_HOST','DB_PORT','ADMIN_EMAIL','ADMIN_USERNAME','ADMIN_PASSWORD'] as $k) {
            if ($cfg[$k] === '') throw new RuntimeException("الحقل {$k} مطلوب");
        }

        if (!can_write_env($ROOT)) {
            throw new RuntimeException('لا يمكن كتابة ملف .env في جذر المشروع. عدّل صلاحيات المجلد أو أنشئ ملف .env فارغ وأعطه صلاحية كتابة.');
        }

        $dsn = "mysql:host={$cfg['DB_HOST']};dbname={$cfg['DB_NAME']};port={$cfg['DB_PORT']};charset=utf8mb4";
        $pdo = new PDO($dsn, $cfg['DB_USER'], $cfg['DB_PASS'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Best-effort session settings for MariaDB
        $pdo->exec("SET NAMES utf8mb4");
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");

        $ran = [];

        // 1) Core schema
        $core = $ROOT . '/install/sql/schema_core.sql';
        if (!is_file($core)) throw new RuntimeException('schema_core.sql غير موجود');
        run_sql_file($pdo, $core);
        $ran[] = 'schema_core.sql';

        // 2) Migrations (no demo seeds)
        $skip = [
            '2025_11_21_0008_seed_default_pages.sql', // removed in RAW package
        ];
        $ran = array_merge($ran, run_sql_dir($pdo, $ROOT . '/database/migrations', $skip));
        $ran = array_merge($ran, run_sql_dir($pdo, $ROOT . '/admin/db/migrations', []));
        $ran = array_merge($ran, run_sql_dir($pdo, $ROOT . '/migrations', []));
        // 2b) Runtime compatibility patch (missing columns/tables expected by UI)
        $patch = $ROOT . '/install/sql/patch_existing_runtime.sql';
        if (is_file($patch)) {
            run_sql_file($pdo, $patch);
            $ran[] = 'patch_existing_runtime.sql';
        }


        // 3) Create admin user & assign admin role
        $passHash = password_hash($cfg['ADMIN_PASSWORD'], PASSWORD_BCRYPT);
        if (!$passHash) throw new RuntimeException('فشل توليد كلمة المرور');

        // Ensure admin role exists (schema_core seeds it, but keep idempotent)
        $pdo->exec("INSERT INTO roles (name,label,description,is_system) VALUES ('admin','مدير','صلاحيات كاملة',1)
                    ON DUPLICATE KEY UPDATE label=VALUES(label), description=VALUES(description), is_system=VALUES(is_system)");

        // Get admin role id
        $rid = (int)$pdo->query("SELECT id FROM roles WHERE name='admin' LIMIT 1")->fetchColumn();
        if ($rid <= 0) throw new RuntimeException('تعذر إيجاد role admin');

        // Upsert user by email/username
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email=:e OR username=:u LIMIT 1");
        $stmt->execute([':e'=>$cfg['ADMIN_EMAIL'], ':u'=>$cfg['ADMIN_USERNAME']]);
        $exists = $stmt->fetch();
        if ($exists) {
            $uid = (int)$exists['id'];
            $upd = $pdo->prepare("UPDATE users SET username=:u,email=:e,password_hash=:p,password=:p,updated_at=NOW() WHERE id=:id");
            $upd->execute([':u'=>$cfg['ADMIN_USERNAME'], ':e'=>$cfg['ADMIN_EMAIL'], ':p'=>$passHash, ':id'=>$uid]);
        } else {
            $ins = $pdo->prepare("INSERT INTO users (username,email,password_hash,password,role,is_admin,status,created_at,updated_at) VALUES (:u,:e,:p,:p,'admin',1,'active',NOW(),NOW())");
            $ins->execute([':u'=>$cfg['ADMIN_USERNAME'], ':e'=>$cfg['ADMIN_EMAIL'], ':p'=>$passHash]);
            $uid = (int)$pdo->lastInsertId();
        }

        // Assign role (idempotent)
        $ur = $pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:u,:r)");
        $ur->execute([':u'=>$uid, ':r'=>$rid]);

        // 4) Write .env
        $key = bin2hex(random_bytes(32));
        $env = [];
        $env[] = env_line('APP_ENV','production');
        $env[] = env_line('APP_URL',$cfg['APP_URL']);
        $env[] = env_line('TIMEZONE',$cfg['TIMEZONE']);
        $env[] = env_line('DB_DRIVER','mysql');
        $env[] = env_line('DB_HOST',$cfg['DB_HOST']);
        $env[] = env_line('DB_PORT',$cfg['DB_PORT']);
        $env[] = env_line('DB_NAME',$cfg['DB_NAME']);
        $env[] = env_line('DB_USER',$cfg['DB_USER']);
        $env[] = env_line('DB_PASS',$cfg['DB_PASS']);
        $env[] = env_line('DB_CHARSET','utf8mb4');
        $env[] = env_line('ENCRYPTION_KEY',$key);
        $env[] = '';
        file_put_contents($ROOT . '/.env', implode("\n", $env), LOCK_EX);

        // 5) Lock installer and re-enable FK checks
        @file_put_contents($lockFile, "installed_at=".date('c')."\n", LOCK_EX);
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

        $body = '<div class="ok">✅ تم إنشاء الجداول وإنشاء حساب المدير بنجاح.</div>';
        $body .= '<p>يمكنك الآن الدخول إلى لوحة الإدارة:</p>';
        $body .= '<div class="row"><a class="btn" href="'.h($cfg['APP_URL']).'/admin/login.php">تسجيل الدخول</a></div>';
        $body .= '<p class="muted" style="margin-top:10px">إذا لم يظهر زر الدخول بسبب إعدادات APP_URL، افتح: /admin/login.php</p>';
        $body .= '<details style="margin-top:12px"><summary class="muted">عرض الملفات التي تم تنفيذها</summary><div class="muted" style="margin-top:8px">'.h(implode(", ", $ran)).'</div></details>';
        render('تم التثبيت', $body);

    } catch (Throwable $e) {
        $body = '<div class="err"><strong>فشل إنشاء الجداول</strong><br>'.h($e->getMessage()).'</div>';
        $body .= '<p><a class="btn2" href="?step=2">رجوع</a></p>';
        render('خطأ', $body);
    }
}

render('خطأ', '<div class="err">خطوة غير صالحة</div>');
