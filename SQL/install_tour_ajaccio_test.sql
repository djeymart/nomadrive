-- ═══════════════════════════════════════════════════════════
--  Tour test Ajaccio — stops pour tablette2.php ?admin=1
--  Départ  : 44 rue des Oblades, 20000 Ajaccio
--  Arrivée : EDF Agence Ajaccio (bd Sampiero)
-- ═══════════════════════════════════════════════════════════

INSERT INTO nomadrive_tour_stops
    (tour_slug, ordre, nom_fr, nom_en, nom_it, description_fr, description_en, lat, lng, rayon_m, est_arret, est_poi)
VALUES
    ('tour_ajaccio_test', 1,  'Départ – Rue des Oblades',  'Departure – Rue des Oblades',  'Partenza – Rue des Oblades',
     'Point de départ du tour test depuis le centre d\'Ajaccio.', 'Test tour departure point from central Ajaccio.',
     41.9099300, 8.6821903, 100, 1, 0),

    ('tour_ajaccio_test', 10, 'Maison Bonaparte',           'Maison Bonaparte',              'Casa Bonaparte',
     'Maison natale de Napoléon Bonaparte, classée monument historique.', 'Napoleon Bonaparte\'s birthplace, listed as a historic monument.',
     41.9212000, 8.7372000, 300, 0, 1),

    ('tour_ajaccio_test', 20, 'Cathédrale Notre-Dame',      'Notre-Dame Cathedral',          'Cattedrale di Nostra Signora',
     'Cathédrale Notre-Dame de l\'Assomption, où Napoléon fut baptisé en 1771.', 'Notre-Dame de l\'Assomption Cathedral, where Napoleon was baptised in 1771.',
     41.9189000, 8.7348000, 300, 0, 1),

    ('tour_ajaccio_test', 30, 'Place du Maréchal Foch',     'Place du Maréchal Foch',        'Piazza del Maresciallo Foch',
     'Place centrale d\'Ajaccio avec la statue de Napoléon en Premier Consul.', 'Central square of Ajaccio with the statue of Napoleon as First Consul.',
     41.9195000, 8.7338000, 300, 0, 1),

    ('tour_ajaccio_test', 40, 'Musée Fesch',                'Musée Fesch',                   'Museo Fesch',
     'Musée des Beaux-Arts, l\'une des plus importantes collections de peintures italiennes en France.', 'Fine arts museum, one of the most important Italian painting collections in France.',
     41.9228000, 8.7380000, 300, 0, 1),

    ('tour_ajaccio_test', 99, 'Arrivée – EDF Ajaccio',      'Arrival – EDF Ajaccio',         'Arrivo – EDF Ajaccio',
     'Agence EDF, boulevard du Maréchal de Sampiero, Ajaccio.', 'EDF agency, boulevard du Maréchal de Sampiero, Ajaccio.',
     41.9511299, 8.8066353, 100, 1, 0);
