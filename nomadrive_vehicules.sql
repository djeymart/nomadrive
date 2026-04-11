-- ─────────────────────────────────────────────────────────────────────────────
-- Table : nomadrive_vehicules
-- Flotte de véhicules disponibles à la location
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `nomadrive_vehicules` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `immatriculation` VARCHAR(20)  NOT NULL,
    `marque`         VARCHAR(50)   NOT NULL,
    `modele`         VARCHAR(50)   NOT NULL,
    `couleur`        VARCHAR(10)   DEFAULT NULL,
    `actif`          TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_immat` (`immatriculation`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- Données initiales
-- ─────────────────────────────────────────────────────────────────────────────

INSERT INTO `nomadrive_vehicules` (`id`, `immatriculation`, `marque`, `modele`, `couleur`) VALUES
(1, 'HF-735-CQ', 'FIAT',    'TOPOLINO', '#219411'),
(2, 'HH-250-YV', 'FIAT',    'TOPOLINO', '#219411'),
(3, 'HH-469-YT', 'FIAT',    'TOPOLINO', '#219411'),
(4, 'HH-303-ZZ', 'CITROEN', 'AMI',      '#F54927'),
(5, 'HH-358-ZZ', 'CITROEN', 'AMI',      '#F54927'),
(6, 'HH-108-YV', 'FIAT',    'TOPOLINO', '#219411'),
(7, 'HH-348-ZZ', 'CITROEN', 'AMI',      '#F54927'),
(8, 'HH-339-ZZ', 'CITROEN', 'AMI',      '#F54927'),
(9, 'HH-902-YQ', 'FIAT',    'TOPOLINO', '#219411'),
(10, 'HH-327-ZZ', 'CITROEN', 'AMI',      '#F54927');
