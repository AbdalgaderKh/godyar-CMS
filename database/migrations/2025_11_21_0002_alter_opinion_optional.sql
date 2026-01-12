-- تأكد من وجود بعض الحقول النموذجية في جدول opinion (تنفيذ يدوي حسب الحاجة)
-- يرجى مراجعة هذا السكربت قبل تشغيله في بيئة الإنتاج.

-- ALTER TABLE opinion ADD COLUMN content LONGTEXT NULL AFTER title;
-- ALTER TABLE opinion ADD COLUMN author_slug VARCHAR(190) NULL AFTER author_name;
-- ALTER TABLE opinion ADD COLUMN views INT UNSIGNED NOT NULL DEFAULT 0 AFTER status;
