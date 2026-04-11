-- ─────────────────────────────────────────────────────────────────────────────
-- Table : nomadrive_contrats
-- Stocke les contrats de location signés électroniquement
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `nomadrive_contrats` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Locataire
    `nom`           VARCHAR(100)  NOT NULL,
    `prenom`        VARCHAR(100)  NOT NULL,
    `email`         VARCHAR(255)  NOT NULL,
    `adresse`       VARCHAR(500)  DEFAULT NULL,

    -- Véhicule
    `vehicule_id`   INT UNSIGNED  DEFAULT NULL,
    `vehicule`      VARCHAR(200)  DEFAULT NULL,   -- libellé dénormalisé (snapshot au moment du contrat)
    `date_debut`         DATE         DEFAULT NULL,
    `heure_debut`        TIME         DEFAULT NULL,
    `dossier_empreinte`  VARCHAR(100) DEFAULT NULL,

    -- Documents
    `signature`          LONGTEXT      NOT NULL,               -- base64 PNG (signature manuscrite)
    `url_contrat_pdf`    VARCHAR(500)  DEFAULT NULL,           -- GCP : madi_bucket/nomadrive/contrats/ND-XXXXX.pdf
    `url_permis_recto`   VARCHAR(500)  DEFAULT NULL,           -- GCP : madi_bucket/nomadrive/permis/ND-XXXXX-recto.jpg
    `url_permis_verso`   VARCHAR(500)  DEFAULT NULL,           -- GCP : madi_bucket/nomadrive/permis/ND-XXXXX-verso.jpg

    PRIMARY KEY (`id`),
    INDEX `idx_email`       (`email`),
    INDEX `idx_created_at`  (`created_at`),
    INDEX `idx_vehicule_id` (`vehicule_id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
