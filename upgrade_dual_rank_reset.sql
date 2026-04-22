-- 双榜（周榜/月榜）升级脚本
-- 适用于 MySQL 8.x
-- 说明：可重复执行，不会因字段已存在而中断

SET NAMES utf8mb4;

-- 1) 宿舍表新增月分数字段
SET @has_monthly_score := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'dormitories'
      AND COLUMN_NAME = 'monthly_score'
);
SET @sql_add_monthly_score := IF(
    @has_monthly_score = 0,
    'ALTER TABLE `dormitories` ADD COLUMN `monthly_score` DECIMAL(10,2) NOT NULL DEFAULT 100.00 COMMENT ''当前月总分'' AFTER `score`',
    'SELECT 1'
);
PREPARE stmt_add_monthly_score FROM @sql_add_monthly_score;
EXECUTE stmt_add_monthly_score;
DEALLOCATE PREPARE stmt_add_monthly_score;

-- 2) 月分数初始化为当前周分数（仅首次升级时有意义）
UPDATE `dormitories` SET `monthly_score` = `score` WHERE `monthly_score` IS NULL;

-- 3) 月分数字段索引
SET @has_monthly_score_idx := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'dormitories'
      AND INDEX_NAME = 'idx_monthly_score'
);
SET @sql_add_monthly_score_idx := IF(
    @has_monthly_score_idx = 0,
    'ALTER TABLE `dormitories` ADD KEY `idx_monthly_score` (`monthly_score`)',
    'SELECT 1'
);
PREPARE stmt_add_monthly_score_idx FROM @sql_add_monthly_score_idx;
EXECUTE stmt_add_monthly_score_idx;
DEALLOCATE PREPARE stmt_add_monthly_score_idx;

-- 4) 补齐周/月周期设置键
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`)
VALUES ('current_week', DATE_FORMAT(NOW(), '%x-W%v'), '当前计分周次')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

INSERT INTO `settings` (`setting_key`, `setting_value`, `description`)
VALUES ('current_month', DATE_FORMAT(NOW(), '%Y-%m'), '当前计分月份')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- 5) 如果 current_month 被旧版本写成周次，自动修正为当前月份
UPDATE `settings`
SET `setting_value` = DATE_FORMAT(NOW(), '%Y-%m')
WHERE `setting_key` = 'current_month'
  AND (`setting_value` IS NULL OR `setting_value` = '' OR `setting_value` REGEXP '^\\d{4}-W(0[1-9]|[1-4][0-9]|5[0-3])$');

-- 6) 如果 current_week 无效，自动修正为当前周次
UPDATE `settings`
SET `setting_value` = DATE_FORMAT(NOW(), '%x-W%v')
WHERE `setting_key` = 'current_week'
  AND (`setting_value` IS NULL OR `setting_value` = '' OR `setting_value` NOT REGEXP '^\\d{4}-W(0[1-9]|[1-4][0-9]|5[0-3])$');

-- 7) 保证周总分上限键存在（兼容旧版）
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`)
VALUES ('weekly_max_score', '100', '每周每个宿舍总分上限')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- 8) 减分可选分值
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`)
VALUES ('score_option_values', '0.5,1,1.5,2', '减分可选分值')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);
