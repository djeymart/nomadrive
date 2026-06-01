-- ═══════════════════════════════════════════════════════════
--  nomadrive_tour_stops — création + données initiales
-- ═══════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS nomadrive_tour_stops (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tour_slug        VARCHAR(32)   NOT NULL,          -- 'tour1' | 'tour2' | 'tour3'
    ordre            TINYINT UNSIGNED NOT NULL DEFAULT 0,
    nom_fr           VARCHAR(120)  NOT NULL,
    nom_en           VARCHAR(120)  NOT NULL DEFAULT '',
    nom_it           VARCHAR(120)  NOT NULL DEFAULT '',
    description_fr   TEXT          NULL,
    description_en   TEXT          NULL,
    lat              DECIMAL(10,7) NOT NULL,
    lng              DECIMAL(10,7) NOT NULL,
    rayon_m          SMALLINT UNSIGNED NOT NULL DEFAULT 200,
    est_arret        TINYINT(1)    NOT NULL DEFAULT 1,  -- arrêt dans l'itinéraire gauche
    est_poi          TINYINT(1)    NOT NULL DEFAULT 0,  -- POI panneau bas-droit
    services         JSON          NULL,                -- {"toilettes":true,"parking":false,...}
    google_place_id  VARCHAR(100)  NULL,
    image_url        VARCHAR(512)  NULL,
    INDEX idx_tour_ordre (tour_slug, ordre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────
--  TOUR 1 — City (4 arrêts)
-- ───────────────────────────────────────────────────────────
INSERT INTO nomadrive_tour_stops
    (tour_slug, ordre, nom_fr, nom_en, nom_it, lat, lng, est_arret, est_poi, rayon_m) VALUES

('tour1', 1,
 'Départ / Arrivée — Place Guynemer',
 'Start / End — Place Guynemer',
 'Partenza / Arrivo — Place Guynemer',
 43.6943040, 7.2822270, 1, 0, 100),

('tour1', 2,
 'Fort du Mont Alban — Villefranche-sur-Mer',
 'Fort du Mont Alban — Villefranche-sur-Mer',
 'Fort du Mont Alban — Villefranche-sur-Mer',
 43.7008080, 7.3002300, 1, 1, 200),

('tour1', 3,
 'Belvédère du Mont Boron',
 'Mont Boron Viewpoint',
 'Belvedere del Mont Boron',
 43.6964690, 7.2977430, 1, 1, 200),

('tour1', 4,
 'Cathédrale Saint-Nicolas de Nice',
 'Saint Nicholas Cathedral',
 'Cattedrale di San Nicola di Nizza',
 43.7026130, 7.2536070, 1, 1, 200);

-- ───────────────────────────────────────────────────────────
--  TOUR 2 — French Riviera (4 arrêts)
-- ───────────────────────────────────────────────────────────
INSERT INTO nomadrive_tour_stops
    (tour_slug, ordre, nom_fr, nom_en, nom_it, lat, lng, est_arret, est_poi, rayon_m) VALUES

('tour2', 1,
 'Départ / Arrivée — Place Guynemer',
 'Start / End — Place Guynemer',
 'Partenza / Arrivo — Place Guynemer',
 43.6943040, 7.2822270, 1, 0, 100),

('tour2', 2,
 'Anse de Villefranche-sur-Mer',
 'Villefranche-sur-Mer Bay',
 'Baia di Villefranche-sur-Mer',
 43.7073100, 7.3165030, 1, 1, 200),

('tour2', 3,
 'Plage du Lido — Saint-Jean-Cap-Ferrat',
 'Lido Beach — Saint-Jean-Cap-Ferrat',
 'Spiaggia del Lido — Saint-Jean-Cap-Ferrat',
 43.6926740, 7.3235870, 1, 1, 200),

('tour2', 4,
 'Belvédère des Hespérides — Nice',
 'Hespérides Viewpoint — Nice',
 'Belvedere delle Esperidi — Nizza',
 43.6921400, 7.3063280, 1, 1, 200);

-- ───────────────────────────────────────────────────────────
--  TOUR 3 — Sunset / Gold (6 arrêts)
-- ───────────────────────────────────────────────────────────
INSERT INTO nomadrive_tour_stops
    (tour_slug, ordre, nom_fr, nom_en, nom_it, lat, lng, est_arret, est_poi, rayon_m) VALUES

('tour3', 1,
 'Départ / Arrivée — Place Guynemer',
 'Start / End — Place Guynemer',
 'Partenza / Arrivo — Place Guynemer',
 43.6943040, 7.2822270, 1, 0, 100),

('tour3', 2,
 'Allée des Hespérides — Nice',
 'Allée des Hespérides — Nice',
 'Allée des Hespérides — Nizza',
 43.6939970, 7.3063450, 1, 1, 200),

('tour3', 3,
 'Anse de Villefranche-sur-Mer',
 'Villefranche-sur-Mer Bay',
 'Baia di Villefranche-sur-Mer',
 43.7072900, 7.3165800, 1, 1, 200),

('tour3', 4,
 'Avenue Marie-Louise Sabatier — Cap-Ferrat',
 'Avenue Marie-Louise Sabatier — Cap-Ferrat',
 'Avenue Marie-Louise Sabatier — Cap-Ferrat',
 43.6882230, 7.3309250, 1, 1, 200),

('tour3', 5,
 'Villa Ephrussi de Rothschild — Cap-Ferrat',
 'Villa Ephrussi de Rothschild — Cap-Ferrat',
 'Villa Ephrussi de Rothschild — Cap-Ferrat',
 43.6867830, 7.3397380, 1, 1, 200),

('tour3', 6,
 'Aire Demontzey — Panorama sur Nice',
 'Aire Demontzey — Nice Panorama',
 'Aire Demontzey — Panorama su Nizza',
 43.6959120, 7.2991830, 1, 1, 200);

