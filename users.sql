
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'user'
);

INSERT INTO users (username, password, role) VALUES
('admin', '$2y$10$nOUIs5kJ7naTuTFkBy1/eeFJWr29Zhc4qshNq3Le.Q1orY0c88q/e', 'admin')
ON DUPLICATE KEY UPDATE username=username;
-- Password is: admin123
