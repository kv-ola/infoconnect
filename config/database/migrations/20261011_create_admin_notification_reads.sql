CREATE TABLE IF NOT EXISTS `admin_notification_reads` (
  `admin_user_id` int(10) unsigned NOT NULL,
  `notification_key` varchar(100) NOT NULL,
  `read_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`admin_user_id`, `notification_key`),
  KEY `idx_admin_notification_reads_read_at` (`admin_user_id`, `read_at`),
  CONSTRAINT `fk_admin_notification_reads_user` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
