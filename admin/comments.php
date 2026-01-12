<?php
declare(strict_types=1);


require_once __DIR__ . '/_admin_guard.php';
session_start();
require_once __DIR__ . '/../includes/bootstrap.php';

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$pdo = gdy_pdo_safe();
if (!($pdo instanceof PDO)) {
    die('DB error');
}

// baseUrl (لدعم التثبيت في مجلد فرعي)
$baseUrl = function_exists('base_url')
    ? rtrim((string)base_url(), '/')
    : (defined('BASE_URL') ? rtrim((string)BASE_URL, '/') : '');

$actionMsg = '';

// معالجة الأوامر: قبول / رفض / حذف
if (!empty($_GET['do']) && !empty($_GET['id'])) {
    $id  = (int)$_GET['id'];
    $do  = $_GET['do'];

    // جلب التعليق لمعرفة news_id
    $stmt = $pdo->prepare("SELECT id, news_id FROM news_comments WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($comment) {
        $nid = (int)$comment['news_id'];

        if ($do === 'approve') {
            $pdo->prepare("UPDATE news_comments SET status='approved' WHERE id=:id")
                ->execute([':id' => $id]);
            $actionMsg = __('t_97ae8b25c7', 'تم قبول التعليق.');
        } elseif ($do === 'reject') {
            $pdo->prepare("UPDATE news_comments SET status='rejected' WHERE id=:id")
                ->execute([':id' => $id]);
            $actionMsg = __('t_828a510a3e', 'تم رفض التعليق.');
        } elseif ($do === 'delete') {
            $pdo->prepare("DELETE FROM news_comments WHERE id=:id")
                ->execute([':id' => $id]);
            $actionMsg = __('t_17a1b66879', 'تم حذف التعليق.');
        }

        // إعادة حساب comments_count للخبر
        $pdo->prepare("
            UPDATE news n
            SET comments_count = (
              SELECT COUNT(*) FROM news_comments c
              WHERE c.news_id = n.id AND c.status='approved'
            )
            WHERE n.id = :nid
        ")->execute([':nid' => $nid]);
    }
}

// جلب آخر التعليقات
$stmt = $pdo->query("
    SELECT c.id, c.news_id, c.name, c.email, c.body, c.status, c.created_at,
           n.title AS news_title
    FROM news_comments c
    LEFT JOIN news n ON n.id = c.news_id
    ORDER BY c.created_at DESC
    LIMIT 200
");
$comments = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
?>
<!doctype html>
<html lang="<?= htmlspecialchars((string)(function_exists('current_lang') ? current_lang() : (string)($_SESSION['lang'] ?? 'ar')), ENT_QUOTES, 'UTF-8') ?>" dir="<?= ((function_exists('current_lang') ? current_lang() : (string)($_SESSION['lang'] ?? 'ar')) === 'ar' ? 'rtl' : 'ltr') ?>">
<head>
  <meta charset="utf-8">
  <title><?= h(__('t_08a0cc810c', 'إدارة التعليقات')) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
	  <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css">
</head>
<body class="bg-light">
<div class="container my-4">
  <h1 class="h4 mb-3"><?= h(__('t_08a0cc810c', 'إدارة التعليقات')) ?></h1>

  <?php if ($actionMsg): ?>
    <div class="alert alert-success py-2"><?= h($actionMsg) ?></div>
  <?php endif; ?>

  <div class="table-responsive">
    <table class="table table-sm table-striped align-middle">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th><?= h(__('t_213a03802a', 'الخبر')) ?></th>
          <th><?= h(__('t_2e8b171b46', 'الاسم')) ?></th>
          <th><?= h(__('t_533ba29a76', 'التعليق')) ?></th>
          <th><?= h(__('t_1253eb5642', 'الحالة')) ?></th>
          <th><?= h(__('t_8456f22b47', 'التاريخ')) ?></th>
          <th><?= h(__('t_11053ef7fa', 'أوامر')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($comments as $c): ?>
        <tr>
          <td><?= (int)$c['id'] ?></td>
          <td>
            <?php if (!empty($c['news_title'])): ?>
	              <a href="<?= h($baseUrl . '/news/id/' . (int)$c['news_id']) ?>" target="_blank">
                <?= h($c['news_title']) ?>
              </a>
            <?php else: ?>
              <span class="text-muted"><?= h(__('t_18fc31c036', 'غير مرتبط')) ?></span>
            <?php endif; ?>
          </td>
          <td><?= h($c['name']) ?></td>
          <td class="small"><?= nl2br(h(mb_strimwidth($c['body'],0,140,'...','UTF-8'))) ?></td>
          <td>
            <?php if ($c['status']==='approved'): ?>
              <span class="badge bg-success"><?= h(__('t_19837e3e8e', 'مقبول')) ?></span>
            <?php elseif ($c['status']==='rejected'): ?>
              <span class="badge bg-danger"><?= h(__('t_20a971a379', 'مرفوض')) ?></span>
            <?php else: ?>
              <span class="badge bg-warning text-dark"><?= h(__('t_0b8364216b', 'قيد المراجعة')) ?></span>
            <?php endif; ?>
          </td>
          <td class="small text-muted">
            <?= h(date('Y-m-d H:i', strtotime((string)$c['created_at']))) ?>
          </td>
          <td>
            <a class="btn btn-sm btn-outline-success"
               href="?do=approve&id=<?= (int)$c['id'] ?>"><?= h(__('t_d7e02c9230', 'قبول')) ?></a>
            <a class="btn btn-sm btn-outline-secondary"
               href="?do=reject&id=<?= (int)$c['id'] ?>"><?= h(__('t_b7dee9747a', 'رفض')) ?></a>
            <a class="btn btn-sm btn-outline-danger"
               href="?do=delete&id=<?= (int)$c['id'] ?>"
               data-confirm='حذف نهائي؟'><?= h(__('t_3b9854e1bb', 'حذف')) ?></a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
