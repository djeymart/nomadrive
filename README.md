# NOMADRIVE

Tours guidés en voiture électrique à Nice — NICE ACTIVITY SAS, 2 place Guynemer, 06300 Nice.

---

## Vue d'ensemble

Trois produits Bokun :
- **City** (1194328) — 20 km, 2h, créneaux 10h et 14h
- **French Riviera** (1197812) — 35 km, 2h30, créneaux 10h et 14h
- **Sunset** (1197894) — 35 km, 2h30, créneau variable selon saison

L'application couvre quatre périmètres : site public, back-office opérateur, tablette embarquée dans les véhicules, et automatisations (crons + webhook).

---

## Architecture des fichiers

```
nomadrive/
│
├── config.php               Clés chiffrées AES-128-CBC (Bokun, SMTP, Stripe, Spotify…) + chargement settings BDD
├── nomadrive_auth.php       Auth partagée : session + cookie remember-me 30 jours
├── settings.php             Panel admin paramètres opérationnels (super-admin MADI requis pour modifier)
│
├── index.php                Site public (FR / EN / IT)
├── cgv.php                  Conditions générales
├── legal.php                Mentions légales
├── faq.php                  FAQ standalone
├── qr.php                   Tracking QR codes (source, device, OS)
│
├── dashboard.php            Dashboard opérateur — dossiers de location + caution + véhicules
├── manage.php               Back-office Bokun — résas, pool, planning, flotte
├── contrat.php              Formulaire contrat de location (5 étapes opérateur / 4 étapes client via lien sécurisé)
├── tablette.php             Interface embarquée dans les véhicules (GPS + Spotify)
│
├── stripe_caution.php       AJAX — caution pré-autorisation : create/send/capture/cancel + email pré-arrivée
├── webhook_stripe.php       Webhook Stripe compte principal (caution)
│
├── tip.php                  Page publique pourboire guide (Apple Pay / Google Pay / CB)
├── tip_api.php              API JSON — create_intent (public) + gestion guides (admin)
├── tip_admin.php            Admin pourboires — guides, onboarding Stripe Connect, stats
├── tip_webhook.php          Webhook Stripe Connect (payment_intent.succeeded)
│
├── cron_reviews.php         Cron 15 min — sync Bokun + emails avis
├── cron_caution.php         Cron horaire — auto-pré-enregistrement Bokun (si activé) + email pré-arrivée (si activé)
├── cron_closeouts.php       Cron 15 min — gestion pool (overcapacity + annulations)
├── webhook_sarbacane.php    Webhook passif — événements email Sarbacane
├── sync_gyg_reviews.php     Fonction de scrape GYG (appelée par index.php)
├── push_reviews.php         Endpoint passif — réception avis depuis script externe
│
├── test_sogecommerce.php    Page de test Sogecommerce (en attente activation REST)
├── SQL/
│   ├── nomadrive.sql        Schéma complet initial
│   ├── sql_stripe.sql       Table nomadrive_stripe_cautions
│   └── settings.sql         Paramètres opérationnels + colonne bokun_booking_id (à exécuter une fois)
│
├── AUTOMATISATIONS.md       Détail de tout ce qui tourne automatiquement
└── README.md                Ce fichier
```

---

## Dépendances

Le projet vit dans `/var/www/html/nomadrive/` et s'appuie sur le stack de `madi.mt` voisin :

```
/var/www/html/madi.mt/
├── vendor/autoload.php     Composer (PHPMailer, mPDF, Google Cloud Storage SDK)
├── php/fonctions.php       Helpers partagés dont upload_object() pour GCP
└── php/config.php          $db1 (PDO MySQL partagé entre madi.mt et nomadrive)
```

---

## Base de données

Toutes les tables sont préfixées `nomadrive_` dans la même DB que madi.mt.

| Table | Rôle |
|---|---|
| `nomadrive_customers` | Résas importées depuis Bokun. Clé : `bokun_booking_id`. Contient `closeout_resource_ids` pour la gestion du pool. |
| `nomadrive_vehicules` | Flotte propre (marque, modele, immatriculation, couleur, actif, guide) |
| `nomadrive_contrats` | Contrats signés : données client, véhicule, signature base64, URLs GCP (permis, PDF) |
| `nomadrive_dossiers` | Dossiers de location liés aux contrats : état avant/après (km, notes, photos GCP). Créé à l'arrivée physique du client, pas à la pré-complétion. |
| `nomadrive_email_log` | Trace de chaque email envoyé + statut webhook Sarbacane |
| `nomadrive_reviews` | Avis clients (source : google, gyg, manual) |
| `nomadrive_reviews_meta` | Note globale et total par source, avec timestamp de dernière sync |
| `nomadrive_settings` | Config clé/valeur dont `admin_password_hash` (sha256 doublé) |
| `nomadrive_auth_sessions` | Tokens remember-me (64 hex, expiry glissante 30 jours) |
| `nomadrive_stripe_cautions` | Cautions Stripe par contrat — session_id, payment_intent_id, status, checkout_url |
| `nomadrive_guides` | Guides avec compte Stripe Express — slug, stripe_account_id, onboarding_complete |
| `nomadrive_tips` | Pourboires reçus — guide_id, montant, commission, status |

---

## Stockage fichiers

GCP bucket `madi_bucket`, chemins :
- `nomadrive/permis/{ND-XXXXX}-recto-{token}.jpg`
- `nomadrive/contrats/{ND-XXXXX}-{token}.pdf`
- `nomadrive/dossiers/{ND-XXXXX}-avant-1.jpg`
- `nomadrive/dossiers/{ND-XXXXX}-apres-1.jpg`

---

## Fichiers en détail

### index.php — Site public
Page vitrine multilingue (FR/EN/IT via `?lang=`). Présente les trois tours, FAQ, formulaire de contact, liens de réservation Bokun. Inclut `sync_gyg_reviews.php` au chargement pour alimenter le bloc avis (cache 24h).

### dashboard.php — Dossiers de location
Interface opérateur pour la gestion physique des véhicules. Vues disponibles via `?view=` :
- `dashboard` — résumé : véhicules, contrats pré-remplis en attente d'arrivée, dossiers ouverts, dossiers récents
- `etat_avant` — saisie km + photos départ
- `etat_apres` — saisie km + photos retour + notes dommages + caution
- `dossier_detail` — fiche complète avant/après avec photos GCP + sélecteur changement de véhicule
- `dossiers_fermes` — historique paginé
- `login` — formulaire d'authentification

**Section "Pré-remplis"** — affiche les contrats avec signature mais sans dossier ouvert. Permet à l'opérateur de choisir un véhicule et d'ouvrir le dossier à l'arrivée physique du client (`action=open_dossier` → crée le dossier et redirige vers `dossier_detail`).

**Action `change_vehicule`** — réassigne le véhicule d'un dossier ouvert depuis la fiche detail (AJAX, mise à jour simultanée de `nomadrive_dossiers` et `nomadrive_contrats`).

**Clôture dossier (`save_etat_apres`)** — si une caution `authorized` existe, la clôture déclenche automatiquement la capture Stripe (partielle si `caution_retenu < montant autorisé`) ou l'annulation (si `caution_liberee`). Email de clôture bilingue EN/FR envoyé au client (template dark-header, récap km, statut caution).

### manage.php — Gestion Bokun
Back-office complet, accès restreint. Fonctions principales :
- **Sync Bokun** — import résas sur plage de dates
- **Audit ressources** — vérifie par résa le nombre de voitures assignées dans le pool, propose correction
- **Vérifier le stock** — tableau par date/horaire (10h puis 14h) avec statut OK/Ajusté/Manque
- **Vérifier annulations** — libère les closeouts des résas annulées
- **Planning** (`?view=planning`) — affectation round-robin des véhicules clients aux groupes du jour
- **Flotte** — gestion des véhicules (actif/indispo, guide)

Pool Bokun : ID `1018292`, 8 ressources (IDs 1029380–1029388 sauf 1029385). startTimeId par créneau : City 10h=4908401, City 14h=4933733, Riviera 10h=4940234, Riviera 14h=4928196.

### contrat.php — Contrat de location

Deux modes de fonctionnement :

**Mode opérateur (session auth)** — 5 étapes : Informations → Permis + empreinte → État avant + photos → Contrat → Signature.
Panel **"Départ du jour"** en tête de page : 3 tabs horaires, dropdown réservation → voiture pré-affectée, pré-remplissage en un clic.
À l'envoi : génération PDF, upload GCP, email client (template dark-header bilingue EN/FR, véhicule visible), création dossier en DB.

**Mode client — `link_mode` (`?cid=X&token=Y`)** — 4 étapes : Informations → Permis → Caution → Récap + Signature.
Déclenché depuis le lien pré-arrivée envoyé par `cron_caution.php`. Le token HMAC-SHA256 (24 chars, dérivé de `MANAGE_PASSWORD`) garantit que le lien ne peut accéder qu'au contrat correspondant.
- Pas d'auth opérateur requise
- Étapes "État des lieux" et "photos" remplacées par une étape Stripe (pré-autorisation caution)
- Le formulaire est pré-rempli (nom, prénom, email en lecture seule)
- Le nom du véhicule n'est jamais affiché (ni dans le récap, ni dans le PDF, ni dans l'email)
- L'envoi fait un UPDATE du contrat existant (pas d'INSERT) ; aucun dossier créé
- Retour Stripe (`?caution=ok`) → `$initial_step = 4` en JS, le récap s'affiche directement
- Email de confirmation : template dark-header bilingue EN/FR, sans véhicule

**Flux Stripe (link_mode)** :
- `?action=stripe_redirect` (GET) — crée la Checkout Session si nécessaire, `success_url = contrat.php?cid=X&token=Y&caution=ok`, `cancel_url = …&caution=cancel`. Réutilise une session existante si `checkout_url IS NOT NULL`.
- `save_permis` (POST AJAX) — upload permis vers GCP avant le redirect Stripe pour ne pas perdre les photos.

### stripe_caution.php — Pré-autorisation caution

Endpoint AJAX (POST) appelé depuis `dashboard.php` ou `manage.php`. Actions :
- `create` — crée une Checkout Session Stripe (`capture_method=manual`, `success_url` vers `contrat.php?caution=ok`). Si une caution `pending`/`authorized` existe déjà, la retourne sans créer de doublon.
- `send_email` — envoie l'email pré-arrivée au client (même template single-CTA que `cron_caution.php` : un seul bouton "Prepare my arrival" / "Préparer mon arrivée", encadré orange).
- `capture` — capture le PaymentIntent avec montant partiel possible.
- `cancel` — annule le PaymentIntent.
- `status` — retourne la dernière caution pour un contrat.
- `create_dossier` — crée un contrat + dossier manuellement (flux opérateur manage.php).

`webhook_stripe.php` reçoit `checkout.session.completed` → status `authorized`, `payment_intent.succeeded` → `captured`, `payment_intent.canceled` → `canceled`.

### tip.php + tip_api.php + tip_admin.php — Pourboires Stripe Connect

**Architecture :**
- Chaque guide a un compte **Stripe Express** (onboarding via `tip_api.php?action=onboard_guide`).
- Le paiement est créé sur le compte plateforme avec `transfer_data.destination` vers le compte du guide.
- Commission plateforme définie par `TIP_PLATFORM_FEE_PERCENT` dans `config.php`.

**`tip.php?g=slug`** — page publique responsive (pas d'auth). Stripe Payment Element avec détection automatique Apple Pay / Google Pay. Boutons 2 €, 5 €, 10 €, 20 € + montant libre (1–200 €).

**`tip_admin.php`** — gestion des guides (ajout, onboarding, activation), historique des pourboires et commissions.

**`tip_webhook.php`** — webhook Connect, écoute `payment_intent.succeeded` et `payment_intent.payment_failed`.

### tablette.php — Interface véhicule
Accès par `?key=<licence_key>` (16 hex unique par véhicule). Sert de GPS guidé embarqué avec carte Leaflet, statut GPS temps réel, et panneau musique Spotify (recherche via API Spotify avec cache token fichier). Conçu pour fonctionner en plein écran sur tablette en paysage.

### settings.php — Paramètres opérationnels

Panel admin pour modifier les constantes opérationnelles sans toucher au code.

**Niveaux d'accès :**
- **Opérateur** (`ndIsAuth`) : peut consulter les paramètres en lecture seule.
- **Super-admin MADI** : peut modifier. Accès via token HMAC signé, généré depuis `madi.mt/nd_settings.php` (WebAuthn obligatoire). Token valable 5 min, stocké en session ensuite.

**Flow super-admin :**
1. Se connecter sur `madi.mt` via WebAuthn
2. Aller sur `madi.mt/nd_settings.php`
3. Redirect automatique vers `nomadrive.fr/settings.php` avec token signé
4. Session super-admin ouverte pour la durée de la navigation

**Secret partagé :** `nd_settings_secret` dans `nomadrive_settings` — généré automatiquement par `SQL/settings.sql` (SHA256 aléatoire). Utilisé par les deux serveurs via le `$db1` MySQL partagé.

### nomadrive_auth.php
Authentification partagée entre `dashboard.php` et `contrat.php`. Session PHP + cookie remember-me (sliding expiry 30 jours, token 64 hex en DB). Mot de passe stocké hashé sha256×2 dans `nomadrive_settings`.

---

## Automatisations

Voir [AUTOMATISATIONS.md](AUTOMATISATIONS.md) pour le détail complet.

Résumé :

| Script | Fréquence | Ce qu'il fait |
|---|---|---|
| `cron_reviews.php` | Toutes les 15 min | Sync Bokun J-7→J+90 + email avis 1h après tour + relance 24h |
| `cron_caution.php` | Toutes les heures | 1) Si `CRON_AUTO_PREREGISTER` : crée les contrats manquants depuis résas Bokun J/J+1/J+2. 2) Si `CRON_CAUTION_ACTIVE` : envoie l'email pré-arrivée (J-1 16h pour 10h, J-1 20h pour 14h, J 08h pour 18h). |
| `cron_closeouts.php` | Toutes les 15 min | Blocage voitures overcapacity + libération si annulation |
| `webhook_sarbacane.php` | Temps réel (push) | Réception statuts email (delivered, bounce, open…) |
| `sync_gyg_reviews.php` | À chaque chargement de index.php (cache 24h) | Scrape avis GYG via JSON-LD |
| `push_reviews.php` | Manuel (script Mac) | Injection d'avis depuis sources externes |

---

## Sécurité — chiffrement des clés

Plus de `.env` en clair. Toutes les clés sont chiffrées AES-128-CBC + HMAC-SHA256 et stockées dans `config.php` via `define('KEY', decrypt('...'))`. La fonction `decrypt()` est fournie par `fonctions.php` MADI.

`fonctions.php` doit être inclus **avant** `config.php` dans chaque fichier.

| Constante | Utilisée par |
|---|---|
| `BOKUN_ACCESS_KEY` / `BOKUN_SECRET_KEY` | manage.php, cron_closeouts.php, cron_reviews.php |
| `SMTP_USERNAME` / `SMTP_PASSWORD` | cron_reviews.php, contrat.php, dashboard.php (Sarbacane) |
| `SPOTIFY_CLIENT_ID` / `SPOTIFY_CLIENT_SECRET` | tablette.php |
| `REVIEWS_PUSH_TOKEN` | push_reviews.php |
| `MANAGE_PASSWORD` | manage.php, dashboard.php, contrat.php (dérivation token HMAC lien client) |
| `GOOGLE_MAPS_API_KEY` / `GOOGLE_PLACES_API_KEY` | tablette.php, contrat.php |
| `RECAPTCHA_SITE_KEY` / `RECAPTCHA_API_KEY` / `RECAPTCHA_PROJECT_ID` | index.php (formulaire contact) |
| `SOGE_MODE` / `SOGE_TEST_*` / `SOGE_PROD_*` / `SOGE_CAUTION_AMOUNT` | test_sogecommerce.php — en attente activation API REST Sogecommerce |
| `STRIPE_TEST_*` / `STRIPE_LIVE_*` | stripe_caution.php, webhook_stripe.php, contrat.php — clés chiffrées, restent dans config.php |
| `STRIPE_LIVE_WEBHOOK_SECRET` / `STRIPE_TEST_WEBHOOK_SECRET` | webhook_stripe.php (selon STRIPE_MODE) |
| `STRIPE_TIP_WEBHOOK_SECRET` | tip_webhook.php (webhook Connect) |
| `TIP_PLATFORM_FEE_PERCENT` | tip_api.php — commission plateforme (défaut : 0.10 = 10%, en clair) |

Les constantes opérationnelles suivantes sont chargées depuis `nomadrive_settings` (BDD) via `config.php` :

| Constante | Clé BDD | Défaut | Rôle |
|---|---|---|---|
| `STRIPE_MODE` | `stripe_mode` | `test` | Mode Stripe live/test |
| `MAIL_TEST_OVERRIDE` | `mail_test_override` | `null` | Redirection emails (vide = prod) |
| `CAUTION_MONTANT` | `caution_montant_eur` | `500` | Montant caution en € (affiché) |
| `STRIPE_CAUTION_AMOUNT` | `caution_montant_eur` | `50000` | Montant en centimes pour Stripe |
| `CRON_CAUTION_ACTIVE` | `cron_caution_active` | `0` | Active l'envoi email pré-arrivée |
| `CRON_AUTO_PREREGISTER` | `cron_auto_preregister` | `0` | Active le pré-enregistrement Bokun → contrats |

Modifiable via `settings.php` (super-admin MADI uniquement — voir section ci-dessous).

---

## Flux opérationnel quotidien

```
Bokun (réservation client)
    │
    ▼ cron toutes les 15 min (cron_reviews.php)
nomadrive_customers ──────────────────────────────────────────┐
    │                                                          │
    ▼ cron_closeouts.php                                       │
Pool Bokun ajusté (closeouts overcapacity)                    │
    │                                                          │
    ▼ J-1 ou J matin (cron_caution.php — quand activé)        │
Email pré-arrivée → client ouvre contrat.php?cid=X&token=Y   │
    │  Étapes : Infos → Permis → Caution Stripe → Signature   │
    │  Aucun dossier créé à ce stade                          │
    │                                                          │
    ▼ matin du jour J (manage.php)                             │
Planning véhicules affiché                                     │
    │                                                          │
    ▼ à l'arrivée du client (dashboard.php)                    │
Section "Pré-remplis" : opérateur choisit le véhicule        │
→ open_dossier → dossier créé, véhicule bloqué               │
→ photos état avant dans dossier_detail                       │
    │                                                          │
    │  (si client non pré-rempli : contrat.php mode opérateur) │
    │  5 étapes sur place, dossier créé à l'envoi             │
    │                                                          │
    ▼ pendant le tour                                          │
tablette.php (GPS + Spotify)                                   │
    │                                                          │
    ▼ retour (dashboard.php)                                   │
État après → dossier fermé                                     │
→ si caution autorisée : capture auto ou annulation           │
→ email de clôture bilingue EN/FR au client                   │
    │                                                          │
    ▼ J+1h (cron_reviews.php) ◄────────────────────────────────┘
Email avis → relance 24h
    │
    ▼ webhook_sarbacane.php
Statuts email tracés en DB
```
