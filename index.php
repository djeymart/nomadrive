<?php
// ─── Connexion base de données ────────────────────────────────────────────────
$madiDir = '/var/www/html/madi.mt';
if (!is_dir($madiDir)) $madiDir = dirname(__DIR__);
require_once $madiDir . '/vendor/autoload.php';
require_once $madiDir . '/php/fonctions.php';
require_once $madiDir . '/php/config.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sync_gyg_reviews.php';
$db1->query("SET NAMES 'utf8mb4'");

// ─── Langue ──────────────────────────────────────────────────────────────────
$lang = in_array($_GET['lang'] ?? '', ['fr', 'en', 'it']) ? $_GET['lang'] : 'fr';

$t = [
    'fr' => [
        'nav_tours' => 'Nos tours',
        'nav_faq' => 'FAQ',
        'nav_contact' => 'Contact',
        'nav_book' => 'Réserver',
        'hero_badge' => 'L\'EXPÉRIENCE NOMADRIVE',
        'hero_title' => 'Découvrez la Côte d\'Azur en toute liberté',
        'hero_desc' => 'Votre aventure commence ici — visites guidées en véhicules 100% électriques. <br /><strong>❤️ Choisissez vos tours ❤️</strong>',
        'trust_elec' => '100% électrique',
        'trust_guide' => 'Suivez notre guide local !',
        'trust_permit' => 'Permis B exigé',
        'trust_cancel' => 'Annulation gratuite 24h',
        'trust_caution' => 'Caution 500 € / véhicule',
        'faq_label' => 'QUESTIONS FRÉQUENTES',
        'faq_title' => 'Tout savoir sur nos tours guidés à Nice',
        'faq_q1' => 'Faut-il un permis de conduire pour participer à un tour ?',
        'faq_a1' => 'Oui, un <strong>permis de conduire valide</strong> (permis B ou équivalent) est obligatoire pour conduire nos véhicules lors du tour guidé. Le conducteur doit présenter son permis <strong>en version physique</strong> au départ du tour. <strong>Les permis virtuels ou présentés depuis un téléphone ne sont pas acceptés.</strong>',
        'faq_q2' => 'Puis-je garder le véhicule après le tour ?',
        'faq_a2' => 'Non, le véhicule est <strong>mis à disposition uniquement pendant la durée du tour guidé</strong>. Au retour, le véhicule est restitué au point de départ. Il ne s\'agit pas d\'une location libre.',
        'faq_q3' => 'Un enfant peut-il participer au tour ?',
        'faq_a3' => 'Oui, <strong>un enfant maximum</strong> peut être présent dans le véhicule, à condition d\'être <strong>obligatoirement accompagné d\'un adulte</strong> titulaire du permis de conduire. L\'adulte doit être le conducteur du véhicule.',
        'faq_q4' => 'Où a lieu le départ du tour ?',
        'faq_a4' => 'Le point de départ et de retour se situe au <strong>2 place Guynemer, 06300 Nice</strong>. Les instructions d\'accès vous seront communiquées par email après votre réservation.',
        'faq_q5' => 'Comment se déroule le tour guidé ?',
        'faq_a5' => '<strong>Merci d\'arriver 30 minutes avant l\'heure de départ</strong> pour signer le contrat et réaliser l\'état des lieux du véhicule. Un <strong>briefing rapide</strong> vous est ensuite donné. Vous prenez le volant de votre véhicule électrique et suivez l\'<strong>itinéraire guidé par GPS</strong> (tablette embarquée). Le parcours vous guide à travers les plus beaux quartiers de Nice avec des arrêts photo aux points d\'intérêt.',
        'faq_q6' => 'Que faut-il apporter le jour du tour ?',
        'faq_a6' => 'Vous devez impérativement présenter votre <strong>permis de conduire physique</strong> (pas de version numérique). Une <strong>pré-autorisation bancaire de 500 €</strong> sera effectuée à titre de caution : merci de vous munir d\'une <strong>carte bancaire physique</strong> (les cartes virtuelles ne sont pas acceptées).',
        'contact_label' => 'CONTACT',
        'contact_form_h3' => 'Envoyez-nous un message',
        'contact_send' => 'Envoyer le message',
        'contact_name' => 'Nom complet',
        'contact_email' => 'Email',
        'contact_msg' => 'Message',
        'contact_ph_name' => 'Votre nom',
        'contact_ph_email' => 'votre@email.com',
        'contact_ph_msg' => 'Comment pouvons-nous vous aider ?',
        'contact_call' => 'Nous appeler',
        'contact_address_label' => 'Point de départ des tours :',
        'footer_tagline' => 'Tours guidés en voiture électrique à Nice.',
        'footer_tours' => 'Nos tours',
        'footer_info' => 'Infos',
        'footer_contact' => 'Contact',
        'footer_hours' => 'Lun–Dim · 9h–22h',
        'footer_copyright' => '© 2026 NOMADRIVE — Tours guidés en voiture électrique à Nice. Tous droits réservés.',
        'footer_privacy' => 'Confidentialité',
        'footer_legal' => 'Mentions légales',
        'tours_desc_title' => 'DESCRIPTION DES TOURS',
        'tours_details_label' => '✨ L\'EXPÉRIENCE NOMADRIVE',
        'tours_details_body' => 'Tout commence dans notre local face à la mer, au Port de Nice, autour d\'un café de bienvenue. Nous vous confions ensuite les clés d\'un <strong>Ami Buggy ou Topolino Dolcevita</strong> — 100&nbsp;% électriques, ouverts sur le paysage, tablette et système audio à bord.<br><br>Un guide dédié vous accompagne pour dévoiler panoramas confidentiels, anecdotes et trésors cachés de la Riviera. Chaque tour est ponctué de collations pensées pour vous.<br><br>Pas de groupe impersonnel, pas de course — vous roulez à votre rythme, avec style.',
        'tour1_title' => '🏙️ CITY — 20 km · 2h',
        'tour1_body' => 'De la Promenade des Anglais au Château de Nice : le Negresco, la Cathédrale Orthodoxe Russe, les Arènes de Cimiez, le Boulevard Maeterlinck suspendu entre falaises et mer. Un panorama exceptionnel sur la Baie des Anges en point d\'orgue.<br><span class="tour-tags">Guide dédié · Collation incluse</span>',
        'tour2_title' => '🌊 FRENCH RIVIERA — 35 km · 2h30',
        'tour2_body' => 'Nice, La Réserve, Villefranche-sur-Mer et sa rade légendaire, Saint-Jean-Cap-Ferrat et ses villas Belle Époque, jusqu\'à Beaulieu-sur-Mer surnommée la « Petite Afrique ». Une pause baignade et collation face à la mer incluses. Pensez à votre maillot.<br><span class="tour-tags">Guide dédié · Surprise · Collation incluse</span>',
        'tour3_title' => '🌅 SUNSET — 35 km · 2h30',
        'tour3_body' => 'La Promenade, Villefranche sous la lumière du soir, puis le Château de Nice juste avant le coucher de soleil. Un apéritif face à la Baie des Anges vous attend au belvédère — boissons fraîches et planche gourmande. Heure de départ ajustée selon la saison.<br><span class="tour-tags">Guide dédié · Apéritif au coucher de soleil</span>',
        'tour_vehicle_note' => 'Par personne',
        'tour_coming_soon' => 'Réservation bientôt disponible',
        'book_btn' => 'Réserver',
    ],
    'en' => [
        'nav_tours' => 'Our tours',
        'nav_faq' => 'FAQ',
        'nav_contact' => 'Contact',
        'nav_book' => 'Book now',
        'hero_badge' => 'THE NOMADRIVE EXPERIENCE',
        'hero_title' => 'Discover the Côte d\'Azur in complete freedom',
        'hero_desc' => 'Your adventure starts here — guided tours in 100% electric vehicles. <br /><strong>❤️ Choose your tour ❤️</strong>',
        'trust_elec' => '100% electric',
        'trust_guide' => 'Follow our local guide!',
        'trust_permit' => 'Driving licence required',
        'trust_cancel' => 'Free cancellation 24h',
        'trust_caution' => '€500 security deposit',
        'faq_label' => 'FREQUENTLY ASKED QUESTIONS',
        'faq_title' => 'Everything about our guided tours in Nice',
        'faq_q1' => 'Is a driving licence required to join a tour?',
        'faq_a1' => 'Yes, a <strong>valid driving licence</strong> (category B or equivalent) is required to drive our vehicles during the guided tour. The driver must present their licence <strong>in physical form</strong> at the start of the tour. <strong>Digital licences or licences shown on a phone are not accepted.</strong>',
        'faq_q2' => 'Can I keep the vehicle after the tour?',
        'faq_a2' => 'No, the vehicle is <strong>available only for the duration of the guided tour</strong>. It is returned to the departure point at the end. This is not a free-roaming rental.',
        'faq_q3' => 'Can a child join the tour?',
        'faq_a3' => 'Yes, <strong>one child</strong> may be in the vehicle, provided they are <strong>accompanied by a licensed adult driver</strong>. The adult must be the driver.',
        'faq_q4' => 'Where does the tour depart from?',
        'faq_a4' => 'The departure and return point is at <strong>2 place Guynemer, 06300 Nice</strong>. Access instructions will be sent to you by email after booking.',
        'faq_q5' => 'How does the guided tour work?',
        'faq_a5' => '<strong>Please arrive 30 minutes before your departure time</strong> to sign the contract and complete the vehicle inspection. You\'ll then receive a <strong>quick briefing</strong>. You take the wheel of your electric vehicle and follow the <strong>GPS-guided route</strong> (on-board tablet). The route takes you through Nice\'s most beautiful neighbourhoods with photo stops at points of interest.',
        'faq_q6' => 'What do I need to bring on the day?',
        'faq_a6' => 'You must present your <strong>physical driving licence</strong> (digital versions are not accepted). A <strong>€500 pre-authorisation</strong> will be placed on your card as a security deposit: please bring a <strong>physical bank card</strong> — virtual cards are not accepted.',
        'contact_label' => 'CONTACT',
        'contact_form_h3' => 'Send us a message',
        'contact_send' => 'Send message',
        'contact_name' => 'Full name',
        'contact_email' => 'Email',
        'contact_msg' => 'Message',
        'contact_ph_name' => 'Your name',
        'contact_ph_email' => 'your@email.com',
        'contact_ph_msg' => 'How can we help you?',
        'contact_call' => 'Call us',
        'contact_address_label' => 'Tour departure point:',
        'footer_tagline' => 'Guided electric car tours in Nice.',
        'footer_tours' => 'Our tours',
        'footer_info' => 'Info',
        'footer_contact' => 'Contact',
        'footer_hours' => 'Mon–Sun · 9am–10pm',
        'footer_copyright' => '© 2026 NOMADRIVE — Guided electric car tours in Nice. All rights reserved.',
        'footer_privacy' => 'Privacy',
        'footer_legal' => 'Legal notice',
        'tours_desc_title' => 'TOUR DESCRIPTIONS',
        'tours_details_label' => '✨ THE NOMADRIVE EXPERIENCE',
        'tours_details_body' => 'It all starts in our sea-view space at Nice\'s Port, over a welcome coffee. We hand you the keys to an <strong>Ami Buggy or Topolino Dolcevita</strong> — 100% electric, open to the landscape, with an on-board tablet and audio system.<br><br>A dedicated guide accompanies you to reveal hidden panoramas, local stories and secret gems of the Riviera. Each tour includes thoughtful snacks along the way.<br><br>No impersonal groups, no rushing — your pace, your style.',
        'tour1_title' => '🏙️ CITY — 20 km · 2h',
        'tour1_body' => 'From the Promenade des Anglais to the Château de Nice: the Negresco, the Russian Orthodox Cathedral, the Roman arenas of Cimiez, the Maeterlinck Boulevard suspended between cliffs and sea. An exceptional panorama over the Baie des Anges.<br><span class="tour-tags">Dedicated guide · Snack included</span>',
        'tour2_title' => '🌊 FRENCH RIVIERA — 35 km · 2h30',
        'tour2_body' => 'Nice, La Réserve, Villefranche-sur-Mer and its legendary bay, Saint-Jean-Cap-Ferrat with its Belle Époque villas, up to Beaulieu-sur-Mer known as the « Petite Afrique ». A swim break and seaside snack included. Don\'t forget your swimsuit.<br><span class="tour-tags">Dedicated guide · Surprise · Snack included</span>',
        'tour3_title' => '🌅 SUNSET — 35 km · 2h30',
        'tour3_body' => 'The Promenade, Villefranche in the evening light, then the Château de Nice just before sunset. An aperitif overlooking the Baie des Anges awaits — cold drinks and a charcuterie board. Departure time adjusted to capture the exact sunset.<br><span class="tour-tags">Dedicated guide · Sunset aperitif</span>',
        'tour_vehicle_note' => 'Per person',
        'tour_coming_soon' => 'Booking coming soon',
        'book_btn' => 'Book now',
    ],
    'it' => [
        'nav_tours' => 'I nostri tour',
        'nav_faq' => 'FAQ',
        'nav_contact' => 'Contatto',
        'nav_book' => 'Prenota',
        'hero_badge' => 'L\'ESPERIENZA NOMADRIVE',
        'hero_title' => 'Scopri la Costa Azzurra in totale libertà',
        'hero_desc' => 'La tua avventura comincia qui — tour guidati in veicoli 100% elettrici. <br /><strong>❤️ Scegli il tuo tour ❤️</strong>',
        'trust_elec' => '100% elettrico',
        'trust_guide' => 'Segui la nostra guida!',
        'trust_permit' => 'Patente richiesta',
        'trust_cancel' => 'Cancellazione gratuita 24h',
        'trust_caution' => 'Cauzione 500 € / veicolo',
        'faq_label' => 'DOMANDE FREQUENTI',
        'faq_title' => 'Tutto sui nostri tour guidati a Nizza',
        'faq_q1' => 'È necessaria la patente per partecipare a un tour?',
        'faq_a1' => 'Sì, una <strong>patente di guida valida</strong> (categoria B o equivalente) è obbligatoria per guidare i nostri veicoli durante il tour. Il conducente deve presentare la patente <strong>in formato fisico</strong> all\'inizio del tour. <strong>Le patenti digitali o mostrate dal telefono non sono accettate.</strong>',
        'faq_q2' => 'Posso tenere il veicolo dopo il tour?',
        'faq_a2' => 'No, il veicolo è <strong>disponibile solo per la durata del tour guidato</strong>. Al ritorno, il veicolo viene restituito al punto di partenza. Non si tratta di un noleggio libero.',
        'faq_q3' => 'Un bambino può partecipare al tour?',
        'faq_a3' => 'Sì, <strong>un bambino al massimo</strong> può essere presente nel veicolo, a condizione di essere <strong>obbligatoriamente accompagnato da un adulto</strong> con patente. L\'adulto deve essere il conducente.',
        'faq_q4' => 'Da dove parte il tour?',
        'faq_a4' => 'Il punto di partenza e di ritorno è al <strong>2 place Guynemer, 06300 Nizza</strong>. Le istruzioni di accesso saranno comunicate per email dopo la prenotazione.',
        'faq_q5' => 'Come si svolge il tour guidato?',
        'faq_a5' => '<strong>Si prega di arrivare 30 minuti prima dell\'orario di partenza</strong> per firmare il contratto e effettuare il controllo del veicolo. Riceverete poi un <strong>rapido briefing</strong>. Prendete il volante del vostro veicolo elettrico e seguite il <strong>percorso guidato via GPS</strong> (tablet a bordo). Il percorso vi guida nei quartieri più belli di Nizza con soste fotografiche nei punti d\'interesse.',
        'faq_q6' => 'Cosa portare il giorno del tour?',
        'faq_a6' => 'È obbligatorio presentare la <strong>patente di guida fisica</strong> (le versioni digitali non sono accettate). Verrà effettuata una <strong>pre-autorizzazione di 500 €</strong> sulla vostra carta come cauzione: portate una <strong>carta bancaria fisica</strong> — le carte virtuali non sono accettate.',
        'contact_label' => 'CONTATTO',
        'contact_form_h3' => 'Inviaci un messaggio',
        'contact_send' => 'Invia messaggio',
        'contact_name' => 'Nome completo',
        'contact_email' => 'Email',
        'contact_msg' => 'Messaggio',
        'contact_ph_name' => 'Il tuo nome',
        'contact_ph_email' => 'tua@email.com',
        'contact_ph_msg' => 'Come possiamo aiutarti?',
        'contact_call' => 'Chiamaci',
        'contact_address_label' => 'Punto di partenza dei tour:',
        'footer_tagline' => 'Tour guidati in auto elettrica a Nizza.',
        'footer_tours' => 'I nostri tour',
        'footer_info' => 'Info',
        'footer_contact' => 'Contatto',
        'footer_hours' => 'Lun–Dom · 9–22',
        'footer_copyright' => '© 2026 NOMADRIVE — Tour guidati in auto elettrica a Nizza. Tutti i diritti riservati.',
        'footer_privacy' => 'Privacy',
        'footer_legal' => 'Note legali',
        'tours_desc_title' => 'DESCRIZIONE DEI TOUR',
        'tours_details_label' => '✨ L\'ESPERIENZA NOMADRIVE',
        'tours_details_body' => 'Tutto comincia nel nostro locale affacciato sul mare, al Porto di Nizza, davanti a un caffè di benvenuto. Vi consegniamo poi le chiavi di un <strong>Ami Buggy o Topolino Dolcevita</strong> — 100% elettrici, aperti sul paesaggio, con tablet e sistema audio a bordo.<br><br>Una guida dedicata vi accompagna per svelare panorami riservati, aneddoti e tesori nascosti della Riviera. Ogni tour è scandito da piccole attenzioni pensate per voi.<br><br>Nessun gruppo impersonale, nessuna corsa — si guida al proprio ritmo, con stile.',
        'tour1_title' => '🏙️ CITY — 20 km · 2h',
        'tour1_body' => 'Dalla Promenade des Anglais al Castello di Nizza: il Negresco, la Cattedrale Ortodossa Russa, le Arene romane di Cimiez, il Boulevard Maeterlinck sospeso tra scogliere e mare. Un panorama eccezionale sulla Baia degli Angeli.<br><span class="tour-tags">Guida dedicata · Snack incluso</span>',
        'tour2_title' => '🌊 FRENCH RIVIERA — 35 km · 2h30',
        'tour2_body' => 'Nizza, La Réserve, Villefranche-sur-Mer con la sua baia leggendaria, Saint-Jean-Cap-Ferrat con le sue ville Belle Époque, fino a Beaulieu-sur-Mer. Una pausa balneare e uno snack fronte mare inclusi. Ricordatevi il costume.<br><span class="tour-tags">Guida dedicata · Sorpresa · Snack incluso</span>',
        'tour3_title' => '🌅 SUNSET — 35 km · 2h30',
        'tour3_body' => 'La Promenade, Villefranche nella luce della sera, poi il Castello di Nizza poco prima del tramonto. Un aperitivo affacciato sulla Baia degli Angeli vi aspetta al belvedere — bibite fresche e tagliere. Orario adattato alla stagione.<br><span class="tour-tags">Guida dedicata · Aperitivo al tramonto</span>',
        'tour_vehicle_note' => 'A persona',
        'tour_coming_soon' => 'Prenotazione disponibile a breve',
        'book_btn' => 'Prenota ora',
    ],
][$lang];

// ─── Carousel photos ─────────────────────────────────────────────────────────
$carousel_dir = __DIR__ . '/images/carousel/';
$carousel_files = glob($carousel_dir . '*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];
shuffle($carousel_files);
$carousel_photos = array_slice($carousel_files, 0, 4);
// Fallback si pas encore de photos : placeholders CSS
$carousel_count = max(count($carousel_photos), 4);

// ─── Google Reviews ──────────────────────────────────────────────────────────
function callGooglePlacesAPI(): array {
    $placeId = 'ChIJGdQvT-7bzRIRgGC_8yxIQKc';
    $apiKey  = defined('GOOGLE_PLACES_API_KEY') ? GOOGLE_PLACES_API_KEY : '';
    $url     = "https://places.googleapis.com/v1/places/{$placeId}";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            "X-Goog-Api-Key: {$apiKey}",
            "X-Goog-FieldMask: rating,reviews,userRatingCount",
        ],
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if (!$raw || $code !== 200) {
        return ['_error' => "Erreur curl (HTTP {$code}) : {$err}"];
    }
    $data = json_decode($raw, true);
    if (!isset($data['rating'])) {
        return ['_error' => 'Réponse API invalide : ' . ($data['error']['message'] ?? json_encode($data))];
    }
    return $data;
}

function fetchGoogleReviews(PDO $db): array {
    $meta = $db->query("SELECT * FROM nomadrive_reviews_meta WHERE source = 'google' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $needsSync = !$meta || (strtotime($meta['last_synced_at']) < time() - 3600);

    if ($needsSync) {
        $apiData = callGooglePlacesAPI();
        if (!isset($apiData['_error'])) {
            $upsert = $db->prepare("
                INSERT INTO nomadrive_reviews
                    (source, external_review_id, author_name, author_photo_url, rating, review_text, relative_date, fetched_at)
                VALUES
                    ('google', :gid, :author, :photo, :rating, :text, :reldate, NOW())
                ON DUPLICATE KEY UPDATE
                    author_name      = VALUES(author_name),
                    author_photo_url = VALUES(author_photo_url),
                    rating           = VALUES(rating),
                    review_text      = VALUES(review_text),
                    relative_date    = VALUES(relative_date),
                    fetched_at       = NOW()
            ");
            foreach ($apiData['reviews'] ?? [] as $r) {
                $upsert->execute([
                    ':gid'    => $r['name'] ?? '',
                    ':author' => $r['authorAttribution']['displayName'] ?? '',
                    ':photo'  => $r['authorAttribution']['photoUri'] ?? null,
                    ':rating' => (int)($r['rating'] ?? 0),
                    ':text'   => $r['text']['text'] ?? null,
                    ':reldate'=> $r['relativePublishTimeDescription'] ?? null,
                ]);
            }
            $db->prepare("
                INSERT INTO nomadrive_reviews_meta (source, overall_rating, total_count, last_synced_at)
                VALUES ('google', :r, :t, NOW())
                ON DUPLICATE KEY UPDATE overall_rating = :r, total_count = :t, last_synced_at = NOW()
            ")->execute([
                ':r' => $apiData['rating'] ?? 0,
                ':t' => $apiData['userRatingCount'] ?? 0,
            ]);
            $meta = $db->query("SELECT * FROM nomadrive_reviews_meta WHERE source = 'google' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        }
    }

    if (!$meta || empty($meta['overall_rating'])) {
        return ['_error' => 'Aucun avis en base, synchronisation à venir.'];
    }

    $reviews = $db->query("
        SELECT * FROM nomadrive_reviews WHERE rating = 5 ORDER BY RAND() LIMIT 6
    ")->fetchAll(PDO::FETCH_ASSOC);

    return [
        'rating'  => (float)$meta['overall_rating'],
        'total'   => (int)$meta['total_count'],
        'reviews' => $reviews,
    ];
}
$googleReviews = fetchGoogleReviews($db1);
fetchGygReviews($db1);
$gygMeta = $db1->query("SELECT overall_rating, total_count FROM nomadrive_reviews_meta WHERE source = 'gyg' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

// Form processing for contact
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'contact') {
    header('Content-Type: application/json');

    $name = strip_tags(trim($_POST['name'] ?? ''));
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $message = strip_tags(trim($_POST['message'] ?? ''));
    $token = $_POST['recaptcha_token'] ?? '';

    if (empty($name) || empty($email) || empty($message) || empty($token)) {
        echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs.']);
        exit;
    }

    $projectId = defined('RECAPTCHA_PROJECT_ID') ? RECAPTCHA_PROJECT_ID : '';
    $apiKey = defined('RECAPTCHA_API_KEY') ? RECAPTCHA_API_KEY : '';
    $siteKey = defined('RECAPTCHA_SITE_KEY') ? RECAPTCHA_SITE_KEY : '';

    // Verify reCAPTCHA Enterprise
    $url = "https://recaptchaenterprise.googleapis.com/v1/projects/{$projectId}/assessments?key={$apiKey}";
    $data = [
        'event' => [
            'token' => $token,
            'siteKey' => $siteKey,
            'expectedAction' => 'submit'
        ]
    ];
    $options = [
        'http' => [
            'header' => "Content-type: application/json\r\n",
            'method' => 'POST',
            'content' => json_encode($data)
        ]
    ];
    $context = stream_context_create($options);
    $verifyResult = @file_get_contents($url, false, $context);

    if ($verifyResult) {
        $responseData = json_decode($verifyResult, true);
        if (!isset($responseData['tokenProperties']['valid']) || !$responseData['tokenProperties']['valid']) {
            echo json_encode(['success' => false, 'message' => 'La vérification anti-spam a échoué.']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur de connexion au service anti-spam.']);
        exit;
    }

    // ── Charger PHPMailer via autoload (même chemin que contrat.php) ──
    $madiDir = '/var/www/html/madi.mt';
    if (!is_dir($madiDir))
        $madiDir = dirname(__DIR__);
    $autoloadFile = $madiDir . '/vendor/autoload.php';
    if (file_exists($autoloadFile)) {
        require_once $autoloadFile;
    }

    $smtpUsername = SMTP_USERNAME;
    $smtpPassword = SMTP_PASSWORD;

    // Send Mail via Sarbacane SMTP
    $sent = false;
    $send_error = '';

    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer') && !empty($smtpUsername)) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp-sendkit.sarbacane.com';
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUsername;
            $mail->Password = $smtpPassword;
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';

            $mail->setFrom('contact@nomadrive.fr', 'NOMADRIVE');
            $mail->addReplyTo($email, $name);
            $mail->addAddress('contact@nomadrive.fr', 'NOMADRIVE');

            $mail->isHTML(true);
            $mail->Subject = "Nouveau message de $name - (Contact NOMADRIVE)";
            $mail->Body = "
                <div style='font-family:Arial,sans-serif;color:#222;max-width:600px;margin:0 auto;padding:20px;'>
                    <h2 style='color:#0077b6;margin:0 0 16px;'>Nouveau message — Contact NOMADRIVE</h2>
                    <table style='width:100%;border-collapse:collapse;font-size:14px;'>
                        <tr><td style='padding:8px;background:#f0f8ff;font-weight:bold;width:30%;'>Nom</td>
                            <td style='padding:8px;background:#f0f8ff;'>" . htmlspecialchars($name) . "</td></tr>
                        <tr><td style='padding:8px;font-weight:bold;'>Email</td>
                            <td style='padding:8px;'><a href='mailto:" . htmlspecialchars($email) . "'>" . htmlspecialchars($email) . "</a></td></tr>
                    </table>
                    <div style='margin-top:16px;padding:16px;background:#f9f9f9;border-radius:8px;font-size:14px;line-height:1.7;'>
                        <strong>Message :</strong><br>" . nl2br(htmlspecialchars($message)) . "
                    </div>
                    <hr style='border:none;border-top:1px solid #eee;margin:20px 0;'>
                    <p style='font-size:11px;color:#aaa;text-align:center;'>Ce message a été envoyé depuis le formulaire de contact de nomadrive.fr</p>
                </div>";
            $mail->AltBody = "Nom: $name\nEmail: $email\n\nMessage:\n$message";

            $mail->send();
            $sent = true;
        } catch (\Exception $e) {
            $send_error = $e->getMessage();
        }
    } else {
        // Fallback avec mail() natif
        $to = 'contact@nomadrive.fr';
        $subject = "Nouveau message de $name - (Contact NOMADRIVE)";
        $body = "Nom: $name\nEmail: $email\n\nMessage:\n$message";
        $headers = "From: $email" . "\r\n" .
            "Reply-To: $email" . "\r\n" .
            "X-Mailer: PHP/" . phpversion();
        $sent = mail($to, $subject, $body, $headers);
        if (!$sent)
            $send_error = 'mail() failed';
    }

    if ($sent) {
        echo json_encode(['success' => true, 'message' => 'Votre message a bien été envoyé.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Une erreur est survenue lors de l\'envoi de l\'email.']);
    }
    exit;
}
?>
<?php
$seo = [
    'fr' => [
        'title' => 'Tours Guidés en Voiture Électrique à Nice | NOMADRIVE',
        'description' => 'Découvrez la Côte d\'Azur en voiture électrique ouverte — 3 tours guidés au départ de Nice : CITY (2h), FRENCH RIVIERA (2h30) et SUNSET avec apéritif au belvédère. Guide dédié, collations incluses. Permis B requis.',
        'keywords' => 'tours guidés nice, visite nice voiture électrique, guide nice, excursion côte d\'azur, ami buggy nice, topolino dolcevita nice, tour accompagné nice, riviera française, coucher de soleil nice',
        'og_title' => 'Tours Guidés en Voiture Électrique à Nice | NOMADRIVE',
        'og_desc' => '3 circuits guidés depuis Nice en Ami Buggy ou Topolino Dolcevita — CITY, FRENCH RIVIERA & SUNSET. Guide local, tablette embarquée, collations incluses. Permis B requis.',
        'og_locale' => 'fr_FR',
        'og_img_alt' => 'Vue panoramique sur la Côte d\'Azur depuis Nice avec NOMADRIVE',
    ],
    'en' => [
        'title' => 'Guided Electric Car Tours in Nice, French Riviera | NOMADRIVE',
        'description' => 'Discover the French Riviera in an open-air electric vehicle — 3 guided tours from Nice: CITY (2h), FRENCH RIVIERA (2h30) and SUNSET with rooftop aperitif. Dedicated guide, snacks included. Driving licence required.',
        'keywords' => 'guided tours nice, electric car tour nice, french riviera tour, nice sightseeing, ami buggy nice, topolino dolcevita nice, cote d\'azur tour, sunset tour nice',
        'og_title' => 'Guided Electric Car Tours in Nice | NOMADRIVE',
        'og_desc' => '3 guided routes from Nice in an open-air electric vehicle — CITY, FRENCH RIVIERA & SUNSET. Local guide, on-board tablet, snacks included. Driving licence required.',
        'og_locale' => 'en_US',
        'og_img_alt' => 'Panoramic view of the French Riviera from Nice with NOMADRIVE',
    ],
    'it' => [
        'title' => 'Tour Guidati in Auto Elettrica a Nizza | NOMADRIVE',
        'description' => 'Scopri la Costa Azzurra in auto elettrica aperta — 3 tour guidati da Nizza: CITY (2h), FRENCH RIVIERA (2h30) e SUNSET con aperitivo al belvedere. Guida dedicata, snack inclusi. Patente B richiesta.',
        'keywords' => 'tour guidati nizza, visita nizza auto elettrica, costa azzurra tour, ami buggy nizza, topolino dolcevita nizza, gita costa azzurra, tramonto nizza',
        'og_title' => 'Tour Guidati in Auto Elettrica a Nizza | NOMADRIVE',
        'og_desc' => '3 percorsi guidati da Nizza in auto elettrica aperta — CITY, FRENCH RIVIERA & SUNSET. Guida locale, tablet a bordo, snack inclusi. Patente B richiesta.',
        'og_locale' => 'it_IT',
        'og_img_alt' => 'Vista panoramica sulla Costa Azzurra da Nizza con NOMADRIVE',
    ],
];
$sm = $seo[$lang];
$canonical = $lang === 'fr' ? 'https://nomadrive.fr/' : 'https://nomadrive.fr/?lang=' . $lang;
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Favicon & App Icons -->
    <link rel="icon" type="image/jpeg" href="/images/logo_nomadrive.jpg">
    <link rel="apple-touch-icon" href="/images/logo_nomadrive.jpg">
    <meta name="theme-color" content="#1a1a2e">

    <!-- SEO Primary Meta -->
    <title><?= htmlspecialchars($sm['title']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($sm['description']) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($sm['keywords']) ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= $canonical ?>">

    <!-- Geo Targeting -->
    <meta name="geo.region" content="FR-06">
    <meta name="geo.placename" content="Nice">
    <meta name="geo.position" content="43.7102;7.2620">
    <meta name="ICBM" content="43.7102, 7.2620">

    <!-- Hreflang -->
    <link rel="alternate" hreflang="fr" href="https://nomadrive.fr/?lang=fr">
    <link rel="alternate" hreflang="en" href="https://nomadrive.fr/?lang=en">
    <link rel="alternate" hreflang="it" href="https://nomadrive.fr/?lang=it">
    <link rel="alternate" hreflang="x-default" href="https://nomadrive.fr/">

    <!-- Open Graph (WhatsApp, iMessage, Messenger…) -->
    <meta property="og:type" content="website">
    <meta property="og:locale" content="<?= $sm['og_locale'] ?>">
    <meta property="og:site_name" content="NOMADRIVE">
    <meta property="og:title" content="<?= htmlspecialchars($sm['og_title']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($sm['og_desc']) ?>">
    <meta property="og:url" content="<?= $canonical ?>">
    <meta property="og:image" content="https://nomadrive.fr/images/nice-panorama.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="<?= htmlspecialchars($sm['og_img_alt']) ?>">

    <!-- Twitter / X Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($sm['og_title']) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($sm['og_desc']) ?>">
    <meta name="twitter:image" content="https://nomadrive.fr/images/nice-panorama.png">
    <meta name="twitter:image:alt" content="<?= htmlspecialchars($sm['og_img_alt']) ?>">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://kit.fontawesome.com/494ceebc6d.js" crossorigin="anonymous"></script>

    <!-- reCAPTCHA Enterprise (sécurité — exempt de consentement CNIL) -->
    <script
        src="https://www.google.com/recaptcha/enterprise.js?render=<?= defined('RECAPTCHA_SITE_KEY') ? RECAPTCHA_SITE_KEY : '6LchIXwsAAAAAFZ__UAGkDra42FaWW7XpdDH3NiK' ?>"></script>

    <!-- Google Tag Manager — chargé uniquement après consentement cookies -->
    <script>var GTM_ID = 'GTM-5NH9D8CC';</script>

    <!-- JSON-LD: LocalBusiness -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "LocalBusiness",
        "name": "NOMADRIVE",
        "description": "Tours guidés en voiture électrique ouverte à Nice et sur la Côte d'Azur. 3 circuits : CITY, FRENCH RIVIERA et SUNSET. Guide dédié, tablette embarquée, collations incluses.",
        "url": "https://nomadrive.fr",
        "telephone": "+336-33-33-87-92",
        "email": "contact@nomadrive.fr",
        "image": "https://nomadrive.fr/images/nice-panorama.png",
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "2 place Guynemer",
            "addressLocality": "Nice",
            "postalCode": "06300",
            "addressRegion": "Provence-Alpes-Côte d'Azur",
            "addressCountry": "FR"
        },
        "geo": {
            "@type": "GeoCoordinates",
            "latitude": 43.7102,
            "longitude": 7.2620
        },
        "openingHours": "Mo-Su 09:00-22:00",
        "priceRange": "€€",
        "currenciesAccepted": "EUR",
        "paymentAccepted": "Credit Card, Online",
        "areaServed": {
            "@type": "GeoCircle",
            "geoMidpoint": { "@type": "GeoCoordinates", "latitude": 43.7102, "longitude": 7.2620 },
            "geoRadius": "40000"
        }
    }
    </script>

    <!-- JSON-LD: Tours (TouristTrip) -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "ItemList",
        "name": "Tours guidés NOMADRIVE",
        "itemListElement": [
            {
                "@type": "ListItem",
                "position": 1,
                "item": {
                    "@type": "TouristTrip",
                    "name": "NOMADRIVE CITY — Tour guidé Nice 2h",
                    "description": "De la Promenade des Anglais au Château de Nice : le Negresco, la Cathédrale Orthodoxe Russe, les Arènes de Cimiez, le Boulevard Maeterlinck. Panorama exceptionnel sur la Baie des Anges.",
                    "image": "https://nomadrive.fr/images/tour1.jpg",
                    "url": "https://nomadrive.fr/",
                    "touristType": "Sightseeing",
                    "offers": {
                        "@type": "Offer",
                        "price": "75",
                        "priceCurrency": "EUR",
                        "availability": "https://schema.org/InStock"
                    },
                    "itinerary": {
                        "@type": "ItemList",
                        "name": "Étapes du tour CITY",
                        "itemListElement": [
                            { "@type": "ListItem", "position": 1, "name": "Promenade des Anglais" },
                            { "@type": "ListItem", "position": 2, "name": "Negresco" },
                            { "@type": "ListItem", "position": 3, "name": "Cathédrale Orthodoxe Russe" },
                            { "@type": "ListItem", "position": 4, "name": "Arènes de Cimiez" },
                            { "@type": "ListItem", "position": 5, "name": "Château de Nice — Baie des Anges" }
                        ]
                    }
                }
            },
            {
                "@type": "ListItem",
                "position": 2,
                "item": {
                    "@type": "TouristTrip",
                    "name": "NOMADRIVE FRENCH RIVIERA — Tour guidé 2h30",
                    "description": "Nice, Villefranche-sur-Mer, Saint-Jean-Cap-Ferrat et Beaulieu-sur-Mer. Pause baignade et collation face à la mer incluses.",
                    "image": "https://nomadrive.fr/images/tour2.jpg",
                    "url": "https://nomadrive.fr/",
                    "touristType": "Sightseeing",
                    "offers": {
                        "@type": "Offer",
                        "price": "90",
                        "priceCurrency": "EUR",
                        "availability": "https://schema.org/InStock"
                    },
                    "itinerary": {
                        "@type": "ItemList",
                        "name": "Étapes du tour FRENCH RIVIERA",
                        "itemListElement": [
                            { "@type": "ListItem", "position": 1, "name": "Nice — Port" },
                            { "@type": "ListItem", "position": 2, "name": "Villefranche-sur-Mer" },
                            { "@type": "ListItem", "position": 3, "name": "Saint-Jean-Cap-Ferrat" },
                            { "@type": "ListItem", "position": 4, "name": "Beaulieu-sur-Mer" }
                        ]
                    }
                }
            },
            {
                "@type": "ListItem",
                "position": 3,
                "item": {
                    "@type": "TouristTrip",
                    "name": "NOMADRIVE SUNSET — Tour guidé coucher de soleil 2h",
                    "description": "La Promenade, Villefranche sous la lumière du soir, puis le Château de Nice au coucher de soleil. Apéritif face à la Baie des Anges au belvédère.",
                    "image": "https://nomadrive.fr/images/tour3.jpg",
                    "url": "https://nomadrive.fr/",
                    "touristType": "Sightseeing",
                    "offers": {
                        "@type": "Offer",
                        "price": "100",
                        "priceCurrency": "EUR",
                        "availability": "https://schema.org/InStock"
                    },
                    "itinerary": {
                        "@type": "ItemList",
                        "name": "Étapes du tour SUNSET",
                        "itemListElement": [
                            { "@type": "ListItem", "position": 1, "name": "Promenade des Anglais" },
                            { "@type": "ListItem", "position": 2, "name": "Villefranche-sur-Mer au crépuscule" },
                            { "@type": "ListItem", "position": 3, "name": "Château de Nice — coucher de soleil" },
                            { "@type": "ListItem", "position": 4, "name": "Belvédère — apéritif Baie des Anges" }
                        ]
                    }
                }
            }
        ]
    }
    </script>

    <!-- JSON-LD: FAQPage -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "FAQPage",
        "mainEntity": [
            {
                "@type": "Question",
                "name": "Faut-il un permis de conduire pour participer à un tour ?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Oui, un permis de conduire valide (permis B ou équivalent) est obligatoire pour conduire nos véhicules lors du tour guidé. Le conducteur doit être en mesure de présenter son permis au départ du tour."
                }
            },
            {
                "@type": "Question",
                "name": "Puis-je garder le véhicule après le tour ?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Non, le véhicule est mis à disposition uniquement pendant la durée du tour guidé. Au retour, le véhicule est restitué au point de départ. Il ne s'agit pas d'une location libre."
                }
            },
            {
                "@type": "Question",
                "name": "Un enfant peut-il participer au tour ?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Oui, un enfant maximum peut être présent dans le véhicule, à condition d'être obligatoirement accompagné d'un adulte titulaire du permis de conduire. L'adulte doit être le conducteur du véhicule."
                }
            },
            {
                "@type": "Question",
                "name": "Où a lieu le départ du tour ?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Le point de départ et de retour se situe au 2 place Guynemer, 06300 Nice. Les instructions d'accès vous seront communiquées par email après votre réservation."
                }
            },
            {
                "@type": "Question",
                "name": "Comment se déroule le tour guidé ?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "À votre arrivée, un briefing rapide vous est donné. Vous prenez ensuite le volant de votre véhicule électrique et suivez le guide (tablette embarquée et accompagnement humain). Le parcours vous guide à travers les plus beaux quartiers de Nice avec des arrêts photo aux points d'intérêt."
                }
            },
            {
                "@type": "Question",
                "name": "Où se garer à proximité ?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Le Parking Port Lympia se trouve à 200 mètres de notre local."
                }
            }
        ]
    }
    </script>
</head>

<body>

    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-5NH9D8CC" height="0" width="0"
            style="display:none;visibility:hidden"></iframe></noscript>


    <!-- Navigation -->
    <nav class="main-nav" aria-label="Navigation principale">
        <div class="nav-inner">
            <a href="/?lang=<?= $lang ?>" class="nav-logo" aria-label="NOMADRIVE - Accueil">NOMADRIVE</a>
            <div class="nav-links" id="nav-links">
                <a href="#reserver"><?= $t['nav_tours'] ?></a>
                <a href="/faq.php?lang=<?= $lang ?>"><?= $t['nav_faq'] ?></a>
                <a href="#contact"><?= $t['nav_contact'] ?></a>
            </div>
            <div class="nav-actions">
                <div class="lang-switcher">
                    <a href="?lang=fr" class="lang-btn <?= $lang === 'fr' ? 'active' : '' ?>">🇫🇷</a>
                    <a href="?lang=en" class="lang-btn <?= $lang === 'en' ? 'active' : '' ?>">🇬🇧</a>
                    <a href="?lang=it" class="lang-btn <?= $lang === 'it' ? 'active' : '' ?>">🇮🇹</a>
                </div>
                <a href="#reserver" class="nav-cta-btn"><?= $t['nav_book'] ?></a>
                <button class="hamburger" id="hamburger-btn" aria-label="Ouvrir le menu" aria-expanded="false">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </div>
    </nav>

    <main>

        <!-- ── Carousel photos ── -->
        <section class="carousel-section" aria-label="Photos">
            <div class="carousel-track" id="carousel-track">
                <?php if ($carousel_photos): ?>
                    <?php foreach ($carousel_photos as $photo): ?>
                        <div class="carousel-slide">
                            <img src="/images/carousel/<?= htmlspecialchars(basename($photo)) ?>" alt="NOMADRIVE Nice"
                                loading="lazy">
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Placeholders tant qu'il n'y a pas encore de photos -->
                    <div class="carousel-slide carousel-placeholder"
                        style="background:linear-gradient(135deg,#0077b6,#00b4d8)"></div>
                    <div class="carousel-slide carousel-placeholder"
                        style="background:linear-gradient(135deg,#023e8a,#0077b6)"></div>
                    <div class="carousel-slide carousel-placeholder"
                        style="background:linear-gradient(135deg,#48cae4,#0096c7)"></div>
                    <div class="carousel-slide carousel-placeholder"
                        style="background:linear-gradient(135deg,#90e0ef,#48cae4)"></div>
                <?php endif; ?>
            </div>
            <button class="carousel-btn carousel-prev" id="carousel-prev" aria-label="Précédent"><i
                    class="fa-solid fa-chevron-left"></i></button>
            <button class="carousel-btn carousel-next" id="carousel-next" aria-label="Suivant"><i
                    class="fa-solid fa-chevron-right"></i></button>
            <div class="carousel-dots" id="carousel-dots"></div>
        </section>

        <!-- Hero — Nos tours guidés -->
        <section class="hero-booking-section" id="reserver">
            <div class="hero-booking-inner">
                <span class="product-badge"><?= $t['hero_badge'] ?></span>
                <h1 class="product-title"><?= $t['hero_title'] ?></h1>
                <p class="hero-description"><?= $t['hero_desc'] ?></p>

                <div class="tour-booking-grid">

                    <!-- TOUR 1 — CITY -->
                    <div class="tour-booking-card">
                        <div class="tour-booking-img-wrap">
                            <img src="/images/tour1.jpg" alt="Tour City Nice" class="tour-booking-img">
                            <span class="tour-booking-badge tour-badge-city">🏙️ CITY</span>
                        </div>
                        <div class="tour-booking-info">
                            <p class="tour-booking-route">Promenade des Anglais, Vieux-Nice &amp; le Port</p>
                            <div class="tour-booking-meta">
                                <span class="tour-meta-duration"><i class="fa-regular fa-clock"></i> 2h</span>
                                <span class="tour-meta-price">75 € / pers.</span>
                            </div>
                            <p class="tour-booking-vehicle"><?= $t['tour_vehicle_note'] ?></p>
                            <button class="tour-book-btn" onclick="toggleBooking(this)">
                                <?= $t['book_btn'] ?> <span class="tour-book-arrow">↓</span>
                            </button>
                        </div>
                        <div class="tour-booking-widget" style="display:none">
                            <div class="bokunWidget"
                                data-src="https://widgets.bokun.io/online-sales/9a25aafd-ff84-47d1-824a-49fa6a64a423/experience-calendar/1194328">
                            </div>
                            <noscript>Please enable javascript in your browser to book</noscript>
                        </div>
                    </div>

                    <!-- TOUR 2 — FRENCH RIVIERA -->
                    <div class="tour-booking-card">
                        <div class="tour-booking-img-wrap">
                            <img src="/images/tour2.jpg" alt="Tour French Riviera" class="tour-booking-img">
                            <span class="tour-booking-badge tour-badge-riviera">🌊 FRENCH RIVIERA</span>
                        </div>
                        <div class="tour-booking-info">
                            <p class="tour-booking-route">Villefranche-sur-Mer, Saint-Jean-Cap-Ferrat, Beaulieu-sur-Mer
                            </p>
                            <div class="tour-booking-meta">
                                <span class="tour-meta-duration"><i class="fa-regular fa-clock"></i> 2h30</span>
                                <span class="tour-meta-price">90 € / pers.</span>
                            </div>
                            <p class="tour-booking-vehicle"><?= $t['tour_vehicle_note'] ?></p>
                            <button class="tour-book-btn" onclick="toggleBooking(this)">
                                <?= $t['book_btn'] ?> <span class="tour-book-arrow">↓</span>
                            </button>
                        </div>
                        <div class="tour-booking-widget" style="display:none">
                            <div class="bokunWidget"
                                data-src="https://widgets.bokun.io/online-sales/9a25aafd-ff84-47d1-824a-49fa6a64a423/experience-calendar/1197812">
                            </div>
                            <noscript>Please enable javascript in your browser to book</noscript>
                        </div>
                    </div>

                    <!-- TOUR 3 — SUNSET -->
                    <div class="tour-booking-card">
                        <div class="tour-booking-img-wrap">
                            <img src="/images/tour3.jpg" alt="Tour Sunset Nice" class="tour-booking-img">
                            <span class="tour-booking-badge tour-badge-sunset">🌅 SUNSET</span>
                        </div>
                        <div class="tour-booking-info">
                            <p class="tour-booking-route">Le parcours coucher de soleil sur la Riviera</p>
                            <div class="tour-booking-meta">
                                <span class="tour-meta-duration"><i class="fa-regular fa-clock"></i> 2h30</span>
                                <span class="tour-meta-price">100 € / pers.</span>
                            </div>
                            <p class="tour-booking-vehicle"><?= $t['tour_vehicle_note'] ?></p>
                            <button class="tour-book-btn" onclick="toggleBooking(this)">
                                <?= $t['book_btn'] ?> <span class="tour-book-arrow">↓</span>
                            </button>
                        </div>
                        <div class="tour-booking-widget" style="display:none">
                            <div class="bokunWidget"
                                data-src="https://widgets.bokun.io/online-sales/9a25aafd-ff84-47d1-824a-49fa6a64a423/experience-calendar/1197894">
                            </div>
                            <noscript>Please enable javascript in your browser to book</noscript>
                        </div>
                    </div>

                </div>

                <div class="trust-badges-hero">
                    <span><i class="fa-duotone fa-solid fa-bolt trust-icon"></i><?= $t['trust_elec'] ?></span>
                    <span><i class="fa-duotone fa-solid fa-compass trust-icon"></i><?= $t['trust_guide'] ?></span>
                    <span><i class="fa-duotone fa-solid fa-id-card trust-icon"></i><?= $t['trust_permit'] ?></span>
                    <span><i class="fa-duotone fa-solid fa-rotate-left trust-icon"></i><?= $t['trust_cancel'] ?></span>
                    <span><i class="fa-duotone fa-solid fa-lock trust-icon"></i><?= $t['trust_caution'] ?></span>
                </div>

                <div class="tours-details-accordion">
                    <p class="tours-details-label"><?= $t['tours_details_label'] ?></p>
                    <p class="tours-details-body"><?= $t['tours_details_body'] ?></p>
                    <div class="tours-desc-encart">
                        <h3 class="tours-desc-encart-title"><?= $t['tours_desc_title'] ?></h3>
                        <?php
                        $tours_acc = [
                            ['title' => $t['tour1_title'], 'body' => $t['tour1_body']],
                            ['title' => $t['tour2_title'], 'body' => $t['tour2_body']],
                            ['title' => $t['tour3_title'], 'body' => $t['tour3_body']],
                        ];
                        foreach ($tours_acc as $tour): ?>
                            <div class="faq-item">
                                <button class="faq-toggle" onclick="toggleFaq(this)" aria-expanded="false">
                                    <span><?= $tour['title'] ?></span>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2">
                                        <path d="m6 9 6 6 6-6" />
                                    </svg>
                                </button>
                                <div class="faq-answer">
                                    <p><?= $tour['body'] ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </section>

        <!-- ── Avis clients ── -->
        <?php if (!empty($googleReviews['reviews'])): ?>
        <section class="reviews-section">
            <div class="reviews-inner">
                <span class="product-badge">AVIS CLIENTS</span>
                <div class="reviews-summary">
                    <div class="reviews-platform-block">
                        <svg width="36" height="36" viewBox="0 0 24 24" style="flex-shrink:0"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                        <div class="reviews-score"><?= number_format($googleReviews['rating'], 1) ?></div>
                        <div class="reviews-summary-right">
                            <div class="reviews-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <svg viewBox="0 0 24 24" fill="<?= $i <= round($googleReviews['rating']) ? '#fbbf24' : 'none' ?>" stroke="#fbbf24" stroke-width="1.5"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/></svg>
                                <?php endfor; ?>
                            </div>
                            <p class="reviews-total"><?= $googleReviews['total'] ?> avis Google</p>
                        </div>
                    </div>
                    <?php if (!empty($gygMeta['total_count'])): ?>
                    <div class="reviews-platform-divider"></div>
                    <div class="reviews-platform-block">
                        <img src="/images/gyg_logo_short.png" width="36" height="36" alt="GYG" style="border-radius:6px;flex-shrink:0">
                        <div class="reviews-score"><?= number_format($gygMeta['overall_rating'], 1) ?></div>
                        <div class="reviews-summary-right">
                            <div class="reviews-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <svg viewBox="0 0 24 24" fill="<?= $i <= round($gygMeta['overall_rating']) ? '#fbbf24' : 'none' ?>" stroke="#fbbf24" stroke-width="1.5"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/></svg>
                                <?php endfor; ?>
                            </div>
                            <p class="reviews-total"><?= $gygMeta['total_count'] ?> avis GetYourGuide</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="reviews-grid">
                    <?php foreach ($googleReviews['reviews'] as $review): ?>
                    <div class="review-card">
                        <div class="review-header">
                            <img src="<?= htmlspecialchars($review['author_photo_url'] ?? '') ?>" alt="" class="review-avatar" onerror="this.style.display='none'">
                            <div class="review-meta">
                                <strong class="review-author"><?= htmlspecialchars($review['author_name'] ?? '') ?></strong>
                                <span class="review-date"><?= htmlspecialchars($review['relative_date'] ?? '') ?></span>
                            </div>
                            <div class="review-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <svg viewBox="0 0 24 24" fill="#fbbf24" stroke="#fbbf24" stroke-width="1.5" width="13" height="13"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/></svg>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <p class="review-text"><?= nl2br(htmlspecialchars($review['review_text'] ?? '')) ?></p>
                        <div class="review-source review-source--<?= $review['source'] ?>">
                            <?php if ($review['source'] === 'google'): ?>
                                <svg width="14" height="14" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                                Google
                            <?php else: ?>
                                <img src="/images/gyg_logo_short.png" width="14" height="14" alt="GYG" style="border-radius:3px">
                                GetYourGuide
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="reviews-cta-group">
                    <a href="https://www.google.com/maps/search/?api=1&query=NOMADRIVE+Nice&query_place_id=ChIJGdQvT-7bzRIRgGC_8yxIQKc" target="_blank" rel="noopener" class="reviews-cta reviews-cta--google">
                        <svg width="16" height="16" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                        Avis Google
                    </a>
                    <a href="https://www.getyourguide.com/nice-l314/discover-the-riviera-and-nice-by-electric-vehicle-t1285889/" target="_blank" rel="noopener" class="reviews-cta reviews-cta--gyg">
                        <img src="/images/gyg_logo_short.png" width="16" height="16" alt="GYG" style="border-radius:3px">
                        GetYourGuide
                    </a>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Contact Section -->
        <section class="contact-section" id="contact">
            <div class="contact-inner">
                <span class="product-badge"><?= $t['contact_label'] ?></span>

                <div class="contact-grid">
                    <!-- Formulaire de contact -->
                    <div class="contact-form-container">
                        <h3><?= $t['contact_form_h3'] ?></h3>
                        <form id="contact-form" class="contact-form" onsubmit="submitContact(event)">
                            <div class="form-group">
                                <label for="name"><?= $t['contact_name'] ?></label>
                                <input type="text" id="name" name="name" required
                                    placeholder="<?= $t['contact_ph_name'] ?>">
                            </div>
                            <div class="form-group">
                                <label for="email"><?= $t['contact_email'] ?></label>
                                <input type="email" id="email" name="email" required
                                    placeholder="<?= $t['contact_ph_email'] ?>">
                            </div>
                            <div class="form-group">
                                <label for="message"><?= $t['contact_msg'] ?></label>
                                <textarea id="message" name="message" rows="4" required
                                    placeholder="<?= $t['contact_ph_msg'] ?>"></textarea>
                            </div>
                            <!-- Hidden token field -->
                            <input type="hidden" name="recaptcha_token" id="recaptcha_token">
                            <input type="hidden" name="action" value="contact">

                            <button type="submit" class="submit-btn" id="submit-btn"><?= $t['contact_send'] ?></button>
                            <div id="form-msg" class="form-msg"></div>
                        </form>
                    </div>

                    <!-- Infos de contact & Map -->
                    <div class="contact-info-container">
                        <div class="contact-direct-links">
                            <a href="tel:+33633338792" class="contact-link">
                                <i class="fa-solid fa-phone contact-icon"></i> <?= $t['contact_call'] ?>
                            </a>
                            <a href="https://wa.me/33633338792" target="_blank" class="contact-link whatsapp">
                                <svg class="contact-icon brand-svg" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24" fill="currentColor">
                                    <path
                                        d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                                </svg> WhatsApp
                            </a>
                            <a href="https://www.instagram.com/noma.drive/" target="_blank"
                                class="contact-link instagram">
                                <svg class="contact-icon brand-svg" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24" fill="currentColor">
                                    <path
                                        d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
                                </svg> Instagram
                            </a>
                        </div>

                        <div class="contact-address">
                            <p><strong><?= $t['contact_address_label'] ?></strong><br>
                                2 place Guynemer<br>06300 Nice, France</p>
                        </div>

                        <div class="map-container">
                            <iframe width="100%" height="250" frameborder="0" style="border:0; border-radius: 12px;"
                                src="https://maps.google.com/maps?q=2+place+Guynemer,+06300+Nice&output=embed&z=16"
                                allowfullscreen loading="lazy">
                            </iframe>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <!-- Footer -->
    <footer class="site-footer">
        <div class="footer-inner">
            <div class="footer-brand">
                <h3 class="footer-logo">NOMADRIVE</h3>
                <p class="footer-tagline"><?= $t['footer_tagline'] ?></p>
            </div>
            <div class="footer-links">
                <div class="footer-col">
                    <h4><?= $t['footer_tours'] ?></h4>
                    <a href="#reserver">City</a>
                    <a href="#reserver">French Riviera</a>
                    <a href="#reserver">Sunset</a>
                </div>
                <div class="footer-col">
                    <h4><?= $t['footer_info'] ?></h4>
                    <a href="/faq.php?lang=<?= $lang ?>">FAQ</a>
                    <a href="/cgv.php?lang=<?= $lang ?>">CGV</a>
                    <a href="/legal.php?lang=<?= $lang ?>"><?= $t['footer_legal'] ?></a>
                </div>
                <div class="footer-col">
                    <h4><?= $t['footer_contact'] ?></h4>
                    <a href="mailto:contact@nomadrive.fr">contact@nomadrive.fr</a>
                    <p><?= $t['footer_hours'] ?></p>
                    <p>2 place Guynemer, 06300 Nice</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p><?= $t['footer_copyright'] ?></p>
                <div class="footer-legal">
                    <a href="/legal.php?lang=<?= $lang ?>#donnees"><?= $t['footer_privacy'] ?></a>
                    <a href="/cgv.php?lang=<?= $lang ?>">CGV</a>
                    <a href="/legal.php?lang=<?= $lang ?>"><?= $t['footer_legal'] ?></a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // ── Carousel ──────────────────────────────────────────────────────────
        (function () {
            const track = document.getElementById('carousel-track');
            const slides = track ? track.querySelectorAll('.carousel-slide') : [];
            const dotsEl = document.getElementById('carousel-dots');
            if (!slides.length) return;
            let current = 0, timer;

            // Créer les dots
            slides.forEach((_, i) => {
                const d = document.createElement('button');
                d.className = 'carousel-dot' + (i === 0 ? ' active' : '');
                d.setAttribute('aria-label', 'Slide ' + (i + 1));
                d.onclick = () => goTo(i);
                dotsEl.appendChild(d);
            });

            function goTo(n) {
                current = (n + slides.length) % slides.length;
                track.style.transform = `translateX(-${current * 100}%)`;
                dotsEl.querySelectorAll('.carousel-dot').forEach((d, i) =>
                    d.classList.toggle('active', i === current));
            }

            document.getElementById('carousel-prev').onclick = () => { goTo(current - 1); resetTimer(); };
            document.getElementById('carousel-next').onclick = () => { goTo(current + 1); resetTimer(); };

            function resetTimer() { clearInterval(timer); timer = setInterval(() => goTo(current + 1), 4500); }
            resetTimer();
        })();

        // FAQ Accordion
        function toggleFaq(btn) {
            const item = btn.parentElement;
            const isOpen = item.classList.contains('open');
            item.classList.toggle('open');
            btn.setAttribute('aria-expanded', !isOpen);
        }

        // Tour booking toggle
        function toggleBooking(btn) {
            const card = btn.closest('.tour-booking-card');
            const widget = card.querySelector('.tour-booking-widget');
            const arrow = btn.querySelector('.tour-book-arrow');
            const isOpen = widget.style.display !== 'none';

            widget.style.display = isOpen ? 'none' : 'block';
            arrow.textContent = isOpen ? '↓' : '↑';

            if (!isOpen) {
                setTimeout(() => widget.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 80);
            }
        }

        // Hamburger mobile menu
        const hamburgerBtn = document.getElementById('hamburger-btn');
        const navLinks = document.getElementById('nav-links');
        if (hamburgerBtn) {
            hamburgerBtn.addEventListener('click', () => {
                const isOpen = navLinks.classList.toggle('mobile-open');
                hamburgerBtn.classList.toggle('active');
                hamburgerBtn.setAttribute('aria-expanded', isOpen);
                hamburgerBtn.setAttribute('aria-label', isOpen ? 'Fermer le menu' : 'Ouvrir le menu');
            });
            navLinks.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', () => {
                    navLinks.classList.remove('mobile-open');
                    hamburgerBtn.classList.remove('active');
                    hamburgerBtn.setAttribute('aria-expanded', 'false');
                });
            });
        }

        // Nav scroll effect
        window.addEventListener('scroll', () => {
            const nav = document.querySelector('.main-nav');
            if (window.scrollY > 50) nav.classList.add('scrolled');
            else nav.classList.remove('scrolled');
        });

        // Afficher le bouton "Réserver" uniquement quand #reserver est passé
        (function () {
            const navCtaBtn = document.querySelector('.nav-cta-btn');
            const bookingSection = document.getElementById('reserver');
            if (!navCtaBtn || !bookingSection) return;
            const observer = new IntersectionObserver(([entry]) => {
                const pastSection = !entry.isIntersecting && entry.boundingClientRect.top < 0;
                navCtaBtn.classList.toggle('visible', pastSection);
            }, { threshold: 0 });
            observer.observe(bookingSection);
        })();

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        // Form Submit Ajax with reCAPTCHA Enterprise
        function submitContact(e) {
            e.preventDefault();
            const form = document.getElementById('contact-form');
            const submitBtn = document.getElementById('submit-btn');
            const msgDiv = document.getElementById('form-msg');
            const siteKey = "<?= defined('RECAPTCHA_SITE_KEY') ? RECAPTCHA_SITE_KEY : '6LchIXwsAAAAAFZ__UAGkDra42FaWW7XpdDH3NiK' ?>";


            submitBtn.disabled = true;
            submitBtn.innerText = "Envoi en cours...";

            grecaptcha.enterprise.ready(function () {
                grecaptcha.enterprise.execute(siteKey, { action: 'submit' }).then(function (token) {
                    document.getElementById('recaptcha_token').value = token;

                    const formData = new FormData(form);
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            msgDiv.style.display = 'block';
                            msgDiv.className = 'form-msg ' + (data.success ? 'success' : 'error');
                            msgDiv.innerText = data.message;
                            if (data.success) {
                                form.reset();
                            }
                        })
                        .catch(error => {
                            msgDiv.style.display = 'block';
                            msgDiv.className = 'form-msg error';
                            msgDiv.innerText = "Une erreur est survenue lors de l'envoi.";
                        })
                        .finally(() => {
                            submitBtn.disabled = false;
                            submitBtn.innerText = "Envoyer le message";
                        });
                });
            });
        }
    </script>

    <!-- Floating Social Buttons -->
    <div class="floating-social">
        <a href="https://wa.me/33633338792" target="_blank" rel="noopener" class="float-btn float-whatsapp"
            aria-label="WhatsApp">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22">
                <path
                    d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
            </svg>
        </a>
        <a href="https://www.instagram.com/noma.drive/" target="_blank" rel="noopener" class="float-btn float-instagram"
            aria-label="Instagram">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22">
                <path
                    d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
            </svg>
        </a>
    </div>

    <!-- Bokun Booking Widget (fonctionnel — exempt de consentement) -->
    <script type="text/javascript"
        src="https://widgets.bokun.io/assets/javascripts/apps/build/BokunWidgetsLoader.js?bookingChannelUUID=9a25aafd-ff84-47d1-824a-49fa6a64a423"
        async></script>

    <!-- Cookie Consent Banner (pour Google Tag Manager / analytics uniquement) -->
    <div class="cookie-banner" id="cookie-banner" role="dialog" aria-label="Cookies">
        <div class="cookie-banner-inner">
            <div class="cookie-banner-icon">🍪</div>
            <div class="cookie-banner-text">
                <?php if ($lang === 'fr'): ?>
                    Ce site utilise des cookies de mesure d'audience pour améliorer votre expérience.
                    <a href="/legal.php?lang=fr#cookies">En savoir plus</a>
                <?php elseif ($lang === 'it'): ?>
                    Questo sito utilizza cookie di analisi per migliorare la tua esperienza.
                    <a href="/legal.php?lang=en#cookies">Saperne di più</a>
                <?php else: ?>
                    This site uses audience measurement cookies to improve your experience.
                    <a href="/legal.php?lang=en#cookies">Learn more</a>
                <?php endif; ?>
            </div>
            <div class="cookie-banner-actions">
                <button class="cookie-btn cookie-btn-refuse" id="cookie-refuse">
                    <?= $lang === 'fr' ? 'Refuser' : ($lang === 'it' ? 'Rifiuta' : 'Refuse') ?>
                </button>
                <button class="cookie-btn cookie-btn-accept" id="cookie-accept">
                    <?= $lang === 'fr' ? 'Accepter' : ($lang === 'it' ? 'Accetta' : 'Accept') ?>
                </button>
            </div>
        </div>
    </div>

    <script>
        // ── Cookie Consent Manager (GTM uniquement) ──────────────────────────────
        (function () {
            const COOKIE_KEY = 'nd_cookie_consent';
            const EXPIRY_DAYS = 180; // 6 mois
            const banner = document.getElementById('cookie-banner');

            function getConsent() {
                try {
                    const data = JSON.parse(localStorage.getItem(COOKIE_KEY));
                    if (data && data.expiry > Date.now()) return data.value;
                    localStorage.removeItem(COOKIE_KEY);
                } catch (e) { }
                return null;
            }

            function setConsent(value) {
                const expiry = Date.now() + (EXPIRY_DAYS * 24 * 60 * 60 * 1000);
                localStorage.setItem(COOKIE_KEY, JSON.stringify({ value, expiry }));
            }

            function loadGTM() {
                if (typeof GTM_ID === 'undefined' || document.getElementById('gtm-script')) return;
                // GTM inline loader
                (function (w, d, s, l, i) {
                    w[l] = w[l] || []; w[l].push({
                        'gtm.start':
                            new Date().getTime(), event: 'gtm.js'
                    }); var f = d.getElementsByTagName(s)[0],
                        j = d.createElement(s), dl = l != 'dataLayer' ? '&l=' + l : ''; j.async = true; j.src =
                            'https://www.googletagmanager.com/gtm.js?id=' + i + dl; j.id = 'gtm-script';
                    f.parentNode.insertBefore(j, f);
                })(window, document, 'script', 'dataLayer', GTM_ID);
            }

            // Init
            const consent = getConsent();
            if (consent === 'accepted') {
                loadGTM();
            } else if (consent === null) {
                setTimeout(function () { banner.classList.add('visible'); }, 800);
            }
            // consent === 'refused' → do nothing, no GTM

            // Buttons
            document.getElementById('cookie-accept').addEventListener('click', function () {
                setConsent('accepted');
                banner.classList.remove('visible');
                loadGTM();
            });

            document.getElementById('cookie-refuse').addEventListener('click', function () {
                setConsent('refused');
                banner.classList.remove('visible');
            });
        })();
    </script>

</body>

</html>