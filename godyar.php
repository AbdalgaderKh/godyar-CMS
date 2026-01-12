<?php
// godyar/admin/news/edit.php

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// تأكد أن لديك اتصال PDO في $pdo
// لو اتصالك اسمه مختلف عدّل هنا
if (!isset($pdo)) {
    // مثال إن كان لديك ملف config خاص بالاتصال
    // require_once __DIR__ . '/../config.php';
}

// 1) التقاط معرف الخبر
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors  = [];
$success = '';

// 2) جلب بيانات الخبر من قاعدة البيانات
$news = [];
if ($id > 0 && isset($pdo)) {
    $stmt = $pdo->prepare("SELECT * FROM news WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $news = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$news) {
        $errors[] = "لم يتم العثور على الخبر المطلوب.";
    }
} else {
    $errors[] = "معرّف الخبر غير صحيح.";
}

// 3) جلب قائمة الفئات
$categories = [];
if (isset($pdo)) {
    $catStmt = $pdo->query("SELECT id, name FROM categories ORDER BY sort_order ASC, name ASC");
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
}

// 4) في حالة POST: حفظ التعديلات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors) && isset($pdo)) {

    $title       = trim($_POST['title']       ?? '');
    $slug        = trim($_POST['slug']        ?? '');
    $excerpt     = trim($_POST['excerpt']     ?? '');
    $body        = trim($_POST['body']        ?? '');
    $status      = trim($_POST['status']      ?? 'draft');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $publish_at  = trim($_POST['published_at'] ?? '');
    $read_time   = (int)($_POST['read_time']   ?? 0);
    $views       = (int)($_POST['views']       ?? 0);

    // تحقق بسيط
    if ($title === '') {
        $errors[] = "الرجاء إدخال عنوان الخبر.";
    }
    if ($category_id <= 0) {
        $errors[] = "الرجاء اختيار فئة (قسم) للخبر.";
    }

    // تحويل تاريخ النشر لصيغة قاعدة البيانات
    $publish_at_db = null;
    if ($publish_at !== '') {
        // يأتي من input[type=datetime-local] بصيغة: 2025-11-29T22:15
        $ts = strtotime($publish_at);
        if ($ts !== false) {
            $publish_at_db = date('Y-m-d H:i:s', $ts);
        }
    }

    // لو لا يوجد أخطاء → نفّذ التحديث
    if (empty($errors)) {
        // تحديث الحقول النصية
        $sql = "UPDATE news 
                   SET title        = :title,
                       slug         = :slug,
                       excerpt      = :excerpt,
                       body         = :body,
                       status       = :status,
                       category_id  = :category_id,
                       published_at = :published_at,
                       read_time    = :read_time,
                       views        = :views
                 WHERE id = :id
                 LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $ok = $stmt->execute([
            'title'        => $title,
            'slug'         => $slug,
            'excerpt'      => $excerpt,
            'body'         => $body,
            'status'       => $status,
            'category_id'  => $category_id,
            'published_at' => $publish_at_db,
            'read_time'    => $read_time,
            'views'        => $views,
            'id'           => $id,
        ]);

        // رفع صورة جديدة (إن وجدت)
        if (!empty($_FILES['featured_image']['name'])) {
            $uploadDir  = __DIR__ . '/../../uploads/news/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }

            $ext = pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION);
            $ext = strtolower($ext ?: 'jpg');
            $newName = 'news_' . $id . '_' . time() . '.' . $ext;
            $destPath = $uploadDir . $newName;

            if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $destPath)) {
                // نحفظ المسار النسبي في قاعدة البيانات
                $publicPath = '/uploads/news/' . $newName;
                $u = $pdo->prepare("UPDATE news SET featured_image = :img WHERE id = :id LIMIT 1");
                $u->execute(['img' => $publicPath, 'id' => $id]);
            }
        }

        if ($ok) {
            $success = "تم حفظ التعديلات بنجاح.";
            // إعادة تحميل الخبر بعد الحفظ
            $stmt = $pdo->prepare("SELECT * FROM news WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $id]);
            $news = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $errors[] = "حدث خطأ أثناء حفظ البيانات.";
        }
    }
}

// إعادة تحضير المتغيرات للعرض
$title       = $news['title']         ?? ($title       ?? '');
$slug        = $news['slug']          ?? ($slug        ?? '');
$excerpt     = $news['excerpt']       ?? ($excerpt     ?? '');
$body        = $news['body']          ?? ($body        ?? '');
$status      = $news['status']        ?? ($status      ?? 'draft');
$category_id = (int)($news['category_id'] ?? ($category_id ?? 0));
$publish_at  = $news['published_at']  ?? ($publish_at  ?? '');
$read_time   = (int)($news['read_time'] ?? ($read_time ?? 0));
$views       = (int)($news['views']     ?? ($views     ?? 0));
$featuredImg = $news['featured_image'] ?? '';
?>

<main class="col-12 col-md-9 col-lg-10 p-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h5 mb-0">تعديل خبر</h2>
    <?php if ($id): ?>
      <span class="text-muted small">#<?= $id ?></span>
    <?php endif; ?>
  </div>

  <?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $e): ?>
          <li><?= h($e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($id && $news): ?>
  <form method="post" enctype="multipart/form-data" class="card p-3">

    <div class="mb-3">
      <label class="form-label">عنوان الخبر</label>
      <input type="text" name="title" class="form-control" required
             value="<?= h($title) ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">الرابط المختصر (Slug)</label>
      <input type="text" name="slug" class="form-control"
             value="<?= h($slug) ?>"
             placeholder="اتركه فارغاً ليُولد تلقائياً من العنوان">
    </div>

    <!-- الفئة -->
    <div class="mb-3">
      <label class="form-label">الفئة / القسم</label>
      <select name="category_id" class="form-select" required>
        <option value="">— اختر الفئة —</option>
        <?php foreach ($categories as $cat): ?>
          <?php $cid = (int)($cat['id'] ?? 0); ?>
          <option value="<?= $cid ?>" <?= $cid === $category_id ? 'selected' : '' ?>>
            <?= h($cat['name'] ?? ('فئة #' . $cid)) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div class="form-text text-muted">
        تأكد من اختيار الفئة الصحيحة حتى يظهر الخبر داخل صفحة هذه الفئة في الواجهة.
      </div>
    </div>

    <div class="row">
      <div class="col-md-4 mb-3">
        <label class="form-label">حالة الخبر</label>
        <select name="status" class="form-select">
          <option value="draft"     <?= $status === 'draft'     ? 'selected' : '' ?>>مسودة</option>
          <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>منشور</option>
          <option value="archived"  <?= $status === 'archived'  ? 'selected' : '' ?>>مؤرشف</option>
        </select>
      </div>

      <div class="col-md-4 mb-3">
        <label class="form-label">تاريخ / وقت النشر</label>
        <input type="datetime-local" name="published_at" class="form-control"
               value="<?= $publish_at ? date('Y-m-d\TH:i', strtotime($publish_at)) : '' ?>">
      </div>

      <div class="col-md-2 mb-3">
        <label class="form-label">وقت القراءة (دقيقة)</label>
        <input type="number" name="read_time" class="form-control" min="0"
               value="<?= $read_time ?: '' ?>">
      </div>

      <div class="col-md-2 mb-3">
        <label class="form-label">المشاهدات</label>
        <input type="number" name="views" class="form-control" min="0"
               value="<?= $views ?>">
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">ملخص قصير</label>
      <textarea name="excerpt" rows="3" class="form-control"><?= h($excerpt) ?></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">نص الخبر</label>
      <textarea name="body" rows="10" class="form-control" required><?= h($body) ?></textarea>
    </div>

    <div class="row mb-3">
      <div class="col-md-6">
        <label class="form-label">الصورة المميزة</label>
        <input type="file" name="featured_image" class="form-control" accept="image/*">
      </div>
      <?php if ($featuredImg): ?>
        <div class="col-md-6">
          <label class="form-label d-block">الصورة الحالية</label>
          <img src="<?= h($featuredImg) ?>" alt="الصورة الحالية"
               class="img-fluid rounded border" style="max-height:150px;">
        </div>
      <?php endif; ?>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-3">
      <a href="news_list.php" class="btn btn-outline-secondary">
        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> رجوع لقائمة الأخبار
      </a>

      <button type="submit" class="btn btn-primary">
        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> حفظ التعديلات
      </button>
    </div>

  </form>
  <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>