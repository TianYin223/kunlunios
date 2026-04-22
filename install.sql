-- 学生管理系统数据库
-- MySQL 8.x + PHP 8.x
-- 注意：请先手动创建数据库（名称需与 config/config.php 中 DB_NAME 一致），然后选择该数据库后再导入此文件

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 用户表
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL COMMENT '用户名',
  `password` VARCHAR(255) NOT NULL COMMENT '密码(bcrypt)',
  `real_name` VARCHAR(50) NOT NULL COMMENT '真实姓名',
  `role` ENUM('admin', 'inspector') NOT NULL DEFAULT 'inspector' COMMENT '角色：管理员/检查人',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态：1启用 0禁用',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- APP 登录令牌表
DROP TABLE IF EXISTS `api_tokens`;
CREATE TABLE `api_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL COMMENT '用户ID',
  `token_hash` CHAR(64) NOT NULL COMMENT 'Token哈希',
  `device_name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '设备名称',
  `last_used_at` DATETIME NULL COMMENT '最后使用时间',
  `expires_at` DATETIME NOT NULL COMMENT '过期时间',
  `revoked_at` DATETIME NULL COMMENT '撤销时间',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token_hash` (`token_hash`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_revoked_at` (`revoked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='APP API Token';

-- 宿舍表
DROP TABLE IF EXISTS `dormitories`;
CREATE TABLE `dormitories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dormitory_no` VARCHAR(20) NOT NULL COMMENT '宿舍号',
  `score` DECIMAL(10,2) NOT NULL DEFAULT 100.00 COMMENT '当前周总分',
  `monthly_score` DECIMAL(10,2) NOT NULL DEFAULT 100.00 COMMENT '当前月总分',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态：1启用 0禁用',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_monthly_score` (`monthly_score`),
  UNIQUE KEY `uk_dormitory_no` (`dormitory_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='宿舍表';

-- 打分记录表
DROP TABLE IF EXISTS `score_records`;
CREATE TABLE `score_records` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dormitory_id` INT UNSIGNED NOT NULL COMMENT '宿舍ID',
  `dormitory_no` VARCHAR(20) NOT NULL COMMENT '宿舍号(冗余)',
  `inspector_id` INT UNSIGNED NOT NULL COMMENT '检查人ID',
  `inspector_name` VARCHAR(50) NOT NULL COMMENT '检查人姓名(冗余)',
  `score_type` ENUM('add', 'subtract') NOT NULL COMMENT '加分/减分',
  `score` DECIMAL(10,2) NOT NULL COMMENT '分数',
  `reason` VARCHAR(500) NOT NULL COMMENT '原因',
  `images_json` TEXT NULL COMMENT '上传图片JSON数组',
  `month_year` VARCHAR(8) NOT NULL COMMENT '周期标识：周次YYYY-Www 或 月份YYYY-MM',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dormitory_id` (`dormitory_id`),
  KEY `idx_inspector_id` (`inspector_id`),
  KEY `idx_month_year` (`month_year`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='打分记录表';

-- 原因选项表
DROP TABLE IF EXISTS `reason_options`;
CREATE TABLE `reason_options` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reason_text` VARCHAR(200) NOT NULL COMMENT '原因文本',
  `score_type` ENUM('add', 'subtract', 'both') NOT NULL DEFAULT 'both' COMMENT '适用类型',
  `sort_order` INT NOT NULL DEFAULT 0 COMMENT '排序',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态：1启用 0禁用',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='原因选项表';

-- 系统设置表
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(50) NOT NULL COMMENT '设置键',
  `setting_value` TEXT COMMENT '设置值',
  `description` VARCHAR(200) COMMENT '描述',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统设置表';

-- 历史排行榜表
DROP TABLE IF EXISTS `history_rankings`;
CREATE TABLE `history_rankings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `month_year` VARCHAR(8) NOT NULL COMMENT '周期标识：周次YYYY-Www 或 月份YYYY-MM',
  `dormitory_id` INT UNSIGNED NOT NULL COMMENT '宿舍ID',
  `dormitory_no` VARCHAR(20) NOT NULL COMMENT '宿舍号',
  `final_score` DECIMAL(10,2) NOT NULL COMMENT '最终分数',
  `ranking` INT UNSIGNED NOT NULL COMMENT '排名',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_month_dormitory` (`month_year`, `dormitory_id`),
  KEY `idx_month_year` (`month_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='历史排行榜表';

-- 插入默认系统设置
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('site_name', '学生管理系统', '网站名称/网站昵称'),
('weekly_max_score', '100', '每周每个宿舍总分上限'),
('monthly_max_score', '100', '兼容旧版：每周每个宿舍总分上限'),
('fixed_score', '0', '固定分数值，0表示不固定'),
('score_option_values', '0.5,1,1.5,2', '减分可选分值'),
('daily_limit', '3', '每个宿舍每天打分次数限制'),
('current_week', DATE_FORMAT(NOW(), '%x-W%v'), '当前计分周次'),
('current_month', DATE_FORMAT(NOW(), '%Y-%m'), '当前计分月份');

-- 插入默认管理员账号
-- 密码: admin123 (请登录后立即修改)
INSERT INTO `users` (`username`, `password`, `real_name`, `role`) VALUES
('admin', '$2y$10$qEK3bIfBZMLJhGRVLy4LVuZBvZxm9wkYkjM09C5WSo/DSgcQvTIwW', '系统管理员', 'admin');

-- 插入默认原因选项
INSERT INTO `reason_options` (`reason_text`, `score_type`, `sort_order`) VALUES
('地面不干净', 'subtract', 1),
('垃圾未倒', 'subtract', 2),
('物品摆放混乱', 'subtract', 3),
('被子未叠', 'subtract', 4),
('窗户未擦', 'subtract', 5),
('整体整洁', 'add', 6),
('表现优秀', 'add', 7);

-- 审计日志表
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NULL COMMENT '操作用户ID',
  `username` VARCHAR(50) NULL COMMENT '用户名',
  `action` VARCHAR(50) NOT NULL COMMENT '操作类型',
  `target_type` VARCHAR(50) NOT NULL COMMENT '目标类型',
  `target_id` INT UNSIGNED NULL COMMENT '目标ID',
  `details` TEXT NULL COMMENT '详细信息',
  `ip_address` VARCHAR(45) NOT NULL COMMENT 'IP地址',
  `user_agent` VARCHAR(255) NULL COMMENT '用户代理',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='审计日志表';

SET FOREIGN_KEY_CHECKS = 1;
