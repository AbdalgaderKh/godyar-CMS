-- Admin menu dynamic table (optional)
-- إذا لم تنشئ هذا الجدول سيعمل السايدبار بالوضع الثابت.
CREATE TABLE IF NOT EXISTS admin_menu (
  id INT AUTO_INCREMENT PRIMARY KEY,
  section VARCHAR(100) NOT NULL,
  label VARCHAR(150) NOT NULL,
  sub_label VARCHAR(255) NULL,
  href VARCHAR(255) NOT NULL,
  icon VARCHAR(50) NULL,
  perm VARCHAR(100) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- أمثلة عناصر (عدّلها كما تريد)
INSERT INTO admin_menu (section,label,sub_label,href,icon,perm,sort_order,is_active) VALUES
('نظرة عامة','الرئيسية','نظرة عامة على أداء النظام','index.php','house','',10,1),
('المحتوى','الأخبار','إدارة الأخبار والمقالات','news/index.php','newspaper','posts.view',10,1),
('المحتوى','التصنيفات','إدارة التصنيفات والأقسام','categories/index.php','layer-group','categories.view',20,1),
('المحتوى','الوسوم','إدارة وسوم الأخبار','tags/index.php','hashtag','categories.view',30,1),
('المحتوى','الإعلانات','إدارة أماكن الإعلانات','ads/index.php','bullhorn','ads.manage',40,1),
('المحتوى','القاموس','إدارة المصطلحات','glossary/index.php','book','glossary.manage',50,1),
('المحتوى','كتاب الرأي','إدارة كتّاب الرأي','opinion_authors/index.php','pen-fancy','opinion_authors.manage',60,1),
('الإدارة','فريق العمل','إدارة صفحة فريق العمل','team/index.php','users','team.manage',10,1),
('الإدارة','رسائل التواصل','قراءة وإدارة رسائل الموقع','contact/index.php','envelope','contact.manage',20,1),
('الإدارة','المستخدمون','إدارة الحسابات والصلاحيات','users/index.php','user-gear','manage_users',30,1),
('الإدارة','الأدوار','صلاحيات النظام','roles/index.php','user-shield','manage_roles',40,1),
('الإدارة','الإعدادات','إعدادات النظام','settings/index.php','gear','manage_settings',90,1);


-- عناصر إضافية لميزات v8 (ترجمات/استطلاعات/أسئلة)
INSERT INTO admin_menu (section,label,sub_label,href,icon,perm,sort_order,is_active) VALUES
('المحتوى','ترجمات الأخبار','إدارة نسخ اللغات للمقالات','news/translations.php','language','posts.view',12,1),
('المحتوى','استطلاعات الأخبار','إنشاء وإدارة استطلاعات داخل المقال','news/polls.php','square-poll-vertical','posts.edit',13,1),
('المحتوى','أسئلة القرّاء','مراجعة أسئلة اسأل الكاتب والرد عليها','news/questions.php','circle-question','posts.view',14,1);
