-- إضافة دور الكاتب (writer) / المؤلف (author) لعمود role
-- نفّذ هذا الملف مرة واحدة في قاعدة بياناتك

ALTER TABLE `users`
  MODIFY `role` ENUM('admin','editor','writer','author','user') NOT NULL DEFAULT 'user';

-- (اختياري) لو عندك كتّاب حالياً مسجلين كـ user وتبغى تحويلهم:
-- UPDATE `users` SET `role`='writer' WHERE `role`='user' AND `email` IN ('writer@example.com');
