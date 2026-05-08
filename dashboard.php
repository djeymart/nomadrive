<?php
if (session_status() === PHP_SESSION_NONE)
  session_start();

// ─── Includes ─────────────────────────────────────────────────────────────────
$madiDir = '/var/www/html/madi.mt';
if (!is_dir($madiDir))
  $madiDir = dirname(__DIR__);
require_once $madiDir . '/vendor/autoload.php';
require_once $madiDir . '/php/fonctions.php';
require_once $madiDir . '/php/config.php';
$db1->query("SET NAMES 'utf8mb4'");

require_once __DIR__ . '/nomadrive_auth.php';

$gcp_bucket = 'madi_bucket';
$gcp_base_url = "https://storage.googleapis.com/$gcp_bucket";

// ─── Auth helpers ─────────────────────────────────────────────────────────────
function requireAuth(PDO $db): void
{
  if (!ndIsAuth($db)) {
    header('Location: dashboard.php?view=login');
    exit;
  }
}

// ─── Upload helper (photos état des lieux) ────────────────────────────────────
function uploadEtatPhotos(array $b64list, string $ref, string $type, string $bucket, string $base): array
{
  $urls = [];
  foreach ($b64list as $i => $b64) {
    if (empty($b64) || !preg_match('/^data:(image\/\w+);base64,(.+)$/s', $b64, $m))
      continue;
    $ext = $m[1] === 'image/png' ? 'png' : 'jpg';
    $tmp = tempnam(sys_get_temp_dir(), "nd_{$type}_") . ".$ext";
    file_put_contents($tmp, base64_decode($m[2]));
    $gcpPath = "nomadrive/dossiers/{$ref}-{$type}-" . ($i + 1) . ".{$ext}";
    upload_object($bucket, $gcpPath, $tmp, 1);
    @unlink($tmp);
    $urls[] = "$base/$gcpPath";
  }
  return $urls;
}

// ─── POST handlers (JSON API) ─────────────────────────────────────────────────
$action = $_POST['action'] ?? '';

if ($action === 'login') {
  header('Content-Type: application/json');
  $pwd = $_POST['password'] ?? '';
  $hash = hash('sha256', hash('sha256', $pwd));
  $stored = $db1->query("SELECT valeur FROM nomadrive_settings WHERE cle='admin_password_hash'")->fetchColumn();
  if ($stored && hash_equals($stored, $hash)) {
    $_SESSION['nd_auth'] = true;
    ndCreateRememberToken($db1);
    echo json_encode(['success' => true]);
  } else {
    echo json_encode(['success' => false, 'message' => 'Mot de passe incorrect.']);
  }
  exit;
}

if ($action === 'logout') {
  ndRevokeRememberToken($db1);
  session_destroy();
  header('Location: dashboard.php?view=login');
  exit;
}

if ($action === 'save_etat_avant') {
  requireAuth($db1);
  header('Content-Type: application/json');
  $did = (int) ($_POST['dossier_id'] ?? 0);
  $km = (int) ($_POST['km'] ?? 0);
  $notes = strip_tags(trim($_POST['notes'] ?? ''));
  $photos = json_decode($_POST['photos_json'] ?? '[]', true) ?: [];

  $dos = $db1->query("SELECT d.*, CONCAT('ND-', LPAD(d.contrat_id,5,'0')) AS ref FROM nomadrive_dossiers d WHERE d.id=$did AND d.statut='ouvert'")->fetch(PDO::FETCH_ASSOC);
  if (!$dos) {
    echo json_encode(['success' => false, 'message' => 'Dossier introuvable.']);
    exit;
  }

  $urls = uploadEtatPhotos($photos, $dos['ref'], 'avant', $gcp_bucket, $gcp_base_url);
  $db1->prepare("UPDATE nomadrive_dossiers SET etat_avant_km=:km, etat_avant_notes=:n, etat_avant_photos=:p, etat_avant_at=NOW() WHERE id=:id")
    ->execute([':km' => $km, ':n' => $notes, ':p' => json_encode($urls), ':id' => $did]);
  echo json_encode(['success' => true]);
  exit;
}

if ($action === 'save_etat_apres') {
  requireAuth($db1);
  header('Content-Type: application/json');
  $did          = (int)($_POST['dossier_id'] ?? 0);
  $km           = (int)($_POST['km'] ?? 0);
  $notes        = strip_tags(trim($_POST['notes'] ?? ''));
  $photos       = json_decode($_POST['photos_json'] ?? '[]', true) ?: [];
  $caution_lib  = (int)($_POST['caution_liberee'] ?? 1);
  $caution_ret  = (float)($_POST['caution_retenu'] ?? 0);
  $caution_note = strip_tags(trim($_POST['caution_note'] ?? ''));

  $dos = $db1->query("
    SELECT d.*, CONCAT('ND-', LPAD(d.contrat_id,5,'0')) AS ref,
           c.nom, c.prenom, c.email, c.date_debut, c.heure_debut, c.vehicule
    FROM nomadrive_dossiers d
    JOIN nomadrive_contrats c ON c.id = d.contrat_id
    WHERE d.id = $did AND d.statut = 'ouvert'
  ")->fetch(PDO::FETCH_ASSOC);
  if (!$dos) { echo json_encode(['success' => false, 'message' => 'Dossier introuvable.']); exit; }

  $urls = uploadEtatPhotos($photos, $dos['ref'], 'apres', $gcp_bucket, $gcp_base_url);
  $db1->prepare("UPDATE nomadrive_dossiers SET etat_apres_km=:km, etat_apres_notes=:n, etat_apres_photos=:p, etat_apres_at=NOW(), caution_liberee=:cl, caution_retenu=:cr, caution_note=:cn, statut='ferme', closed_at=NOW() WHERE id=:id")
      ->execute([':km' => $km, ':n' => $notes, ':p' => json_encode($urls), ':cl' => $caution_lib, ':cr' => $caution_ret > 0 ? $caution_ret : null, ':cn' => $caution_note, ':id' => $did]);

  // ── Email de clôture au client ─────────────────────────────────────────────
  $envFile = $madiDir . '/.env';
  if (!file_exists($envFile)) $envFile = __DIR__ . '/.env';
  if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
      if ($line[0] === '#' || strpos($line, '=') === false) continue;
      [$k, $v] = explode('=', $line, 2);
      $_ENV[trim($k)] = trim($v);
    }
  }

  $km_avant   = (int)($dos['etat_avant_km'] ?? 0);
  $distance   = ($km > 0 && $km_avant > 0) ? ($km - $km_avant) : null;
  $nom_complet = htmlspecialchars($dos['prenom'] . ' ' . $dos['nom']);
  $caution_html = $caution_lib
    ? '<span style="color:#155724;font-weight:600">✅ Caution libérée intégralement</span>'
    : '<span style="color:#721c24;font-weight:600">❌ Montant retenu : ' . ($caution_ret > 0 ? number_format($caution_ret, 0, '.', '') . ' €' : 'non précisé') . '</span>';

  $body = "
  <div style='font-family:Arial,sans-serif;color:#222;max-width:600px;margin:0 auto;padding:20px;'>
    <div style='text-align:center;margin-bottom:20px;'>
      <h1 style='color:#0077b6;margin:0;'>NOMADRIVE</h1>
      <p style='color:#555;font-size:13px;'>2 place Guynemer, 06000 Nice</p>
    </div>
    <p>Bonjour <strong>{$nom_complet}</strong>,</p>
    <p>Votre location <strong>{$dos['ref']}</strong> est maintenant clôturée. Voici le récapitulatif :</p>

    <table style='width:100%;border-collapse:collapse;margin:16px 0;font-size:13px;'>
      <tr><td style='padding:5px 8px;background:#f0f8ff;width:40%;font-weight:bold;'>Véhicule</td>
          <td style='padding:5px 8px;background:#f0f8ff;'>" . htmlspecialchars($dos['vehicule']) . "</td></tr>
      <tr><td style='padding:5px 8px;'>Départ</td>
          <td style='padding:5px 8px;'>" . htmlspecialchars($dos['date_debut'] ?? '—') . " à " . htmlspecialchars($dos['heure_debut'] ?? '—') . "</td></tr>
      " . ($km_avant > 0 ? "<tr><td style='padding:5px 8px;background:#f0f8ff;'>Km départ</td><td style='padding:5px 8px;background:#f0f8ff;'>" . number_format($km_avant, 0, '.', ' ') . " km</td></tr>" : '') . "
      " . ($km > 0 ? "<tr><td style='padding:5px 8px;'>Km retour</td><td style='padding:5px 8px;'>" . number_format($km, 0, '.', ' ') . " km</td></tr>" : '') . "
      " . ($distance !== null ? "<tr><td style='padding:5px 8px;background:#f0f8ff;'>Distance parcourue</td><td style='padding:5px 8px;background:#f0f8ff;'>" . number_format($distance, 0, '.', ' ') . " km</td></tr>" : '') . "
    </table>

    " . (!empty($notes) ? "<p style='font-size:13px;color:#555;'><strong>Observations retour :</strong> " . htmlspecialchars($notes) . "</p>" : '') . "

    <div style='padding:14px;border-radius:8px;margin:16px 0;background:" . ($caution_lib ? '#d4edda' : '#f8d7da') . ";'>
      {$caution_html}
      " . (!empty($caution_note) ? "<p style='font-size:12px;margin:6px 0 0;'>" . htmlspecialchars($caution_note) . "</p>" : '') . "
    </div>

    <hr style='border:none;border-top:1px solid #eee;margin:16px 0;'/>
    <p style='font-size:11px;color:#aaa;text-align:center;'>NICE ACTIVITY (NOMADRIVE) · SAS au capital de 100 000 € · RCS Nice 994 620 615 · contact@nomadrive.fr</p>
  </div>";

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
    $mail->setFrom('contact@madi.mt', 'NOMADRIVE');
    $mail->addReplyTo('contact@nomadrive.fr', 'NOMADRIVE');
    $mail->addAddress($dos['email'], $nom_complet);
    $mail->addCC('contact@nomadrive.fr', 'NOMADRIVE');
    $mail->isHTML(true);
    $mail->Subject = "Fin de location NOMADRIVE — {$dos['ref']}";
    $mail->Body    = $body;
    $mail->send();
  } catch (\Exception $e) {
    // Email raté, on ne bloque pas la clôture
  }

  echo json_encode(['success' => true]);
  exit;
}

// ─── View routing ─────────────────────────────────────────────────────────────
$view = $_GET['view'] ?? 'dashboard';
if ($view !== 'login')
  requireAuth($db1);

// Données pour les vues etat_avant / etat_apres / dossier_detail
$dossier_detail = null;
if (in_array($view, ['etat_avant', 'etat_apres', 'dossier_detail'])) {
  $did = (int) ($_GET['dossier'] ?? 0);
  $statut_cond = ($view === 'dossier_detail') ? '1=1' : "d.statut = 'ouvert'";
  $dossier_detail = $db1->query("
    SELECT d.*,
           CONCAT('ND-', LPAD(d.contrat_id,5,'0')) AS contrat_ref,
           c.nom, c.prenom, c.email, c.date_debut, c.heure_debut,
           c.url_contrat_pdf, c.url_permis_recto, c.url_permis_verso,
           v.marque, v.modele, v.immatriculation
    FROM nomadrive_dossiers d
    JOIN nomadrive_contrats c ON c.id = d.contrat_id
    JOIN nomadrive_vehicules v ON v.id = d.vehicule_id
    WHERE d.id = $did AND $statut_cond
  ")->fetch(PDO::FETCH_ASSOC);
  if (!$dossier_detail) {
    header('Location: dashboard.php');
    exit;
  }
}

// Données pour la vue dossiers fermés
$closed_all   = [];
$closed_total = 0;
$closed_page  = max(1, (int)($_GET['page'] ?? 1));
$closed_per   = 25;
if ($view === 'dossiers_fermes') {
  $closed_total = (int)$db1->query("SELECT COUNT(*) FROM nomadrive_dossiers WHERE statut='ferme'")->fetchColumn();
  $off = ($closed_page - 1) * $closed_per;
  $closed_all = $db1->query("
    SELECT d.*,
           CONCAT('ND-', LPAD(d.contrat_id,5,'0')) AS contrat_ref,
           c.nom, c.prenom, c.date_debut,
           v.marque, v.modele, v.immatriculation
    FROM nomadrive_dossiers d
    JOIN nomadrive_contrats c ON c.id = d.contrat_id
    JOIN nomadrive_vehicules v ON v.id = d.vehicule_id
    WHERE d.statut = 'ferme'
    ORDER BY d.closed_at DESC
    LIMIT $closed_per OFFSET $off
  ")->fetchAll(PDO::FETCH_ASSOC);
}

// Données pour le dashboard
$open_dossiers = [];
$closed_recent = [];
$all_vehicules = [];
if ($view === 'dashboard') {
  $open_dossiers = $db1->query("
    SELECT d.*,
           CONCAT('ND-', LPAD(d.contrat_id,5,'0')) AS contrat_ref,
           c.nom, c.prenom, c.date_debut, c.heure_debut,
           v.marque, v.modele, v.immatriculation
    FROM nomadrive_dossiers d
    JOIN nomadrive_contrats c ON c.id = d.contrat_id
    JOIN nomadrive_vehicules v ON v.id = d.vehicule_id
    WHERE d.statut = 'ouvert'
    ORDER BY d.created_at DESC
  ")->fetchAll(PDO::FETCH_ASSOC);

  $closed_recent = $db1->query("
    SELECT d.*,
           CONCAT('ND-', LPAD(d.contrat_id,5,'0')) AS contrat_ref,
           c.nom, c.prenom,
           v.marque, v.modele, v.immatriculation
    FROM nomadrive_dossiers d
    JOIN nomadrive_contrats c ON c.id = d.contrat_id
    JOIN nomadrive_vehicules v ON v.id = d.vehicule_id
    WHERE d.statut = 'ferme' AND d.closed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY d.closed_at DESC
  ")->fetchAll(PDO::FETCH_ASSOC);

  $all_vehicules = $db1->query("
    SELECT v.*, d.id AS dossier_id
    FROM nomadrive_vehicules v
    LEFT JOIN nomadrive_dossiers d ON d.vehicule_id = v.id AND d.statut = 'ouvert'
    ORDER BY v.id
  ")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="robots" content="noindex,nofollow">
  <title>Dashboard — NOMADRIVE</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://kit.fontawesome.com/494ceebc6d.js" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
  <style>
    *,
    *::before,
    *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0
    }

    :root {
      --blue: #0077b6;
      --blue-l: #00a8e8;
      --green: #2ecc71;
      --red: #e74c3c;
      --orange: #f39c12;
      --gray: #f4f6f8;
      --border: #dde2e9;
      --text: #1a1a2e;
      --muted: #6b7280;
    }

    html,
    body {
      font-family: 'Inter', sans-serif;
      background: var(--gray);
      color: var(--text);
      min-height: 100%
    }

    a {
      color: inherit;
      text-decoration: none
    }

    /* ── Header ── */
    header {
      background: #fff;
      border-bottom: 1px solid var(--border);
      padding: 14px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 100
    }

    header .logo {
      font-weight: 700;
      font-size: 18px;
      color: var(--blue);
      letter-spacing: -.5px
    }

    header .subtitle {
      font-size: 12px;
      color: var(--muted)
    }

    /* ── Main layout ── */
    .main {
      max-width: 960px;
      margin: 0 auto;
      padding: 24px 20px
    }

    /* ── Card ── */
    .card {
      background: #fff;
      border-radius: 14px;
      border: 1px solid var(--border);
      padding: 24px;
      margin-bottom: 20px;
      box-shadow: 0 1px 4px rgba(0, 0, 0, .04)
    }

    .card-title {
      font-size: 14px;
      font-weight: 600;
      color: var(--blue);
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 8px
    }

    /* ── Vehicle grid ── */
    .vehicle-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 12px;
      margin-bottom: 24px
    }

    .vehicle-card {
      background: #fff;
      border: 2px solid var(--border);
      border-radius: 12px;
      padding: 14px 16px;
      transition: border-color .2s
    }

    .vehicle-card.libre {
      border-color: #c3e6cb
    }

    .vehicle-card.occupe {
      border-color: #f5c6cb
    }

    .vehicle-badge {
      display: inline-block;
      font-size: 11px;
      font-weight: 600;
      letter-spacing: .04em;
      padding: 3px 8px;
      border-radius: 20px;
      margin-bottom: 8px
    }

    .vehicle-badge.libre {
      background: #d4edda;
      color: #155724
    }

    .vehicle-badge.occupe {
      background: #f8d7da;
      color: #721c24
    }

    .vehicle-name {
      font-size: 13px;
      font-weight: 600;
      color: var(--text)
    }

    .vehicle-immat {
      font-size: 11px;
      color: var(--muted);
      margin-top: 2px
    }

    /* ── Table ── */
    .table-wrap {
      overflow-x: auto
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px
    }

    th {
      text-align: left;
      padding: 10px 12px;
      font-size: 11px;
      font-weight: 600;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: .05em;
      border-bottom: 2px solid var(--border)
    }

    td {
      padding: 12px;
      border-bottom: 1px solid var(--border);
      vertical-align: middle
    }

    tr:last-child td {
      border-bottom: none
    }

    tr:hover td {
      background: #fafbfc
    }

    /* ── Status badges ── */
    .badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: 11px;
      font-weight: 600;
      padding: 3px 8px;
      border-radius: 20px
    }

    .badge-new {
      background: #e8f4fd;
      color: #0077b6
    }

    .badge-avant {
      background: #fff3cd;
      color: #856404
    }

    .badge-ready {
      background: #d4edda;
      color: #155724
    }

    .badge-done {
      background: #e2e3e5;
      color: #383d41
    }

    .badge-caution-ok {
      background: #d4edda;
      color: #155724
    }

    .badge-caution-ko {
      background: #f8d7da;
      color: #721c24
    }

    /* ── Buttons ── */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      padding: 9px 16px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      font-family: inherit;
      border: none;
      cursor: pointer;
      transition: all .2s;
      white-space: nowrap
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--blue), var(--blue-l));
      color: #fff
    }

    .btn-primary:hover {
      opacity: .9;
      transform: translateY(-1px)
    }

    .btn-primary:disabled {
      opacity: .5;
      cursor: not-allowed;
      transform: none
    }

    .btn-secondary {
      background: var(--gray);
      color: var(--muted);
      border: 1px solid var(--border)
    }

    .btn-danger {
      background: #fff0f0;
      color: var(--red);
      border: 1px solid #fcc
    }

    .btn-success {
      background: #d4edda;
      color: #155724
    }

    .btn-sm {
      padding: 6px 12px;
      font-size: 12px
    }

    .btn-block {
      width: 100%
    }

    /* ── Form ── */
    .form-group {
      display: flex;
      flex-direction: column;
      gap: 5px;
      margin-bottom: 14px
    }

    label {
      font-size: 12px;
      font-weight: 500;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: .4px
    }

    input[type=text],
    input[type=password],
    input[type=number],
    textarea,
    select {
      width: 100%;
      padding: 11px 13px;
      border: 1.5px solid var(--border);
      border-radius: 8px;
      font-size: 15px;
      font-family: inherit;
      color: var(--text);
      background: #fff;
      transition: border-color .2s;
      -webkit-appearance: none
    }

    input:focus,
    textarea:focus,
    select:focus {
      outline: none;
      border-color: var(--blue)
    }

    textarea {
      resize: vertical;
      min-height: 80px
    }

    /* ── Radio caution ── */
    .radio-group {
      display: flex;
      gap: 12px
    }

    .radio-btn {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 12px;
      border: 2px solid var(--border);
      border-radius: 10px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 500;
      transition: all .2s
    }

    .radio-btn input {
      display: none
    }

    .radio-btn.selected-lib {
      border-color: var(--green);
      background: #d4edda;
      color: #155724
    }

    .radio-btn.selected-ret {
      border-color: var(--red);
      background: #f8d7da;
      color: #721c24
    }

    /* ── Photo grid ── */
    .photo-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      margin-bottom: 14px
    }

    .photo-thumb {
      position: relative;
      border-radius: 8px;
      overflow: hidden;
      aspect-ratio: 4/3;
      background: var(--gray);
      border: 1px solid var(--border)
    }

    .photo-thumb img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block
    }

    .photo-thumb .del-photo {
      position: absolute;
      top: 4px;
      right: 4px;
      background: rgba(0, 0, 0, .55);
      color: #fff;
      border: none;
      border-radius: 50%;
      width: 22px;
      height: 22px;
      font-size: 12px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center
    }

    .photo-add {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 6px;
      border: 2px dashed var(--border);
      border-radius: 8px;
      aspect-ratio: 4/3;
      cursor: pointer;
      color: var(--muted);
      font-size: 12px;
      transition: border-color .2s
    }

    .photo-add:hover {
      border-color: var(--blue)
    }

    /* ── Camera modal ── */
    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .85);
      z-index: 200;
      display: none;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 16px
    }

    .modal-overlay.open {
      display: flex
    }

    #camera-video {
      width: min(100vw, 500px);
      border-radius: 10px;
      background: #000;
      max-height: 60vh;
      object-fit: cover
    }

    .camera-controls {
      display: flex;
      gap: 16px
    }

    .btn-capture {
      width: 64px;
      height: 64px;
      border-radius: 50%;
      background: #fff;
      border: 4px solid #aaa;
      cursor: pointer;
      transition: transform .1s
    }

    .btn-capture:active {
      transform: scale(.92)
    }

    .btn-cancel-camera {
      padding: 14px 28px;
      background: transparent;
      border: 2px solid rgba(255, 255, 255, .4);
      color: #fff;
      border-radius: 8px;
      font-size: 14px;
      cursor: pointer
    }

    /* ── Login ── */
    .login-wrap {
      max-width: 380px;
      margin: 80px auto;
      padding: 0 20px
    }

    .login-logo {
      text-align: center;
      margin-bottom: 32px
    }

    .login-logo h1 {
      font-size: 28px;
      font-weight: 700;
      color: var(--blue)
    }

    .login-logo p {
      font-size: 13px;
      color: var(--muted);
      margin-top: 4px
    }

    /* ── Spinner ── */
    .spinner {
      width: 18px;
      height: 18px;
      border: 3px solid rgba(255, 255, 255, .3);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin .7s linear infinite
    }

    @keyframes spin {
      to {
        transform: rotate(360deg)
      }
    }

    /* ── Toast ── */
    #toast {
      position: fixed;
      bottom: 24px;
      left: 50%;
      transform: translateX(-50%) translateY(80px);
      background: var(--red);
      color: #fff;
      padding: 12px 20px;
      border-radius: 8px;
      font-size: 14px;
      z-index: 300;
      transition: transform .3s;
      max-width: 90vw;
      text-align: center
    }

    #toast.show {
      transform: translateX(-50%) translateY(0)
    }

    #toast.success {
      background: var(--green)
    }

    /* ── Section title ── */
    .section-title {
      font-size: 16px;
      font-weight: 700;
      color: var(--text);
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 8px
    }

    .empty-state {
      text-align: center;
      padding: 32px;
      color: var(--muted);
      font-size: 14px
    }

    /* ── Responsive ── */
    @media(max-width:600px) {
      .main {
        padding: 16px
      }

      .card {
        padding: 18px
      }

      .vehicle-grid {
        grid-template-columns: repeat(2, 1fr)
      }

      .photo-grid {
        grid-template-columns: repeat(2, 1fr)
      }

      th:nth-child(4),
      td:nth-child(4) {
        display: none
      }
    }
  </style>
</head>

<body>

  <?php if ($view === 'login'): ?>
    <!-- ══════════════════════════════════════════════════════
     VUE : LOGIN
═══════════════════════════════════════════════════════ -->
    <div class="login-wrap">
      <div class="login-logo">
        <h1>NOMADRIVE</h1>
        <p>Administration — Accès sécurisé</p>
      </div>
      <div class="card">
        <div class="form-group">
          <label for="pwd">Mot de passe</label>
          <input type="password" id="pwd" placeholder="••••••••" autocomplete="current-password">
        </div>
        <button class="btn btn-primary btn-block" id="btn-login" onclick="doLogin()">
          <i class="fa-solid fa-lock"></i> Connexion
        </button>
      </div>
    </div>

  <?php elseif ($view === 'dossiers_fermes'): ?>
    <!-- ══════════════════════════════════════════════════════
     VUE : TOUS LES DOSSIERS FERMÉS
═══════════════════════════════════════════════════════ -->
    <header>
      <div><div class="logo">NOMADRIVE</div><div class="subtitle">Dossiers fermés</div></div>
      <a href="dashboard.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Retour</a>
    </header>
    <div class="main">
      <div class="section-title">
        <i class="fa-duotone fa-solid fa-folder-closed"></i>
        Dossiers fermés (<?= $closed_total ?>)
      </div>
      <?php if (empty($closed_all)): ?>
        <div class="card"><div class="empty-state">Aucun dossier fermé.</div></div>
      <?php else: ?>
        <div class="card">
          <div class="table-wrap">
            <table>
              <thead>
                <tr><th>Réf.</th><th>Véhicule</th><th>Client</th><th>Départ</th><th>Fermé le</th><th>Caution</th><th></th></tr>
              </thead>
              <tbody>
                <?php foreach ($closed_all as $d): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($d['contrat_ref']) ?></strong></td>
                  <td><?= htmlspecialchars($d['marque'] . ' ' . $d['modele']) ?><br><span style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($d['immatriculation']) ?></span></td>
                  <td><?= htmlspecialchars($d['prenom'] . ' ' . $d['nom']) ?></td>
                  <td style="font-size:12px"><?= htmlspecialchars($d['date_debut'] ?? '—') ?></td>
                  <td style="font-size:12px"><?= $d['closed_at'] ? date('d/m/Y H:i', strtotime($d['closed_at'])) : '—' ?></td>
                  <td>
                    <?php if ($d['caution_liberee'] === null): ?>
                      <span class="badge badge-done">—</span>
                    <?php elseif ($d['caution_liberee']): ?>
                      <span class="badge badge-caution-ok">✅ Libérée</span>
                    <?php else: ?>
                      <span class="badge badge-caution-ko">❌ <?= $d['caution_retenu'] ? number_format($d['caution_retenu'], 0, '.', '') . ' €' : 'Retenue' ?></span>
                    <?php endif; ?>
                  </td>
                  <td><a href="dashboard.php?view=dossier_detail&dossier=<?= $d['id'] ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-eye"></i></a></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php if ($closed_total > $closed_per): ?>
          <div style="display:flex;justify-content:center;gap:8px;margin-top:4px;">
            <?php for ($p = 1; $p <= ceil($closed_total / $closed_per); $p++): ?>
              <a href="dashboard.php?view=dossiers_fermes&page=<?= $p ?>"
                 class="btn btn-sm <?= $p === $closed_page ? 'btn-primary' : 'btn-secondary' ?>"
                 style="min-width:36px"><?= $p ?></a>
            <?php endfor; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

  <?php elseif ($view === 'dossier_detail' && $dossier_detail): ?>
    <!-- ══════════════════════════════════════════════════════
     VUE : CONSULTATION DOSSIER (lecture seule)
═══════════════════════════════════════════════════════ -->
    <?php
      $d = $dossier_detail;
      $photos_avant = json_decode($d['etat_avant_photos'] ?? '[]', true) ?: [];
      $photos_apres = json_decode($d['etat_apres_photos'] ?? '[]', true) ?: [];
      $is_open = $d['statut'] === 'ouvert';
    ?>
    <header>
      <div><div class="logo">NOMADRIVE</div><div class="subtitle"><?= htmlspecialchars($d['contrat_ref']) ?> — <?= $is_open ? 'En cours' : 'Clôturé' ?></div></div>
      <a href="dashboard.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Retour</a>
    </header>
    <div class="main">

      <div class="card" style="background:linear-gradient(135deg,#e8f4fd,#f0f9ff);border-color:#b3d9f5;">
        <strong><?= htmlspecialchars($d['prenom'] . ' ' . $d['nom']) ?></strong>
        &nbsp;·&nbsp; <?= htmlspecialchars($d['marque'] . ' ' . $d['modele'] . ' — ' . $d['immatriculation']) ?>
        &nbsp;·&nbsp; <?= htmlspecialchars($d['date_debut'] ?? '—') ?> <?= htmlspecialchars($d['heure_debut'] ?? '') ?>
        <?php if (!$is_open && $d['closed_at']): ?>
          &nbsp;·&nbsp; <span style="color:var(--muted)">Fermé le <?= date('d/m/Y à H:i', strtotime($d['closed_at'])) ?></span>
        <?php endif; ?>
      </div>

      <!-- Contrat & documents -->
      <?php if (!empty($d['url_contrat_pdf']) || !empty($d['url_permis_recto'])): ?>
      <div class="card">
        <div class="card-title"><i class="fa-duotone fa-solid fa-file-contract"></i> Documents du contrat</div>
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-start">
          <?php if (!empty($d['url_contrat_pdf'])): ?>
            <a href="<?= htmlspecialchars($d['url_contrat_pdf']) ?>" target="_blank" class="btn btn-primary btn-sm">
              <i class="fa-solid fa-file-pdf"></i> Contrat PDF
            </a>
          <?php endif; ?>
          <?php if (!empty($d['url_permis_recto'])): ?>
            <a href="<?= htmlspecialchars($d['url_permis_recto']) ?>" target="_blank" style="display:block;border-radius:8px;overflow:hidden;height:80px;border:1px solid var(--border)">
              <img src="<?= htmlspecialchars($d['url_permis_recto']) ?>" style="height:80px;width:auto;display:block" alt="Permis recto">
            </a>
          <?php endif; ?>
          <?php if (!empty($d['url_permis_verso'])): ?>
            <a href="<?= htmlspecialchars($d['url_permis_verso']) ?>" target="_blank" style="display:block;border-radius:8px;overflow:hidden;height:80px;border:1px solid var(--border)">
              <img src="<?= htmlspecialchars($d['url_permis_verso']) ?>" style="height:80px;width:auto;display:block" alt="Permis verso">
            </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- État des lieux avant -->
      <div class="card">
        <div class="card-title">
          <i class="fa-duotone fa-solid fa-gauge"></i> État des lieux — Départ
          <?php if (!empty($d['etat_avant_at'])): ?>
            <span class="badge badge-ready" style="margin-left:auto">✓ Renseigné</span>
          <?php else: ?>
            <span class="badge badge-done" style="margin-left:auto">Non renseigné</span>
          <?php endif; ?>
        </div>
        <?php if (!empty($d['etat_avant_at'])): ?>
          <?php if (!empty($d['etat_avant_km'])): ?>
            <p style="font-size:14px;margin-bottom:12px"><strong><?= number_format($d['etat_avant_km'], 0, '.', ' ') ?> km</strong></p>
          <?php endif; ?>
          <?php if (!empty($photos_avant)): ?>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px">
              <?php foreach ($photos_avant as $url): ?>
                <a href="<?= htmlspecialchars($url) ?>" target="_blank" style="display:block;border-radius:8px;overflow:hidden;aspect-ratio:4/3;background:var(--gray)">
                  <img src="<?= htmlspecialchars($url) ?>" style="width:100%;height:100%;object-fit:cover;display:block">
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <?php if (!empty($d['etat_avant_notes'])): ?>
            <p style="font-size:13px;color:var(--muted);white-space:pre-wrap"><?= htmlspecialchars($d['etat_avant_notes']) ?></p>
          <?php endif; ?>
        <?php else: ?>
          <p style="color:var(--muted);font-size:13px">Aucun état des lieux de départ enregistré.</p>
        <?php endif; ?>
      </div>

      <!-- État des lieux après -->
      <?php if (!empty($d['etat_apres_at']) || !$is_open): ?>
      <div class="card">
        <div class="card-title">
          <i class="fa-duotone fa-solid fa-flag-checkered"></i> État des lieux — Retour
          <?php if (!empty($d['etat_apres_at'])): ?>
            <span class="badge badge-ready" style="margin-left:auto">✓ Renseigné</span>
          <?php else: ?>
            <span class="badge badge-done" style="margin-left:auto">Non renseigné</span>
          <?php endif; ?>
        </div>
        <?php if (!empty($d['etat_apres_at'])): ?>
          <?php if (!empty($d['etat_apres_km'])): ?>
            <p style="font-size:14px;margin-bottom:4px"><strong><?= number_format($d['etat_apres_km'], 0, '.', ' ') ?> km</strong>
            <?php if (!empty($d['etat_avant_km'])): ?>
              <span style="font-size:12px;color:var(--muted)"> (+ <?= number_format($d['etat_apres_km'] - $d['etat_avant_km'], 0, '.', ' ') ?> km parcourus)</span>
            <?php endif; ?>
            </p>
          <?php endif; ?>
          <?php if (!empty($photos_apres)): ?>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:12px 0">
              <?php foreach ($photos_apres as $url): ?>
                <a href="<?= htmlspecialchars($url) ?>" target="_blank" style="display:block;border-radius:8px;overflow:hidden;aspect-ratio:4/3;background:var(--gray)">
                  <img src="<?= htmlspecialchars($url) ?>" style="width:100%;height:100%;object-fit:cover;display:block">
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <?php if (!empty($d['etat_apres_notes'])): ?>
            <p style="font-size:13px;color:var(--muted);white-space:pre-wrap;margin-bottom:12px"><?= htmlspecialchars($d['etat_apres_notes']) ?></p>
          <?php endif; ?>
          <div style="padding:12px;border-radius:10px;<?= $d['caution_liberee'] ? 'background:#d4edda' : 'background:#f8d7da' ?>">
            <?php if ($d['caution_liberee']): ?>
              <strong style="color:#155724">✅ Caution libérée intégralement</strong>
            <?php else: ?>
              <strong style="color:#721c24">❌ Montant retenu : <?= $d['caution_retenu'] ? number_format($d['caution_retenu'], 0, '.', '') . ' €' : 'non précisé' ?></strong>
            <?php endif; ?>
            <?php if (!empty($d['caution_note'])): ?>
              <p style="font-size:12px;margin-top:6px;margin-bottom:0"><?= htmlspecialchars($d['caution_note']) ?></p>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <p style="color:var(--muted);font-size:13px">Aucun état des lieux de retour enregistré.</p>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if ($is_open): ?>
        <a href="dashboard.php?view=etat_apres&dossier=<?= $d['id'] ?>" class="btn btn-primary" style="display:flex;align-items:center;justify-content:center;gap:8px;padding:14px;border-radius:10px;text-decoration:none">
          <i class="fa-solid fa-flag-checkered"></i> Clôturer le dossier
        </a>
      <?php endif; ?>
    </div>

  <?php elseif ($view === 'etat_avant' && $dossier_detail): ?>
    <!-- ══════════════════════════════════════════════════════
     VUE : ÉTAT DES LIEUX AVANT
═══════════════════════════════════════════════════════ -->
    <header>
      <div>
        <div class="logo">NOMADRIVE</div>
        <div class="subtitle">État des lieux — Départ</div>
      </div>
      <a href="dashboard.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Retour</a>
    </header>
    <div class="main">
      <?php $d = $dossier_detail; ?>
      <div class="card" style="background:linear-gradient(135deg,#e8f4fd,#f0f9ff);border-color:#b3d9f5;">
        <strong><?= htmlspecialchars($d['prenom'] . ' ' . $d['nom']) ?></strong>
        &nbsp;·&nbsp; <?= htmlspecialchars($d['marque'] . ' ' . $d['modele'] . ' — ' . $d['immatriculation']) ?>
        &nbsp;·&nbsp; <span style="color:var(--muted)"><?= htmlspecialchars($d['contrat_ref']) ?></span>
        &nbsp;·&nbsp; <?= htmlspecialchars($d['date_debut']) ?>   <?= htmlspecialchars($d['heure_debut']) ?>
      </div>

      <div class="card">
        <div class="card-title"><i class="fa-duotone fa-solid fa-gauge"></i> Kilométrage départ</div>
        <div class="form-group">
          <input type="number" id="km-avant" placeholder="Kilométrage actuel" min="0" inputmode="numeric">
        </div>
      </div>

      <div class="card">
        <div class="card-title"><i class="fa-duotone fa-solid fa-camera"></i> Photos du véhicule</div>
        <div class="photo-grid" id="photo-grid-avant"></div>
        <button class="btn btn-secondary btn-block" onclick="addPhoto('avant')" id="btn-add-avant">
          <i class="fa-solid fa-plus"></i> Ajouter une photo
        </button>
        <input type="hidden" id="photos-json-avant" value="[]">
      </div>

      <div class="card">
        <div class="card-title"><i class="fa-duotone fa-solid fa-note-sticky"></i> Observations</div>
        <div class="form-group">
          <textarea id="notes-avant" placeholder="Rayures, dommages existants, niveau de batterie, remarques…"></textarea>
        </div>
      </div>

      <button class="btn btn-primary btn-block" id="btn-submit-avant" onclick="submitEtat('avant', <?= $d['id'] ?>)">
        <i class="fa-solid fa-check"></i> Valider l'état des lieux départ
      </button>
    </div>

  <?php elseif ($view === 'etat_apres' && $dossier_detail): ?>
    <!-- ══════════════════════════════════════════════════════
     VUE : ÉTAT DES LIEUX APRÈS
═══════════════════════════════════════════════════════ -->
    <header>
      <div>
        <div class="logo">NOMADRIVE</div>
        <div class="subtitle">État des lieux — Retour</div>
      </div>
      <a href="dashboard.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Retour</a>
    </header>
    <div class="main">
      <?php $d = $dossier_detail; ?>
      <div class="card" style="background:linear-gradient(135deg,#e8f4fd,#f0f9ff);border-color:#b3d9f5;">
        <strong><?= htmlspecialchars($d['prenom'] . ' ' . $d['nom']) ?></strong>
        &nbsp;·&nbsp; <?= htmlspecialchars($d['marque'] . ' ' . $d['modele'] . ' — ' . $d['immatriculation']) ?>
        &nbsp;·&nbsp; <span style="color:var(--muted)"><?= htmlspecialchars($d['contrat_ref']) ?></span>
        <?php if (!empty($d['etat_avant_km'])): ?>
          &nbsp;·&nbsp; <span style="color:var(--muted)">Départ : <?= $d['etat_avant_km'] ?> km</span>
        <?php endif; ?>
      </div>

      <div class="card">
        <div class="card-title"><i class="fa-duotone fa-solid fa-gauge"></i> Kilométrage retour</div>
        <div class="form-group">
          <input type="number" id="km-apres" placeholder="Kilométrage actuel" min="0" inputmode="numeric">
        </div>
        <?php if (!empty($d['etat_avant_km'])): ?>
          <p style="font-size:12px;color:var(--muted)">Départ à <?= $d['etat_avant_km'] ?> km — distance parcourue calculée
            automatiquement.</p>
        <?php endif; ?>
      </div>

      <div class="card">
        <div class="card-title"><i class="fa-duotone fa-solid fa-camera"></i> Photos du retour</div>
        <div class="photo-grid" id="photo-grid-apres"></div>
        <button class="btn btn-secondary btn-block" onclick="addPhoto('apres')" id="btn-add-apres">
          <i class="fa-solid fa-plus"></i> Ajouter une photo
        </button>
        <input type="hidden" id="photos-json-apres" value="[]">
      </div>

      <div class="card">
        <div class="card-title"><i class="fa-duotone fa-solid fa-note-sticky"></i> Observations retour</div>
        <div class="form-group">
          <textarea id="notes-apres"
            placeholder="État général, dommages constatés, niveau de batterie, remarques…"></textarea>
        </div>
      </div>

      <div class="card">
        <div class="card-title"><i class="fa-duotone fa-solid fa-credit-card"></i> Caution</div>
        <div class="radio-group" style="margin-bottom:14px">
          <label class="radio-btn selected-lib" id="lbl-lib" onclick="selectCaution('lib')">
            <input type="radio" name="caution" value="1" checked> ✅ Libérée intégralement
          </label>
          <label class="radio-btn" id="lbl-ret" onclick="selectCaution('ret')">
            <input type="radio" name="caution" value="0"> ❌ Montant retenu
          </label>
        </div>
        <div id="retenu-wrap" style="display:none">
          <div class="form-group">
            <label>Montant retenu (€)</label>
            <input type="number" id="caution-retenu" placeholder="ex : 150" min="0" max="500" inputmode="decimal">
          </div>
        </div>
        <div class="form-group">
          <label>Note caution (optionnel)</label>
          <textarea id="caution-note" placeholder="Raison, dommage constaté…" style="min-height:60px"></textarea>
        </div>
      </div>

      <button class="btn btn-primary btn-block" id="btn-submit-apres" onclick="submitEtat('apres', <?= $d['id'] ?>)">
        <i class="fa-solid fa-flag-checkered"></i> Clôturer le dossier
      </button>
    </div>

  <?php else: ?>
    <!-- ══════════════════════════════════════════════════════
     VUE : DASHBOARD
═══════════════════════════════════════════════════════ -->
    <header>
      <div>
        <div class="logo">NOMADRIVE</div>
        <div class="subtitle">Tableau de bord</div>
      </div>
      <div style="display:flex;gap:8px;align-items:center">
        <a href="contrat.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-file-signature"></i> Nouveau
          contrat</a>
        <form method="POST" style="margin:0">
          <input type="hidden" name="action" value="logout">
          <button type="submit" class="btn btn-secondary btn-sm"><i class="fa-solid fa-right-from-bracket"></i>
            Déconnexion</button>
        </form>
      </div>
    </header>
    <div class="main">

      <!-- Statut des véhicules -->
      <div class="section-title"><i class="fa-duotone fa-solid fa-car-side"></i> Véhicules</div>
      <div class="vehicle-grid">
        <?php foreach ($all_vehicules as $v): ?>
          <?php $libre = empty($v['dossier_id']); ?>
          <?php if ($libre): ?><a href="contrat.php?vehicule=<?= $v['id'] ?>" class="vehicle-card libre" style="display:block;text-decoration:none;"><?php else: ?><div class="vehicle-card occupe"><?php endif; ?>
            <span class="vehicle-badge <?= $libre ? 'libre' : 'occupe' ?>"><?= $libre ? 'Libre' : 'En location' ?></span>
            <div class="vehicle-name"><?= htmlspecialchars($v['marque'] . ' ' . $v['modele']) ?></div>
            <div class="vehicle-immat"><?= htmlspecialchars($v['immatriculation']) ?></div>
            <?php if ($libre): ?><div style="font-size:11px;color:var(--blue);margin-top:6px;font-weight:500;">+ Nouveau contrat</div><?php endif; ?>
          <?php if ($libre): ?></a><?php else: ?></div><?php endif; ?>
        <?php endforeach; ?>
        <?php if (empty($all_vehicules)): ?>
          <div style="grid-column:1/-1;color:var(--muted);font-size:13px">Aucun véhicule configuré.</div>
        <?php endif; ?>
      </div>

      <!-- Dossiers ouverts -->
      <div class="section-title"><i class="fa-duotone fa-solid fa-folder-open"></i> Dossiers ouverts
        (<?= count($open_dossiers) ?>)</div>
      <?php if (empty($open_dossiers)): ?>
        <div class="card">
          <div class="empty-state"><i class="fa-regular fa-circle-check"
              style="font-size:32px;color:var(--green);display:block;margin-bottom:10px"></i>Aucun dossier ouvert — tous les
            véhicules sont libres.</div>
        </div>
      <?php else: ?>
        <div class="card">
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Réf.</th>
                  <th>Véhicule</th>
                  <th>Client</th>
                  <th>Départ</th>
                  <th>Statut</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($open_dossiers as $d): ?>
                  <?php
                  $has_avant = !empty($d['etat_avant_at']);
                  $has_apres = !empty($d['etat_apres_at']);
                  if (!$has_avant) {
                    $badge = '<span class="badge badge-new">Nouveau</span>';
                  } elseif ($has_avant && !$has_apres) {
                    $badge = '<span class="badge badge-avant">En cours</span>';
                  } else {
                    $badge = '<span class="badge badge-ready">Retour enregistré</span>';
                  }
                  ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($d['contrat_ref']) ?></strong></td>
                    <td><?= htmlspecialchars($d['marque'] . ' ' . $d['modele']) ?><br><span
                        style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($d['immatriculation']) ?></span></td>
                    <td><?= htmlspecialchars($d['prenom'] . ' ' . $d['nom']) ?></td>
                    <td style="font-size:12px">
                      <?= htmlspecialchars($d['date_debut'] ?? '—') ?><br><?= htmlspecialchars($d['heure_debut'] ?? '') ?>
                    </td>
                    <td><?= $badge ?></td>
                    <td>
                      <div style="display:flex;gap:6px;flex-wrap:wrap">
                        <a href="dashboard.php?view=dossier_detail&dossier=<?= $d['id'] ?>" class="btn btn-secondary btn-sm">
                          <i class="fa-solid fa-eye"></i> Consulter
                        </a>
                        <a href="dashboard.php?view=etat_apres&dossier=<?= $d['id'] ?>" class="btn btn-sm"
                          style="background:#fff3cd;color:#856404;border:1px solid #ffeeba">
                          <i class="fa-solid fa-flag-checkered"></i> Clôturer
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

      <!-- Dossiers fermés (24h) -->
      <?php if (!empty($closed_recent)): ?>
        <div class="section-title" style="margin-top:8px">
          <i class="fa-duotone fa-solid fa-folder-closed"></i> Fermés (dernières 24h)
          <a href="dashboard.php?view=dossiers_fermes" class="btn btn-secondary btn-sm" style="margin-left:auto;font-size:12px;">Voir tout</a>
        </div>
        <div class="card">
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Réf.</th>
                  <th>Véhicule</th>
                  <th>Client</th>
                  <th>Fermé le</th>
                  <th>Caution</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($closed_recent as $d): ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($d['contrat_ref']) ?></strong></td>
                    <td><?= htmlspecialchars($d['marque'] . ' ' . $d['modele']) ?></td>
                    <td><?= htmlspecialchars($d['prenom'] . ' ' . $d['nom']) ?></td>
                    <td style="font-size:12px"><?= $d['closed_at'] ? date('d/m H:i', strtotime($d['closed_at'])) : '—' ?></td>
                    <td>
                      <?php if ($d['caution_liberee'] === null): ?>
                        <span class="badge badge-done">—</span>
                      <?php elseif ($d['caution_liberee']): ?>
                        <span class="badge badge-caution-ok">✅ Libérée</span>
                      <?php else: ?>
                        <span class="badge badge-caution-ko">❌
                          <?= $d['caution_retenu'] ? number_format($d['caution_retenu'], 0, '.', '') . ' €' : 'Retenue' ?></span>
                      <?php endif; ?>
                    </td>
                    <td><a href="dashboard.php?view=dossier_detail&dossier=<?= $d['id'] ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-eye"></i></a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

    </div>
  <?php endif; ?>

  <!-- ── Modale caméra ──────────────────────────────────────────────────────── -->
  <div class="modal-overlay" id="camera-modal">
    <video id="camera-video" autoplay playsinline></video>
    <div class="camera-controls">
      <button class="btn-cancel-camera" onclick="closeCamera()">Annuler</button>
      <button class="btn-capture" id="btn-capture" title="Prendre la photo"></button>
    </div>
  </div>
  <canvas id="capture-canvas" style="display:none"></canvas>
  <div id="toast"></div>

  <script>
    // ── État global ────────────────────────────────────────────────────────────────
    const state = {
      photos: { avant: [], apres: [] },
      currentTarget: null,
      cameraStream: null,
    };

    // ── Login ──────────────────────────────────────────────────────────────────────
    async function doLogin() {
      const pwd = document.getElementById('pwd')?.value;
      const btn = document.getElementById('btn-login');
      if (!pwd) { showToast('Saisissez le mot de passe.'); return; }
      btn.disabled = true;
      btn.innerHTML = '<div class="spinner"></div>';
      try {
        const fd = new FormData();
        fd.append('action', 'login');
        fd.append('password', pwd);
        const r = await fetch('dashboard.php', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) {
          window.location.href = 'dashboard.php';
        } else {
          showToast(d.message || 'Mot de passe incorrect.');
          btn.disabled = false;
          btn.innerHTML = '<i class="fa-solid fa-lock"></i> Connexion';
        }
      } catch (e) { showToast('Erreur réseau.'); btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-lock"></i> Connexion'; }
    }
    document.getElementById('pwd')?.addEventListener('keydown', e => { if (e.key === 'Enter') doLogin(); });

    // ── Photos ────────────────────────────────────────────────────────────────────
    function addPhoto(target) {
      if (state.photos[target] && state.photos[target].length >= 8) { showToast('Maximum 8 photos.'); return; }
      state.currentTarget = target;
      if (navigator.mediaDevices?.getUserMedia) {
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
          .then(stream => {
            state.cameraStream = stream;
            document.getElementById('camera-video').srcObject = stream;
            document.getElementById('camera-modal').classList.add('open');
          })
          .catch(() => openFileFallback(target));
      } else { openFileFallback(target); }
    }

    function openFileFallback(target) {
      let inp = document.getElementById('file-input-' + target);
      if (!inp) {
        inp = document.createElement('input');
        inp.type = 'file'; inp.accept = 'image/*'; inp.capture = 'environment';
        inp.id = 'file-input-' + target; inp.style.display = 'none';
        document.body.appendChild(inp);
        inp.addEventListener('change', function () {
          const file = this.files[0]; if (!file) return;
          const reader = new FileReader();
          reader.onload = e => applyPhoto(e.target.result);
          reader.readAsDataURL(file);
        });
      }
      inp.click();
    }

    document.getElementById('btn-capture')?.addEventListener('click', () => {
      const video = document.getElementById('camera-video');
      const canvas = document.getElementById('capture-canvas');
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      canvas.getContext('2d').drawImage(video, 0, 0);
      applyPhoto(canvas.toDataURL('image/jpeg', 0.85));
      closeCamera();
    });

    function closeCamera() {
      state.cameraStream?.getTracks().forEach(t => t.stop());
      state.cameraStream = null;
      document.getElementById('camera-modal').classList.remove('open');
    }

    function applyPhoto(dataUrl) {
      const t = state.currentTarget;
      if (!t || !state.photos[t]) return;
      const idx = state.photos[t].length;
      state.photos[t].push(dataUrl);
      updatePhotosField(t);
      renderPhotoGrid(t);
    }

    function removePhoto(target, idx) {
      state.photos[target].splice(idx, 1);
      updatePhotosField(target);
      renderPhotoGrid(target);
    }

    function updatePhotosField(target) {
      const el = document.getElementById('photos-json-' + target);
      if (el) el.value = JSON.stringify(state.photos[target]);
    }

    function renderPhotoGrid(target) {
      const grid = document.getElementById('photo-grid-' + target);
      if (!grid) return;
      grid.innerHTML = '';
      state.photos[target].forEach((src, i) => {
        const div = document.createElement('div');
        div.className = 'photo-thumb';
        div.innerHTML = `<img src="${src}"><button class="del-photo" onclick="removePhoto('${target}',${i})">✕</button>`;
        grid.appendChild(div);
      });
      const addBtn = document.getElementById('btn-add-' + target);
      if (addBtn) addBtn.style.display = state.photos[target].length >= 8 ? 'none' : '';
    }

    // ── Caution selector ──────────────────────────────────────────────────────────
    function selectCaution(choice) {
      document.getElementById('lbl-lib').className = 'radio-btn' + (choice === 'lib' ? ' selected-lib' : '');
      document.getElementById('lbl-ret').className = 'radio-btn' + (choice === 'ret' ? ' selected-ret' : '');
      document.getElementById('retenu-wrap').style.display = choice === 'ret' ? 'block' : 'none';
    }

    // ── Submit état des lieux ─────────────────────────────────────────────────────
    async function submitEtat(type, dossierId) {
      const btn = document.getElementById('btn-submit-' + type);
      const km = document.getElementById('km-' + type)?.value || '0';

      const fd = new FormData();
      fd.append('action', 'save_etat_' + type);
      fd.append('dossier_id', dossierId);
      fd.append('km', km);
      fd.append('notes', document.getElementById('notes-' + type)?.value || '');
      fd.append('photos_json', document.getElementById('photos-json-' + type)?.value || '[]');

      if (type === 'apres') {
        const libCheck = document.querySelector('input[name="caution"]:checked');
        fd.append('caution_liberee', libCheck ? libCheck.value : '1');
        fd.append('caution_retenu', document.getElementById('caution-retenu')?.value || '0');
        fd.append('caution_note', document.getElementById('caution-note')?.value || '');
      }

      btn.disabled = true;
      btn.innerHTML = '<div class="spinner"></div> Enregistrement…';

      try {
        const r = await fetch('dashboard.php', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) {
          window.location.href = 'dashboard.php';
        } else {
          showToast(d.message || 'Erreur lors de l\'enregistrement.');
          btn.disabled = false;
          btn.innerHTML = type === 'avant'
            ? '<i class="fa-solid fa-check"></i> Valider l\'état des lieux départ'
            : '<i class="fa-solid fa-flag-checkered"></i> Clôturer le dossier';
        }
      } catch (e) {
        showToast('Erreur réseau.'); btn.disabled = false;
      }
    }

    // ── Toast ─────────────────────────────────────────────────────────────────────
    let toastTimer;
    function showToast(msg, type = 'error') {
      const t = document.getElementById('toast');
      t.textContent = msg;
      t.className = type === 'success' ? 'success show' : 'show';
      clearTimeout(toastTimer);
      toastTimer = setTimeout(() => t.classList.remove('show'), 3800);
    }
  </script>
</body>

</html>