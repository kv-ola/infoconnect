CREATE TABLE IF NOT EXISTS `support_requests` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `requested_by` int(10) UNSIGNED NOT NULL,
  `subject` varchar(150) NOT NULL,
  `concern_type` varchar(40) NOT NULL,
  `message` text NOT NULL,
  `status` enum('open','in_progress','resolved') NOT NULL DEFAULT 'open',
  `handled_by` int(10) UNSIGNED DEFAULT NULL,
  `handled_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_support_requests_requester` (`requested_by`),
  KEY `idx_support_requests_status_created` (`status`,`created_at`),
  CONSTRAINT `fk_support_requests_requester` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_support_requests_handler` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
