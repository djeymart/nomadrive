<?php
$madiDir = '/var/www/html/madi.mt';
if (!is_dir($madiDir)) $madiDir = dirname(__DIR__);
require_once $madiDir . '/vendor/autoload.php';
require_once $madiDir . '/php/fonctions.php';
require_once $madiDir . '/php/config.php';
require_once __DIR__ . '/config.php';
$lang = in_array($_GET['lang'] ?? '', ['fr', 'en', 'it']) ? $_GET['lang'] : 'fr';

$t = [
    'fr' => [
        'nav_tours'    => 'Nos tours',
        'nav_faq'      => 'FAQ',
        'nav_contact'  => 'Contact',
        'nav_book'     => 'Réserver',
        'page_title'   => 'Questions fréquentes',
        'page_label'   => 'FAQ',
        'page_subtitle'=> 'Tout savoir sur nos tours guidés à Nice',
        'back'         => 'Retour à l\'accueil',
        'questions' => [
            [
                'q' => 'Faut-il un permis de conduire pour participer à un tour ?',
                'a' => 'Oui, un <strong>permis de conduire valide</strong> (permis B ou équivalent) est obligatoire pour conduire nos véhicules lors du tour guidé. Le conducteur doit présenter son permis <strong>en version physique</strong> au départ du tour. <strong>Les permis virtuels ou présentés depuis un téléphone ne sont pas acceptés.</strong>',
            ],
            [
                'q' => 'Puis-je garder le véhicule après le tour ?',
                'a' => 'Non, le véhicule est <strong>mis à disposition uniquement pendant la durée du tour guidé</strong>. Au retour, il est restitué au point de départ. Il ne s\'agit pas d\'une location libre.',
            ],
            [
                'q' => 'Un enfant peut-il participer au tour ?',
                'a' => 'Oui, <strong>un enfant maximum</strong> peut être présent dans le véhicule, à condition d\'être <strong>obligatoirement accompagné d\'un adulte</strong> titulaire du permis de conduire. L\'adulte doit être le conducteur.',
            ],
            [
                'q' => 'Où a lieu le départ du tour ?',
                'a' => 'Le point de départ et de retour se situe au <strong>2 place Guynemer, 06300 Nice</strong>. Les instructions d\'accès vous seront communiquées par email après votre réservation.',
            ],
            [
                'q' => 'Comment se déroule le tour guidé ?',
                'a' => 'À votre arrivée, un <strong>briefing rapide</strong> vous est donné. Vous prenez ensuite le volant de votre véhicule électrique et suivez l\'<strong>itinéraire guidé par GPS</strong> (tablette embarquée). Le parcours vous guide à travers les plus beaux quartiers de Nice avec des arrêts photo aux points d\'intérêt.',
            ],
            [
                'q' => 'Quelle est la politique d\'annulation ?',
                'a' => 'Annulation <strong>plus de 24h avant</strong> le départ : remboursement intégral. Annulation <strong>24h ou moins avant</strong> : aucun remboursement. No-show ou retard de plus de 30 min sans avertissement : aucun remboursement.',
            ],
            [
                'q' => 'Combien de personnes peuvent monter dans un véhicule ?',
                'a' => 'Nos véhicules (Citroën Ami, Fiat Topolino) sont des <strong>quadricycles électriques 2 places</strong>. Chaque véhicule accueille un conducteur et un passager. Pour les groupes, plusieurs véhicules peuvent partir ensemble.',
            ],
            [
                'q' => 'Que se passe-t-il en cas de mauvais temps ?',
                'a' => 'En cas d\'alerte météo orange ou rouge Météo-France rendant la circulation dangereuse, nous nous réservons le droit d\'annuler ou de reporter le tour. Un report ou un <strong>remboursement intégral</strong> vous sera proposé.',
            ],
            [
                'q' => 'Quelle carte bancaire apporter pour la caution ?',
                'a' => 'Une <strong>pré-autorisation bancaire de 500 €</strong> est effectuée à titre de caution au départ du tour. Vous devez impérativement vous munir d\'une <strong>carte bancaire physique</strong> (carte plastique) — <strong>les cartes virtuelles ne sont pas acceptées</strong>.',
            ],
            [
                'q' => 'Où se garer à proximité ?',
                'a' => 'Le <strong>Parking Port Lympia</strong> se trouve à <strong>200 mètres</strong> de notre local. <a href="https://www.google.com/maps/place/Parking+Port+Lympia+-+Port+de+Nice/@43.6943639,7.2796884,17z" target="_blank" rel="noopener">Voir sur Google Maps</a>',
            ],
        ],
    ],
    'en' => [
        'nav_tours'    => 'Our tours',
        'nav_faq'      => 'FAQ',
        'nav_contact'  => 'Contact',
        'nav_book'     => 'Book now',
        'page_title'   => 'Frequently Asked Questions',
        'page_label'   => 'FAQ',
        'page_subtitle'=> 'Everything about our guided tours in Nice',
        'back'         => 'Back to home',
        'questions' => [
            [
                'q' => 'Is a driving licence required to join a tour?',
                'a' => 'Yes, a <strong>valid driving licence</strong> (category B or equivalent) is required to drive our vehicles during the guided tour. The driver must present their licence <strong>in physical form</strong> at the start of the tour. <strong>Digital licences or licences shown on a phone are not accepted.</strong>',
            ],
            [
                'q' => 'Can I keep the vehicle after the tour?',
                'a' => 'No, the vehicle is <strong>available only for the duration of the guided tour</strong>. It is returned to the departure point at the end. This is not a free-roaming rental.',
            ],
            [
                'q' => 'Can a child join the tour?',
                'a' => 'Yes, <strong>one child</strong> may be in the vehicle, provided they are <strong>accompanied by a licensed adult driver</strong>. The adult must be the driver.',
            ],
            [
                'q' => 'Where does the tour depart from?',
                'a' => 'The departure and return point is at <strong>2 place Guynemer, 06300 Nice</strong>. Access instructions will be sent to you by email after booking.',
            ],
            [
                'q' => 'How does the guided tour work?',
                'a' => 'On arrival, you\'ll receive a <strong>quick briefing</strong>. You then take the wheel of your electric vehicle and follow the <strong>GPS-guided route</strong> (on-board tablet). The route takes you through Nice\'s most beautiful neighbourhoods with photo stops at points of interest.',
            ],
            [
                'q' => 'What is the cancellation policy?',
                'a' => 'Cancellation <strong>more than 24 hours before</strong> departure: full refund. Cancellation <strong>24 hours or less before</strong>: no refund. No-show or arrival more than 30 minutes late without notice: no refund.',
            ],
            [
                'q' => 'How many people can ride in one vehicle?',
                'a' => 'Our vehicles (Citroën Ami, Fiat Topolino) are <strong>2-seat electric quadricycles</strong>. Each vehicle accommodates a driver and one passenger. For groups, multiple vehicles can depart together.',
            ],
            [
                'q' => 'What happens in bad weather?',
                'a' => 'In the event of an orange or red Météo-France weather warning making driving unsafe, we reserve the right to cancel or reschedule the tour. A rescheduled booking or a <strong>full refund</strong> will be offered.',
            ],
            [
                'q' => 'Which bank card do I need for the deposit?',
                'a' => 'A <strong>€500 pre-authorisation</strong> is placed on your card as a security deposit at the start of the tour. You must bring a <strong>physical bank card</strong> (plastic card) — <strong>virtual cards are not accepted</strong>.',
            ],
            [
                'q' => 'Where can I park nearby?',
                'a' => '<strong>Port Lympia car park</strong> is <strong>200 metres</strong> from our venue. <a href="https://www.google.com/maps/place/Parking+Port+Lympia+-+Port+de+Nice/@43.6943639,7.2796884,17z" target="_blank" rel="noopener">View on Google Maps</a>',
            ],
        ],
    ],
    'it' => [
        'nav_tours'    => 'I nostri tour',
        'nav_faq'      => 'FAQ',
        'nav_contact'  => 'Contatto',
        'nav_book'     => 'Prenota',
        'page_title'   => 'Domande frequenti',
        'page_label'   => 'FAQ',
        'page_subtitle'=> 'Tutto sui nostri tour guidati a Nizza',
        'back'         => 'Torna alla home',
        'questions' => [
            [
                'q' => 'È necessaria la patente per partecipare a un tour?',
                'a' => 'Sì, una <strong>patente di guida valida</strong> (categoria B o equivalente) è obbligatoria per guidare i nostri veicoli durante il tour. Il conducente deve presentare la patente <strong>in formato fisico</strong> all\'inizio del tour. <strong>Le patenti digitali o mostrate dal telefono non sono accettate.</strong>',
            ],
            [
                'q' => 'Posso tenere il veicolo dopo il tour?',
                'a' => 'No, il veicolo è <strong>disponibile solo per la durata del tour guidato</strong>. Al ritorno viene restituito al punto di partenza. Non si tratta di un noleggio libero.',
            ],
            [
                'q' => 'Un bambino può partecipare al tour?',
                'a' => 'Sì, <strong>un bambino al massimo</strong> può essere presente nel veicolo, a condizione di essere <strong>obbligatoriamente accompagnato da un adulto</strong> con patente. L\'adulto deve essere il conducente.',
            ],
            [
                'q' => 'Da dove parte il tour?',
                'a' => 'Il punto di partenza e di ritorno è al <strong>2 place Guynemer, 06300 Nizza</strong>. Le istruzioni di accesso saranno comunicate per email dopo la prenotazione.',
            ],
            [
                'q' => 'Come si svolge il tour guidato?',
                'a' => 'All\'arrivo riceverete un <strong>rapido briefing</strong>. Poi prendete il volante del vostro veicolo elettrico e seguite il <strong>percorso guidato via GPS</strong> (tablet a bordo). Il percorso vi guida nei quartieri più belli di Nizza con soste fotografiche.',
            ],
            [
                'q' => 'Qual è la politica di cancellazione?',
                'a' => 'Cancellazione <strong>più di 24h prima</strong> della partenza: rimborso completo. Cancellazione <strong>24h o meno prima</strong>: nessun rimborso. No-show o ritardo superiore a 30 min senza avviso: nessun rimborso.',
            ],
            [
                'q' => 'Quante persone possono salire su un veicolo?',
                'a' => 'I nostri veicoli (Citroën Ami, Fiat Topolino) sono <strong>quadricicli elettrici a 2 posti</strong>. Ogni veicolo ospita un conducente e un passeggero. Per i gruppi, più veicoli possono partire insieme.',
            ],
            [
                'q' => 'Cosa succede in caso di maltempo?',
                'a' => 'In caso di allerta meteo arancione o rossa di Météo-France che rende la guida pericolosa, ci riserviamo il diritto di annullare o posticipare il tour. Verrà proposta una data alternativa o un <strong>rimborso completo</strong>.',
            ],
            [
                'q' => 'Quale carta bancaria portare per la cauzione?',
                'a' => 'Una <strong>pre-autorizzazione di 500 €</strong> viene effettuata sulla vostra carta come cauzione all\'inizio del tour. È obbligatorio portare una <strong>carta bancaria fisica</strong> (carta plastificata) — <strong>le carte virtuali non sono accettate</strong>.',
            ],
            [
                'q' => 'Dove parcheggiare nelle vicinanze?',
                'a' => 'Il <strong>Parcheggio Port Lympia</strong> si trova a <strong>200 metri</strong> dalla nostra sede. <a href="https://www.google.com/maps/place/Parking+Port+Lympia+-+Port+de+Nice/@43.6943639,7.2796884,17z" target="_blank" rel="noopener">Vedi su Google Maps</a>',
            ],
        ],
    ],
][$lang];
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $t['page_title'] ?> | NOMADRIVE</title>
    <meta name="description" content="<?= $lang === 'fr' ? 'Réponses à vos questions sur les tours guidés en voiture électrique à Nice — NOMADRIVE.' : ($lang === 'it' ? 'Risposte alle vostre domande sui tour guidati in auto elettrica a Nizza — NOMADRIVE.' : 'Answers to your questions about guided electric car tours in Nice — NOMADRIVE.') ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://nomadrive.fr/faq.php">
    <link rel="alternate" hreflang="fr" href="https://nomadrive.fr/faq.php?lang=fr">
    <link rel="alternate" hreflang="en" href="https://nomadrive.fr/faq.php?lang=en">
    <link rel="alternate" hreflang="it" href="https://nomadrive.fr/faq.php?lang=it">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://kit.fontawesome.com/494ceebc6d.js" crossorigin="anonymous"></script>
    <script>var GTM_ID = 'GTM-5NH9D8CC';</script>
    <style>
        .cgv-page { max-width: 860px; margin: 0 auto; padding: 40px 20px 80px; }
        .cgv-header { text-align: center; margin-bottom: 48px; padding-bottom: 32px; border-bottom: 2px solid #e8f4fd; }
        .cgv-header .badge { display: inline-block; background: #e8f4fd; color: #0077b6; font-size: 11px; font-weight: 700; letter-spacing: .1em; padding: 4px 12px; border-radius: 20px; margin-bottom: 14px; }
        .cgv-header h1 { font-family: 'Playfair Display', serif; font-size: clamp(1.6rem, 4vw, 2.4rem); color: #0a1628; margin: 0 0 10px; }
        .cgv-header .cgv-meta { font-size: 13px; color: #6b7280; }
        .cgv-footer-note { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px; padding: 20px 24px; text-align: center; font-size: 13px; color: #0369a1; margin-top: 48px; }
        .cgv-footer-note a { color: #0077b6; font-weight: 600; }
        .back-link { display: inline-flex; align-items: center; gap: 6px; color: #0077b6; font-size: 14px; font-weight: 500; text-decoration: none; margin-bottom: 32px; }
        .back-link:hover { text-decoration: underline; }
        @media (max-width: 600px) {
            .cgv-page { padding: 24px 16px 60px; }
        }
    </style>

    <!-- JSON-LD FAQPage -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "FAQPage",
        "mainEntity": [
            <?php foreach ($t['questions'] as $i => $faq): ?>
            <?= $i > 0 ? ',' : '' ?>
            {
                "@type": "Question",
                "name": <?= json_encode(strip_tags($faq['q'])) ?>,
                "acceptedAnswer": { "@type": "Answer", "text": <?= json_encode(strip_tags($faq['a'])) ?> }
            }
            <?php endforeach; ?>
        ]
    }
    </script>
</head>
<body>

    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-5NH9D8CC" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>

    <nav class="main-nav" aria-label="Navigation principale">
        <div class="nav-inner">
            <a href="/?lang=<?= $lang ?>" class="nav-logo" aria-label="NOMADRIVE - Accueil">NOMADRIVE</a>
            <div class="nav-links" id="nav-links">
                <a href="/#reserver"><?= $t['nav_tours'] ?></a>
                <a href="/faq.php?lang=<?= $lang ?>"><?= $t['nav_faq'] ?></a>
                <a href="/#contact"><?= $t['nav_contact'] ?></a>
            </div>
            <div class="nav-actions">
                <div class="lang-switcher">
                    <a href="?lang=fr" class="lang-btn <?= $lang === 'fr' ? 'active' : '' ?>">FR</a>
                    <a href="?lang=en" class="lang-btn <?= $lang === 'en' ? 'active' : '' ?>">EN</a>
                    <a href="?lang=it" class="lang-btn <?= $lang === 'it' ? 'active' : '' ?>">IT</a>
                </div>
                <a href="/#reserver" class="nav-cta-btn visible"><?= $t['nav_book'] ?></a>
                <button class="hamburger" id="hamburger-btn" aria-label="Ouvrir le menu" aria-expanded="false">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </div>
    </nav>

    <main>
        <div class="cgv-page">

            <a href="/?lang=<?= $lang ?>" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> <?= $t['back'] ?>
            </a>

            <div class="cgv-header">
                <div class="badge"><?= $t['page_label'] ?></div>
                <h1><?= $t['page_title'] ?><br><span style="font-family:'Inter',sans-serif;font-size:0.6em;font-weight:400;color:#6b7280;">NOMADRIVE</span></h1>
                <p class="cgv-meta"><?= $t['page_subtitle'] ?></p>
            </div>

            <div class="faq-list" style="max-width:720px;margin:0 auto;">
                <?php foreach ($t['questions'] as $i => $faq): ?>
                <div class="faq-item <?= $i === 0 ? 'open' : '' ?>">
                    <button class="faq-toggle" onclick="toggleFaq(this)" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>">
                        <span><?= htmlspecialchars($faq['q']) ?></span>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="m6 9 6 6 6-6"/>
                        </svg>
                    </button>
                    <div class="faq-answer">
                        <p><?= $faq['a'] ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="cgv-footer-note" style="margin-top:48px;">
                <strong>Une autre question ?</strong><br>
                <a href="/#contact">Contactez-nous</a> &nbsp;·&nbsp;
                <a href="https://wa.me/33633338792" target="_blank">WhatsApp</a>
            </div>

        </div>
    </main>

    <footer class="site-footer">
        <div class="footer-inner">
            <div class="footer-brand">
                <h3 class="footer-logo">NOMADRIVE</h3>
                <p class="footer-tagline"><?= $lang === 'fr' ? 'Tours guidés en voiture électrique à Nice.' : ($lang === 'it' ? 'Tour guidati in auto elettrica a Nizza.' : 'Guided electric car tours in Nice.') ?></p>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> NOMADRIVE</p>
                <div class="footer-legal">
                    <a href="/faq.php?lang=<?= $lang ?>">FAQ</a>
                    <a href="/cgv.php?lang=<?= $lang ?>">CGV</a>
                    <a href="/legal.php?lang=<?= $lang ?>"><?= $lang === 'fr' ? 'Mentions légales' : ($lang === 'it' ? 'Note legali' : 'Legal notice') ?></a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Floating Social Buttons -->
    <div class="floating-social">
        <a href="https://wa.me/33633338792" target="_blank" rel="noopener" class="float-btn float-whatsapp" aria-label="WhatsApp">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        </a>
        <a href="https://www.instagram.com/noma.drive/" target="_blank" rel="noopener" class="float-btn float-instagram" aria-label="Instagram">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
        </a>
    </div>

    <div class="cookie-banner" id="cookie-banner" role="dialog" aria-label="Cookies">
        <div class="cookie-banner-inner">
            <div class="cookie-banner-icon">🍪</div>
            <div class="cookie-banner-text">
                <?php if ($lang === 'fr'): ?>
                    Ce site utilise des cookies de mesure d'audience. <a href="/legal.php?lang=fr#cookies">En savoir plus</a>
                <?php elseif ($lang === 'it'): ?>
                    Questo sito utilizza cookie di analisi. <a href="/legal.php?lang=en#cookies">Saperne di più</a>
                <?php else: ?>
                    This site uses analytics cookies. <a href="/legal.php?lang=en#cookies">Learn more</a>
                <?php endif; ?>
            </div>
            <div class="cookie-banner-actions">
                <button class="cookie-btn cookie-btn-refuse" id="cookie-refuse"><?= $lang === 'fr' ? 'Refuser' : ($lang === 'it' ? 'Rifiuta' : 'Refuse') ?></button>
                <button class="cookie-btn cookie-btn-accept" id="cookie-accept"><?= $lang === 'fr' ? 'Accepter' : ($lang === 'it' ? 'Accetta' : 'Accept') ?></button>
            </div>
        </div>
    </div>

    <script>
        const hamburgerBtn = document.getElementById('hamburger-btn');
        const navLinks = document.getElementById('nav-links');
        if (hamburgerBtn) {
            hamburgerBtn.addEventListener('click', () => {
                const isOpen = navLinks.classList.toggle('mobile-open');
                hamburgerBtn.classList.toggle('active');
                hamburgerBtn.setAttribute('aria-expanded', isOpen);
            });
        }
        window.addEventListener('scroll', () => {
            const nav = document.querySelector('.main-nav');
            if (window.scrollY > 50) nav.classList.add('scrolled');
            else nav.classList.remove('scrolled');
        });
        function toggleFaq(btn) {
            const item = btn.parentElement;
            item.classList.toggle('open');
            btn.setAttribute('aria-expanded', item.classList.contains('open'));
        }
        (function () {
            const K = 'nd_cookie_consent', b = document.getElementById('cookie-banner');
            function get() { try { const d = JSON.parse(localStorage.getItem(K)); if (d && d.expiry > Date.now()) return d.value; localStorage.removeItem(K); } catch(e) {} return null; }
            function set(v) { localStorage.setItem(K, JSON.stringify({ value: v, expiry: Date.now() + 180*86400000 })); }
            function loadGTM() {
                if (typeof GTM_ID === 'undefined' || document.getElementById('gtm-script')) return;
                (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;j.id='gtm-script';f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',GTM_ID);
            }
            const c = get();
            if (c === 'accepted') loadGTM();
            else if (!c) setTimeout(() => b.classList.add('visible'), 800);
            document.getElementById('cookie-accept').onclick = () => { set('accepted'); b.classList.remove('visible'); loadGTM(); };
            document.getElementById('cookie-refuse').onclick = () => { set('refused'); b.classList.remove('visible'); };
        })();
    </script>

</body>
</html>
