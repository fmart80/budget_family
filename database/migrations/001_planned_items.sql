-- Postes prévus : revenus et dépenses récurrents ou ponctuels servant aux prévisions et au lissage.
CREATE TABLE IF NOT EXISTS planned_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    type ENUM('income','expense') NOT NULL DEFAULT 'expense',
    amount DECIMAL(12,2) NOT NULL,
    months VARCHAR(40) NOT NULL DEFAULT '1,2,3,4,5,6,7,8,9,10,11,12',
    day TINYINT UNSIGNED NOT NULL DEFAULT 1,
    category_id INT UNSIGNED NULL,
    start_month CHAR(7) NULL,
    end_month CHAR(7) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
