-- Adminer 5.4.2 MySQL 8.0.26-google dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `nomadrive_auth_sessions`;
CREATE TABLE `nomadrive_auth_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `nomadrive_contrats`;
CREATE TABLE `nomadrive_contrats` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `adresse` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vehicule_id` int unsigned DEFAULT NULL,
  `vehicule` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_debut` date DEFAULT NULL,
  `heure_debut` time DEFAULT NULL,
  `dossier_empreinte` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signature` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `url_contrat_pdf` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url_permis_recto` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url_permis_verso` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_vehicule_id` (`vehicule_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `nomadrive_customers`;
CREATE TABLE `nomadrive_customers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bokun_booking_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ID interne Bokun',
  `confirmation_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Code de confirmation visible client',
  `first_name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_country_code` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` int unsigned DEFAULT NULL COMMENT 'ID activité Bokun',
  `product_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activity_date` date DEFAULT NULL COMMENT 'Date du tour',
  `start_datetime` datetime DEFAULT NULL,
  `participants` smallint unsigned NOT NULL DEFAULT '1',
  `end_datetime` datetime DEFAULT NULL,
  `booking_status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'CONFIRMED | CANCELLED | RESERVED',
  `channel` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `review_requested_at` datetime DEFAULT NULL COMMENT 'Date d''envoi de la demande d''avis',
  `review_followup_at` datetime DEFAULT NULL,
  `synced_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `closeout_resource_ids` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bokun_booking` (`bokun_booking_id`),
  KEY `idx_email` (`email`),
  KEY `idx_activity_date` (`activity_date`),
  KEY `idx_status` (`booking_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `nomadrive_dossiers`;
CREATE TABLE `nomadrive_dossiers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `contrat_id` int unsigned NOT NULL,
  `vehicule_id` int unsigned NOT NULL,
  `statut` enum('ouvert','ferme') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ouvert',
  `etat_avant_km` int unsigned DEFAULT NULL,
  `etat_avant_notes` text COLLATE utf8mb4_unicode_ci,
  `etat_avant_photos` json DEFAULT NULL,
  `etat_avant_at` datetime DEFAULT NULL,
  `etat_apres_km` int unsigned DEFAULT NULL,
  `etat_apres_notes` text COLLATE utf8mb4_unicode_ci,
  `etat_apres_photos` json DEFAULT NULL,
  `etat_apres_at` datetime DEFAULT NULL,
  `caution_liberee` tinyint(1) DEFAULT NULL,
  `caution_retenu` decimal(8,2) DEFAULT NULL,
  `caution_note` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `closed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vehicule_statut` (`vehicule_id`,`statut`),
  KEY `idx_contrat` (`contrat_id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `nomadrive_email_log`;
CREATE TABLE `nomadrive_email_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `customer_id` int DEFAULT NULL COMMENT 'FK nomadrive_customers.id',
  `email_to` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'review_request | review_followup',
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Message-ID SMTP (corrélation webhook)',
  `sent_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sent' COMMENT 'sent | delivered | bounce | complaint | unsubscribe | open | click',
  `webhook_event` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Dernier événement Sarbacane reçu',
  `webhook_at` datetime DEFAULT NULL COMMENT 'Date du dernier webhook',
  `raw_webhook` json DEFAULT NULL COMMENT 'Payload brut du dernier webhook',
  PRIMARY KEY (`id`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_message_id` (`message_id`),
  KEY `idx_email_to` (`email_to`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `nomadrive_qr`;
CREATE TABLE `nomadrive_qr` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `source_id` int NOT NULL,
  `source_name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Copie dénormalisée pour rapidité',
  `HTTP_USER_AGENT` text COLLATE utf8mb4_unicode_ci,
  `REMOTE_ADDR` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lang` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `os` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_type` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `browser` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referer` text COLLATE utf8mb4_unicode_ci,
  `unique_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SHA256(IP+UA+date) — visiteur unique par jour',
  PRIMARY KEY (`id`),
  KEY `idx_date` (`date`),
  KEY `idx_source_id` (`source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `nomadrive_qr_sources`;
CREATE TABLE `nomadrive_qr_sources` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nom lisible : local, flyer…',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Description libre',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `nomadrive_qr_sources` (`id`, `name`, `description`, `created_at`) VALUES
(1,	'local',	'QR code affiché devant le local / vitrine',	'2026-05-05 17:37:15'),
(2,	'flyer',	'QR code imprimé sur le flyer',	'2026-05-05 17:37:15');

DROP TABLE IF EXISTS `nomadrive_reviews`;
CREATE TABLE `nomadrive_reviews` (
  `id` int NOT NULL AUTO_INCREMENT,
  `source` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'google',
  `external_review_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `author_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `author_photo_url` text COLLATE utf8mb4_unicode_ci,
  `rating` tinyint unsigned NOT NULL DEFAULT '5',
  `review_text` text COLLATE utf8mb4_unicode_ci,
  `relative_date` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ex : « il y a 2 semaines »',
  `fetched_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_source_review` (`source`,`external_review_id`),
  KEY `idx_rating` (`rating`),
  KEY `idx_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `nomadrive_reviews_meta`;
CREATE TABLE `nomadrive_reviews_meta` (
  `id` int NOT NULL AUTO_INCREMENT,
  `source` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'google',
  `overall_rating` decimal(3,1) NOT NULL DEFAULT '0.0',
  `total_count` int unsigned NOT NULL DEFAULT '0',
  `last_synced_at` datetime NOT NULL DEFAULT '2000-01-01 00:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `nomadrive_settings`;
CREATE TABLE `nomadrive_settings` (
  `cle` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valeur` text COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `nomadrive_settings` (`cle`, `valeur`) VALUES
('admin_password_hash',	'96357b99a1c3d3a760332c989a2743dd5ef875e3c2b79466580218f8963e3099');

DROP TABLE IF EXISTS `nomadrive_vehicules`;
CREATE TABLE `nomadrive_vehicules` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `immatriculation` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `marque` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `modele` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `couleur` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `licence_key` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Clé de licence tablette (hex 64-bit)',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `guide` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_immat` (`immatriculation`),
  UNIQUE KEY `uq_licence_key` (`licence_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `nomadrive_vehicules` (`id`, `immatriculation`, `marque`, `modele`, `couleur`, `notes`, `licence_key`, `actif`, `guide`, `created_at`, `updated_at`) VALUES
(1,	'HH-327-ZZ',	'CITROEN',	'AMI',	'#F54927',	NULL,	'f8918b8dcacbe72d',	1,	0,	'2026-04-11 16:34:31',	'2026-04-17 17:45:00'),
(2,	'HH-339-ZZ',	'CITROEN',	'AMI',	'#F54927',	NULL,	'62d2d3e5ba729360',	1,	0,	'2026-04-11 16:34:31',	'2026-04-17 17:45:00'),
(3,	'HH-348-ZZ',	'CITROEN',	'AMI',	'#F54927',	NULL,	'728cf6a989a0ab40',	1,	0,	'2026-04-11 16:34:31',	'2026-04-17 17:45:00'),
(4,	'HH-358-ZZ',	'CITROEN',	'AMI',	'#F54927',	NULL,	'7b0de5675a16ca85',	1,	0,	'2026-04-11 16:34:31',	'2026-04-17 17:45:00'),
(5,	'HH-303-ZZ',	'CITROEN',	'AMI',	'#F54927',	NULL,	'2c6a2f1fce631243',	1,	0,	'2026-04-11 16:34:31',	'2026-04-17 17:45:01'),
(6,	'HF-735-CQ',	'FIAT',	'TOPOLINO',	'#219411',	'',	'87d11699802119ca',	1,	1,	'2026-04-11 16:34:31',	'2026-05-16 11:51:22'),
(7,	'HH-469-YT',	'FIAT',	'TOPOLINO',	'#219411',	NULL,	'684b062f76b68ae6',	1,	0,	'2026-04-11 16:34:31',	'2026-04-17 17:45:01'),
(8,	'HH-250-YV',	'FIAT',	'TOPOLINO',	'#219411',	NULL,	'b742852661d7c48d',	1,	0,	'2026-04-11 16:34:31',	'2026-04-17 17:45:01'),
(9,	'HH-108-YV',	'FIAT',	'TOPOLINO',	'#219411',	NULL,	'afdbb1a9d9774ef0',	1,	0,	'2026-04-11 16:34:31',	'2026-04-17 17:45:01'),
(10,	'HH-902-YQ',	'FIAT',	'TOPOLINO',	'#219411',	NULL,	'1fc6f289bbbd121c',	0,	0,	'2026-04-11 16:34:31',	'2026-04-17 17:47:46');

-- 2026-05-16 17:28:57 UTC
