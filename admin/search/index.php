<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';
$pdo = gdy_pdo_safe();

$pageTitle = __('t_admin_global_search', 'بحث شامل');
$searchTerm = trim((string)($_GET['q'] ?? ''));

require_once __DIR__ . '/../layout/app_start.php';

function table_exists(PDO $pdo, string $table): bool {
  try {
    $st = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
    return (bool)($st && $st->fetchColumn());
  } catch (Throwable $e) {
    return false;
  }
}

function col_exists(PDO $pdo, string $table, string $col): bool {
  try {
    $st = $pdo->query("SHOW COLUMNS FROM `$table` LIKE " . $pdo->quote($col));
    return (bool)($st && $st->fetchColumn());
  } catch (Throwable $e) {
    return false;
  }
}

function highlight_html(string $escaped, string $q): string {
  $q = trim($q);
  if ($q === '') return $escaped;
  // split by whitespace and punctuation
  $parts = preg_split('/[\s\p{P}]+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
  $parts = array_values(array_unique(array_filter($parts, function($w){ return mb_strlen($w, 'UTF-8') >= 2; })));
  if (!$parts) return $escaped;

  foreach ($parts as $w) {
    $re = '/' . preg_quote($w, '/') . '/iu';
    $escaped = preg_replace($re, '<mark>$0</mark>', $escaped) ?? $escaped;
  }
  return $escaped;
}

/**
 * Build a safe OR-LIKE condition without reusing the same PDO named placeholder.
 *
 * NOTE: Reusing the same named placeholder multiple times (e.g. `:like` repeated)
 * can throw `SQLSTATE[HY093]: Invalid parameter number` depending on PDO settings.
 */
function build_like_or(array $expressions, string $paramPrefix, string $like, array &$params): string {
  $parts = [];
  $i = 1;
  foreach ($expressions as $expr) {
    $key = $paramPrefix . $i;
    $parts[] = $expr . ' LIKE :' . $key;
    $params[$key] = $like;
    $i++;
  }
  if (!$parts) return '(1=0)';
  return '(' . implode(' OR ', $parts) . ')';
}

$results = [
  'news' => [],
  'users' => [],
  'comments' => [],
  'cats_tags' => [],
  'media' => [],
];

$error = null;

if ($searchTerm !== '' && $pdo instanceof PDO) {
  $like = '%' . $searchTerm . '%';

  // ---------------- News ----------------
  try {
    if (table_exists($pdo, 'news')) {
      // FULLTEXT?
      $hasFt = false;
      try {
        $idx = $pdo->query("SHOW INDEX FROM news");
        if ($idx) {
          while ($r = $idx->fetch(PDO::FETCH_ASSOC)) {
            if (($r['Index_type'] ?? '') === 'FULLTEXT') { $hasFt = true; break; }
          }
        }
      } catch (Throwable $e) {}

      $joinUsers = table_exists($pdo, 'users') && col_exists($pdo,'news','author_id') && col_exists($pdo,'users','id');
      $joinOpinion = table_exists($pdo, 'opinion_authors') && col_exists($pdo,'news','opinion_author_id');

      $params = [];
      $likeExpr = [
        'n.title',
        'n.slug',
        'n.excerpt',
        'n.content',
      ];
      if ($joinUsers) {
        $likeExpr[] = 'u.username';
        $likeExpr[] = 'u.email';
      }
      if ($joinOpinion) {
        $likeExpr[] = 'oa.name';
        $likeExpr[] = 'oa.slug';
      }
      $whereLike = build_like_or($likeExpr, 'nlike', $like, $params);

      $sql = "SELECT n.id, n.title, n.slug, n.status, n.created_at
              " . ($joinUsers ? ", u.username AS author_username, u.email AS author_email" : "") . "
              " . ($joinOpinion ? ", oa.name AS opinion_author_name" : "") . "
              FROM news n
              " . ($joinUsers ? "LEFT JOIN users u ON u.id = n.author_id" : "") . "
              " . ($joinOpinion ? "LEFT JOIN opinion_authors oa ON oa.id = n.opinion_author_id" : "") . "
              WHERE $whereLike
              ORDER BY n.id DESC
              LIMIT 20";
      // If FULLTEXT exists, add score ordering (still keep LIKE for Arabic)
      // IMPORTANT: Do NOT reuse the same named placeholder (:q) more than once.
      // Some PDO configurations/drivers throw: SQLSTATE[HY093] Invalid parameter number.
      if ($hasFt) {
        $sql = "SELECT n.id, n.title, n.slug, n.status, n.created_at,
                       MATCH(n.title,n.excerpt,n.content) AGAINST(:q1 IN NATURAL LANGUAGE MODE) AS score
                " . ($joinUsers ? ", u.username AS author_username, u.email AS author_email" : "") . "
                " . ($joinOpinion ? ", oa.name AS opinion_author_name" : "") . "
                FROM news n
                " . ($joinUsers ? "LEFT JOIN users u ON u.id = n.author_id" : "") . "
                " . ($joinOpinion ? "LEFT JOIN opinion_authors oa ON oa.id = n.opinion_author_id" : "") . "
                WHERE (
                  MATCH(n.title,n.excerpt,n.content) AGAINST(:q2 IN NATURAL LANGUAGE MODE)
                  OR $whereLike
                )
                ORDER BY score DESC, n.id DESC
                LIMIT 20";
      }

      $st = $pdo->prepare($sql);
      if ($hasFt) {
        $params['q1'] = $searchTerm;
        $params['q2'] = $searchTerm;
      }
      $st->execute($params);
      $results['news'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
  } catch (Throwable $e) { $error = $e->getMessage(); }

  // ---------------- Users ----------------
  try {
    if (table_exists($pdo, 'users')) {
      $exprs = [];
      foreach (['username','email','name','full_name','display_name'] as $c) {
        if (col_exists($pdo,'users',$c)) $exprs[] = "u.`$c`";
      }
      $joinProfiles = table_exists($pdo, 'user_profiles') && col_exists($pdo,'user_profiles','user_id');
      if ($joinProfiles) {
        foreach (['full_name','name','bio'] as $c) {
          if (col_exists($pdo,'user_profiles',$c)) $exprs[] = "up.`$c`";
        }
      }
      if (!$exprs) $exprs = ["u.`email`"]; 

      $params = [];
      $where = build_like_or($exprs, 'ulike', $like, $params);

      $sql = "SELECT u.id, " . (col_exists($pdo,'users','username') ? "u.username" : "u.email") . " AS username, u.email, u.role, u.status
              FROM users u
              " . ($joinProfiles ? "LEFT JOIN user_profiles up ON up.user_id = u.id" : "") . "
              WHERE $where
              ORDER BY u.id DESC
              LIMIT 20";
      $st = $pdo->prepare($sql);
      $st->execute($params);
      $results['users'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
  } catch (Throwable $e) { $error = $e->getMessage(); }

  // ---------------- Comments ----------------
  try {
    $commentRows = [];
    foreach (['comments','news_comments'] as $tbl) {
      if (!table_exists($pdo, $tbl)) continue;

      $textCol = null;
      foreach (['body','content','comment','text','message'] as $c) {
        if (col_exists($pdo, $tbl, $c)) { $textCol = $c; break; }
      }
      if (!$textCol) continue;

      $titleCol = col_exists($pdo,$tbl,'title') ? 'title' : null;
      $userCol = col_exists($pdo,$tbl,'user_id') ? 'user_id' : null;

      $params = [];
      $exprs = ["`$textCol`"]; 
      if ($titleCol) $exprs[] = "`$titleCol`";
      $where = build_like_or($exprs, 'clike', $like, $params);

      $sql = "SELECT id, " . ($titleCol ? "$titleCol AS title," : "NULL AS title,") . " `$textCol` AS body, created_at
              FROM `$tbl`
              WHERE $where
              ORDER BY id DESC
              LIMIT 10";
      $st = $pdo->prepare($sql);
      $st->execute($params);
      $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
      foreach ($rows as &$r) { $r['_table'] = $tbl; }
      $commentRows = array_merge($commentRows, $rows);
    }
    // sort merged by id desc roughly
    usort($commentRows, function($a,$b){ return (int)($b['id']??0) <=> (int)($a['id']??0); });
    $results['comments'] = array_slice($commentRows, 0, 20);
  } catch (Throwable $e) { $error = $e->getMessage(); }

  // ---------------- Categories + Tags ----------------
  try {
    $catsTags = [];

    if (table_exists($pdo, 'categories')) {
      $params = [];
      $where = build_like_or(['`name`','`slug`'], 'catlike', $like, $params);
      $st = $pdo->prepare("SELECT id, name, slug FROM categories WHERE $where ORDER BY id DESC LIMIT 10");
      $st->execute($params);
      $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
      foreach ($rows as &$r) { $r['_type'] = 'category'; }
      $catsTags = array_merge($catsTags, $rows);
    }

    if (table_exists($pdo, 'tags')) {
      $params = [];
      $where = build_like_or(['`name`','`slug`'], 'taglike', $like, $params);
      $st = $pdo->prepare("SELECT id, name, slug FROM tags WHERE $where ORDER BY id DESC LIMIT 10");
      $st->execute($params);
      $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
      foreach ($rows as &$r) { $r['_type'] = 'tag'; }
      $catsTags = array_merge($catsTags, $rows);
    }

    $results['cats_tags'] = $catsTags;
  } catch (Throwable $e) { $error = $e->getMessage(); }

  // ---------------- Media ----------------
  try {
    if (table_exists($pdo, 'media')) {
      $exprs = [];
      foreach (['title','file_name','path','alt','caption'] as $c) {
        if (col_exists($pdo,'media',$c)) $exprs[] = "`$c`";
      }
      if (!$exprs) $exprs = ["`id`"]; // will be LIKE, harmless fallback

      $params = [];
      $where = build_like_or($exprs, 'mlike', $like, $params);

      $sql = "SELECT id, " . (col_exists($pdo,'media','title') ? "title" : "NULL AS title") . ",
                     " . (col_exists($pdo,'media','file_name') ? "file_name" : "NULL AS file_name") . ",
                     created_at
              FROM media
              WHERE $where
              ORDER BY id DESC
              LIMIT 20";
      $st = $pdo->prepare($sql);
      $st->execute($params);
      $results['media'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
  } catch (Throwable $e) { $error = $e->getMessage(); }
}

?>

<div class="row g-3">
  <div class="col-12">
    <div class="gdy-card card">
      <div class="card-body">
        <form method="get" class="d-flex flex-column flex-md-row gap-2 align-items-stretch">
          <input type="text" name="q" class="form-control" placeholder="<?= h(__('t_admin_search_placeholder','ابحث داخل الأخبار والمستخدمين والتعليقات والتصنيفات والوسوم والوسائط')) ?>" value="<?= h($searchTerm) ?>">
          <button class="btn btn-gdy btn-gdy-primary" type="submit">
            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#search"></use></svg> <?= h(__('t_admin_search','بحث')) ?>
          </button>
        </form>
        <?php if ($error): ?>
          <div class="alert alert-warning mt-3 small"><?= h($error) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($searchTerm !== ''): ?>
    <div class="col-12 col-lg-6">
      <div class="gdy-card card">
        <div class="card-body">
          <h5 class="mb-3"><?= h(__('t_admin_news','الأخبار')) ?> <span class="text-muted">(<?= (int)count($results['news']) ?>)</span></h5>
          <?php if (!$results['news']): ?>
            <div class="text-muted small"><?= h(__('t_admin_no_results','لا توجد نتائج.')) ?></div>
          <?php else: ?>
            <div class="list-group list-group-flush">
              <?php foreach ($results['news'] as $n): ?>
                <?php
                  $title = highlight_html(h((string)($n['title'] ?? '')), $searchTerm);
                  $sub = [];
                  if (!empty($n['author_username'])) $sub[] = (string)$n['author_username'];
                  if (!empty($n['opinion_author_name'])) $sub[] = (string)$n['opinion_author_name'];
                  $subTxt = highlight_html(h(implode(' • ', $sub)), $searchTerm);
                ?>
                <a class="list-group-item list-group-item-action" href="/admin/news/edit.php?id=<?= (int)$n['id'] ?>">
                  <div class="d-flex justify-content-between gap-2">
                    <div class="text-truncate"><?= $title ?></div>
                    <small class="text-muted"><?= h((string)($n['status'] ?? '')) ?></small>
                  </div>
                  <?php if ($subTxt !== ''): ?><div class="small text-muted"><?= $subTxt ?></div><?php endif; ?>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="gdy-card card">
        <div class="card-body">
          <h5 class="mb-3"><?= h(__('t_admin_users','المستخدمون')) ?> <span class="text-muted">(<?= (int)count($results['users']) ?>)</span></h5>
          <?php if (!$results['users']): ?>
            <div class="text-muted small"><?= h(__('t_admin_no_results','لا توجد نتائج.')) ?></div>
          <?php else: ?>
            <div class="list-group list-group-flush">
              <?php foreach ($results['users'] as $u): ?>
                <?php $name = highlight_html(h((string)($u['username'] ?? $u['email'] ?? '')), $searchTerm); ?>
                <a class="list-group-item list-group-item-action" href="/admin/users/edit.php?id=<?= (int)$u['id'] ?>">
                  <div class="d-flex justify-content-between gap-2">
                    <div class="text-truncate"><?= $name ?></div>
                    <small class="text-muted"><?= h((string)($u['role'] ?? '')) ?></small>
                  </div>
                  <div class="small text-muted"><?= highlight_html(h((string)($u['email'] ?? '')), $searchTerm) ?></div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="gdy-card card">
        <div class="card-body">
          <h5 class="mb-3"><?= h(__('t_admin_comments','التعليقات')) ?> <span class="text-muted">(<?= (int)count($results['comments']) ?>)</span></h5>
          <?php if (!$results['comments']): ?>
            <div class="text-muted small"><?= h(__('t_admin_no_results','لا توجد نتائج.')) ?></div>
          <?php else: ?>
            <div class="list-group list-group-flush">
              <?php foreach ($results['comments'] as $c): ?>
                <?php
                  $body = (string)($c['body'] ?? '');
                  $body = mb_substr($body, 0, 160, 'UTF-8');
                  $body = highlight_html(h($body), $searchTerm);
                  $tbl = (string)($c['_table'] ?? 'comments');
                  $href = '/admin/comments/index.php';
                ?>
                <a class="list-group-item list-group-item-action" href="<?= h($href) ?>">
                  <div class="small text-muted mb-1"><?= h($tbl) ?> • #<?= (int)$c['id'] ?></div>
                  <div><?= $body ?></div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="gdy-card card">
        <div class="card-body">
          <h5 class="mb-3"><?= h(__('t_admin_categories_tags','التصنيفات والوسوم')) ?> <span class="text-muted">(<?= (int)count($results['cats_tags']) ?>)</span></h5>
          <?php if (!$results['cats_tags']): ?>
            <div class="text-muted small"><?= h(__('t_admin_no_results','لا توجد نتائج.')) ?></div>
          <?php else: ?>
            <div class="list-group list-group-flush">
              <?php foreach ($results['cats_tags'] as $ct): ?>
                <?php
                  $type = (string)($ct['_type'] ?? '');
                  $href = $type === 'tag' ? ('/admin/tags/edit.php?id='.(int)$ct['id']) : ('/admin/categories/edit.php?id='.(int)$ct['id']);
                  $label = $type === 'tag' ? __('t_admin_tag','وسم') : __('t_admin_category','تصنيف');
                ?>
                <a class="list-group-item list-group-item-action" href="<?= h($href) ?>">
                  <div class="d-flex justify-content-between gap-2">
                    <div class="text-truncate"><?= highlight_html(h((string)($ct['name'] ?? '')), $searchTerm) ?></div>
                    <small class="text-muted"><?= h($label) ?></small>
                  </div>
                  <div class="small text-muted"><?= highlight_html(h((string)($ct['slug'] ?? '')), $searchTerm) ?></div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="gdy-card card">
        <div class="card-body">
          <h5 class="mb-3"><?= h(__('t_admin_media','الوسائط')) ?> <span class="text-muted">(<?= (int)count($results['media']) ?>)</span></h5>
          <?php if (!$results['media']): ?>
            <div class="text-muted small"><?= h(__('t_admin_no_results','لا توجد نتائج.')) ?></div>
          <?php else: ?>
            <div class="list-group list-group-flush">
              <?php foreach ($results['media'] as $m): ?>
                <a class="list-group-item list-group-item-action" href="/admin/media/edit.php?id=<?= (int)$m['id'] ?>">
                  <div class="d-flex justify-content-between gap-2">
                    <div class="text-truncate">
                      <?= highlight_html(h((string)($m['title'] ?? $m['file_name'] ?? ('#'.$m['id']))), $searchTerm) ?>
                    </div>
                    <small class="text-muted">#<?= (int)$m['id'] ?></small>
                  </div>
                  <?php if (!empty($m['file_name'])): ?><div class="small text-muted"><?= highlight_html(h((string)$m['file_name']), $searchTerm) ?></div><?php endif; ?>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layout/app_end.php'; ?>