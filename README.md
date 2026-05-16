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
├── config.php               Charge le .env → constantes PHP (Bokun, SMTP, Spotify, tokens)
├── nomadrive_auth.php       Auth partagée : session + cookie remember-me 30 jours
│
├── index.php                Site public (FR / EN / IT)
├── cgv.php                  Conditions générales
├── legal.php                Mentions légales
├── faq.php                  FAQ standalone
├── qr.php                   Génération QR codes
│
├── dashboard.php            Dashboard opérateur — dossiers de location
├── manage.php               Back-office Bokun — résas, pool, planning, flotte
├── contrat.php              Formulaire contrat de location (5 étapes)
├── tablette.php             Interface embarquée dans les véhicules (GPS + Spotify)
│
├── cron_reviews.php         Cron 15 min — sync Bokun + emails avis
├── cron_closeouts.php       Cron 15 min — gestion pool (overcapacity + annulations)
├── webhook_sarbacane.php    Webhook passif — événements email Sarbacane
├── sync_gyg_reviews.php     Fonction de scrape GYG (appelée par index.php)
├── push_reviews.php         Endpoint passif — réception avis depuis script externe
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

Chaque fichier PHP commence par :
```php
require_once '/var/www/html/madi.mt/vendor/autoload.php';
require_once '/var/www/html/madi.mt/php/fonctions.php';
require_once '/var/www/html/madi.mt/php/config.php';
require_once __DIR__ . '/config.php'; // .env nomadrive
```

---

## Base de données

Toutes les tables sont préfixées `nomadrive_` dans la même DB que madi.mt.

| Table | Rôle |
|---|---|
| `nomadrive_customers` | Résas importées depuis Bokun. Clé : `bokun_booking_id`. Contient `closeout_resource_ids` pour la gestion du pool. |
| `nomadrive_vehicules` | Flotte propre (marque, modele, immatriculation, couleur, actif, guide) |
| `nomadrive_contrats` | Contrats signés : données client, véhicule, signature base64, URLs GCP |
| `nomadrive_dossiers` | Dossiers de location liés aux contrats : état avant/après (km, notes, photos GCP) |
| `nomadrive_email_log` | Trace de chaque email envoyé + statut webhook Sarbacane |
| `nomadrive_reviews` | Avis clients (source : google, gyg, manual) |
| `nomadrive_reviews_meta` | Note globale et total par source, avec timestamp de dernière sync |
| `nomadrive_settings` | Config clé/valeur dont `admin_password_hash` (sha256 doublé) |
| `nomadrive_auth_sessions` | Tokens remember-me (64 hex, expiry glissante 30 jours) |

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
- `dashboard` — résumé : véhicules, dossiers ouverts, dossiers récents
- `etat_avant` — saisie km + photos départ
- `etat_apres` — saisie km + photos retour + notes dommages
- `dossier_detail` — fiche complète avant/après avec photos GCP
- `dossiers_fermes` — historique paginé
- `login` — formulaire d'authentification

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
Formulaire mobile-first en 5 étapes (Informations → Permis → État avant → Contrat → Signature). En tête de page, panel **"Départ du jour"** :
- 3 tabs horaires auto-sélectionnés selon l'heure (avant 12h → 10h, avant 16h → 14h, sinon Soir)
- Dropdown réservation du jour → dropdown voiture pré-affectée (même algorithme que le planning)
- Pré-remplit le formulaire en un clic

À l'envoi : génération PDF via mPDF, upload GCP, envoi email Sarbacane SMTP avec PDF en pièce jointe, création dossier de location en DB.

### tablette.php — Interface véhicule
Accès par `?key=<licence_key>` (16 hex unique par véhicule). Sert de GPS guidé embarqué avec carte Leaflet, statut GPS temps réel, et panneau musique Spotify (recherche via API Spotify avec cache token fichier). Conçu pour fonctionner en plein écran sur tablette en paysage.

### nomadrive_auth.php
Authentification partagée entre `dashboard.php` et `contrat.php`. Session PHP + cookie remember-me (sliding expiry 30 jours, token 64 hex en DB). Mot de passe stocké hashé sha256×2 dans `nomadrive_settings`.

---

## Automatisations

Voir [AUTOMATISATIONS.md](AUTOMATISATIONS.md) pour le détail complet.

Résumé :

| Script | Fréquence | Ce qu'il fait |
|---|---|---|
| `cron_reviews.php` | Toutes les 15 min | Sync Bokun J-7→J+90 + email avis 1h après tour + relance 24h |
| `cron_closeouts.php` | Toutes les 15 min | Blocage voitures overcapacity + libération si annulation |
| `webhook_sarbacane.php` | Temps réel (push) | Réception statuts email (delivered, bounce, open…) |
| `sync_gyg_reviews.php` | À chaque chargement de index.php (cache 24h) | Scrape avis GYG via JSON-LD |
| `push_reviews.php` | Manuel (script Mac) | Injection d'avis depuis sources externes |

---

## Variables d'environnement (.env)

| Variable | Utilisée par |
|---|---|
| `BOKUN_ACCESS_KEY` / `BOKUN_SECRET_KEY` | manage.php, cron_closeouts.php, cron_reviews.php |
| `SMTP_USERNAME` / `SMTP_PASSWORD` | cron_reviews.php, contrat.php (Sarbacane SendKit) |
| `SPOTIFY_CLIENT_ID` / `SPOTIFY_CLIENT_SECRET` | tablette.php |
| `REVIEWS_PUSH_TOKEN` | push_reviews.php |

---

## Flux opérationnel quotidien

```
Bokun (réservation client)
    │
    ▼ cron toutes les 15 min
nomadrive_customers ──────────────────────────────────────────┐
    │                                                          │
    ▼ cron_closeouts.php                                       │
Pool Bokun ajusté (closeouts overcapacity)                    │
    │                                                          │
    ▼ matin (manage.php)                                       │
Planning véhicules affiché                                     │
    │                                                          │
    ▼ départ (contrat.php)                                     │
Contrat signé → PDF GCP → email client                        │
    │                                                          │
    ▼ pendant le tour                                          │
tablette.php (GPS + Spotify)                                   │
    │                                                          │
    ▼ retour (dashboard.php)                                   │
État après → dossier fermé                                     │
    │                                                          │
    ▼ J+1h (cron_reviews.php) ◄────────────────────────────────┘
Email avis → relance 24h
    │
    ▼ webhook_sarbacane.php
Statuts email tracés en DB
```
