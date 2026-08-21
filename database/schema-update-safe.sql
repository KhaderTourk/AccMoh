-- =============================================================================
-- تحديث هيكل قاعدة البيانات - آمن للاستيراد على الاستضافة
-- يضيف الجداول والأعمدة الجديدة فقط دون تعديل أو حذف البيانات الموجودة
-- =============================================================================
-- طريقة الاستخدام:
-- 1) الموصى به: بعد رفع الكود، نفّذ على السيرفر: php artisan migrate
--
-- 2) استيراد هذا الملف يدوياً:
--    من سطر الأوامر (استخدم -f للمتابعة عند الأخطاء مثل العمود المكرر):
--    mysql -f -u USER -p DATABASE_NAME < database/schema-update-safe.sql
--
--    أو من phpMyAdmin: انسخ والصق الأقسام واحداً واحداً، وتخطَّ أي بيان يعطي "Duplicate column"
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ---------- جدول الأدوار ----------
CREATE TABLE IF NOT EXISTS `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- جدول الصلاحيات ----------
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `module` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- جدول ربط الأدوار بالصلاحيات ----------
CREATE TABLE IF NOT EXISTS `role_permission` (
  `role_id` bigint unsigned NOT NULL,
  `permission_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `role_permission_permission_id_foreign` (`permission_id`),
  CONSTRAINT `role_permission_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permission_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- أعمدة جديدة لجدول المستخدمين (تُضاف فقط إن لم تكن موجودة) ----------
SET @db = DATABASE();

SET @q = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='users' AND COLUMN_NAME='role_id')=0,
  'ALTER TABLE users ADD COLUMN role_id bigint unsigned NULL AFTER id', 'SELECT 1');
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @q = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='users' AND COLUMN_NAME='is_super_admin')=0,
  'ALTER TABLE users ADD COLUMN is_super_admin tinyint(1) NOT NULL DEFAULT 0 AFTER role_id', 'SELECT 1');
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @q = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='users' AND COLUMN_NAME='is_active')=0,
  'ALTER TABLE users ADD COLUMN is_active tinyint(1) NOT NULL DEFAULT 1 AFTER is_super_admin', 'SELECT 1');
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ربط role_id بجدول roles (فقط إن لم يُضَف المفتاح مسبقاً)
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=@db AND TABLE_NAME='users' AND CONSTRAINT_NAME='users_role_id_foreign');
SET @q = IF(@fk_exists=0, 'ALTER TABLE users ADD CONSTRAINT users_role_id_foreign FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- جدول التبرعات ----------
CREATE TABLE IF NOT EXISTS `donations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `donor_name` varchar(255) NOT NULL,
  `donor_email` varchar(255) DEFAULT NULL,
  `donor_phone` varchar(255) DEFAULT NULL,
  `donor_type` varchar(255) NOT NULL DEFAULT 'individual',
  `amount` decimal(15,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'ILS',
  `donation_method` varchar(255) NOT NULL,
  `donation_date` date NOT NULL,
  `reference_number` varchar(255) DEFAULT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `purpose_id` bigint unsigned DEFAULT NULL,
  `notes` text,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `rejection_reason` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `donations_reviewed_by_foreign` (`reviewed_by`),
  CONSTRAINT `donations_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- جدول الحركات المالية ----------
CREATE TABLE IF NOT EXISTS `financial_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'ILS',
  `transaction_date` date NOT NULL,
  `beneficiary_name` varchar(255) NOT NULL,
  `beneficiary_type` varchar(255) NOT NULL DEFAULT 'other',
  `purpose` text NOT NULL,
  `reference_type` varchar(255) DEFAULT NULL,
  `reference_id` bigint unsigned DEFAULT NULL,
  `payment_method` varchar(255) NOT NULL,
  `bank_reference` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `rejection_reason` text,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `financial_transactions_approved_by_foreign` (`approved_by`),
  KEY `financial_transactions_created_by_foreign` (`created_by`),
  CONSTRAINT `financial_transactions_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `financial_transactions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- جدول سجل التدقيق المالي ----------
CREATE TABLE IF NOT EXISTS `financial_audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `financial_audit_logs_user_id_foreign` (`user_id`),
  CONSTRAINT `financial_audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
