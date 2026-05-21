-- ── Tables Stripe — caution ──────────────────────────────────────────────────
-- À exécuter sur la BDD nomadrive

CREATE TABLE IF NOT EXISTS `nomadrive_stripe_cautions` (
  `id`                        int UNSIGNED    NOT NULL AUTO_INCREMENT,
  `contrat_id`                int UNSIGNED    NOT NULL,
  `dossier_id`                int UNSIGNED    DEFAULT NULL,
  `stripe_session_id`         varchar(255)    DEFAULT NULL,
  `stripe_payment_intent_id`  varchar(255)    DEFAULT NULL,
  `amount`                    int             NOT NULL COMMENT 'Centimes EUR',
  `currency`                  char(3)         NOT NULL DEFAULT 'eur',
  `status`  enum('pending','authorized','captured','canceled','expired') NOT NULL DEFAULT 'pending',
  `checkout_url`              text            DEFAULT NULL,
  `email_sent_at`             datetime        DEFAULT NULL,
  `created_at`                datetime        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`                datetime        DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `contrat_id`               (`contrat_id`),
  KEY `stripe_session_id`        (`stripe_session_id`(50)),
  KEY `stripe_payment_intent_id` (`stripe_payment_intent_id`(50))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
