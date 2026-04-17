<?php
require_once __DIR__ . '/config.php';
$lang = in_array($_GET['lang'] ?? '', ['fr', 'en']) ? $_GET['lang'] : 'fr';
$fr = ($lang === 'fr');
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $fr ? 'Mentions Légales' : 'Legal Notice' ?> | NOMADRIVE</title>
    <meta name="description" content="<?= $fr ? 'Mentions légales du site NOMADRIVE — Tours guidés en voiture électrique à Nice.' : 'Legal notice of NOMADRIVE — Guided electric car tours in Nice.' ?>">
    <meta name="robots" content="noindex, follow">
    <link rel="canonical" href="https://nomadrive.fr/legal.php">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://kit.fontawesome.com/494ceebc6d.js" crossorigin="anonymous"></script>
    <style>
        .legal-page { max-width: 860px; margin: 0 auto; padding: 40px 20px 80px; }
        .legal-header { text-align: center; margin-bottom: 48px; padding-bottom: 32px; border-bottom: 2px solid #e8f4fd; }
        .legal-header .badge { display: inline-block; background: #e8f4fd; color: #0077b6; font-size: 11px; font-weight: 700; letter-spacing: .1em; padding: 4px 12px; border-radius: 20px; margin-bottom: 14px; text-transform: uppercase; }
        .legal-header h1 { font-family: 'Playfair Display', serif; font-size: clamp(1.6rem, 4vw, 2.4rem); color: #0a1628; margin: 0 0 10px; }
        .legal-header .legal-meta { font-size: 13px; color: #6b7280; }
        .legal-toc { background: #f8fbfe; border: 1px solid #dbeafe; border-radius: 12px; padding: 20px 24px; margin-bottom: 48px; }
        .legal-toc h2 { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #0077b6; margin: 0 0 12px; }
        .legal-toc ol { margin: 0; padding-left: 18px; }
        .legal-toc li { margin-bottom: 6px; }
        .legal-toc a { color: #0077b6; text-decoration: none; font-size: 14px; }
        .legal-toc a:hover { text-decoration: underline; }
        .legal-part { margin-bottom: 52px; }
        .legal-part-title { display: flex; align-items: center; gap: 12px; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: #0077b6; margin-bottom: 24px; padding-bottom: 10px; border-bottom: 2px solid #e8f4fd; }
        .legal-part-title .part-icon { background: #0077b6; color: #fff; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
        .legal-section { margin-bottom: 28px; }
        .legal-section h3 { font-size: 15px; font-weight: 700; color: #0a1628; margin: 0 0 10px; }
        .legal-section p, .legal-section li { font-size: 14px; line-height: 1.75; color: #374151; margin: 0 0 8px; }
        .legal-section ul { padding-left: 18px; margin: 8px 0; list-style: disc; }
        .legal-section ul li { margin-bottom: 5px; }
        .legal-section a { color: #0077b6; text-decoration: none; }
        .legal-section a:hover { text-decoration: underline; }
        .legal-info-card { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px; padding: 20px 24px; margin: 14px 0; }
        .legal-info-card strong { color: #0369a1; }
        .legal-info-card p { margin: 0 0 6px; font-size: 14px; color: #0c4a6e; }
        .legal-info-card p:last-child { margin-bottom: 0; }
        .legal-important { background: #fff8e1; border-left: 4px solid #f59e0b; border-radius: 0 8px 8px 0; padding: 12px 16px; margin: 14px 0; }
        .legal-important strong { color: #b45309; font-size: 13px; text-transform: uppercase; letter-spacing: .05em; display: block; margin-bottom: 4px; }
        .legal-important p { margin: 0; font-size: 13px; color: #78350f; }
        .legal-footer-note { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px; padding: 20px 24px; text-align: center; font-size: 13px; color: #0369a1; margin-top: 48px; }
        .legal-footer-note a { color: #0077b6; font-weight: 600; }
        .back-link { display: inline-flex; align-items: center; gap: 6px; color: #0077b6; font-size: 14px; font-weight: 500; text-decoration: none; margin-bottom: 32px; }
        .back-link:hover { text-decoration: underline; }
        .legal-table { width: 100%; border-collapse: collapse; margin: 12px 0; }
        .legal-table td { padding: 10px 14px; font-size: 14px; border-bottom: 1px solid #e8f4fd; color: #374151; }
        .legal-table td:first-child { font-weight: 600; color: #0a1628; width: 40%; background: #f8fbfe; }
        @media (max-width: 600px) {
            .legal-page { padding: 24px 16px 60px; }
            .legal-toc { padding: 16px; }
            .legal-table td { padding: 8px 10px; font-size: 13px; }
            .legal-table td:first-child { width: 35%; }
        }
    </style>

    <!-- Google Tag Manager -->
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
        <div class="legal-page">

            <a href="/" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
                <?= $fr ? 'Retour à l\'accueil' : 'Back to home' ?>
            </a>

            <div class="legal-header">
                <div class="badge"><?= $fr ? 'Mentions Légales' : 'Legal Notice' ?></div>
                <h1><?= $fr ? 'Mentions Légales' : 'Legal Notice' ?><br>NOMADRIVE</h1>
                <p class="legal-meta">
                    <?= $fr ? 'Dernière mise à jour' : 'Last updated' ?> : <?= date('d/m/Y') ?>
                </p>
            </div>

            <!-- Sommaire -->
            <div class="legal-toc">
                <h2><?= $fr ? 'Sommaire' : 'Table of Contents' ?></h2>
                <ol>
                    <li><a href="#editeur"><?= $fr ? 'Éditeur du site' : 'Website Publisher' ?></a></li>
                    <li><a href="#directeur"><?= $fr ? 'Directeur de la publication' : 'Publication Director' ?></a></li>
                    <li><a href="#hebergeur"><?= $fr ? 'Hébergement' : 'Website Hosting' ?></a></li>
                    <li><a href="#propriete"><?= $fr ? 'Propriété intellectuelle' : 'Intellectual Property' ?></a></li>
                    <li><a href="#donnees"><?= $fr ? 'Protection des données personnelles' : 'Personal Data Protection' ?></a></li>
                    <li><a href="#cookies"><?= $fr ? 'Cookies' : 'Cookies' ?></a></li>
                    <li><a href="#responsabilite"><?= $fr ? 'Limitation de responsabilité' : 'Limitation of Liability' ?></a></li>
                    <li><a href="#litiges"><?= $fr ? 'Droit applicable et litiges' : 'Applicable Law and Disputes' ?></a></li>
                    <li><a href="#mediation"><?= $fr ? 'Médiation de la consommation' : 'Consumer Mediation' ?></a></li>
                    <li><a href="#credits"><?= $fr ? 'Crédits' : 'Credits' ?></a></li>
                </ol>
            </div>

            <!-- 1. Éditeur du site -->
            <div class="legal-part" id="editeur">
                <div class="legal-part-title">
                    <div class="part-icon"><i class="fa-solid fa-building"></i></div>
                    <?= $fr ? 'Éditeur du site' : 'Website Publisher' ?>
                </div>
                <div class="legal-section">
                    <?php if ($fr): ?>
                    <p>Le site <strong>nomadrive.fr</strong> est édité par :</p>
                    <?php else: ?>
                    <p>The website <strong>nomadrive.fr</strong> is published by:</p>
                    <?php endif; ?>

                    <div class="legal-info-card">
                        <table class="legal-table">
                            <tr>
                                <td><?= $fr ? 'Raison sociale' : 'Company name' ?></td>
                                <td><strong>NICE ACTIVITY</strong></td>
                            </tr>
                            <tr>
                                <td><?= $fr ? 'Nom commercial' : 'Trade name' ?></td>
                                <td><strong>NOMADRIVE</strong></td>
                            </tr>
                            <tr>
                                <td><?= $fr ? 'Forme juridique' : 'Legal form' ?></td>
                                <td><?= $fr ? 'SAS (Société par Actions Simplifiée)' : 'SAS (Simplified Joint Stock Company)' ?></td>
                            </tr>
                            <tr>
                                <td><?= $fr ? 'Capital social' : 'Share capital' ?></td>
                                <td>100 000 €</td>
                            </tr>
                            <tr>
                                <td><?= $fr ? 'Immatriculation RCS' : 'Trade Register (RCS)' ?></td>
                                <td>RCS Nice 994 620 615</td>
                            </tr>
                            <tr>
                                <td>EUID</td>
                                <td>FR0605.994620615</td>
                            </tr>
                            <tr>
                                <td><?= $fr ? 'N° de gestion' : 'Management number' ?></td>
                                <td>2025B04038</td>
                            </tr>
                            <tr>
                                <td><?= $fr ? 'Siège social' : 'Registered office' ?></td>
                                <td>2 Place Guynemer, 06300 Nice, France</td>
                            </tr>
                            <tr>
                                <td><?= $fr ? 'Téléphone' : 'Phone' ?></td>
                                <td><a href="tel:+33633338792">+33 6 33 33 87 92</a></td>
                            </tr>
                            <tr>
                                <td>Email</td>
                                <td><a href="mailto:contact@nomadrive.fr">contact@nomadrive.fr</a></td>
                            </tr>
                            <tr>
                                <td><?= $fr ? 'Site internet' : 'Website' ?></td>
                                <td><a href="https://nomadrive.fr" target="_blank">nomadrive.fr</a></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 2. Directeur de la publication -->
            <div class="legal-part" id="directeur">
                <div class="legal-part-title">
                    <div class="part-icon"><i class="fa-solid fa-user-tie"></i></div>
                    <?= $fr ? 'Directeur de la publication' : 'Publication Director' ?>
                </div>
                <div class="legal-section">
                    <?php if ($fr): ?>
                    <p>Le directeur de la publication est le <strong>représentant légal</strong> de la société NICE ACTIVITY.</p>
                    <p>Contact : <a href="mailto:contact@nomadrive.fr">contact@nomadrive.fr</a></p>
                    <?php else: ?>
                    <p>The publication director is the <strong>legal representative</strong> of NICE ACTIVITY.</p>
                    <p>Contact: <a href="mailto:contact@nomadrive.fr">contact@nomadrive.fr</a></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 3. Hébergement -->
            <div class="legal-part" id="hebergeur">
                <div class="legal-part-title">
                    <div class="part-icon"><i class="fa-solid fa-server"></i></div>
                    <?= $fr ? 'Hébergement' : 'Website Hosting' ?>
                </div>
                <div class="legal-section">
                    <?php if ($fr): ?>
                    <p>Le site nomadrive.fr est hébergé par :</p>
                    <?php else: ?>
                    <p>The website nomadrive.fr is hosted by:</p>
                    <?php endif; ?>

                    <div class="legal-info-card">
                        <table class="legal-table">
                            <tr>
                                <td><?= $fr ? 'Hébergeur' : 'Host' ?></td>
                                <td><strong>Google Cloud Platform (GCP)</strong></td>
                            </tr>
                            <tr>
                                <td><?= $fr ? 'Raison sociale' : 'Company name' ?></td>
                                <td>Google Ireland Limited</td>
                            </tr>
                            <tr>
                                <td><?= $fr ? 'Adresse' : 'Address' ?></td>
                                <td>Gordon House, Barrow Street, Dublin 4, Ireland</td>
                            </tr>
                            <tr>
                                <td><?= $fr ? 'Site internet' : 'Website' ?></td>
                                <td><a href="https://cloud.google.com" target="_blank" rel="noopener">cloud.google.com</a></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 4. Propriété intellectuelle -->
            <div class="legal-part" id="propriete">
                <div class="legal-part-title">
                    <div class="part-icon"><i class="fa-solid fa-copyright"></i></div>
                    <?= $fr ? 'Propriété intellectuelle' : 'Intellectual Property' ?>
                </div>
                <div class="legal-section">
                    <?php if ($fr): ?>
                    <p>L'ensemble du contenu de ce site (textes, images, photographies, logos, vidéos, graphismes, icônes, mise en page, code source, etc.) est la propriété exclusive de <strong>NICE ACTIVITY</strong> (exploitant sous le nom commercial NOMADRIVE) ou de ses partenaires, et est protégé par les lois françaises et internationales relatives à la propriété intellectuelle.</p>
                    <p>Toute reproduction, représentation, modification, publication, adaptation, distribution ou exploitation de tout ou partie du contenu de ce site, par quelque moyen ou procédé que ce soit, est <strong>interdite</strong> sans l'autorisation préalable écrite de NICE ACTIVITY.</p>
                    <p>Le nom <strong>NOMADRIVE</strong>, le logo, ainsi que les noms des services et produits associés, sont des marques ou dénominations commerciales de NICE ACTIVITY. Toute utilisation non autorisée est constitutive de contrefaçon et passible de sanctions pénales.</p>
                    <?php else: ?>
                    <p>All content on this website (texts, images, photographs, logos, videos, graphics, icons, layout, source code, etc.) is the exclusive property of <strong>NICE ACTIVITY</strong> (operating under the trade name NOMADRIVE) or its partners, and is protected by French and international intellectual property laws.</p>
                    <p>Any reproduction, representation, modification, publication, adaptation, distribution or exploitation of all or part of the content of this website, by any means or process, is <strong>prohibited</strong> without prior written authorisation from NICE ACTIVITY.</p>
                    <p>The name <strong>NOMADRIVE</strong>, the logo, as well as the names of associated services and products, are trademarks or trade names of NICE ACTIVITY. Any unauthorised use constitutes counterfeiting and is subject to criminal penalties.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 5. Protection des données personnelles -->
            <div class="legal-part" id="donnees">
                <div class="legal-part-title">
                    <div class="part-icon"><i class="fa-solid fa-shield-halved"></i></div>
                    <?= $fr ? 'Protection des données personnelles' : 'Personal Data Protection' ?>
                </div>
                <div class="legal-section">
                    <?php if ($fr): ?>
                    <h3>Responsable du traitement</h3>
                    <p>Le responsable du traitement des données personnelles est <strong>NICE ACTIVITY</strong>, 2 Place Guynemer, 06300 Nice — <a href="mailto:contact@nomadrive.fr">contact@nomadrive.fr</a>.</p>

                    <h3>Données collectées</h3>
                    <p>Dans le cadre de l'utilisation du site et de nos services, nous pouvons être amenés à collecter les données personnelles suivantes :</p>
                    <ul>
                        <li><strong>Formulaire de contact :</strong> nom, adresse email, message</li>
                        <li><strong>Contrat de location :</strong> nom, prénom, adresse, email, photographie du permis de conduire, signature électronique</li>
                        <li><strong>Navigation sur le site :</strong> adresse IP, données de navigation (cookies, pages consultées)</li>
                        <li><strong>Données de géolocalisation :</strong> données issues du système de localisation embarqué dans les véhicules</li>
                    </ul>

                    <h3>Finalités du traitement</h3>
                    <p>Vos données personnelles sont collectées et traitées pour les finalités suivantes :</p>
                    <ul>
                        <li>Gestion des réservations et des contrats de location</li>
                        <li>Réponse à vos demandes via le formulaire de contact</li>
                        <li>Gestion de la flotte de véhicules et sécurité</li>
                        <li>Gestion des sinistres et des litiges</li>
                        <li>Respect de nos obligations légales et réglementaires</li>
                        <li>Amélioration de nos services et de l'expérience utilisateur</li>
                    </ul>

                    <h3>Base légale</h3>
                    <p>Le traitement de vos données repose sur :</p>
                    <ul>
                        <li><strong>L'exécution du contrat</strong> de location conclu entre vous et NICE ACTIVITY</li>
                        <li><strong>Le consentement</strong> que vous donnez lors de l'envoi du formulaire de contact</li>
                        <li><strong>L'intérêt légitime</strong> de NICE ACTIVITY (sécurité, gestion de flotte, amélioration des services)</li>
                        <li><strong>Le respect de ses obligations légales</strong></li>
                    </ul>

                    <h3>Durée de conservation</h3>
                    <p>Vos données personnelles sont conservées pendant la durée nécessaire aux finalités pour lesquelles elles ont été collectées :</p>
                    <ul>
                        <li><strong>Données contractuelles :</strong> 5 ans après la fin de la relation contractuelle (prescription civile)</li>
                        <li><strong>Données de contact :</strong> 3 ans à compter du dernier contact</li>
                        <li><strong>Données de navigation :</strong> 13 mois maximum</li>
                        <li><strong>Pièces comptables :</strong> 10 ans (obligation légale)</li>
                    </ul>

                    <h3>Vos droits</h3>
                    <p>Conformément au <strong>Règlement Général sur la Protection des Données (RGPD)</strong> et à la loi Informatique et Libertés, vous disposez des droits suivants :</p>
                    <ul>
                        <li><strong>Droit d'accès :</strong> obtenir la confirmation que vos données sont traitées et en obtenir une copie</li>
                        <li><strong>Droit de rectification :</strong> demander la correction de données inexactes ou incomplètes</li>
                        <li><strong>Droit à l'effacement :</strong> demander la suppression de vos données</li>
                        <li><strong>Droit à la limitation du traitement :</strong> demander la suspension du traitement dans certains cas</li>
                        <li><strong>Droit à la portabilité :</strong> recevoir vos données dans un format structuré</li>
                        <li><strong>Droit d'opposition :</strong> vous opposer au traitement de vos données</li>
                    </ul>
                    <p>Pour exercer vos droits, contactez-nous à : <a href="mailto:contact@nomadrive.fr">contact@nomadrive.fr</a></p>
                    <p>En cas de réclamation, vous pouvez saisir la <strong>Commission Nationale de l'Informatique et des Libertés (CNIL)</strong> : <a href="https://www.cnil.fr" target="_blank" rel="noopener">www.cnil.fr</a>.</p>

                    <h3>Transfert de données</h3>
                    <p>Vos données personnelles peuvent être transférées vers des pays situés en dehors de l'Union européenne dans le cadre de l'utilisation de services tiers (hébergement, envoi d'emails). Ces transferts sont encadrés par des garanties appropriées conformément au RGPD (clauses contractuelles types, décisions d'adéquation).</p>
                    <?php else: ?>
                    <h3>Data Controller</h3>
                    <p>The data controller is <strong>NICE ACTIVITY</strong>, 2 Place Guynemer, 06300 Nice, France — <a href="mailto:contact@nomadrive.fr">contact@nomadrive.fr</a>.</p>

                    <h3>Data Collected</h3>
                    <p>When using our website and services, we may collect the following personal data:</p>
                    <ul>
                        <li><strong>Contact form:</strong> name, email address, message</li>
                        <li><strong>Rental agreement:</strong> first and last name, address, email, driving licence photograph, electronic signature</li>
                        <li><strong>Website browsing:</strong> IP address, browsing data (cookies, pages viewed)</li>
                        <li><strong>Geolocation data:</strong> data from the vehicle's on-board tracking system</li>
                    </ul>

                    <h3>Purposes of Processing</h3>
                    <p>Your personal data is collected and processed for the following purposes:</p>
                    <ul>
                        <li>Management of bookings and rental agreements</li>
                        <li>Responding to enquiries via the contact form</li>
                        <li>Fleet management and safety</li>
                        <li>Claims and dispute management</li>
                        <li>Compliance with legal and regulatory obligations</li>
                        <li>Improvement of our services and user experience</li>
                    </ul>

                    <h3>Legal Basis</h3>
                    <p>The processing of your data is based on:</p>
                    <ul>
                        <li><strong>Performance of the contract</strong> entered into between you and NICE ACTIVITY</li>
                        <li><strong>Consent</strong> given when submitting the contact form</li>
                        <li><strong>Legitimate interest</strong> of NICE ACTIVITY (safety, fleet management, service improvement)</li>
                        <li><strong>Compliance with legal obligations</strong></li>
                    </ul>

                    <h3>Data Retention</h3>
                    <p>Your personal data is retained for the period necessary for the purposes for which it was collected:</p>
                    <ul>
                        <li><strong>Contractual data:</strong> 5 years after the end of the contractual relationship (civil limitation period)</li>
                        <li><strong>Contact data:</strong> 3 years from last contact</li>
                        <li><strong>Browsing data:</strong> 13 months maximum</li>
                        <li><strong>Accounting records:</strong> 10 years (legal requirement)</li>
                    </ul>

                    <h3>Your Rights</h3>
                    <p>In accordance with the <strong>General Data Protection Regulation (GDPR)</strong> and the French Data Protection Act, you have the following rights:</p>
                    <ul>
                        <li><strong>Right of access:</strong> obtain confirmation that your data is being processed and receive a copy</li>
                        <li><strong>Right to rectification:</strong> request correction of inaccurate or incomplete data</li>
                        <li><strong>Right to erasure:</strong> request deletion of your data</li>
                        <li><strong>Right to restriction of processing:</strong> request suspension of processing in certain cases</li>
                        <li><strong>Right to data portability:</strong> receive your data in a structured format</li>
                        <li><strong>Right to object:</strong> object to the processing of your data</li>
                    </ul>
                    <p>To exercise your rights, contact us at: <a href="mailto:contact@nomadrive.fr">contact@nomadrive.fr</a></p>
                    <p>If you have a complaint, you may contact the <strong>Commission Nationale de l'Informatique et des Libertés (CNIL)</strong>: <a href="https://www.cnil.fr" target="_blank" rel="noopener">www.cnil.fr</a>.</p>

                    <h3>Data Transfers</h3>
                    <p>Your personal data may be transferred to countries outside the European Union when using third-party services (hosting, email delivery). These transfers are governed by appropriate safeguards in accordance with the GDPR (standard contractual clauses, adequacy decisions).</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 6. Cookies -->
            <div class="legal-part" id="cookies">
                <div class="legal-part-title">
                    <div class="part-icon"><i class="fa-solid fa-cookie-bite"></i></div>
                    Cookies
                </div>
                <div class="legal-section">
                    <?php if ($fr): ?>
                    <h3>Qu'est-ce qu'un cookie ?</h3>
                    <p>Un cookie est un petit fichier texte stocké sur votre terminal (ordinateur, smartphone, tablette) lors de la visite d'un site internet. Il permet au site de mémoriser certaines informations relatives à votre navigation.</p>

                    <h3>Cookies utilisés sur ce site</h3>
                    <p>Le site nomadrive.fr utilise les types de cookies suivants :</p>

                    <h3>Cookies exemptés de consentement (strictement nécessaires)</h3>
                    <p>Ces cookies ne nécessitent pas votre consentement car ils sont indispensables au fonctionnement du site ou à la fourniture d'un service que vous avez explicitement demandé :</p>
                    <ul>
                        <li><strong>Cookies techniques :</strong> préférences de langue, mémorisation du choix de consentement cookies</li>
                        <li><strong>Cookies de sécurité (Google reCAPTCHA Enterprise) :</strong> protection du formulaire de contact contre les soumissions abusives. Ces cookies sont nécessaires à la sécurité du site et sont exemptés de consentement conformément aux recommandations de la CNIL.</li>
                        <li><strong>Cookies fonctionnels (Bokun / TripAdvisor) :</strong> notre système de réservation peut déposer des cookies lorsque vous utilisez le module de réservation en ligne. Ces cookies sont nécessaires au service de réservation que vous avez demandé.</li>
                    </ul>

                    <h3>Cookies soumis à consentement</h3>
                    <p>Ces cookies ne sont déposés qu'après votre accord explicite via le bandeau de consentement affiché lors de votre première visite :</p>
                    <ul>
                        <li><strong>Cookies de mesure d'audience (Google Tag Manager / Google Analytics) :</strong> nous utilisons <strong>Google Tag Manager</strong> (identifiant : GTM-5NH9D8CC) pour analyser la fréquentation et l'utilisation de notre site. Ces cookies permettent de collecter des statistiques anonymes de visite afin d'améliorer nos services. En cas de refus, aucun cookie d'analyse n'est déposé et le fonctionnement du site n'est pas affecté.</li>
                    </ul>

                    <h3>Votre choix et gestion des cookies</h3>
                    <p>Lors de votre première visite, un <strong>bandeau de consentement</strong> vous permet d'accepter ou de refuser les cookies de mesure d'audience. Votre choix est conservé pendant <strong>6 mois</strong>.</p>
                    <p>Vous pouvez également configurer votre navigateur pour accepter, refuser ou supprimer les cookies :</p>
                    <ul>
                        <li><a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener">Google Chrome</a></li>
                        <li><a href="https://support.mozilla.org/fr/kb/activer-desactiver-cookies" target="_blank" rel="noopener">Mozilla Firefox</a></li>
                        <li><a href="https://support.apple.com/fr-fr/guide/safari/sfri11471/mac" target="_blank" rel="noopener">Safari</a></li>
                        <li><a href="https://support.microsoft.com/fr-fr/microsoft-edge/supprimer-les-cookies-dans-microsoft-edge-63947406-40ac-c3b8-57b9-2a946a29ae09" target="_blank" rel="noopener">Microsoft Edge</a></li>
                    </ul>
                    <?php else: ?>
                    <h3>What is a cookie?</h3>
                    <p>A cookie is a small text file stored on your device (computer, smartphone, tablet) when visiting a website. It allows the site to remember certain information about your browsing.</p>

                    <h3>Cookies used on this site</h3>
                    <p>The website nomadrive.fr uses the following types of cookies:</p>

                    <h3>Cookies exempt from consent (strictly necessary)</h3>
                    <p>These cookies do not require your consent as they are essential for the website to function or for providing a service you have explicitly requested:</p>
                    <ul>
                        <li><strong>Technical cookies:</strong> language preferences, cookie consent choice storage</li>
                        <li><strong>Security cookies (Google reCAPTCHA Enterprise):</strong> protection of the contact form against abusive submissions. These cookies are necessary for website security and are exempt from consent in accordance with CNIL guidelines.</li>
                        <li><strong>Functional cookies (Bokun / TripAdvisor):</strong> our booking system may set cookies when you use the online booking module. These cookies are necessary for the booking service you have requested.</li>
                    </ul>

                    <h3>Cookies subject to consent</h3>
                    <p>These cookies are only set after your explicit agreement via the consent banner displayed on your first visit:</p>
                    <ul>
                        <li><strong>Analytics cookies (Google Tag Manager / Google Analytics):</strong> we use <strong>Google Tag Manager</strong> (ID: GTM-5NH9D8CC) to analyse website traffic and usage. These cookies collect anonymous visitor statistics to help improve our services. If refused, no analytics cookies are set and website functionality is not affected.</li>
                    </ul>

                    <h3>Your choice and cookie management</h3>
                    <p>On your first visit, a <strong>consent banner</strong> allows you to accept or refuse analytics cookies. Your choice is stored for <strong>6 months</strong>.</p>
                    <p>You can also configure your browser to accept, refuse or delete cookies:</p>
                    <ul>
                        <li><a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener">Google Chrome</a></li>
                        <li><a href="https://support.mozilla.org/en-US/kb/enable-and-disable-cookies-website-preferences" target="_blank" rel="noopener">Mozilla Firefox</a></li>
                        <li><a href="https://support.apple.com/en-gb/guide/safari/sfri11471/mac" target="_blank" rel="noopener">Safari</a></li>
                        <li><a href="https://support.microsoft.com/en-us/microsoft-edge/delete-cookies-in-microsoft-edge-63947406-40ac-c3b8-57b9-2a946a29ae09" target="_blank" rel="noopener">Microsoft Edge</a></li>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 7. Limitation de responsabilité -->
            <div class="legal-part" id="responsabilite">
                <div class="legal-part-title">
                    <div class="part-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    <?= $fr ? 'Limitation de responsabilité' : 'Limitation of Liability' ?>
                </div>
                <div class="legal-section">
                    <?php if ($fr): ?>
                    <p>Les informations contenues sur ce site sont fournies à titre indicatif et sont susceptibles d'être modifiées à tout moment sans préavis. <strong>NICE ACTIVITY</strong> ne saurait être tenue responsable des éventuelles erreurs ou omissions dans le contenu du site.</p>
                    <p>NICE ACTIVITY ne pourra être tenue responsable des dommages directs ou indirects pouvant résulter de l'accès ou de l'utilisation du site, y compris l'inaccessibilité, les pertes de données, les détériorations, destructions ou virus pouvant affecter l'équipement informatique de l'utilisateur.</p>
                    <p>Le site peut contenir des liens hypertextes vers d'autres sites internet. NICE ACTIVITY n'exerce aucun contrôle sur ces sites et décline toute responsabilité quant à leur contenu ou leurs pratiques en matière de protection des données personnelles.</p>
                    <?php else: ?>
                    <p>The information provided on this website is given for indicative purposes only and may be modified at any time without notice. <strong>NICE ACTIVITY</strong> cannot be held liable for any errors or omissions in the website content.</p>
                    <p>NICE ACTIVITY shall not be held liable for any direct or indirect damage that may result from access to or use of the website, including but not limited to inaccessibility, data loss, deterioration, destruction or viruses that may affect the user's computer equipment.</p>
                    <p>The website may contain hyperlinks to other websites. NICE ACTIVITY exercises no control over these sites and disclaims all responsibility regarding their content or their personal data protection practices.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 8. Droit applicable et litiges -->
            <div class="legal-part" id="litiges">
                <div class="legal-part-title">
                    <div class="part-icon"><i class="fa-solid fa-scale-balanced"></i></div>
                    <?= $fr ? 'Droit applicable et litiges' : 'Applicable Law and Disputes' ?>
                </div>
                <div class="legal-section">
                    <?php if ($fr): ?>
                    <p>Les présentes mentions légales sont soumises au <strong>droit français</strong>.</p>
                    <p>En cas de litige relatif à l'interprétation ou à l'exécution des présentes, et à défaut de résolution amiable, les tribunaux compétents du ressort de <strong>Nice (France)</strong> seront seuls compétents.</p>
                    <?php else: ?>
                    <p>This legal notice is governed by <strong>French law</strong>.</p>
                    <p>In the event of any dispute relating to the interpretation or enforcement of this notice, and failing an amicable resolution, the competent courts of <strong>Nice (France)</strong> shall have exclusive jurisdiction.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 9. Médiation de la consommation -->
            <div class="legal-part" id="mediation">
                <div class="legal-part-title">
                    <div class="part-icon"><i class="fa-solid fa-handshake"></i></div>
                    <?= $fr ? 'Médiation de la consommation' : 'Consumer Mediation' ?>
                </div>
                <div class="legal-section">
                    <?php if ($fr): ?>
                    <p>Conformément aux articles L.611-1 et suivants du Code de la consommation, en cas de litige non résolu, le consommateur peut recourir gratuitement à un <strong>médiateur de la consommation</strong> en vue de la résolution amiable du différend.</p>
                    <p>Vous pouvez également déposer votre réclamation sur la plateforme européenne de règlement en ligne des litiges (RLL) : <a href="https://ec.europa.eu/consumers/odr/" target="_blank" rel="noopener">https://ec.europa.eu/consumers/odr/</a>.</p>
                    <p>Pour toute réclamation préalable, contactez-nous à : <a href="mailto:contact@nomadrive.fr">contact@nomadrive.fr</a>.</p>
                    <?php else: ?>
                    <p>In accordance with Articles L.611-1 et seq. of the French Consumer Code, in the event of an unresolved dispute, the consumer may have free recourse to a <strong>consumer mediator</strong> for amicable resolution of the dispute.</p>
                    <p>You may also file your complaint on the European Online Dispute Resolution (ODR) platform: <a href="https://ec.europa.eu/consumers/odr/" target="_blank" rel="noopener">https://ec.europa.eu/consumers/odr/</a>.</p>
                    <p>For any prior complaint, contact us at: <a href="mailto:contact@nomadrive.fr">contact@nomadrive.fr</a>.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 10. Crédits -->
            <div class="legal-part" id="credits">
                <div class="legal-part-title">
                    <div class="part-icon"><i class="fa-solid fa-palette"></i></div>
                    <?= $fr ? 'Crédits' : 'Credits' ?>
                </div>
                <div class="legal-section">
                    <?php if ($fr): ?>
                    <p><strong>Conception et développement du site :</strong> NICE ACTIVITY</p>
                    <p><strong>Photographies :</strong> © NICE ACTIVITY / NOMADRIVE — Tous droits réservés</p>
                    <p><strong>Icônes :</strong> <a href="https://fontawesome.com" target="_blank" rel="noopener">Font Awesome</a></p>
                    <p><strong>Typographies :</strong> <a href="https://fonts.google.com" target="_blank" rel="noopener">Google Fonts</a> (Inter, Playfair Display)</p>
                    <?php else: ?>
                    <p><strong>Website design and development:</strong> NICE ACTIVITY</p>
                    <p><strong>Photography:</strong> © NICE ACTIVITY / NOMADRIVE — All rights reserved</p>
                    <p><strong>Icons:</strong> <a href="https://fontawesome.com" target="_blank" rel="noopener">Font Awesome</a></p>
                    <p><strong>Fonts:</strong> <a href="https://fonts.google.com" target="_blank" rel="noopener">Google Fonts</a> (Inter, Playfair Display)</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pied de page mentions légales -->
            <div class="legal-footer-note">
                <strong>NICE ACTIVITY (NOMADRIVE)</strong> &mdash; SAS au capital de 100 000 € &mdash; RCS Nice 994 620 615<br>
                EUID FR0605.994620615 &mdash; N° gestion 2025B04038<br>
                2 Place Guynemer, 06300 Nice &mdash; <a href="mailto:contact@nomadrive.fr">contact@nomadrive.fr</a> &mdash; <a href="https://nomadrive.fr">nomadrive.fr</a>
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
                    <a href="/cgv.php<?= $lang !== 'fr' ? '?lang='.$lang : '' ?>">CGV</a>
                    <a href="/legal.php<?= $lang !== 'fr' ? '?lang='.$lang : '' ?>"><?= $fr ? 'Mentions légales' : 'Legal notice' ?></a>
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
                    <a href="#cookies">En savoir plus</a>
                <?php else: ?>
                    This site uses audience measurement cookies to improve your experience.
                    <a href="#cookies">Learn more</a>
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
