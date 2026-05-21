<?php
$madiDir = '/var/www/html/madi.mt';
if (!is_dir($madiDir)) $madiDir = dirname(__DIR__);
require_once $madiDir . '/vendor/autoload.php';
require_once $madiDir . '/php/fonctions.php';
require_once $madiDir . '/php/config.php';
require_once __DIR__ . '/config.php';
$lang = in_array($_GET['lang'] ?? '', ['fr', 'en']) ? $_GET['lang'] : 'fr';
$caution = defined('CAUTION_MONTANT') ? CAUTION_MONTANT : 500;
$fr = ($lang === 'fr');
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $fr ? 'Conditions Générales de Location' : 'General Rental Terms and Conditions' ?> | NOMADRIVE</title>
    <meta name="description" content="<?= $fr ? 'Conditions Générales de Location de NOMADRIVE — location de véhicules électriques à Nice.' : 'General Rental Terms and Conditions of NOMADRIVE — electric vehicle rental in Nice.' ?>">
    <meta name="robots" content="noindex, follow">
    <link rel="canonical" href="https://nomadrive.fr/cgv.php">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://kit.fontawesome.com/494ceebc6d.js" crossorigin="anonymous"></script>
    <style>
        .cgv-page { max-width: 860px; margin: 0 auto; padding: 40px 20px 80px; }
        .cgv-header { text-align: center; margin-bottom: 48px; padding-bottom: 32px; border-bottom: 2px solid #e8f4fd; }
        .cgv-header .badge { display: inline-block; background: #e8f4fd; color: #0077b6; font-size: 11px; font-weight: 700; letter-spacing: .1em; padding: 4px 12px; border-radius: 20px; margin-bottom: 14px; }
        .cgv-header h1 { font-family: 'Playfair Display', serif; font-size: clamp(1.6rem, 4vw, 2.4rem); color: #0a1628; margin: 0 0 10px; }
        .cgv-header .cgv-meta { font-size: 13px; color: #6b7280; }
        .cgv-toc { background: #f8fbfe; border: 1px solid #dbeafe; border-radius: 12px; padding: 20px 24px; margin-bottom: 48px; }
        .cgv-toc h2 { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #0077b6; margin: 0 0 12px; }
        .cgv-toc ol { margin: 0; padding-left: 18px; }
        .cgv-toc li { margin-bottom: 6px; }
        .cgv-toc a { color: #0077b6; text-decoration: none; font-size: 14px; }
        .cgv-toc a:hover { text-decoration: underline; }
        .cgv-part { margin-bottom: 52px; }
        .cgv-part-title { display: flex; align-items: center; gap: 12px; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: #0077b6; margin-bottom: 24px; padding-bottom: 10px; border-bottom: 2px solid #e8f4fd; }
        .cgv-part-title .part-num { background: #0077b6; color: #fff; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
        .cgv-section { margin-bottom: 28px; }
        .cgv-section h3 { font-size: 15px; font-weight: 700; color: #0a1628; margin: 0 0 10px; }
        .cgv-section p, .cgv-section li { font-size: 14px; line-height: 1.75; color: #374151; margin: 0 0 8px; }
        .cgv-section ul { padding-left: 18px; margin: 8px 0; }
        .cgv-section ul li { margin-bottom: 5px; }
        .cgv-important { background: #fff8e1; border-left: 4px solid #f59e0b; border-radius: 0 8px 8px 0; padding: 12px 16px; margin: 14px 0; }
        .cgv-important strong { color: #b45309; font-size: 13px; text-transform: uppercase; letter-spacing: .05em; display: block; margin-bottom: 4px; }
        .cgv-important p { margin: 0; font-size: 13px; color: #78350f; }
        .cgv-footer-note { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px; padding: 20px 24px; text-align: center; font-size: 13px; color: #0369a1; margin-top: 48px; }
        .cgv-footer-note a { color: #0077b6; font-weight: 600; }
        .back-link { display: inline-flex; align-items: center; gap: 6px; color: #0077b6; font-size: 14px; font-weight: 500; text-decoration: none; margin-bottom: 32px; }
        .back-link:hover { text-decoration: underline; }
        @media (max-width: 600px) {
            .cgv-page { padding: 24px 16px 60px; }
            .cgv-toc { padding: 16px; }
        }
    </style>

    <!-- Google Tag Manager — chargé uniquement après consentement cookies -->
    <script>var GTM_ID = 'GTM-5NH9D8CC';</script>
</head>
<body>

    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-5NH9D8CC" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>

    <!-- Navigation -->
    <nav class="main-nav" aria-label="Navigation principale">
        <div class="nav-inner">
            <a href="/" class="nav-logo" aria-label="NOMADRIVE - Accueil">NOMADRIVE</a>
            <div class="nav-links" id="nav-links">
                <a href="/#reserver"><?= $fr ? 'Nos tours' : 'Our tours' ?></a>
                <a href="/#faq">FAQ</a>
                <a href="/#contact">Contact</a>
            </div>
            <div class="nav-actions">
                <div class="lang-switcher">
                    <a href="?lang=fr" class="lang-btn <?= $lang === 'fr' ? 'active' : '' ?>">FR</a>
                    <a href="?lang=en" class="lang-btn <?= $lang === 'en' ? 'active' : '' ?>">EN</a>
                </div>
                <a href="/#reserver" class="nav-cta-btn"><?= $fr ? 'Réserver' : 'Book' ?></a>
                <button class="hamburger" id="hamburger-btn" aria-label="<?= $fr ? 'Ouvrir le menu' : 'Open menu' ?>" aria-expanded="false">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </div>
    </nav>

    <main>
        <div class="cgv-page">

            <a href="/" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
                <?= $fr ? 'Retour à l\'accueil' : 'Back to home' ?>
            </a>

            <div class="cgv-header">
                <div class="badge"><?= $fr ? 'CONDITIONS GÉNÉRALES DE LOCATION' : 'GENERAL RENTAL TERMS AND CONDITIONS' ?></div>
                <h1><?= $fr ? 'Contrat de Location' : 'Rental Agreement' ?><br>NOMADRIVE</h1>
                <p class="cgv-meta">
                    <strong>NICE ACTIVITY</strong> — SAS au capital de 100 000 € — RCS Nice 994 620 615<br>
                    EUID FR0605.994620615 — N° gestion 2025B04038<br>
                    <?= $fr ? 'Nom commercial' : 'Trade name' ?> : <strong>NOMADRIVE</strong><br>
                    2 Place Guynemer, 06300 Nice &nbsp;·&nbsp; <a href="mailto:contact@nomadrive.fr">contact@nomadrive.fr</a><br>
                    <?= $fr ? 'Dernière mise à jour' : 'Last updated' ?> : <?= date('d/m/Y') ?>
                </p>
            </div>

            <!-- Table des matières -->
            <div class="cgv-toc">
                <h2><?= $fr ? 'Sommaire' : 'Table of Contents' ?></h2>
                <ol>
                    <li><a href="#informations-generales"><?= $fr ? 'Informations générales' : 'General Information' ?></a></li>
                    <li><a href="#prise-en-charge"><?= $fr ? 'Prise en charge du véhicule' : 'Vehicle Pick-up' ?></a></li>
                    <li><a href="#utilisation"><?= $fr ? 'Utilisation du véhicule' : 'Vehicle Use' ?></a></li>
                    <li><a href="#accidents"><?= $fr ? 'Accidents et sinistres' : 'Accidents and Claims' ?></a></li>
                    <li><a href="#restitution"><?= $fr ? 'Restitution du véhicule' : 'Vehicle Return' ?></a></li>
                    <li><a href="#annulation"><?= $fr ? 'Politique d\'annulation' : 'Cancellation Policy' ?></a></li>
                    <li><a href="#dispositions-finales"><?= $fr ? 'Dispositions finales' : 'Final Provisions' ?></a></li>
                </ol>
            </div>

            <!-- PARTIE I -->
            <div class="cgv-part" id="informations-generales">
                <div class="cgv-part-title">
                    <div class="part-num"><i class="fa-solid fa-circle-info"></i></div>
                    <?= $fr ? 'Informations générales' : 'General Information' ?>
                </div>

                <div class="cgv-section">
                    <?php if ($fr): ?>
                    <h3>1. Définitions</h3>
                    <p>Les présentes Conditions Générales de Location régissent tout contrat de location conclu entre <strong>NICE ACTIVITY</strong> (SAS au capital de 100 000 €, RCS Nice 994 620 615), exploitant sous le nom commercial <strong>NOMADRIVE</strong> (ci-après « le loueur », « nous ») et le locataire (ci-après « vous »).</p>
                    <p>Le <strong>Contrat de Location</strong> est le document signé électroniquement lors de la prise en charge du véhicule. Il inclut un résumé de votre location (durée, véhicule, frais estimés) et incorpore les présentes Conditions Générales par référence.</p>
                    <p>Le terme <strong>« véhicule »</strong> désigne le quadricycle léger électrique (Fiat Topolino, Citroën Ami ou tout modèle équivalent) mis à votre disposition, ainsi que tous ses équipements et accessoires.</p>
                    <p>En signant le Contrat de Location, vous reconnaissez avoir pris connaissance des présentes Conditions Générales et vous vous engagez à les respecter.</p>
                    <?php else: ?>
                    <h3>1. Definitions</h3>
                    <p>These General Rental Terms and Conditions govern any rental agreement between <strong>NICE ACTIVITY</strong> (SAS with share capital of €100,000, RCS Nice 994 620 615), operating under the trade name <strong>NOMADRIVE</strong> (hereinafter "the lessor", "we", "us") and the renter (hereinafter "you").</p>
                    <p>The <strong>Rental Agreement</strong> is the document signed electronically at the time of vehicle pick-up. It includes a summary of your rental (duration, vehicle, estimated charges) and incorporates these General Terms by reference.</p>
                    <p>The term <strong>"vehicle"</strong> refers to the light electric quadricycle (Fiat Topolino, Citroën Ami or any equivalent model) made available to you, along with all its equipment and accessories.</p>
                    <p>By signing the Rental Agreement, you acknowledge that you have read and agree to be bound by these General Terms and Conditions.</p>
                    <?php endif; ?>
                </div>

                <div class="cgv-section">
                    <?php if ($fr): ?>
                    <h3>2. Responsabilités</h3>
                    <p>NICE ACTIVITY s'engage à vous remettre un véhicule en bon état de fonctionnement, propre et avec sa batterie chargée.</p>
                    <p>Vous devez utiliser, entretenir et restituer le véhicule conformément aux présentes conditions.</p>
                    <div class="cgv-important">
                        <strong>Important</strong>
                        <p>Vous engagez votre responsabilité en cas de restitution tardive, de perte du véhicule ou de tout dommage survenu pendant la location. Vous êtes également responsable de toute amende ou contravention reçue pendant la durée de la location.</p>
                    </div>
                    <?php else: ?>
                    <h3>2. Responsibilities</h3>
                    <p>NICE ACTIVITY undertakes to provide you with a vehicle in good working order, clean and with a charged battery.</p>
                    <p>You must use, maintain and return the vehicle in accordance with these terms.</p>
                    <div class="cgv-important">
                        <strong>Important</strong>
                        <p>You are liable in the event of late return, loss of the vehicle or any damage occurring during the rental. You are also responsible for any fines or traffic penalties received during the rental period.</p>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="cgv-section">
                    <?php if ($fr): ?>
                    <h3>3. Litiges</h3>
                    <p>En cas de différend, nous nous efforçons de le résoudre amiablement. Toute contestation doit nous être adressée par écrit à <a href="mailto:contact@nomadrive.fr">contact@nomadrive.fr</a> dans les <strong>14 jours</strong> suivant la fin de la location, avec les détails du contrat et les preuves à l'appui.</p>
                    <p>En cas de désaccord persistant, vous pouvez saisir un médiateur de la consommation ou le tribunal compétent du ressort de Nice (France). Le présent contrat est soumis au droit français.</p>
                    <?php else: ?>
                    <h3>3. Disputes</h3>
                    <p>In the event of a dispute, we will endeavour to resolve it amicably. Any complaint must be sent to us in writing at <a href="mailto:contact@nomadrive.fr">contact@nomadrive.fr</a> within <strong>14 days</strong> of the end of the rental, including the contract details and supporting evidence.</p>
                    <p>If the dispute cannot be resolved, you may refer the matter to a consumer mediator or the competent court in Nice (France). This agreement is governed by French law.</p>
                    <?php endif; ?>
                </div>

                <div class="cgv-section">
                    <?php if ($fr): ?>
                    <h3>4. Confidentialité et données du véhicule</h3>
                    <p>Les données personnelles collectées (nom, prénom, email, adresse, photo du permis de conduire, signature) sont traitées uniquement dans le cadre de la location et conservées conformément au <strong>RGPD</strong>. Vous disposez d'un droit d'accès, de rectification et de suppression en écrivant à <a href="mailto:contact@nomadrive.fr">contact@nomadrive.fr</a>.</p>
                    <p>Les véhicules peuvent être équipés d'un système de localisation. Ces données sont utilisées à des fins de sécurité, de gestion de flotte et de gestion des sinistres. Nous pourrons vous contacter en cas d'alerte de sécurité ou opérationnelle détectée sur le véhicule.</p>
                    <?php else: ?>
                    <h3>4. Privacy and Vehicle Data</h3>
                    <p>Personal data collected (name, email, address, driving licence photo, signature) is processed solely for the purpose of the rental and retained in accordance with the <strong>GDPR</strong>. You have the right to access, rectify and delete your data by writing to <a href="mailto:contact@nomadrive.fr">contact@nomadrive.fr</a>.</p>
                    <p>Vehicles may be equipped with a GPS tracking system. This data is used for safety, fleet management and claims handling purposes. We may contact you if a safety or operational alert is detected on the vehicle.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PARTIE II -->
            <div class="cgv-part" id="prise-en-charge">
                <div class="cgv-part-title">
                    <div class="part-num"><i class="fa-duotone fa-solid fa-car-side"></i></div>
                    <?= $fr ? 'Prise en charge du véhicule' : 'Vehicle Pick-up' ?>
                </div>

                <div class="cgv-section">
                    <?php if ($fr): ?>
                    <h3>1. Caution / Empreinte bancaire</h3>
                    <p>Lors de la remise des clés, une empreinte bancaire (pré-autorisation) est effectuée sur votre carte de crédit ou de débit pour un montant de <strong><?= $caution ?> €</strong>. Ce montant couvre les dommages éventuels et tout frais supplémentaire.</p>
                    <p>La pré-autorisation est libérée dans un délai maximum de <strong>30 jours</strong> après la restitution du véhicule en bon état. Ce délai dépend de votre établissement bancaire.</p>
                    <div class="cgv-important">
                        <strong>Important</strong>
                        <p>Assurez-vous de disposer d'un crédit suffisant sur votre carte bancaire avant la prise en charge du véhicule.</p>
                    </div>
                    <?php else: ?>
                    <h3>1. Security Deposit / Card Pre-authorisation</h3>
                    <p>At the time of key handover, a card pre-authorisation is placed on your credit or debit card for an amount of <strong>€<?= $caution ?></strong>. This amount covers any potential damage and additional charges.</p>
                    <p>The pre-authorisation is released within a maximum of <strong>30 days</strong> after the vehicle is returned in good condition. This timeframe depends on your bank.</p>
                    <div class="cgv-important">
                        <strong>Important</strong>
                        <p>Please ensure you have sufficient available credit on your card before picking up the vehicle.</p>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="cgv-section">
                    <?php if ($fr): ?>
                    <h3>2. Frais de location</h3>
                    <p>Le montant de la location est précisé lors de la réservation et figure au recto du Contrat de Location. En signant le contrat, vous acceptez de payer ce montant ainsi que tout frais supplémentaire éventuel.</p>
                    <p>Frais supplémentaires pouvant s'appliquer :</p>
                    <ul>
                        <li>Restitution tardive du véhicule</li>
                        <li>Dommages non couverts par l'état des lieux initial</li>
                        <li>Amendes ou contraventions reçues pendant la location</li>
                        <li>Nettoyage exceptionnel du véhicule</li>
                    </ul>
                    <?php else: ?>
                    <h3>2. Rental Charges</h3>
                    <p>The rental amount is specified at the time of booking and shown on the front of the Rental Agreement. By signing the agreement, you agree to pay this amount as well as any additional charges that may apply.</p>
                    <p>Additional charges that may apply:</p>
                    <ul>
                        <li>Late vehicle return</li>
                        <li>Damage not covered by the initial vehicle inspection report</li>
                        <li>Fines or traffic penalties received during the rental</li>
                        <li>Exceptional vehicle cleaning</li>
                    </ul>
                    <?php endif; ?>
                </div>

                <div class="cgv-section">
                    <?php if ($fr): ?>
                    <h3>3. État du véhicule</h3>
                    <p>À la prise en charge, un état des lieux est réalisé conjointement. Tout dommage préexistant est consigné sur la <strong>Fiche d'État du Véhicule</strong>. Il est important de vérifier l'état du véhicule avant de quitter les locaux.</p>
                    <div class="cgv-important">
                        <strong>Important</strong>
                        <p>Vous devez restituer le véhicule dans le même état qu'à la prise en charge. Tout dommage non consigné à l'état des lieux initial sera imputé au locataire.</p>
                    </div>
                    <?php else: ?>
                    <h3>3. Vehicle Condition</h3>
                    <p>At pick-up, a joint vehicle inspection is carried out. Any pre-existing damage is recorded on the <strong>Vehicle Condition Report</strong>. It is important to check the vehicle's condition before leaving the premises.</p>
                    <div class="cgv-important">
                        <strong>Important</strong>
                        <p>You must return the vehicle in the same condition as at pick-up. Any damage not recorded in the initial inspection report will be charged to the renter.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PARTIE III -->
            <div class="cgv-part" id="utilisation">
                <div class="cgv-part-title">
                    <div class="part-num"><i class="fa-duotone fa-solid fa-road"></i></div>
                    <?= $fr ? 'Utilisation du véhicule' : 'Vehicle Use' ?>
                </div>

                <div class="cgv-section">
                    <?php if ($fr): ?>
                    <h3>1. Restrictions d'utilisation</h3>
                    <p>Le véhicule est notre propriété. Vous n'êtes pas autorisé(e) à le sous-louer, le céder ou le vendre.</p>
                    <p>Il est strictement interdit d'utiliser le véhicule pour :</p>
                    <ul>
                        <li>Transporter des passagers contre rémunération (taxi, VTC, covoiturage)</li>
                        <li>Emprunter des voies non carrossables, chemins hors-pistes ou plages</li>
                        <li>Transporter un nombre de passagers excédant la capacité du véhicule (2 personnes)</li>
                        <li>Remorquer ou pousser un autre véhicule ou tout autre équipement</li>
                        <li>Transporter des matières dangereuses, inflammables, toxiques ou explosives</li>
                        <li>Pratiquer des activités sportives motorisées, courses ou compétitions</li>
                        <li>Conduire en infraction au Code de la route</li>
                        <li>Toute utilisation illicite</li>
                    </ul>
                    <?php else: ?>
                    <h3>1. Restrictions on Use</h3>
                    <p>The vehicle is our property. You are not permitted to sublet, transfer or sell it.</p>
                    <p>It is strictly prohibited to use the vehicle for:</p>
                    <ul>
                        <li>Carrying passengers for hire or reward (taxi, ridesharing)</li>
                        <li>Driving on unpaved roads, off-road tracks or beaches</li>
                        <li>Carrying more passengers than the vehicle's capacity (2 persons)</li>
                        <li>Towing or pushing another vehicle or any other equipment</li>
                        <li>Transporting hazardous, flammable, toxic or explosive materials</li>
                        <li>Motor sports, racing or competitive events</li>
                        <li>Driving in breach of traffic regulations</li>
                        <li>Any unlawful purpose</li>
                    </ul>
                    <?php endif; ?>
                </div>

                <div class="cgv-section">
                    <?php if ($fr): ?>
                    <h3>2. Conducteurs autorisés</h3>
                    <p>Seul le locataire désigné au contrat est autorisé à conduire le véhicule. Le conducteur doit être titulaire d'un <strong>permis de conduire valide</strong> (catégorie AM ou B) au moment de la location.</p>
                    <p>Il est interdit de conduire le véhicule sous l'influence d'alcool, de drogues ou de tout médicament susceptible d'altérer les réflexes ou l'attention.</p>
                    <?php else: ?>
                    <h3>2. Authorised Drivers</h3>
                    <p>Only the renter named in the agreement is authorised to drive the vehicle. The driver must hold a <strong>valid driving licence</strong> (category AM or B) at the time of rental.</p>
                    <p>It is forbidden to drive the vehicle under the influence of alcohol, drugs or any medication likely to impair reflexes or attention.</p>
                    <?php endif; ?>
                </div>

                <div class="cgv-section">
                    <?php if ($fr): ?>
                    <h3>3. Zone de circulation</h3>
                    <p>Le véhicule est autorisé à circuler uniquement sur le territoire de la commune de Nice et ses environs immédiats, dans le cadre d'une utilisation touristique ou de loisirs. Toute sortie de la zone autorisée doit faire l'objet d'un accord préalable de NOMADRIVE.</p>
                    <?php else: ?>
                    <h3>3. Authorised Area</h3>
                    <p>The vehicle is authorised to travel only within the municipality of Nice and its immediate surroundings, for tourist or leisure purposes. Any travel outside the authorised area must be agreed in advance with NOMADRIVE.</p>
                    <?php endif; ?>
                </div>

                <div class="cgv-section">
                    <?php if ($fr): ?>
                    <h3>4. Bonnes pratiques de conduite</h3>
                    <p>Vous devez conduire et garer le véhicule avec précaution, en respectant le Code de la route et en adoptant une conduite adaptée aux conditions de circulation.</p>
                    <p>En cas d'affichage d'un témoin d'avertissement sur le tableau de bord, vous devez contacter immédiatement NOMADRIVE avant de poursuivre votre trajet.</p>
                    <?php else: ?>
                    <h3>4. Safe Driving Practices</h3>
                    <p>You must drive and park the vehicle with care, complying with traffic regulations and adapting your driving to road conditions.</p>
                    <p>If a warning light appears on the dashboard, you must immediately contact NOMADRIVE before continuing your journey.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PARTIE IV -->
            <div class="cgv-part" id="accidents">
                <div class="cgv-part-title">
                    <div class="part-num"><i class="fa-duotone fa-solid fa-triangle-exclamation"></i></div>
                    <?= $fr ? 'Accidents et sinistres' : 'Accidents and Claims' ?>
                </div>

                <div class="cgv-section">
                    <?php if ($fr): ?>
                    <p>En cas d'accident, de panne ou d'incident, vous devez :</p>
                    <ul>
                        <li>Assurer votre sécurité et celle des autres usagers</li>
                        <li>Contacter immédiatement NOMADRIVE : <a href="mailto:contact@nomadrive.fr">contact@nomadrive.fr</a></li>
                        <li>En cas d'accident avec tiers : appeler le 15 ou le 17 si nécessaire et remplir un constat amiable</li>
                        <li>Ne jamais quitter les lieux sans avoir relevé les coordonnées de l'autre partie</li>
                    </ul>
                    <p>Tout incident doit être déclaré dans les <strong>24 heures</strong> suivant sa survenance. En cas de vol, vous devez déposer une plainte auprès des autorités compétentes dans les 24 heures et remettre une copie à NOMADRIVE.</p>
                    <p>Votre responsabilité financière en cas de dommage est couverte par la caution de <?= $caution ?> € prélevée à la prise en charge. En cas de dommages dépassant ce montant, des frais supplémentaires pourront vous être facturés.</p>
                    <?php else: ?>
                    <p>In the event of an accident, breakdown or incident, you must:</p>
                    <ul>
                        <li>Ensure your own safety and that of other road users</li>
                        <li>Immediately contact NOMADRIVE: <a href="mailto:contact@nomadrive.fr">contact@nomadrive.fr</a></li>
                        <li>In the event of an accident involving a third party: call emergency services if necessary and complete an accident report form</li>
                        <li>Never leave the scene without obtaining the other party's details</li>
                    </ul>
                    <p>Any incident must be reported within <strong>24 hours</strong> of its occurrence. In the event of theft, you must file a police report within 24 hours and provide a copy to NOMADRIVE.</p>
                    <p>Your financial liability in the event of damage is covered by the €<?= $caution ?> security deposit collected at pick-up. If damage exceeds this amount, additional charges may be invoiced.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PARTIE V -->
            <div class="cgv-part" id="restitution">
                <div class="cgv-part-title">
                    <div class="part-num"><i class="fa-duotone fa-solid fa-flag-checkered"></i></div>
                    <?= $fr ? 'Restitution du véhicule' : 'Vehicle Return' ?>
                </div>

                <div class="cgv-section">
                    <?php if ($fr): ?>
                    <p>Le véhicule doit être restitué à la date et à l'heure convenues, au lieu de prise en charge ou à tout autre lieu préalablement convenu. Toute restitution tardive sera facturée en supplément selon le tarif horaire en vigueur.</p>
                    <p>Le véhicule doit être restitué :</p>
                    <ul>
                        <li>Dans le même état qu'à la prise en charge (hors usure normale)</li>
                        <li>Propre intérieurement et extérieurement</li>
                        <li>Avec tous ses équipements et accessoires</li>
                    </ul>
                    <p>Un état des lieux de restitution est réalisé conjointement. En cas d'absence du locataire, NOMADRIVE procédera à l'état des lieux seul et le résultat fera foi.</p>
                    <?php else: ?>
                    <p>The vehicle must be returned on the agreed date and time, at the pick-up location or any other previously agreed location. Any late return will be charged at the applicable hourly rate.</p>
                    <p>The vehicle must be returned:</p>
                    <ul>
                        <li>In the same condition as at pick-up (excluding normal wear)</li>
                        <li>Clean inside and out</li>
                        <li>With all its equipment and accessories</li>
                    </ul>
                    <p>A joint return inspection is carried out. If the renter is absent, NOMADRIVE will proceed with the inspection alone and the result will be binding.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PARTIE VI -->
            <div class="cgv-part" id="annulation">
                <div class="cgv-part-title">
                    <div class="part-num"><i class="fa-duotone fa-solid fa-calendar-xmark"></i></div>
                    <?= $fr ? 'Politique d\'annulation' : 'Cancellation Policy' ?>
                </div>

                <div class="cgv-section">
                    <?php if ($fr): ?>
                    <h3>1. Modalités de demande</h3>
                    <p>Toute demande d'annulation doit être adressée par écrit à <a href="mailto:contact@nomadrive.fr">contact@nomadrive.fr</a> ou effectuée directement depuis la plateforme de réservation utilisée lors de la commande (site Nomadrive ou OTA partenaire). Le terme « OTA » (Online Travel Agency) désigne toute agence de voyage en ligne par l'intermédiaire de laquelle la réservation a pu être effectuée.</p>
                    <?php else: ?>
                    <h3>1. How to Cancel</h3>
                    <p>Any cancellation request must be sent in writing to <a href="mailto:contact@nomadrive.fr">contact@nomadrive.fr</a> or made directly through the booking platform used at the time of the order (Nomadrive website or partner OTA). The term "OTA" (Online Travel Agency) refers to any online travel agency through which the booking may have been made.</p>
                    <?php endif; ?>
                </div>

                <div class="cgv-section">
                    <?php if ($fr): ?>
                    <h3>2. Conditions de remboursement</h3>
                    <ul>
                        <li><strong>Annulation plus de 24 heures avant</strong> la date et l'heure de début de la location ou de la visite : remboursement intégral de la somme versée.</li>
                        <li><strong>Annulation 24 heures ou moins avant</strong> la date et l'heure de début : aucun remboursement (100 % de frais d'annulation).</li>
                        <li><strong>Absence du client (no-show)</strong> ou retard supérieur à 30 minutes sans avoir prévenu : aucun remboursement.</li>
                    </ul>
                    <?php else: ?>
                    <h3>2. Refund Conditions</h3>
                    <ul>
                        <li><strong>Cancellation more than 24 hours before</strong> the scheduled start date and time of the rental or tour: full refund.</li>
                        <li><strong>Cancellation 24 hours or less before</strong> the scheduled start date and time: no refund (100% cancellation fee).</li>
                        <li><strong>No-show</strong> or arrival more than 30 minutes late without prior notice: no refund.</li>
                    </ul>
                    <?php endif; ?>
                </div>

                <div class="cgv-section">
                    <?php if ($fr): ?>
                    <h3>3. Conditions météorologiques</h3>
                    <p>En cas de conditions météorologiques rendant l'utilisation du véhicule dangereuse (alerte orange ou rouge Météo-France, route impraticable), NOMADRIVE se réserve le droit d'annuler ou de reporter la prestation. Dans ce cas, le client se verra proposer au choix un report à une date ultérieure ou un remboursement intégral.</p>
                    <?php else: ?>
                    <h3>3. Weather Conditions</h3>
                    <p>In the event of weather conditions making use of the vehicle unsafe (orange or red Météo-France weather warning, impassable roads), NOMADRIVE reserves the right to cancel or reschedule the service. In such cases, the customer will be offered either a rescheduled date or a full refund.</p>
                    <?php endif; ?>
                </div>

                <div class="cgv-section">
                    <?php if ($fr): ?>
                    <h3>4. Force majeure</h3>
                    <p>En cas de force majeure au sens de l'article 1218 du Code civil (événement imprévisible, irrésistible et extérieur aux parties) empêchant l'exécution de la prestation, aucune des parties ne pourra être tenue responsable. Un report ou un remboursement sera proposé selon les circonstances.</p>
                    <?php else: ?>
                    <h3>4. Force Majeure</h3>
                    <p>In the event of force majeure within the meaning of Article 1218 of the French Civil Code (an unforeseeable, irresistible event beyond the control of the parties) preventing performance of the service, neither party shall be held liable. A rescheduled booking or a refund will be offered according to the circumstances.</p>
                    <?php endif; ?>
                </div>

                <div class="cgv-section">
                    <?php if ($fr): ?>
                    <h3>5. Réservations via OTA</h3>
                    <p>Lorsque la réservation a été effectuée via une OTA partenaire, la politique d'annulation de ladite OTA s'applique en complément des présentes conditions. En cas de contradiction, la règle la plus favorable au client prévaut dans la limite des engagements commerciaux pris par NOMADRIVE auprès de ces partenaires.</p>
                    <?php else: ?>
                    <h3>5. OTA Bookings</h3>
                    <p>Where the booking was made through a partner OTA, the cancellation policy of that OTA applies in addition to these terms. In case of conflict, the rule most favourable to the customer shall prevail, within the limits of the commercial commitments made by NOMADRIVE to such partners.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PARTIE VII -->
            <div class="cgv-part" id="dispositions-finales">
                <div class="cgv-part-title">
                    <div class="part-num"><i class="fa-duotone fa-solid fa-scale-balanced"></i></div>
                    <?= $fr ? 'Dispositions finales' : 'Final Provisions' ?>
                </div>

                <div class="cgv-section">
                    <?php if ($fr): ?>
                    <p>Le non-respect des présentes Conditions Générales de Location pourra entraîner la résiliation immédiate du contrat, sans remboursement du montant payé, et l'engagement de la responsabilité du locataire pour tous les préjudices en découlant.</p>
                    <p>NOMADRIVE se réserve le droit de modifier les présentes conditions à tout moment. La version applicable est celle en vigueur à la date de signature du contrat.</p>
                    <p>Si une clause des présentes conditions s'avérait illégale ou inapplicable, les autres clauses resteraient pleinement en vigueur.</p>
                    <p>Le présent contrat est régi par le <strong>droit français</strong>. Tout litige relève de la compétence des tribunaux de Nice.</p>
                    <?php else: ?>
                    <p>Failure to comply with these General Rental Terms and Conditions may result in the immediate termination of the agreement, without refund of any amount paid, and the renter's liability for all resulting losses.</p>
                    <p>NOMADRIVE reserves the right to amend these terms at any time. The applicable version is the one in force at the date of signing the agreement.</p>
                    <p>If any provision of these terms is found to be unlawful or unenforceable, the remaining provisions shall remain in full force and effect.</p>
                    <p>This agreement is governed by <strong>French law</strong>. Any dispute falls under the jurisdiction of the courts of Nice.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="cgv-footer-note">
                <strong>NICE ACTIVITY (NOMADRIVE)</strong> &mdash; SAS au capital de 100 000 € &mdash; RCS Nice 994 620 615<br>
                EUID FR0605.994620615 &mdash; N° gestion 2025B04038<br>
                2 Place Guynemer, 06300 Nice &mdash; <a href="mailto:contact@nomadrive.fr">contact@nomadrive.fr</a> &mdash; <a href="https://nomadrive.fr">nomadrive.fr</a><br>
                <small><?= $fr ? 'Ce document constitue les Conditions Générales de Location applicables à toute location de véhicule électrique auprès de NICE ACTIVITY, exploitée sous le nom commercial NOMADRIVE.' : 'This document constitutes the General Rental Terms and Conditions applicable to any electric vehicle rental from NICE ACTIVITY, operating under the trade name NOMADRIVE.' ?></small>
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer class="site-footer">
        <div class="footer-inner">
            <div class="footer-brand">
                <h3 class="footer-logo">NOMADRIVE</h3>
                <p class="footer-tagline"><?= $fr ? 'Tours guidés en voiture électrique à Nice.' : 'Guided electric car tours in Nice.' ?></p>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> NOMADRIVE — <?= $fr ? 'Tous droits réservés.' : 'All rights reserved.' ?></p>
                <div class="footer-legal">
                    <a href="/cgv.php">CGV</a>
                    <a href="/legal.php?lang=<?= $lang ?>"><?= $fr ? 'Mentions légales' : 'Legal notice' ?></a>
                </div>
            </div>
        </div>
    </footer>

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
    </script>
    <!-- Cookie Consent Banner (analytics uniquement) -->
    <div class="cookie-banner" id="cookie-banner" role="dialog">
        <div class="cookie-banner-inner">
            <div class="cookie-banner-icon">🍪</div>
            <div class="cookie-banner-text">
                <?php if ($fr): ?>
                    Ce site utilise des cookies de mesure d'audience pour améliorer votre expérience.
                    <a href="/legal.php?lang=fr#cookies">En savoir plus</a>
                <?php else: ?>
                    This site uses audience measurement cookies to improve your experience.
                    <a href="/legal.php?lang=en#cookies">Learn more</a>
                <?php endif; ?>
            </div>
            <div class="cookie-banner-actions">
                <button class="cookie-btn cookie-btn-refuse" id="cookie-refuse"><?= $fr ? 'Refuser' : 'Refuse' ?></button>
                <button class="cookie-btn cookie-btn-accept" id="cookie-accept"><?= $fr ? 'Accepter' : 'Accept' ?></button>
            </div>
        </div>
    </div>

    <script>
    (function() {
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
