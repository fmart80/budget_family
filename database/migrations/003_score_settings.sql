-- Réglages de l'application (profil fiscal, plafond 3a…) sous forme clé / valeur.
CREATE TABLE IF NOT EXISTS settings (
    name VARCHAR(100) PRIMARY KEY,
    value TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historique mensuel du score financier (dernier calcul du mois).
CREATE TABLE IF NOT EXISTS score_history (
    month CHAR(7) PRIMARY KEY,
    score TINYINT UNSIGNED NOT NULL,
    details TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
