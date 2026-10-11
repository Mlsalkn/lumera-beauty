-- Fresh empty database only. Existing tables must be inspected before migrations.
-- Intentionally no IF NOT EXISTS: conflicts must stop instead of masking schema differences.
CREATE TABLE users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 phone VARCHAR(25) NULL,
 role ENUM('CUSTOMER','EMPLOYEE','ADMIN') NOT NULL DEFAULT 'CUSTOMER',
 is_active BOOLEAN NOT NULL DEFAULT TRUE,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE employees (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL UNIQUE,
 title VARCHAR(120) NOT NULL,
 bio TEXT NULL,
 photo_path VARCHAR(255) NULL,
 is_active BOOLEAN NOT NULL DEFAULT TRUE,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE services (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL,
 description TEXT NULL,
 category VARCHAR(100) NULL,
 image_path VARCHAR(255) NULL,
 duration_minutes SMALLINT UNSIGNED NOT NULL,
 price DECIMAL(10,2) NOT NULL,
 is_active BOOLEAN NOT NULL DEFAULT TRUE,
 CHECK (duration_minutes > 0), CHECK (price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE employee_services (
 employee_id BIGINT UNSIGNED NOT NULL,
 service_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY (employee_id, service_id),
 FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE RESTRICT,
 FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE working_hours (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 employee_id BIGINT UNSIGNED NOT NULL,
 weekday TINYINT UNSIGNED NOT NULL,
 start_time TIME NOT NULL,
 end_time TIME NOT NULL,
 UNIQUE KEY schedule (employee_id, weekday, start_time),
 FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE RESTRICT,
 CHECK (weekday BETWEEN 1 AND 7), CHECK (end_time > start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE appointments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL,
 employee_id BIGINT UNSIGNED NOT NULL,
 service_id BIGINT UNSIGNED NOT NULL,
 start_at DATETIME NOT NULL,
 end_at DATETIME NOT NULL,
 status ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
 service_price DECIMAL(10,2) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX availability (employee_id, status, start_at, end_at),
 INDEX customer_history (customer_id, start_at),
 FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE RESTRICT,
 FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT,
 CHECK (end_at > start_at), CHECK (service_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE reviews (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 appointment_id BIGINT UNSIGNED NOT NULL UNIQUE,
 customer_id BIGINT UNSIGNED NOT NULL,
 rating TINYINT UNSIGNED NOT NULL,
 comment TEXT NULL,
 is_visible BOOLEAN NOT NULL DEFAULT TRUE,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE RESTRICT,
 FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE RESTRICT,
 CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE employee_leaves (
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
