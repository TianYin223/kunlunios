-- 为已部署系统新增 APP API Token 表
-- 执行方式：在当前业务数据库中直接执行本脚本

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `api_tokens` (
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

