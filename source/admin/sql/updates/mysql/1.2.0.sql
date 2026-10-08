CREATE TABLE IF NOT EXISTS `#__newsletterbridge_presets` (
 `article_id` int unsigned NOT NULL,
 `name` varchar(255) NOT NULL,
 `builtin_id` varchar(64) DEFAULT NULL,
 `subject` varchar(255) NOT NULL,
 PRIMARY KEY (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
