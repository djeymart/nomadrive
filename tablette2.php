<?php
$madiDir = '/var/www/html/madi.mt';
if (!is_dir($madiDir)) $madiDir = dirname(__DIR__);
require_once $madiDir . '/vendor/autoload.php';
require_once $madiDir . '/php/fonctions.php';
require_once $madiDir . '/php/config.php';
require_once __DIR__ . '/config.php';

// ─── Identification tablette/véhicule ────────────────────────────────────────
$tablette_key = preg_replace('/[^a-f0-9]/i', '', $_GET['key'] ?? '');
$tablette_vehicule_id = null;

// ─── Mode admin (test hors parcours) ─────────────────────────────────────────
// Accès : ?key=xxx&admin=1 — bypass sélection tour, GPS seul, pas de KML/stops
$admin_mode = !empty($_GET['admin']);

// ─── Handler Spotify (anciennement spotify_proxy.php) ──────────────────────
if (isset($_GET['spotify_q'])) {
    header('Content-Type: application/json; charset=utf-8');

    $query = trim($_GET['spotify_q']);
    if (strlen($query) < 2) {
        echo json_encode(['results' => []]);
        exit;
    }

    // Token avec cache fichier
    $cacheFile = sys_get_temp_dir() . '/nomadrive_spotify_token.json';
    $token = null;
    if (file_exists($cacheFile)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached && isset($cached['token']) && $cached['expires'] > time()) {
            $token = $cached['token'];
        }
    }
    if (!$token) {
        $ch = curl_init('https://accounts.spotify.com/api/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode(SPOTIFY_CLIENT_ID . ':' . SPOTIFY_CLIENT_SECRET),
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code === 200) {
            $data = json_decode($resp, true);
            if (isset($data['access_token'])) {
                $token = $data['access_token'];
                file_put_contents($cacheFile, json_encode([
                    'token' => $token,
                    'expires' => time() + $data['expires_in'] - 60,
                ]));
            }
        }
    }
    if (!$token) {
        http_response_code(500);
        echo json_encode(['error' => 'Token Spotify indisponible', 'results' => []]);
        exit;
    }

    $ch = curl_init('https://api.spotify.com/v1/search?' . http_build_query(['q' => $query, 'type' => 'track', 'limit' => 8, 'market' => 'FR']));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
        CURLOPT_TIMEOUT => 10,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) {
        http_response_code(502);
        echo json_encode(['error' => 'Erreur Spotify', 'results' => []]);
        exit;
    }

    $data = json_decode($resp, true);
    $results = [];
    foreach (($data['tracks']['items'] ?? []) as $track) {
        $image = '';
        if (!empty($track['album']['images']))
            $image = end($track['album']['images'])['url'] ?? $track['album']['images'][0]['url'] ?? '';
        $results[] = [
            'type' => 'track',
            'id' => $track['id'],
            'title' => $track['name'],
            'artist' => implode(', ', array_map(fn($a) => $a['name'], $track['artists'])),
            'image' => $image,
        ];
    }
    echo json_encode(['results' => $results]);
    exit;
}

// ─── Handler stops (chargement des arrêts depuis la BDD) ──────────────────
if (isset($_GET['stops_q'])) {
    header('Content-Type: application/json; charset=utf-8');
    $tourSlug = preg_replace('/[^a-z0-9_-]/', '', $_GET['stops_q'] ?? '');
    if (!$tourSlug) { echo json_encode(['stops' => []]); exit; }
    try {
        $stmt = $db1->prepare("SELECT * FROM nomadrive_tour_stops WHERE tour_slug = ? ORDER BY ordre ASC");
        $stmt->execute([$tourSlug]);
        $stops = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $apiKey = defined('GOOGLE_PLACES_API_KEY') ? GOOGLE_PLACES_API_KEY : '';
        foreach ($stops as &$stop) {
            // Enrichissement Google Places si place_id défini et image absente
            if ($apiKey && !empty($stop['google_place_id']) && empty($stop['image_url'])) {
                $url = 'https://maps.googleapis.com/maps/api/place/details/json?place_id='
                     . urlencode($stop['google_place_id'])
                     . '&fields=photos,rating&key=' . urlencode($apiKey);
                $ctx  = stream_context_create(['http' => ['timeout' => 4]]);
                $resp = @file_get_contents($url, false, $ctx);
                if ($resp) {
                    $pd  = json_decode($resp, true);
                    $ref = $pd['result']['photos'][0]['photo_reference'] ?? null;
                    if ($ref) {
                        $imgUrl = 'https://maps.googleapis.com/maps/api/place/photo?maxwidth=400&photo_reference='
                                . urlencode($ref) . '&key=' . urlencode($apiKey);
                        $db1->prepare("UPDATE nomadrive_tour_stops SET image_url=? WHERE id=?")->execute([$imgUrl, $stop['id']]);
                        $stop['image_url'] = $imgUrl;
                    }
                    if (isset($pd['result']['rating'])) $stop['google_rating'] = $pd['result']['rating'];
                }
            }
            if (!empty($stop['services']) && is_string($stop['services']))
                $stop['services'] = json_decode($stop['services'], true) ?: [];
        }
        echo json_encode(['stops' => $stops]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['stops' => [], 'error' => $e->getMessage()]);
    }
    exit;
}
// ─────────────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>NOMADRIVE — Votre parcours</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        html,
        body {
            width: 100%;
            height: 100%;
            overflow: hidden;
            font-family: 'Inter', -apple-system, sans-serif;
            background: #0a0c10;
            color: #fff;
        }

        /* ─── ÉCRAN DE SÉLECTION ─── */

        .select-screen {
            position: fixed;
            inset: 0;
            z-index: 2000;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: linear-gradient(160deg, #0a0c10 0%, #111827 40%, #0f172a 100%);
            transition: opacity 0.5s ease, transform 0.5s ease;
        }

        .select-screen.hidden {
            opacity: 0;
            transform: scale(1.05);
            pointer-events: none;
        }

        .select-screen::before {
            content: '';
            position: absolute;
            top: -40%;
            right: -20%;
            width: 80vw;
            height: 80vw;
            background: radial-gradient(circle, rgba(78, 205, 196, 0.08) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .select-screen::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -20%;
            width: 60vw;
            height: 60vw;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.06) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .select-content {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 32px;
            width: 100%;
            max-width: 720px;
        }

        .brand-logo {
            font-size: 32px;
            font-weight: 900;
            letter-spacing: 6px;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #fff 0%, #94a3b8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .brand-tagline {
            font-size: 13px;
            color: #64748b;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 48px;
            font-weight: 500;
        }

        /* Sélecteur de langue */
        .lang-selector {
            display: flex;
            gap: 8px;
            margin-bottom: 40px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 4px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .lang-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            background: transparent;
            color: #64748b;
            font-family: inherit;
            font-size: 14px;
            font-weight: 500;
            border-radius: 9px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .lang-btn .flag {
            font-size: 20px;
            line-height: 1;
        }

        .lang-btn.active {
            background: rgba(78, 205, 196, 0.15);
            color: #4ecdc4;
            box-shadow: 0 0 20px rgba(78, 205, 196, 0.1);
        }

        .lang-btn:hover:not(.active) {
            color: #94a3b8;
            background: rgba(255, 255, 255, 0.03);
        }

        .section-label {
            font-size: 12px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #4ecdc4;
            margin-bottom: 20px;
            font-weight: 600;
        }

        /* Cartes de tour */
        .tour-cards {
            display: flex;
            flex-direction: column;
            gap: 16px;
            width: 100%;
        }

        .tour-card {
            position: relative;
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 24px 28px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            overflow: hidden;
        }

        .tour-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(78, 205, 196, 0.08) 0%, transparent 60%);
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .tour-card:hover {
            border-color: rgba(78, 205, 196, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(78, 205, 196, 0.08);
        }

        .tour-card:hover::before {
            opacity: 1;
        }

        .tour-card:active {
            transform: translateY(0) scale(0.99);
        }

        .tour-icon {
            position: relative;
            z-index: 1;
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            flex-shrink: 0;
        }

        .tour-card:nth-child(1) .tour-icon {
            background: linear-gradient(135deg, rgba(78, 205, 196, 0.15) 0%, rgba(78, 205, 196, 0.05) 100%);
        }

        .tour-card:nth-child(2) .tour-icon {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.15) 0%, rgba(139, 92, 246, 0.05) 100%);
        }

        .tour-card:nth-child(3) .tour-icon {
            background: linear-gradient(135deg, rgba(251, 146, 60, 0.15) 0%, rgba(251, 146, 60, 0.05) 100%);
        }

        /* ─── PREMIUM BADGE ─── */

        .tour-card-premium {
            border-color: rgba(251, 191, 36, 0.25);
            background: linear-gradient(160deg, rgba(251, 191, 36, 0.04) 0%, rgba(255, 255, 255, 0.02) 100%);
        }

        .tour-card-premium:hover {
            border-color: rgba(251, 191, 36, 0.5);
            box-shadow: 0 8px 32px rgba(251, 191, 36, 0.1);
        }

        .tour-card-premium::before {
            background: linear-gradient(135deg, rgba(251, 191, 36, 0.1) 0%, transparent 60%);
        }

        .tour-card-premium:hover .tour-arrow {
            background: rgba(251, 191, 36, 0.15);
        }

        .tour-card-premium:hover .tour-arrow svg {
            color: #fbbf24;
        }

        .tour-premium-badge {
            position: absolute;
            top: 14px;
            right: 14px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
            color: #fbbf24;
            background: rgba(251, 191, 36, 0.12);
            border: 1px solid rgba(251, 191, 36, 0.3);
            border-radius: 6px;
            padding: 3px 8px;
            z-index: 2;
        }

        .tour-info {
            position: relative;
            z-index: 1;
            flex: 1;
        }

        .tour-name {
            font-size: 17px;
            font-weight: 700;
            margin-bottom: 4px;
            color: #f1f5f9;
        }

        .tour-desc {
            font-size: 13px;
            color: #64748b;
            line-height: 1.4;
        }

        .tour-meta {
            display: flex;
            gap: 12px;
            margin-top: 8px;
        }

        .tour-meta span {
            font-size: 11px;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 4px;
            font-weight: 500;
        }

        .tour-meta span svg {
            width: 13px;
            height: 13px;
            opacity: 0.6;
        }

        .tour-arrow {
            position: relative;
            z-index: 1;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }

        .tour-card:hover .tour-arrow {
            background: rgba(78, 205, 196, 0.15);
        }

        .tour-arrow svg {
            width: 18px;
            height: 18px;
            color: #475569;
            transition: all 0.3s ease;
        }

        .tour-card:hover .tour-arrow svg {
            color: #4ecdc4;
            transform: translateX(2px);
        }

        .select-footer {
            margin-top: 40px;
            text-align: center;
        }

        .select-footer p {
            font-size: 12px;
            color: #334155;
            line-height: 1.6;
        }

        .select-footer p strong {
            color: #475569;
        }

        /* ─── ÉCRAN DE LA CARTE ─── */

        .map-screen {
            position: fixed;
            inset: 0;
            z-index: 1000;
            display: none;
            flex-direction: column;
            background: #0a0c10;
        }

        .map-screen.active { display: flex; }

        /* Layout principal : itinéraire | carte+POI */
        .main-layout {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        /* ─── PANNEAU GAUCHE — ITINÉRAIRE (35%) ─── */
        .itinerary-panel {
            width: 35%;
            flex-shrink: 0;
            background: #0f172a;
            border-right: 1px solid rgba(255,255,255,0.06);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }

        /* ── Instruction GPS (haut panneau gauche) ── */
        .nav-instruction {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            background: #1a5c3a;
            flex-shrink: 0;
            border-bottom: 2px solid #22c55e;
        }

        .nav-arrow {
            width: 44px;
            height: 44px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.12);
            border-radius: 10px;
        }

        .nav-arrow svg { width: 28px; height: 28px; color: #fff; }

        .nav-instruction-text { flex: 1; min-width: 0; }

        .nav-next-name {
            font-size: 15px;
            font-weight: 800;
            color: #ffffff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.2;
        }

        .nav-next-dist {
            font-size: 13px;
            color: rgba(255,255,255,0.75);
            margin-top: 3px;
            font-weight: 500;
        }

        .nav-eta-badge {
            flex-shrink: 0;
            background: rgba(255,255,255,0.12);
            border-radius: 8px;
            padding: 5px 10px;
            font-size: 12px;
            font-weight: 700;
            color: #fff;
            text-align: center;
            line-height: 1.3;
        }

        /* ── Mini-carte Waze (centre panneau gauche) ── */
        .nav-map-wrap {
            flex: 1;
            position: relative;
            overflow: hidden;
        }

        #navMap { width: 100%; height: 100%; }

        /* ── Progression + liste compacte (bas panneau gauche) ── */
        .nav-bottom {
            flex-shrink: 0;
            background: #0f172a;
            border-top: 1px solid rgba(255,255,255,0.06);
        }

        .nav-progress-bar {
            padding: 8px 14px 4px;
        }

        .progress-track {
            height: 3px;
            background: rgba(255,255,255,0.08);
            border-radius: 2px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4ecdc4, #22c55e);
            border-radius: 2px;
            transition: width 0.6s ease;
        }

        .progress-meta {
            display: flex;
            justify-content: space-between;
            margin-top: 4px;
            font-size: 10px;
            color: #475569;
            font-weight: 500;
        }

        .svc-tag {
            font-size: 10px;
            padding: 1px 6px;
            border-radius: 4px;
            background: rgba(255,255,255,0.05);
            color: #64748b;
        }

        /* ─── PANNEAU DROIT ─── */
        .right-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .map-zone {
            flex: 1;
            position: relative;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        /* ─── PANNEAU POI BAS-DROIT (50%) ─── */
        .poi-panel {
            flex: 1;
            background: #0b1120;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Vue "aucun POI" — prochains arrêts */
        .upcoming-view {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 10px 14px;
            overflow: hidden;
        }

        .panel-section-title {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #334155;
            margin-bottom: 8px;
            flex-shrink: 0;
        }

        .upcoming-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
            overflow-y: auto;
            flex: 1;
            scrollbar-width: none;
        }

        .upcoming-list::-webkit-scrollbar { display: none; }

        .upcoming-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            background: rgba(255,255,255,0.03);
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.05);
        }

        .upcoming-order {
            width: 22px;
            height: 22px;
            border-radius: 7px;
            background: rgba(78,205,196,0.1);
            color: #4ecdc4;
            font-size: 11px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .upcoming-info { flex: 1; min-width: 0; }

        .upcoming-name {
            font-size: 12px;
            font-weight: 600;
            color: #cbd5e1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .upcoming-eta { font-size: 11px; color: #475569; margin-top: 2px; }

        .upcoming-svc { display: flex; gap: 4px; margin-left: auto; flex-shrink: 0; }
        .svc-icon { font-size: 13px; }

        /* Vue "POI à proximité" */
        .poi-active-card {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            animation: poiSlide 0.35s ease;
        }

        @keyframes poiSlide {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .poi-card-top {
            padding: 8px 14px 6px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }

        .poi-nearby-badge {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 3px 8px;
            border-radius: 6px;
            background: rgba(251,146,60,0.15);
            color: #fb923c;
            border: 1px solid rgba(251,146,60,0.3);
        }

        .poi-dist-text { font-size: 11px; color: #fb923c; font-weight: 600; margin-left: auto; }

        .poi-card-body {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        .poi-img {
            width: 90px;
            flex-shrink: 0;
            object-fit: cover;
        }

        .poi-img-placeholder {
            width: 90px;
            flex-shrink: 0;
            background: rgba(255,255,255,0.04);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }

        .poi-details {
            flex: 1;
            padding: 10px 12px;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }

        .poi-details::-webkit-scrollbar { display: none; }

        .poi-name {
            font-size: 15px;
            font-weight: 800;
            color: #f1f5f9;
            margin-bottom: 4px;
            line-height: 1.2;
        }

        .poi-desc {
            font-size: 12px;
            color: #64748b;
            line-height: 1.5;
            margin-bottom: 8px;
        }

        .poi-svc-list { display: flex; gap: 5px; flex-wrap: wrap; }

        /* Barre supérieure */
        .top-bar {
            height: 52px;
            background: linear-gradient(135deg, #111827 0%, #1e293b 100%);
            display: flex;
            align-items: center;
            padding: 0 16px;
            gap: 12px;
            flex-shrink: 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            z-index: 100;
        }

        .back-btn {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            color: #94a3b8;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(78, 205, 196, 0.3);
            color: #4ecdc4;
        }

        .back-btn svg {
            width: 18px;
            height: 18px;
        }

        .top-bar .bar-logo {
            font-size: 15px;
            font-weight: 800;
            letter-spacing: 4px;
            color: #f1f5f9;
            flex: 1;
            text-align: center;
        }

        .top-bar .bar-logo span {
            color: #4ecdc4;
        }

        .tour-badge {
            font-size: 11px;
            font-weight: 600;
            padding: 5px 12px;
            border-radius: 8px;
            background: rgba(78, 205, 196, 0.12);
            color: #4ecdc4;
            letter-spacing: 0.5px;
            flex-shrink: 0;
            white-space: nowrap;
        }

        #map {
            width: 100%;
            height: 100%;
        }

        /* Bouton recentrer GPS */
        .gps-recenter-btn {
            position: absolute;
            bottom: 24px;
            right: 12px;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            border: none;
            background: #1e293b;
            color: #94a3b8;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4);
            transition: all 0.2s ease;
            z-index: 10;
        }

        .gps-recenter-btn:hover {
            background: #334155;
            color: #4ecdc4;
        }

        .gps-recenter-btn.tracking {
            color: #4ecdc4;
            background: rgba(78, 205, 196, 0.15);
            border: 1px solid rgba(78, 205, 196, 0.3);
        }

        .gps-recenter-btn svg {
            width: 22px;
            height: 22px;
        }

        /* ─── PANNEAU MUSIQUE ─── */

        .music-topbar-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 10px;
            border: 1px solid rgba(29, 185, 84, 0.3);
            background: rgba(29, 185, 84, 0.12);
            color: #1DB954;
            font-family: inherit;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            flex-shrink: 0;
            letter-spacing: 0.5px;
        }

        .music-topbar-btn:hover {
            background: rgba(29, 185, 84, 0.2);
            border-color: rgba(29, 185, 84, 0.5);
        }

        .music-topbar-btn.active {
            background: #1DB954;
            color: #000;
            border-color: #1DB954;
        }

        .music-topbar-btn .btn-icon {
            font-size: 15px;
            line-height: 1;
        }

        /* Panneau musique */
        .music-panel {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 15;
            background: rgba(15, 23, 42, 0.97);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px 20px 0 0;
            transform: translateY(100%);
            transition: transform 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            max-height: 65vh;
        }

        .music-panel.open {
            transform: translateY(0);
        }

        .music-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px 12px;
            flex-shrink: 0;
        }

        .music-panel-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
            font-weight: 700;
            color: #f1f5f9;
        }

        .music-panel-title .spotify-icon {
            color: #1DB954;
            font-size: 18px;
        }

        .music-panel-close {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 18px;
        }

        .music-panel-close:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #f1f5f9;
        }

        /* Sélecteur de playlists */
        .playlist-selector {
            display: flex;
            gap: 8px;
            padding: 0 20px 14px;
            overflow-x: auto;
            flex-shrink: 0;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }

        .playlist-selector::-webkit-scrollbar {
            display: none;
        }

        .playlist-chip {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.04);
            color: #94a3b8;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .playlist-chip .chip-emoji {
            font-size: 14px;
        }

        .playlist-chip:hover {
            border-color: rgba(29, 185, 84, 0.3);
            color: #e2e8f0;
        }

        .playlist-chip.active {
            background: rgba(29, 185, 84, 0.15);
            border-color: rgba(29, 185, 84, 0.4);
            color: #1DB954;
        }

        /* Conteneur de l'embed Spotify */
        .spotify-embed-container {
            padding: 0 20px 16px;
            flex: 1;
            min-height: 0;
        }

        .spotify-embed-container iframe {
            width: 100%;
            height: 152px;
            border-radius: 12px;
            border: none;
        }

        /* Barre de recherche */
        .music-search-wrap {
            padding: 0 20px 12px;
            flex-shrink: 0;
        }

        .music-search {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .music-search:focus-within {
            border-color: rgba(29, 185, 84, 0.4);
            background: rgba(255, 255, 255, 0.08);
        }

        .music-search svg {
            width: 16px;
            height: 16px;
            color: #64748b;
            flex-shrink: 0;
        }

        .music-search input {
            flex: 1;
            border: none;
            background: transparent;
            color: #f1f5f9;
            font-family: inherit;
            font-size: 13px;
            outline: none;
        }

        .music-search input::placeholder {
            color: #475569;
        }

        .search-clear-btn {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: none;
            background: rgba(255, 255, 255, 0.1);
            color: #64748b;
            font-size: 11px;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .search-clear-btn.visible {
            display: flex;
        }

        /* Résultats de recherche */
        .search-results {
            padding: 0 20px;
            display: none;
            flex-direction: column;
            gap: 2px;
            max-height: 180px;
            overflow-y: auto;
            margin-bottom: 12px;
            -webkit-overflow-scrolling: touch;
        }

        .search-results.visible {
            display: flex;
        }

        .search-results::-webkit-scrollbar {
            width: 4px;
        }

        .search-results::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 4px;
        }

        .search-result-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 10px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .search-result-item:hover {
            background: rgba(255, 255, 255, 0.06);
        }

        .search-result-item:active {
            background: rgba(29, 185, 84, 0.1);
        }

        .result-thumb {
            width: 40px;
            height: 40px;
            border-radius: 6px;
            object-fit: cover;
            flex-shrink: 0;
            background: rgba(255, 255, 255, 0.05);
        }

        .result-info {
            flex: 1;
            min-width: 0;
        }

        .result-title {
            font-size: 13px;
            font-weight: 600;
            color: #f1f5f9;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .result-artist {
            font-size: 11px;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .result-play-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #1DB954;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: #000;
            font-size: 12px;
        }

        .search-loading {
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            color: #64748b;
            font-size: 12px;
            gap: 8px;
        }

        .search-loading.visible {
            display: flex;
        }

        .search-loading .mini-spinner {
            width: 14px;
            height: 14px;
            border: 2px solid rgba(29, 185, 84, 0.2);
            border-top-color: #1DB954;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        /* Indicateur "En lecture" sur la barre du haut */
        .now-playing {
            display: none;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: #1DB954;
            font-weight: 500;
        }

        .now-playing.visible {
            display: flex;
        }

        .now-playing-bars {
            display: flex;
            align-items: flex-end;
            gap: 2px;
            height: 14px;
        }

        .now-playing-bars span {
            display: block;
            width: 3px;
            background: #1DB954;
            border-radius: 1px;
            animation: musicBar 0.8s ease infinite alternate;
        }

        .now-playing-bars span:nth-child(1) {
            height: 4px;
            animation-delay: 0s;
        }

        .now-playing-bars span:nth-child(2) {
            height: 8px;
            animation-delay: 0.2s;
        }

        .now-playing-bars span:nth-child(3) {
            height: 5px;
            animation-delay: 0.4s;
        }

        .now-playing-bars span:nth-child(4) {
            height: 10px;
            animation-delay: 0.1s;
        }

        @keyframes musicBar {
            from {
                height: 3px;
            }

            to {
                height: 14px;
            }
        }

        /* GPS status indicator */
        .gps-status {
            position: absolute;
            top: 12px;
            left: 50%;
            transform: translateX(-50%);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.5px;
            z-index: 10;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            pointer-events: none;
        }

        .gps-status.searching {
            background: rgba(251, 191, 36, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(251, 191, 36, 0.2);
        }

        .gps-status.connected {
            background: rgba(34, 197, 94, 0.15);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.2);
            opacity: 0;
            transition: opacity 1s ease 3s;
        }

        .gps-status.error {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .gps-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
            animation: gpsPulse 1.5s ease infinite;
        }

        @keyframes gpsPulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.3;
            }
        }

        /* Écran de chargement */
        .loading-overlay {
            position: absolute;
            inset: 0;
            background: #0a0c10;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 200;
            transition: opacity 0.6s ease;
        }

        .loading-overlay.hidden {
            opacity: 0;
            pointer-events: none;
        }

        .loader {
            width: 44px;
            height: 44px;
            border: 3px solid rgba(78, 205, 196, 0.15);
            border-top-color: #4ecdc4;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        .loading-overlay p {
            color: #475569;
            margin-top: 16px;
            font-size: 13px;
            font-weight: 500;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* ─── RESPONSIVE ─── */

        @media (min-width: 600px) {
            .tour-cards {
                flex-direction: row;
                flex-wrap: wrap;
            }

            .tour-card {
                flex: 1;
                min-width: 280px;
                flex-direction: column;
                text-align: center;
                padding: 32px 24px;
            }

            .tour-info {
                text-align: center;
            }

            .tour-meta {
                justify-content: center;
            }

            .tour-arrow {
                display: none;
            }
        }

        /* ─── TABLETTES ANDROID (ratio 16:9 / 16:10, plus hautes qu'un iPad) ─── */

        /* Portrait sur grand écran : réduire les espacements verticaux */
        @media (min-height: 900px) and (orientation: portrait) {
            .brand-tagline {
                margin-bottom: 32px;
            }

            .lang-selector {
                margin-bottom: 28px;
            }

            .select-footer {
                margin-top: 28px;
            }

            .tour-card {
                padding: 20px 24px;
            }
        }

        /* Paysage sur tablette (hauteur limitée) : mode compact */
        @media (max-height: 700px) and (orientation: landscape) {
            .select-content {
                padding: 16px 32px;
            }

            .brand-logo {
                font-size: 24px;
                margin-bottom: 4px;
            }

            .brand-tagline {
                font-size: 11px;
                margin-bottom: 16px;
            }

            .lang-selector {
                margin-bottom: 16px;
                padding: 3px;
            }

            .lang-btn {
                padding: 7px 14px;
                font-size: 13px;
            }

            .section-label {
                margin-bottom: 12px;
            }

            .tour-cards {
                flex-direction: row;
                flex-wrap: wrap;
                gap: 10px;
            }

            .tour-card {
                flex: 1;
                min-width: 240px;
                flex-direction: column;
                text-align: center;
                padding: 14px 16px;
            }

            .tour-info {
                text-align: center;
            }

            .tour-meta {
                justify-content: center;
            }

            .tour-arrow {
                display: none;
            }

            .select-footer {
                margin-top: 16px;
            }

            .tour-icon {
                width: 44px;
                height: 44px;
                font-size: 20px;
                margin-bottom: 8px;
            }
        }

        /* Animation d'entrée */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .select-content>* {
            animation: fadeInUp 0.6s ease backwards;
        }

        .select-content> :nth-child(1) {
            animation-delay: 0.1s;
        }

        .select-content> :nth-child(2) {
            animation-delay: 0.15s;
        }

        .select-content> :nth-child(3) {
            animation-delay: 0.2s;
        }

        .select-content> :nth-child(4) {
            animation-delay: 0.3s;
        }

        .select-content> :nth-child(5) {
            animation-delay: 0.4s;
        }

        .select-content> :nth-child(6) {
            animation-delay: 0.5s;
        }
    </style>
</head>

<body>

    <!-- ═══ ÉCRAN DE SÉLECTION ═══ -->
    <div class="select-screen" id="selectScreen">
        <div class="select-content">

            <div class="brand-logo">NOMADRIVE</div>
            <div class="brand-tagline" data-i18n="tagline">Explorez Nice en liberté</div>

            <div class="lang-selector">
                <button class="lang-btn active" onclick="setLang('fr')" id="lang-fr">
                    <span class="flag">🇫🇷</span> Français
                </button>
                <button class="lang-btn" onclick="setLang('en')" id="lang-en">
                    <span class="flag">🇬🇧</span> English
                </button>
                <button class="lang-btn" onclick="setLang('it')" id="lang-it">
                    <span class="flag">🇮🇹</span> Italiano
                </button>
            </div>

            <div class="section-label" data-i18n="chooseRoute">Choisissez votre parcours</div>

            <div class="tour-cards">

                <div class="tour-card" onclick="startTour('tour1')">
                    <div class="tour-icon">🏙️</div>
                    <div class="tour-info">
                        <div class="tour-name" data-i18n="tour1_name">🏙️ City</div>
                        <div class="tour-desc" data-i18n="tour1_desc">Promenade des Anglais · Negresco · Cimiez · Château de Nice</div>
                        <div class="tour-meta">
                            <span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10" />
                                    <polyline points="12,6 12,12 16,14" />
                                </svg>
                                2h
                            </span>
                            <span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                    <circle cx="12" cy="10" r="3" />
                                </svg>
                                ~20 km
                            </span>
                        </div>
                    </div>
                    <div class="tour-arrow">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </div>
                </div>

                <div class="tour-card" onclick="startTour('tour2')">
                    <div class="tour-icon">🌊</div>
                    <div class="tour-info">
                        <div class="tour-name" data-i18n="tour2_name">🌊 French Riviera</div>
                        <div class="tour-desc" data-i18n="tour2_desc">Villefranche-sur-Mer · Cap-Ferrat · Beaulieu-sur-Mer</div>
                        <div class="tour-meta">
                            <span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10" />
                                    <polyline points="12,6 12,12 16,14" />
                                </svg>
                                2h30
                            </span>
                            <span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                    <circle cx="12" cy="10" r="3" />
                                </svg>
                                ~35 km
                            </span>
                        </div>
                    </div>
                    <div class="tour-arrow">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </div>
                </div>

                <div class="tour-card tour-card-premium" onclick="startTour('tour3')">
                    <div class="tour-premium-badge" data-i18n="premium_badge">✦ GOLD</div>
                    <div class="tour-icon">🌅</div>
                    <div class="tour-info">
                        <div class="tour-name" data-i18n="tour3_name">Sunset · Gold</div>
                        <div class="tour-desc" data-i18n="tour3_desc">Place Masséna · Promenade des Anglais · Château de Nice · Belvédère</div>
                        <div class="tour-meta">
                            <span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10" />
                                    <polyline points="12,6 12,12 16,14" />
                                </svg>
                                ~2h30
                            </span>
                            <span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                    <circle cx="12" cy="10" r="3" />
                                </svg>
                                ~35 km
                            </span>
                        </div>
                    </div>
                    <div class="tour-arrow">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </div>
                </div>

            </div>

            <div class="select-footer">
                <p data-i18n="footer_info">
                    <strong>Naviguez librement</strong> sur la carte pour découvrir les lieux.<br>
                    Suivez le tracé de votre parcours et explorez à votre rythme.
                </p>
            </div>

        </div>
    </div>

    <!-- ═══ ÉCRAN DE LA CARTE ═══ -->
    <div class="map-screen" id="mapScreen">

        <div class="top-bar">
            <button class="back-btn" onclick="backToSelect()" title="Retour">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7" />
                </svg>
            </button>
            <div class="bar-logo">NOMA<span>DRIVE</span></div>
            <div class="now-playing" id="nowPlaying">
                <div class="now-playing-bars">
                    <span></span><span></span><span></span><span></span>
                </div>
            </div>
            <button class="music-topbar-btn" id="musicToggleBtn" onclick="toggleMusicPanel()">
                <span class="btn-icon">🎧</span> Musique
            </button>
            <div class="tour-badge" id="tourBadge">Tour 1</div>
            <?php if ($admin_mode): ?>
            <div style="font-size:10px;font-weight:700;letter-spacing:1px;padding:4px 10px;border-radius:6px;background:rgba(239,68,68,0.2);color:#ef4444;border:1px solid rgba(239,68,68,0.4);flex-shrink:0">ADMIN</div>
            <?php endif; ?>
        </div>

        <div class="main-layout">

            <!-- ── GAUCHE 35% : Itinéraire ── -->
            <div class="itinerary-panel" id="itineraryPanel">

                <div class="loading-overlay" id="loadingOverlay">
                    <div class="loader"></div>
                    <p>Chargement de votre parcours...</p>
                </div>

                <!-- Instruction prochaine étape -->
                <div class="nav-instruction" id="navInstruction">
                    <div class="nav-arrow" id="navArrow">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 19V5M5 12l7-7 7 7"/>
                        </svg>
                    </div>
                    <div class="nav-instruction-text">
                        <div class="nav-next-name" id="navNextName">Départ</div>
                        <div class="nav-next-dist" id="navNextDist">--</div>
                    </div>
                    <div class="nav-eta-badge" id="navEtaBadge" style="display:none"></div>
                </div>

                <!-- Mini-carte navigation (Waze-style) -->
                <div class="nav-map-wrap">
                    <div id="navMap"></div>
                </div>

                <!-- Progression + liste compacte -->
                <div class="nav-bottom">
                    <div class="nav-progress-bar">
                        <div class="progress-track">
                            <div class="progress-fill" id="progressFill" style="width:0%"></div>
                        </div>
                        <div class="progress-meta">
                            <span id="progressLabel">0 / 0 arrêts</span>
                            <span id="progressTime"></span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ── DROITE 65% : Carte + POI ── -->
            <div class="right-panel">

                <!-- Carte (50% hauteur) -->
                <div class="map-zone">
                    <div class="gps-status searching" id="gpsStatus">
                        <div class="gps-dot"></div>
                        <span>Recherche GPS...</span>
                    </div>
                    <div id="map"></div>
                    <button class="gps-recenter-btn" id="gpsRecenterBtn" onclick="recenterOnGps()" title="Ma position">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M12 2v3M12 19v3M2 12h3M19 12h3"/>
                            <circle cx="12" cy="12" r="8"/>
                        </svg>
                    </button>
                    <!-- Panneau musique (overlay sur la carte) -->
                    <div class="music-panel" id="musicPanel">
                        <div class="music-panel-header">
                            <div class="music-panel-title">
                                <span class="spotify-icon">🎧</span>
                                <span>Musique</span>
                            </div>
                            <button class="music-panel-close" onclick="toggleMusicPanel()">✕</button>
                        </div>
                        <div class="music-search-wrap">
                            <div class="music-search">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
                                </svg>
                                <input type="text" id="musicSearchInput" placeholder="Rechercher un titre, artiste..." autocomplete="off"/>
                                <button class="search-clear-btn" id="searchClearBtn" onclick="clearSearch()">✕</button>
                            </div>
                        </div>
                        <div class="search-loading" id="searchLoading">
                            <div class="mini-spinner"></div>
                            <span>Recherche...</span>
                        </div>
                        <div class="search-results" id="searchResults"></div>
                        <div class="playlist-selector" id="playlistSelector">
                            <button class="playlist-chip active" onclick="selectPlaylist('chill')"><span class="chip-emoji">🌊</span> Chill</button>
                            <button class="playlist-chip" onclick="selectPlaylist('summer')"><span class="chip-emoji">☀️</span> Summer</button>
                            <button class="playlist-chip" onclick="selectPlaylist('jazz')"><span class="chip-emoji">🎷</span> Jazz</button>
                            <button class="playlist-chip" onclick="selectPlaylist('pop')"><span class="chip-emoji">🎶</span> Pop</button>
                            <button class="playlist-chip" onclick="selectPlaylist('french')"><span class="chip-emoji">🇫🇷</span> French</button>
                            <button class="playlist-chip" onclick="selectPlaylist('sunset')"><span class="chip-emoji">🌅</span> Sunset</button>
                        </div>
                        <div class="spotify-embed-container">
                            <iframe id="spotifyEmbed" src="" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>
                        </div>
                    </div>
                </div>

                <!-- POI / prochains arrêts (50% hauteur) -->
                <div class="poi-panel" id="poiPanel">
                    <div class="upcoming-view" id="upcomingView">
                        <div class="panel-section-title">Prochains arrêts</div>
                        <div class="upcoming-list" id="upcomingList"></div>
                    </div>
                </div>

            </div>

        </div>

    </div>

    <script>
        // ═══════════════════════════════════════════
        //  CONFIGURATION
        // ═══════════════════════════════════════════

        const MAPS_API_KEY = '<?= defined("GOOGLE_MAPS_API_KEY") ? GOOGLE_MAPS_API_KEY : "" ?>';
        const ADMIN_MODE   = <?= $admin_mode ? 'true' : 'false' ?>;

        // KML servis directement depuis le serveur (un fichier par tour)
        const TOUR_KML_FILES = {
            tour1:              '/kml/tour1.kml',
            tour2:              '/kml/tour2.kml',
            tour3:              '/kml/tour_sunset.kml',
            tour_ajaccio_test:  '/kml/tour_ajaccio_test.kml'
        };

        // Centre par défaut (Nice)
        const DEFAULT_CENTER = { lat: 43.69807, lng: 7.29830 };
        const DEFAULT_ZOOM = 14;

        // ⬇️ Passe à true pour activer le panneau musique Spotify
        const ENABLE_MUSIC = false;

        // Fréquence de mise à jour GPS (millisecondes)
        const GPS_UPDATE_INTERVAL = 3000;

        const TOUR_LABELS = {
            tour1:             { fr: '🏙️ City', en: '🏙️ City', it: '🏙️ City' },
            tour2:             { fr: '🌊 French Riviera', en: '🌊 French Riviera', it: '🌊 French Riviera' },
            tour3:             { fr: '🌅 Sunset · Gold', en: '🌅 Sunset · Gold', it: '🌅 Sunset · Gold' },
            tour_ajaccio_test: { fr: 'Test Ajaccio', en: 'Ajaccio Test', it: 'Test Ajaccio' }
        };

        // ═══════════════════════════════════════════
        //  TRADUCTIONS
        // ═══════════════════════════════════════════

        const translations = {
            fr: {
                tagline: 'Explorez Nice en liberté',
                chooseRoute: 'Choisissez votre parcours',
                tour1_name: '🏙️ City',
                tour1_desc: 'Promenade des Anglais · Negresco · Cimiez · Château de Nice',
                tour2_name: '🌊 French Riviera',
                tour2_desc: 'Villefranche-sur-Mer · Cap-Ferrat · Beaulieu-sur-Mer',
                tour3_name: '🌅 Sunset · Gold',
                tour3_desc: 'Place Masséna · Promenade des Anglais · Beaulieu-sur-Mer · Château de Nice · Belvédère',
                premium_badge: '✦ GOLD',
                footer_info: '<strong>Naviguez librement</strong> sur la carte pour découvrir les lieux.<br>Suivez le tracé de votre parcours et explorez à votre rythme.',
                loading: 'Chargement de votre parcours...',
                gps_searching: 'Recherche GPS...',
                gps_connected: 'GPS connecté',
                gps_error: 'GPS indisponible',
                music_title: 'Musique',
                pl_chill: 'Chill', pl_summer: 'Summer', pl_jazz: 'Jazz',
                pl_pop: 'Pop Hits', pl_french: 'French', pl_sunset: 'Sunset',
                search_placeholder: 'Rechercher un titre, artiste...',
                searching: 'Recherche...'
            },
            en: {
                tagline: 'Explore Nice freely',
                chooseRoute: 'Choose your route',
                tour1_name: '🏙️ City',
                tour1_desc: 'Promenade des Anglais · Negresco · Cimiez · Château de Nice',
                tour2_name: '🌊 French Riviera',
                tour2_desc: 'Villefranche-sur-Mer · Cap-Ferrat · Beaulieu-sur-Mer',
                tour3_name: '🌅 Sunset · Gold',
                tour3_desc: 'Place Masséna · Promenade des Anglais · Beaulieu-sur-Mer · Château de Nice · Belvedere',
                premium_badge: '✦ GOLD',
                footer_info: '<strong>Navigate freely</strong> on the map to discover places.<br>Follow your route and explore at your own pace.',
                loading: 'Loading your route...',
                gps_searching: 'Searching GPS...',
                gps_connected: 'GPS connected',
                gps_error: 'GPS unavailable',
                music_title: 'Music',
                pl_chill: 'Chill', pl_summer: 'Summer', pl_jazz: 'Jazz',
                pl_pop: 'Pop Hits', pl_french: 'French', pl_sunset: 'Sunset',
                search_placeholder: 'Search for a song, artist...',
                searching: 'Searching...'
            },
            it: {
                tagline: 'Esplora Nizza in libertà',
                chooseRoute: 'Scegli il tuo percorso',
                tour1_name: '🏙️ City',
                tour1_desc: 'Promenade des Anglais · Negresco · Cimiez · Castello di Nizza',
                tour2_name: '🌊 French Riviera',
                tour2_desc: 'Villefranche-sur-Mer · Cap-Ferrat · Beaulieu-sur-Mer',
                tour3_name: '🌅 Sunset · Gold',
                tour3_desc: 'Place Masséna · Promenade des Anglais · Beaulieu-sur-Mer · Castello di Nizza · Belvedere',
                premium_badge: '✦ GOLD',
                footer_info: '<strong>Naviga liberamente</strong> sulla mappa per scoprire i luoghi.<br>Segui il tracciato del tuo percorso e esplora al tuo ritmo.',
                loading: 'Caricamento del percorso...',
                gps_searching: 'Ricerca GPS...',
                gps_connected: 'GPS connesso',
                gps_error: 'GPS non disponibile',
                music_title: 'Musica',
                pl_chill: 'Chill', pl_summer: 'Estate', pl_jazz: 'Jazz',
                pl_pop: 'Pop Hits', pl_french: 'Francese', pl_sunset: 'Tramonto',
                search_placeholder: 'Cerca un brano, artista...',
                searching: 'Ricerca...'
            }
        };

        // ═══════════════════════════════════════════
        //  ÉTAT
        // ═══════════════════════════════════════════

        let currentLang = 'fr';
        let currentTour = null;
        let map = null;
        let routeOverlays = [];
        let navRouteOverlays = [];
        let gpsMarker = null;
        let navMap = null;
        let navGpsMarker = null;
        let lastHeading = 0;
        let gpsAccuracyCircle = null;
        let gpsWatchId = null;
        let isFollowingGps = true;
        let mapInitialized = false;
        const cachedKmlDocs = {};   // cache par tourId
        let poiMarkers    = [];
        let openInfoWindow = null;

        // ── Stops / itinéraire ──
        let tourStops     = [];
        let passedStops   = new Set();
        let activeStopIdx = 0;
        let nearbyPoi     = null;   // stop actuellement dans le rayon POI
        let userPos       = null;   // {lat, lng} dernière position GPS

        // ── Icône voiture navMap (créée une seule fois après chargement API) ──
        const NAV_CAR_SVG = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 52" width="28" height="46">
            <rect x="8"  y="2"  width="16" height="6"  rx="3"   fill="#1655a2"/>
            <rect x="4"  y="6"  width="24" height="38" rx="8"   fill="#4285F4" stroke="#fff" stroke-width="1.2"/>
            <rect x="7"  y="9"  width="18" height="10" rx="3"   fill="rgba(210,235,255,0.75)"/>
            <rect x="8"  y="21" width="16" height="10" rx="2"   fill="#3578d4"/>
            <rect x="7"  y="33" width="18" height="8"  rx="3"   fill="rgba(210,235,255,0.55)"/>
            <rect x="7"  y="4"  width="6"  height="3"  rx="1.5" fill="#fffde8" opacity="0.95"/>
            <rect x="19" y="4"  width="6"  height="3"  rx="1.5" fill="#fffde8" opacity="0.95"/>
            <rect x="0"  y="9"  width="5"  height="10" rx="2.5" fill="#111"/>
            <rect x="27" y="9"  width="5"  height="10" rx="2.5" fill="#111"/>
            <rect x="0"  y="33" width="5"  height="10" rx="2.5" fill="#111"/>
            <rect x="27" y="33" width="5"  height="10" rx="2.5" fill="#111"/>
        </svg>`;
        let navCarIcon = null;
        function getNavCarIcon() {
            if (!navCarIcon) navCarIcon = {
                url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(NAV_CAR_SVG),
                scaledSize: new google.maps.Size(28, 46),
                anchor:     new google.maps.Point(14, 30),
            };
            return navCarIcon;
        }

        // ── Animation interpolation navMap ──
        let navAnimFrame   = null;
        let navAnimStart   = null;
        let navFromPos     = null;
        let navToPos       = null;
        let navFromHeading = 0;
        let navToHeading   = 0;

        function animateNavMap(ts) {
            if (!navAnimStart) navAnimStart = ts;
            const t = Math.min((ts - navAnimStart) / GPS_UPDATE_INTERVAL, 1);
            const e = 1 - Math.pow(1 - t, 3); // ease-out cubic

            const pos = {
                lat: navFromPos.lat + (navToPos.lat - navFromPos.lat) * e,
                lng: navFromPos.lng + (navToPos.lng - navFromPos.lng) * e,
            };

            // Interpolation heading chemin le plus court (évite le tour complet)
            let dh = navToHeading - navFromHeading;
            if (dh > 180) dh -= 360;
            if (dh < -180) dh += 360;
            const h = navFromHeading + dh * e;

            navMap.setCenter(pos);
            navMap.setHeading(h);
            if (navGpsMarker) navGpsMarker.setPosition(pos);

            navAnimFrame = t < 1 ? requestAnimationFrame(animateNavMap) : null;
        }

        // Couleurs par tour (pour les tracés)
        const TOUR_COLORS = {
            tour1:             '#4ecdc4',
            tour2:             '#8b5cf6',
            tour3:             '#fbbf24',
            tour_ajaccio_test: '#f97316'
        };

        // ═══════════════════════════════════════════
        //  LANGUE
        // ═══════════════════════════════════════════

        function setLang(lang) {
            currentLang = lang;
            document.querySelectorAll('.lang-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('lang-' + lang).classList.add('active');
            document.querySelectorAll('[data-i18n]').forEach(el => {
                const key = el.getAttribute('data-i18n');
                if (translations[lang] && translations[lang][key]) {
                    el.innerHTML = translations[lang][key];
                }
            });
        }

        // ═══════════════════════════════════════════
        //  GOOGLE MAPS
        // ═══════════════════════════════════════════

        // ═══════════════════════════════════════════
        //  HAVERSINE
        // ═══════════════════════════════════════════

        function haversine(lat1, lng1, lat2, lng2) {
            const R = 6371000;
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLng = (lng2 - lng1) * Math.PI / 180;
            const a = Math.sin(dLat/2)**2
                    + Math.cos(lat1 * Math.PI/180) * Math.cos(lat2 * Math.PI/180) * Math.sin(dLng/2)**2;
            return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        }

        // ═══════════════════════════════════════════
        //  CHARGEMENT DES STOPS
        // ═══════════════════════════════════════════

        async function loadTourStops(tourId) {
            tourStops   = [];
            passedStops = new Set();
            activeStopIdx = 0;
            nearbyPoi = null;
            try {
                const resp = await fetch(`?stops_q=${encodeURIComponent(tourId)}`);
                const data = await resp.json();
                tourStops = data.stops || [];
            } catch (e) {
                console.error('Erreur chargement stops:', e);
            }
            renderItinerary();
            renderPoiPanel();
            renderPoiMarkersOnMap();
        }

        // ═══════════════════════════════════════════
        //  RENDU ITINÉRAIRE GAUCHE
        // ═══════════════════════════════════════════

        function getStopName(stop) {
            return stop['nom_' + currentLang] || stop.nom_fr || stop.nom_en || '';
        }

        function getStopDesc(stop) {
            return stop['description_' + currentLang] || stop.description_fr || stop.description_en || '';
        }

        function formatEta(dureeMin) {
            if (!dureeMin && dureeMin !== 0) return '';
            if (dureeMin < 60) return `${dureeMin} min`;
            return `${Math.floor(dureeMin/60)}h${dureeMin%60 ? String(dureeMin%60).padStart(2,'0') : ''}`;
        }

        function serviceIcons(services) {
            if (!services || typeof services !== 'object') return '';
            const map = {
                toilettes:  ['🚻','Toilettes'],
                parking:    ['🅿️','Parking'],
                restaurant: ['🍽️','Restaurant'],
                cafe:       ['☕','Café'],
                plage:      ['🏖️','Plage'],
                vue:        ['🔭','Point de vue'],
                boutique:   ['🛍️','Boutique'],
            };
            return Object.entries(services)
                .filter(([, v]) => v)
                .map(([k]) => map[k] ? `<span class="svc-tag">${map[k][0]} ${map[k][1]}</span>` : '')
                .join('');
        }

        function renderItinerary() {
            if (!tourStops.length) return;

            const active = tourStops[activeStopIdx] || tourStops[0];
            const passed = passedStops.size;
            const total  = tourStops.filter(s => s.est_arret == 1).length;
            const pct    = total ? Math.round(passed / total * 100) : 0;
            document.getElementById('progressFill').style.width = pct + '%';
            document.getElementById('progressLabel').textContent = `${passed} / ${total} arrêts`;
            document.getElementById('progressTime').textContent  = active.duree_min ? formatEta(active.duree_min) + ' depuis le départ' : '';
        }

        // ═══════════════════════════════════════════
        //  MARQUEURS POI SUR LA CARTE PRINCIPALE
        // ═══════════════════════════════════════════

        function renderPoiMarkersOnMap() {
            poiMarkers.forEach(m => m.setMap(null));
            poiMarkers = [];
            if (!map || !tourStops.length) return;

            const tourColor = TOUR_COLORS[currentTour] || '#4ecdc4';

            tourStops.forEach(s => {
                const isPoi   = s.est_poi == 1;
                const isArret = s.est_arret == 1;
                if (!isPoi && !isArret) return;

                const pos  = { lat: parseFloat(s.lat), lng: parseFloat(s.lng) };
                const name = getStopName(s);
                const desc = getStopDesc(s);

                const icon = isPoi ? {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 11,
                    fillColor: tourColor,
                    fillOpacity: 0.95,
                    strokeColor: '#ffffff',
                    strokeWeight: 2.5,
                } : {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 7,
                    fillColor: '#22c55e',
                    fillOpacity: 0.9,
                    strokeColor: '#ffffff',
                    strokeWeight: 2,
                };

                const marker = new google.maps.Marker({ position: pos, map, title: name || String(s.ordre || s.id || ''), icon, zIndex: 10 });

                marker.addListener('click', () => {
                    if (openInfoWindow) openInfoWindow.close();
                    // Recalcul à la demande pour respecter la langue courante et avoir un fallback
                    const n = getStopName(s) || `Arrêt ${s.ordre ?? s.id ?? ''}`;
                    const d = getStopDesc(s);
                    const iw = new google.maps.InfoWindow({
                        content: `<div style="max-width:240px;padding:4px 2px;font-family:sans-serif">`
                               + `<b style="font-size:14px;color:#1a1a1a;display:block;margin-bottom:2px">${n}</b>`
                               + (d ? `<span style="font-size:12px;color:#555;line-height:1.4">${d}</span>` : '')
                               + `</div>`
                    });
                    iw.open(map, marker);
                    openInfoWindow = iw;
                });

                poiMarkers.push(marker);
            });
        }

        // ═══════════════════════════════════════════
        //  PANNEAU POI BAS-DROIT
        // ═══════════════════════════════════════════

        function renderPoiPanel() {
            const panel = document.getElementById('poiPanel');

            if (nearbyPoi) {
                const s    = nearbyPoi.stop;
                const dist = nearbyPoi.dist;
                const name = getStopName(s);
                const desc = getStopDesc(s);
                panel.innerHTML = `
                <div class="poi-active-card">
                    <div class="poi-card-top">
                        <span class="poi-nearby-badge">À proximité</span>
                        <span class="poi-dist-text">${Math.round(dist)} m</span>
                    </div>
                    <div class="poi-card-body">
                        ${s.image_url
                            ? `<img class="poi-img" src="${s.image_url}" alt="${name}" onerror="this.style.display='none'">`
                            : `<div class="poi-img-placeholder">📍</div>`}
                        <div class="poi-details">
                            <div class="poi-name">${name}</div>
                            ${desc ? `<div class="poi-desc">${desc}</div>` : ''}
                            <div class="poi-svc-list">${serviceIcons(s.services)}</div>
                        </div>
                    </div>
                </div>`;
            } else {
                const upcoming = tourStops
                    .filter(s => s.est_arret == 1 && !passedStops.has(s.id))
                    .slice(0, 4);
                panel.innerHTML = `
                <div class="upcoming-view">
                    <div class="panel-section-title">Prochains arrêts</div>
                    <div class="upcoming-list">
                        ${upcoming.length ? upcoming.map((s, i) => `
                        <div class="upcoming-item">
                            <div class="upcoming-order">${i + 1}</div>
                            <div class="upcoming-info">
                                <div class="upcoming-name">${getStopName(s)}</div>
                                ${s.duree_min != null ? `<div class="upcoming-eta">${formatEta(s.duree_min)}</div>` : ''}
                            </div>
                            <div class="upcoming-svc">${
                                s.services ? Object.entries(s.services).filter(([,v])=>v)
                                    .map(([k]) => ({toilettes:'🚻',parking:'🅿️',restaurant:'🍽️',cafe:'☕',plage:'🏖️',vue:'🔭',boutique:'🛍️'}[k]||''))
                                    .filter(Boolean).map(ic=>`<span class="svc-icon">${ic}</span>`).join('') : ''
                            }</div>
                        </div>`).join('') : '<div style="color:#475569;font-size:12px;padding:8px 0">Tous les arrêts sont passés.</div>'}
                    </div>
                </div>`;
            }
        }

        // ═══════════════════════════════════════════
        //  VÉRIFICATION PROXIMITÉ
        // ═══════════════════════════════════════════

        function checkProximity(pos) {
            userPos = pos;
            let newNearby = null;

            for (const stop of tourStops) {
                const dist = haversine(pos.lat, pos.lng, parseFloat(stop.lat), parseFloat(stop.lng));
                const rayon = stop.rayon_m || 200;

                if (dist <= rayon) {
                    // Marquer comme passé si c'est un arrêt
                    if (stop.est_arret == 1 && !passedStops.has(stop.id)) {
                        passedStops.add(stop.id);
                        // Avancer l'index actif au prochain stop non passé
                        const nextIdx = tourStops.findIndex((s, i) => i > activeStopIdx && !passedStops.has(s.id));
                        if (nextIdx !== -1) activeStopIdx = nextIdx;
                    }
                    // POI à afficher
                    if (stop.est_poi == 1 && (!newNearby || dist < newNearby.dist)) {
                        newNearby = { stop, dist };
                    }
                }
            }

            const changed = (newNearby?.stop?.id) !== (nearbyPoi?.stop?.id);
            nearbyPoi = newNearby;

            // Mettre à jour la distance au stop actif (si l'élément existe dans le DOM)
            if (tourStops[activeStopIdx]) {
                const s = tourStops[activeStopIdx];
                const d = haversine(pos.lat, pos.lng, parseFloat(s.lat), parseFloat(s.lng));
                const el = document.getElementById('currentStopDist');
                const valEl = document.getElementById('currentStopDistVal');
                if (el && valEl) {
                    valEl.textContent = d < 1000 ? Math.round(d) + ' m' : (d/1000).toFixed(1) + ' km';
                    el.style.display = 'inline-flex';
                }
            }

            renderItinerary();
            if (changed) renderPoiPanel();
        }

        // ── Bearing entre deux points ──
        function bearingTo(lat1, lng1, lat2, lng2) {
            const dLng = (lng2 - lng1) * Math.PI / 180;
            const φ1 = lat1 * Math.PI / 180, φ2 = lat2 * Math.PI / 180;
            const y = Math.sin(dLng) * Math.cos(φ2);
            const x = Math.cos(φ1) * Math.sin(φ2) - Math.sin(φ1) * Math.cos(φ2) * Math.cos(dLng);
            return ((Math.atan2(y, x) * 180 / Math.PI) + 360) % 360;
        }

        // ── Flèche directionnelle selon angle relatif ──
        function directionSvg(relAngle) {
            // relAngle : différence heading GPS vs bearing vers le prochain stop
            const a = ((relAngle % 360) + 360) % 360;
            if (a < 30 || a > 330) {
                // Tout droit
                return `<path d="M12 19V5M5 12l7-7 7 7"/>`;
            } else if (a >= 30 && a < 90) {
                // Légère droite
                return `<path d="M5 19L19 5M19 5H10M19 5v9"/>`;
            } else if (a >= 90 && a < 180) {
                // Droite franche
                return `<path d="M5 12h14M14 5l7 7-7 7"/>`;
            } else if (a >= 180 && a < 270) {
                // Gauche franche
                return `<path d="M19 12H5M10 5l-7 7 7 7"/>`;
            } else {
                // Légère gauche
                return `<path d="M19 19L5 5M5 5v9M5 5h9"/>`;
            }
        }

        // ── Mise à jour de l'instruction GPS ──
        function updateNavInstruction(pos, heading) {
            if (!tourStops.length) return;
            const next = tourStops.find((s, i) => i >= activeStopIdx && !passedStops.has(s.id));
            if (!next) return;

            const dist = haversine(pos.lat, pos.lng, parseFloat(next.lat), parseFloat(next.lng));
            const distStr = dist < 1000 ? Math.round(dist) + ' m' : (dist / 1000).toFixed(1) + ' km';

            document.getElementById('navNextName').textContent = getStopName(next);
            document.getElementById('navNextDist').textContent = 'dans ' + distStr;

            if (next.duree_min != null) {
                const badge = document.getElementById('navEtaBadge');
                badge.style.display = '';
                badge.innerHTML = formatEta(next.duree_min);
            }

            // Flèche directionnelle
            if (heading !== null && heading !== undefined && !isNaN(heading)) {
                const bear = bearingTo(pos.lat, pos.lng, parseFloat(next.lat), parseFloat(next.lng));
                const rel  = (bear - heading + 360) % 360;
                document.getElementById('navArrow').innerHTML =
                    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">${directionSvg(rel)}</svg>`;
            }
        }

        // ── Map ID vectoriel Google Maps (requis pour tilt en roadmap) ──
        // Créer dans Google Cloud Console > Google Maps Platform > Map management > Créer Map ID
        // Plateforme : JavaScript, Renderer : Vector — coller l'ID ici :
        const NAV_MAP_ID = 'c2a241e7a1ff94fd43ccc17a';

        // ── Création du navMap (Waze-style, vector map avec tilt natif) ──
        function createNavMap() {
            if (navMap) return;
            navMap = new google.maps.Map(document.getElementById('navMap'), {
                center: DEFAULT_CENTER,
                zoom: 19,
                mapId: NAV_MAP_ID,    // active le renderer vectoriel → tilt en roadmap
                disableDefaultUI: true,
                gestureHandling: 'none',
                rotateControl: false,
                tilt: 45,
                heading: 0,
            });
            // Forcer le tilt après chargement (Vector Maps peut nécessiter un rappel)
            google.maps.event.addListenerOnce(navMap, 'tilesloaded', () => navMap.setTilt(45));
        }

        // ── Mise à jour du marqueur GPS sur navMap (avec interpolation fluide) ──
        function updateNavMapPosition(pos, heading) {
            if (!navMap) return;

            const resolvedHeading = (heading !== null && heading !== undefined && !isNaN(heading))
                ? heading : lastHeading;

            // Annuler l'animation précédente
            if (navAnimFrame) { cancelAnimationFrame(navAnimFrame); navAnimFrame = null; }

            if (!navGpsMarker) {
                // Premier fix : placement direct sans animation
                navGpsMarker = new google.maps.Marker({
                    position: pos, map: navMap,
                    icon: getNavCarIcon(), zIndex: 999, title: ''
                });
                navMap.setCenter(pos);
                navMap.setHeading(resolvedHeading);
                lastHeading  = resolvedHeading;
                navFromPos   = pos;
                navFromHeading = resolvedHeading;
                return;
            }

            // Interpoler depuis la position actuelle du marqueur vers le nouveau fix
            const cur      = navGpsMarker.getPosition();
            navFromPos     = { lat: cur.lat(), lng: cur.lng() };
            navToPos       = pos;
            navFromHeading = lastHeading;
            navToHeading   = resolvedHeading;
            lastHeading    = resolvedHeading;
            navAnimStart   = null;
            navAnimFrame   = requestAnimationFrame(animateNavMap);
        }

        // ── Dessine la route sur le navMap (réutilise les polylines du KML) ──
        function drawRouteOnNavMap(tourColor) {
            navRouteOverlays.forEach(o => o.setMap(null));
            navRouteOverlays = [];
            routeOverlays.forEach(overlay => {
                if (overlay instanceof google.maps.Polyline) {
                    const poly = new google.maps.Polyline({
                        path: overlay.getPath().getArray(),
                        strokeColor: tourColor,
                        strokeOpacity: 0.9,
                        strokeWeight: 5,
                        map: navMap,
                        zIndex: 2
                    });
                    navRouteOverlays.push(poly);
                }
            });
        }

        function initMap() {
            mapInitialized = true;
            if (ADMIN_MODE) startAdminMode();
        }

        function startAdminMode() {
            // Lance le tour test Ajaccio (KML + stops BDD) sans passer par l'écran de sélection
            startTour('tour_ajaccio_test');
        }

        function createMap() {
            if (map) return;

            map = new google.maps.Map(document.getElementById('map'), {
                center: DEFAULT_CENTER,
                zoom: DEFAULT_ZOOM,
                mapTypeId: 'roadmap',
                disableDefaultUI: true,
                zoomControl: true,
                zoomControlOptions: {
                    position: google.maps.ControlPosition.RIGHT_CENTER
                },
                mapTypeControl: true,
                mapTypeControlOptions: {
                    style: google.maps.MapTypeControlStyle.DROPDOWN_MENU,
                    position: google.maps.ControlPosition.TOP_RIGHT,
                    mapTypeIds: ['roadmap', 'satellite']
                },
                fullscreenControl: false,
                streetViewControl: false,
                gestureHandling: 'greedy',
                styles: [
                    {
                        featureType: 'poi',
                        elementType: 'labels',
                        stylers: [{ visibility: 'simplified' }]
                    },
                    {
                        featureType: 'transit',
                        elementType: 'labels',
                        stylers: [{ visibility: 'off' }]
                    }
                ]
            });

            // Quand l'utilisateur touche/déplace la carte, arrêter le suivi automatique
            map.addListener('dragstart', () => {
                isFollowingGps = false;
                document.getElementById('gpsRecenterBtn').classList.remove('tracking');
            });
        }

        // ═══════════════════════════════════════════
        //  CHARGEMENT KML SÉLECTIF PAR TOUR
        // ═══════════════════════════════════════════

        // Supprime tous les overlays du tour précédent
        function clearRouteOverlays() {
            routeOverlays.forEach(overlay => overlay.setMap(null));
            routeOverlays = [];
            navRouteOverlays.forEach(o => o.setMap(null));
            navRouteOverlays = [];
            if (navGpsMarker) { navGpsMarker.setMap(null); navGpsMarker = null; }
            poiMarkers.forEach(m => m.setMap(null));
            poiMarkers = [];
            if (openInfoWindow) { openInfoWindow.close(); openInfoWindow = null; }
        }

        // Charge et parse le KML du tour sélectionné (fichier local par tour)
        async function loadTourFromKml(tourId) {
            clearRouteOverlays();

            try {
                if (!cachedKmlDocs[tourId]) {
                    const url = TOUR_KML_FILES[tourId];
                    const response = await fetch(url);
                    if (!response.ok) throw new Error(`KML HTTP ${response.status}`);
                    const kmlText = await response.text();
                    const parser = new DOMParser();
                    cachedKmlDocs[tourId] = parser.parseFromString(kmlText, 'text/xml');
                }

                const doc = cachedKmlDocs[tourId];
                const tourColor = TOUR_COLORS[tourId];
                const bounds = new google.maps.LatLngBounds();

                // Tracé (tous les LineString du fichier)
                doc.querySelectorAll('LineString').forEach(lineString => {
                    const coordsText = lineString.querySelector('coordinates')?.textContent?.trim();
                    if (!coordsText) return;
                    const path = coordsText.split(/\s+/).filter(c => c.length > 0).map(coord => {
                        const parts = coord.split(',');
                        const latlng = { lat: parseFloat(parts[1]), lng: parseFloat(parts[0]) };
                        bounds.extend(latlng);
                        return latlng;
                    });
                    const polyline = new google.maps.Polyline({
                        path,
                        strokeColor: tourColor,
                        strokeOpacity: 0.85,
                        strokeWeight: 5,
                        map,
                        zIndex: 2
                    });
                    routeOverlays.push(polyline);
                });

                // Points (Placemark > Point) s'il y en a
                doc.querySelectorAll('Placemark').forEach(placemark => {
                    const name = placemark.querySelector('name')?.textContent?.trim() || '';
                    const point = placemark.querySelector('Point');
                    if (!point) return;
                    const coordsText = point.querySelector('coordinates')?.textContent?.trim();
                    if (!coordsText) return;
                    const parts = coordsText.split(',');
                    const position = { lat: parseFloat(parts[1]), lng: parseFloat(parts[0]) };
                    bounds.extend(position);
                    const marker = new google.maps.Marker({
                        position,
                        map,
                        title: name,
                        icon: {
                            path: google.maps.SymbolPath.CIRCLE,
                            scale: 8,
                            fillColor: tourColor,
                            fillOpacity: 1,
                            strokeColor: '#ffffff',
                            strokeWeight: 2.5,
                            strokeOpacity: 1
                        },
                        zIndex: 5
                    });
                    if (name) {
                        const infoWindow = new google.maps.InfoWindow({ content: `<strong>${name}</strong>` });
                        marker.addListener('click', () => infoWindow.open(map, marker));
                    }
                    routeOverlays.push(marker);
                });

                if (!bounds.isEmpty()) {
                    map.fitBounds(bounds, { top: 60, bottom: 40, left: 20, right: 20 });
                }

                document.getElementById('loadingOverlay').classList.add('hidden');

            } catch (error) {
                console.error('Erreur chargement KML:', error);
                document.getElementById('loadingOverlay').classList.add('hidden');
            }
        }

        // ═══════════════════════════════════════════
        //  GÉOLOCALISATION (GPS)
        // ═══════════════════════════════════════════

        function startGpsTracking() {
            const gpsStatus = document.getElementById('gpsStatus');

            // Afficher "Recherche GPS..."
            gpsStatus.className = 'gps-status searching';
            gpsStatus.querySelector('span:last-child').textContent = translations[currentLang].gps_searching;

            // ── Fully Kiosk Browser : utiliser l'API native (plus fiable sur Android kiosk) ──
            if (typeof fully !== 'undefined' && typeof fully.getGeolocation === 'function') {
                window.onDeviceGeolocationChanged = function (lat, lng, alt, accuracy, bearing, speed, time) {
                    const pos     = { lat: parseFloat(lat), lng: parseFloat(lng) };
                    const heading = parseFloat(bearing);
                    gpsStatus.className = 'gps-status connected';
                    gpsStatus.querySelector('span:last-child').textContent = translations[currentLang].gps_connected;
                    updateGpsMarker(pos, parseFloat(accuracy) || 10);
                    updateNavMapPosition(pos, heading);
                    if (tourStops.length) { checkProximity(pos); updateNavInstruction(pos, heading); }
                    if (isFollowingGps && map) map.panTo(pos);
                };
                // provider : "gps" pour haute précision, minTime en ms, minDistance en mètres
                fully.getGeolocation('gps', GPS_UPDATE_INTERVAL, 0);
                return;
            }

            // ── Fallback : API Web Geolocation standard ──
            if (!navigator.geolocation) {
                gpsStatus.className = 'gps-status error';
                gpsStatus.querySelector('[data-i18n]').setAttribute('data-i18n', 'gps_error');
                gpsStatus.querySelector('span:last-child').textContent = translations[currentLang].gps_error;
                return;
            }

            gpsWatchId = navigator.geolocation.watchPosition(
                (position) => {
                    const pos     = { lat: position.coords.latitude, lng: position.coords.longitude };
                    const accuracy = position.coords.accuracy;
                    const heading  = position.coords.heading;

                    gpsStatus.className = 'gps-status connected';
                    gpsStatus.querySelector('span:last-child').textContent = translations[currentLang].gps_connected;

                    updateGpsMarker(pos, accuracy);
                    updateNavMapPosition(pos, heading);
                    if (tourStops.length) { checkProximity(pos); updateNavInstruction(pos, heading); }
                    if (isFollowingGps && map) map.panTo(pos);
                },
                (error) => {
                    console.warn('Erreur GPS:', error.message);
                    gpsStatus.className = 'gps-status error';
                    gpsStatus.querySelector('span:last-child').textContent = translations[currentLang].gps_error;
                },
                {
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: GPS_UPDATE_INTERVAL
                }
            );
        }

        function updateGpsMarker(position, accuracy) {
            if (!map) return;

            if (!gpsMarker) {
                // Créer le marqueur GPS personnalisé (point bleu style Google Maps)
                gpsMarker = new google.maps.Marker({
                    position: position,
                    map: map,
                    icon: {
                        path: google.maps.SymbolPath.CIRCLE,
                        scale: 10,
                        fillColor: '#4285F4',
                        fillOpacity: 1,
                        strokeColor: '#ffffff',
                        strokeWeight: 3,
                        strokeOpacity: 1
                    },
                    zIndex: 999,
                    title: 'Votre position'
                });

                // Cercle de précision GPS
                gpsAccuracyCircle = new google.maps.Circle({
                    center: position,
                    radius: accuracy,
                    map: map,
                    fillColor: '#4285F4',
                    fillOpacity: 0.1,
                    strokeColor: '#4285F4',
                    strokeOpacity: 0.3,
                    strokeWeight: 1,
                    zIndex: 1
                });

                // Activer le suivi par défaut
                isFollowingGps = true;
                document.getElementById('gpsRecenterBtn').classList.add('tracking');
            } else {
                // Animer le déplacement du marqueur
                gpsMarker.setPosition(position);
                gpsAccuracyCircle.setCenter(position);
                gpsAccuracyCircle.setRadius(accuracy);
            }
        }

        function stopGpsTracking() {
            // Fully Kiosk Browser
            if (typeof fully !== 'undefined' && typeof fully.stopGeolocation === 'function') {
                fully.stopGeolocation();
                window.onDeviceGeolocationChanged = null;
            }
            if (gpsWatchId !== null) {
                navigator.geolocation.clearWatch(gpsWatchId);
                gpsWatchId = null;
            }
            if (gpsMarker) {
                gpsMarker.setMap(null);
                gpsMarker = null;
            }
            if (gpsAccuracyCircle) {
                gpsAccuracyCircle.setMap(null);
                gpsAccuracyCircle = null;
            }
        }

        function recenterOnGps() {
            if (gpsMarker && map) {
                isFollowingGps = true;
                document.getElementById('gpsRecenterBtn').classList.add('tracking');
                map.panTo(gpsMarker.getPosition());
                map.setZoom(16);
            }
        }

        // ═══════════════════════════════════════════
        //  NAVIGATION
        // ═══════════════════════════════════════════

        function startTour(tourId) {
            currentTour = tourId;

            const selectScreen = document.getElementById('selectScreen');
            const mapScreen = document.getElementById('mapScreen');
            const tourBadge = document.getElementById('tourBadge');
            const loadingOverlay = document.getElementById('loadingOverlay');

            // Badge du tour
            tourBadge.textContent = TOUR_LABELS[tourId][currentLang];

            // Afficher le loading
            loadingOverlay.classList.remove('hidden');

            // Masquer la sélection
            selectScreen.classList.add('hidden');

            // Afficher la carte
            setTimeout(() => {
                mapScreen.classList.add('active');

                // Initialiser la carte Google Maps
                createMap();
                createNavMap();

                // Activer le suivi GPS dès le départ
                isFollowingGps = true;
                document.getElementById('gpsRecenterBtn').classList.add('tracking');

                // Charger le tour KML + les stops depuis la BDD (en parallèle)
                const tourColor = TOUR_COLORS[tourId];
                loadTourFromKml(tourId).then(() => drawRouteOnNavMap(tourColor));
                loadTourStops(tourId);

                // Démarrer le suivi GPS
                startGpsTracking();
            }, 300);
        }

        function backToSelect() {
            const selectScreen = document.getElementById('selectScreen');
            const mapScreen = document.getElementById('mapScreen');

            // Arrêter le GPS
            stopGpsTracking();

            // Cacher la carte
            mapScreen.classList.remove('active');

            // Supprimer les overlays du tour
            clearRouteOverlays();

            // Réafficher la sélection
            setTimeout(() => {
                selectScreen.classList.remove('hidden');
            }, 100);

            // Remettre le status GPS et le bouton de suivi
            const gpsStatus = document.getElementById('gpsStatus');
            gpsStatus.className = 'gps-status searching';
            isFollowingGps = true;
            document.getElementById('gpsRecenterBtn').classList.remove('tracking');
        }

        // ═══════════════════════════════════════════
        //  PANNEAU MUSIQUE (SPOTIFY)
        // ═══════════════════════════════════════════

        // IDs des playlists Spotify
        // ⚠️ REMPLACE ces IDs par tes propres playlists NOMADRIVE
        const PLAYLISTS = {
            chill: '37i9dQZF1DX4WYpdgoIcn6',  // Chill Hits
            summer: '37i9dQZF1DXdPec7aLTmlC',  // Summer Hits  
            jazz: '37i9dQZF1DX0SM0LYsmbMT',  // Jazz Vibes
            pop: '37i9dQZF1DXcBWIGoYBM5M',  // Today's Top Hits
            french: '37i9dQZF1DX1HCSbq0nkYb',  // French Touch
            sunset: '37i9dQZF1DX2UgsUIg75Vg'   // Sunset Chill
        };

        // Spotify API pour la recherche
        // Les identifiants sont stockés côté serveur dans spotify_proxy.php
        // → Aucun secret exposé dans le navigateur

        let musicPanelOpen = false;
        let currentPlaylist = null;
        let searchTimeout = null;

        function toggleMusicPanel() {
            const panel = document.getElementById('musicPanel');
            const btn = document.getElementById('musicToggleBtn');

            musicPanelOpen = !musicPanelOpen;
            panel.classList.toggle('open', musicPanelOpen);
            btn.classList.toggle('active', musicPanelOpen);

            // Charger la première playlist si pas encore fait
            if (musicPanelOpen && !currentPlaylist) {
                selectPlaylist('chill');
            }
        }

        function selectPlaylist(playlistKey) {
            const playlistId = PLAYLISTS[playlistKey];
            if (!playlistId) return;

            currentPlaylist = playlistKey;
            clearSearch();

            // Mettre à jour les chips actifs
            document.querySelectorAll('.playlist-chip').forEach(chip => chip.classList.remove('active'));
            const chips = document.querySelectorAll('.playlist-chip');
            chips.forEach(chip => {
                const chipKey = chip.getAttribute('onclick')?.match(/'(\w+)'/)?.[1];
                if (chipKey === playlistKey) chip.classList.add('active');
            });

            loadSpotifyEmbed('playlist', playlistId);
        }

        function loadSpotifyEmbed(type, id) {
            const iframe = document.getElementById('spotifyEmbed');
            iframe.src = `https://open.spotify.com/embed/${type}/${id}?utm_source=generator&theme=0`;
            document.getElementById('nowPlaying').classList.add('visible');
        }

        // ─── RECHERCHE SPOTIFY (via proxy serveur) ───

        async function searchSpotify(query) {
            try {
                const response = await fetch(`?spotify_q=${encodeURIComponent(query)}`);
                const data = await response.json();
                return data.results || [];
            } catch (e) {
                console.error('Erreur recherche:', e);
                return [];
            }
        }

        function onSearchInput(e) {
            const query = e.target.value.trim();
            const clearBtn = document.getElementById('searchClearBtn');
            clearBtn.classList.toggle('visible', query.length > 0);

            if (searchTimeout) clearTimeout(searchTimeout);

            if (query.length < 2) {
                document.getElementById('searchResults').classList.remove('visible');
                document.getElementById('searchLoading').classList.remove('visible');
                return;
            }

            document.getElementById('searchLoading').classList.add('visible');
            document.getElementById('searchResults').classList.remove('visible');

            // Debounce de 400ms
            searchTimeout = setTimeout(async () => {
                const results = await searchSpotify(query);
                displaySearchResults(results);
            }, 400);
        }

        function displaySearchResults(results) {
            const container = document.getElementById('searchResults');
            const loading = document.getElementById('searchLoading');
            loading.classList.remove('visible');

            if (results.length === 0) {
                container.innerHTML = '<div style="padding:12px;color:#64748b;font-size:12px;text-align:center">Aucun résultat</div>';
                container.classList.add('visible');
                return;
            }

            container.innerHTML = results.map(item => `
                <div class="search-result-item" onclick="playSearchResult('${item.type}', '${item.id}')">
                    <img class="result-thumb" src="${item.image}" alt="" onerror="this.style.display='none'" />
                    <div class="result-info">
                        <div class="result-title">${item.title}</div>
                        <div class="result-artist">${item.artist}</div>
                    </div>
                    <div class="result-play-icon">▶</div>
                </div>
            `).join('');

            container.classList.add('visible');
        }

        function playSearchResult(type, id) {
            // Désactiver les chips playlists
            document.querySelectorAll('.playlist-chip').forEach(chip => chip.classList.remove('active'));
            // Charger dans l'embed
            loadSpotifyEmbed(type, id);
            // Fermer les résultats
            document.getElementById('searchResults').classList.remove('visible');
        }

        function clearSearch() {
            const input = document.getElementById('musicSearchInput');
            if (input) input.value = '';
            document.getElementById('searchClearBtn')?.classList.remove('visible');
            document.getElementById('searchResults')?.classList.remove('visible');
            document.getElementById('searchLoading')?.classList.remove('visible');
        }

        // Initialiser la recherche
        document.addEventListener('DOMContentLoaded', () => {
            // Masquer la musique si désactivée
            if (!ENABLE_MUSIC) {
                document.getElementById('musicToggleBtn')?.style.setProperty('display', 'none');
                document.getElementById('musicPanel')?.style.setProperty('display', 'none');
            }

            const searchInput = document.getElementById('musicSearchInput');
            if (searchInput) {
                searchInput.addEventListener('input', onSearchInput);
            }
        });

        // ═══════════════════════════════════════════
        //  ANTI-ZOOM & ANTI-PULL-TO-REFRESH
        // ═══════════════════════════════════════════

        document.addEventListener('gesturestart', function (e) { e.preventDefault(); });
        document.body.addEventListener('touchmove', function (e) {
            if (e.target === document.body) e.preventDefault();
        }, { passive: false });
    </script>

    <!-- Charger l'API Google Maps -->
    <script>
        // Charger dynamiquement l'API Google Maps
        (function loadGoogleMapsApi() {
            const script = document.createElement('script');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${MAPS_API_KEY}&callback=initMap&language=${currentLang}`;
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);
        })();
    </script>

</body>

</html>