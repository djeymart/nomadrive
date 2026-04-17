-- ─────────────────────────────────────────────────────────────────────────────
-- Table : nomadrive_vehicules
-- Flotte de véhicules disponibles à la location
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `nomadrive_vehicules` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `immatriculation` VARCHAR(20)   NOT NULL,
    `marque`          VARCHAR(50)   NOT NULL,
    `modele`          VARCHAR(50)   NOT NULL,
    `couleur`         VARCHAR(10)   DEFAULT NULL,
    `licence_key`     VARCHAR(16)   DEFAULT NULL COMMENT 'Clé de licence tablette (hex 64-bit, SHA-256 tronqué)',
    `actif`           TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_immat`         (`immatriculation`),
    UNIQUE KEY `uq_licence_key`   (`licence_key`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- Données initiales
-- ─────────────────────────────────────────────────────────────────────────────

INSERT INTO `nomadrive_vehicules` (`id`, `immatriculation`, `marque`, `modele`, `couleur`, `licence_key`) VALUES
(1,  'HH-327-ZZ',  'CITROEN', 'AMI',      '#F54927', 'f8918b8dcacbe72d'),
(2,  'HH-339-ZZ',  'CITROEN', 'AMI',      '#F54927', '62d2d3e5ba729360'),
(3,  'HH-348-ZZ',  'CITROEN', 'AMI',      '#F54927', '728cf6a989a0ab40'),
(4,  'HH-358-ZZ',  'CITROEN', 'AMI',      '#F54927', '7b0de5675a16ca85'),
(5,  'HH-303-ZZ',  'CITROEN', 'AMI',      '#F54927', '2c6a2f1fce631243'),
(6,  'HF-735-CQ',  'FIAT',    'TOPOLINO', '#219411', '87d11699802119ca'),
(7,  'HH-469-YT',  'FIAT',    'TOPOLINO', '#219411', '684b062f76b68ae6'),
(8,  'HH-250-YV',  'FIAT',    'TOPOLINO', '#219411', 'b742852661d7c48d'),
(9,  'HH-108-YV',  'FIAT',    'TOPOLINO', '#219411', 'afdbb1a9d9774ef0'),
(10, 'HH-902-YQ',  'FIAT',    'TOPOLINO', '#219411', '1fc6f289bbbd121c');
