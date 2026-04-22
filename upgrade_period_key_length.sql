-- 周期标识长度升级脚本
-- 目的：兼容周次格式 YYYY-Www（长度 8）
-- 执行方式：在当前业务数据库中直接执行本脚本
SET NAMES utf8mb4;

SET @has_score_records_month_year := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'score_records'
      AND COLUMN_NAME = 'month_year'
);

SET @score_records_month_year_len := (
    SELECT IFNULL(CHARACTER_MAXIMUM_LENGTH, 0)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'score_records'
      AND COLUMN_NAME = 'month_year'
    LIMIT 1
);

SET @sql_alter_score_records := IF(
    @has_score_records_month_year = 1 AND @score_records_month_year_len < 8,
    'ALTER TABLE `score_records` MODIFY COLUMN `month_year` VARCHAR(8) NOT NULL COMMENT ''周期标识：周次YYYY-Www 或 月份YYYY-MM''',
    'SELECT 1'
);

PREPARE stmt_alter_score_records FROM @sql_alter_score_records;
EXECUTE stmt_alter_score_records;
DEALLOCATE PREPARE stmt_alter_score_records;

SET @has_history_rankings_month_year := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'history_rankings'
      AND COLUMN_NAME = 'month_year'
);

SET @history_rankings_month_year_len := (
    SELECT IFNULL(CHARACTER_MAXIMUM_LENGTH, 0)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'history_rankings'
      AND COLUMN_NAME = 'month_year'
    LIMIT 1
);

SET @sql_alter_history_rankings := IF(
    @has_history_rankings_month_year = 1 AND @history_rankings_month_year_len < 8,
    'ALTER TABLE `history_rankings` MODIFY COLUMN `month_year` VARCHAR(8) NOT NULL COMMENT ''周期标识：周次YYYY-Www 或 月份YYYY-MM''',
    'SELECT 1'
);

PREPARE stmt_alter_history_rankings FROM @sql_alter_history_rankings;
EXECUTE stmt_alter_history_rankings;
DEALLOCATE PREPARE stmt_alter_history_rankings;
