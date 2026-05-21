# NOMADRIVE — Automatisations & processus

---

## Crons

### cron_reviews.php — toutes les 15 min
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

### cron_caution.php — toutes les heures (actuellement commenté)
```
# 0 * * * * php /var/www/html/nomadrive/cron_caution.php >> /var/log/nomadrive_caution.log 2>&1
```
A activer quand le flow link_mode (pré-complétion client) est ouvert aux clients.

Envoie l'email pré-arrivée à chaque client dont l'heure d'envoi tombe dans la fenêtre de la dernière heure écoulée.

**Fenêtre d'envoi par créneau :**
- Tours **10h** → envoi à **J-1 16h00** (18h avant le départ)
- Tours **14h** → envoi à **J-1 20h00** (18h avant le départ)
- Tours **18h** → envoi à **J 08h00** (10h avant le départ)

**Ce que fait chaque passage :**
1. SQL : sélectionne les contrats confirmés dont `tour_datetime - lead_hours` tombe dans `[NOW()-1h, NOW()]` et `email_sent_at IS NULL` dans `nomadrive_stripe_cautions`
2. Pour chaque contrat : insère une ligne de tracking dans `nomadrive_stripe_cautions` (`status=pending`, sans session Stripe — créée lazily dans `contrat.php` quand le client clique)
3. Construit le lien sécurisé `contrat.php?cid=X&token=Y` (HMAC-SHA256, 24 chars)
4. Envoie l'email pré-arrivée bilingue EN/FR via Sarbacane SMTP
5. Met à jour `email_sent_at` dans `nomadrive_stripe_cautions`

**Email pré-arrivée :**
- Style dark header / white card, template identique à tous les emails NOMADRIVE
- Un seul bouton CTA "Prepare my arrival" / "Préparer mon arrivée" → `contrat.php?cid=X&token=Y`
- Encadré orange : caution demandée sur place si non faite en ligne, cartes physiques uniquement, permis physique obligatoire
- Le nom du véhicule n'est jamais mentionné

---

### cron_closeouts.php — toutes les 15 min
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

## Templates email

Tous les emails NOMADRIVE partagent le même template dark-header / white card, bilingue EN/FR :
- Header `#0f172a` avec logo + "NOMADRIVE" + "NICE · CÔTE D'AZUR"
- Corps EN en haut, séparateur "— Version française ci-dessous —", corps FR en bas
- Footer avec mentions légales

| Email | Déclenché par | Contenu |
|---|---|---|
| Pré-arrivée | `cron_caution.php` ou `stripe_caution.php send_email` | Lien contrat (1 seul bouton CTA), encadré orange obligations |
| Confirmation contrat | `contrat.php` (après signature) | Réf. contrat, date, PDF en pièce jointe. Véhicule masqué en link_mode. |
| Clôture | `dashboard.php save_etat_apres` | Récap km + distance, statut caution (vert = libérée, orange = retenu) |
| Avis | `cron_reviews.php` | Liens GYG + Google selon canal Bokun |

---

## Actions manuelles (manage.php)

> **Note :** `BOKUN_API_ENABLED = false` dans manage.php — toutes les actions qui appellent l'API Bokun sont désactivées le temps des tests. Le stock check reste actif (lecture seule locale).

| Action | Déclencheur | Bokun API | Ce qu'elle fait |
|---|---|---|---|
| Vérifier le stock | **Auto au chargement** | Non | Calcule les places libres par date/créneau depuis `nomadrive_customers` |
| Sync Bokun | Bouton + plage de dates | Oui (désactivé) | Upsert résas sur la plage choisie |
| Audit ressources | Bouton | Oui (désactivé) | Vérifie par résa le nombre de voitures assignées dans le pool |
| Compenser pool | Bouton par résa | Oui (désactivé) | Closeout N voitures libres + sauvegarde `closeout_resource_ids` |
| Assigner voiture | Bouton par résa | Oui (désactivé) | POST assignment via `experienceBookingId` |
| Vérifier annulations | Bouton | Oui (désactivé) | Libère les closeouts des résas annulées |

---

## Flux arrivée client (dashboard.php)

Le dossier de location n'est créé qu'à l'arrivée physique du client — jamais lors de la pré-complétion du contrat en ligne.

**Client a pré-rempli (link_mode) :**
1. Section "Pré-remplis" du dashboard → l'opérateur voit le client avec statut caution + permis
2. L'opérateur choisit le véhicule dans le sélecteur et clique "Ouvrir"
3. `action=open_dossier` → `nomadrive_dossiers` créé, `vehicule_id` mis à jour sur le contrat
4. Redirection vers `dossier_detail` → photos état avant saisies

**Client sans pré-remplissage :**
1. Contrat créé sur place via `contrat.php` mode opérateur (5 étapes)
2. Dossier créé à l'envoi du formulaire

---

## Ce qui est encore manuel

- Contrats sur place si le client n'a pas pré-rempli son dossier en ligne (contrat.php mode opérateur)
- Vérification du permis et validation du véhicule au départ (dashboard.php)
- Changement de véhicule si nécessaire (dashboard.php → dossier_detail → sélecteur)
- Affectation des véhicules clients aux groupes du jour (planning manage.php, pas persisté)
- Envoi des avis GYG depuis le Mac (push_reviews.php)
- Correction des anomalies d'assignation Bokun (audit → bouton fix, Bokun API à réactiver)

---

## Pistes d'amélioration

- Réactiver `BOKUN_API_ENABLED` une fois les tests Bokun validés
- Passer `STRIPE_MODE` en `live` et activer `cron_caution.php` quand le flow link_mode est validé en production
