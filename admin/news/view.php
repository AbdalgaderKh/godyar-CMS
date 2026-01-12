<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/_news_helpers.php';
// admin/news/view.php

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';

use Godyar\Auth;

$currentPage = 'posts';
$pageTitle   = __('t_405fe86576', 'عرض خبر/مقال');

if (!Auth::isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}


// صلاحية عرض المقالات
Auth::requirePermission('posts.view');

$isWriter = Auth::isWriter();
$userId   = (int)($_SESSION['user']['id'] ?? 0);

$pdo = \Godyar\DB::pdo();

if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    die(__('t_2ba0fe02fe', 'معرّف المقال غير صالح.'));
}

$stmt = $pdo->prepare("SELECT * FROM news WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    die(__('t_a429a9419c', 'المقال غير موجود.'));
}

// الكاتب يرى مقاله فقط
if ($isWriter && (int)($row['author_id'] ?? 0) !== $userId) {
    http_response_code(403);
    die(__('t_dfcf6b976c', 'غير مسموح لك عرض هذا المقال.'));
}



// تجهيز حقول العرض بشكل متوافق مع اختلاف أسماء الأعمدة
$statusLabels = [
    'published' => __('t_ecfb62b400', 'منشور'),
    'draft' => __('t_9071af8f2d', 'مسودة'),
    'pending' => __('t_e9210fb9c2', 'بانتظار المراجعة'),
    'archived' => __('t_2e67aea8ca', 'مؤرشف'),
    'deleted' => __('t_4528d8b9b1', 'محذوف'),
];

$summaryText = (string)($row['summary'] ?? ($row['excerpt'] ?? ''));
$bodyText    = (string)($row['content'] ?? ($row['body'] ?? ''));
$displayDate = (string)($row['published_at'] ?? ($row['created_at'] ?? ''));

// المرفقات
$attachments = gdy_get_news_attachments($pdo, $id);


require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>
<style>
/* التصميم الموحد للعرض - صفحة عرض الخبر في لوحة التحكم */
html, body {
  overflow-x: hidden;
}

@media (min-width: 992px) {
  .admin-content {
    margin-right: 260px !important; /* نفس عرض القائمة الجانبية */
  }
}

.admin-content.gdy-admin-page {
  background: radial-gradient(circle at top left, #020617 0%, #020617 45%, #020617 100%);
  min-height: 100vh;
  color: #e5e7eb;
}

/* حاوية داخلية لتقليص العرض وتوسيط المحتوى */
.admin-content.gdy-admin-page.container-fluid {
  max-width: 1200px;
  margin: 0 auto;
  padding: 1.5rem 1rem 2rem;
}

/* تحسين شكل الكروت */
.admin-content.gdy-admin-page .card.bg-dark {
  background-color: rgba(15,23,42,0.96) !important;
  border-color: rgba(148,163,184,0.4) !important;
}
.admin-content.gdy-admin-page .card-header {
  border-color: rgba(31,41,55,0.9) !important;
}
</style>

<div class="admin-content gdy-admin-page container-fluid py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div>
      <h1 class="h4 mb-1 text-white">
        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg>
        <?= h(__('t_d87bb21604', 'عرض الخبر / المقال')) ?>
      </h1>
      <p class="text-muted mb-0 small">
        <?= h(__('t_a7cd995583', 'معاينة تفاصيل الخبر كما تظهر في واجهة الإدارة.')) ?>
      </p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
      <a href="edit.php?id=<?= (int)$row['id'] ?>" class="btn btn-primary">
        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_759fdc242e', 'تعديل')) ?>
      </a>
      <a href="index.php" class="btn btn-outline-light">
        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_19ae074cbf', 'العودة للقائمة')) ?>
      </a>
    </div>
  </div>

  <div class="card glass-card mb-3" style="background:rgba(15,23,42,.95);color:#e5e7eb;">
    <div class="card-body">
      <div class="row">
        <div class="col-md-8">
          <h2 class="h5 mb-1"><?= h($row['title']) ?></h2>
          <div class="small text-muted mb-2">
            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <?= h($displayDate) ?>
          </div>
          <?php if ($summaryText !== ''): ?>
            <p class="mb-0"><?= nl2br(h($summaryText)) ?></p>
          <?php endif; ?>
        </div>
        <div class="col-md-4">
          <div class="border rounded p-3 text-center" style="background:#020617;">
            <div class="mb-2">
              <span class="badge bg-info-subtle text-info">
                <?= h(__('t_4404f61de7', 'رقم الخبر:')) ?> <?= (int)$row['id'] ?>
              </span>
            </div>
            <div class="small text-muted">
              <div><?= h(__('t_3f3f7de97d', 'الحالة:')) ?><?= h($statusLabels[$row['status']] ?? $row['status']) ?></div>
              <?php if (!empty($row['category_id'])): ?>
                <div><?= h(__('t_d32197597f', 'التصنيف: #')) ?><?= (int)$row['category_id'] ?></div>
              <?php endif; ?>
              <?php if (!empty($row['author_id'])): ?>
                <div><?= h(__('t_214fa8fcfb', 'الكاتب: #')) ?><?= (int)$row['author_id'] ?></div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>


  <?php if (!empty($attachments)): ?>
    <div class="card glass-card mb-3" style="background:rgba(15,23,42,.95);color:#e5e7eb;">
      <div class="card-header" style="background:#020617;border-bottom:1px solid #1f2937;">
        <h2 class="h6 mb-0"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_a2737af54c', 'المرفقات')) ?></h2>
      </div>
      <div class="card-body">
        <div class="d-flex flex-column gap-2">
          <?php foreach ($attachments as $idx => $att):
            $attUrl = '/' . ltrim((string)$att['file_path'], '/');
            $icon   = gdy_attachment_icon_class((string)$att['original_name']);
            $ext    = strtolower(pathinfo((string)$att['original_name'], PATHINFO_EXTENSION));
            $cid    = 'attPrev_' . (int)$id . '_' . (int)($att['id'] ?? 0) . '_' . (int)$idx;
            $isPdf  = ($ext === 'pdf');
            $isImg  = in_array($ext, ['png','jpg','jpeg','gif','webp'], true);
            $isTxt  = in_array($ext, ['txt','rtf'], true);
          ?>
            <div class="p-2 rounded"
                 style="border:1px solid rgba(148,163,184,.25);background:rgba(2,6,23,.6);">
              <div class="d-flex align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2" style="min-width:0;">
                  <i class="<?= h($icon) ?>"></i>
                  <div class="text-truncate" style="max-width:520px;"><?= h($att['original_name']) ?></div>
                </div>
                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                  <button class="btn btn-sm btn-outline-light" type="button"
                          data-bs-toggle="collapse" data-bs-target="#<?= h($cid) ?>"
                          aria-expanded="false" aria-controls="<?= h($cid) ?>" title="<?= h(__('t_d6a504f92a', 'مشاهدة داخل الصفحة')) ?>">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  </button>
                  <a class="btn btn-sm btn-outline-info" href="<?= h($attUrl) ?>" download title="<?= h(__('t_871a087a1d', 'حفظ')) ?>">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  </a>
                </div>
              </div>

              <div class="collapse mt-2" id="<?= h($cid) ?>">
                <div class="rounded p-2" style="background:rgba(15,23,42,.9);border:1px solid rgba(148,163,184,.2);">
                  <?php if ($isPdf): ?>
                    <iframe src="<?= h($attUrl) ?>"
                            style="width:100%;height:560px;border:0;border-radius:10px;background:#0b1220;"
                            loading="lazy"></iframe>
                  <?php elseif ($isImg): ?>
                    <div class="text-center">
                      <img src="<?= h($attUrl) ?>" alt="<?= h($att['original_name']) ?>"
                           style="max-width:100%;height:auto;border-radius:10px;border:1px solid rgba(148,163,184,.25);">
                    </div>
                  <?php elseif ($isTxt): ?>
                    <iframe src="<?= h($attUrl) ?>"
                            style="width:100%;height:420px;border:0;border-radius:10px;background:#0b1220;"
                            loading="lazy"></iframe>
                  <?php else: ?>
                    <div class="small text-muted">
                      <?= h(__('t_9f1b6a7644', 'لا يمكن عرض هذا النوع مباشرة داخل الصفحة. استخدم زر')) ?> <strong><?= h(__('t_871a087a1d', 'حفظ')) ?></strong> <?= h(__('t_0fa14e657d', 'لتنزيله وفتحه على جهازك.')) ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>


  <div class="card glass-card gdy-card" style="background:rgba(15,23,42,.95);color:#e5e7eb;">
    <div class="card-header" style="background:#020617;border-bottom:1px solid #1f2937;">
      <h2 class="h6 mb-0"><?= h(__('t_9b0ff1e677', 'النص الكامل')) ?></h2>
    </div>
    <div class="card-body">
      <div style="white-space:pre-wrap;word-wrap:break-word;">
        <?= nl2br(h($bodyText)) ?>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../layout/footer.php'; ?>
