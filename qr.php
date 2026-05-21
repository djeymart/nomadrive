<?php
// ─── Tracking QR code NOMADRIVE ───────────────────────────────────────────────
// QR code local  → https://nomadrive.fr/qr.php?id=1
// QR code flyer  → https://nomadrive.fr/qr.php?id=2
// L'ID est opaque dans le QR — la correspondance est en BDD (nomadrive_qr_sources)

$madiDir = '/var/www/html/madi.mt';
if (!is_dir($madiDir)) $madiDir = dirname(__DIR__);
require_once $madiDir . '/vendor/autoload.php';
require_once $madiDir . '/php/fonctions.php';
require_once $madiDir . '/php/config.php';
require_once __DIR__ . '/config.php';
$db1->query("SET NAMES 'utf8mb4'");

// ── Résolution de la source ───────────────────────────────────────────────────
$source_id = (int)($_GET['id'] ?? 0);
$source_name = 'unknown';

if ($source_id > 0) {
    $s = $db1->prepare("SELECT name FROM nomadrive_qr_sources WHERE id = :id LIMIT 1");
    $s->execute([':id' => $source_id]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if ($row) $source_name = $row['name'];
}

// ── Collecte silencieuse ──────────────────────────────────────────────────────
$ip_raw  = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$ip      = trim(explode(',', $ip_raw)[0]);
$ua      = $_SERVER['HTTP_USER_AGENT']      ?? '';
$referer = $_SERVER['HTTP_REFERER']         ?? null;

$lang_raw = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
$lang     = strtolower(substr(explode(',', $lang_raw)[0], 0, 10)) ?: null;

function nd_os(string $ua): string {
    if (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) return 'iOS';
    if (stripos($ua, 'Android') !== false) return 'Android';
    if (stripos($ua, 'Windows') !== false) return 'Windows';
    if (stripos($ua, 'Mac OS')  !== false) return 'macOS';
    if (stripos($ua, 'Linux')   !== false) return 'Linux';
    return 'Autre';
}

function nd_device(string $ua): string {
    if (stripos($ua, 'iPad')    !== false || stripos($ua, 'Tablet')  !== false) return 'tablette';
    if (stripos($ua, 'Mobile')  !== false || stripos($ua, 'iPhone')  !== false) return 'mobile';
    if (stripos($ua, 'Android') !== false) return 'tablette';
    return 'desktop';
}

function nd_browser(string $ua): string {
    if (stripos($ua, 'SamsungBrowser') !== false) return 'Samsung';
    if (stripos($ua, 'Edg')           !== false) return 'Edge';
    if (stripos($ua, 'OPR')           !== false) return 'Opera';
    if (stripos($ua, 'Opera')         !== false) return 'Opera';
    if (stripos($ua, 'Firefox')       !== false) return 'Firefox';
    if (stripos($ua, 'Chrome')        !== false) return 'Chrome';
    if (stripos($ua, 'Safari')        !== false) return 'Safari';
    return 'Autre';
}

// Hash anonyme — déduplique par jour sans stocker l'IP en clair
$unique_hash = hash('sha256', $ip . $ua . date('Y-m-d'));

// ── Enregistrement ────────────────────────────────────────────────────────────
$db1->prepare("
    INSERT INTO nomadrive_qr
        (date, source_id, source_name, HTTP_USER_AGENT, REMOTE_ADDR, lang, os, device_type, browser, referer, unique_hash)
    VALUES
        (NOW(), :sid, :sname, :ua, :ip, :lang, :os, :device, :browser, :referer, :hash)
")->execute([
    ':sid'     => $source_id ?: null,
    ':sname'   => $source_name,
    ':ua'      => $ua,
    ':ip'      => $ip,
    ':lang'    => $lang,
    ':os'      => nd_os($ua),
    ':device'  => nd_device($ua),
    ':browser' => nd_browser($ua),
    ':referer' => $referer,
    ':hash'    => $unique_hash,
]);

// ── Redirection transparente ──────────────────────────────────────────────────
header('Location: https://nomadrive.fr/');
exit;
