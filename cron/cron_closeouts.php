<?php
// ── CRON — Gestion automatique des closeouts véhicules ───────────────────────
// 1. Bloque les voitures manquantes pour les résas overcapacity (>2 pax, 1 voiture)
// 2. Libère les closeouts des résas annulées
// Cron suggéré : 0 6 * * * php /var/www/html/nomadrive/cron_closeouts.php >> /var/log/nomadrive_closeouts.log 2>&1

$madiDir = '/var/www/html/madi.mt';
require_once $madiDir . '/vendor/autoload.php';
require_once $madiDir . '/php/fonctions.php';
require_once $madiDir . '/php/config.php';
require_once dirname(__DIR__) . '/config.php';
$db1->query("SET NAMES 'utf8mb4'");

const POOL_ID          = 1018292;
const POOL_VEHICLE_IDS = [1029380, 1029381, 1029382, 1029383, 1029384, 1029386, 1029387, 1029388];

function _bokunHeaders(string $method, string $path): array {
    $accessKey = BOKUN_ACCESS_KEY;
    $secretKey = BOKUN_SECRET_KEY;
    $date      = gmdate('Y-m-d H:i:s');
    $signature = base64_encode(hash_hmac('sha1', $date . $accessKey . strtoupper($method) . $path, $secretKey, true));
    return [
        "X-Bokun-AccessKey: {$accessKey}",
        "X-Bokun-Date: {$date}",
        "X-Bokun-Signature: {$signature}",
        "Content-Type: application/json;charset=UTF-8",
        "Accept: application/json",
    ];
}

function _bokunGet(string $path): array {
    $ch = curl_init('https://api.bokun.io' . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => _bokunHeaders('GET', $path)]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'data' => json_decode($raw, true)];
}

function _bokunPost(string $path): array {
    $ch = curl_init('https://api.bokun.io' . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_POST => true, CURLOPT_POSTFIELDS => '{}', CURLOPT_HTTPHEADER => _bokunHeaders('POST', $path)]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'data' => json_decode($raw, true)];
}

function _bokunDelete(string $path): array {
    $ch = curl_init('https://api.bokun.io' . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CUSTOMREQUEST => 'DELETE', CURLOPT_HTTPHEADER => _bokunHeaders('DELETE', $path)]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'data' => json_decode($raw, true)];
}

$log = fn(string $tag, string $msg) => print(date('[Y-m-d H:i:s]') . " [{$tag}] {$msg}\n");

// ── PHASE 1 : Bloquer les nouvelles résas overcapacity ───────────────────────

$toCheck = $db1->query("
    SELECT bokun_booking_id, participants, DATE(activity_date) AS day,
           confirmation_code, TRIM(CONCAT(first_name,' ',last_name)) AS name
    FROM nomadrive_customers
    WHERE booking_status = 'CONFIRMED'
      AND start_datetime > NOW()
      AND start_datetime <= DATE_ADD(NOW(), INTERVAL 90 DAY)
      AND product_id IN (1194328, 1197812)
      AND bokun_booking_id IS NOT NULL AND bokun_booking_id != ''
      AND (closeout_resource_ids IS NULL OR closeout_resource_ids = '')
")->fetchAll(PDO::FETCH_ASSOC);

$log('INFO', count($toCheck) . " résa(s) confirmée(s) à vérifier pour overcapacity.");

foreach ($toCheck as $row) {
    $bid  = $row['bokun_booking_id'];
    $pax  = (int)$row['participants'];
    $date = $row['day'];

    $resp = _bokunGet("/restapi/v2.0/resource/assignments/{$date}?poolId=" . POOL_ID . "&bookingId={$bid}");
    if ($resp['code'] !== 200) {
        $log('ERREUR', "{$row['confirmation_code']} — assignments HTTP {$resp['code']}");
        continue;
    }

    $assignments    = $resp['data'] ?? [];
    $uniqueVehicles = count(array_unique(array_filter(array_column($assignments, 'resourceId'))));
    $needed         = max(1, (int)ceil($pax / 2));

    if ($uniqueVehicles >= $needed) continue; // OK

    $toBlock = $needed - $uniqueVehicles;

    // Trouver les voitures libres ce jour-là
    $dayResp    = _bokunGet("/restapi/v2.0/resource/assignments/{$date}?poolId=" . POOL_ID);
    $usedIds    = array_unique(array_filter(array_column($dayResp['data'] ?? [], 'resourceId')));
    $freeVehicles = array_values(array_diff(POOL_VEHICLE_IDS, $usedIds));

    $blocked  = 0;
    $blockedIds = [];
    foreach ($freeVehicles as $vid) {
        if ($blocked >= $toBlock) break;
        $r = _bokunPost("/restapi/v2.0/resource/closeout/{$date}?resourceId={$vid}");
        if (in_array($r['code'], [200, 201, 204])) {
            $blockedIds[] = $vid;
            $blocked++;
        } else {
            $log('ERREUR', "{$row['confirmation_code']} — closeout #{$vid} HTTP {$r['code']}");
        }
    }

    if (!empty($blockedIds)) {
        $db1->prepare("UPDATE nomadrive_customers SET closeout_resource_ids = ? WHERE bokun_booking_id = ?")
            ->execute([implode(',', $blockedIds), $bid]);
        $log('BLOQUÉ', "{$row['confirmation_code']} ({$row['name']}) {$pax} pax — voitures bloquées : " . implode(', ', array_map(fn($v) => "#{$v}", $blockedIds)));
    }

    if ($blocked < $toBlock) {
        $log('WARN', "{$row['confirmation_code']} — seulement {$blocked}/{$toBlock} voitures bloquées (pool insuffisant)");
    }
}

// ── PHASE 2 : Libérer les closeouts des résas annulées ───────────────────────

$withCloseouts = $db1->query("
    SELECT bokun_booking_id, closeout_resource_ids, DATE(activity_date) AS day,
           confirmation_code, TRIM(CONCAT(first_name,' ',last_name)) AS name
    FROM nomadrive_customers
    WHERE closeout_resource_ids IS NOT NULL AND closeout_resource_ids != ''
")->fetchAll(PDO::FETCH_ASSOC);

$log('INFO', count($withCloseouts) . " résa(s) avec closeout actif à vérifier.");

foreach ($withCloseouts as $row) {
    $bid    = $row['bokun_booking_id'];
    $detail = _bokunGet("/booking.json/{$bid}");

    if ($detail['code'] !== 200) {
        $log('ERREUR', "{$row['confirmation_code']} — status HTTP {$detail['code']}");
        continue;
    }

    $pb     = ($detail['data']['productBookings'] ?? [])[0] ?? [];
    $status = $pb['status'] ?? $detail['data']['status'] ?? '';

    if ($status === 'CONFIRMED') {
        $log('OK', "{$row['confirmation_code']} ({$row['name']}) — encore confirmée, closeout maintenu.");
        continue;
    }

    $vids     = array_map('trim', explode(',', $row['closeout_resource_ids']));
    $released = [];
    $errors   = [];
    foreach ($vids as $vid) {
        $r = _bokunDelete("/restapi/v2.0/resource/closeout/{$row['day']}?resourceId={$vid}");
        if (in_array($r['code'], [200, 201, 204])) {
            $released[] = $vid;
        } else {
            $errors[] = "#{$vid} HTTP {$r['code']}";
        }
    }

    $db1->prepare("UPDATE nomadrive_customers SET closeout_resource_ids = NULL WHERE bokun_booking_id = ?")
        ->execute([$bid]);

    $label = empty($errors)
        ? "voitures libérées : " . implode(', ', array_map(fn($v) => "#{$v}", $released))
        : "partiellement libéré — erreurs : " . implode(', ', $errors);
    $log('LIBÉRÉ', "{$row['confirmation_code']} ({$row['name']}) statut={$status} — {$label}");
}

$log('INFO', "Terminé.");
