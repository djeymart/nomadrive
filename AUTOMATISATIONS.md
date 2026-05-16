# NOMADRIVE — Automatisations & processus

---

## Crons (toutes les 15 min)

### cron_reviews.php
`*/15 * * * * php /var/www/html/nomadrive/cron_reviews.php >> /var/log/nomadrive_reviews.log 2>&1`

Fait trois choses en un seul passage :

**1. Sync Bokun**
- Importe toutes les résas de J-7 à J+90
- Produits : City (1194328), Riviera (1197812), Sunset (1197894)
- Upsert sur `nomadrive_customers` (bokun_booking_id = clé unique)

**2. Email avis — 1er envoi**
- Condition : `end_datetime + 1h <= NOW()` et `end_datetime + 48h >= NOW()` et `review_requested_at IS NULL`
- Bilingue FR/EN dans un seul email
- Bouton GYG ajouté si le canal contient "getyourguide"
- Trace dans `nomadrive_email_log` + `review_requested_at` mis à jour

**3. Relance avis**
- Condition : `review_requested_at + 24h <= NOW()` et `review_followup_at IS NULL`
- Même template, objet différent ("Un petit rappel…")
- Trace dans `nomadrive_email_log` + `review_followup_at` mis à jour

---

### cron_closeouts.php
`*/15 * * * * php /var/www/html/nomadrive/cron_closeouts.php >> /var/log/nomadrive_closeouts.log 2>&1`

**Phase 1 — Blocage overcapacity**
- Cible : résas CONFIRMED, futures, Tour City ou Riviera, sans `closeout_resource_ids`
- Logique : si pax > 2, ceil(pax/2) voitures nécessaires. Si Bokun en a assigné moins, closeout les voitures libres du pool
- Pool : ID 1018292, 8 véhicules (1029380–1029388 sauf 1029385)
- Sauvegarde les IDs bloqués dans `closeout_resource_ids` (VARCHAR 200)

**Phase 2 — Libération annulations**
- Cible : toutes les résas avec `closeout_resource_ids` non vide
- Vérifie le statut réel via `GET /booking.json/{id}`
- Si statut != CONFIRMED : DELETE closeout sur chaque resourceId + `closeout_resource_ids = NULL`
- Si toujours CONFIRMED : rien (closeout maintenu)

---

## Webhook (réactif)

### webhook_sarbacane.php
`POST https://nomadrive.fr/webhook_sarbacane.php` — configuré côté Sarbacane

- Reçoit les événements email en temps réel : `delivered`, `bounce`, `open`, `click`, `complaint`, `unsubscribe`
- Retrouve le log via `message_id` ou `email_to`
- Met à jour `nomadrive_email_log` : `status`, `webhook_event`, `webhook_at`, `raw_webhook`

---

## Avis — semi-automatique

### sync_gyg_reviews.php
- Appelé au chargement de `index.php` (pas un cron autonome)
- Cache 24h via `nomadrive_reviews_meta`
- Scrape la page GYG publique, extrait le JSON-LD schema.org
- Filtre : uniquement les avis 5 étoiles avec texte
- Upsert dans `nomadrive_reviews` (source = 'gyg')

### push_reviews.php
- Endpoint passif : `POST /push_reviews.php` avec header `X-Push-Token`
- Reçoit un JSON depuis un script externe (Mac) et insère dans `nomadrive_reviews`
- Utilisé pour pousser des avis depuis d'autres sources manuellement

---

## Actions manuelles (manage.php)

| Action | Déclencheur | Ce qu'elle fait |
|---|---|---|
| Sync Bokun | Bouton + plage de dates | Même upsert que le cron, sur la plage choisie |
| Audit ressources | Bouton | Vérifie par résa si le bon nombre de voitures est assigné dans le pool Bokun |
| Compenser pool | Bouton par résa | Closeout N voitures libres + sauvegarde `closeout_resource_ids` |
| Assigner voiture | Bouton par résa | POST assignment Bokun via `experienceBookingId` |
| Vérifier annulations | Bouton | Même logique que Phase 2 du cron, à la demande |
| Vérifier le stock | Bouton | GET assignments Bokun par date, tableau par horaire (10h/14h), statut par créneau |

---

## Ce qui est encore manuel

- Ouverture et clôture des contrats de location (contrat.php + dossiers)
- Affectation des véhicules clients aux groupes (planning proposé, pas persisté)
- Envoi des avis GYG depuis le Mac (push_reviews.php)
- Correction des anomalies d'assignation Bokun (audit → bouton fix)

---

## Pistes d'amélioration

-
-
-
