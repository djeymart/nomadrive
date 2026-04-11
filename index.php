<?php
require_once __DIR__ . '/config.php';

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

    // Send Mail
    $to = 'contact@nomadrive.fr';
    $subject = "Nouveau message de $name - (Contact NOMADRIVE)";
    $body = "Nom: $name\nEmail: $email\n\nMessage:\n$message";
    $headers = "From: $email" . "\r\n" .
        "Reply-To: $email" . "\r\n" .
        "X-Mailer: PHP/" . phpversion();

    if (mail($to, $subject, $body, $headers)) {
        echo json_encode(['success' => true, 'message' => 'Votre message a bien été envoyé.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Une erreur est survenue lors de l\'envoi de l\'email.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">

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
    <link rel="alternate" hreflang="fr" href="https://nomadrive.fr/">
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

    <!-- reCAPTCHA Enterprise -->
    <script
        src="https://www.google.com/recaptcha/enterprise.js?render=<?= defined('RECAPTCHA_SITE_KEY') ? RECAPTCHA_SITE_KEY : '6LchIXwsAAAAAFZ__UAGkDra42FaWW7XpdDH3NiK' ?>"></script>

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



    <!-- Navigation -->
    <nav class="main-nav" aria-label="Navigation principale">
        <div class="nav-inner">
            <a href="/" class="nav-logo" aria-label="NOMADRIVE - Accueil">NOMADRIVE</a>
            <div class="nav-links" id="nav-links">
                <a href="#reserver">Nos tours</a>
                <a href="#faq">FAQ</a>
                <a href="#contact">Contact</a>
            </div>
            <div class="nav-actions">
                <a href="#reserver" class="nav-cta-btn">Réserver</a>
                <button class="hamburger" id="hamburger-btn" aria-label="Ouvrir le menu" aria-expanded="false">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </div>
    </nav>

    <main>

        <!-- Hero — Nos tours guidés -->
        <section class="hero-booking-section" id="reserver">
            <div class="hero-booking-inner">
                <span class="product-badge">TOURS GUIDÉS À NICE</span>
                <h1 class="product-title">Découvrez Nice avec nos tours accompagnés en voiture électrique</h1>
                <p class="hero-description">Prenez le volant d'une <strong>Citroën Ami</strong> ou d'un <strong>Fiat
                        Topolino 100% électrique</strong> et suivez notre guide à travers les plus beaux quartiers de
                    Nice. <strong>Permis de conduire requis</strong>, itinéraire sécurisé, accompagnement personnalisé.
                </p>

                <div class="booking-tours-grid">

                    <!-- Tour 1 — City (ACTIF) -->
                    <div class="booking-tour-card active" onclick="openBookingModal('city')" id="tour-card-city">
                        <div class="booking-tour-header">
                            <span class="booking-tour-emoji">🏙️</span>
                            <div>
                                <h2 class="booking-tour-name">City</h2>
                                <p class="booking-tour-meta">~2h · 20 km</p>
                            </div>
                        </div>
                        <p class="booking-tour-desc">Tour guidé Promenade des Anglais, Cimiez, Port, Chateau</p>
                        <div class="tour-cta-btn">Réserver ce tour →</div>
                    </div>

                    <!-- Tour 2 — French Riviera (BIENTÔT) -->
                    <div class="booking-tour-card coming-soon" id="tour-card-riviera">
                        <div class="booking-tour-header">
                            <span class="booking-tour-emoji">🌴</span>
                            <div>
                                <h2 class="booking-tour-name">French Riviera</h2>
                                <p class="booking-tour-meta">~2h30 · 40 km</p>
                            </div>
                        </div>
                        <p class="booking-tour-desc">Tour guidé Mont Boron, Cap-Ferrat & Villefranche</p>
                        <div class="coming-soon-badge">
                            <span>Bientôt disponible</span>
                        </div>
                    </div>

                    <!-- Tour 3 — Sunset (BIENTÔT) -->
                    <div class="booking-tour-card coming-soon" id="tour-card-sunset">
                        <div class="booking-tour-header">
                            <span class="booking-tour-emoji">🌅</span>
                            <div>
                                <h2 class="booking-tour-name">Sunset</h2>
                                <p class="booking-tour-meta">~2h · 20 km</p>
                            </div>
                        </div>
                        <p class="booking-tour-desc">Tour guidé coucher de soleil sur la Riviera</p>
                        <div class="coming-soon-badge">
                            <span>Bientôt disponible</span>
                        </div>
                    </div>

                </div>

                <div class="trust-badges-hero">
                    <span>⚡ 100% électrique</span>
                    <span>👨‍✈️ Suivez notre guide local !</span>
                    <span>🪪 Permis B exigé</span>
                    <span>🔄 Annulation gratuite 24h</span>
                </div>
            </div>
        </section>

        <!-- Modal Réservation Bokun (plein écran) -->
        <div class="booking-modal" id="booking-modal">
            <div class="booking-modal-content">
                <button class="booking-modal-close" onclick="closeBookingModal()" aria-label="Fermer">✕</button>
                <div class="booking-modal-body" id="booking-modal-body">
                    <!-- Le widget Bokun sera injecté ici -->
                </div>
            </div>
        </div>

        <!-- FAQ Section -->
        <section class="faq-section" id="faq">
            <div class="faq-inner">
                <span class="section-label">QUESTIONS FRÉQUENTES</span>
                <h2 class="faq-title">Tout savoir sur nos tours guidés à Nice</h2>
                <div class="faq-list">
                    <div class="faq-item open">
                        <button class="faq-toggle" onclick="toggleFaq(this)" aria-expanded="true">
                            <span>Faut-il un permis de conduire pour participer à un tour ?</span>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="m6 9 6 6 6-6" />
                            </svg>
                        </button>
                        <div class="faq-answer">
                            <p>Oui, un <strong>permis de conduire valide</strong> (permis B ou équivalent) est
                                obligatoire pour conduire nos véhicules lors du tour guidé. Le conducteur doit être en
                                mesure de présenter son permis au départ du tour.</p>
                        </div>
                    </div>
                    <div class="faq-item">
                        <button class="faq-toggle" onclick="toggleFaq(this)" aria-expanded="false">
                            <span>Puis-je garder le véhicule après le tour ?</span>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="m6 9 6 6 6-6" />
                            </svg>
                        </button>
                        <div class="faq-answer">
                            <p>Non, le véhicule est <strong>mis à disposition uniquement pendant la durée du tour
                                    guidé</strong>. Au retour, le véhicule est restitué au point de départ.
                                Il ne s'agit pas d'une location libre.</p>
                        </div>
                    </div>
                    <div class="faq-item">
                        <button class="faq-toggle" onclick="toggleFaq(this)" aria-expanded="false">
                            <span>Un enfant peut-il participer au tour ?</span>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="m6 9 6 6 6-6" />
                            </svg>
                        </button>
                        <div class="faq-answer">
                            <p>Oui, <strong>un enfant maximum</strong> peut être présent dans le véhicule, à
                                condition d'être <strong>obligatoirement accompagné d'un adulte</strong> titulaire du
                                permis de conduire. L'adulte doit être le conducteur du véhicule.</p>
                        </div>
                    </div>
                    <div class="faq-item">
                        <button class="faq-toggle" onclick="toggleFaq(this)" aria-expanded="false">
                            <span>Où a lieu le départ du tour ?</span>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="m6 9 6 6 6-6" />
                            </svg>
                        </button>
                        <div class="faq-answer">
                            <p>Le point de départ et de retour se situe au <strong>2 place Guynemer, 06300
                                    Nice</strong>.
                                Les instructions d'accès vous seront communiquées par email après votre réservation.</p>
                        </div>
                    </div>
                    <div class="faq-item">
                        <button class="faq-toggle" onclick="toggleFaq(this)" aria-expanded="false">
                            <span>Comment se déroule le tour guidé ?</span>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="m6 9 6 6 6-6" />
                            </svg>
                        </button>
                        <div class="faq-answer">
                            <p>À votre arrivée, un <strong>briefing rapide</strong> vous est donné. Vous prenez
                                ensuite le volant de votre véhicule électrique et suivez l'<strong>itinéraire guidé
                                    par GPS</strong> (tablette embarquée). Le parcours vous guide à travers les plus
                                beaux quartiers de Nice avec des arrêts photo aux points d'intérêt.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>



        <!-- Contact Section -->
        <section class="contact-section" id="contact">
            <div class="contact-inner">
                <span class="section-label">CONTACT & ACCÈS</span>
                <h2 class="contact-title">Nous contacter ou nous trouver</h2>

                <div class="contact-grid">
                    <!-- Formulaire de contact -->
                    <div class="contact-form-container">
                        <h3>Envoyez-nous un message</h3>
                        <form id="contact-form" class="contact-form" onsubmit="submitContact(event)">
                            <div class="form-group">
                                <label for="name">Nom complet</label>
                                <input type="text" id="name" name="name" required placeholder="Votre nom">
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" required placeholder="votre@email.com">
                            </div>
                            <div class="form-group">
                                <label for="message">Message</label>
                                <textarea id="message" name="message" rows="4" required
                                    placeholder="Comment pouvons-nous vous aider ?"></textarea>
                            </div>
                            <!-- Hidden token field -->
                            <input type="hidden" name="recaptcha_token" id="recaptcha_token">
                            <input type="hidden" name="action" value="contact">

                            <button type="submit" class="submit-btn" id="submit-btn">Envoyer le message</button>
                            <div id="form-msg" class="form-msg"></div>
                        </form>
                    </div>

                    <!-- Infos de contact & Map -->
                    <div class="contact-info-container">
                        <div class="contact-direct-links">
                            <a href="tel:+33633338792" class="contact-link">
                                <span>📞</span> Nous appeler
                            </a>
                            <a href="https://wa.me/33633338792" target="_blank" class="contact-link whatsapp">
                                <span>💬</span> WhatsApp
                            </a>
                            <a href="https://www.instagram.com/noma.drive/" target="_blank"
                                class="contact-link instagram">
                                <span>📸</span> Instagram
                            </a>
                        </div>

                        <div class="contact-address">
                            <p><strong>Point de départ des tours :</strong><br>
                                2 place Guynemer<br>06300 Nice, France</p>
                        </div>

                        <div class="map-container">
                            <iframe width="100%" height="250" frameborder="0" style="border:0; border-radius: 12px;"
                                src="https://www.google.com/maps/embed/v1/place?key=<?= defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : '' ?>&q=2+place+Guynemer,+06300+Nice"
                                allowfullscreen>
                            </iframe>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <!-- Footer -->
    <footer class="site-footer" id="contact">
        <div class="footer-inner">
            <div class="footer-brand">
                <h3 class="footer-logo">NOMADRIVE</h3>
                <p class="footer-tagline">Tours guidés en voiture électrique à Nice.</p>
            </div>
            <div class="footer-links">
                <div class="footer-col">
                    <h4>Nos tours</h4>
                    <a href="#reserver">City</a>
                    <a href="#reserver">French Riviera</a>
                    <a href="#reserver">Sunset</a>
                </div>
                <div class="footer-col">
                    <h4>Infos</h4>
                    <a href="#faq">FAQ</a>
                    <a href="#">CGV</a>
                    <a href="#">Mentions légales</a>
                </div>
                <div class="footer-col">
                    <h4>Contact</h4>
                    <a href="mailto:contact@nomadrive.fr">contact@nomadrive.fr</a>
                    <p>Lun–Dim · 9h–19h</p>
                    <p>2 place Guynemer, 06300 Nice</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2026 NOMADRIVE — Tours guidés en voiture électrique à Nice. Tous droits réservés.</p>
                <div class="footer-legal">
                    <a href="#">Confidentialité</a>
                    <a href="#">CGV</a>
                    <a href="#">Mentions légales</a>
                </div>
            </div>
        </div>
    </footer>

    <script>
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
        const TOUR_BOKUN_MAP = {
            city: 'https://widgets.bokun.io/online-sales/9a25aafd-ff84-47d1-824a-49fa6a64a423/experience/1194328',
            // Ajouter ici les futurs tours :
            // riviera: 'https://widgets.bokun.io/online-sales/...',
            // sunset: 'https://widgets.bokun.io/online-sales/...',
        };

        function openBookingModal(tourKey) {
            const url = TOUR_BOKUN_MAP[tourKey];
            if (!url) return;
            const modal = document.getElementById('booking-modal');
            const body = document.getElementById('booking-modal-body');
            body.innerHTML = '<div class="bokunWidget" data-src="' + url + '"></div>';
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

    <!-- Bokun Booking Widget -->
    <script type="text/javascript"
        src="https://widgets.bokun.io/assets/javascripts/apps/build/BokunWidgetsLoader.js?bookingChannelUUID=9a25aafd-ff84-47d1-824a-49fa6a64a423"
        async></script>
</body>

</html>