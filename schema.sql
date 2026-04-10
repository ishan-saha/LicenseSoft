CREATE DATABASE IF NOT EXISTS licensesoft CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE licensesoft;

CREATE TABLE admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE tools (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    aes_key VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(255) NOT NULL,
    organisation_name VARCHAR(255) NOT NULL,
    org_domain VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE licenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    license_key CHAR(40) NOT NULL UNIQUE,
    installation_id VARCHAR(255) DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    status ENUM('active', 'revoked') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    INDEX idx_license_key (license_key)
);

CREATE TABLE license_tools (
    id INT PRIMARY KEY AUTO_INCREMENT,
    license_id INT NOT NULL,
    tool_id INT NOT NULL,
    FOREIGN KEY (license_id) REFERENCES licenses(id) ON DELETE CASCADE,
    FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE,
    UNIQUE KEY unique_license_tool (license_id, tool_id)
);

CREATE TABLE rate_limits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ip VARCHAR(45) NOT NULL,
    request_count INT NOT NULL DEFAULT 0,
    window_start DATETIME NOT NULL,
    INDEX idx_ip (ip)
);

CREATE TABLE login_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ip VARCHAR(45) NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    locked_until DATETIME DEFAULT NULL,
    last_attempt DATETIME NOT NULL,
    INDEX idx_ip (ip)
);

CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    license_id INT NOT NULL,
    installation_id VARCHAR(255) NOT NULL,
    tool_slug VARCHAR(100) NOT NULL,
    action ENUM('activated', 'verified', 'failed', 'revoked') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (license_id) REFERENCES licenses(id) ON DELETE CASCADE
);

-- Default admin: username=admin, password=admin (CHANGE IMMEDIATELY after first login)
-- Generate a proper hash on your server: php -r "echo password_hash('YourPassword', PASSWORD_BCRYPT);"
-- Then run: INSERT INTO admins (username, password_hash) VALUES ('admin', '<hash>');
