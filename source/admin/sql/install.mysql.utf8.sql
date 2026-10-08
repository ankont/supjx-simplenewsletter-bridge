CREATE TABLE IF NOT EXISTS `#__newsletterbridge_archive` (
 `job_id` int unsigned NOT NULL,
 `title` varchar(255) NOT NULL,
 `subject` varchar(255) NOT NULL,
 `body` mediumtext NOT NULL,
 `published` tinyint NOT NULL DEFAULT 0,
 `revision` int unsigned NOT NULL DEFAULT 1,
 `modified` datetime NOT NULL,
 `modified_by` int unsigned NOT NULL,
 `published_at` datetime DEFAULT NULL,
 PRIMARY KEY (`job_id`), KEY `published_date` (`published`, `published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__newsletterbridge_presets` (
 `article_id` int unsigned NOT NULL,
 `name` varchar(255) NOT NULL,
 `builtin_id` varchar(64) DEFAULT NULL,
 `subject` varchar(255) NOT NULL,
 PRIMARY KEY (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
