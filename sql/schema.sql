CREATE TABLE IF NOT EXISTS `support_tickets` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `ticket_id` char(6) NOT NULL,
    `email` varchar(255) NOT NULL,
    `name` varchar(150) NOT NULL,
    `type` varchar(32) NOT NULL DEFAULT 'general',
    `subject` varchar(255) NOT NULL,
    `description` text NOT NULL,
    `status` varchar(32) NOT NULL DEFAULT 'open',
    `tier` varchar(16) NOT NULL DEFAULT 't1',
    `assigned_to` varchar(100) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    `closed_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `support_tickets_ticket_id_unique` (`ticket_id`),
    KEY `support_tickets_status_index` (`status`),
    KEY `support_tickets_tier_index` (`tier`),
    KEY `support_tickets_email_index` (`email`),
    KEY `support_tickets_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `support_replies` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `ticket_id` bigint unsigned NOT NULL,
    `author` varchar(150) NOT NULL,
    `message` text NOT NULL,
    `is_internal` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `support_replies_ticket_index` (`ticket_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `support_events` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `ticket_id` bigint unsigned NOT NULL,
    `event_type` varchar(32) NOT NULL,
    `from_value` varchar(255) DEFAULT NULL,
    `to_value` varchar(255) DEFAULT NULL,
    `actor` varchar(150) DEFAULT NULL,
    `note` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `support_events_ticket_index` (`ticket_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
