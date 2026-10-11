CREATE TABLE IF NOT EXISTS support_tickets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reference CHAR(24) NOT NULL UNIQUE,
 user_id BIGINT UNSIGNED NULL,
 name VARCHAR(120) NOT NULL,
 email VARCHAR(190) NOT NULL,
 subject VARCHAR(160) NOT NULL,
 message TEXT NOT NULL,
 status ENUM('open','in_progress','resolved') NOT NULL DEFAULT 'open',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX support_status_created(status,created_at),
 CONSTRAINT support_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS sample_content (
 entity_type ENUM('service','employee') NOT NULL,
 seed_key VARCHAR(60) NOT NULL,
 entity_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(entity_type,seed_key),
 UNIQUE KEY sample_entity(entity_type,entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
