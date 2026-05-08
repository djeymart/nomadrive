-- ═══════════════════════════════════════════════════════════
--  MIGRATION NOMADRIVE — Tables tracking QR codes
--  À exécuter une seule fois
-- ═══════════════════════════════════════════════════════════

-- Table de correspondance ID → nom de source
CREATE TABLE IF NOT EXISTS `nomadrive_qr_sources` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(64)  NOT NULL COMMENT 'Nom lisible : local, flyer…',
  `description` VARCHAR(255)          COMMENT 'Description libre',
  `created_at`  DATETIME     NOT NULL DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sources initiales (IDs opaques dans les QR)
INSERT INTO `nomadrive_qr_sources` (id, name, description) VALUES
  (1, 'local',  'QR code affiché devant le local / vitrine'),
  (2, 'flyer',  'QR code imprimé sur le flyer');

-- Table de tracking des scans
CREATE TABLE IF NOT EXISTS `nomadrive_qr` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `date`             DATETIME     NOT NULL DEFAULT NOW(),
  `source_id`        INT          NOT NULL,
  `source_name`      VARCHAR(64)  NOT NULL COMMENT 'Copie dénormalisée pour rapidité',
  `HTTP_USER_AGENT`  TEXT,
  `REMOTE_ADDR`      VARCHAR(64),
  `lang`             VARCHAR(16),
  `os`               VARCHAR(32),
  `device_type`      VARCHAR(16),
  `browser`          VARCHAR(32),
  `referer`          TEXT,
  `unique_hash`      VARCHAR(64)  COMMENT 'SHA256(IP+UA+date) — visiteur unique par jour',
  INDEX `idx_date`        (`date`),
  INDEX `idx_source_id`   (`source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
