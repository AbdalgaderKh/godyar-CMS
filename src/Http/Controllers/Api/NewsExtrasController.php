<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use PDO;
use Throwable;
use Godyar\Services\NewsService;
use Godyar\Services\TagService;
use Godyar\Services\CategoryService;

/**
 * API endpoints for: reactions, polls, Q&A, translation, TTS, search suggestions, PWA, push subscriptions.
 *
 * Notes:
 * - Router in app.php matches by path only; we validate method per endpoint.
 * - All responses are JSON unless otherwise specified (TTS audio).
 */
final class NewsExtrasController
{
    private PDO $pdo;
    private NewsService $news;
    private TagService $tags;
    private CategoryService $categories;

    public function __construct(PDO $pdo, NewsService $news, TagService $tags, CategoryService $categories)
    {
        $this->pdo = $pdo;
        $this->news = $news;
        $this->tags = $tags;
        $this->categories = $categories;
    }

    /* -----------------------------
       Public endpoints (router)
       ----------------------------- */

    public function capabilities(): void
    {
        $caps = [
            'tts_download' => $this->canTtsDownload(),
            'push' => true,
            'translation' => true,
        ];
        $this->json($caps);
    }

    public function reactions(): void
    {
        $this->ensureReactionsTable();

        $newsId = (int)($_GET['news_id'] ?? 0);
        if ($newsId <= 0) {
            $this->json(['ok' => false, 'error' => 'news_id is required'], 400);
        }

        $voterKey = $this->voterKey();
        $counts = $this->reactionCounts($newsId);
        $mine = $this->myReactions($newsId, $voterKey);

        $this->json(['ok' => true, 'news_id' => $newsId, 'counts' => $counts, 'mine' => $mine]);
    }

public function react(): void
{
    $this->ensureReactionsTable();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        $this->json(['ok' => false, 'error' => 'POST required'], 405);
    }

    $newsId = (int)($_POST['news_id'] ?? 0);
    $reaction = trim((string)($_POST['reaction'] ?? ''));
    if ($newsId <= 0 || $reaction === '') {
        $this->json(['ok' => false, 'error' => 'news_id and reaction are required'], 400);
    }

    $allowed = ['like','useful','disagree','angry','funny'];
    if (!in_array($reaction, $allowed, true)) {
        $this->json(['ok' => false, 'error' => 'invalid reaction'], 400);
    }

    $schema = $this->reactionsSchema();
    $col = $schema['col'];

    $voterKey = $this->voterKey();
    $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));

    // Build voter predicate based on available columns
    $where = ["news_id = :n", "`{$col}` = :r"];
    $bind = [':n' => $newsId, ':r' => $reaction];

    if (!empty($schema['has_voter_key'])) {
        $where[] = "voter_key = :k";
        $bind[':k'] = $voterKey;
    } elseif (!empty($schema['has_ip_address'])) {
        $where[] = "ip_address = :ip";
        $bind[':ip'] = ($ip !== '' ? $ip : '0.0.0.0');
    }

    $active = true;

    try {
        if (!empty($schema['has_voter_key']) || !empty($schema['has_ip_address'])) {
            $st = $this->pdo->prepare("SELECT id FROM news_reactions WHERE " . implode(' AND ', $where) . " LIMIT 1");
            $st->execute($bind);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            if ($row && !empty($row['id'])) {
                $del = $this->pdo->prepare("DELETE FROM news_reactions WHERE id = :id");
                $del->execute([':id' => (int)$row['id']]);
                $active = false;
            } else {
                $cols = ['news_id', "`{$col}`"];
                $vals = [':n', ':r'];
                $insBind = [':n' => $newsId, ':r' => $reaction];

                if (!empty($schema['has_voter_key'])) {
                    $cols[] = 'voter_key';
                    $vals[] = ':k';
                    $insBind[':k'] = $voterKey;
                }
                if (!empty($schema['has_ip_address'])) {
                    $cols[] = 'ip_address';
                    $vals[] = ':ip';
                    $insBind[':ip'] = ($ip !== '' ? $ip : '0.0.0.0');
                }
                if (!empty($schema['has_user_agent'])) {
                    $cols[] = 'user_agent';
                    $vals[] = ':ua';
                    $insBind[':ua'] = ($ua !== '' ? $ua : null);
                }
                if (!empty($schema['has_created_at'])) {
                    $cols[] = 'created_at';
                    $vals[] = 'NOW()';
                }

                $sql = "INSERT INTO news_reactions (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
                $ins = $this->pdo->prepare($sql);
                $ins->execute($insBind);
                $active = true;
            }
        } else {
            // No stable voter column available -> best-effort insert only (no toggle)
            $cols = ['news_id', "`{$col}`"];
            $vals = [':n', ':r'];
            $insBind = [':n' => $newsId, ':r' => $reaction];

            if (!empty($schema['has_created_at'])) {
                $cols[] = 'created_at';
                $vals[] = 'NOW()';
            }

            $sql = "INSERT INTO news_reactions (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
            $ins = $this->pdo->prepare($sql);
            $ins->execute($insBind);
            $active = true;
        }
    } catch (Throwable $e) {
        @error_log('[NewsExtras] react error: ' . $e->getMessage());
        $this->json(['ok' => false, 'error' => 'reaction store failed'], 500);
    }

    $counts = $this->reactionCounts($newsId);
    $mine = $this->myReactions($newsId, $voterKey);

    $this->json(['ok'=>true,'active'=>$active,'counts'=>$counts,'mine'=>$mine]);
}

    
    /* -----------------------------
       Bookmarks (Saved)
       ----------------------------- */

    public function bookmarksList(): void
    {
        $this->ensureBookmarksTable();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $userId = (int)($_SESSION['user']['id'] ?? ($_SESSION['user_id'] ?? 0));
        if ($userId <= 0) {
            $this->json(['ok' => false, 'error' => 'AUTH_REQUIRED'], 401);
        }

        $limit = (int)($_GET['limit'] ?? 100);
        if ($limit <= 0) $limit = 100;
        if ($limit > 200) $limit = 200;

        $sql = "
            SELECT n.id, n.title, n.slug, n.published_at, n.image, b.created_at
            FROM user_bookmarks b
            INNER JOIN news n ON n.id = b.news_id
            WHERE b.user_id = :uid
            ORDER BY b.created_at DESC
            LIMIT {$limit}
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$r) {
            $id = (int)($r['id'] ?? 0);
            $r['url'] = '/news/id/' . $id;
        }
        unset($r);

        $this->json(['ok' => true, 'items' => $rows]);
    }

    public function bookmarkStatus(): void
    {
        $this->ensureBookmarksTable();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $userId = (int)($_SESSION['user']['id'] ?? ($_SESSION['user_id'] ?? 0));
        if ($userId <= 0) {
            $this->json(['ok' => false, 'error' => 'AUTH_REQUIRED'], 401);
        }

        $newsId = (int)($_GET['news_id'] ?? 0);
        if ($newsId <= 0) {
            $this->json(['ok' => false, 'error' => 'news_id is required'], 400);
        }

        $stmt = $this->pdo->prepare("SELECT 1 FROM user_bookmarks WHERE user_id = :uid AND news_id = :nid LIMIT 1");
        $stmt->execute([':uid' => $userId, ':nid' => $newsId]);
        $saved = (bool)$stmt->fetchColumn();

        $this->json(['ok' => true, 'news_id' => $newsId, 'saved' => $saved]);
    }

    public function bookmarksToggle(): void
    {
        $this->ensureBookmarksTable();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->json(['ok' => false, 'error' => 'POST required'], 405);
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $userId = (int)($_SESSION['user']['id'] ?? ($_SESSION['user_id'] ?? 0));
        if ($userId <= 0) {
            $this->json(['ok' => false, 'error' => 'AUTH_REQUIRED'], 401);
        }

        $newsId = (int)($_POST['news_id'] ?? 0);
        $action = (string)($_POST['action'] ?? 'toggle');
        if ($newsId <= 0) {
            $this->json(['ok' => false, 'error' => 'news_id is required'], 400);
        }

        if ($action === 'add') {
            $st = $this->pdo->prepare("INSERT IGNORE INTO user_bookmarks (user_id, news_id) VALUES (:uid,:nid)");
            $st->execute([':uid' => $userId, ':nid' => $newsId]);
            $this->json(['ok' => true, 'status' => 'added', 'news_id' => $newsId]);
        }

        if ($action === 'remove') {
            $st = $this->pdo->prepare("DELETE FROM user_bookmarks WHERE user_id = :uid AND news_id = :nid");
            $st->execute([':uid' => $userId, ':nid' => $newsId]);
            $this->json(['ok' => true, 'status' => 'removed', 'news_id' => $newsId]);
        }

        // toggle
        $st = $this->pdo->prepare("SELECT 1 FROM user_bookmarks WHERE user_id = :uid AND news_id = :nid LIMIT 1");
        $st->execute([':uid' => $userId, ':nid' => $newsId]);
        $exists = (bool)$st->fetchColumn();

        if ($exists) {
            $del = $this->pdo->prepare("DELETE FROM user_bookmarks WHERE user_id = :uid AND news_id = :nid");
            $del->execute([':uid' => $userId, ':nid' => $newsId]);
            $this->json(['ok' => true, 'status' => 'removed', 'news_id' => $newsId]);
        } else {
            $ins = $this->pdo->prepare("INSERT INTO user_bookmarks (user_id, news_id) VALUES (:uid,:nid)");
            $ins->execute([':uid' => $userId, ':nid' => $newsId]);
            $this->json(['ok' => true, 'status' => 'added', 'news_id' => $newsId]);
        }
    }

    public function bookmarksImport(): void
    {
        $this->ensureBookmarksTable();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->json(['ok' => false, 'error' => 'POST required'], 405);
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $userId = (int)($_SESSION['user']['id'] ?? ($_SESSION['user_id'] ?? 0));
        if ($userId <= 0) {
            $this->json(['ok' => false, 'error' => 'AUTH_REQUIRED'], 401);
        }

        $newsIds = [];

        // Accept JSON body: {"news_ids":[1,2,3]}
        $raw = (string)file_get_contents('php://input');
        $t = ltrim($raw);
        if ($t !== '' && isset($t[0]) && $t[0] === '{') {
            $j = json_decode($raw, true);
            if (is_array($j) && isset($j['news_ids']) && is_array($j['news_ids'])) {
                $newsIds = $j['news_ids'];
            }
        }
        if (empty($newsIds) && isset($_POST['news_ids'])) {
            $newsIds = is_array($_POST['news_ids']) ? $_POST['news_ids'] : explode(',', (string)$_POST['news_ids']);
        }

        $ids = [];
        foreach ($newsIds as $v) {
            $id = (int)$v;
            if ($id > 0) $ids[] = $id;
        }
        $ids = array_values(array_unique($ids));
        if (count($ids) > 300) {
            $ids = array_slice($ids, 0, 300);
        }

        if (empty($ids)) {
            $this->json(['ok' => true, 'imported' => 0]);
        }

        $placeholders = [];
        $params = [];
        foreach ($ids as $i => $nid) {
            $placeholders[] = "(:uid{$i}, :nid{$i})";
            $params[":uid{$i}"] = $userId;
            $params[":nid{$i}"] = $nid;
        }

        $sql = "INSERT IGNORE INTO user_bookmarks (user_id, news_id) VALUES " . implode(',', $placeholders);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $this->json(['ok' => true, 'imported' => count($ids)]);
    }

public function poll(): void
    {
        $this->ensurePollTables();

        $newsId = (int)($_GET['news_id'] ?? 0);
        if ($newsId <= 0) {
            $this->json(['ok' => false, 'error' => 'news_id is required'], 400);
        }

        $poll = $this->pollForNews($newsId);
        if (!$poll) {
            $this->json(['ok'=>true,'has_poll'=>false]);
        }

        // لا نعرض الاستطلاع إلا إذا كان فعّالاً
        if ((int)($poll['is_active'] ?? 0) !== 1) {
            $this->json(['ok'=>true,'has_poll'=>false]);
        }

        $voterKey = $this->voterKey();

        $options = $this->pollOptionsWithCounts((int)$poll['id']);
        $myVote = $this->myPollVote((int)$poll['id'], $voterKey);

        $this->json([
            'ok'=>true,
            'has_poll'=>true,
            'poll'=>[
                'id'=>(int)$poll['id'],
                'question'=>(string)$poll['question'],
                'is_active'=>(int)$poll['is_active'],
                'options'=>$options,
                'my_vote'=>$myVote,
            ]
        ]);
    }

    public function pollVote(): void
    {
        $this->ensurePollTables();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->json(['ok' => false, 'error' => 'POST required'], 405);
        }

        $newsId = (int)($_POST['news_id'] ?? 0);
        $optionId = (int)($_POST['option_id'] ?? 0);
        if ($newsId <= 0 || $optionId <= 0) {
            $this->json(['ok' => false, 'error' => 'news_id and option_id are required'], 400);
        }

        // simple rate limit (session-based)
        if (!$this->rateLimit('pollvote:' . $newsId, 15, 60)) {
            $this->json(['ok'=>false,'error'=>'too many requests'], 429);
        }

        $poll = $this->pollForNews($newsId);
        if (!$poll || (int)$poll['is_active'] !== 1) {
            $this->json(['ok'=>false,'error'=>'poll not found or inactive'], 404);
        }

        $pollId = (int)$poll['id'];
        $voterKey = $this->voterKey();

        // Validate option belongs to poll
        $stOpt = $this->pdo->prepare("SELECT id FROM news_poll_options WHERE id = :oid AND poll_id = :pid LIMIT 1");
        $stOpt->execute([':oid'=>$optionId, ':pid'=>$pollId]);
        $okOpt = $stOpt->fetch(PDO::FETCH_ASSOC);
        if (!$okOpt) {
            $this->json(['ok'=>false,'error'=>'invalid option'], 400);
        }

        // one vote per poll per voter
        $st = $this->pdo->prepare("SELECT id FROM news_poll_votes WHERE poll_id = :pid AND voter_key = :k LIMIT 1");
        $st->execute([':pid'=>$pollId, ':k'=>$voterKey]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['id'])) {
            // update vote
            $up = $this->pdo->prepare("UPDATE news_poll_votes SET option_id = :oid, updated_at = NOW() WHERE id = :id");
            $up->execute([':oid'=>$optionId, ':id'=>(int)$row['id']]);
        } else {
            $ins = $this->pdo->prepare("INSERT INTO news_poll_votes (poll_id, option_id, voter_key, created_at, updated_at) VALUES (:pid,:oid,:k,NOW(),NOW())");
            $ins->execute([':pid'=>$pollId, ':oid'=>$optionId, ':k'=>$voterKey]);
        }

        $options = $this->pollOptionsWithCounts($pollId);
        $myVote = $this->myPollVote($pollId, $voterKey);

        $this->json(['ok'=>true,'options'=>$options,'my_vote'=>$myVote]);
    }

    public function questions(): void
    {
        if (!$this->ensureQuestionsTableReady()) { return; }

        $newsId = (int)($_GET['news_id'] ?? 0);
        if ($newsId <= 0) {
            $this->json(['ok'=>false,'error'=>'news_id required'], 400);
        }

        try {
            $st = $this->pdo->prepare("SELECT id, question, answer, created_at, answered_at
                FROM news_questions
                WHERE news_id = :n AND status = 'approved'
                ORDER BY id DESC
                LIMIT 100");
            $st->execute([':n'=>$newsId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $this->json(['ok'=>true,'items'=>$rows]);
        } catch (Throwable $e) {
            @error_log('[NewsExtras] questions error: ' . $e->getMessage());
            $this->json(['ok'=>false,'error'=>'db_error','message'=>'تعذر تحميل الأسئلة.'], 500);
        }
    }

    public function ask(): void
    {
        if (!$this->ensureQuestionsTableReady()) { return; }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->json(['ok' => false, 'error' => 'POST required'], 405);
        }

        $newsId = (int)($_POST['news_id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $question = trim((string)($_POST['question'] ?? ''));

        if ($newsId <= 0 || $question === '') {
            $this->json(['ok'=>false,'error'=>'news_id and question required'], 400);
        }

        if (!$this->rateLimit('ask:' . $newsId, 5, 300)) {
            $this->json(['ok'=>false,'error'=>'too many requests'], 429);
        }
        if ($name === '') $name = 'زائر';

        $userId = (int)($_SESSION['user']['id'] ?? ($_SESSION['user_id'] ?? 0));

        try {
            $ins = $this->pdo->prepare("INSERT INTO news_questions (news_id, user_id, name, email, question, status, created_at)
                VALUES (:n, :u, :name, :email, :q, 'pending', NOW())");
            $ins->execute([
                ':n'=>$newsId,
                ':u'=>($userId>0?$userId:null),
                ':name'=>$name,
                ':email'=>($email!==''?$email:null),
                ':q'=>$question
            ]);

            $newId = (int)($this->pdo->lastInsertId() ?: 0);
            $this->json(['ok'=>true,'id'=>$newId,'message'=>'تم إرسال سؤالك وسيتم عرضه بعد المراجعة.']);
        } catch (Throwable $e) {
            @error_log('[NewsExtras] ask insert error: ' . $e->getMessage());
            $this->json(['ok'=>false,'error'=>'db_error','message'=>'تعذر حفظ السؤال.'], 500);
        }
    }

    public function tts(): void
    {
        // audio download endpoint (best-effort: uses espeak + ffmpeg/lame if available)
        $newsId = (int)($_GET['news_id'] ?? 0);
        if ($newsId <= 0) {
            $this->json(['ok'=>false,'error'=>'news_id required'], 400);
        }

        if (!$this->canTtsDownload()) {
            $this->json(['ok'=>false,'error'=>'TTS غير متاح. ثبّت محرك صوت محلي (espeak + ffmpeg/lame) أو اضبط GDY_TTS_REMOTE_URL'], 501);
            return;
        }

        $post = $this->news->findBySlugOrId((string)$newsId);
        if (!$post || empty($post['id'])) {
            $this->json(['ok'=>false,'error'=>'news not found'], 404);
        }

        $title = (string)($post['title'] ?? '');
        $html = (string)($post['content'] ?? ($post['body'] ?? ''));
        $text = trim($this->stripText($title . "\n\n" . $html));
        if ($text === '') {
            $this->json(['ok'=>false,'error'=>'empty content'], 400);
        }

        $lang = strtolower(trim((string)($_GET['lang'] ?? 'ar')));
        $rate = (float)($_GET['rate'] ?? 1.0);
        if ($rate < 0.7) $rate = 0.7;
        if ($rate > 1.3) $rate = 1.3;

        $tmpDir = sys_get_temp_dir();
        $hash = substr(sha1($newsId . '|' . $lang . '|' . $rate . '|' . md5($text)), 0, 16);
        $mp3 = $tmpDir . '/gdy_tts_' . $hash . '.mp3';
        if (!is_file($mp3) || filesize($mp3) < 1000) {
            $wav = $tmpDir . '/gdy_tts_' . $hash . '.wav';
            @unlink($wav);
            @unlink($mp3);

            // espeak: words per minute approx 175; adjust by rate
            $wpm = (int)round(175 * $rate);

            $cmdEspeak = $this->which('espeak') ?: $this->which('espeak-ng');
            $cmdFfmpeg = $this->which('ffmpeg');
            $cmdLame = $this->which('lame');

            $escapedText = escapeshellarg($text);
            $voice = ($lang === 'ar' || str_starts_with($lang,'ar')) ? 'ar' : $lang;

            $localOk = (bool)$cmdEspeak && (bool)($cmdFfmpeg || $cmdLame);
            if ($localOk) {
                // espeak writes wav file
                $cmd = $cmdEspeak . ' -v ' . escapeshellarg($voice) . ' -s ' . (int)$wpm . ' -w ' . escapeshellarg($wav) . ' ' . $escapedText;
                @shell_exec($cmd . ' 2>/dev/null');

                if (!is_file($wav) || filesize($wav) < 1000) {
                    $this->json(['ok'=>false,'error'=>'tts engine failed to generate audio'], 502);
                    return;
                }

                if ($cmdFfmpeg) {
                    $cmd2 = $cmdFfmpeg . ' -y -loglevel error -i ' . escapeshellarg($wav) . ' -codec:a libmp3lame -qscale:a 4 ' . escapeshellarg($mp3);
                    @shell_exec($cmd2 . ' 2>/dev/null');
                } else {
                    $cmd2 = $cmdLame . ' --silent ' . escapeshellarg($wav) . ' ' . escapeshellarg($mp3);
                    @shell_exec($cmd2 . ' 2>/dev/null');
                }
                @unlink($wav);
            } else {
                // Remote fallback (works on shared hostings)
                $res = $this->ttsRemoteGenerate($mp3, $text, $lang, $rate);
                if (empty($res['ok'])) {
                    $err = (string)($res['error'] ?? 'tts remote failed');
                    $code = (int)($res['code'] ?? 0);
                    $hint = (string)($res['hint'] ?? '');
                    $payload = ['ok' => false, 'error' => $err];
                    if ($code > 0) $payload['code'] = $code;
                    if ($hint !== '') $payload['hint'] = $hint;
                    $this->json($payload, 502);
                    return;
                }
            }
        }

        if (!is_file($mp3) || filesize($mp3) < 1000) {
            $this->json(['ok'=>false,'error'=>'tts encoding failed'], 502);
            return;
        }

        header('Content-Type: audio/mpeg');
        header('Content-Disposition: attachment; filename="news-'.$newsId.'.mp3"');
        header('Cache-Control: public, max-age=86400');
        readfile($mp3);
        exit;
    }

    public function suggest(): void
    {
        $q = trim((string)($_GET['q'] ?? ''));
        $q = preg_replace('~\s+~u',' ', $q ?? '');
        if ($q === '') {
            $this->json(['ok'=>true,'q'=>'','suggestions'=>[],'corrected'=>null]);
        }

        $qLike = '%' . $q . '%';

        // Titles
        $items = [];

        try {
            $st = $this->pdo->prepare("SELECT id, title, slug
                FROM news
                WHERE title LIKE :q
                ORDER BY id DESC
                LIMIT 8");
            $st->execute([':q'=>$qLike]);
            foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
                $slug = (string)($row['slug'] ?? '');
                $url = $slug !== '' ? ('/news/' . $slug) : ('/news/id/' . (int)$row['id']);
                $items[] = ['type'=>'news','title'=>(string)$row['title'],'url'=>$url];
            }
        } catch (Throwable $e) {
            // ignore
        }

        // Tags
        try {
            $st = $this->pdo->prepare("SELECT id, name, slug FROM tags WHERE name LIKE :q ORDER BY name ASC LIMIT 6");
            $st->execute([':q'=>$qLike]);
            foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
                $slug = (string)($row['slug'] ?? '');
                if ($slug === '') $slug = $this->slugify((string)$row['name']);
                $items[] = ['type'=>'tag','title'=>(string)$row['name'],'url'=>'/topic/' . $slug];
            }
        } catch (Throwable $e) {}

        // Categories
        try {
            $st = $this->pdo->prepare("SELECT id, name, slug FROM categories WHERE name LIKE :q ORDER BY name ASC LIMIT 6");
            $st->execute([':q'=>$qLike]);
            foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
                $slug = (string)($row['slug'] ?? '');
                if ($slug === '') $slug = $this->slugify((string)$row['name']);
                $items[] = ['type'=>'category','title'=>(string)$row['name'],'url'=>'/category/' . $slug];
            }
        } catch (Throwable $e) {}

        $corrected = $this->spellCorrect($q);

        $this->json(['ok'=>true,'q'=>$q,'suggestions'=>$items,'corrected'=>$corrected]);
    }

    public function latest(): void
    {
        // PWA helper endpoint
        $list = $this->news->latest(1, 20);
        $items = [];
        foreach (($list['items'] ?? []) as $it) {
            $id = (int)($it['id'] ?? 0);
            $slug = (string)($it['slug'] ?? '');
            $items[] = [
                'id'=>$id,
                'title'=>(string)($it['title'] ?? ''),
                'url'=>$slug !== '' ? ('/news/' . $slug) : ('/news/id/' . $id),
                'published_at'=>(string)($it['publish_at'] ?? ($it['published_at'] ?? ($it['created_at'] ?? ''))),
            ];
        }
        $this->json(['ok'=>true,'items'=>$items]);
    }

    public function pushSubscribe(): void
    {
        $this->ensurePushTable();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->json(['ok'=>false,'error'=>'POST required'], 405);
        }

        $raw = file_get_contents('php://input');
        $data = json_decode((string)$raw, true);
        if (!is_array($data)) $data = [];

        $endpoint = trim((string)($data['endpoint'] ?? ''));
        $keys = $data['keys'] ?? [];
        $p256dh = is_array($keys) ? trim((string)($keys['p256dh'] ?? '')) : '';
        $auth = is_array($keys) ? trim((string)($keys['auth'] ?? '')) : '';

        $prefs = $data['prefs'] ?? [];
        if (!is_array($prefs)) $prefs = [];

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            $this->json(['ok'=>false,'error'=>'invalid subscription'], 400);
        }

        $userId = (int)($_SESSION['user']['id'] ?? ($_SESSION['user_id'] ?? 0));
        $hash = sha1($endpoint);

        $st = $this->pdo->prepare("INSERT INTO push_subscriptions (endpoint_hash, user_id, endpoint, p256dh, auth, prefs_json, created_at, updated_at)
            VALUES (:h, :u, :e, :p, :a, :prefs, NOW(), NOW())
            ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), p256dh = VALUES(p256dh), auth = VALUES(auth), prefs_json = VALUES(prefs_json), updated_at = NOW()");
        $st->execute([
            ':h'=>$hash,
            ':u'=>($userId>0?$userId:null),
            ':e'=>$endpoint,
            ':p'=>$p256dh,
            ':a'=>$auth,
            ':prefs'=>json_encode($prefs, JSON_UNESCAPED_UNICODE),
        ]);

        $this->json(['ok'=>true]);
    }

    public function pushUnsubscribe(): void
    {
        $this->ensurePushTable();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->json(['ok'=>false,'error'=>'POST required'], 405);
        }

        $raw = file_get_contents('php://input');
        $data = json_decode((string)$raw, true);
        if (!is_array($data)) $data = [];
        $endpoint = trim((string)($data['endpoint'] ?? ''));
        if ($endpoint === '') {
            $this->json(['ok'=>false,'error'=>'endpoint required'], 400);
        }
        $hash = sha1($endpoint);
        $st = $this->pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint_hash = :h");
        $st->execute([':h'=>$hash]);
        $this->json(['ok'=>true]);
    }

    /* -----------------------------
       Helpers
       ----------------------------- */

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function voterKey(): string
    {
        $uid = (int)($_SESSION['user']['id'] ?? ($_SESSION['user_id'] ?? 0));
        if ($uid > 0) return 'u:' . $uid;

        // Visitor: stable key (cookie) + IP (soft) to reduce easy vote-abuse
        $cookieName = 'gdy_vk';
        $token = (string)($_COOKIE[$cookieName] ?? '');
        if ($token === '' || strlen($token) < 16) {
            try {
                $token = bin2hex(random_bytes(16));
            } catch (Throwable $e) {
                $token = substr(sha1(uniqid('', true)), 0, 32);
            }
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            @setcookie($cookieName, $token, [
                'expires' => time() + 31536000,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            $_COOKIE[$cookieName] = $token;
        }

        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if ($ip === '') $ip = '0.0.0.0';
        return 'g:' . substr(sha1($token . '|' . $ip), 0, 24);
    }

    private function rateLimit(string $key, int $maxHits, int $perSeconds): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) return true;
        if (!isset($_SESSION['gdy_rl']) || !is_array($_SESSION['gdy_rl'])) $_SESSION['gdy_rl'] = [];
        $now = time();
        $bucket = $_SESSION['gdy_rl'][$key] ?? [];
        if (!is_array($bucket)) $bucket = [];
        $bucket = array_values(array_filter($bucket, fn($t) => is_int($t) && ($now - $t) <= $perSeconds));
        if (count($bucket) >= $maxHits) {
            $_SESSION['gdy_rl'][$key] = $bucket;
            return false;
        }
        $bucket[] = $now;
        $_SESSION['gdy_rl'][$key] = $bucket;
        return true;
    }

private function reactionCounts(int $newsId): array
{
    $out = ['like'=>0,'useful'=>0,'disagree'=>0,'angry'=>0,'funny'=>0];
    try {
        $schema = $this->reactionsSchema();
        $col = $schema['col'];
        $st = $this->pdo->prepare("SELECT `$col` AS reaction, COUNT(*) c FROM news_reactions WHERE news_id = :n GROUP BY `$col`");
        $st->execute([':n'=>$newsId]);
        foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
            $k = (string)($r['reaction'] ?? '');
            if ($k === '') continue;
            $out[$k] = (int)($r['c'] ?? 0);
        }
    } catch (Throwable $e) {
        @error_log('[NewsExtras] reactionCounts error: ' . $e->getMessage());
    }
    return $out;
}

private function myReactions(int $newsId, string $voterKey): array
{
    $mine = [];
    try {
        $schema = $this->reactionsSchema();
        $col = $schema['col'];

        $where = ["news_id = :n"];
        $bind = [':n' => $newsId];

        if (!empty($schema['has_voter_key'])) {
            $where[] = "voter_key = :k";
            $bind[':k'] = $voterKey;
        } elseif (!empty($schema['has_ip_address'])) {
            $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
            if ($ip === '') $ip = '0.0.0.0';
            $where[] = "ip_address = :ip";
            $bind[':ip'] = $ip;
        } else {
            return [];
        }

        $st = $this->pdo->prepare("SELECT `$col` AS reaction FROM news_reactions WHERE " . implode(' AND ', $where));
        $st->execute($bind);

        foreach (($st->fetchAll(PDO::FETCH_COLUMN) ?: []) as $r) {
            $mine[] = (string)$r;
        }
    } catch (Throwable $e) {
        @error_log('[NewsExtras] myReactions error: ' . $e->getMessage());
    }
    return $mine;
}

    private function pollForNews(int $newsId): ?array
    {
        $st = $this->pdo->prepare("SELECT id, question, is_active FROM news_polls WHERE news_id = :n ORDER BY id DESC LIMIT 1");
        $st->execute([':n'=>$newsId]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        return $row ?: null;
    }

    private function pollOptionsWithCounts(int $pollId): array
    {
        $st = $this->pdo->prepare("SELECT o.id, o.label, o.sort_order,
            (SELECT COUNT(*) FROM news_poll_votes v WHERE v.option_id = o.id) AS votes
            FROM news_poll_options o
            WHERE o.poll_id = :p
            ORDER BY o.sort_order ASC, o.id ASC");
        $st->execute([':p'=>$pollId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $total = 0;
        foreach ($rows as $r) $total += (int)($r['votes'] ?? 0);
        foreach ($rows as &$r) {
            $v = (int)($r['votes'] ?? 0);
            $r['votes'] = $v;
            $r['pct'] = ($total > 0) ? round(($v / $total) * 100, 1) : 0;
            $r['id'] = (int)$r['id'];
            $r['sort_order'] = (int)($r['sort_order'] ?? 0);
        }
        return ['total'=>$total,'items'=>$rows];
    }

    private function myPollVote(int $pollId, string $voterKey): ?int
    {
        $st = $this->pdo->prepare("SELECT option_id FROM news_poll_votes WHERE poll_id = :p AND voter_key = :k LIMIT 1");
        $st->execute([':p'=>$pollId, ':k'=>$voterKey]);
        $v = $st->fetch(PDO::FETCH_ASSOC);
        return $v ? (int)$v['option_id'] : null;
    }

    private function stripText(string $html): string
    {
        $t = preg_replace('~<script\b[^>]*>.*?</script>~is', ' ', $html);
        $t = preg_replace('~<style\b[^>]*>.*?</style>~is', ' ', $t ?? '');
        $t = strip_tags((string)$t);
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace('~\s+~u', ' ', $t ?? '');
        return trim((string)$t);
    }

    private function canTtsDownload(): bool
    {
        // Local engines (VPS) or remote fallback (shared hostings)
        $espeak = $this->which('espeak') ?: $this->which('espeak-ng');
        $ffmpeg = $this->which('ffmpeg');
        $lame = $this->which('lame');
        if ($espeak && ($ffmpeg || $lame)) return true;
        $remote = $this->ttsRemoteUrl();
        if ($remote !== '' && function_exists('curl_init')) return true;
        return false;
    }

    private function ttsRemoteUrl(): string
    {
        $url = (string)(getenv('GDY_TTS_REMOTE_URL') ?: ($_ENV['GDY_TTS_REMOTE_URL'] ?? ''));
        $url = trim($url);
        if ($url === '') {
            // Default public endpoint (can be overridden by env)
            $url = 'https://api.streamelements.com/kappa/v2/speech';
        }
        // basic validation
        if (!preg_match('~^https?://~i', $url)) return '';
        return $url;
    }

    /**
     * Generate MP3 via remote TTS.
     * Returns: ['ok'=>true] or ['ok'=>false,'code'=>int,'error'=>string,'hint'=>string]
     */
    private function ttsRemoteGenerate(string $mp3Path, string $text, string $lang, float $rate): array
    {
        $endpoint = $this->ttsRemoteUrl();
        if ($endpoint === '') return ['ok'=>false,'error'=>'tts remote not configured'];
        if (!function_exists('curl_init')) return ['ok'=>false,'error'=>'curl not available'];

        // Some providers have strict limits. We chunk and append MP3 frames.
        $maxChars = 320;
        $chunks = [];
        $clean = preg_replace('~\s+~u', ' ', $text ?? '');
        $clean = trim((string)$clean);
        if ($clean === '') return ['ok'=>false,'error'=>'empty text'];
        while (mb_strlen($clean, 'UTF-8') > $maxChars) {
            $part = mb_substr($clean, 0, $maxChars, 'UTF-8');
            $cut = mb_strrpos($part, ' ', 0, 'UTF-8');
            if ($cut !== false && $cut > 100) {
                $part = mb_substr($part, 0, $cut, 'UTF-8');
            }
            $chunks[] = $part;
            $clean = trim((string)mb_substr($clean, mb_strlen($part, 'UTF-8'), null, 'UTF-8'));
        }
        if ($clean !== '') $chunks[] = $clean;

        $voice = (string)(getenv('GDY_TTS_VOICE') ?: ($_ENV['GDY_TTS_VOICE'] ?? ''));
        $voice = trim($voice);
        if ($voice === '') {
            // Best-effort default for Arabic
            $voice = (str_starts_with(strtolower($lang), 'ar')) ? 'Zeina' : 'Brian';
        }

        @unlink($mp3Path);
        $fh = @fopen($mp3Path, 'ab');
        if (!$fh) return ['ok'=>false,'error'=>'cannot write temp audio'];

        // Optional auth (StreamElements requires Authorization: Bearer <token> for some endpoints)
        $bearer = (string)($this->envGet('GDY_TTS_REMOTE_BEARER')
            ?? $this->envGet('GDY_TTS_API_KEY')
            ?? $this->envGet('GDY_TTS_BEARER')
            ?? '');
        $bearer = trim($bearer);
        $needsAuth = (bool)preg_match('~api\\.streamelements\\.com/.*?/speech~i', $endpoint);

        foreach ($chunks as $chunk) {
            $query = http_build_query([
                'voice' => $voice,
                'text'  => $chunk,
            ]);
            $url = $endpoint . (str_contains($endpoint, '?') ? '&' : '?') . $query;

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
            $headers = [
                'Accept: audio/mpeg,*/*',
                'User-Agent: GodyarCMS/1.0 (+https://godyar.org)',
            ];
            if ($bearer !== '') {
                $headers[] = 'Authorization: Bearer ' . $bearer;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $bin = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $ct = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $curlErr = (string)curl_error($ch);
            curl_close($ch);

            if (!is_string($bin) || $bin === '' || $code < 200 || $code >= 300) {
                fclose($fh);
                @unlink($mp3Path);
                $peek = '';
                if (is_string($bin)) {
                    $peek = trim(substr($bin, 0, 200));
                }
                @error_log('[TTS remote] failed: code=' . $code . ' err=' . $curlErr . ' voice=' . $voice . ' endpoint=' . $endpoint . ' peek=' . ($peek !== '' ? $peek : ''));

                if ($code === 401 || str_contains(strtolower($peek), 'no api key')) {
                    $hint = ($bearer === '' && $needsAuth)
                        ? 'يتطلب مزوّد TTS (StreamElements) مفتاح/Token. أضف GDY_TTS_REMOTE_BEARER في ملف .env ثم أعد المحاولة.'
                        : 'تحقق من صحة Token أو صلاحياته ثم أعد المحاولة.';
                    return ['ok'=>false,'code'=>$code ?: 401,'error'=>'TTS_AUTH_REQUIRED','hint'=>$hint];
                }
                if ($code === 429) {
                    return ['ok'=>false,'code'=>429,'error'=>'TTS_RATE_LIMIT','hint'=>'تم تجاوز حد الطلبات لدى مزوّد الصوت. جرّب بعد قليل.'];
                }
                if ($curlErr !== '') {
                    return ['ok'=>false,'code'=>$code,'error'=>'TTS_NETWORK_ERROR','hint'=>$curlErr];
                }
                return ['ok'=>false,'code'=>$code,'error'=>'tts remote failed'];
            }
            fwrite($fh, $bin);
        }
        fclose($fh);

        $ok = is_file($mp3Path) && filesize($mp3Path) > 1000;
        if (!$ok) {
            return ['ok'=>false,'error'=>'tts remote produced empty audio'];
        }
        return ['ok'=>true];
    }

    private function which(string $cmd): ?string
    {
        $out = @shell_exec('command -v ' . escapeshellarg($cmd) . ' 2>/dev/null');
        $out = trim((string)$out);
        return $out !== '' ? $out : null;
    }

    /**
     * Read env var reliably across Apache/LiteSpeed/PHP-FPM.
     * - SetEnv usually available via getenv/$_ENV
     * - RewriteRule [E=...] often appears in $_SERVER as KEY or REDIRECT_KEY
     */
    private function envGet(string $key): ?string
    {
        $val = getenv($key);
        if (is_string($val) && $val !== '') return $val;

        $val = $_ENV[$key] ?? null;
        if (is_string($val) && $val !== '') return $val;

        $val = $_SERVER[$key] ?? null;
        if (is_string($val) && $val !== '') return $val;

        $rk = 'REDIRECT_' . $key;
        $val = $_SERVER[$rk] ?? null;
        if (is_string($val) && $val !== '') return $val;

        return null;
    }

    private function spellCorrect(string $q): ?string
    {
        // Best-effort spell correction (tags + categories + recent titles).
        // NOTE: PHP's levenshtein is byte-based; this is still helpful for many typos.
        $q = trim($q);
        if ($q === '') return null;

        $tokens = preg_split('~\s+~u', preg_replace('~\s+~u', ' ', $q) ?? '', -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (!$tokens) return null;

        // Only correct short queries (avoid surprising changes)
        if (count($tokens) > 2) return null;

        $corpus = [];
        try {
            $st = $this->pdo->query("SELECT name FROM tags ORDER BY id DESC LIMIT 600");
            foreach (($st?($st->fetchAll(PDO::FETCH_COLUMN) ?: []):[]) as $w) {
                $w = trim((string)$w);
                if ($w !== '') $corpus[] = $w;
            }
        } catch (Throwable $e) {}
        try {
            $st = $this->pdo->query("SELECT name FROM categories ORDER BY id DESC LIMIT 300");
            foreach (($st?($st->fetchAll(PDO::FETCH_COLUMN) ?: []):[]) as $w) {
                $w = trim((string)$w);
                if ($w !== '') $corpus[] = $w;
            }
        } catch (Throwable $e) {}
        try {
            $st = $this->pdo->query("SELECT title FROM news WHERE status='published' ORDER BY id DESC LIMIT 400");
            foreach (($st?($st->fetchAll(PDO::FETCH_COLUMN) ?: []):[]) as $t) {
                $t = preg_replace('~[^\pL\pN\s]+~u', ' ', (string)$t);
                $t = preg_replace('~\s+~u', ' ', $t ?? '');
                foreach (preg_split('~\s+~u', trim((string)$t), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $w) {
                    if (mb_strlen($w, 'UTF-8') >= 3) $corpus[] = $w;
                }
            }
        } catch (Throwable $e) {}

        if (!$corpus) return null;
        // de-dup / cap
        $uniq = [];
        foreach ($corpus as $w) {
            $k = mb_strtolower($w, 'UTF-8');
            $uniq[$k] = $w;
            if (count($uniq) >= 1500) break;
        }
        $corpus = array_values($uniq);

        $corrected = [];
        $changed = false;
        foreach ($tokens as $tok) {
            $tok = (string)$tok;
            $tokL = mb_strtolower($tok, 'UTF-8');
            if (mb_strlen($tokL, 'UTF-8') < 3) { $corrected[] = $tok; continue; }

            $best = null;
            $bestD = 999;
            $first = mb_substr($tokL, 0, 1, 'UTF-8');

            foreach ($corpus as $w) {
                $wl = mb_strtolower((string)$w, 'UTF-8');
                // quick filters
                if ($first !== '' && mb_substr($wl, 0, 1, 'UTF-8') !== $first) continue;
                $lenDiff = abs(mb_strlen($wl, 'UTF-8') - mb_strlen($tokL, 'UTF-8'));
                if ($lenDiff > 2) continue;

                $d = levenshtein($tokL, $wl);
                if ($d < $bestD) {
                    $bestD = $d;
                    $best = (string)$w;
                    if ($bestD === 0) break;
                }
            }

            // accept only very close
            $threshold = (mb_strlen($tokL, 'UTF-8') >= 7) ? 3 : 2;
            if ($best !== null && $bestD > 0 && $bestD <= $threshold) {
                $corrected[] = $best;
                $changed = true;
            } else {
                $corrected[] = $tok;
            }
        }

        if (!$changed) return null;
        $out = trim(implode(' ', $corrected));
        if ($out === '' || $out === $q) return null;
        return $out;
    }

    private function slugify(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('~[^\pL\pN]+~u', '-', $s);
        $s = trim((string)$s, '-');
        $s = mb_strtolower((string)$s, 'UTF-8');
        return $s !== '' ? $s : 'topic';
    }

    /* -----------------------------
       Schema helpers (best effort)
       ----------------------------- */

private static array $dbColCache = [];

private function hasDbColumn(string $table, string $column): bool
{
    $key = $table . ':' . $column;
    if (array_key_exists($key, self::$dbColCache)) {
        return (bool)self::$dbColCache[$key];
    }
    try {
        $st = $this->pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE :c");
        $st->execute([':c' => $column]);
        $ok = (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        $ok = false;
    }
    self::$dbColCache[$key] = $ok;
    return $ok;
}

/**
 * Returns the actual schema we have for news_reactions across different installs.
 * Some installs use `type` instead of `reaction` and may not include `voter_key`.
 *
 * @return array{col:string, has_voter_key:bool, has_ip_address:bool, has_user_agent:bool, has_created_at:bool}
 */
private function reactionsSchema(): array
{
    static $cache = null;
    if (is_array($cache)) return $cache;

    $hasReaction = $this->hasDbColumn('news_reactions', 'reaction');
    $hasType = $this->hasDbColumn('news_reactions', 'type');

    $cache = [
        'col' => $hasReaction ? 'reaction' : ($hasType ? 'type' : 'reaction'),
        'has_voter_key' => $this->hasDbColumn('news_reactions', 'voter_key'),
        'has_ip_address' => $this->hasDbColumn('news_reactions', 'ip_address'),
        'has_user_agent' => $this->hasDbColumn('news_reactions', 'user_agent'),
        'has_created_at' => $this->hasDbColumn('news_reactions', 'created_at'),
    ];
    return $cache;
}

private function ensureReactionsTable(): void
{
    // Create a modern schema (new installs). If the table already exists with a legacy schema
    // (e.g., column `type` + ip_address/user_agent), we will not rename columns; we adapt at query time.
    $this->pdo->exec("CREATE TABLE IF NOT EXISTS news_reactions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        news_id INT UNSIGNED NOT NULL,
        reaction VARCHAR(20) NOT NULL,
        voter_key VARCHAR(80) DEFAULT NULL,
        ip_address VARCHAR(64) DEFAULT NULL,
        user_agent VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NULL,
        KEY idx_news (news_id),
        KEY idx_news_reaction (news_id, reaction)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    try {
        // If legacy schema uses `type`, keep it and just add missing columns if possible.
        $hasReaction = $this->hasDbColumn('news_reactions', 'reaction');
        $hasType = $this->hasDbColumn('news_reactions', 'type');

        if (!$hasReaction && !$hasType) {
            $this->pdo->exec("ALTER TABLE news_reactions ADD COLUMN reaction VARCHAR(20) NOT NULL DEFAULT 'like' AFTER news_id");
            self::$dbColCache = [];
        }

        // voter_key is optional (older schema uses ip_address/user_agent)
        if (!$this->hasDbColumn('news_reactions', 'voter_key')) {
            try {
                $this->pdo->exec("ALTER TABLE news_reactions ADD COLUMN voter_key VARCHAR(80) DEFAULT NULL");
                self::$dbColCache = [];
            } catch (Throwable $e) {}
        }

        if (!$this->hasDbColumn('news_reactions', 'ip_address')) {
            try {
                $this->pdo->exec("ALTER TABLE news_reactions ADD COLUMN ip_address VARCHAR(64) DEFAULT NULL");
                self::$dbColCache = [];
            } catch (Throwable $e) {}
        }

        if (!$this->hasDbColumn('news_reactions', 'user_agent')) {
            try {
                $this->pdo->exec("ALTER TABLE news_reactions ADD COLUMN user_agent VARCHAR(255) DEFAULT NULL");
                self::$dbColCache = [];
            } catch (Throwable $e) {}
        }

        if (!$this->hasDbColumn('news_reactions', 'created_at')) {
            try {
                $this->pdo->exec("ALTER TABLE news_reactions ADD COLUMN created_at DATETIME NULL");
                self::$dbColCache = [];
            } catch (Throwable $e) {}
        }

        // Best-effort unique index when voter_key exists
        $schema = $this->reactionsSchema();
        if (!empty($schema['has_voter_key'])) {
            $col = $schema['col'];
            $hasUniq = $this->pdo->query("SHOW INDEX FROM news_reactions WHERE Key_name='uniq_react'")->fetch(PDO::FETCH_ASSOC);
            if (!$hasUniq) {
                try {
                    $this->pdo->exec("ALTER TABLE news_reactions ADD UNIQUE KEY uniq_react (news_id, `$col`, voter_key)");
                } catch (Throwable $e) {}
            }
        }
    } catch (Throwable $e) {
        // ignore (no privileges / table missing)
    }
}

    private function ensureBookmarksTable(): void
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS user_bookmarks (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            news_id INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_user_news (user_id, news_id),
            KEY idx_user_id (user_id),
            KEY idx_news_id (news_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private function ensurePollTables(): void
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS news_polls (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            news_id INT UNSIGNED NOT NULL,
            question VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NULL,
            KEY idx_news (news_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS news_poll_options (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            poll_id INT UNSIGNED NOT NULL,
            label VARCHAR(255) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            KEY idx_poll (poll_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS news_poll_votes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            poll_id INT UNSIGNED NOT NULL,
            option_id INT UNSIGNED NOT NULL,
            voter_key VARCHAR(80) NOT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            UNIQUE KEY uniq_vote (poll_id, voter_key),
            KEY idx_option (option_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function ensureQuestionsTableReady(): bool
{
    try {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS news_questions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            news_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            name VARCHAR(120) NULL,
            email VARCHAR(190) NULL,
            question TEXT NOT NULL,
            answer TEXT NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            created_at DATETIME NULL,
            answered_at DATETIME NULL,
            KEY idx_news (news_id),
            KEY idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return true;
    } catch (Throwable $e) {
        // Some shared hostings deny CREATE TABLE at runtime.
        $this->json([
            'ok' => false,
            'error' => 'ميزة "اسأل الكاتب" غير مفعّلة في قاعدة البيانات.',
            'hint'  => 'شغّل ملف SQL: database/migrations/2026_01_02_create_news_questions.sql ثم أعد المحاولة.'
        ], 501);
        return false;
    }
}


    private function ensurePushTable(): void
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS push_subscriptions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            endpoint_hash CHAR(40) NOT NULL,
            user_id INT UNSIGNED NULL,
            endpoint TEXT NOT NULL,
            p256dh TEXT NOT NULL,
            auth TEXT NOT NULL,
            prefs_json JSON NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            UNIQUE KEY uniq_endpoint (endpoint_hash),
            KEY idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}
