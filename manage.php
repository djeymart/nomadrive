<?php
session_start();

$madiDir = '/var/www/html/madi.mt';
if (!is_dir($madiDir)) $madiDir = dirname(__DIR__);
require_once $madiDir . '/vendor/autoload.php';
require_once $madiDir . '/php/fonctions.php';
require_once $madiDir . '/php/config.php';
require_once __DIR__ . '/config.php';
$db1->query("SET NAMES 'utf8mb4'");
try { $db1->exec("ALTER TABLE nomadrive_customers ADD COLUMN closeout_resource_ids VARCHAR(200) NULL DEFAULT NULL"); } catch (PDOException $e) {}

require_once __DIR__ . '/nomadrive_auth.php';

// ── Auth — même système que dashboard.php ─────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $pwd    = $_POST['password'] ?? '';
    $hash   = hash('sha256', hash('sha256', $pwd));
    $stored = $db1->query("SELECT valeur FROM nomadrive_settings WHERE cle='admin_password_hash'")->fetchColumn();
    if ($stored && hash_equals($stored, $hash)) {
        $_SESSION['nd_auth'] = true;
        ndCreateRememberToken($db1);
    } else {
        $loginError = 'Mot de passe incorrect.';
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'logout') {
    ndRevokeRememberToken($db1);
    session_destroy();
    header('Location: manage.php');
    exit;
}

if (!ndIsAuth($db1)) { ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>NOMADRIVE — Gestion</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #0f172a; display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: system-ui, sans-serif; }
.login { background: #1e293b; border-radius: 16px; padding: 48px; width: 360px; text-align: center; }
.login h1 { color: #fff; font-size: 22px; margin-bottom: 8px; }
.login p { color: #94a3b8; font-size: 14px; margin-bottom: 32px; }
.login input { width: 100%; padding: 14px 16px; border-radius: 10px; border: 1px solid #334155; background: #0f172a; color: #fff; font-size: 16px; text-align: center; letter-spacing: 4px; margin-bottom: 16px; outline: none; }
.login input:focus { border-color: #6366f1; }
.login button { width: 100%; padding: 14px; border-radius: 10px; border: none; background: #6366f1; color: #fff; font-size: 15px; font-weight: 600; cursor: pointer; }
.login button:hover { background: #4f46e5; }
.error { color: #f87171; font-size: 13px; margin-top: 12px; }
</style>
</head>
<body>
<div class="login">
    <h1>NOMADRIVE</h1>
    <p>Espace de gestion</p>
    <form method="POST">
        <input type="hidden" name="action" value="login">
        <input type="password" name="password" placeholder="••••••••" autofocus>
        <button type="submit">Accéder</button>
        <?php if (!empty($loginError)): ?>
        <p class="error"><?= htmlspecialchars($loginError) ?></p>
        <?php endif; ?>
    </form>
</div>
</body>
</html>
<?php
    exit;
}

// ── Bokun helpers ─────────────────────────────────────────────────────────────
function bokunHeaders(string $method, string $path): array {
    $accessKey = defined('BOKUN_ACCESS_KEY') ? BOKUN_ACCESS_KEY : '';
    $secretKey = defined('BOKUN_SECRET_KEY') ? BOKUN_SECRET_KEY : '';
    $date      = gmdate('Y-m-d H:i:s');
    $message   = $date . $accessKey . strtoupper($method) . $path;
    $signature = base64_encode(hash_hmac('sha1', $message, $secretKey, true));
    return [
        "X-Bokun-AccessKey: {$accessKey}",
        "X-Bokun-Date: {$date}",
        "X-Bokun-Signature: {$signature}",
        "Content-Type: application/json;charset=UTF-8",
        "Accept: application/json",
    ];
}

// ── Bokun API — actions manuelles désactivées temporairement ─────────────────
// Remettre à true pour réactiver sync, audit, fix, release, unassigned.
// N'affecte pas le stock check (lecture auto).
const BOKUN_API_ENABLED = false;

function bokunPost(string $path, array $body): array {
    $url = 'https://api.bokun.io' . $path;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => bokunHeaders('POST', $path),
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return ['code' => $code, 'data' => json_decode($raw, true), 'raw' => $raw];
}

function bokunPut(string $path, array $body): array {
    $url = 'https://api.bokun.io' . $path;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CUSTOMREQUEST  => 'PUT',
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => bokunHeaders('PUT', $path),
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return ['code' => $code, 'data' => json_decode($raw, true), 'raw' => $raw];
}

function bokunRaw(string $path, string $method, string $rawBody): array {
    $url = 'https://api.bokun.io' . $path;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_POSTFIELDS     => $rawBody,
        CURLOPT_HTTPHEADER     => bokunHeaders(strtoupper($method), $path),
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return ['code' => $code, 'data' => json_decode($raw, true), 'raw' => $raw];
}

function bokunGet(string $path): array {
    $url = 'https://api.bokun.io' . $path;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => bokunHeaders('GET', $path),
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return ['code' => $code, 'data' => json_decode($raw, true), 'raw' => $raw];
}

function bokunDelete(string $path): array {
    $url = 'https://api.bokun.io' . $path;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CUSTOMREQUEST  => 'DELETE',
        CURLOPT_HTTPHEADER     => bokunHeaders('DELETE', $path),
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return ['code' => $code, 'data' => json_decode($raw, true), 'raw' => $raw];
}

// Produits Bokun (depuis les widgets)
const BOKUN_PRODUCTS = [
    1194328 => '🏙️ City',
    1197812 => '🌊 French Riviera',
    1197894 => '🌅 Sunset',
];

const POOL_ID          = 1018292;
const POOL_VEHICLE_IDS = [1029380, 1029381, 1029382, 1029383, 1029384, 1029386, 1029387, 1029388];

// ── Sync Bokun ────────────────────────────────────────────────────────────────
// ── Bokun — exploration API disponibilités ────────────────────────────────────
$bokunExploreResult = null;
if (isset($_POST['bokun_explore']) && BOKUN_API_ENABLED) {
    $exploreId   = (int)($_POST['explore_product_id'] ?? 1197812);
    $exploreDate = $_POST['explore_date'] ?? date('Y-m-d', strtotime('+1 day'));
    $bokunExploreResult = [
        'activity'      => bokunGet("/activity.json/{$exploreId}"),
        'availabilities'=> bokunGet("/activity.json/{$exploreId}/availabilities.json?lang=en&currency=EUR&date={$exploreDate}"),
        'seasons'       => bokunGet("/activity.json/{$exploreId}/seasons.json"),
    ];
}

$bokunFreeResult = null;
if (isset($_POST['bokun_free']) && BOKUN_API_ENABLED) {
    $freePath   = trim($_POST['free_path'] ?? '');
    $freeMethod = $_POST['free_method'] ?? 'GET';
    $freeBody   = trim($_POST['free_body'] ?? '');
    if ($freePath) {
        if ($freeMethod === 'GET') {
            $bokunFreeResult = bokunGet($freePath);
        } elseif ($freeBody !== '') {
            $bokunFreeResult = bokunRaw($freePath, $freeMethod, $freeBody);
        } else {
            $bokunFreeResult = bokunRaw($freePath, $freeMethod, '');
        }
        $bokunFreeResult['path']      = $freeMethod . ' ' . $freePath;
        $bokunFreeResult['body_sent'] = $freeBody ?: null;
    }
}

// ── Bokun — réservations sans ressource affectée ─────────────────────────────
$unassignedResult = null;
if (isset($_POST['load_unassigned']) && BOKUN_API_ENABLED) {
    $page      = 0;
    $totalHits = null;
    $unassigned    = [];
    $underassigned = [];
    do {
        $resp = bokunPost('/booking.json/booking-search', [
            'startDateRange' => ['from' => date('Y-m-d', strtotime('+1 day')), 'to' => date('Y-m-d', strtotime('+365 days'))],
            'statuses'       => ['CONFIRMED'],
            'pageSize'       => 50,
            'page'           => $page,
        ]);
        if ($resp['code'] !== 200) break;
        $totalHits = $resp['data']['totalHits'] ?? 0;
        $items     = $resp['data']['items']     ?? [];
        if (empty($items)) break;
        foreach ($items as $booking) {
            $pb     = ($booking['productBookings'] ?? [])[0] ?? [];
            $status = $pb['status'] ?? $booking['status'] ?? '';
            if ($status !== 'CONFIRMED') continue;

            $bookingId = $booking['id'] ?? null;
            if (!$bookingId) continue;
            $detail   = bokunGet('/booking.json/' . $bookingId);
            if ($detail['code'] !== 200) continue;
            $detailPb = ($detail['data']['productBookings'] ?? [])[0] ?? [];

            $pcbs              = $detailPb['fields']['priceCategoryBookings'] ?? [];
            $pax               = $pb['totalParticipants'] ?? array_sum(array_column($pcbs, 'quantity'));
            $assignedResources = $detailPb['assignedResources'] ?? [];
            $assignedCount     = count($assignedResources);
            $vehiclesNeeded    = max(1, (int) ceil((int)$pax / 2));
            $isUnassigned      = $assignedCount === 0;
            // Bokun assigne 1 véhicule pour tout le booking (EMPTY_THEN_PRIVATE)
            // → un booking de N>2 pax n'a qu'1 véhicule → pool cross-tour sous-décompté
            $isUnderassigned   = !$isUnassigned && $assignedCount < $vehiclesNeeded;

            $startMs    = $pb['startDateTime'] ?? $pb['startDate'] ?? null;
            $entry = [
                'code'          => $booking['confirmationCode'] ?? '',
                'name'          => trim(($booking['customer']['firstName'] ?? '') . ' ' . ($booking['customer']['lastName'] ?? '')),
                'product'       => $pb['product']['title'] ?? '',
                'date'          => $startMs ? date('Y-m-d H:i', intval($startMs / 1000)) : '',
                'pax'           => (int) $pax,
                'channel'       => $booking['seller']['title'] ?? ($booking['channel']['title'] ?? ''),
                'assigned_count'  => $assignedCount,
                'vehicles_needed' => $vehiclesNeeded,
            ];

            if ($isUnassigned) {
                $unassigned[] = $entry;
            } elseif ($isUnderassigned) {
                $underassigned[] = $entry;
            }
        }
        $page++;
    } while ($totalHits && ($page * 50) < $totalHits);
    usort($unassigned,    fn($a, $b) => strcmp($a['date'], $b['date']));
    usort($underassigned, fn($a, $b) => strcmp($a['date'], $b['date']));
    $unassignedResult = [
        'total'        => $totalHits,
        'unassigned'   => count($unassigned),
        'underassigned'=> count($underassigned),
        'items'        => $unassigned,
        'under_items'  => $underassigned,
    ];
}

// ── Bokun — libération manuelle des closeouts annulés ────────────────────────
$releaseResult = null;
if (isset($_POST['release_closeouts']) && BOKUN_API_ENABLED) {
    $withCloseouts = $db1->query("
        SELECT bokun_booking_id, closeout_resource_ids, DATE(activity_date) AS day,
               confirmation_code, TRIM(CONCAT(first_name,' ',last_name)) AS name
        FROM nomadrive_customers
        WHERE closeout_resource_ids IS NOT NULL AND closeout_resource_ids != ''
    ")->fetchAll(PDO::FETCH_ASSOC);

    $releaseResult = ['released' => [], 'kept' => [], 'errors' => []];

    foreach ($withCloseouts as $row) {
        $detail = bokunGet('/booking.json/' . $row['bokun_booking_id']);
        if ($detail['code'] !== 200) {
            $releaseResult['errors'][] = "{$row['confirmation_code']} : HTTP {$detail['code']}";
            continue;
        }
        $pb     = ($detail['data']['productBookings'] ?? [])[0] ?? [];
        $status = $pb['status'] ?? $detail['data']['status'] ?? '';

        if ($status === 'CONFIRMED') {
            $releaseResult['kept'][] = $row['confirmation_code'];
            continue;
        }

        $vids   = array_map('trim', explode(',', $row['closeout_resource_ids']));
        $errors = [];
        foreach ($vids as $vid) {
            $r = bokunDelete("/restapi/v2.0/resource/closeout/{$row['day']}?resourceId={$vid}");
            if (!in_array($r['code'], [200, 201, 204])) $errors[] = "#{$vid} HTTP {$r['code']}";
        }
        $db1->prepare("UPDATE nomadrive_customers SET closeout_resource_ids = NULL WHERE bokun_booking_id = ?")
            ->execute([$row['bokun_booking_id']]);

        if ($errors) {
            $releaseResult['errors'][] = "{$row['confirmation_code']} partiellement libéré — " . implode(', ', $errors);
        } else {
            $releaseResult['released'][] = "{$row['confirmation_code']} ({$row['name']}) — voitures " . implode(', ', array_map(fn($v) => "#{$v}", $vids));
        }
    }
}

// ── Bokun — gestion liste ignorés audit ──────────────────────────────────────
if (!empty($_POST['audit_ignore'])) {
    $_SESSION['audit_ignored'][$_POST['audit_ignore']] = true;
}
if (isset($_POST['audit_ignore_reset'])) {
    unset($_SESSION['audit_ignored']);
}
$auditIgnored = $_SESSION['audit_ignored'] ?? [];

// ── Bokun — correction affectation véhicules ─────────────────────────────────
$fixResult = null;
if (!empty($_POST['fix_booking_id']) && !empty($_POST['fix_type']) && !empty($_POST['fix_date']) && BOKUN_API_ENABLED) {
    $fixDate      = $_POST['fix_date'];
    $fixBookingId = $_POST['fix_booking_id'];
    $fixType      = $_POST['fix_type'];
    $fixNeeded    = (int)($_POST['fix_needed']   ?? 1);
    $fixAssigned  = (int)($_POST['fix_assigned']  ?? 0);

    $fixResult = ['actions' => [], 'errors' => []];

    // Véhicules déjà utilisés ce jour-là (tous slots confondus)
    $dayResp    = bokunGet("/restapi/v2.0/resource/assignments/{$fixDate}?poolId=" . POOL_ID);
    $dayAssigns = ($dayResp['code'] === 200) ? ($dayResp['data'] ?? []) : [];
    $usedIds    = array_unique(array_filter(array_column($dayAssigns, 'resourceId')));
    $freeVehicles = array_values(array_diff(POOL_VEHICLE_IDS, $usedIds));

    if ($fixType === 'overcapacity') {
        // Bloquer (needed - assigned) voitures libres pour compenser le pool
        $toBlock         = $fixNeeded - $fixAssigned;
        $blocked         = 0;
        $attempts        = 0;
        $blockedVehicles = [];
        foreach ($freeVehicles as $vid) {
            if ($blocked >= $toBlock) break;
            if ($attempts >= $toBlock) break;
            $attempts++;
            $r = bokunPost("/restapi/v2.0/resource/closeout/{$fixDate}?resourceId={$vid}", []);
            if (in_array($r['code'], [200, 201, 204])) {
                $fixResult['actions'][] = "Voiture #{$vid} bloquée pour le {$fixDate}";
                $blockedVehicles[] = $vid;
                $blocked++;
            } else {
                $fixResult['errors'][] = "Échec blocage #{$vid} : HTTP {$r['code']} — " . substr($r['raw'] ?? '', 0, 150);
            }
        }
        if ($blocked < $toBlock) {
            $fixResult['errors'][] = "{$blocked}/{$toBlock} voitures bloquées — pool insuffisant";
        }
        if (!empty($blockedVehicles)) {
            $db1->prepare("UPDATE nomadrive_customers SET closeout_resource_ids = ? WHERE bokun_booking_id = ?")
                ->execute([implode(',', $blockedVehicles), $fixBookingId]);
        }
    } elseif ($fixType === 'unassigned') {
        // Récupérer l'experienceBookingId puis assigner une voiture libre
        if (empty($freeVehicles)) {
            $fixResult['errors'][] = "Aucune voiture libre le {$fixDate}";
        } else {
            $detail = bokunGet("/booking.json/{$fixBookingId}");
            if ($detail['code'] !== 200) {
                $fixResult['errors'][] = "Impossible de lire la résa : HTTP {$detail['code']}";
            } else {
                $expBookingId = ($detail['data']['productBookings'] ?? [])[0]['id'] ?? null;
                if (!$expBookingId) {
                    $fixResult['errors'][] = "experienceBookingId introuvable pour la résa {$fixBookingId}";
                } else {
                    $vid = $freeVehicles[0];
                    $r   = bokunPost("/restapi/v2.0/resource/assignment/for/experienceBooking/{$expBookingId}/pool/" . POOL_ID . "/resource/{$vid}", []);
                    if (in_array($r['code'], [200, 201, 204])) {
                        $fixResult['actions'][] = "Voiture #{$vid} assignée à la résa (expBookingId {$expBookingId})";
                    } else {
                        $fixResult['errors'][] = "Échec assignation : HTTP {$r['code']} — " . substr($r['raw'] ?? '', 0, 200);
                    }
                }
            }
        }
    }
}

// ── Bokun — audit affectation véhicules ──────────────────────────────────────
$resourceAuditResult = null;
if (isset($_POST['audit_resources']) && BOKUN_API_ENABLED) {
    try {
        $rows = $db1->query("
            SELECT DATE(start_datetime) AS day,
                   DATE_FORMAT(start_datetime, '%H:%i') AS slot,
                   bokun_booking_id, product_id, participants, product_name,
                   TRIM(CONCAT(first_name,' ',last_name)) AS name,
                   confirmation_code
            FROM nomadrive_customers
            WHERE booking_status = 'CONFIRMED'
              AND start_datetime > NOW()
              AND start_datetime <= DATE_ADD(NOW(), INTERVAL 90 DAY)
              AND product_id IN (1194328, 1197812)
              AND bokun_booking_id IS NOT NULL AND bokun_booking_id != ''
              AND (closeout_resource_ids IS NULL OR closeout_resource_ids = '')
            ORDER BY start_datetime
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $rows = []; }

    $issues   = [];
    $debugRaw = null;

    foreach ($rows as $b) {
        $date = $b['day'];
        $bid  = $b['bokun_booking_id'];
        $pax  = (int)$b['participants'];

        $resp = bokunGet("/restapi/v2.0/resource/assignments/{$date}?poolId=1018292&bookingId={$bid}");
        if ($resp['code'] !== 200) continue;
        $assignments = $resp['data'] ?? [];
        if ($debugRaw === null) $debugRaw = ['date' => $date, 'bookingId' => $bid, 'sample' => array_slice($assignments, 0, 3)];

        // Count distinct vehicles assigned to this booking
        $uniqueVehicles = count(array_unique(array_filter(array_column($assignments, 'resourceId'))));
        $vehiclesNeeded = max(1, (int)ceil($pax / 2));

        if ($uniqueVehicles < $vehiclesNeeded) {
            $issues[] = [
                'type'       => $uniqueVehicles === 0 ? 'unassigned' : 'overcapacity',
                'date'       => $date,
                'slot'       => $b['slot'],
                'code'       => $b['confirmation_code'],
                'name'       => $b['name'],
                'product'    => $b['product_name'],
                'pax'        => $pax,
                'assigned'   => $uniqueVehicles,
                'needed'     => $vehiclesNeeded,
                'booking_id' => $b['bokun_booking_id'],
            ];
        }
    }

    $allDates = count(array_unique(array_column($rows, 'day')));
    $issues   = array_values(array_filter($issues, fn($i) => empty($auditIgnored[$i['booking_id'] ?? ''])));
    $resourceAuditResult = ['dates' => $allDates, 'issues' => $issues, 'debug' => $debugRaw];
}

// ── Bokun — mise à jour disponibilités Tour 2 ─────────────────────────────────
// Tour 1 (City) : 1194328  |  Tour 2 (French Riviera) : 1197812
// Capacité partagée : 16 places / 8 voitures
const PRODUCT_TOUR1   = 1194328;
const PRODUCT_TOUR2   = 1197812;

// IDs des créneaux horaires — récupérés via explorateur API
// Tour 1 (City, 1194328)
const TOUR1_START_TIME_IDS = [
    '10:00' => 4908401,
    '14:00' => 4933733,
];
// Tour 2 (French Riviera, 1197812)
const TOUR2_START_TIME_IDS = [
    '10:00' => 4940234,
    '14:00' => 4928196,
];

// ── Bokun — vérification stock pool ──────────────────────────────────────────
$stockCheckResult = null;
if (true) { // auto-run — pas besoin d'action POST
    // Ordre d'affichage : par horaire, City puis Riviera dans chaque groupe
    $stDef = [
        '10:00' => [4908401 => [PRODUCT_TOUR1, 'City 10h'], 4940234 => [PRODUCT_TOUR2, 'Riviera 10h']],
        '14:00' => [4933733 => [PRODUCT_TOUR1, 'City 14h'], 4928196 => [PRODUCT_TOUR2, 'Riviera 14h']],
    ];

    $rows = $db1->query("
        SELECT DATE(start_datetime) AS day,
               DATE_FORMAT(start_datetime, '%H:%i') AS slot,
               product_id, participants, closeout_resource_ids
        FROM nomadrive_customers
        WHERE booking_status = 'CONFIRMED'
          AND start_datetime > NOW()
          AND start_datetime <= DATE_ADD(NOW(), INTERVAL 90 DAY)
          AND product_id IN (" . PRODUCT_TOUR1 . ", " . PRODUCT_TOUR2 . ")
        ORDER BY start_datetime
    ")->fetchAll(PDO::FETCH_ASSOC);

    $byDate = [];
    foreach ($rows as $r) $byDate[$r['day']][] = $r;

    $stockCheckResult = [];
    foreach ($byDate as $date => $bookings) {
        $resp        = bokunGet("/restapi/v2.0/resource/assignments/{$date}?poolId=" . POOL_ID);
        $assignments = ($resp['code'] === 200) ? ($resp['data'] ?? []) : [];

        $byStid = [];
        foreach ($assignments as $a) {
            $stid = $a['startTimeId'] ?? null;
            $rid  = $a['resourceId']  ?? null;
            if ($stid && $rid) $byStid[$stid][$rid] = true;
        }

        // Closeouts comptés sur la journée (bloquent toute la journée)
        $dayCloseouts = 0;
        foreach ($bookings as $b) {
            if (!empty($b['closeout_resource_ids']))
                $dayCloseouts += count(array_filter(explode(',', $b['closeout_resource_ids'])));
        }

        $groups = [];
        foreach ($stDef as $time => $stids) {
            $slotRows = [];
            $timeVehicles = [];
            foreach ($stids as $stid => [$pid, $label]) {
                $slotBookings = array_values(array_filter($bookings, fn($b) => $b['product_id'] == $pid && $b['slot'] === $time));
                if (empty($slotBookings)) continue;

                $slotPax      = array_sum(array_column($slotBookings, 'participants'));
                $slotAssigned = count($byStid[$stid] ?? []);
                $slotExpected = array_sum(array_map(fn($b) => max(1, (int)ceil((int)$b['participants'] / 2)), $slotBookings));
                $slotClosed   = 0;
                $hasCron      = false;
                foreach ($slotBookings as $b) {
                    if (!empty($b['closeout_resource_ids'])) {
                        $slotClosed += count(array_filter(explode(',', $b['closeout_resource_ids'])));
                        $hasCron = true;
                    }
                }
                $compensated = $slotAssigned + $slotClosed;
                $slotRows[]  = [
                    'label'    => $label,
                    'pax'      => $slotPax,
                    'assigned' => $slotAssigned,
                    'closed'   => $slotClosed,
                    'expected' => $slotExpected,
                    'status'   => $compensated >= $slotExpected ? ($hasCron ? 'auto' : 'ok') : 'warning',
                ];
                $timeVehicles = array_merge($timeVehicles, array_keys($byStid[$stid] ?? []));
            }
            if (empty($slotRows)) continue;
            $groups[] = [
                'time'    => $time,
                'slots'   => $slotRows,
                'libres'  => max(0, count(POOL_VEHICLE_IDS) - count(array_unique($timeVehicles)) - $dayCloseouts),
            ];
        }

        if (!empty($groups)) {
            $stockCheckResult[] = ['date' => $date, 'groups' => $groups];
        }
    }
}

$syncResult = null;
if (isset($_POST['sync_bokun']) && BOKUN_API_ENABLED) {
    $dateFrom = $_POST['date_from'] ?? date('Y-m-d', strtotime('-90 days'));
    $dateTo   = $_POST['date_to']   ?? date('Y-m-d');

    $upsert = $db1->prepare("
        INSERT INTO nomadrive_customers
            (bokun_booking_id, confirmation_code, first_name, last_name, email,
             phone, phone_country_code, product_id, product_name, activity_date, start_datetime, end_datetime, participants, booking_status, channel, synced_at)
        VALUES
            (:bid, :code, :fn, :ln, :email, :phone, :pcc, :pid, :pname, :adate, :startdt, :enddt, :participants, :status, :channel, NOW())
        ON DUPLICATE KEY UPDATE
            first_name           = VALUES(first_name),
            last_name            = VALUES(last_name),
            email                = VALUES(email),
            phone                = VALUES(phone),
            phone_country_code   = VALUES(phone_country_code),
            product_name         = VALUES(product_name),
            activity_date        = VALUES(activity_date),
            start_datetime       = VALUES(start_datetime),
            end_datetime         = VALUES(end_datetime),
            participants         = VALUES(participants),
            booking_status       = VALUES(booking_status),
            channel              = VALUES(channel),
            synced_at            = NOW()
    ");

    $debugSample  = null;
    $debugRawResp = null;
    $inserted = 0;
    $errors   = [];

    $page      = 0;
    $totalHits = null;

    do {
        $resp = bokunPost('/booking.json/booking-search', [
            'startDateRange' => ['from' => $dateFrom, 'to' => $dateTo],
            'pageSize'       => 50,
            'page'           => $page,
        ]);

        if ($debugRawResp === null) $debugRawResp = ['code' => $resp['code'], 'data' => $resp['data']];

        if ($resp['code'] !== 200) {
            $errors[] = "Page {$page} : HTTP {$resp['code']} — " . substr($resp['raw'], 0, 300);
            break;
        }

        $totalHits = $resp['data']['totalHits'] ?? 0;
        $results   = $resp['data']['items']     ?? [];

        if (empty($results)) break;

        if ($debugSample === null) $debugSample = $results[0];

        foreach ($results as $booking) {
            $customer = $booking['customer'] ?? [];
            $pb       = ($booking['productBookings'] ?? [])[0] ?? [];

            // startDateTime = timestamp complet avec heure (startDate = minuit UTC, inutile pour l'heure)
            $startMs = $pb['startDateTime'] ?? $pb['startDate'] ?? null;
            $endMs   = $pb['endDateTime']   ?? null;
            $actDate = $startMs ? date('Y-m-d',       intval($startMs / 1000)) : null;
            $startDt = $startMs ? date('Y-m-d H:i:s', intval($startMs / 1000)) : null;
            $endDt   = $endMs   ? date('Y-m-d H:i:s', intval($endMs   / 1000)) : null;

            // Produit
            $product      = $pb['product'] ?? [];
            $activityId   = $product['id']    ?? null;
            $activityName = $product['title'] ?? null;

            // Statut dans productBookings
            $status  = $pb['status'] ?? $booking['status'] ?? '';
            $channel = $booking['seller']['title'] ?? $booking['channel']['title'] ?? '';

            $participants = max(1,
                (int)($pb['totalParticipants'] ?? 0)
                ?: array_sum(array_column($pb['fields']['priceCategoryBookings'] ?? [], 'quantity'))
                ?: 1
            );

            // Si la résa passe à non-CONFIRMED et avait des closeouts → les libérer
            if ($status !== 'CONFIRMED' && !empty($booking['id'])) {
                $crStmt = $db1->prepare("SELECT closeout_resource_ids, DATE(activity_date) AS day FROM nomadrive_customers WHERE bokun_booking_id = ? AND closeout_resource_ids IS NOT NULL AND closeout_resource_ids != ''");
                $crStmt->execute([(string)$booking['id']]);
                if ($cr = $crStmt->fetch(PDO::FETCH_ASSOC)) {
                    foreach (explode(',', $cr['closeout_resource_ids']) as $vid) {
                        bokunDelete("/restapi/v2.0/resource/closeout/{$cr['day']}?resourceId=" . trim($vid));
                    }
                    $db1->prepare("UPDATE nomadrive_customers SET closeout_resource_ids = NULL WHERE bokun_booking_id = ?")
                        ->execute([(string)$booking['id']]);
                }
            }

            $upsert->execute([
                ':bid'          => (string)($booking['id'] ?? ''),
                ':code'         => $booking['confirmationCode'] ?? '',
                ':fn'           => $customer['firstName'] ?? '',
                ':ln'           => $customer['lastName']  ?? '',
                ':email'        => $customer['email']     ?? '',
                ':phone'        => $customer['phoneNumber'] ?? '',
                ':pcc'          => $customer['phoneNumberCountryCode'] ?? '',
                ':pid'          => $activityId,
                ':pname'        => BOKUN_PRODUCTS[$activityId] ?? $activityName ?? '',
                ':adate'        => $actDate,
                ':startdt'      => $startDt,
                ':enddt'        => $endDt,
                ':participants' => $participants,
                ':status'       => $status,
                ':channel'      => $channel,
            ]);
            $inserted++;
        }

        $page++;
    } while (($page * 50) < $totalHits);

    $syncResult = ['inserted' => $inserted, 'total_hits' => $totalHits, 'errors' => $errors, 'debug' => $debugSample, 'rawResp' => $debugRawResp];
}

// ── Envoi email demande d'avis ────────────────────────────────────────────────
// Mettre '' pour désactiver le CC NOMADRIVE une fois les tests validés
const REVIEW_CC_EMAIL = 'contact@nomadrive.fr';

$emailResult = null;

function sendReviewEmail(string $toEmail, string $toName, string $firstName, string $lastName, string $tour, string $actDate, bool $isGyg, ?PDO $db = null, ?int $customerId = null, string $emailType = 'review_request'): array {
    global $db1;
    $db = $db ?? $db1;

    $googleReviewUrl = 'https://search.google.com/local/writereview?placeid=ChIJGdQvT-7bzRIRgGC_8yxIQKc';
    $gygReviewUrl    = 'https://www.getyourguide.com/nice-l314/discover-the-riviera-and-nice-by-electric-vehicle-t1285889/#reviews';
    $dateDisplay     = $actDate ? date('d/m/Y', strtotime($actDate)) : date('d/m/Y');
    $subject         = $emailType === 'review_followup'
        ? "A reminder — How was your NOMADRIVE experience? / Un petit rappel"
        : "How was your NOMADRIVE experience? / Votre avis nous intéresse !";

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp-sendkit.sarbacane.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USERNAME'] ?? '';
        $mail->Password   = $_ENV['SMTP_PASSWORD'] ?? '';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'base64';

        $mail->setFrom('contact@nomadrive.fr', 'NOMADRIVE');
        $mail->addReplyTo('contact@nomadrive.fr', 'NOMADRIVE');
        $mail->addAddress($toEmail, $toName);
        if (REVIEW_CC_EMAIL !== '') {
            $mail->addCC(REVIEW_CC_EMAIL, 'NOMADRIVE');
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = buildReviewEmailHtml($firstName, $lastName, $tour, $dateDisplay, $googleReviewUrl, $gygReviewUrl, $isGyg);

        $mail->send();
        $messageId = $mail->getLastMessageID();

        if ($db) {
            $db->prepare("
                INSERT INTO nomadrive_email_log (customer_id, email_to, email_type, subject, message_id, sent_at, status)
                VALUES (?, ?, ?, ?, ?, NOW(), 'sent')
            ")->execute([$customerId, $toEmail, $emailType, $subject, $messageId]);
        }

        return ['ok' => true, 'msg' => "Email envoyé à {$toEmail}"];
    } catch (\Exception $e) {
        return ['ok' => false, 'msg' => $e->getMessage()];
    }
}

if (isset($_POST['send_review_test'])) {
    $emailResult = sendReviewEmail('jeremy.martinetti@gmail.com', 'Jeremy Martinetti', 'Jeremy', 'Martinetti', '🌊 French Riviera', date('Y-m-d'), true);
}

if (isset($_POST['send_review_customer'])) {
    $cid  = (int)($_POST['customer_id'] ?? 0);
    $row  = $cid ? $db1->query("SELECT * FROM nomadrive_customers WHERE id = {$cid}")->fetch(PDO::FETCH_ASSOC) : null;
    if ($row) {
        $isGyg       = (stripos($row['channel'] ?? '', 'getyourguide') !== false);
        $toEmail     = $row['email'] ?: 'jeremy.martinetti@gmail.com';
        $toName      = trim($row['first_name'] . ' ' . $row['last_name']);
        $emailResult = sendReviewEmail($toEmail, $toName, $row['first_name'], $row['last_name'], $row['product_name'] ?? '', $row['activity_date'] ?? '', $isGyg, $db1, (int)$row['id']);
        if ($emailResult['ok']) {
            $db1->prepare("UPDATE nomadrive_customers SET review_requested_at = NOW() WHERE id = ?")->execute([$cid]);
        }
    } else {
        $emailResult = ['ok' => false, 'msg' => 'Client introuvable.'];
    }
}

function batchQuery(PDO $db): array {
    return $db->query("
        SELECT * FROM nomadrive_customers
        WHERE booking_status = 'CONFIRMED'
          AND review_requested_at IS NULL
          AND email IS NOT NULL AND email != ''
        ORDER BY activity_date ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

if (isset($_POST['send_review_batch_one'])) {
    $batch = batchQuery($db1);
    if (empty($batch)) {
        $emailResult = ['ok' => true, 'msg' => 'Aucun client éligible.'];
    } else {
        $row    = $batch[0];
        $isGyg  = stripos($row['channel'] ?? '', 'getyourguide') !== false;
        $toName = trim($row['first_name'] . ' ' . $row['last_name']);
        $emailResult = sendReviewEmail($row['email'], $toName, $row['first_name'], $row['last_name'], $row['product_name'] ?? '', $row['activity_date'] ?? '', $isGyg, $db1, (int)$row['id']);
        if ($emailResult['ok']) {
            $db1->prepare("UPDATE nomadrive_customers SET review_requested_at = NOW(), review_followup_at = NOW() WHERE id = ?")->execute([$row['id']]);
            $remaining = count($batch) - 1;
            $emailResult['msg'] .= " — {$remaining} client(s) restant(s) à envoyer.";
        }
    }
}

if (isset($_POST['send_review_batch'])) {
    $batch    = batchQuery($db1);
    $batchOk  = 0;
    $batchErr = [];
    foreach ($batch as $row) {
        $isGyg  = stripos($row['channel'] ?? '', 'getyourguide') !== false;
        $toName = trim($row['first_name'] . ' ' . $row['last_name']);
        $res    = sendReviewEmail($row['email'], $toName, $row['first_name'], $row['last_name'], $row['product_name'] ?? '', $row['activity_date'] ?? '', $isGyg, $db1, (int)$row['id']);
        if ($res['ok']) {
            $db1->prepare("UPDATE nomadrive_customers SET review_requested_at = NOW(), review_followup_at = NOW() WHERE id = ?")->execute([$row['id']]);
            $batchOk++;
        } else {
            $batchErr[] = "{$toName} : " . $res['msg'];
        }
    }
    $emailResult = [
        'ok'  => empty($batchErr),
        'msg' => "{$batchOk} email(s) envoyé(s)" . (count($batchErr) ? ' — Erreurs : ' . implode(', ', $batchErr) : ''),
    ];
}

function buildReviewEmailHtml(string $firstName, string $lastName, string $tour, string $date, string $googleUrl, string $gygUrl, bool $isGyg): string {
    $name    = htmlspecialchars(trim($firstName . ' ' . $lastName));
    $tour    = htmlspecialchars($tour);
    $date    = htmlspecialchars($date);
    $gygBtnEn = $isGyg ? "<td><a href=\"{$gygUrl}\" style=\"display:inline-block;background:#FF5533;color:#ffffff;font-size:14px;font-weight:700;padding:14px 24px;border-radius:10px;text-decoration:none;\">&#11088; Review on GetYourGuide</a></td>" : '';
    $gygBtnFr = $isGyg ? "<td><a href=\"{$gygUrl}\" style=\"display:inline-block;background:#FF5533;color:#ffffff;font-size:14px;font-weight:700;padding:14px 24px;border-radius:10px;text-decoration:none;\">&#11088; Avis GetYourGuide</a></td>" : '';
    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:system-ui,-apple-system,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;">

      <!-- Header -->
      <tr>
        <td style="background:#0f172a;padding:28px 40px;text-align:center;">
          <img src="https://nomadrive.fr/images/logo_nomadrive.jpg" alt="NOMADRIVE" width="180" style="display:block;margin:0 auto;max-width:180px;height:auto;">
          <div style="font-size:22px;font-weight:800;color:#ffffff;letter-spacing:3px;margin-top:14px;">NOMADRIVE</div>
          <div style="font-size:12px;color:#64748b;margin-top:4px;letter-spacing:1px;">NICE &middot; C&Ocirc;TE D'AZUR</div>
        </td>
      </tr>

      <!-- Body EN -->
      <tr>
        <td style="padding:40px 40px 8px;">
          <p style="margin:0 0 16px;font-size:18px;font-weight:700;color:#0f172a;">Thank you {$name}!</p>
          <p style="margin:0 0 12px;font-size:15px;color:#334155;line-height:1.6;">We hope your <strong>{$tour}</strong> experience on <strong>{$date}</strong> was unforgettable.</p>
          <p style="margin:0 0 24px;font-size:15px;color:#334155;line-height:1.6;">If you enjoyed your tour, leaving a review makes a huge difference for us &mdash; it only takes 2 minutes!</p>
          <table cellpadding="0" cellspacing="0" style="margin-bottom:32px;">
            <tr>
              <td style="padding-right:12px;">
                <a href="{$googleUrl}" style="display:inline-block;background:#1a1a2e;color:#ffffff;font-size:14px;font-weight:700;padding:14px 24px;border-radius:10px;text-decoration:none;">&#11088; Leave a Google Review</a>
              </td>
              {$gygBtnEn}
            </tr>
          </table>
        </td>
      </tr>

      <!-- Divider -->
      <tr>
        <td style="padding:0 40px;">
          <div style="border-top:1px solid #e2e8f0;"></div>
          <p style="text-align:center;font-size:11px;color:#94a3b8;margin:16px 0;">&mdash; Version fran&ccedil;aise ci-dessous &mdash;</p>
          <div style="border-top:1px solid #e2e8f0;"></div>
        </td>
      </tr>

      <!-- Body FR -->
      <tr>
        <td style="padding:32px 40px 8px;">
          <p style="margin:0 0 16px;font-size:18px;font-weight:700;color:#0f172a;">Merci {$name} !</p>
          <p style="margin:0 0 12px;font-size:15px;color:#334155;line-height:1.6;">Nous esp&eacute;rons que votre exp&eacute;rience <strong>{$tour}</strong> du <strong>{$date}</strong> a &eacute;t&eacute; m&eacute;morable.</p>
          <p style="margin:0 0 24px;font-size:15px;color:#334155;line-height:1.6;">Si vous avez appr&eacute;ci&eacute; votre tour, votre avis nous aide &eacute;norm&eacute;ment &agrave; nous faire conna&icirc;tre. Cela ne prend que 2 minutes !</p>
          <table cellpadding="0" cellspacing="0" style="margin-bottom:32px;">
            <tr>
              <td style="padding-right:12px;">
                <a href="{$googleUrl}" style="display:inline-block;background:#1a1a2e;color:#ffffff;font-size:14px;font-weight:700;padding:14px 24px;border-radius:10px;text-decoration:none;">&#11088; Laisser un avis Google</a>
              </td>
              {$gygBtnFr}
            </tr>
          </table>
        </td>
      </tr>

      <!-- Footer -->
      <tr>
        <td style="background:#f8fafc;padding:24px 40px;border-top:1px solid #e2e8f0;">
          <p style="margin:0;font-size:11px;color:#94a3b8;text-align:center;line-height:1.8;">
            NICE ACTIVITY (NOMADRIVE) &middot; SAS au capital de 100 000 &euro; &middot; RCS Nice 994 620 615<br>
            2 Place Guynemer, 06300 Nice &middot; <a href="mailto:contact@nomadrive.fr" style="color:#64748b;">contact@nomadrive.fr</a> &middot; <a href="https://nomadrive.fr" style="color:#64748b;">nomadrive.fr</a>
          </p>
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}

// ── Preview envois automatiques ───────────────────────────────────────────────
$autoQueue = $db1->query("
    SELECT *,
           DATE_ADD(end_datetime, INTERVAL 1 HOUR) AS send_at
    FROM nomadrive_customers
    WHERE booking_status = 'CONFIRMED'
      AND end_datetime IS NOT NULL
      AND DATE(end_datetime) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)
      AND review_requested_at IS NULL
    ORDER BY end_datetime ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ── Données ───────────────────────────────────────────────────────────────────
$search  = trim($_GET['q'] ?? '');
$status  = $_GET['status'] ?? '';
$where   = 'WHERE 1=1';
$params  = [];

if ($search) {
    $where   .= " AND (first_name LIKE :q OR last_name LIKE :q OR email LIKE :q OR confirmation_code LIKE :q)";
    $params[':q'] = "%{$search}%";
}
if ($status) {
    $where   .= " AND booking_status = :status";
    $params[':status'] = $status;
}

$stmt = $db1->prepare("SELECT * FROM nomadrive_customers {$where} ORDER BY activity_date DESC, synced_at DESC LIMIT 200");
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats = $db1->query("
    SELECT
        COUNT(*) as total,
        SUM(booking_status = 'CONFIRMED') as confirmed,
        SUM(email != '' AND email IS NOT NULL) as with_email,
        SUM(review_requested_at IS NOT NULL) as review_sent
    FROM nomadrive_customers
")->fetch(PDO::FETCH_ASSOC);

// ── Flotte — actions ──────────────────────────────────────────────────────────
if (isset($_POST['fleet_action']) && $_POST['fleet_action'] === 'update_vehicle') {
    $db1->prepare("UPDATE nomadrive_vehicules SET actif = ?, guide = ?, notes = ? WHERE id = ?")
        ->execute([
            (int)($_POST['veh_actif'] ?? 1),
            isset($_POST['veh_guide']) ? 1 : 0,
            trim($_POST['veh_notes'] ?? ''),
            (int)($_POST['veh_id'] ?? 0),
        ]);
}

// ── Flotte — données ──────────────────────────────────────────────────────────
try {
    $vehicles = $db1->query("SELECT * FROM nomadrive_vehicules ORDER BY actif DESC, marque, modele, immatriculation")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $vehicles = [];
}

$activeVehicles = count(array_filter($vehicles, fn($v) => $v['actif'] == 1));
$fleetCap       = max(0, ($activeVehicles - 1) * 2);

try {
    $fleetCalRaw = $db1->query("
        SELECT
            DATE(start_datetime)                          AS day,
            DATE_FORMAT(start_datetime, '%H:%i')          AS slot,
            product_name,
            SUM(CEIL(participants / 2) * 2)               AS cnt
        FROM nomadrive_customers
        WHERE booking_status = 'CONFIRMED'
          AND start_datetime IS NOT NULL
          AND start_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(start_datetime), DATE_FORMAT(start_datetime, '%H:%i'), product_name
        ORDER BY day, slot, product_name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $fleetCalRaw = [];
}

// Structure : $calByDay[day][slot][] = ['product_name'=>..., 'cnt'=>...]
$calByDay = [];
foreach ($fleetCalRaw as $row) {
    $calByDay[$row['day']][$row['slot']][] = $row;
}

// Véhicules clients (actifs, hors guide), triés par immatriculation
try {
    $clientVehicles = $db1->query("
        SELECT id, marque, modele, immatriculation, couleur
        FROM nomadrive_vehicules
        WHERE actif = 1 AND guide = 0
        ORDER BY immatriculation
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $clientVehicles = [];
}

// Réservations individuelles des 30 prochains jours pour affectation
try {
    $bookingsRaw = $db1->query("
        SELECT DATE(start_datetime)                 AS day,
               DATE_FORMAT(start_datetime, '%H:%i') AS slot,
               id, first_name, last_name, email, participants, product_name, product_id
        FROM nomadrive_customers
        WHERE booking_status = 'CONFIRMED'
          AND start_datetime IS NOT NULL
          AND start_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)
        ORDER BY day, slot, product_id, id
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $bookingsRaw = [];
}

$bookingsBySlot = [];
foreach ($bookingsRaw as $b) {
    $bookingsBySlot[$b['day']][$b['slot']][] = $b;
}

// Interleave les véhicules par modèle : [Topolino, Ami, Topolino, Ami, ...]
// pour alterner les modèles plutôt que d'épuiser un groupe avant l'autre
function interleaveByModel(array $vehicles): array {
    $groups = [];
    foreach ($vehicles as $v) {
        $groups[$v['modele']][] = $v;
    }
    $result = [];
    $maxLen = max(array_map('count', $groups));
    foreach (array_keys($groups) as $model) {
        sort($groups[$model]); // stable order within each model
    }
    for ($i = 0; $i < $maxLen; $i++) {
        foreach ($groups as $list) {
            if (isset($list[$i])) $result[] = $list[$i];
        }
    }
    return $result;
}

// Affecte les véhicules clients aux réservations d'un créneau
// $startOffset : décalage dans la liste pour varier selon le jour
function assignVehicles(array $bookings, array $vehicles, int $startOffset = 0): array {
    if (empty($vehicles)) return [];
    $n    = count($vehicles);
    $assignments = [];
    $vIdx = $startOffset % $n;
    foreach ($bookings as $b) {
        $pax    = max(1, (int)$b['participants']);
        $groups = (int)ceil($pax / 2);
        $rem    = $pax;
        for ($g = 0; $g < $groups; $g++) {
            $gpax = min(2, $rem);
            $rem -= $gpax;
            $assignments[] = [
                'booking_id'  => $b['id'],
                'email'       => $b['email'] ?? '',
                'first_name'  => $b['first_name'] ?? '',
                'last_name'   => $b['last_name']  ?? '',
                'name'        => trim($b['first_name'] . ' ' . $b['last_name']),
                'product'     => $b['product_name'],
                'total_pax'   => $pax,
                'group_pax'   => $gpax,
                'group_num'   => $g + 1,
                'group_total' => $groups,
                'first_group' => $g === 0,
                'vehicle'     => $vehicles[$vIdx % $n],
            ];
            $vIdx++;
        }
    }
    return $assignments;
}

// ── Données caution — dossiers ouverts ───────────────────────────────────────
try {
    $cautionDossiers = $db1->query("
        SELECT d.id AS dossier_id, d.contrat_id,
               CONCAT('ND-', LPAD(d.contrat_id,5,'0')) AS ref,
               c.nom, c.prenom, c.email, c.date_debut,
               sc.id             AS caution_id,
               sc.status         AS caution_status,
               sc.checkout_url   AS caution_url,
               sc.email_sent_at  AS caution_sent_at
        FROM nomadrive_dossiers d
        JOIN nomadrive_contrats c ON c.id = d.contrat_id
        LEFT JOIN nomadrive_stripe_cautions sc ON sc.contrat_id = d.contrat_id
          AND sc.id = (SELECT MAX(id) FROM nomadrive_stripe_cautions WHERE contrat_id = d.contrat_id)
        WHERE d.statut = 'ouvert'
        ORDER BY c.date_debut ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $cautionDossiers = []; }

// Index cautions par email pour lookup rapide dans le planning
$cautionByEmail = [];
foreach ($cautionDossiers as $cd) {
    if ($cd['email']) $cautionByEmail[strtolower($cd['email'])] = $cd;
}

// ── Vue planning standalone (GET ?view=planning) ──────────────────────────────
if (($_GET['view'] ?? '') === 'planning') {
    $interleavedVehs = interleaveByModel($clientVehicles);
    $vehColors       = ['#6366f1','#0ea5e9','#10b981','#f59e0b','#ec4899','#f97316','#8b5cf6','#14b8a6'];
    $vehColorMap     = [];
    foreach ($interleavedVehs as $ci => $cv) {
        $vehColorMap[$cv['immatriculation']] = $vehColors[$ci % count($vehColors)];
    }
    $daysFrP = ['Monday'=>'Lun','Tuesday'=>'Mar','Wednesday'=>'Mer','Thursday'=>'Jeu','Friday'=>'Ven','Saturday'=>'Sam','Sunday'=>'Dim'];
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>NOMADRIVE — Planning véhicules</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #0f172a; color: #e2e8f0; font-family: system-ui, -apple-system, sans-serif; font-size: 14px; padding: 24px; }
h1 { font-size: 18px; font-weight: 800; color: #fff; letter-spacing: 1px; margin-bottom: 4px; }
.subtitle { font-size: 12px; color: #475569; margin-bottom: 28px; }
.day-block { margin-bottom: 28px; }
.day-header { font-size: 15px; font-weight: 700; color: #f1f5f9; border-bottom: 1px solid #334155; padding-bottom: 8px; margin-bottom: 12px; display: flex; align-items: baseline; gap: 12px; }
.day-header .slot-label { font-family: monospace; font-size: 14px; color: #64748b; font-weight: 400; }
.row { display: flex; align-items: center; gap: 12px; padding: 10px 14px; background: #1e293b; border-radius: 8px; margin-bottom: 6px; border-left: 3px solid var(--c); }
.row-name { flex: 1; font-weight: 600; color: #f1f5f9; }
.row-split { font-size: 10px; color: #64748b; margin-left: 6px; }
.row-tour { font-size: 11px; color: #475569; margin-left: 8px; }
.row-pax { font-size: 12px; color: #64748b; white-space: nowrap; }
.row-veh { display: flex; align-items: center; gap: 6px; white-space: nowrap; }
.row-veh-name { font-weight: 600; }
.row-veh-immat { font-size: 11px; font-family: monospace; color: #64748b; }
.swatch { display: inline-block; width: 10px; height: 10px; border-radius: 50%; border: 1px solid #334155; }
.empty { color: #475569; font-size: 13px; }
</style>
</head>
<body>
<h1>NOMADRIVE — Planning véhicules</h1>
<p class="subtitle">Affectation proposée · mise à jour à chaque rechargement · <?= date('d/m/Y H:i') ?></p>
<?php if (empty($bookingsBySlot)): ?>
    <p class="empty">Aucune réservation confirmée sur les 30 prochains jours.</p>
<?php else: ?>
    <?php foreach ($bookingsBySlot as $day => $slots):
        $dtP      = new DateTime($day);
        $isToday  = $day === date('Y-m-d');
        $dayLabel = $isToday ? "Aujourd'hui" : ($daysFrP[$dtP->format('l')] . ' ' . $dtP->format('d/m'));
        foreach ($slots as $slot => $bookings):
            $offset      = abs(crc32($day)) % max(1, count($interleavedVehs));
            $assignments = assignVehicles($bookings, $interleavedVehs, $offset);
            if (empty($assignments)) continue;
    ?>
    <div class="day-block">
        <div class="day-header">
            <span style="color:<?= $isToday ? '#a5b4fc' : '#f1f5f9' ?>"><?= $dayLabel ?></span>
            <span class="slot-label"><?= $slot ?></span>
        </div>
        <?php foreach ($assignments as $a):
            $veh   = $a['vehicle'];
            $color = $vehColorMap[$veh['immatriculation']] ?? '#64748b';
            $split = $a['group_total'] > 1 ? '<span class="row-split">(' . $a['group_num'] . '/' . $a['group_total'] . ')</span>' : '';
        ?>
        <div class="row" style="--c:<?= $color ?>">
            <div class="row-name"><?= htmlspecialchars($a['name']) ?><?= $split ?><span class="row-tour"><?= htmlspecialchars($a['product']) ?></span></div>
            <div class="row-pax"><?= $a['group_pax'] ?> pax</div>
            <div class="row-veh">
                <?php if ($veh['couleur']): ?><span class="swatch" style="background:<?= htmlspecialchars($veh['couleur']) ?>"></span><?php endif; ?>
                <span class="row-veh-name" style="color:<?= $color ?>"><?= htmlspecialchars($veh['marque'] . ' ' . $veh['modele']) ?></span>
                <span class="row-veh-immat"><?= htmlspecialchars($veh['immatriculation']) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; endforeach; ?>
<?php endif; ?>
</body>
</html>
<?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>NOMADRIVE — Gestion</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #0f172a; color: #e2e8f0; font-family: system-ui, -apple-system, sans-serif; font-size: 14px; }
a { color: inherit; text-decoration: none; }

.topbar { background: #1e293b; border-bottom: 1px solid #334155; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; }
.topbar h1 { font-size: 16px; font-weight: 700; color: #fff; letter-spacing: 1px; }
.topbar-right { display: flex; align-items: center; gap: 16px; }
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; border: none; font-size: 13px; font-weight: 600; cursor: pointer; transition: opacity .15s; }
.btn:hover { opacity: .85; }
.btn-primary { background: #6366f1; color: #fff; }
.btn-danger  { background: #ef4444; color: #fff; }
.btn-ghost   { background: #334155; color: #94a3b8; }

.content { padding: 24px; max-width: 1400px; margin: 0 auto; }

/* Stats */
.stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
.stat { background: #1e293b; border-radius: 12px; padding: 20px; }
.stat-value { font-size: 36px; font-weight: 800; color: #fff; line-height: 1; }
.stat-label { font-size: 12px; color: #64748b; margin-top: 6px; text-transform: uppercase; letter-spacing: .05em; }

/* Sync */
.sync-box { background: #1e293b; border-radius: 12px; padding: 20px; margin-bottom: 24px; }
.sync-box h2 { font-size: 14px; font-weight: 600; color: #fff; margin-bottom: 16px; }
.sync-form { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.sync-form label { font-size: 12px; color: #94a3b8; }
.sync-form input[type=date] { background: #0f172a; border: 1px solid #334155; color: #fff; border-radius: 8px; padding: 8px 12px; font-size: 13px; }
.sync-result { margin-top: 14px; padding: 12px 16px; border-radius: 8px; font-size: 13px; }
.sync-result.ok    { background: #14532d33; color: #4ade80; border: 1px solid #16a34a44; }
.sync-result.error { background: #7f1d1d33; color: #f87171; border: 1px solid #dc262644; }

/* Filters */
.filters { display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; align-items: center; }
.search-input { flex: 1; min-width: 200px; background: #1e293b; border: 1px solid #334155; color: #fff; border-radius: 8px; padding: 8px 14px; font-size: 13px; outline: none; }
.search-input:focus { border-color: #6366f1; }
.filter-btn { padding: 7px 14px; border-radius: 8px; border: 1px solid #334155; background: transparent; color: #94a3b8; font-size: 12px; cursor: pointer; }
.filter-btn.active { background: #6366f1; color: #fff; border-color: #6366f1; }

/* Table */
.table-wrap { background: #1e293b; border-radius: 12px; overflow: hidden; }
table { width: 100%; border-collapse: collapse; }
thead th { background: #0f172a; padding: 12px 16px; text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: #64748b; }
tbody tr { border-top: 1px solid #1e293b; transition: background .1s; }
tbody tr:hover { background: #1e293b99; }
tbody td { padding: 11px 16px; color: #cbd5e1; vertical-align: middle; }
.badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
.badge-confirmed { background: #14532d33; color: #4ade80; }
.badge-cancelled { background: #7f1d1d33; color: #f87171; }
.badge-reserved  { background: #1e3a5f33; color: #60a5fa; }
.badge-default   { background: #334155;   color: #94a3b8; }
.text-muted { color: #475569; }
.text-white { color: #f1f5f9; font-weight: 500; }
.btn-sm { padding: 5px 10px; font-size: 11px; border-radius: 6px; }
.btn-review { background: #6366f1; color: #fff; }
.btn-review-sent { background: #14532d33; color: #4ade80; cursor: default; }
.channel-gyg { display: inline-block; padding: 2px 6px; background: #FF553322; color: #FF5533; border-radius: 4px; font-size: 10px; font-weight: 700; }

/* Module nav */
.module-nav { background: #1e293b; border-bottom: 1px solid #334155; display: flex; padding: 0 24px; gap: 4px; }
.module-tab { padding: 13px 20px; background: none; border: none; color: #64748b; font-size: 13px; font-weight: 600; cursor: pointer; border-bottom: 3px solid transparent; transition: color .15s, border-color .15s; letter-spacing: .02em; }
.module-tab:hover { color: #cbd5e1; }
.module-tab.active { color: #a5b4fc; border-bottom-color: #6366f1; }

/* Fleet */
.fleet-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; margin-bottom: 24px; }
.veh-card { background: #1e293b; border-radius: 12px; padding: 20px; position: relative; border: 1px solid #334155; }
.veh-card.maintenance { border-color: #f59e0b44; }
.veh-card.retired { opacity: .5; }
.veh-card-name { font-size: 16px; font-weight: 700; color: #f1f5f9; margin-bottom: 4px; }
.veh-card-model { font-size: 12px; color: #64748b; margin-bottom: 12px; }
.veh-card-meta { font-size: 12px; color: #94a3b8; margin-bottom: 6px; }
.badge-active      { background: #14532d33; color: #4ade80; }
.badge-maintenance { background: #78350f33; color: #fbbf24; }
.badge-retired     { background: #1e293b;   color: #475569; }
.veh-form { margin-top: 14px; border-top: 1px solid #334155; padding-top: 14px; display: flex; flex-direction: column; gap: 8px; }
.veh-form select, .veh-form textarea { background: #0f172a; border: 1px solid #334155; color: #e2e8f0; border-radius: 8px; padding: 8px 10px; font-size: 12px; resize: vertical; }
.veh-form textarea { min-height: 50px; }

/* Calendar */
.cal-section { background: #1e293b; border-radius: 12px; padding: 20px; margin-bottom: 24px; overflow: hidden; }
.cal-section h2 { font-size: 14px; font-weight: 600; color: #fff; margin-bottom: 16px; }

/* Add vehicle form */
.add-veh-form { background: #1e293b; border-radius: 12px; padding: 20px; margin-bottom: 24px; }
.add-veh-form h2 { font-size: 14px; font-weight: 600; color: #fff; margin-bottom: 16px; }
.form-row { display: flex; gap: 12px; flex-wrap: wrap; }
.form-field { display: flex; flex-direction: column; gap: 4px; flex: 1; min-width: 140px; }
.form-field label { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: .05em; }
.form-field input, .form-field select { background: #0f172a; border: 1px solid #334155; color: #e2e8f0; border-radius: 8px; padding: 9px 12px; font-size: 13px; outline: none; }
.form-field input:focus, .form-field select:focus { border-color: #6366f1; }
</style>
</head>
<body>

<div class="topbar">
    <h1>NOMADRIVE · GESTION</h1>
    <div class="topbar-right">
        <span style="color:#64748b;font-size:12px"><?= date('d/m/Y H:i') ?></span>
        <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="logout">
            <button type="submit" class="btn btn-ghost">Déconnexion</button>
        </form>
    </div>
</div>

<div class="module-nav">
    <button class="module-tab active" id="tab-btn-avis" onclick="switchTab('avis')">Avis clients</button>
    <button class="module-tab" id="tab-btn-flotte" onclick="switchTab('flotte')">Parc de véhicules</button>
    <button class="module-tab" id="tab-btn-planning" onclick="switchTab('planning')">Planning affectation</button>
    <button class="module-tab" id="tab-btn-api" onclick="switchTab('api')">API Bokun</button>
    <a href="?view=planning" target="_blank" style="margin-left:auto;align-self:center;padding:6px 14px;border-radius:8px;background:#1e3a5f;color:#60a5fa;font-size:12px;font-weight:600;text-decoration:none">Ouvrir vue locale &rarr;</a>
</div>

<div class="content">
<div id="tab-avis" class="tab-pane">

    <!-- Stats -->
    <div class="stats">
        <div class="stat">
            <div class="stat-value"><?= $stats['total'] ?? 0 ?></div>
            <div class="stat-label">Clients total</div>
        </div>
        <div class="stat">
            <div class="stat-value"><?= $stats['confirmed'] ?? 0 ?></div>
            <div class="stat-label">Confirmés</div>
        </div>
        <div class="stat">
            <div class="stat-value"><?= $stats['with_email'] ?? 0 ?></div>
            <div class="stat-label">Avec email</div>
        </div>
        <div class="stat">
            <div class="stat-value"><?= $stats['review_sent'] ?? 0 ?></div>
            <div class="stat-label">Demandes avis envoyées</div>
        </div>
    </div>


    <!-- File d'attente automatique -->
    <div class="sync-box">
        <h2>File d'envoi automatique — 48h <span style="font-weight:400;color:#64748b;font-size:12px">(confirmés · sans avis demandé)</span></h2>
        <?php if (empty($autoQueue)): ?>
            <p style="color:#475569;font-size:13px">Aucun client éligible sur les 2 derniers jours.</p>
        <?php else: ?>
            <p style="color:#94a3b8;font-size:12px;margin-bottom:14px"><?= count($autoQueue) ?> client(s) recevraient un email 1h après la fin de leur tour.</p>
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="text-align:left;font-size:11px;color:#64748b;padding:6px 12px;text-transform:uppercase;letter-spacing:.05em;background:#0f172a;">Client</th>
                        <th style="text-align:left;font-size:11px;color:#64748b;padding:6px 12px;text-transform:uppercase;letter-spacing:.05em;background:#0f172a;">Tour</th>
                        <th style="text-align:left;font-size:11px;color:#64748b;padding:6px 12px;text-transform:uppercase;letter-spacing:.05em;background:#0f172a;">Fin du tour</th>
                        <th style="text-align:left;font-size:11px;color:#64748b;padding:6px 12px;text-transform:uppercase;letter-spacing:.05em;background:#0f172a;">Envoi prévu</th>
                        <th style="text-align:left;font-size:11px;color:#64748b;padding:6px 12px;text-transform:uppercase;letter-spacing:.05em;background:#0f172a;">Email</th>
                        <th style="text-align:left;font-size:11px;color:#64748b;padding:6px 12px;text-transform:uppercase;letter-spacing:.05em;background:#0f172a;">Canal</th>
                        <th style="text-align:left;font-size:11px;color:#64748b;padding:6px 12px;text-transform:uppercase;letter-spacing:.05em;background:#0f172a;">Boutons</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($autoQueue as $q): ?>
                <?php $qIsGyg = stripos($q['channel'] ?? '', 'getyourguide') !== false; ?>
                <tr style="border-top:1px solid #334155;">
                    <td style="padding:9px 12px;color:#f1f5f9;font-weight:500;"><?= htmlspecialchars(trim($q['first_name'] . ' ' . $q['last_name'])) ?></td>
                    <td style="padding:9px 12px;color:#cbd5e1;"><?= htmlspecialchars($q['product_name'] ?? '—') ?></td>
                    <td style="padding:9px 12px;color:#cbd5e1;"><?= $q['end_datetime'] ? date('d/m/Y H:i', strtotime($q['end_datetime'])) : '—' ?></td>
                    <td style="padding:9px 12px;color:#4ade80;font-size:12px;font-weight:600;"><?= $q['send_at'] ? date('d/m/Y H:i', strtotime($q['send_at'])) : '—' ?></td>
                    <td style="padding:9px 12px;color:#94a3b8;font-size:12px;"><?= $q['email'] ? htmlspecialchars($q['email']) : '<span style="color:#475569">—</span>' ?></td>
                    <td style="padding:9px 12px;"><?php if ($qIsGyg): ?><span class="channel-gyg">GYG</span><?php else: ?><span style="color:#475569;font-size:11px">Direct / Autre</span><?php endif; ?></td>
                    <td style="padding:9px 12px;font-size:12px;color:#94a3b8;">Google<?= $qIsGyg ? ' + GYG' : '' ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Sync Bokun -->
    <div class="sync-box">
        <h2>Synchronisation Bokun</h2>
        <form method="POST" class="sync-form">
            <div>
                <label>Du</label><br>
                <input type="date" name="date_from" value="<?= htmlspecialchars($_POST['date_from'] ?? '2026-04-01') ?>">
            </div>
            <div>
                <label>Au</label><br>
                <input type="date" name="date_to" value="<?= htmlspecialchars($_POST['date_to'] ?? '2026-11-01') ?>">
            </div>
            <div style="margin-top:18px">
                <button name="sync_bokun" class="btn btn-primary">Synchroniser</button>
            </div>
        </form>
        <?php if ($syncResult !== null): ?>
        <div class="sync-result <?= empty($syncResult['errors']) ? 'ok' : 'error' ?>">
            <?php if (!empty($syncResult['errors'])): ?>
                <?php foreach ($syncResult['errors'] as $e): ?>
                    <div><?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>
            <?php else: ?>
                <?= $syncResult['inserted'] ?> réservation(s) synchronisée(s) — totalHits Bokun : <?= $syncResult['total_hits'] ?>.
                <?php if (!empty($syncResult['debug'])): ?>
                <details style="margin-top:10px;cursor:pointer" open>
                    <summary style="font-size:11px;color:#64748b">Structure brute Bokun (1er enregistrement)</summary>
                    <pre style="margin-top:8px;font-size:10px;overflow:auto;max-height:400px"><?= htmlspecialchars(json_encode($syncResult['debug'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                </details>
                <?php endif; ?>
                <?php if ($syncResult['inserted'] === 0 && !empty($syncResult['rawResp'])): ?>
                <details style="margin-top:10px;cursor:pointer" open>
                    <summary style="font-size:11px;color:#f59e0b">Réponse brute API (debug 0 résultats)</summary>
                    <pre style="margin-top:8px;font-size:10px;overflow:auto;max-height:400px"><?= htmlspecialchars(json_encode($syncResult['rawResp'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                </details>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Filtres + table -->
    <form method="GET" class="filters">
        <input type="text" name="q" class="search-input" placeholder="Rechercher nom, email, code..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" name="status" value="" class="filter-btn <?= !$status ? 'active' : '' ?>">Tous</button>
        <button type="submit" name="status" value="CONFIRMED" class="filter-btn <?= $status === 'CONFIRMED' ? 'active' : '' ?>">Confirmés</button>
        <button type="submit" name="status" value="CANCELLED" class="filter-btn <?= $status === 'CANCELLED' ? 'active' : '' ?>">Annulés</button>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Email</th>
                    <th>Téléphone</th>
                    <th>Tour</th>
                    <th>Date du tour</th>
                    <th>Statut</th>
                    <th>Code / Canal</th>
                    <th>Avis demandé</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($customers)): ?>
                <tr><td colspan="9" style="text-align:center;padding:40px;color:#475569">
                    Aucun client — lancez une synchronisation Bokun.
                </td></tr>
            <?php else: ?>
                <?php foreach ($customers as $c): ?>
                <?php $isGyg = stripos($c['channel'] ?? '', 'getyourguide') !== false; ?>
                <tr>
                    <td class="text-white"><?= htmlspecialchars(trim($c['first_name'] . ' ' . $c['last_name'])) ?></td>
                    <td><?= $c['email'] ? htmlspecialchars($c['email']) : '<span class="text-muted">—</span>' ?></td>
                    <td><?= $c['phone'] ? htmlspecialchars(($c['phone_country_code'] ? '+' . $c['phone_country_code'] . ' ' : '') . $c['phone']) : '<span class="text-muted">—</span>' ?></td>
                    <td><?= $c['product_name'] ? htmlspecialchars($c['product_name']) : '<span class="text-muted">—</span>' ?></td>
                    <td><?= $c['activity_date'] ? date('d/m/Y', strtotime($c['activity_date'])) : '<span class="text-muted">—</span>' ?></td>
                    <td>
                        <?php
                        $s = strtoupper($c['booking_status'] ?? '');
                        $cls = match($s) {
                            'CONFIRMED' => 'badge-confirmed',
                            'CANCELLED' => 'badge-cancelled',
                            'RESERVED'  => 'badge-reserved',
                            default     => 'badge-default',
                        };
                        ?>
                        <span class="badge <?= $cls ?>"><?= htmlspecialchars($c['booking_status'] ?? '—') ?></span>
                    </td>
                    <td class="text-muted">
                        <?= htmlspecialchars($c['confirmation_code'] ?? '—') ?>
                        <?php if ($isGyg): ?><br><span class="channel-gyg">GYG</span><?php endif; ?>
                    </td>
                    <td><?= $c['review_requested_at'] ? date('d/m/Y', strtotime($c['review_requested_at'])) : '<span class="text-muted">—</span>' ?></td>
                    <td>
                        <?php if ($c['review_requested_at']): ?>
                            <span class="btn btn-sm btn-review-sent">Envoyé</span>
                        <?php else: ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="send_review_customer" value="1">
                                <input type="hidden" name="customer_id" value="<?= (int)$c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-review">Envoyer avis</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div><!-- /tab-avis -->

<!-- ══ MODULE FLOTTE ══════════════════════════════════════════════════════════ -->
<div id="tab-flotte" class="tab-pane" style="display:none">

    <!-- Calendrier 30j -->
    <div class="cal-section">
        <h2>Charge flotte — 30 prochains jours
            <span style="font-weight:400;color:#64748b;font-size:12px;margin-left:8px"><?= $fleetCap ?> places · <?= $activeVehicles ?> véhicule(s) actif(s)</span>
        </h2>
        <?php if (empty($calByDay)): ?>
            <p style="color:#475569;font-size:13px">Aucune réservation confirmée sur les 30 prochains jours avec heure de début. Lancez une synchro Bokun.</p>
        <?php else: ?>
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:#0f172a;">
                    <th style="text-align:left;padding:9px 14px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.06em;white-space:nowrap">Date</th>
                    <th style="text-align:left;padding:9px 14px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.06em">Créneau</th>
                    <th style="text-align:left;padding:9px 14px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.06em">Tours</th>
                    <th style="text-align:center;padding:9px 14px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.06em">Résa</th>
                    <th style="text-align:center;padding:9px 14px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.06em">Dispo</th>
                    <th style="padding:9px 14px;min-width:140px"></th>
                </tr>
            </thead>
            <tbody>
            <?php
            $daysFr    = ['Monday'=>'Lun','Tuesday'=>'Mar','Wednesday'=>'Mer','Thursday'=>'Jeu','Friday'=>'Ven','Saturday'=>'Sam','Sunday'=>'Dim'];
            $palette   = ['#6366f1','#0ea5e9','#f59e0b','#ec4899','#10b981','#f97316'];
            $tourColors = [];
            $colorIdx  = 0;
            foreach ($calByDay as $day => $slots):
                $isToday  = $day === date('Y-m-d');
                $dt       = new DateTime($day);
                $dayLabel = $isToday ? "Aujourd'hui" : ($daysFr[$dt->format('l')] . ' ' . $dt->format('d/m'));
                $rowspan  = count($slots);
                $firstSlot = true;
                foreach ($slots as $slot => $tours):
                    $totalBooked = array_sum(array_column($tours, 'cnt'));
                    $dispo = $fleetCap - $totalBooked;
                    $ratio = $totalBooked / $fleetCap;
                    $dispoColor = $ratio >= 1 ? '#ef4444' : ($ratio >= 0.7 ? '#f59e0b' : '#4ade80');
                    foreach ($tours as $t) {
                        if (!isset($tourColors[$t['product_name']])) {
                            $tourColors[$t['product_name']] = $palette[$colorIdx % count($palette)];
                            $colorIdx++;
                        }
                    }
                    $parts = [];
                    foreach ($tours as $t) {
                        $c = $tourColors[$t['product_name']];
                        $parts[] = '<span style="color:#cbd5e1">' . htmlspecialchars($t['product_name']) . '</span>'
                                 . ' <span style="color:' . $c . ';font-weight:700">(' . $t['cnt'] . ')</span>';
                    }
                    $toursLabel = implode(' <span style="color:#334155;margin:0 3px">·</span> ', $parts);
            ?>
                <tr style="border-top:1px solid #334155;<?= $firstSlot && $isToday ? 'background:#1e3a5f22' : '' ?>">
                    <?php if ($firstSlot): ?>
                    <td rowspan="<?= $rowspan ?>" style="padding:10px 14px;vertical-align:top;padding-top:12px;white-space:nowrap">
                        <span style="font-size:13px;font-weight:700;color:<?= $isToday ? '#a5b4fc' : '#f1f5f9' ?>"><?= $dayLabel ?></span>
                    </td>
                    <?php endif; ?>
                    <td style="padding:10px 14px;font-size:15px;font-weight:700;color:#f1f5f9;font-family:monospace;white-space:nowrap"><?= htmlspecialchars($slot) ?></td>
                    <td style="padding:10px 14px;font-size:13px"><?= $toursLabel ?></td>
                    <td style="padding:10px 14px;text-align:center;font-size:14px;font-weight:700;color:#f1f5f9"><?= $totalBooked ?></td>
                    <td style="padding:10px 14px;text-align:center;font-size:14px;font-weight:700;color:<?= $dispoColor ?>"><?= $dispo ?></td>
                    <td style="padding:10px 14px;min-width:140px">
                        <div style="background:#0f172a;border-radius:4px;height:8px;overflow:hidden;display:flex;gap:1px">
                            <?php foreach ($tours as $t):
                                $w = min(100, round($t['cnt'] / $fleetCap * 100));
                            ?>
                            <div style="height:100%;width:<?= $w ?>%;background:<?= $tourColors[$t['product_name']] ?>;flex-shrink:0"></div>
                            <?php endforeach; ?>
                        </div>
                        <div style="font-size:10px;color:#475569;margin-top:3px;text-align:right"><?= $totalBooked ?>/<?= $fleetCap ?></div>
                    </td>
                </tr>
            <?php $firstSlot = false; endforeach; endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Gestion dispo Bokun -->
    <div class="sync-box" style="margin-bottom:24px">
        <h2>Stock pool Bokun
            <span style="font-weight:400;color:#64748b;font-size:12px;margin-left:8px"><?= count(POOL_VEHICLE_IDS) ?> voitures · pool <?= POOL_ID ?></span>
        </h2>
        <p style="font-size:12px;color:#64748b;margin-bottom:12px">Compare les assignments Bokun (GET) avec les résas en base. Vérifie que chaque date est correctement compensée.</p>
        <?php if ($stockCheckResult !== null): ?>
        <?php if (empty($stockCheckResult)): ?>
            <div class="sync-result ok" style="margin-top:12px">Aucune résa future — pool entièrement libre.</div>
        <?php else: ?>
        <div style="margin-top:12px;overflow:auto">
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr style="background:#0f172a">
                        <th style="text-align:left;padding:6px 12px;font-size:11px;color:#64748b;text-transform:uppercase">Date</th>
                        <th style="text-align:left;padding:6px 12px;font-size:11px;color:#64748b;text-transform:uppercase">Créneau</th>
                        <th style="text-align:center;padding:6px 12px;font-size:11px;color:#64748b;text-transform:uppercase">Pax</th>
                        <th style="text-align:center;padding:6px 12px;font-size:11px;color:#64748b;text-transform:uppercase">Voitures Bokun</th>
                        <th style="text-align:center;padding:6px 12px;font-size:11px;color:#64748b;text-transform:uppercase">Attendues</th>
                        <th style="text-align:center;padding:6px 12px;font-size:11px;color:#64748b;text-transform:uppercase">Libres pool</th>
                        <th style="text-align:left;padding:6px 12px;font-size:11px;color:#64748b;text-transform:uppercase">Statut</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $daysFr = ['Monday'=>'Lun','Tuesday'=>'Mar','Wednesday'=>'Mer','Thursday'=>'Jeu','Friday'=>'Ven','Saturday'=>'Sam','Sunday'=>'Dim'];
                foreach ($stockCheckResult as $d):
                    $dt        = new DateTime($d['date']);
                    $dateLabel = $daysFr[$dt->format('l')] . ' ' . $dt->format('d/m');
                    $totalRows = array_sum(array_map(fn($g) => count($g['slots']), $d['groups']));
                    $firstRow  = true;
                    foreach ($d['groups'] as $gi => $group):
                        $nGroupSlots = count($group['slots']);
                        $libresColor = $group['libres'] <= 1 ? '#f87171' : ($group['libres'] <= 3 ? '#f59e0b' : '#4ade80');
                        foreach ($group['slots'] as $si => $s):
                            $isFirstInDate  = $firstRow;
                            $isFirstInGroup = $si === 0;
                            $borderTop = $isFirstInDate ? '2px solid #475569' : ($isFirstInGroup ? '2px solid #334155' : '1px solid #1e293b');
                            $statusColor = match($s['status']) { 'ok' => '#4ade80', 'auto' => '#60a5fa', 'warning' => '#f87171', default => '#94a3b8' };
                            $statusLabel = match($s['status']) { 'ok' => 'OK', 'auto' => 'Ajusté auto ■', 'warning' => 'Manque voiture', default => $s['status'] };
                            $paxColor    = $s['pax'] >= 8 ? '#f87171' : ($s['pax'] >= 4 ? '#f59e0b' : '#f1f5f9');
                            $firstRow = false;
                ?>
                <tr style="border-top:<?= $borderTop ?>">
                    <?php if ($isFirstInDate): ?>
                    <td rowspan="<?= $totalRows ?>" style="padding:8px 12px;color:#f1f5f9;font-weight:600;white-space:nowrap;vertical-align:top;border-right:1px solid #334155"><?= $dateLabel ?><?php if ($totalRows > 1): ?><div style="font-size:10px;color:#475569;margin-top:2px;font-weight:400"><?= $totalRows ?> créneaux</div><?php endif; ?></td>
                    <?php endif; ?>
                    <td style="padding:8px 12px;font-size:12px;color:#94a3b8;white-space:nowrap"><?= htmlspecialchars($s['label']) ?></td>
                    <td style="padding:8px 12px;text-align:center;font-size:15px;font-weight:700;color:<?= $paxColor ?>"><?= $s['pax'] ?></td>
                    <td style="padding:8px 12px;text-align:center;font-weight:700;color:#f1f5f9"><?= $s['assigned'] ?><?php if ($s['closed'] > 0): ?> <span style="color:#60a5fa;font-size:11px;font-weight:400">+<?= $s['closed'] ?>&#9632;</span><?php endif; ?></td>
                    <td style="padding:8px 12px;text-align:center;color:#64748b"><?= $s['expected'] ?></td>
                    <?php if ($isFirstInGroup): ?>
                    <td rowspan="<?= $nGroupSlots ?>" style="padding:8px 12px;text-align:center;font-weight:700;color:<?= $libresColor ?>;vertical-align:middle;border-left:1px solid #334155"><?= $group['libres'] ?></td>
                    <?php endif; ?>
                    <td style="padding:8px 12px;white-space:nowrap"><span style="font-size:11px;font-weight:600;color:<?= $statusColor ?>"><?= $statusLabel ?></span></td>
                </tr>
                <?php endforeach; endforeach; endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <?php endif; ?>

    </div>

    <!-- Parc de véhicules -->
    <?php if (!empty($vehicles)): ?>
    <div class="sync-box" style="margin-bottom:24px">
        <h2>Parc de véhicules
            <span style="font-weight:400;color:#64748b;font-size:12px;margin-left:8px"><?= $activeVehicles ?>/<?= count($vehicles) ?> disponible(s)</span>
        </h2>
        <div class="fleet-grid">
            <?php foreach ($vehicles as $v): ?>
            <?php $isActive = $v['actif'] == 1; ?>
            <div class="veh-card <?= $isActive ? 'active' : 'maintenance' ?>">
                <div style="position:absolute;top:16px;right:16px;display:flex;align-items:center;gap:8px">
                    <?php if ($v['couleur']): ?>
                    <span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:<?= htmlspecialchars($v['couleur']) ?>;border:2px solid #334155;flex-shrink:0"></span>
                    <?php endif; ?>
                    <?php if (!empty($v['guide'])): ?>
                    <span class="badge" style="background:#78350f33;color:#fbbf24">Guide</span>
                    <?php endif; ?>
                    <span class="badge badge-<?= $isActive ? 'active' : 'maintenance' ?>"><?= $isActive ? 'Disponible' : 'Indispo' ?></span>
                </div>
                <div class="veh-card-name"><?= htmlspecialchars($v['marque'] . ' ' . $v['modele']) ?></div>
                <div class="veh-card-model">Immat. <strong style="color:#f1f5f9"><?= htmlspecialchars($v['immatriculation']) ?></strong></div>
                <?php if (!empty($v['licence_key'])): ?>
                <div class="veh-card-meta" style="font-size:11px;color:#334155;font-family:monospace"><?= htmlspecialchars($v['licence_key']) ?></div>
                <?php endif; ?>
                <?php if (!empty($v['notes'])): ?><div class="veh-card-meta" style="margin-top:8px;font-style:italic;color:#fbbf24"><?= htmlspecialchars($v['notes']) ?></div><?php endif; ?>
                <?php $isGuide = !empty($v['guide']); ?>
                <form method="POST" class="veh-form">
                    <input type="hidden" name="fleet_action" value="update_vehicle">
                    <input type="hidden" name="veh_id" value="<?= (int)$v['id'] ?>">
                    <select name="veh_actif">
                        <option value="1" <?= $isActive ? 'selected' : '' ?>>Disponible</option>
                        <option value="0" <?= !$isActive ? 'selected' : '' ?>>Indisponible</option>
                    </select>
                    <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:#64748b;cursor:pointer">
                        <input type="checkbox" name="veh_guide" <?= $isGuide ? 'checked' : '' ?> style="accent-color:#f59e0b">
                        Voiture guide (exclue de l'affectation)
                    </label>
                    <input type="text" name="veh_notes" placeholder="Raison (maintenance, panne…)" value="<?= htmlspecialchars($v['notes'] ?? '') ?>" style="background:#0f172a;border:1px solid #334155;color:#e2e8f0;border-radius:8px;padding:8px 10px;font-size:12px">
                    <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /tab-flotte -->

<!-- ══ MODULE PLANNING ════════════════════════════════════════════════════════ -->
<div id="tab-planning" class="tab-pane" style="display:none">
    <div class="cal-section">
        <h2>Affectation véhicules — proposition
            <span style="font-weight:400;color:#64748b;font-size:12px;margin-left:8px"><?= count($clientVehicles) ?> voiture(s) disponibles</span>
        </h2>
        <?php
        $daysFrA         = ['Monday'=>'Lun','Tuesday'=>'Mar','Wednesday'=>'Mer','Thursday'=>'Jeu','Friday'=>'Ven','Saturday'=>'Sam','Sunday'=>'Dim'];
        $vehColors       = ['#6366f1','#0ea5e9','#10b981','#f59e0b','#ec4899','#f97316','#8b5cf6','#14b8a6'];
        $interleavedVehs = interleaveByModel($clientVehicles);
        $vehColorMap     = [];
        foreach ($interleavedVehs as $ci => $cv) {
            $vehColorMap[$cv['immatriculation']] = $vehColors[$ci % count($vehColors)];
        }
        if (empty($bookingsBySlot)): ?>
            <p style="color:#475569;font-size:13px">Aucune réservation confirmée sur les 30 prochains jours.</p>
        <?php else:
            foreach ($bookingsBySlot as $day => $slots):
                $dtA       = new DateTime($day);
                $isToday   = $day === date('Y-m-d');
                $dayLabelA = $isToday ? "Aujourd'hui" : ($daysFrA[$dtA->format('l')] . ' ' . $dtA->format('d/m'));
                foreach ($slots as $slot => $bookings):
                    $offset      = abs(crc32($day)) % max(1, count($interleavedVehs));
                    $assignments = assignVehicles($bookings, $interleavedVehs, $offset);
                    if (empty($assignments)) continue;
        ?>
        <div style="margin-bottom:20px">
            <div style="font-size:13px;font-weight:700;color:<?= $isToday ? '#a5b4fc' : '#f1f5f9' ?>;margin-bottom:8px;padding:6px 0;border-bottom:1px solid #334155">
                <?= $dayLabelA ?> &nbsp;<span style="font-family:monospace;color:#94a3b8"><?= $slot ?></span>
            </div>
            <div style="display:flex;flex-direction:column;gap:6px">
            <?php foreach ($assignments as $a):
                $veh        = $a['vehicle'];
                $color      = $vehColorMap[$veh['immatriculation']] ?? '#64748b';
                $splitLabel = $a['group_total'] > 1 ? ' <span style="font-size:10px;color:#64748b">(' . $a['group_num'] . '/' . $a['group_total'] . ')</span>' : '';
            ?>
            <?php
            $caution = isset($a['email']) ? ($cautionByEmail[strtolower($a['email'])] ?? null) : null;
            $cs = $caution['caution_status'] ?? null;
            ?>
            <div style="display:flex;align-items:center;gap:12px;padding:8px 12px;background:#0f172a;border-radius:8px;border-left:3px solid <?= $color ?>">
                <div style="flex:1;min-width:0">
                    <span style="font-weight:600;color:#f1f5f9"><?= htmlspecialchars($a['name']) ?></span><?= $splitLabel ?>
                    <span style="color:#475569;font-size:11px;margin-left:8px"><?= htmlspecialchars($a['product']) ?></span>
                </div>
                <div style="font-size:12px;color:#64748b;white-space:nowrap"><?= $a['group_pax'] ?> pax</div>
                <?php if ($a['first_group']): ?>
                <div style="display:flex;align-items:center;gap:6px;white-space:nowrap">
                    <?php if ($cs === 'authorized'): ?>
                        <span style="font-size:11px;font-weight:600;padding:2px 7px;border-radius:4px;background:#14532d33;color:#4ade80">&#128274; Caution OK</span>
                    <?php elseif ($cs === 'captured'): ?>
                        <span style="font-size:11px;font-weight:600;padding:2px 7px;border-radius:4px;background:#7f1d1d33;color:#f87171">Débitée</span>
                    <?php elseif ($cs === 'pending' && $caution['caution_sent_at']): ?>
                        <span style="font-size:11px;font-weight:600;padding:2px 7px;border-radius:4px;background:#1e3a5f33;color:#60a5fa">Email envoyé</span>
                        <button onclick="manageSendCaution(<?= (int)$caution['contrat_id'] ?>,<?= (int)$caution['caution_id'] ?>,this)" style="background:#1e293b;border:1px solid #334155;color:#94a3b8;border-radius:5px;padding:3px 8px;font-size:10px;cursor:pointer">Renvoyer</button>
                    <?php elseif ($caution): ?>
                        <span style="font-size:11px;font-weight:600;padding:2px 7px;border-radius:4px;background:#334155;color:#94a3b8">Non envoyé</span>
                        <button onclick="manageSendCaution(<?= (int)$caution['contrat_id'] ?>,<?= (int)($caution['caution_id'] ?? 0) ?>,this)" style="background:#6366f1;border:none;color:#fff;border-radius:5px;padding:3px 8px;font-size:10px;cursor:pointer">Envoyer</button>
                    <?php else: ?>
                        <button onclick='createDossier(<?= htmlspecialchars(json_encode([
                            'prenom'      => $a['first_name'],
                            'nom'         => $a['last_name'],
                            'email'       => $a['email'],
                            'vehicule_id' => $a['vehicle']['id'],
                            'vehicule'    => $a['vehicle']['marque'] . ' ' . $a['vehicle']['modele'],
                            'date_debut'  => $day,
                            'heure_debut' => $slot,
                        ]), ENT_QUOTES) ?>,this)'
                            style="background:#334155;border:none;color:#94a3b8;border-radius:5px;padding:3px 8px;font-size:10px;cursor:pointer">
                            Créer dossier
                        </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <div style="display:flex;align-items:center;gap:6px;white-space:nowrap">
                    <?php if ($veh['couleur']): ?>
                    <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= htmlspecialchars($veh['couleur']) ?>;border:1px solid #334155"></span>
                    <?php endif; ?>
                    <span style="font-weight:600;color:<?= $color ?>"><?= htmlspecialchars($veh['marque'] . ' ' . $veh['modele']) ?></span>
                    <span style="font-size:11px;font-family:monospace;color:#64748b"><?= htmlspecialchars($veh['immatriculation']) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; endforeach; endif; ?>
    </div>

    <!-- Caution Stripe — dossiers ouverts -->
    <div class="sync-box" style="margin-top:24px">
        <h2>Caution Stripe — dossiers ouverts
            <span style="font-weight:400;color:#64748b;font-size:12px;margin-left:8px"><?= count($cautionDossiers) ?> dossier(s)</span>
        </h2>
        <?php if (empty($cautionDossiers)): ?>
            <p style="font-size:13px;color:#475569">Aucun dossier ouvert.</p>
        <?php else: ?>
        <div style="overflow-x:auto;margin-top:12px">
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr style="background:#0f172a">
                        <th style="text-align:left;padding:8px 14px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.05em">Réf</th>
                        <th style="text-align:left;padding:8px 14px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.05em">Client</th>
                        <th style="text-align:left;padding:8px 14px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.05em">Début</th>
                        <th style="text-align:left;padding:8px 14px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.05em">Statut</th>
                        <th style="text-align:left;padding:8px 14px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.05em">Email envoyé</th>
                        <th style="padding:8px 14px"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($cautionDossiers as $d):
                    $cs = $d['caution_status'] ?? null;
                    if ($cs === 'authorized')
                        $badge = '<span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;background:#14532d33;color:#4ade80">Autorisée</span>';
                    elseif ($cs === 'captured')
                        $badge = '<span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;background:#7f1d1d33;color:#f87171">Débitée</span>';
                    elseif ($cs === 'canceled')
                        $badge = '<span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;background:#334155;color:#94a3b8">Annulée</span>';
                    elseif ($cs === 'pending')
                        $badge = '<span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;background:#1e3a5f33;color:#60a5fa">En attente</span>';
                    else
                        $badge = '<span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;background:#334155;color:#475569">Non créée</span>';
                ?>
                <tr style="border-top:1px solid #334155">
                    <td style="padding:10px 14px;font-family:monospace;font-size:12px;color:#f1f5f9"><?= htmlspecialchars($d['ref']) ?></td>
                    <td style="padding:10px 14px;font-weight:500;color:#cbd5e1"><?= htmlspecialchars($d['prenom'] . ' ' . $d['nom']) ?></td>
                    <td style="padding:10px 14px;font-size:12px;color:#94a3b8"><?= $d['date_debut'] ? date('d/m/Y', strtotime($d['date_debut'])) : '—' ?></td>
                    <td style="padding:10px 14px"><?= $badge ?></td>
                    <td style="padding:10px 14px;font-size:12px;color:<?= $d['caution_sent_at'] ? '#4ade80' : '#475569' ?>">
                        <?= $d['caution_sent_at'] ? date('d/m/Y H:i', strtotime($d['caution_sent_at'])) : '—' ?>
                    </td>
                    <td style="padding:10px 14px">
                        <?php if (!in_array($cs, ['authorized', 'captured'])): ?>
                        <button class="btn btn-primary btn-sm"
                            onclick="manageSendCaution(<?= (int)$d['contrat_id'] ?>, <?= (int)($d['caution_id'] ?? 0) ?>, this)">
                            <?= $d['caution_id'] ? 'Renvoyer' : 'Envoyer' ?>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div><!-- /tab-planning -->

<!-- ══ MODULE API BOKUN ═══════════════════════════════════════════════════════ -->
<div id="tab-api" class="tab-pane" style="display:none">

    <!-- Réservations sans véhicule affecté -->
    <div class="sync-box" style="margin-bottom:24px">
        <h2>Réservations sans véhicule affecté (Bokun)
            <?php if ($unassignedResult): ?>
            <?php $totalProblems = ($unassignedResult['unassigned'] ?? 0) + ($unassignedResult['underassigned'] ?? 0); ?>
            <span style="font-weight:400;color:<?= $totalProblems > 0 ? '#f87171' : '#4ade80' ?>;font-size:12px;margin-left:8px">
                <?= $unassignedResult['unassigned'] ?> sans affectation
                <?php if (($unassignedResult['underassigned'] ?? 0) > 0): ?>
                · <?= $unassignedResult['underassigned'] ?> sous-affectée(s)
                <?php endif; ?>
                / <?= $unassignedResult['total'] ?> total
            </span>
            <?php endif; ?>
        </h2>
        <form method="POST">
            <button name="load_unassigned" class="btn btn-ghost btn-sm">Charger depuis Bokun</button>
        </form>
        <?php if ($unassignedResult): ?>
        <?php if (empty($unassignedResult['items'])): ?>
            <div class="sync-result ok" style="margin-top:12px">Toutes les réservations futures ont un véhicule assigné.</div>
        <?php else: ?>
        <div style="margin-top:12px;overflow:auto">
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr>
                        <th style="text-align:left;font-size:11px;color:#64748b;padding:6px 12px;text-transform:uppercase;letter-spacing:.05em;background:#0f172a">Date</th>
                        <th style="text-align:left;font-size:11px;color:#64748b;padding:6px 12px;text-transform:uppercase;letter-spacing:.05em;background:#0f172a">Ref</th>
                        <th style="text-align:left;font-size:11px;color:#64748b;padding:6px 12px;text-transform:uppercase;letter-spacing:.05em;background:#0f172a">Client</th>
                        <th style="text-align:left;font-size:11px;color:#64748b;padding:6px 12px;text-transform:uppercase;letter-spacing:.05em;background:#0f172a">Tour</th>
                        <th style="text-align:right;font-size:11px;color:#64748b;padding:6px 12px;text-transform:uppercase;letter-spacing:.05em;background:#0f172a">Pax</th>
                        <th style="text-align:left;font-size:11px;color:#64748b;padding:6px 12px;text-transform:uppercase;letter-spacing:.05em;background:#0f172a">Canal</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($unassignedResult['items'] as $u): ?>
                <tr style="border-top:1px solid #334155">
                    <td style="padding:8px 12px;font-family:monospace;font-size:12px;color:#f1f5f9;white-space:nowrap"><?= htmlspecialchars($u['date']) ?></td>
                    <td style="padding:8px 12px;font-family:monospace;font-size:11px;color:#64748b;white-space:nowrap"><?= htmlspecialchars($u['code']) ?></td>
                    <td style="padding:8px 12px;font-size:13px;color:#cbd5e1;font-weight:500"><?= htmlspecialchars($u['name']) ?></td>
                    <td style="padding:8px 12px;font-size:12px;color:#94a3b8"><?= htmlspecialchars($u['product']) ?></td>
                    <td style="padding:8px 12px;font-size:13px;color:#f1f5f9;text-align:right;font-weight:600"><?= (int)$u['pax'] ?></td>
                    <td style="padding:8px 12px;font-size:11px">
                        <?php if (stripos($u['channel'], 'getyourguide') !== false): ?>
                            <span class="channel-gyg">GYG</span>
                        <?php else: ?>
                            <span style="color:#475569"><?= htmlspecialchars($u['channel']) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($unassignedResult && !empty($unassignedResult['under_items'])): ?>
        <div style="margin-top:20px;border-top:1px solid #334155;padding-top:16px">
            <div style="font-size:13px;font-weight:600;color:#f59e0b;margin-bottom:8px">
                Sous-affectées — véhicules insuffisants
                <span style="font-weight:400;font-size:11px;color:#64748b;margin-left:8px">(certains pax n'ont pas de voiture → pool mal décompté pour l'autre tour)</span>
            </div>
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr>
                        <th style="text-align:left;font-size:11px;color:#64748b;padding:6px 12px;text-transform:uppercase;letter-spacing:.05em;background:#0f172a">Date</th>
                        <th style="text-align:left;font-size:11px;color:#64748b;padding:6px 12px;text-transform:uppercase;letter-spacing:.05em;background:#0f172a">Ref</th>
                        <th style="text-align:left;font-size:11px;color:#64748b;padding:6px 12px;text-transform:uppercase;letter-spacing:.05em;background:#0f172a">Client</th>
                        <th style="text-align:left;font-size:11px;color:#64748b;padding:6px 12px;text-transform:uppercase;letter-spacing:.05em;background:#0f172a">Tour</th>
                        <th style="text-align:right;font-size:11px;color:#64748b;padding:6px 12px;text-transform:uppercase;letter-spacing:.05em;background:#0f172a">Pax</th>
                        <th style="text-align:right;font-size:11px;color:#64748b;padding:6px 12px;text-transform:uppercase;letter-spacing:.05em;background:#0f172a">Assignés</th>
                        <th style="text-align:left;font-size:11px;color:#64748b;padding:6px 12px;text-transform:uppercase;letter-spacing:.05em;background:#0f172a">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($unassignedResult['under_items'] as $u): ?>
                <tr style="border-top:1px solid #334155">
                    <td style="padding:8px 12px;font-family:monospace;font-size:12px;color:#f1f5f9;white-space:nowrap"><?= htmlspecialchars($u['date']) ?></td>
                    <td style="padding:8px 12px;font-family:monospace;font-size:11px;color:#64748b;white-space:nowrap"><?= htmlspecialchars($u['code']) ?></td>
                    <td style="padding:8px 12px;font-size:13px;color:#cbd5e1;font-weight:500"><?= htmlspecialchars($u['name']) ?></td>
                    <td style="padding:8px 12px;font-size:12px;color:#94a3b8"><?= htmlspecialchars($u['product']) ?></td>
                    <td style="padding:8px 12px;font-size:13px;color:#f1f5f9;text-align:right;font-weight:600"><?= (int)$u['pax'] ?></td>
                    <td style="padding:8px 12px;text-align:right;font-size:12px;color:#f59e0b;font-weight:600"><?= $u['assigned_count'] ?>/<?= $u['vehicles_needed'] ?> voiture(s)</td>
                    <td style="padding:8px 12px;font-size:11px;color:#64748b">Splitter en <?= $u['vehicles_needed'] ?> résas de 2 dans Bokun</td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>

    <!-- Audit affectation véhicules -->
    <div class="sync-box" style="margin-bottom:24px">
        <h2>Audit affectation véhicules
            <?php if ($resourceAuditResult): ?>
            <span style="font-weight:400;color:<?= empty($resourceAuditResult['issues']) ? '#4ade80' : '#f87171' ?>;font-size:12px;margin-left:8px">
                <?= count($resourceAuditResult['issues']) ?> problème(s) sur <?= $resourceAuditResult['dates'] ?> date(s)
            </span>
            <?php endif; ?>
        </h2>
        <p style="font-size:12px;color:#64748b;margin-bottom:12px">Compare les assignments Bokun (v2) avec les résas en base. Détecte les résas sans voiture et les groupes &gt;2 pax avec une seule voiture.</p>
        <form method="POST" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <button name="audit_resources" class="btn btn-primary btn-sm">Lancer l'audit</button>
            <button name="release_closeouts" class="btn btn-sm" style="background:#1e3a5f;color:#60a5fa;border:1px solid #1e40af;font-size:11px">
                Vérifier annulations
            </button>
            <?php if (!empty($auditIgnored)): ?>
            <button name="audit_ignore_reset" class="btn btn-sm" style="background:transparent;border:1px solid #334155;color:#64748b;font-size:11px">
                Réafficher les <?= count($auditIgnored) ?> ignoré(s)
            </button>
            <?php endif; ?>
        </form>

        <?php if ($releaseResult !== null): ?>
        <div style="margin-top:12px;padding:10px 14px;border-radius:8px;background:#0f172a;border:1px solid #334155">
            <?php if (empty($releaseResult['released']) && empty($releaseResult['errors'])): ?>
                <div style="font-size:12px;color:#4ade80">Aucune résa annulée — tous les closeouts sont maintenus (<?= count($releaseResult['kept']) ?> confirmée(s)).</div>
            <?php endif; ?>
            <?php foreach ($releaseResult['released'] as $r): ?>
                <div style="font-size:12px;color:#4ade80;margin-bottom:2px">&#10003; Libéré : <?= htmlspecialchars($r) ?></div>
            <?php endforeach; ?>
            <?php foreach ($releaseResult['kept'] as $k): ?>
                <div style="font-size:12px;color:#64748b;margin-bottom:2px">&#8212; Maintenu : <?= htmlspecialchars($k) ?> (encore confirmée)</div>
            <?php endforeach; ?>
            <?php foreach ($releaseResult['errors'] as $e): ?>
                <div style="font-size:12px;color:#f87171;margin-bottom:2px">&#10007; <?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($fixResult): ?>
        <div style="margin-top:12px;padding:10px 14px;border-radius:8px;background:<?= empty($fixResult['errors']) ? '#052e16' : '#1c0a09' ?>;border:1px solid <?= empty($fixResult['errors']) ? '#166534' : '#7f1d1d' ?>">
            <?php foreach ($fixResult['actions'] as $a): ?>
            <div style="font-size:12px;color:#4ade80;margin-bottom:2px">&#10003; <?= htmlspecialchars($a) ?></div>
            <?php endforeach; ?>
            <?php foreach ($fixResult['errors'] as $e): ?>
            <div style="font-size:12px;color:#f87171;margin-bottom:2px">&#10007; <?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($resourceAuditResult): ?>
        <?php if (empty($resourceAuditResult['issues'])): ?>
            <div class="sync-result ok" style="margin-top:12px">Toutes les résas ont le bon nombre de véhicules.</div>
        <?php else: ?>
        <div style="margin-top:12px;overflow:auto">
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr>
                        <th style="text-align:left;font-size:11px;color:#64748b;padding:6px 12px;text-transform:uppercase;letter-spacing:.05em;background:#0f172a">Date</th>
                        <th style="text-align:left;font-size:11px;color:#64748b;padding:6px 12px;text-transform:uppercase;letter-spacing:.05em;background:#0f172a">Ref</th>
                        <th style="text-align:left;font-size:11px;color:#64748b;padding:6px 12px;text-transform:uppercase;letter-spacing:.05em;background:#0f172a">Client</th>
                        <th style="text-align:left;font-size:11px;color:#64748b;padding:6px 12px;text-transform:uppercase;letter-spacing:.05em;background:#0f172a">Tour</th>
                        <th style="text-align:right;font-size:11px;color:#64748b;padding:6px 12px;text-transform:uppercase;letter-spacing:.05em;background:#0f172a">Pax</th>
                        <th style="text-align:center;font-size:11px;color:#64748b;padding:6px 12px;text-transform:uppercase;letter-spacing:.05em;background:#0f172a">Voitures</th>
                        <th style="text-align:left;font-size:11px;color:#64748b;padding:6px 12px;text-transform:uppercase;letter-spacing:.05em;background:#0f172a">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($resourceAuditResult['issues'] as $u): ?>
                <?php $isOvercap = ($u['type'] ?? '') === 'overcapacity'; ?>
                <tr style="border-top:1px solid #334155">
                    <td style="padding:8px 12px;font-family:monospace;font-size:12px;color:#f1f5f9;white-space:nowrap"><?= htmlspecialchars($u['date']) ?> <span style="color:#64748b"><?= htmlspecialchars($u['slot'] ?? '') ?></span></td>
                    <td style="padding:8px 12px;font-family:monospace;font-size:11px;color:#64748b;white-space:nowrap"><?= htmlspecialchars($u['code']) ?></td>
                    <td style="padding:8px 12px;font-size:13px;color:#cbd5e1;font-weight:500"><?= htmlspecialchars($u['name']) ?></td>
                    <td style="padding:8px 12px;font-size:12px;color:#94a3b8"><?= htmlspecialchars($u['product']) ?></td>
                    <td style="padding:8px 12px;font-size:13px;color:#f1f5f9;text-align:right;font-weight:600"><?= $u['pax'] ?></td>
                    <td style="padding:8px 12px;text-align:center;font-size:12px;font-weight:700;color:<?= $isOvercap ? '#f59e0b' : '#f87171' ?>">
                        <?= $u['assigned'] ?>/<?= $u['needed'] ?>
                    </td>
                    <td style="padding:6px 12px">
                        <div style="display:flex;align-items:center;gap:6px">
                        <form method="POST" style="display:contents">
                            <input type="hidden" name="audit_resources"  value="1">
                            <input type="hidden" name="fix_booking_id"   value="<?= htmlspecialchars($u['booking_id'] ?? '') ?>">
                            <input type="hidden" name="fix_date"         value="<?= htmlspecialchars($u['date']) ?>">
                            <input type="hidden" name="fix_type"         value="<?= htmlspecialchars($u['type']) ?>">
                            <input type="hidden" name="fix_needed"       value="<?= (int)$u['needed'] ?>">
                            <input type="hidden" name="fix_assigned"     value="<?= (int)$u['assigned'] ?>">
                            <button type="submit" class="btn btn-sm" style="background:<?= $isOvercap ? '#78350f' : '#1e3a5f' ?>;color:<?= $isOvercap ? '#fbbf24' : '#60a5fa' ?>;border:1px solid <?= $isOvercap ? '#92400e' : '#1e40af' ?>;font-size:11px;padding:4px 10px;white-space:nowrap">
                                <?= $isOvercap ? 'Compenser pool' : 'Assigner voiture' ?>
                            </button>
                        </form>
                        <form method="POST" style="display:contents">
                            <input type="hidden" name="audit_resources" value="1">
                            <input type="hidden" name="audit_ignore"    value="<?= htmlspecialchars($u['booking_id'] ?? '') ?>">
                            <button type="submit" class="btn btn-sm" style="background:transparent;border:1px solid #334155;color:#475569;font-size:11px;padding:4px 8px" title="Masquer cette ligne">&#10006;</button>
                        </form>
                        <?php if ($isOvercap): ?>
                        <span style="font-size:10px;color:#475569">+ splitter dans Bokun</span>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($resourceAuditResult['debug'])): ?>
        <details style="margin-top:12px">
            <summary style="font-size:11px;color:#475569;cursor:pointer">Debug — structure réponse API assignments</summary>
            <pre style="font-size:10px;overflow:auto;max-height:300px;background:#0f172a;padding:10px;border-radius:6px;color:#94a3b8;margin-top:6px"><?= htmlspecialchars(json_encode($resourceAuditResult['debug'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
        </details>
        <?php endif; ?>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="sync-box">
        <h2>Testeur API Bokun <span style="font-weight:400;color:#64748b;font-size:12px;margin-left:8px">v1 &amp; v2</span></h2>
        <form method="POST" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start">
            <select name="free_method" style="background:#0f172a;border:1px solid #334155;color:#e2e8f0;border-radius:8px;padding:9px 10px;font-size:13px;font-family:monospace">
                <option <?= ($_POST['free_method'] ?? '') === 'GET'    ? 'selected' : '' ?>>GET</option>
                <option <?= ($_POST['free_method'] ?? '') === 'POST'   ? 'selected' : '' ?>>POST</option>
                <option <?= ($_POST['free_method'] ?? '') === 'PUT'    ? 'selected' : '' ?>>PUT</option>
                <option <?= ($_POST['free_method'] ?? '') === 'DELETE' ? 'selected' : '' ?>>DELETE</option>
            </select>
            <input type="text" name="free_path"
                   placeholder="/restapi/v2.0/startTime/4940234/allocations"
                   value="<?= htmlspecialchars($_POST['free_path'] ?? '') ?>"
                   style="flex:1;min-width:360px;background:#0f172a;border:1px solid #334155;color:#e2e8f0;border-radius:8px;padding:9px 12px;font-size:13px;font-family:monospace;outline:none">
            <button name="bokun_free" class="btn btn-primary">Envoyer</button>
            <textarea name="free_body" placeholder="Body JSON pour POST/PUT — laisser vide pour GET"
                      style="width:100%;background:#0f172a;border:1px solid #334155;color:#e2e8f0;border-radius:8px;padding:9px 12px;font-size:12px;font-family:monospace;min-height:80px;resize:vertical;outline:none"><?= htmlspecialchars($_POST['free_body'] ?? '') ?></textarea>
        </form>

        <?php if ($bokunFreeResult): ?>
        <div style="margin-top:16px">
            <div style="font-size:12px;font-family:monospace;font-weight:600;color:<?= in_array($bokunFreeResult['code'], [200,201,204]) ? '#4ade80' : '#f87171' ?>;margin-bottom:8px">
                HTTP <?= $bokunFreeResult['code'] ?> — <?= htmlspecialchars($bokunFreeResult['path']) ?>
            </div>
            <?php if (!empty($bokunFreeResult['body_sent'])): ?>
            <div style="font-size:11px;color:#64748b;margin-bottom:4px">Body envoyé :</div>
            <pre style="font-size:10px;overflow:auto;max-height:120px;background:#0f172a;padding:10px;border-radius:6px;color:#64748b;margin-bottom:8px"><?= htmlspecialchars(json_encode($bokunFreeResult['body_sent'], JSON_PRETTY_PRINT)) ?></pre>
            <?php endif; ?>
            <div style="font-size:11px;color:#64748b;margin-bottom:4px">Réponse :</div>
            <pre style="font-size:11px;overflow:auto;max-height:500px;background:#0f172a;padding:12px;border-radius:8px;color:#94a3b8"><?= htmlspecialchars(json_encode($bokunFreeResult['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: $bokunFreeResult['raw']) ?></pre>
        </div>
        <?php endif; ?>

        <div style="margin-top:20px;border-top:1px solid #334155;padding-top:16px">
            <div style="font-size:11px;color:#475569;margin-bottom:8px;font-weight:600">RACCOURCIS V2</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <?php
                $shortcuts = [
                    ['GET', '/restapi/v2.0/allocations?pageNo=0&pageSize=10', ''],
                    ['GET', '/restapi/v2.0/startTime/' . TOUR2_START_TIME_IDS['10:00'] . '/allocations', ''],
                    ['GET', '/restapi/v2.0/startTime/' . TOUR2_START_TIME_IDS['14:00'] . '/allocations', ''],
                    ['GET', '/restapi/v2.0/startTime/' . TOUR1_START_TIME_IDS['10:00'] . '/allocations', ''],
                    ['GET', '/restapi/v2.0/startTime/' . TOUR1_START_TIME_IDS['14:00'] . '/allocations', ''],
                ];
                foreach ($shortcuts as [$m, $p, $b]): ?>
                <form method="POST" style="display:inline">
                    <input type="hidden" name="free_method" value="<?= $m ?>">
                    <input type="hidden" name="free_path"   value="<?= htmlspecialchars($p) ?>">
                    <input type="hidden" name="free_body"   value="<?= htmlspecialchars($b) ?>">
                    <button name="bokun_free" style="background:#1e293b;border:1px solid #334155;color:#94a3b8;border-radius:6px;padding:5px 10px;font-size:10px;font-family:monospace;cursor:pointer"><?= $m ?> <?= htmlspecialchars($p) ?></button>
                </form>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div><!-- /tab-api -->

</div><!-- /content -->

<script>
async function createDossier(data, btn) {
    if (!confirm('Créer un dossier pour ' + data.prenom + ' ' + data.nom + ' ?')) return;
    btn.disabled = true; btn.textContent = '...';
    try {
        const body = Object.entries(data).map(([k,v]) => encodeURIComponent(k) + '=' + encodeURIComponent(v)).join('&') + '&action=create_dossier';
        const r = await fetch('stripe_caution.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body});
        const d = await r.json();
        if (d.success) { location.reload(); } else { alert('Erreur : ' + (d.message || '')); btn.disabled = false; btn.textContent = 'Créer dossier'; }
    } catch(e) { alert('Erreur réseau'); btn.disabled = false; }
}

async function manageSendCaution(contratId, cautionId, btn) {
    if (!confirm('Envoyer l\'email de caution ?\n\n→ À : jeremy.martinetti@gmail.com\n→ CC : contact@nomadrive.fr\n\n⚠️ Aucun email n\'est envoyé au client pour le moment (mode test).')) return;
    btn.disabled = true;
    btn.textContent = '...';
    try {
        const post = (data) => fetch('stripe_caution.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: Object.entries(data).map(([k,v]) => encodeURIComponent(k) + '=' + encodeURIComponent(v)).join('&')
        }).then(r => r.json());

        let cid = cautionId;
        if (!cid) {
            const r = await post({action: 'create', contrat_id: contratId});
            if (!r.success) { alert('Erreur création : ' + (r.message || '')); btn.disabled = false; btn.textContent = 'Envoyer'; return; }
            cid = r.caution_id;
        }
        const r2 = await post({action: 'send_email', caution_id: cid});
        if (r2.success) { location.reload(); } else { alert('Erreur envoi : ' + (r2.message || '')); btn.disabled = false; btn.textContent = 'Renvoyer'; }
    } catch(e) { alert('Erreur réseau'); btn.disabled = false; }
}

function switchTab(tab) {
    ['avis', 'flotte', 'planning', 'api'].forEach(t => {
        document.getElementById('tab-' + t).style.display = t === tab ? '' : 'none';
        document.getElementById('tab-btn-' + t).classList.toggle('active', t === tab);
    });
    history.replaceState(null, '', '?tab=' + tab);
}
(function() {
    const p = new URLSearchParams(location.search).get('tab');
    if (['flotte', 'planning', 'api'].includes(p)) switchTab(p);
})();
</script>
</body>
</html>
