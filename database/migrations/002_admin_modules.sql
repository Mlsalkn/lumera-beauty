-- Additive migration. The runner checks image_path before applying ALTER.
ALTER TABLE services ADD COLUMN image_path VARCHAR(255) NULL;
CREATE TABLE IF NOT EXISTS employee_leaves (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 employee_id BIGINT UNSIGNED NOT NULL,
 start_at DATETIME NOT NULL,
 end_at DATETIME NOT NULL,
 reason VARCHAR(255) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX leave_availability (employee_id, start_at, end_at),
 FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE RESTRICT,
 CHECK (end_at > start_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
