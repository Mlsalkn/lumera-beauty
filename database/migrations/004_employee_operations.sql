CREATE TABLE IF NOT EXISTS employee_permissions (
 employee_id BIGINT UNSIGNED PRIMARY KEY,
 can_confirm BOOLEAN NOT NULL DEFAULT FALSE,
 can_complete BOOLEAN NOT NULL DEFAULT TRUE,
 can_request_cancel BOOLEAN NOT NULL DEFAULT TRUE,
 FOREIGN KEY(employee_id) REFERENCES employees(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS employee_breaks (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 employee_id BIGINT UNSIGNED NOT NULL,
 weekday TINYINT UNSIGNED NOT NULL,
 start_time TIME NOT NULL,
 end_time TIME NOT NULL,
 reason VARCHAR(120) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(employee_id) REFERENCES employees(id) ON DELETE RESTRICT,
 UNIQUE KEY uq_employee_break(employee_id,weekday,start_time),
 CHECK(weekday BETWEEN 1 AND 7),CHECK(end_time>start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS employee_leave_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 employee_id BIGINT UNSIGNED NOT NULL,
 start_at DATETIME NOT NULL,
 end_at DATETIME NOT NULL,
 reason VARCHAR(1000) NOT NULL,
 status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
 reviewer_id BIGINT UNSIGNED NULL,
 reviewed_at DATETIME NULL,
 response VARCHAR(1000) NULL,
 approved_leave_id BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(employee_id) REFERENCES employees(id) ON DELETE RESTRICT,
 FOREIGN KEY(reviewer_id) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY(approved_leave_id) REFERENCES employee_leaves(id) ON DELETE SET NULL,
 INDEX ix_leave_requests(employee_id,status,start_at),CHECK(end_at>start_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS appointment_cancellation_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 appointment_id BIGINT UNSIGNED NOT NULL,
 employee_id BIGINT UNSIGNED NOT NULL,
 reason VARCHAR(1000) NOT NULL,
 status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
 reviewer_id BIGINT UNSIGNED NULL,
 reviewed_at DATETIME NULL,
 response VARCHAR(1000) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(appointment_id) REFERENCES appointments(id) ON DELETE RESTRICT,
 FOREIGN KEY(employee_id) REFERENCES employees(id) ON DELETE RESTRICT,
 FOREIGN KEY(reviewer_id) REFERENCES users(id) ON DELETE RESTRICT,
 INDEX ix_cancellation_requests(employee_id,status,appointment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
