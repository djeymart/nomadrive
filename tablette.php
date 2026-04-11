<?php
require_once __DIR__ . '/config.php';

// ─── Handler Spotify (anciennement spotify_proxy.php) ──────────────────────
if (isset($_GET['spotify_q'])) {
    header('Content-Type: application/json; charset=utf-8');

    $query = trim($_GET['spotify_q']);
    if (strlen($query) < 2) { echo json_encode(['results' => []]); exit; }

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
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
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
                    'token'   => $token,
                    'expires' => time() + $data['expires_in'] - 60,
                ]));
            }
        }
    }
    if (!$token) { http_response_code(500); echo json_encode(['error' => 'Token Spotify indisponible', 'results' => []]); exit; }

    $ch = curl_init('https://api.spotify.com/v1/search?' . http_build_query(['q' => $query, 'type' => 'track', 'limit' => 8, 'market' => 'FR']));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) { http_response_code(502); echo json_encode(['error' => 'Erreur Spotify', 'results' => []]); exit; }

    $data    = json_decode($resp, true);
    $results = [];
    foreach (($data['tracks']['items'] ?? []) as $track) {
        $image = '';
        if (!empty($track['album']['images']))
            $image = end($track['album']['images'])['url'] ?? $track['album']['images'][0]['url'] ?? '';
        $results[] = [
            'type'   => 'track',
            'id'     => $track['id'],
            'title'  => $track['name'],
            'artist' => implode(', ', array_map(fn($a) => $a['name'], $track['artists'])),
            'image'  => $image,
        ];
    }
    echo json_encode(['results' => $results]);
    exit;
}
// ───────────────────────────────────────────────────────────────────────────
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

        .map-screen.active {
            display: flex;
        }

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

        /* Carte plein écran */
        .map-container {
            flex: 1;
            position: relative;
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

        .now-playing-bars span:nth-child(1) { height: 4px; animation-delay: 0s; }
        .now-playing-bars span:nth-child(2) { height: 8px; animation-delay: 0.2s; }
        .now-playing-bars span:nth-child(3) { height: 5px; animation-delay: 0.4s; }
        .now-playing-bars span:nth-child(4) { height: 10px; animation-delay: 0.1s; }

        @keyframes musicBar {
            from { height: 3px; }
            to { height: 14px; }
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
                    <div class="tour-icon">🏖️</div>
                    <div class="tour-info">
                        <div class="tour-name" data-i18n="tour1_name">Tour 1 — City</div>
                        <div class="tour-desc" data-i18n="tour1_desc">Promenade des Anglais, Port & Vieux Nice</div>
                        <div class="tour-meta">
                            <span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10" />
                                    <polyline points="12,6 12,12 16,14" />
                                </svg>
                                ~1h
                            </span>
                            <span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                    <circle cx="12" cy="10" r="3" />
                                </svg>
                                ~12 km
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
                    <div class="tour-icon">🏘️</div>
                    <div class="tour-info">
                        <div class="tour-name" data-i18n="tour2_name">Tour 2 — French Riviera</div>
                        <div class="tour-desc" data-i18n="tour2_desc">Mont Boron, Cap-Ferrat & Villefranche</div>
                        <div class="tour-meta">
                            <span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10" />
                                    <polyline points="12,6 12,12 16,14" />
                                </svg>
                                ~2h
                            </span>
                            <span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                    <circle cx="12" cy="10" r="3" />
                                </svg>
                                ~25 km
                            </span>
                        </div>
                    </div>
                    <div class="tour-arrow">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </div>
                </div>

                <div class="tour-card" onclick="startTour('tour3')">
                    <div class="tour-icon">🌅</div>
                    <div class="tour-info">
                        <div class="tour-name" data-i18n="tour3_name">Tour 3 — Sunset</div>
                        <div class="tour-desc" data-i18n="tour3_desc">Le parcours coucher de soleil sur la Riviera</div>
                        <div class="tour-meta">
                            <span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10" />
                                    <polyline points="12,6 12,12 16,14" />
                                </svg>
                                ~2h
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
        </div>

        <div class="map-container">
            <div class="loading-overlay" id="loadingOverlay">
                <div class="loader"></div>
                <p data-i18n="loading">Chargement de votre parcours...</p>
            </div>

            <!-- Statut GPS -->
            <div class="gps-status searching" id="gpsStatus">
                <div class="gps-dot"></div>
                <span data-i18n="gps_searching">Recherche GPS...</span>
            </div>

            <!-- Carte Google Maps -->
            <div id="map"></div>

            <!-- Bouton recentrer sur GPS -->
            <button class="gps-recenter-btn" id="gpsRecenterBtn" onclick="recenterOnGps()" title="Ma position">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3" />
                    <path d="M12 2v3M12 19v3M2 12h3M19 12h3" />
                    <circle cx="12" cy="12" r="8" />
                </svg>
            </button>

            <!-- Panneau musique -->
            <div class="music-panel" id="musicPanel">
                <div class="music-panel-header">
                    <div class="music-panel-title">
                        <span class="spotify-icon">🎧</span>
                        <span data-i18n="music_title">Musique</span>
                    </div>
                    <button class="music-panel-close" onclick="toggleMusicPanel()" title="Fermer">
                        ✕
                    </button>
                </div>

                <!-- Barre de recherche -->
                <div class="music-search-wrap">
                    <div class="music-search">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="m21 21-4.3-4.3"/>
                        </svg>
                        <input type="text" id="musicSearchInput" data-i18n-placeholder="search_placeholder"
                            placeholder="Rechercher un titre, artiste..." autocomplete="off" />
                        <button class="search-clear-btn" id="searchClearBtn" onclick="clearSearch()">✕</button>
                    </div>
                </div>

                <!-- Loading recherche -->
                <div class="search-loading" id="searchLoading">
                    <div class="mini-spinner"></div>
                    <span data-i18n="searching">Recherche...</span>
                </div>

                <!-- Résultats de recherche -->
                <div class="search-results" id="searchResults"></div>

                <!-- Chips playlists -->
                <div class="playlist-selector" id="playlistSelector">
                    <button class="playlist-chip active" onclick="selectPlaylist('chill')">
                        <span class="chip-emoji">🌊</span>
                        <span data-i18n="pl_chill">Chill</span>
                    </button>
                    <button class="playlist-chip" onclick="selectPlaylist('summer')">
                        <span class="chip-emoji">☀️</span>
                        <span data-i18n="pl_summer">Summer</span>
                    </button>
                    <button class="playlist-chip" onclick="selectPlaylist('jazz')">
                        <span class="chip-emoji">🎷</span>
                        <span data-i18n="pl_jazz">Jazz</span>
                    </button>
                    <button class="playlist-chip" onclick="selectPlaylist('pop')">
                        <span class="chip-emoji">🎶</span>
                        <span data-i18n="pl_pop">Pop Hits</span>
                    </button>
                    <button class="playlist-chip" onclick="selectPlaylist('french')">
                        <span class="chip-emoji">🇫🇷</span>
                        <span data-i18n="pl_french">French</span>
                    </button>
                    <button class="playlist-chip" onclick="selectPlaylist('sunset')">
                        <span class="chip-emoji">🌅</span>
                        <span data-i18n="pl_sunset">Sunset</span>
                    </button>
                </div>

                <div class="spotify-embed-container">
                    <iframe
                        id="spotifyEmbed"
                        src=""
                        allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
                        loading="lazy">
                    </iframe>
                </div>
            </div>

        </div>

    </div>

    <script>
        // ═══════════════════════════════════════════
        //  CONFIGURATION
        // ═══════════════════════════════════════════

        const MAPS_API_KEY = '<?= defined("GOOGLE_MAPS_API_KEY") ? GOOGLE_MAPS_API_KEY : "" ?>';

        const MYMAPS_ID = '1OcdMIwyllOV17Xs5e1EAM87yUmym5C4';

        // URL KML complète de la carte MyMaps
        const KML_URL = `https://www.google.com/maps/d/kml?forcekml=1&mid=${MYMAPS_ID}`;

        // Centre par défaut (Nice)
        const DEFAULT_CENTER = { lat: 43.69807, lng: 7.29830 };
        const DEFAULT_ZOOM = 14;

        // ⬇️ Passe à true pour activer le panneau musique Spotify
        const ENABLE_MUSIC = false;

        // Fréquence de mise à jour GPS (millisecondes)
        const GPS_UPDATE_INTERVAL = 3000;

        const TOUR_LABELS = {
            tour1: { fr: 'Tour 1 — City', en: 'Tour 1 — City', it: 'Tour 1 — City' },
            tour2: { fr: 'Tour 2 — French Riviera', en: 'Tour 2 — French Riviera', it: 'Tour 2 — French Riviera' },
            tour3: { fr: 'Tour 3 — Sunset', en: 'Tour 3 — Sunset', it: 'Tour 3 — Sunset' }
        };

        // ═══════════════════════════════════════════
        //  TRADUCTIONS
        // ═══════════════════════════════════════════

        const translations = {
            fr: {
                tagline: 'Explorez Nice en liberté',
                chooseRoute: 'Choisissez votre parcours',
                tour1_name: 'Tour 1 — City',
                tour1_desc: 'Promenade des Anglais, Port & Vieux Nice',
                tour2_name: 'Tour 2 — French Riviera',
                tour2_desc: 'Mont Boron, Cap-Ferrat & Villefranche',
                tour3_name: 'Tour 3 — Sunset',
                tour3_desc: 'Le parcours coucher de soleil sur la Riviera',
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
                tour1_name: 'Tour 1 — City',
                tour1_desc: 'Promenade des Anglais, Port & Old Nice',
                tour2_name: 'Tour 2 — French Riviera',
                tour2_desc: 'Mont Boron, Cap-Ferrat & Villefranche',
                tour3_name: 'Tour 3 — Sunset',
                tour3_desc: 'The sunset drive along the Riviera',
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
                tour1_name: 'Tour 1 — City',
                tour1_desc: 'Promenade des Anglais, Porto & Nizza Vecchia',
                tour2_name: 'Tour 2 — French Riviera',
                tour2_desc: 'Mont Boron, Cap-Ferrat & Villefranche',
                tour3_name: 'Tour 3 — Sunset',
                tour3_desc: 'Il percorso al tramonto sulla Riviera',
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
        let routeOverlays = []; // Polylines + Markers créés pour le tour actuel
        let gpsMarker = null;
        let gpsAccuracyCircle = null;
        let gpsWatchId = null;
        let isFollowingGps = true;
        let mapInitialized = false;
        let cachedKmlDoc = null; // Cache du KML parsé

        // Mapping nom de tour → nom du Folder dans le KML
        const TOUR_FOLDER_NAMES = {
            tour1: 'Tour1',
            tour2: 'Tour2',
            tour3: 'Tour3 - Sunset'
        };

        // Couleurs par tour (pour les tracés)
        const TOUR_COLORS = {
            tour1: '#1267FF',
            tour2: '#FF6712',
            tour3: '#FF4444'
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

        function initMap() {
            mapInitialized = true;
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
        }

        // Charge et parse le KML, puis affiche uniquement le tour sélectionné
        async function loadTourFromKml(tourId) {
            clearRouteOverlays();

            try {
                // Charger le KML (avec cache)
                if (!cachedKmlDoc) {
                    const response = await fetch(KML_URL);
                    const kmlText = await response.text();
                    const parser = new DOMParser();
                    cachedKmlDoc = parser.parseFromString(kmlText, 'text/xml');
                }

                const doc = cachedKmlDoc;
                const folders = doc.querySelectorAll('Document > Folder');
                const tourFolderName = TOUR_FOLDER_NAMES[tourId];
                const tourColor = TOUR_COLORS[tourId];
                const bounds = new google.maps.LatLngBounds();

                folders.forEach(folder => {
                    const folderName = folder.querySelector('name')?.textContent?.trim();

                    // Afficher seulement "Départ / Arrivée" et le tour sélectionné
                    if (folderName !== 'Départ / Arrivée' && folderName !== tourFolderName) {
                        return;
                    }

                    const placemarks = folder.querySelectorAll('Placemark');

                    placemarks.forEach(placemark => {
                        const name = placemark.querySelector('name')?.textContent?.trim() || '';

                        // ── Tracé (LineString) ──
                        const lineString = placemark.querySelector('LineString');
                        if (lineString) {
                            const coordsText = lineString.querySelector('coordinates')?.textContent?.trim();
                            if (coordsText) {
                                const path = coordsText.split(/\s+/).filter(c => c.length > 0).map(coord => {
                                    const parts = coord.split(',');
                                    const latlng = { lat: parseFloat(parts[1]), lng: parseFloat(parts[0]) };
                                    bounds.extend(latlng);
                                    return latlng;
                                });

                                const polyline = new google.maps.Polyline({
                                    path: path,
                                    strokeColor: tourColor,
                                    strokeOpacity: 0.85,
                                    strokeWeight: 5,
                                    map: map,
                                    zIndex: 2
                                });
                                routeOverlays.push(polyline);
                            }
                        }

                        // ── Point (Marker) ──
                        const point = placemark.querySelector('Point');
                        if (point) {
                            const coordsText = point.querySelector('coordinates')?.textContent?.trim();
                            if (coordsText) {
                                const parts = coordsText.split(',');
                                const position = { lat: parseFloat(parts[1]), lng: parseFloat(parts[0]) };
                                bounds.extend(position);

                                // Déterminer l'icône et la couleur selon le type de point
                                const isDepart = folderName === 'Départ / Arrivée';
                                const marker = new google.maps.Marker({
                                    position: position,
                                    map: map,
                                    title: name,
                                    icon: {
                                        path: google.maps.SymbolPath.CIRCLE,
                                        scale: isDepart ? 12 : 8,
                                        fillColor: isDepart ? '#0288D1' : tourColor,
                                        fillOpacity: 1,
                                        strokeColor: '#ffffff',
                                        strokeWeight: 2.5,
                                        strokeOpacity: 1
                                    },
                                    zIndex: isDepart ? 10 : 5,
                                    label: isDepart ? {
                                        text: '🏁',
                                        fontSize: '16px'
                                    } : null
                                });

                                // Info window au clic
                                if (name) {
                                    const infoWindow = new google.maps.InfoWindow({ content: `<strong>${name}</strong>` });
                                    marker.addListener('click', () => infoWindow.open(map, marker));
                                }

                                routeOverlays.push(marker);
                            }
                        }
                    });
                });

                // Ajuster la vue pour montrer tout le parcours
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

            if (!navigator.geolocation) {
                gpsStatus.className = 'gps-status error';
                gpsStatus.querySelector('[data-i18n]').setAttribute('data-i18n', 'gps_error');
                gpsStatus.querySelector('span:last-child').textContent = translations[currentLang].gps_error;
                return;
            }

            // Afficher "Recherche GPS..."
            gpsStatus.className = 'gps-status searching';
            gpsStatus.querySelector('span:last-child').textContent = translations[currentLang].gps_searching;

            gpsWatchId = navigator.geolocation.watchPosition(
                (position) => {
                    const pos = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    };
                    const accuracy = position.coords.accuracy;

                    // Mettre à jour le statut GPS
                    gpsStatus.className = 'gps-status connected';
                    gpsStatus.querySelector('span:last-child').textContent = translations[currentLang].gps_connected;

                    // Créer ou mettre à jour le marqueur GPS
                    updateGpsMarker(pos, accuracy);

                    // Suivre la position si le mode suivi est actif
                    if (isFollowingGps && map) {
                        map.panTo(pos);
                    }
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

                // Charger uniquement le tour sélectionné
                loadTourFromKml(tourId);

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

            // Remettre le status GPS
            const gpsStatus = document.getElementById('gpsStatus');
            gpsStatus.className = 'gps-status searching';
        }

        // ═══════════════════════════════════════════
        //  PANNEAU MUSIQUE (SPOTIFY)
        // ═══════════════════════════════════════════

        // IDs des playlists Spotify
        // ⚠️ REMPLACE ces IDs par tes propres playlists NOMADRIVE
        const PLAYLISTS = {
            chill:   '37i9dQZF1DX4WYpdgoIcn6',  // Chill Hits
            summer:  '37i9dQZF1DXdPec7aLTmlC',  // Summer Hits  
            jazz:    '37i9dQZF1DX0SM0LYsmbMT',  // Jazz Vibes
            pop:     '37i9dQZF1DXcBWIGoYBM5M',  // Today's Top Hits
            french:  '37i9dQZF1DX1HCSbq0nkYb',  // French Touch
            sunset:  '37i9dQZF1DX2UgsUIg75Vg'   // Sunset Chill
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