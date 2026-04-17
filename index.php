<?php
require_once __DIR__ . '/config.php';

// ─── Langue ──────────────────────────────────────────────────────────────────
$lang = in_array($_GET['lang'] ?? '', ['fr', 'en', 'it']) ? $_GET['lang'] : 'fr';

$t = [
    'fr' => [
        'nav_tours'     => 'Nos tours',
        'nav_faq'       => 'FAQ',
        'nav_contact'   => 'Contact',
        'nav_book'      => 'Réserver',
        'hero_badge'    => 'TOURS GUIDÉS À NICE',
        'hero_title'    => 'Découvrez Nice avec nos tours accompagnés en voiture électrique',
        'hero_desc'     => 'Prenez le volant d\'une <strong>Citroën Ami</strong> ou d\'un <strong>Fiat Topolino 100% électrique</strong> et suivez notre guide à travers les plus beaux quartiers de Nice. <strong>Permis de conduire requis</strong>, itinéraire sécurisé, accompagnement personnalisé.',
        'book_btn'      => 'Réserver ce tour →',
        'coming_soon'   => 'Bientôt disponible',
        'trust_elec'    => '100% électrique',
        'trust_guide'   => 'Suivez notre guide local !',
        'trust_permit'  => 'Permis B exigé',
        'trust_cancel'  => 'Annulation gratuite 24h',
        'tour_city_desc'    => 'Tour guidé Promenade des Anglais, Cimiez, Port, Château',
        'tour_riviera_desc' => 'Tour guidé Mont Boron, Cap-Ferrat & Villefranche',
        'tour_sunset_desc'  => 'Tour guidé coucher de soleil sur la Riviera',
        'faq_label'     => 'QUESTIONS FRÉQUENTES',
        'faq_title'     => 'Tout savoir sur nos tours guidés à Nice',
        'faq_q1' => 'Faut-il un permis de conduire pour participer à un tour ?',
        'faq_a1' => 'Oui, un <strong>permis de conduire valide</strong> (permis B ou équivalent) est obligatoire pour conduire nos véhicules lors du tour guidé. Le conducteur doit être en mesure de présenter son permis au départ du tour.',
        'faq_q2' => 'Puis-je garder le véhicule après le tour ?',
        'faq_a2' => 'Non, le véhicule est <strong>mis à disposition uniquement pendant la durée du tour guidé</strong>. Au retour, le véhicule est restitué au point de départ. Il ne s\'agit pas d\'une location libre.',
        'faq_q3' => 'Un enfant peut-il participer au tour ?',
        'faq_a3' => 'Oui, <strong>un enfant maximum</strong> peut être présent dans le véhicule, à condition d\'être <strong>obligatoirement accompagné d\'un adulte</strong> titulaire du permis de conduire. L\'adulte doit être le conducteur du véhicule.',
        'faq_q4' => 'Où a lieu le départ du tour ?',
        'faq_a4' => 'Le point de départ et de retour se situe au <strong>2 place Guynemer, 06300 Nice</strong>. Les instructions d\'accès vous seront communiquées par email après votre réservation.',
        'faq_q5' => 'Comment se déroule le tour guidé ?',
        'faq_a5' => 'À votre arrivée, un <strong>briefing rapide</strong> vous est donné. Vous prenez ensuite le volant de votre véhicule électrique et suivez l\'<strong>itinéraire guidé par GPS</strong> (tablette embarquée). Le parcours vous guide à travers les plus beaux quartiers de Nice avec des arrêts photo aux points d\'intérêt.',
        'contact_label' => 'CONTACT & ACCÈS',
        'contact_title' => 'Nous contacter ou nous trouver',
        'contact_form_h3'=> 'Envoyez-nous un message',
        'contact_send'  => 'Envoyer le message',
        'contact_name'  => 'Nom complet',
        'contact_email' => 'Email',
        'contact_msg'   => 'Message',
        'contact_ph_name'  => 'Votre nom',
        'contact_ph_email' => 'votre@email.com',
        'contact_ph_msg'   => 'Comment pouvons-nous vous aider ?',
        'contact_call'  => 'Nous appeler',
        'contact_address_label' => 'Point de départ des tours :',
        'footer_tagline'  => 'Tours guidés en voiture électrique à Nice.',
        'footer_tours'    => 'Nos tours',
        'footer_info'     => 'Infos',
        'footer_contact'  => 'Contact',
        'footer_hours'    => 'Lun–Dim · 9h–19h',
        'footer_copyright'=> '© 2026 NOMADRIVE — Tours guidés en voiture électrique à Nice. Tous droits réservés.',
        'footer_privacy'  => 'Confidentialité',
        'footer_legal'    => 'Mentions légales',
    ],
    'en' => [
        'nav_tours'     => 'Our tours',
        'nav_faq'       => 'FAQ',
        'nav_contact'   => 'Contact',
        'nav_book'      => 'Book now',
        'hero_badge'    => 'GUIDED TOURS IN NICE',
        'hero_title'    => 'Discover Nice on a guided electric car tour',
        'hero_desc'     => 'Take the wheel of a <strong>Citroën Ami</strong> or <strong>Fiat Topolino 100% electric</strong> and follow our guide through Nice\'s most beautiful neighbourhoods. <strong>Driving licence required</strong>, safe route, personalised experience.',
        'book_btn'      => 'Book this tour →',
        'coming_soon'   => 'Coming soon',
        'trust_elec'    => '100% electric',
        'trust_guide'   => 'Follow our local guide!',
        'trust_permit'  => 'Driving licence required',
        'trust_cancel'  => 'Free cancellation 24h',
        'tour_city_desc'    => 'Guided tour: Promenade des Anglais, Cimiez, Port, Castle Hill',
        'tour_riviera_desc' => 'Guided tour: Mont Boron, Cap-Ferrat & Villefranche',
        'tour_sunset_desc'  => 'Guided sunset tour along the Riviera',
        'faq_label'     => 'FREQUENTLY ASKED QUESTIONS',
        'faq_title'     => 'Everything about our guided tours in Nice',
        'faq_q1' => 'Is a driving licence required to join a tour?',
        'faq_a1' => 'Yes, a <strong>valid driving licence</strong> (category B or equivalent) is required to drive our vehicles during the guided tour. The driver must be able to present their licence at the start of the tour.',
        'faq_q2' => 'Can I keep the vehicle after the tour?',
        'faq_a2' => 'No, the vehicle is <strong>available only for the duration of the guided tour</strong>. It is returned to the departure point at the end. This is not a free-roaming rental.',
        'faq_q3' => 'Can a child join the tour?',
        'faq_a3' => 'Yes, <strong>one child</strong> may be in the vehicle, provided they are <strong>accompanied by a licensed adult driver</strong>. The adult must be the driver.',
        'faq_q4' => 'Where does the tour depart from?',
        'faq_a4' => 'The departure and return point is at <strong>2 place Guynemer, 06300 Nice</strong>. Access instructions will be sent to you by email after booking.',
        'faq_q5' => 'How does the guided tour work?',
        'faq_a5' => 'On arrival, you\'ll receive a <strong>quick briefing</strong>. You then take the wheel of your electric vehicle and follow the <strong>GPS-guided route</strong> (on-board tablet). The route takes you through Nice\'s most beautiful neighbourhoods with photo stops at points of interest.',
        'contact_label' => 'CONTACT & ACCESS',
        'contact_title' => 'Contact us or find us',
        'contact_form_h3'=> 'Send us a message',
        'contact_send'  => 'Send message',
        'contact_name'  => 'Full name',
        'contact_email' => 'Email',
        'contact_msg'   => 'Message',
        'contact_ph_name'  => 'Your name',
        'contact_ph_email' => 'your@email.com',
        'contact_ph_msg'   => 'How can we help you?',
        'contact_call'  => 'Call us',
        'contact_address_label' => 'Tour departure point:',
        'footer_tagline'  => 'Guided electric car tours in Nice.',
        'footer_tours'    => 'Our tours',
        'footer_info'     => 'Info',
        'footer_contact'  => 'Contact',
        'footer_hours'    => 'Mon–Sun · 9am–7pm',
        'footer_copyright'=> '© 2026 NOMADRIVE — Guided electric car tours in Nice. All rights reserved.',
        'footer_privacy'  => 'Privacy',
        'footer_legal'    => 'Legal notice',
    ],
    'it' => [
        'nav_tours'     => 'I nostri tour',
        'nav_faq'       => 'FAQ',
        'nav_contact'   => 'Contatto',
        'nav_book'      => 'Prenota',
        'hero_badge'    => 'TOUR GUIDATI A NIZZA',
        'hero_title'    => 'Scopri Nizza con i nostri tour guidati in auto elettrica',
        'hero_desc'     => 'Prendi il volante di una <strong>Citroën Ami</strong> o di una <strong>Fiat Topolino 100% elettrica</strong> e segui la nostra guida nei quartieri più belli di Nizza. <strong>Patente di guida richiesta</strong>, percorso sicuro, accompagnamento personalizzato.',
        'book_btn'      => 'Prenota questo tour →',
        'coming_soon'   => 'Presto disponibile',
        'trust_elec'    => '100% elettrico',
        'trust_guide'   => 'Segui la nostra guida!',
        'trust_permit'  => 'Patente richiesta',
        'trust_cancel'  => 'Cancellazione gratuita 24h',
        'tour_city_desc'    => 'Tour guidato: Promenade des Anglais, Cimiez, Porto, Collina del Castello',
        'tour_riviera_desc' => 'Tour guidato: Mont Boron, Cap-Ferrat & Villefranche',
        'tour_sunset_desc'  => 'Tour guidato al tramonto sulla Riviera',
        'faq_label'     => 'DOMANDE FREQUENTI',
        'faq_title'     => 'Tutto sui nostri tour guidati a Nizza',
        'faq_q1' => 'È necessaria la patente per partecipare a un tour?',
        'faq_a1' => 'Sì, una <strong>patente di guida valida</strong> (categoria B o equivalente) è obbligatoria per guidare i nostri veicoli durante il tour. Il conducente deve presentare la patente all\'inizio del tour.',
        'faq_q2' => 'Posso tenere il veicolo dopo il tour?',
        'faq_a2' => 'No, il veicolo è <strong>disponibile solo per la durata del tour guidato</strong>. Al ritorno, il veicolo viene restituito al punto di partenza. Non si tratta di un noleggio libero.',
        'faq_q3' => 'Un bambino può partecipare al tour?',
        'faq_a3' => 'Sì, <strong>un bambino al massimo</strong> può essere presente nel veicolo, a condizione di essere <strong>obbligatoriamente accompagnato da un adulto</strong> con patente. L\'adulto deve essere il conducente.',
        'faq_q4' => 'Da dove parte il tour?',
        'faq_a4' => 'Il punto di partenza e di ritorno è al <strong>2 place Guynemer, 06300 Nizza</strong>. Le istruzioni di accesso saranno comunicate per email dopo la prenotazione.',
        'faq_q5' => 'Come si svolge il tour guidato?',
        'faq_a5' => 'All\'arrivo, riceverete un <strong>rapido briefing</strong>. Poi prendete il volante del vostro veicolo elettrico e seguite il <strong>percorso guidato via GPS</strong> (tablet a bordo). Il percorso vi guida nei quartieri più belli di Nizza con soste fotografiche nei punti d\'interesse.',
        'contact_label' => 'CONTATTO & ACCESSO',
        'contact_title' => 'Contattaci o trovaci',
        'contact_form_h3'=> 'Inviaci un messaggio',
        'contact_send'  => 'Invia messaggio',
        'contact_name'  => 'Nome completo',
        'contact_email' => 'Email',
        'contact_msg'   => 'Messaggio',
        'contact_ph_name'  => 'Il tuo nome',
        'contact_ph_email' => 'tua@email.com',
        'contact_ph_msg'   => 'Come possiamo aiutarti?',
        'contact_call'  => 'Chiamaci',
        'contact_address_label' => 'Punto di partenza dei tour:',
        'footer_tagline'  => 'Tour guidati in auto elettrica a Nizza.',
        'footer_tours'    => 'I nostri tour',
        'footer_info'     => 'Info',
        'footer_contact'  => 'Contatto',
        'footer_hours'    => 'Lun–Dom · 9–19',
        'footer_copyright'=> '© 2026 NOMADRIVE — Tour guidati in auto elettrica a Nizza. Tutti i diritti riservati.',
        'footer_privacy'  => 'Privacy',
        'footer_legal'    => 'Note legali',
    ],
][$lang];

// ─── Carousel photos ─────────────────────────────────────────────────────────
$carousel_dir   = __DIR__ . '/images/carousel/';
$carousel_files = glob($carousel_dir . '*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];
shuffle($carousel_files);
$carousel_photos = array_slice($carousel_files, 0, 4);
// Fallback si pas encore de photos : placeholders CSS
$carousel_count  = max(count($carousel_photos), 4);

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
    if (!is_dir($madiDir)) $madiDir = dirname(__DIR__);
    $autoloadFile = $madiDir . '/vendor/autoload.php';
    if (file_exists($autoloadFile)) {
        require_once $autoloadFile;
    }

    // Lire les SMTP credentials depuis .env
    $smtpUsername = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
    $smtpPassword = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';

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

            $mail->setFrom('contact@madi.mt', 'NOMADRIVE');
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
        if (!$sent) $send_error = 'mail() failed';
    }

    if ($sent) {
        echo json_encode(['success' => true, 'message' => 'Votre message a bien été envoyé.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Une erreur est survenue lors de l\'envoi de l\'email.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- SEO Primary Meta -->
    <title>Tours Guidés en Voiture Électrique à Nice | NOMADRIVE</title>
    <meta name="description"
        content="Découvrez Nice avec nos tours guidés en voiture électrique : Citroën Ami et Fiat Topolino. Permis B requis, suivez notre guide local sur la Côte d'Azur. Réservez en ligne.">
    <meta name="keywords"
        content="tours guidés nice, visite nice voiture électrique, guide nice, louer ami citroën nice, topolino location nice, excursion voiture électrique nice, tour accompagné nice">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://nomadrive.fr/">

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

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:locale" content="fr_FR">
    <meta property="og:site_name" content="NOMADRIVE">
    <meta property="og:title" content="Tours Guidés en Voiture Électrique à Nice | NOMADRIVE">
    <meta property="og:description"
        content="Montez à bord et suivez notre guide ! Tours accompagnés en Citroën Ami & Fiat Topolino (Permis B requis). Réservez en ligne.">
    <meta property="og:url" content="https://nomadrive.fr/">
    <meta property="og:image" content="https://nomadrive.fr/images/hero-ami.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Tours Guidés en Voiture Électrique à Nice | NOMADRIVE">
    <meta name="twitter:description"
        content="Visitez Nice avec notre guide ! Suivez-le au volant d'une Citroën Ami ou Fiat Topolino 100% électrique.">
    <meta name="twitter:image" content="https://nomadrive.fr/images/hero-ami.png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://kit.fontawesome.com/494ceebc6d.js" crossorigin="anonymous"></script>

    <!-- reCAPTCHA Enterprise (sécurité — exempt de consentement CNIL) -->
    <script src="https://www.google.com/recaptcha/enterprise.js?render=<?= defined('RECAPTCHA_SITE_KEY') ? RECAPTCHA_SITE_KEY : '6LchIXwsAAAAAFZ__UAGkDra42FaWW7XpdDH3NiK' ?>"></script>

    <!-- Google Tag Manager — chargé uniquement après consentement cookies -->
    <script>var GTM_ID = 'GTM-5NH9D8CC';</script>

    <!-- JSON-LD Structured Data: LocalBusiness -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "LocalBusiness",
        "name": "NOMADRIVE",
        "description": "Tours guidés en voitures électriques à Nice. Citroën Ami et Fiat Topolino pour des découvertes avec guide local sur la Côte d'Azur.",
        "url": "https://nomadrive.fr",
        "telephone": "+33-XX-XX-XX-XX",
        "email": "contact@nomadrive.fr",
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
        "openingHours": "Mo-Su 09:00-19:00",
        "priceRange": "€€"
    }
    </script>

    <!-- JSON-LD: Product (Ami) -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Product",
        "name": "Tour Guidé Citroën Ami Électrique - Nice",
        "description": "Participez à un tour guidé avec guide local au volant d'une Citroën Ami 100% électrique à Nice. 2 places, idéale pour découvrir la Côte d'Azur.",
        "image": "https://nomadrive.fr/images/hero-ami.png",
        "brand": {
            "@type": "Brand",
            "name": "Citroën"
        },
        "offers": {
            "@type": "Offer",
            "priceCurrency": "EUR",
            "price": "29",
            "priceValidUntil": "2026-12-31",
            "availability": "https://schema.org/InStock",
            "url": "https://nomadrive.fr/#reserver",
            "unitText": "par heure"
        }
    }
    </script>

    <!-- JSON-LD: Product (Topolino) -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Product",
        "name": "Tour Guidé Fiat Topolino Électrique - Nice",
        "description": "Tour accompagné avec guide au volant d'un Fiat Topolino 100% électrique à Nice. 2 places, parfait pour explorer la Riviera.",
        "image": "https://nomadrive.fr/images/topolino-nice.png",
        "brand": {
            "@type": "Brand",
            "name": "Fiat"
        },
        "offers": {
            "@type": "Offer",
            "priceCurrency": "EUR",
            "price": "35",
            "priceValidUntil": "2026-12-31",
            "availability": "https://schema.org/InStock",
            "url": "https://nomadrive.fr/#reserver",
            "unitText": "par heure"
        }
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
            }
        ]
    }
    </script>
</head>

<body>

    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-5NH9D8CC" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>


    <!-- Navigation -->
    <nav class="main-nav" aria-label="Navigation principale">
        <div class="nav-inner">
            <a href="/?lang=<?= $lang ?>" class="nav-logo" aria-label="NOMADRIVE - Accueil">NOMADRIVE</a>
            <div class="nav-links" id="nav-links">
                <a href="#reserver"><?= $t['nav_tours'] ?></a>
                <a href="#faq"><?= $t['nav_faq'] ?></a>
                <a href="#contact"><?= $t['nav_contact'] ?></a>
            </div>
            <div class="nav-actions">
                <div class="lang-switcher">
                    <a href="?lang=fr" class="lang-btn <?= $lang === 'fr' ? 'active' : '' ?>">FR</a>
                    <a href="?lang=en" class="lang-btn <?= $lang === 'en' ? 'active' : '' ?>">EN</a>
                    <a href="?lang=it" class="lang-btn <?= $lang === 'it' ? 'active' : '' ?>">IT</a>
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
                            <img src="/images/carousel/<?= htmlspecialchars(basename($photo)) ?>" alt="NOMADRIVE Nice" loading="lazy">
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Placeholders tant qu'il n'y a pas encore de photos -->
                    <div class="carousel-slide carousel-placeholder" style="background:linear-gradient(135deg,#0077b6,#00b4d8)"></div>
                    <div class="carousel-slide carousel-placeholder" style="background:linear-gradient(135deg,#023e8a,#0077b6)"></div>
                    <div class="carousel-slide carousel-placeholder" style="background:linear-gradient(135deg,#48cae4,#0096c7)"></div>
                    <div class="carousel-slide carousel-placeholder" style="background:linear-gradient(135deg,#90e0ef,#48cae4)"></div>
                <?php endif; ?>
            </div>
            <button class="carousel-btn carousel-prev" id="carousel-prev" aria-label="Précédent"><i class="fa-solid fa-chevron-left"></i></button>
            <button class="carousel-btn carousel-next" id="carousel-next" aria-label="Suivant"><i class="fa-solid fa-chevron-right"></i></button>
            <div class="carousel-dots" id="carousel-dots"></div>
        </section>

        <!-- Hero — Nos tours guidés -->
        <section class="hero-booking-section" id="reserver">
            <div class="hero-booking-inner">
                <span class="product-badge"><?= $t['hero_badge'] ?></span>
                <h1 class="product-title"><?= $t['hero_title'] ?></h1>
                <p class="hero-description"><?= $t['hero_desc'] ?></p>

                <div class="booking-tours-grid">

                    <!-- Tour 1 — City (ACTIF) -->
                    <div class="booking-tour-card active" onclick="openBookingModal('city')" id="tour-card-city">
                        <div class="tour-card-icon city-icon">
                            <i class="fa-solid fa-city"></i>
                        </div>
                        <h2 class="booking-tour-name">City</h2>
                        <div class="booking-tour-meta">
                            <span><i class="fa-solid fa-clock"></i> ~2h</span>
                            <span><i class="fa-solid fa-route"></i> 20 km</span>
                        </div>
                        <p class="booking-tour-desc"><?= $t['tour_city_desc'] ?></p>
                        <ul class="tour-highlights">
                            <li><i class="fa-solid fa-location-dot"></i> Promenade des Anglais</li>
                            <li><i class="fa-solid fa-location-dot"></i> Vieille-Ville &amp; Cimiez</li>
                            <li><i class="fa-solid fa-location-dot"></i> Port &amp; Colline du Château</li>
                        </ul>
                        <div class="tour-cta-btn"><?= $t['book_btn'] ?></div>
                    </div>

                    <!-- Tour 2 — French Riviera (ACTIF) -->
                    <div class="booking-tour-card active" onclick="openBookingModal('riviera')" id="tour-card-riviera">
                        <div class="tour-card-icon riviera-icon">
                            <i class="fa-solid fa-water"></i>
                        </div>
                        <h2 class="booking-tour-name">French Riviera</h2>
                        <div class="booking-tour-meta">
                            <span><i class="fa-solid fa-clock"></i> ~2h30</span>
                            <span><i class="fa-solid fa-route"></i> 40 km</span>
                        </div>
                        <p class="booking-tour-desc"><?= $t['tour_riviera_desc'] ?></p>
                        <ul class="tour-highlights">
                            <li><i class="fa-solid fa-location-dot"></i> Mont Boron</li>
                            <li><i class="fa-solid fa-location-dot"></i> Saint-Jean-Cap-Ferrat</li>
                            <li><i class="fa-solid fa-location-dot"></i> Villefranche-sur-Mer</li>
                        </ul>
                        <div class="tour-cta-btn"><?= $t['book_btn'] ?></div>
                    </div>

                    <!-- Tour 3 — Sunset (ACTIF) -->
                    <div class="booking-tour-card active" onclick="openBookingModal('sunset')" id="tour-card-sunset">
                        <div class="tour-card-icon sunset-icon">
                            <i class="fa-solid fa-sun"></i>
                        </div>
                        <h2 class="booking-tour-name">Sunset</h2>
                        <div class="booking-tour-meta">
                            <span><i class="fa-solid fa-clock"></i> ~2h30</span>
                            <span><i class="fa-solid fa-route"></i> 20 km</span>
                        </div>
                        <p class="booking-tour-desc"><?= $t['tour_sunset_desc'] ?></p>
                        <ul class="tour-highlights">
                            <li><i class="fa-solid fa-location-dot"></i> Promenade du Paillon</li>
                            <li><i class="fa-solid fa-location-dot"></i> Colline du Château</li>
                            <li><i class="fa-solid fa-location-dot"></i> Vue sur la Méditerranée</li>
                        </ul>
                        <div class="tour-cta-btn"><?= $t['book_btn'] ?></div>
                    </div>

                </div>

                <div class="trust-badges-hero">
                    <span><i class="fa-duotone fa-solid fa-bolt trust-icon"></i><?= $t['trust_elec'] ?></span>
                    <span><i class="fa-duotone fa-solid fa-compass trust-icon"></i><?= $t['trust_guide'] ?></span>
                    <span><i class="fa-duotone fa-solid fa-id-card trust-icon"></i><?= $t['trust_permit'] ?></span>
                    <span><i class="fa-duotone fa-solid fa-rotate-left trust-icon"></i><?= $t['trust_cancel'] ?></span>
                </div>
            </div>
        </section>

        <!-- Modal Réservation Bokun (plein écran) -->
        <div class="booking-modal" id="booking-modal">
            <div class="booking-modal-content">
                <button class="booking-modal-close" onclick="closeBookingModal()" aria-label="Fermer"><i class="fa-solid fa-xmark"></i></button>
                <div class="booking-modal-body" id="booking-modal-body">
                    <!-- Le widget Bokun sera injecté ici -->
                </div>
            </div>
        </div>

        <!-- FAQ Section -->
        <section class="faq-section" id="faq">
            <div class="faq-inner">
                <span class="section-label"><?= $t['faq_label'] ?></span>
                <h2 class="faq-title"><?= $t['faq_title'] ?></h2>
                <div class="faq-list">
                    <?php foreach (range(1, 5) as $i): ?>
                    <div class="faq-item <?= $i === 1 ? 'open' : '' ?>">
                        <button class="faq-toggle" onclick="toggleFaq(this)" aria-expanded="<?= $i === 1 ? 'true' : 'false' ?>">
                            <span><?= $t["faq_q$i"] ?></span>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="faq-answer"><p><?= $t["faq_a$i"] ?></p></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>



        <!-- Contact Section -->
        <section class="contact-section" id="contact">
            <div class="contact-inner">
                <span class="section-label"><?= $t['contact_label'] ?></span>
                <h2 class="contact-title"><?= $t['contact_title'] ?></h2>

                <div class="contact-grid">
                    <!-- Formulaire de contact -->
                    <div class="contact-form-container">
                        <h3><?= $t['contact_form_h3'] ?></h3>
                        <form id="contact-form" class="contact-form" onsubmit="submitContact(event)">
                            <div class="form-group">
                                <label for="name"><?= $t['contact_name'] ?></label>
                                <input type="text" id="name" name="name" required placeholder="<?= $t['contact_ph_name'] ?>">
                            </div>
                            <div class="form-group">
                                <label for="email"><?= $t['contact_email'] ?></label>
                                <input type="email" id="email" name="email" required placeholder="<?= $t['contact_ph_email'] ?>">
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
                                <svg class="contact-icon brand-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg> WhatsApp
                            </a>
                            <a href="https://www.instagram.com/noma.drive/" target="_blank" class="contact-link instagram">
                                <svg class="contact-icon brand-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg> Instagram
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
                    <a href="#faq">FAQ</a>
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
        (function() {
            const track  = document.getElementById('carousel-track');
            const slides = track ? track.querySelectorAll('.carousel-slide') : [];
            const dotsEl = document.getElementById('carousel-dots');
            if (!slides.length) return;
            let current = 0, timer;

            // Créer les dots
            slides.forEach((_, i) => {
                const d = document.createElement('button');
                d.className = 'carousel-dot' + (i === 0 ? ' active' : '');
                d.setAttribute('aria-label', 'Slide ' + (i+1));
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

        // Booking Modal
        const BOKUN_LANG = '<?= $lang ?>'; // fr | en | it
        const TOUR_BOKUN_MAP = {
            city: 'https://widgets.bokun.io/online-sales/9a25aafd-ff84-47d1-824a-49fa6a64a423/experience/1194328',
            riviera: 'https://widgets.bokun.io/online-sales/9a25aafd-ff84-47d1-824a-49fa6a64a423/experience/1197812',
            sunset: 'https://widgets.bokun.io/online-sales/9a25aafd-ff84-47d1-824a-49fa6a64a423/experience/1197894',
        };

        function openBookingModal(tourKey) {
            const url = TOUR_BOKUN_MAP[tourKey];
            if (!url) return;
            const modal = document.getElementById('booking-modal');
            const body = document.getElementById('booking-modal-body');
            body.innerHTML = '<div class="bokunWidget" data-src="' + url + '?lang=' + BOKUN_LANG + '"></div>';
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
            // Re-trigger Bokun widget loader
            if (window.BokunWidgetsLoader) {
                window.BokunWidgetsLoader.init();
            }
        }

        function closeBookingModal() {
            const modal = document.getElementById('booking-modal');
            modal.classList.remove('open');
            document.body.style.overflow = '';
            document.getElementById('booking-modal-body').innerHTML = '';
        }

        // Fermer modal avec Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeBookingModal();
        });

        // Nav scroll effect
        window.addEventListener('scroll', () => {
            const nav = document.querySelector('.main-nav');
            if (window.scrollY > 50) nav.classList.add('scrolled');
            else nav.classList.remove('scrolled');
        });

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

    <!-- Bokun Booking Widget (fonctionnel — exempt de consentement) -->
    <script type="text/javascript" src="https://widgets.bokun.io/assets/javascripts/apps/build/BokunWidgetsLoader.js?bookingChannelUUID=9a25aafd-ff84-47d1-824a-49fa6a64a423" async></script>

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
    (function() {
        const COOKIE_KEY = 'nd_cookie_consent';
        const EXPIRY_DAYS = 180; // 6 mois
        const banner = document.getElementById('cookie-banner');

        function getConsent() {
            try {
                const data = JSON.parse(localStorage.getItem(COOKIE_KEY));
                if (data && data.expiry > Date.now()) return data.value;
                localStorage.removeItem(COOKIE_KEY);
            } catch(e) {}
            return null;
        }

        function setConsent(value) {
            const expiry = Date.now() + (EXPIRY_DAYS * 24 * 60 * 60 * 1000);
            localStorage.setItem(COOKIE_KEY, JSON.stringify({ value, expiry }));
        }

        function loadGTM() {
            if (typeof GTM_ID === 'undefined' || document.getElementById('gtm-script')) return;
            // GTM inline loader
            (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;j.id='gtm-script';
            f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer',GTM_ID);
        }

        // Init
        const consent = getConsent();
        if (consent === 'accepted') {
            loadGTM();
        } else if (consent === null) {
            setTimeout(function() { banner.classList.add('visible'); }, 800);
        }
        // consent === 'refused' → do nothing, no GTM

        // Buttons
        document.getElementById('cookie-accept').addEventListener('click', function() {
            setConsent('accepted');
            banner.classList.remove('visible');
            loadGTM();
        });

        document.getElementById('cookie-refuse').addEventListener('click', function() {
            setConsent('refused');
            banner.classList.remove('visible');
        });
    })();
    </script>

</body>

</html>