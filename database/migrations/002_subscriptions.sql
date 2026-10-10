-- Abonnements (streaming, téléphonie, logiciels, fitness…), avec moyen de paiement et suivi des prix.
CREATE TABLE IF NOT EXISTS subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    cycle ENUM('monthly','quarterly','semiannual','yearly') NOT NULL DEFAULT 'monthly',
    anchor_date DATE NOT NULL,
    account_id INT UNSIGNED NULL,
    category_id INT UNSIGNED NULL,
    keyword VARCHAR(100) NULL,
    owner VARCHAR(100) NULL,
    url VARCHAR(255) NULL,
    notes VARCHAR(255) NULL,
    status ENUM('active','paused','cancelled') NOT NULL DEFAULT 'active',
    cancelled_at DATE NULL,
    previous_amount DECIMAL(10,2) NULL,
    amount_changed_at DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO categories (name, type, color) VALUES ('Abonnements & streaming', 'expense', '#e4606d')
;

-- Suggestions de détection écartées par l'utilisateur (« ce n'est pas un abonnement »).
CREATE TABLE IF NOT EXISTS subscription_ignored (
    keyword VARCHAR(100) PRIMARY KEY,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
