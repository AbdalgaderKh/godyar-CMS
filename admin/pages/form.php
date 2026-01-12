<?php

require_once __DIR__ . '/../_admin_guard.php';
// متوقع وجود المتغيرات التالية قبل تضمين هذا الملف:
// $mode = 'create' أو 'edit'
// $values = [ 'title' => '', 'slug' => '', 'content' => '', 'status' => 'draft', 'meta_title' => '', 'meta_description' => '', 'is_system' => 0 ]
// $errors = []  (مصفوفة أخطاء نصية حسب الحقول)
?>
<div class="card shadow-sm border-0">
  <div class="card-body">
    <div class="mb-3">
      <label class="form-label"><?= h(__('t_3463295a54', 'عنوان الصفحة')) ?> <span class="text-danger">*</span></label>
      <input type="text" name="title" class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>"
             value="<?= htmlspecialchars($values['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      <?php if (!empty($errors['title'])): ?>
        <div class="invalid-feedback"><?= htmlspecialchars($errors['title'], ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
    </div>

    <div class="mb-3">
      <label class="form-label">
        <?= h(__('t_0781965540', 'الرابط (Slug)')) ?>
        <span class="text-muted small d-block"><?= h(__('t_c6a2240b97', 'يُستخدم في الرابط مثل: /page/slug-هنا')) ?></span>
      </label>
      <input type="text" name="slug" class="form-control <?= isset($errors['slug']) ? 'is-invalid' : '' ?>"
             dir="ltr"
             value="<?= htmlspecialchars($values['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      <?php if (!empty($errors['slug'])): ?>
        <div class="invalid-feedback"><?= htmlspecialchars($errors['slug'], ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
    </div>

    <div class="mb-3">
      <label class="form-label"><?= h(__('t_e261adf643', 'محتوى الصفحة')) ?></label>
      <textarea name="content" rows="8" class="form-control"><?= htmlspecialchars($values['content'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
    </div>

    <div class="row">
      <div class="col-md-4 mb-3">
        <label class="form-label"><?= h(__('t_1253eb5642', 'الحالة')) ?></label>
        <select name="status" class="form-select">
          <option value="draft"     <?= ($values['status'] ?? '') === 'draft' ? 'selected' : '' ?>><?= h(__('t_9071af8f2d', 'مسودة')) ?></option>
          <option value="published" <?= ($values['status'] ?? '') === 'published' ? 'selected' : '' ?>><?= h(__('t_c67d973434', 'منشورة')) ?></option>
        </select>
      </div>
      <div class="col-md-4 mb-3">
        <label class="form-label"><?= h(__('t_12d1224b79', 'صفحة نظامية؟')) ?></label>
        <select name="is_system" class="form-select">
          <option value="0" <?= !empty($values['is_system']) ? '' : 'selected' ?>><?= h(__('t_b27ea934ef', 'لا')) ?></option>
          <option value="1" <?= !empty($values['is_system']) ? 'selected' : '' ?>><?= h(__('t_e1dadf4c7c', 'نعم')) ?></option>
        </select>
      </div>
    </div>

    <hr>

    <h6 class="mb-3"><?= h(__('t_5584163b0c', 'إعدادات SEO')) ?></h6>

    <div class="mb-3">
      <label class="form-label"><?= h(__('t_6267a6f940', 'عنوان الميتا (Meta Title)')) ?></label>
      <input type="text" name="meta_title" class="form-control"
             value="<?= htmlspecialchars($values['meta_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    </div>

    <div class="mb-3">
      <label class="form-label"><?= h(__('t_f53c7c0b21', 'وصف الميتا (Meta Description)')) ?></label>
      <textarea name="meta_description" rows="2" class="form-control"><?= htmlspecialchars($values['meta_description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
    </div>

    <div class="d-flex justify-content-between align-items-center">
      <a href="index.php" class="btn btn-outline-secondary btn-sm">
        <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_b6a95f6cdd', 'رجوع للقائمة')) ?>
      </a>
      <button type="submit" class="btn btn-primary btn-sm">
        <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
        <?= $mode === 'edit' ? __('t_02f31ae27c', 'حفظ التغييرات') : __('t_1c7c16fd30', 'حفظ الصفحة') ?>
      </button>
    </div>
  </div>
</div>
