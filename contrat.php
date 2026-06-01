<?php
if (session_status() === PHP_SESSION_NONE)
  session_start();

// ─── Configuration ────────────────────────────────────────────────────────────
// CAUTION_MONTANT est défini dans config.php (chargé depuis nomadrive_settings)

$madiDir = '/var/www/html/madi.mt';
if (!is_dir($madiDir))
  $madiDir = dirname(__DIR__); // fallback local
require_once $madiDir . '/vendor/autoload.php';
require_once $madiDir . '/php/fonctions.php';
require_once $madiDir . '/php/config.php';
require_once __DIR__ . '/config.php';
$db1->query("SET NAMES 'utf8mb4'");

require_once __DIR__ . '/includes/nomadrive_auth.php';

// ── Lien pré-arrivée sécurisé (token HMAC) ────────────────────────────────────
function ndContratToken(int $id): string {
  return substr(hash_hmac('sha256', (string)$id, MANAGE_PASSWORD), 0, 24);
}
$link_cid   = (int)($_GET['cid'] ?? $_POST['link_cid'] ?? 0);
$link_token = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['token'] ?? $_POST['link_token'] ?? '');
$link_mode  = false;
$link_data  = null;
if ($link_cid > 0 && strlen($link_token) === 24
    && hash_equals(ndContratToken($link_cid), $link_token)) {
  $ls = $db1->prepare("SELECT id, nom, prenom, email, vehicule_id, vehicule, date_debut, heure_debut FROM nomadrive_contrats WHERE id = ?");
  $ls->execute([$link_cid]);
  $link_data = $ls->fetch(PDO::FETCH_ASSOC);
  if ($link_data) $link_mode = true;
}

// ─── Chargement des véhicules ─────────────────────────────────────────────────
$vehicules_list         = []; // tous (pour lookup POST)
$vehicules_occupes_ids  = []; // IDs avec dossier ouvert
try {
  $res = $db1->query("SELECT id, immatriculation, marque, modele FROM nomadrive_vehicules ORDER BY id");
  if ($res) $vehicules_list = $res->fetchAll(PDO::FETCH_ASSOC);

  $occ = $db1->query("SELECT vehicule_id FROM nomadrive_dossiers WHERE statut = 'ouvert'");
  if ($occ) $vehicules_occupes_ids = array_column($occ->fetchAll(PDO::FETCH_ASSOC), 'vehicule_id');
} catch (\Exception $e) {
  // Table absente ou erreur DB — on continue avec liste vide
}
$preselect_vehicule_id = (int)($_GET['vehicule'] ?? 0);

// ─── Dernier dossier fermé par véhicule (historique pour état avant) ──────────
$last_dossiers = [];
try {
  $hist = $db1->query("
    SELECT d.vehicule_id, d.etat_apres_km, d.etat_apres_notes, d.closed_at
    FROM nomadrive_dossiers d
    INNER JOIN (
      SELECT vehicule_id, MAX(id) AS max_id
      FROM nomadrive_dossiers
      WHERE statut = 'ferme' AND etat_apres_at IS NOT NULL
      GROUP BY vehicule_id
    ) latest ON d.id = latest.max_id
  ");
  if ($hist) {
    foreach ($hist->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $last_dossiers[(int)$row['vehicule_id']] = $row;
    }
  }
} catch (\Exception $e) {}

// ─── Planning du jour ─────────────────────────────────────────────────────────
$planningHour  = (int)date('H');
$planAutoSlot  = $planningHour < 12 ? '10:00' : ($planningHour < 16 ? '14:00' : 'soir');

$planVehicles  = [];
try {
  $pv = $db1->query("SELECT id, marque, modele, immatriculation, couleur FROM nomadrive_vehicules WHERE actif = 1 AND guide = 0 ORDER BY immatriculation");
  if ($pv) $planVehicles = $pv->fetchAll(PDO::FETCH_ASSOC);
} catch (\Exception $e) {}

function planInterleave(array $vehicles): array {
  $groups = [];
  foreach ($vehicles as $v) $groups[$v['modele']][] = $v;
  if (!$groups) return [];
  $result = []; $maxLen = max(array_map('count', $groups));
  foreach (array_keys($groups) as $m) sort($groups[$m]);
  for ($i = 0; $i < $maxLen; $i++) foreach ($groups as $list) if (isset($list[$i])) $result[] = $list[$i];
  return $result;
}

$planInterleaved = planInterleave($planVehicles);
$planData = []; // [slotKey => [ ['id','first_name','last_name','email','pax','product','vehicles':[]] ]]

try {
  $pb = $db1->query("
    SELECT DATE_FORMAT(start_datetime,'%H:%i') AS slot,
           id, first_name, last_name, email, participants, product_name
    FROM nomadrive_customers
    WHERE booking_status = 'CONFIRMED'
      AND DATE(start_datetime) = CURDATE()
      AND product_id IN (1194328, 1197812)
    ORDER BY slot, product_id, id
  ");
  if ($pb) {
    $n = count($planInterleaved);
    $offset = $n > 0 ? abs(crc32(date('Y-m-d'))) % $n : 0;
    $vIdx = $offset;
    $prevSlot = null;
    foreach ($pb->fetchAll(PDO::FETCH_ASSOC) as $b) {
      $slotKey = $b['slot'] < '16:00' ? $b['slot'] : 'soir';
      if ($slotKey !== $prevSlot) { $vIdx = $offset; $prevSlot = $slotKey; } // reset per slot
      $pax = max(1, (int)$b['participants']);
      $groups = (int)ceil($pax / 2);
      $vehs = [];
      for ($g = 0; $g < $groups; $g++) {
        if ($n > 0) $vehs[] = $planInterleaved[$vIdx % $n];
        $vIdx++;
      }
      $planData[$slotKey][] = [
        'id'         => $b['id'],
        'first_name' => $b['first_name'],
        'last_name'  => $b['last_name'],
        'email'      => $b['email'] ?? '',
        'pax'        => $pax,
        'product'    => $b['product_name'] ?? '',
        'vehicles'   => $vehs,
      ];
    }
  }
} catch (\Exception $e) {}

// ─── Helper upload photos état des lieux ─────────────────────────────────────
function uploadEtatAvantPhotos(array $b64list, string $ref, string $bucket, string $base): array {
  $urls = [];
  foreach ($b64list as $i => $b64) {
    if (empty($b64) || !preg_match('/^data:(image\/\w+);base64,(.+)$/s', $b64, $m)) continue;
    $ext = $m[1] === 'image/png' ? 'png' : 'jpg';
    $tmp = tempnam(sys_get_temp_dir(), 'nd_avant_') . ".$ext";
    file_put_contents($tmp, base64_decode($m[2]));
    $gcpPath = "nomadrive/dossiers/{$ref}-avant-" . ($i + 1) . ".$ext";
    upload_object($bucket, $gcpPath, $tmp, 1);
    @unlink($tmp);
    $urls[] = "$base/$gcpPath";
  }
  return $urls;
}

// ─── Auth guard ───────────────────────────────────────────────────────────────
if (!ndIsAuth($db1) && !$link_mode) {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Session expirée. Veuillez vous reconnecter.']);
    exit;
  }
  header('Location: dashboard.php?view=login');
  exit;
}

// ─── Action : redirect vers Stripe Checkout (link_mode, GET) ─────────────────
if ($link_mode && ($_GET['action'] ?? '') === 'stripe_redirect') {
  // Réutilise la session existante si encore active
  $scRow = $db1->prepare("SELECT checkout_url FROM nomadrive_stripe_cautions WHERE contrat_id=? AND status IN ('pending','authorized') AND checkout_url IS NOT NULL ORDER BY id DESC LIMIT 1");
  $scRow->execute([$link_cid]);
  $existingUrl = $scRow->fetchColumn();
  if ($existingUrl) {
    header('Location: ' . $existingUrl);
    exit;
  }
  $stripeKey = STRIPE_MODE === 'live' ? NDR_STRIPE_LIVE_SECRET_KEY : NDR_STRIPE_TEST_SECRET_KEY;
  \Stripe\Stripe::setApiKey($stripeKey);
  $cautionCents = (int)STRIPE_CAUTION_AMOUNT;
  $baseUrl  = 'https://nomadrive.fr';
  $qs       = 'cid=' . $link_cid . '&token=' . urlencode($link_token);
  $successU = $baseUrl . '/contrat.php?' . $qs . '&caution=ok';
  $cancelU  = $baseUrl . '/contrat.php?' . $qs . '&caution=cancel';
  try {
    $session = \Stripe\Checkout\Session::create([
      'payment_method_types' => ['card'],
      'mode'                 => 'payment',
      'payment_intent_data'  => ['capture_method' => 'manual'],
      'customer_email'       => $link_data['email'],
      'line_items'           => [[
        'price_data' => [
          'currency'     => 'eur',
          'unit_amount'  => $cautionCents,
          'product_data' => [
            'name'        => 'Caution NOMADRIVE — pré-autorisation',
            'description' => 'Tour du ' . ($link_data['date_debut'] ?? '') . ' à ' . substr($link_data['heure_debut'] ?? '', 0, 5),
          ],
        ],
        'quantity' => 1,
      ]],
      'success_url' => $successU,
      'cancel_url'  => $cancelU,
    ]);
    // Mettre à jour la ligne tracking du cron si elle existe, sinon insérer
    $trackRow = $db1->prepare("SELECT id FROM nomadrive_stripe_cautions WHERE contrat_id=? AND stripe_session_id IS NULL ORDER BY id DESC LIMIT 1");
    $trackRow->execute([$link_cid]);
    if ($tid = $trackRow->fetchColumn()) {
      $db1->prepare("UPDATE nomadrive_stripe_cautions SET stripe_session_id=?, amount=?, status='pending', checkout_url=? WHERE id=?")
          ->execute([$session->id, $cautionCents, $session->url, $tid]);
    } else {
      $db1->prepare("INSERT INTO nomadrive_stripe_cautions (contrat_id, stripe_session_id, amount, status, checkout_url) VALUES (?,?,?,'pending',?)")
          ->execute([$link_cid, $session->id, $cautionCents, $session->url]);
    }
    header('Location: ' . $session->url);
  } catch (\Exception $e) {
    error_log('[NOMADRIVE] contrat.php stripe_redirect: ' . $e->getMessage());
    header('Location: ' . $cancelU . '&err=stripe&errmsg=' . urlencode(substr($e->getMessage(), 0, 200)));
  }
  exit;
}

// ─── Action : sauvegarde permis avant redirect Stripe (AJAX, link_mode) ───────
if ($link_mode && ($_POST['action'] ?? '') === 'save_permis') {
  header('Content-Type: application/json');
  $ref_pm  = 'ND-' . str_pad($link_cid, 5, '0', STR_PAD_LEFT);
  $bkt_pm  = 'madi_bucket';
  $base_pm = "https://storage.googleapis.com/$bkt_pm";
  $tok_pm  = substr(bin2hex(random_bytes(6)), 0, 8);
  $saved   = [];
  foreach (['recto', 'verso'] as $side) {
    $b64 = $_POST['permis_' . $side] ?? '';
    if (empty($b64) || !preg_match('/^data:(image\/\w+);base64,(.+)$/s', $b64, $m)) continue;
    $ext = $m[1] === 'image/png' ? 'png' : 'jpg';
    $tmp = tempnam(sys_get_temp_dir(), 'nd_pm_') . ".$ext";
    file_put_contents($tmp, base64_decode($m[2]));
    $gpath = "nomadrive/permis/{$ref_pm}-{$side}-{$tok_pm}.{$ext}";
    upload_object($bkt_pm, $gpath, $tmp, 1);
    @unlink($tmp);
    $saved[$side] = "$base_pm/$gpath";
  }
  if (!empty($saved)) {
    $sets   = array_map(fn($s) => "url_permis_{$s} = ?", array_keys($saved));
    $params = array_merge(array_values($saved), [$link_cid]);
    $db1->prepare("UPDATE nomadrive_contrats SET " . implode(', ', $sets) . " WHERE id=?")->execute($params);
  }
  echo json_encode(['success' => true]);
  exit;
}

// ─── Traitement du formulaire (envoi email + BDD) ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_contract') {
  header('Content-Type: application/json');
  // Garantit qu'on retourne toujours du JSON même en cas de fatal error
  set_error_handler(function ($errno, $errstr) {
    echo json_encode(['success' => false, 'message' => "Erreur PHP : $errstr"]);
    exit;
  });
  register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
      if (!headers_sent())
        header('Content-Type: application/json');
      echo json_encode(['success' => false, 'message' => 'Erreur serveur : ' . $e['message']]);
    }
  });

  $nom = strip_tags(trim($_POST['nom'] ?? ''));
  $prenom = strip_tags(trim($_POST['prenom'] ?? ''));
  $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
  $adresse = strip_tags(trim($_POST['adresse'] ?? ''));
  $vehicule_id = (int) ($_POST['vehicule'] ?? 0);
  $date_debut = strip_tags(trim($_POST['date_debut'] ?? ''));
  $heure_debut = strip_tags(trim($_POST['heure_debut'] ?? ''));
  $dossier_empreinte = strip_tags(trim($_POST['dossier_empreinte'] ?? ''));

  // Récupérer le libellé du véhicule
  $vehicule = '';
  foreach ($vehicules_list as $v) {
    if ((int) $v['id'] === $vehicule_id) {
      $vehicule = '#' . $v['id'] . ' — ' . $v['marque'] . ' ' . $v['modele'] . ' — ' . $v['immatriculation'];
      break;
    }
  }
  $signature = $_POST['signature'] ?? '';       // base64 PNG
  $permis_r = $_POST['permis_recto'] ?? '';    // base64
  $permis_v = $_POST['permis_verso'] ?? '';    // base64
  $empreinte_photo = $_POST['empreinte_photo'] ?? ''; // base64

  $smtpUsername = SMTP_USERNAME;
  $smtpPassword = SMTP_PASSWORD;

  if (empty($nom) || empty($prenom) || !$email || empty($signature)) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes.']);
    exit;
  }

  $date_contrat = date('d/m/Y');
  $nom_complet = htmlspecialchars("$prenom $nom");

  // ── 1. INSERT ou UPDATE selon le mode ────────────────────────────────────
  $file_token = substr(bin2hex(random_bytes(6)), 0, 8); // token anti-enumeration
  if ($link_mode) {
    // Lien pré-arrivée : on met à jour le contrat existant (signature + infos client)
    $contrat_id  = $link_cid;
    $contrat_ref = 'ND-' . str_pad($contrat_id, 5, '0', STR_PAD_LEFT);
    $db1->prepare("UPDATE nomadrive_contrats SET nom=?, prenom=?, email=?, adresse=?, dossier_empreinte=?, signature=? WHERE id=?")
        ->execute([$nom, $prenom, $email, $adresse, $dossier_empreinte ?: null, $signature, $contrat_id]);
    // Récupérer vehicule_id depuis le contrat existant pour la suite du handler
    $vehicule_id = (int)($link_data['vehicule_id'] ?? 0);
    $vehicule    = $link_data['vehicule'] ?? '';
    $date_debut  = $link_data['date_debut'] ?? '';
    $heure_debut = $link_data['heure_debut'] ?? '';
  } else {
    $stmt = $db1->prepare("
          INSERT INTO nomadrive_contrats
              (nom, prenom, email, adresse,
               vehicule_id, vehicule, date_debut, heure_debut, dossier_empreinte,
               signature, created_at)
          VALUES
              (:nom, :prenom, :email, :adresse,
               :vehicule_id, :vehicule, :date_debut, :heure_debut, :dossier_empreinte,
               :signature, NOW())
      ");
    $stmt->bindValue(':nom', $nom, PDO::PARAM_STR);
    $stmt->bindValue(':prenom', $prenom, PDO::PARAM_STR);
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->bindValue(':adresse', $adresse, PDO::PARAM_STR);
    $stmt->bindValue(':vehicule_id', $vehicule_id, PDO::PARAM_INT);
    $stmt->bindValue(':vehicule', $vehicule, PDO::PARAM_STR);
    $stmt->bindValue(':date_debut', $date_debut ?: null, $date_debut ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':heure_debut', $heure_debut ?: null, $heure_debut ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':dossier_empreinte', $dossier_empreinte ?: null, $dossier_empreinte ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':signature', $signature, PDO::PARAM_STR);
    $stmt->execute();
    $contrat_id  = $db1->lastInsertId();
    $contrat_ref = 'ND-' . str_pad($contrat_id, 5, '0', STR_PAD_LEFT);
  }

  // ── 2. Upload photos permis sur GCP ───────────────────────────────────────
  $gcp_bucket = 'madi_bucket';
  $gcp_base_url = "https://storage.googleapis.com/$gcp_bucket";
  $url_permis_r = null;
  $url_permis_v = null;

  $uploadPhoto = function (string $b64, string $suffix) use ($gcp_bucket, $gcp_base_url, $contrat_ref, $file_token): ?string {
    // Extraire le type MIME et décoder
    if (!preg_match('/^data:(image\/\w+);base64,(.+)$/s', $b64, $m))
      return null;
    $ext = $m[1] === 'image/png' ? 'png' : 'jpg';
    $binData = base64_decode($m[2]);
    $tmpFile = tempnam(sys_get_temp_dir(), 'nd_permis_') . '.' . $ext;
    file_put_contents($tmpFile, $binData);
    $gcpPath = "nomadrive/permis/{$contrat_ref}-{$suffix}-{$file_token}.{$ext}";
    upload_object($gcp_bucket, $gcpPath, $tmpFile, 1);
    @unlink($tmpFile);
    return "$gcp_base_url/$gcpPath";
  };

  if (!empty($permis_r))
    $url_permis_r = $uploadPhoto($permis_r, 'recto');
  if (!empty($permis_v))
    $url_permis_v = $uploadPhoto($permis_v, 'verso');
  if (!empty($empreinte_photo))
    $uploadPhoto($empreinte_photo, 'empreinte'); // upload GCP, pas stocké en BDD

  // ── 3. Génération du HTML complet du contrat (pour PDF) ───────────────────
  $permis_html = '';
  if (!empty($permis_r)) {
    $permis_html .= '<h3 style="color:#0077b6;font-size:15px;border-left:3px solid #0077b6;padding-left:10px;">Permis de conduire</h3>';
    $permis_html .= '<table style="width:100%;"><tr>';
    $permis_html .= '<td style="width:50%;padding:8px;text-align:center;"><p style="font-size:12px;color:#555;margin:0 0 4px;">Recto</p><img src="' . $permis_r . '" style="max-width:250px;"/></td>';
    if (!empty($permis_v))
      $permis_html .= '<td style="width:50%;padding:8px;text-align:center;"><p style="font-size:12px;color:#555;margin:0 0 4px;">Verso</p><img src="' . $permis_v . '" style="max-width:250px;"/></td>';
    $permis_html .= '</tr></table>';
  }
  if (!empty($empreinte_photo)) {
    $permis_html .= '<h3 style="color:#0077b6;font-size:15px;border-left:3px solid #0077b6;padding-left:10px;margin-top:14px;">Ticket empreinte bancaire</h3>';
    $permis_html .= '<div style="text-align:center;padding:8px;">';
    if (!empty($dossier_empreinte))
      $permis_html .= '<p style="font-size:12px;color:#555;margin:0 0 6px;">N° dossier : <strong>' . htmlspecialchars($dossier_empreinte) . '</strong></p>';
    $permis_html .= '<img src="' . $empreinte_photo . '" style="max-width:300px;"/></div>';
  }

  $caution_str = CAUTION_MONTANT . ' €';
  $pdf_vehicule_row  = $link_mode ? '' : "<tr><td style=\"padding:4px 8px;background:#f9f9f9;width:40%;\">Véhicule loué</td><td style=\"padding:4px 8px;background:#f9f9f9;\">{$vehicule}</td></tr>";
  $pdf_empreinte_row = $link_mode ? '' : "<tr><td style=\"padding:4px 8px;background:#f9f9f9;\">N° dossier empreinte</td><td style=\"padding:4px 8px;background:#f9f9f9;\">{$dossier_empreinte}</td></tr>";

  $pdf_html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;color:#222;padding:20px;">
  <div style="text-align:center;margin-bottom:20px;">
    <h1 style="color:#0077b6;margin:0;font-size:22px;">NOMADRIVE</h1>
    <p style="margin:2px 0;color:#555;font-size:11px;">NICE ACTIVITY — SAS au capital de 100 000 € — RCS Nice 994 620 615</p>
    <p style="margin:2px 0;color:#555;font-size:11px;">2 Place Guynemer, 06300 Nice — contact@nomadrive.fr — www.nomadrive.fr</p>
    <h2 style="margin:14px 0 0;font-size:16px;border-bottom:2px solid #0077b6;padding-bottom:6px;">CONTRAT DE LOCATION</h2>
  </div>

  <table style="width:100%;border-collapse:collapse;margin-bottom:16px;font-size:13px;">
    <tr><td style="padding:5px 8px;background:#f0f8ff;font-weight:bold;width:40%;">N° de contrat</td>
        <td style="padding:5px 8px;background:#f0f8ff;">{$contrat_ref}</td></tr>
    <tr><td style="padding:5px 8px;">Date</td>
        <td style="padding:5px 8px;">{$date_contrat}</td></tr>
  </table>

  <h3 style="color:#0077b6;font-size:13px;border-left:3px solid #0077b6;padding-left:8px;">Locataire</h3>
  <table style="width:100%;border-collapse:collapse;margin-bottom:16px;font-size:13px;">
    <tr><td style="padding:4px 8px;background:#f9f9f9;width:40%;">Nom complet</td>
        <td style="padding:4px 8px;background:#f9f9f9;">{$nom_complet}</td></tr>
    <tr><td style="padding:4px 8px;">Adresse</td>
        <td style="padding:4px 8px;">{$adresse}</td></tr>
    <tr><td style="padding:4px 8px;background:#f9f9f9;">Email</td>
        <td style="padding:4px 8px;background:#f9f9f9;">{$email}</td></tr>
  </table>

  <h3 style="color:#0077b6;font-size:13px;border-left:3px solid #0077b6;padding-left:8px;">Véhicule</h3>
  <table style="width:100%;border-collapse:collapse;margin-bottom:16px;font-size:13px;">
    {$pdf_vehicule_row}
    <tr><td style="padding:4px 8px;">Date de départ</td>
        <td style="padding:4px 8px;">{$date_debut} à {$heure_debut}</td></tr>
    {$pdf_empreinte_row}
  </table>

  <div style="border:1px solid #ddd;padding:14px;margin-bottom:20px;font-size:11px;line-height:1.6;">
    <h3 style="margin-top:0;color:#0077b6;font-size:13px;">DÉCLARATION DU CLIENT / CUSTOMER DECLARATION</h3>

    <p style="font-weight:bold;margin-bottom:4px;">DOCUMENTATION <em style="font-weight:normal;">(remise sous forme électronique)</em> — J'ai lu et j'accepte :</p>
    <ul style="margin:0 0 6px;padding-left:16px;">
      <li>Les <strong>Conditions Générales de Location</strong> (ma responsabilité sera engagée en cas de perte ou de dommage au véhicule, voir QR code ci-dessous)</li>
      <li>Les <strong>Conditions Particulières Spécifiques au Pays</strong> (voir QR code ci-dessous)</li>
      <li>Les <strong>Conditions des Véhicules Électriques</strong></li>
      <li>L'<strong>estimation des Frais</strong> (voir au recto)</li>
      <li>La <strong>Fiche d'État du Véhicule</strong></li>
    </ul>
    <p style="color:#888;font-style:italic;margin:2px 0 10px;font-size:10px;">
      <em>DOCUMENTATION (provided electronically): I have read and accept the General Rental Conditions, Country-Specific Conditions, Electric Vehicle Conditions, Estimated Charges and Vehicle Condition Report.</em>
    </p>

    <div style="display:flex;justify-content:center;gap:24px;margin:12px 0;text-align:center;font-size:10px;color:#555;">
      <div>
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=90x90&data=https%3A%2F%2Fnomadrive.fr%2FCGL_Nomadrive_FR.pdf" width="90" height="90" alt="CGV FR"/>
        <div style="margin-top:4px;">Conditions générales (FR)</div>
        <div><a href="https://nomadrive.fr/CGL_Nomadrive_FR.pdf" style="color:#0077b6;">nomadrive.fr/CGL_Nomadrive_FR.pdf</a></div>
      </div>
      <div>
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=90x90&data=https%3A%2F%2Fnomadrive.fr%2FRental_Terms_Nomadrive_EN.pdf" width="90" height="90" alt="CGV EN"/>
        <div style="margin-top:4px;">Rental Terms (EN)</div>
        <div><a href="https://nomadrive.fr/Rental_Terms_Nomadrive_EN.pdf" style="color:#0077b6;">nomadrive.fr/Rental_Terms_Nomadrive_EN.pdf</a></div>
      </div>
    </div>

    <p style="font-weight:bold;margin-bottom:4px;">PAIEMENT ET FRAIS — J'accepte :</p>
    <ul style="margin:0 0 6px;padding-left:16px;">
      <li>De payer les <strong>Frais de Location Estimés</strong></li>
      <li>De payer les éventuels <strong>Frais Supplémentaires</strong> découlant de la location</li>
      <li>De payer les coûts de gestion liés aux dommages, amendes ou infractions routières</li>
      <li>Qu'une <strong>pré-autorisation de {$caution_str}</strong> soit effectuée sur ma carte bancaire</li>
      <li>Que vous puissiez <strong>prélever</strong> toute somme due, y compris la franchise, sans autorisation supplémentaire</li>
    </ul>
    <p style="color:#888;font-style:italic;margin:2px 0 10px;font-size:10px;">
      <em>PAYMENT AND CHARGES: I agree to pay the Estimated Rental Charges and any Additional Charges, authorise a pre-authorisation of {$caution_str} on my payment card, and agree that any amounts owed may be charged without further authorisation.</em>
    </p>

    <p style="font-weight:bold;margin-bottom:4px;">INFORMATIONS DU VÉHICULE — J'accepte :</p>
    <ul style="margin:0 0 6px;padding-left:16px;">
      <li>L'utilisation d'un <strong>système embarqué</strong> pour localiser le véhicule et surveiller son état et sa performance</li>
      <li>D'être contacté en cas de problème de sécurité ou opérationnel détecté sur le véhicule</li>
    </ul>
    <p style="color:#888;font-style:italic;margin:2px 0;font-size:10px;">
      <em>VEHICLE INFORMATION: I agree that an onboard system may be used to locate the vehicle and monitor its condition and performance, and that I may be contacted if a safety or operational issue is detected.</em>
    </p>
  </div>

  <h3 style="color:#0077b6;font-size:13px;border-left:3px solid #0077b6;padding-left:8px;">Signature du locataire</h3>
  <div style="border:1px solid #ccc;padding:8px;margin-bottom:20px;text-align:center;">
    <img src="{$signature}" style="max-width:280px;max-height:130px;"/>
    <p style="margin:4px 0 0;font-size:11px;color:#888;">Signé électroniquement le {$date_contrat}</p>
  </div>

  {$permis_html}

  <hr style="margin:20px 0;border:none;border-top:1px solid #eee;"/>
  <p style="font-size:10px;color:#999;text-align:center;">NICE ACTIVITY (NOMADRIVE) — SAS au capital de 100 000 € — RCS Nice 994 620 615<br>2 Place Guynemer, 06300 Nice — contact@nomadrive.fr — Ce document tient lieu de contrat de location signé électroniquement.</p>
</body></html>
HTML;

  // ── 4 & 5. Génération PDF + upload GCP (optionnel si mPDF absent) ───────────
  $url_pdf = null;
  $pdf_html_content = $pdf_html; // conservé pour pièce jointe email
  $mpdf_available = class_exists('\Mpdf\Mpdf');

  if ($mpdf_available) {
    try {
      $pdf_tmp = tempnam(sys_get_temp_dir(), 'nd_contrat_') . '.pdf';
      $mpdf = new \Mpdf\Mpdf(['tempDir' => '/tmp', 'margin_top' => 10, 'margin_bottom' => 10, 'margin_left' => 12, 'margin_right' => 12]);
      $mpdf->SetTitle("Contrat NOMADRIVE — $contrat_ref");
      $mpdf->WriteHTML($pdf_html_content);
      $mpdf->Output($pdf_tmp, \Mpdf\Output\Destination::FILE);
      $gcpPdfPath = "nomadrive/contrats/{$contrat_ref}-{$file_token}.pdf";
      upload_object($gcp_bucket, $gcpPdfPath, $pdf_tmp, 1);
      $url_pdf = "$gcp_base_url/$gcpPdfPath";
      @unlink($pdf_tmp);
    } catch (\Exception $e) {
      $url_pdf = null; // PDF raté, on continue sans
    }
  }

  // ── 6. Mise à jour BDD avec les URLs GCP ──────────────────────────────────
  $upd = $db1->prepare("
        UPDATE nomadrive_contrats
        SET url_contrat_pdf = :pdf,
            url_permis_recto = COALESCE(:pr, url_permis_recto),
            url_permis_verso = COALESCE(:pv, url_permis_verso)
        WHERE id = :id
    ");
  $upd->execute([':pdf' => $url_pdf, ':pr' => $url_permis_r, ':pv' => $url_permis_v, ':id' => $contrat_id]);

  // ── 6b. Création du dossier + sauvegarde état des lieux avant ────────────
  $etat_avant_km     = (int)($_POST['etat_avant_km'] ?? 0);
  $etat_avant_notes  = strip_tags(trim($_POST['etat_avant_notes'] ?? ''));
  $etat_avant_photos = json_decode($_POST['etat_avant_photos_json'] ?? '[]', true) ?: [];

  if ($vehicule_id > 0 && !$link_mode) {
    $already_open = $db1->prepare("SELECT id FROM nomadrive_dossiers WHERE vehicule_id = :vid AND statut = 'ouvert' LIMIT 1");
    $already_open->execute([':vid' => $vehicule_id]);
    if (!$already_open->fetch()) {
      $dos = $db1->prepare("INSERT INTO nomadrive_dossiers (contrat_id, vehicule_id, statut, created_at) VALUES (:cid, :vid, 'ouvert', NOW())");
      $dos->execute([':cid' => $contrat_id, ':vid' => $vehicule_id]);
      $dossier_id = (int)$db1->lastInsertId();

      if ($dossier_id > 0 && ($etat_avant_km > 0 || !empty($etat_avant_notes) || !empty($etat_avant_photos))) {
        $urls_avant = uploadEtatAvantPhotos($etat_avant_photos, $contrat_ref, $gcp_bucket, $gcp_base_url);
        $db1->prepare("UPDATE nomadrive_dossiers SET etat_avant_km=:km, etat_avant_notes=:n, etat_avant_photos=:p, etat_avant_at=NOW() WHERE id=:id")
            ->execute([':km' => $etat_avant_km ?: null, ':n' => $etat_avant_notes ?: null, ':p' => json_encode($urls_avant), ':id' => $dossier_id]);
      }
    }
  }

  // ── 7. Corps email HTML ───────────────────────────────────────────────────
  $date_fr_email    = !empty($date_debut) ? (new DateTime($date_debut))->format('d/m/Y') : '';
  $heure_str_email  = !empty($heure_debut) ? substr($heure_debut, 0, 5) : '';
  $when_en_email    = $date_fr_email . ($heure_str_email ? ' at ' . $heure_str_email : '');
  $when_fr_email    = $date_fr_email . ($heure_str_email ? ' à ' . $heure_str_email : '');
  $first_name_html  = htmlspecialchars($prenom);
  $email_v_row_en   = $link_mode ? '' : "<tr><td style='padding:6px 12px;color:#64748b;width:40%;'>Vehicle</td><td style='padding:6px 12px;'>" . htmlspecialchars($vehicule) . "</td></tr>";
  $email_v_row_fr   = $link_mode ? '' : "<tr><td style='padding:6px 12px;color:#64748b;width:40%;'>V&eacute;hicule</td><td style='padding:6px 12px;'>" . htmlspecialchars($vehicule) . "</td></tr>";

  $email_body = <<<HTML
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
          <p style="margin:0 0 12px;font-size:18px;font-weight:700;color:#0f172a;">Contract confirmed, {$first_name_html}!</p>
          <p style="margin:0 0 20px;font-size:15px;color:#334155;line-height:1.6;">Your NOMADRIVE rental contract for <strong>{$when_en_email}</strong> is signed. Find it attached as a PDF.</p>
          <table cellpadding="0" cellspacing="0" style="width:100%;margin:0 0 20px;border-collapse:collapse;font-size:13px;">
            <tr style="background:#f8fafc;">
              <td style="padding:6px 12px;color:#64748b;width:40%;">Contract ref.</td>
              <td style="padding:6px 12px;">{$contrat_ref}</td>
            </tr>
            <tr>
              <td style="padding:6px 12px;color:#64748b;">Date</td>
              <td style="padding:6px 12px;">{$when_en_email}</td>
            </tr>
            {$email_v_row_en}
          </table>
          <p style="margin:0;font-size:13px;color:#94a3b8;">Questions? <a href="mailto:contact@nomadrive.fr" style="color:#0077b6;">contact@nomadrive.fr</a></p>
        </td>
      </tr>

      <!-- Separator -->
      <tr>
        <td style="padding:24px 40px;">
          <div style="border-top:1px solid #e2e8f0;"></div>
          <p style="text-align:center;font-size:11px;color:#94a3b8;margin:16px 0;">&mdash; Version fran&ccedil;aise ci-dessous &mdash;</p>
          <div style="border-top:1px solid #e2e8f0;"></div>
        </td>
      </tr>

      <!-- Body FR -->
      <tr>
        <td style="padding:0 40px 40px;">
          <p style="margin:0 0 12px;font-size:18px;font-weight:700;color:#0f172a;">Contrat confirm&eacute;, {$first_name_html}&nbsp;!</p>
          <p style="margin:0 0 20px;font-size:15px;color:#334155;line-height:1.6;">Votre contrat de location NOMADRIVE pour le <strong>{$when_fr_email}</strong> est sign&eacute;. Vous le trouverez en pi&egrave;ce jointe au format PDF.</p>
          <table cellpadding="0" cellspacing="0" style="width:100%;margin:0 0 20px;border-collapse:collapse;font-size:13px;">
            <tr style="background:#f8fafc;">
              <td style="padding:6px 12px;color:#64748b;width:40%;">R&eacute;f. contrat</td>
              <td style="padding:6px 12px;">{$contrat_ref}</td>
            </tr>
            <tr>
              <td style="padding:6px 12px;color:#64748b;">Date</td>
              <td style="padding:6px 12px;">{$when_fr_email}</td>
            </tr>
            {$email_v_row_fr}
          </table>
          <p style="margin:0;font-size:13px;color:#94a3b8;">Une question ? <a href="mailto:contact@nomadrive.fr" style="color:#0077b6;">contact@nomadrive.fr</a></p>
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

  // ── 8. Envoi Sarbacane SMTP ───────────────────────────────────────────────
  $sent = false;
  $send_error = '';
  try {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp-sendkit.sarbacane.com';
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUsername;
    $mail->Password = $smtpPassword;
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';

    $mail->setFrom('contact@nomadrive.fr', 'NOMADRIVE');
    $mail->addReplyTo('contact@nomadrive.fr', 'NOMADRIVE');
    $mail->addAddress(MAIL_TEST_OVERRIDE ?? $email, $nom_complet);
    $mail->addCC('contact@nomadrive.fr', 'NOMADRIVE');

    $mail->isHTML(true);
    $mail->Subject = "Votre contrat de location NOMADRIVE — $contrat_ref";
    $mail->Body = $email_body;

    // PDF en pièce jointe (uniquement si mPDF disponible)
    $pdf_attach = null;
    if ($mpdf_available) {
      try {
        $pdf_attach = tempnam(sys_get_temp_dir(), 'nd_attach_') . '.pdf';
        $mpdf2 = new \Mpdf\Mpdf(['tempDir' => '/tmp', 'margin_top' => 10, 'margin_bottom' => 10, 'margin_left' => 12, 'margin_right' => 12]);
        $mpdf2->SetTitle("Contrat NOMADRIVE — $contrat_ref");
        $mpdf2->WriteHTML($pdf_html_content);
        $mpdf2->Output($pdf_attach, \Mpdf\Output\Destination::FILE);
        $mail->addAttachment($pdf_attach, "$contrat_ref.pdf");
      } catch (\Exception $e) {
        $pdf_attach = null;
      }
    }

    $mail->send();
    $sent = true;
    if ($pdf_attach)
      @unlink($pdf_attach);
  } catch (\Exception $e) {
    $send_error = $e->getMessage();
  }

  echo json_encode([
    'success' => $sent,
    'message' => $sent
      ? "Contrat $contrat_ref envoyé à $email"
      : "Erreur d'envoi : $send_error",
  ]);
  exit;
}

// ─── Variables de rendu HTML ──────────────────────────────────────────────────
$lang = ($link_mode && ($_GET['lang'] ?? '') === 'en') ? 'en' : 'fr';
$e = $lang === 'en';
$tr = [
    'page_title'       => $e ? 'Rental agreement — NOMADRIVE'        : 'Contrat de location — NOMADRIVE',
    'subtitle'         => $e ? 'Rental agreement'                    : 'Contrat de location',
    'step1'            => $e ? 'Details'   : 'Informations',
    'step2'            => $e ? 'Licence'   : 'Permis',
    'step3_caution'    => $e ? 'Deposit'   : 'Caution',
    'step3_etat'       => $e ? 'Check-in'  : 'État avant',
    'step4'            => $e ? 'Contract'  : 'Contrat',
    'step5'            => 'Signature',
    // Screen 1
    'card_tenant'      => $e ? 'Tenant details'            : 'Informations du locataire',
    'lbl_nom'          => $e ? 'Last name *'               : 'Nom *',
    'lbl_prenom'       => $e ? 'First name *'              : 'Prénom *',
    'lbl_email'        => 'Email *',
    'lbl_adresse'      => $e ? 'Address'                   : 'Adresse',
    'btn_to_permis'    => $e ? 'Continue — Driving licence' : 'Continuer — Photo du permis',
    // Screen 2
    'card_recto'       => $e ? 'Driving licence — Front'   : 'Permis de conduire — Recto',
    'card_verso'       => $e ? 'Driving licence — Back'    : 'Permis de conduire — Verso',
    'ph_recto'         => $e ? 'Tap to photograph the front' : 'Appuyer pour photographier le recto',
    'ph_verso'         => $e ? 'Tap to photograph the back'  : 'Appuyer pour photographier le verso',
    'retake'           => $e ? 'Retake'   : 'Reprendre',
    'btn_back'         => $e ? 'Back'     : 'Retour',
    'btn_continue'     => $e ? 'Continue' : 'Continuer',
    'permis_optional'  => $e ? 'Licence photos are optional but recommended.' : 'Les photos du permis sont facultatives mais recommandées.',
    // Screen 3 link_mode
    'caution_title'    => $e ? 'Deposit pre-authorisation'  : 'Pré-autorisation caution',
    'caution_amount'   => $e
        ? 'An amount of <strong>' . CAUTION_MONTANT . ' €</strong> will be pre-authorised on your payment card.'
        : 'Un montant de <strong>' . CAUTION_MONTANT . ' €</strong> sera pré-autorisé sur votre carte bancaire.',
    'caution_nodebit'  => $e
        ? 'No immediate charge — the amount is simply held and fully released at the end of the session if everything goes well.'
        : 'Aucun débit immédiat — la somme est simplement bloquée et libérée intégralement à la fin du tour si tout se passe bien.',
    'caution_warning'  => $e
        ? "<strong>Important:</strong> If you do not complete this pre-authorisation online, a deposit of <strong>500 €</strong> will be required on-site. Only <strong>physical bank cards</strong> are accepted (no Apple Pay / Google Pay). Your <strong>physical driving licence</strong> will also be required."
        : "<strong>Important :</strong> Si vous n'effectuez pas cette pré-autorisation en ligne, un dépôt de <strong>500 €</strong> sera demandé sur place. Seules les <strong>cartes physiques</strong> sont acceptées (pas Apple Pay / Google Pay). Votre <strong>permis physique</strong> sera également exigé.",
    'btn_stripe'       => ($e ? 'Pre-authorise ' : 'Pré-autoriser ') . CAUTION_MONTANT . ' € — Stripe',
    'btn_skip'         => $e ? 'Skip — I will pay on-site'  : 'Passer — je réglerai sur place',
    'caution_cancel'   => $e ? 'The pre-authorisation was cancelled. You can try again above.' : 'La pré-autorisation a été annulée. Vous pouvez réessayer ci-dessus.',
    'caution_api_err'  => $e ? 'A technical error prevented the payment from loading. Please try again.' : 'Une erreur technique a empêché le chargement du paiement. Veuillez réessayer.',
    // Screen 4
    'greeting'         => $e ? 'Hello'   : 'Bonjour',
    'read_before_sign' => $e ? 'Please read the rental conditions below carefully before signing.' : 'Veuillez lire attentivement les conditions de location ci-dessous avant de signer.',
    'recap_title'      => $e ? 'Rental agreement — Summary'      : 'Contrat de location — Récapitulatif',
    'recap_tenant'     => $e ? 'Tenant'  : 'Locataire',
    'recap_date'       => $e ? 'Date'    : 'Date',
    'cgv_title'        => $e ? 'General rental conditions'       : 'Conditions générales de location',
    'btn_to_sign'      => $e ? 'Continue to signature'           : 'Continuer vers la signature',
    // Screen 5
    'sig_title'        => $e ? 'Your signature'    : 'Votre signature',
    'sig_desc'         => $e ? 'Sign in the area below with your finger or stylus.' : 'Signez dans la zone ci-dessous avec votre doigt ou le stylet.',
    'sig_clear'        => $e ? 'Clear'             : 'Effacer',
    'sig_hint'         => $e ? 'Sign here →'       : 'Signez ici →',
    'accept_terms'     => $e ? 'I certify that I have read and accepted the general rental conditions. I confirm that the information provided is accurate.' : "Je certifie avoir lu et accepté les conditions générales de location. J'atteste que les informations fournies sont exactes.",
    'btn_submit'       => $e ? 'Sign and send the contract'      : 'Valider et envoyer le contrat',
    'email_sent_to'    => $e ? 'The contract will be sent by email to' : 'Le contrat sera envoyé par email à',
    // Screen 6
    'signed_title'     => $e ? 'Contract signed!'  : 'Contrat signé !',
    'thank_you'        => $e ? 'Thank you'          : 'Merci',
    'contract_sent_to' => $e ? 'Your contract has been sent to' : 'Votre contrat a été envoyé à',
    'enjoy'            => $e ? 'Enjoy your ride with NOMADRIVE!' : 'Bonne location avec NOMADRIVE !',
    // JS
    'js_name_required' => $e ? 'Please enter your first and last name.' : 'Veuillez saisir le nom et le prénom.',
    'js_email_invalid' => $e ? 'Please enter a valid email address.'    : 'Veuillez saisir un email valide.',
    'js_send_error'    => $e ? 'Sending failed.'                        : "Erreur lors de l'envoi.",
    'js_network_error' => $e ? 'Network error. Please try again.'       : 'Erreur réseau. Veuillez réessayer.',
];
$step3_label = $link_mode ? $tr['step3_caution'] : $tr['step3_etat'];
$initial_step = 1;
$stripe_caution_cancelled = false;
$stripe_api_error = false;
if ($link_mode) {
  $cp = $_GET['caution'] ?? null;
  if ($cp === 'ok') {
    $initial_step = 4;
  } elseif ($cp === 'cancel') {
    $initial_step = 3;
    $stripe_caution_cancelled = true;
    $stripe_api_error = ($_GET['err'] ?? '') === 'stripe';
    $stripe_errmsg    = htmlspecialchars(substr($_GET['errmsg'] ?? '', 0, 200));
    // Invalide la session pending pour que le retry crée une nouvelle session Stripe
    $db1->prepare("UPDATE nomadrive_stripe_cautions SET status='canceled' WHERE contrat_id=? AND status='pending'")
        ->execute([$link_cid]);
  } else {
    $scChk = $db1->prepare("SELECT id FROM nomadrive_stripe_cautions WHERE contrat_id=? AND status IN ('authorized','captured') ORDER BY id DESC LIMIT 1");
    $scChk->execute([$link_cid]);
    if ($scChk->fetchColumn()) $initial_step = 4;
  }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="robots" content="noindex,nofollow">
  <title><?= htmlspecialchars($tr['page_title']) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <!-- Signature pad library -->
  <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
  <script src="https://kit.fontawesome.com/494ceebc6d.js" crossorigin="anonymous"></script>
  <style>
    *,
    *::before,
    *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    :root {
      --blue: #0077b6;
      --blue-l: #00a8e8;
      --green: #2ecc71;
      --red: #e74c3c;
      --gray: #f4f6f8;
      --border: #dde2e9;
      --text: #1a1a2e;
      --muted: #6b7280;
    }

    html,
    body {
      height: 100%;
      font-family: 'Inter', sans-serif;
      background: var(--gray);
      color: var(--text);
      -webkit-tap-highlight-color: transparent;
    }

    /* ── Layout ── */
    .app {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    header {
      background: #fff;
      border-bottom: 1px solid var(--border);
      padding: 14px 20px;
      display: flex;
      align-items: center;
      gap: 14px;
      position: sticky;
      top: 0;
      z-index: 100;
    }

    header .logo {
      font-weight: 700;
      font-size: 18px;
      color: var(--blue);
      letter-spacing: -0.5px;
    }

    header .subtitle {
      font-size: 13px;
      color: var(--muted);
    }

    /* ── Progress bar ── */
    .progress-wrap {
      background: #fff;
      border-bottom: 1px solid var(--border);
      padding: 16px 20px 16px;
    }

    .steps {
      display: flex;
      gap: 0;
      max-width: 600px;
      margin: 0 auto;
    }

    .step {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 6px;
      position: relative;
    }

    .step::before {
      content: '';
      position: absolute;
      top: 16px;
      left: 50%;
      right: -50%;
      height: 2px;
      background: var(--border);
      z-index: 0;
    }

    .step:last-child::before {
      display: none;
    }

    .step-dot {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: var(--border);
      color: var(--muted);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      font-weight: 600;
      position: relative;
      z-index: 1;
      transition: all .3s;
    }

    .step.active .step-dot {
      background: var(--blue);
      color: #fff;
    }

    .step.done .step-dot {
      background: var(--green);
      color: #fff;
    }

    .step-label {
      font-size: 11px;
      color: var(--muted);
      text-align: center;
      font-weight: 500;
    }

    .step.active .step-label {
      color: var(--blue);
    }

    .step.done .step-label {
      color: var(--green);
    }

    /* ── Screens ── */
    .screen {
      display: none;
      flex: 1;
      padding: 24px 20px;
      max-width: 680px;
      margin: 0 auto;
      width: 100%;
    }

    .screen.active {
      display: block;
    }

    /* ── Card ── */
    .card {
      background: #fff;
      border-radius: 14px;
      border: 1px solid var(--border);
      padding: 24px;
      margin-bottom: 16px;
      box-shadow: 0 1px 4px rgba(0, 0, 0, .04);
    }

    .card-title {
      font-size: 14px;
      font-weight: 600;
      color: var(--blue);
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .card-title svg {
      flex-shrink: 0;
    }

    /* ── Form ── */
    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px;
    }

    .form-grid .full {
      grid-column: 1 / -1;
    }

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 5px;
    }

    label {
      font-size: 12px;
      font-weight: 500;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: .4px;
    }

    input[type=text],
    input[type=email],
    input[type=tel],
    input[type=date],
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
      -webkit-appearance: none;
    }

    input:focus,
    select:focus {
      outline: none;
      border-color: var(--blue);
    }

    input.error {
      border-color: var(--red);
    }

    /* ── Camera / photo ── */
    .photo-area {
      border: 2px dashed var(--border);
      border-radius: 10px;
      overflow: hidden;
      position: relative;
      min-height: 160px;
      background: var(--gray);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: border-color .2s;
    }

    .photo-area:hover {
      border-color: var(--blue);
    }

    .photo-area.has-photo {
      border-style: solid;
      border-color: var(--green);
    }

    .photo-area .placeholder {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      color: var(--muted);
      font-size: 13px;
      padding: 20px;
      text-align: center;
    }

    .photo-area img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: none;
    }

    .photo-area.has-photo img {
      display: block;
    }

    .photo-area.has-photo .placeholder {
      display: none;
    }

    .photo-area .retake-btn {
      position: absolute;
      bottom: 8px;
      right: 8px;
      background: rgba(0, 0, 0, .55);
      color: #fff;
      border: none;
      border-radius: 6px;
      padding: 5px 10px;
      font-size: 12px;
      cursor: pointer;
      display: none;
    }

    .photo-area.has-photo .retake-btn {
      display: block;
    }

    input[type=file] {
      display: none;
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
      gap: 16px;
    }

    .modal-overlay.open {
      display: flex;
    }

    #camera-video {
      width: min(100vw, 500px);
      border-radius: 10px;
      background: #000;
      max-height: 60vh;
      object-fit: cover;
    }

    .camera-controls {
      display: flex;
      gap: 16px;
    }

    .btn-capture {
      width: 64px;
      height: 64px;
      border-radius: 50%;
      background: #fff;
      border: 4px solid #aaa;
      cursor: pointer;
      transition: transform .1s;
    }

    .btn-capture:active {
      transform: scale(.92);
    }

    .btn-cancel-camera {
      padding: 14px 28px;
      background: transparent;
      border: 2px solid rgba(255, 255, 255, .4);
      color: #fff;
      border-radius: 8px;
      font-size: 14px;
      cursor: pointer;
    }

    /* ── Historique véhicule ── */
    .hist-card{background:#fffbeb;border:1px solid #f0d060;border-radius:10px;padding:14px 16px;margin-bottom:16px}
    .hist-title{font-size:12px;font-weight:600;color:#856404;text-transform:uppercase;letter-spacing:.04em;margin-bottom:8px;display:flex;align-items:center;gap:6px}
    .hist-row{font-size:13px;color:var(--text);margin-bottom:4px}
    .hist-row strong{color:var(--muted);font-weight:500;display:block;font-size:11px;text-transform:uppercase;letter-spacing:.03em}

    /* ── État avant photo grid ── */
    input[type=number],textarea{width:100%;padding:11px 13px;border:1.5px solid var(--border);border-radius:8px;font-size:15px;font-family:inherit;color:var(--text);background:#fff;transition:border-color .2s;-webkit-appearance:none}
    input[type=number]:focus,textarea:focus{outline:none;border-color:var(--blue)}
    textarea{resize:vertical;min-height:80px}
    .etat-photo-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:12px}
    .etat-photo-thumb{position:relative;border-radius:8px;overflow:hidden;aspect-ratio:4/3;background:var(--gray);border:1px solid var(--border)}
    .etat-photo-thumb img{width:100%;height:100%;object-fit:cover;display:block}
    .etat-photo-thumb .del-btn{position:absolute;top:4px;right:4px;background:rgba(0,0,0,.55);color:#fff;border:none;border-radius:50%;width:22px;height:22px;font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center}
    .btn-add-etat{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;border:2px dashed var(--border);border-radius:8px;padding:14px;cursor:pointer;color:var(--muted);font-size:13px;background:transparent;width:100%;font-family:inherit;transition:border-color .2s}
    .btn-add-etat:hover{border-color:var(--blue)}
    @media(max-width:480px){.etat-photo-grid{grid-template-columns:repeat(2,1fr)}}

    /* ── Contrat text ── */
    .contract-body {
      font-size: 13px;
      line-height: 1.8;
      color: #333;
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 20px;
      background: #fafafa;
      max-height: 320px;
      overflow-y: auto;
    }

    .contract-body h4 {
      color: var(--blue);
      margin: 14px 0 4px;
      font-size: 13px;
    }

    .contract-body p {
      margin-bottom: 8px;
    }

    .contract-en {
      color: #888;
      font-size: 12px;
      font-style: italic;
      border-left: 3px solid #ddd;
      padding-left: 8px;
      margin: 4px 0 14px;
    }

    /* ── Signature pad ── */
    .sig-wrap {
      border: 2px solid var(--border);
      border-radius: 10px;
      background: #fff;
      position: relative;
      overflow: hidden;
    }

    .sig-wrap canvas {
      display: block;
      width: 100% !important;
      height: 180px;
      touch-action: none;
    }

    .sig-clear {
      position: absolute;
      top: 8px;
      right: 8px;
      background: rgba(0, 0, 0, .1);
      border: none;
      border-radius: 6px;
      padding: 4px 10px;
      font-size: 12px;
      cursor: pointer;
      color: var(--muted);
    }

    .sig-hint {
      font-size: 12px;
      color: var(--muted);
      text-align: center;
      margin-top: 6px;
    }

    /* ── Checkbox ── */
    .checkbox-group {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      padding: 14px;
      background: #fffbeb;
      border: 1px solid #f0d060;
      border-radius: 8px;
    }

    .checkbox-group input[type=checkbox] {
      width: 20px;
      height: 20px;
      flex-shrink: 0;
      margin-top: 1px;
      accent-color: var(--blue);
    }

    .checkbox-group label {
      font-size: 13px;
      text-transform: none;
      letter-spacing: 0;
      color: var(--text);
      font-weight: 400;
      cursor: pointer;
    }

    /* ── Buttons ── */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 14px 28px;
      border-radius: 10px;
      font-size: 15px;
      font-weight: 600;
      font-family: inherit;
      border: none;
      cursor: pointer;
      transition: all .2s;
      width: 100%;
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--blue), var(--blue-l));
      color: #fff;
    }

    .btn-primary:hover {
      opacity: .9;
      transform: translateY(-1px);
    }

    .btn-primary:active {
      transform: translateY(0);
    }

    .btn-primary:disabled {
      opacity: .5;
      cursor: not-allowed;
      transform: none;
    }

    .btn-secondary {
      background: var(--gray);
      color: var(--muted);
      border: 1px solid var(--border);
    }

    /* ── Success ── */
    .success-screen {
      text-align: center;
      padding: 40px 20px;
    }

    .success-icon {
      width: 72px;
      height: 72px;
      border-radius: 50%;
      background: var(--green);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 20px;
    }

    .success-icon svg {
      color: #fff;
    }

    .success-screen h2 {
      font-size: 22px;
      margin-bottom: 10px;
    }

    .success-screen p {
      color: var(--muted);
      font-size: 15px;
      margin-bottom: 6px;
    }

    /* ── Spinner ── */
    .spinner {
      width: 20px;
      height: 20px;
      border: 3px solid rgba(255, 255, 255, .3);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin .7s linear infinite;
    }

    @keyframes spin {
      to {
        transform: rotate(360deg);
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
      text-align: center;
    }

    #toast.show {
      transform: translateX(-50%) translateY(0);
    }

    #toast.success {
      background: var(--green);
    }

    /* ── Mobile ── */
    @media (max-width: 480px) {
      .form-grid {
        grid-column: 1fr;
      }

      .form-grid .full {
        grid-column: 1;
      }

      header {
        padding: 12px 16px;
      }

      .screen {
        padding: 16px;
      }

      .card {
        padding: 18px;
      }
    }

    /* ── Planning du jour ── */
    .plan-panel {
      background: #eff6ff;
      border-bottom: 2px solid #bfdbfe;
      padding: 14px 20px;
    }
    .plan-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 10px;
    }
    .plan-title {
      font-size: 13px;
      font-weight: 700;
      color: var(--blue);
      display: flex;
      align-items: center;
      gap: 7px;
      text-transform: uppercase;
      letter-spacing: .04em;
    }
    .plan-toggle {
      background: none;
      border: none;
      font-size: 12px;
      color: var(--muted);
      cursor: pointer;
      padding: 4px 8px;
      border-radius: 6px;
    }
    .plan-toggle:hover { background: rgba(0,0,0,.05); }
    .plan-tabs {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      margin-bottom: 12px;
    }
    .plan-tab {
      padding: 5px 14px;
      border-radius: 20px;
      border: 1.5px solid var(--blue);
      background: transparent;
      color: var(--blue);
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      transition: all .15s;
      font-family: inherit;
    }
    .plan-tab.active {
      background: var(--blue);
      color: #fff;
    }
    .plan-slot { display: none; }
    .plan-slot.active { display: block; }
    .plan-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-bottom: 10px;
    }
    @media (max-width: 480px) { .plan-row { grid-template-columns: 1fr; } }
    .plan-pill {
      display: inline-block;
      font-size: 11px;
      font-weight: 600;
      color: var(--blue);
      background: #dbeafe;
      border-radius: 10px;
      padding: 1px 8px;
      margin-left: 6px;
      vertical-align: middle;
    }
    .plan-empty {
      font-size: 13px;
      color: var(--muted);
      padding: 4px 0;
    }
    .btn-plan-fill {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 10px 20px;
      background: var(--blue);
      color: #fff;
      border: none;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      font-family: inherit;
      transition: opacity .15s;
      margin-top: 2px;
    }
    .btn-plan-fill:hover { opacity: .88; }
  </style>
</head>

<body>
  <div class="app">

    <!-- En-tête -->
    <header>
      <div>
        <div class="logo">NOMADRIVE</div>
        <div class="subtitle"><?= $tr['subtitle'] ?></div>
      </div>
      <?php if (!$link_mode): ?>
      <a href="dashboard.php" style="font-size:13px;color:var(--muted);text-decoration:none;display:flex;align-items:center;gap:6px;">
        <i class="fa-solid fa-gauge"></i> Dashboard
      </a>
      <?php endif; ?>
    </header>

    <!-- ═══ PLANNING DU JOUR ═══════════════════════════════════════════════════ -->
    <?php if (!$link_mode): ?>
    <div class="plan-panel" id="plan-panel">
      <div class="plan-header">
        <div class="plan-title">
          <i class="fa-solid fa-sun-bright"></i> Départ du jour
        </div>
        <button class="plan-toggle" id="plan-toggle-btn" onclick="togglePlanPanel()">Réduire</button>
      </div>
      <div id="plan-body">
        <!-- Onglets horaires -->
        <div class="plan-tabs">
          <?php
          $planTabs = array_unique([...array_keys($planData), 'soir']);
          sort($planTabs);
          $tabLabels = ['10:00' => '10h — Matin', '14:00' => '14h — Après-midi', 'soir' => 'Soir / Sunset'];
          foreach ($planTabs as $t):
            $active = $t === $planAutoSlot ? 'active' : '';
          ?>
          <button class="plan-tab <?= $active ?>" data-slot="<?= htmlspecialchars($t) ?>" onclick="switchPlanTab(this)">
            <?= $tabLabels[$t] ?? htmlspecialchars($t) ?>
            <?php if (!empty($planData[$t])): ?><span class="plan-pill"><?= count($planData[$t]) ?></span><?php endif; ?>
          </button>
          <?php endforeach; ?>
        </div>

        <!-- Contenu par créneau -->
        <?php foreach ($planTabs as $t):
          $sid    = 'ps-' . preg_replace('/[^a-z0-9]/i', '-', $t);
          $entries = $planData[$t] ?? [];
          $isActive = $t === $planAutoSlot ? 'active' : '';
        ?>
        <div class="plan-slot <?= $isActive ?>" id="<?= $sid ?>">
          <?php if (empty($entries)): ?>
          <p class="plan-empty">Aucune réservation pour ce créneau — remplissez le formulaire manuellement.</p>
          <?php else: ?>
          <div class="plan-row">
            <div class="form-group">
              <label>Réservation</label>
              <select id="<?= $sid ?>-booking" onchange="onPlanBooking('<?= $sid ?>', this)">
                <option value="">— Choisir une réservation —</option>
                <?php foreach ($entries as $e): ?>
                <option value="<?= (int)$e['id'] ?>"
                  data-fn="<?= htmlspecialchars($e['first_name']) ?>"
                  data-ln="<?= htmlspecialchars($e['last_name']) ?>"
                  data-email="<?= htmlspecialchars($e['email']) ?>"
                  data-slot="<?= htmlspecialchars($t) ?>"
                  data-vids="<?= htmlspecialchars(json_encode(array_column($e['vehicles'], 'id'))) ?>">
                  <?= htmlspecialchars(trim($e['first_name'] . ' ' . $e['last_name'])) ?>
                  (<?= $e['pax'] ?> pax<?= $e['pax'] > 2 ? ' — ' . (int)ceil($e['pax']/2) . ' voitures' : '' ?>)
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Voiture pré-affectée</label>
              <select id="<?= $sid ?>-vehicle">
                <option value="">— Choisir d'abord la résa —</option>
                <?php foreach ($planVehicles as $pv): ?>
                <option value="<?= (int)$pv['id'] ?>"><?= htmlspecialchars($pv['marque'] . ' ' . $pv['modele'] . ' — ' . $pv['immatriculation']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <button class="btn-plan-fill" onclick="prefillFromPlan('<?= $sid ?>')">
            <i class="fa-solid fa-arrow-down"></i> Pré-remplir le contrat
          </button>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Barre de progression -->
    <div class="progress-wrap">
      <div class="steps">
        <div class="step active" id="step-1">
          <div class="step-dot">1</div>
          <div class="step-label"><?= $tr['step1'] ?></div>
        </div>
        <div class="step" id="step-2">
          <div class="step-dot">2</div>
          <div class="step-label"><?= $tr['step2'] ?></div>
        </div>
        <div class="step" id="step-3">
          <div class="step-dot">3</div>
          <div class="step-label"><?= htmlspecialchars($step3_label) ?></div>
        </div>
        <div class="step" id="step-4">
          <div class="step-dot">4</div>
          <div class="step-label"><?= $tr['step4'] ?></div>
        </div>
        <div class="step" id="step-5">
          <div class="step-dot">5</div>
          <div class="step-label"><?= $tr['step5'] ?></div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
       ÉCRAN 1 — Informations client (secrétaire)
  ════════════════════════════════════════════════════════════ -->
    <div class="screen active" id="screen-1">

      <div class="card">
        <div class="card-title">
          <i class="fa-duotone fa-solid fa-user-tie"></i>
          <?= $tr['card_tenant'] ?>
        </div>
        <div class="form-grid">
          <?php if ($link_mode): ?>
          <input type="hidden" id="link_cid" value="<?= $link_cid ?>">
          <input type="hidden" id="link_token" value="<?= htmlspecialchars($link_token) ?>">
          <?php endif; ?>
          <div class="form-group">
            <label for="nom"><?= $tr['lbl_nom'] ?></label>
            <input type="text" id="nom" placeholder="DUPONT" autocomplete="family-name" autocapitalize="words"
              value="<?= $link_mode ? htmlspecialchars($link_data['nom']) : '' ?>">
          </div>
          <div class="form-group">
            <label for="prenom"><?= $tr['lbl_prenom'] ?></label>
            <input type="text" id="prenom" placeholder="Sophie" autocomplete="given-name" autocapitalize="words"
              value="<?= $link_mode ? htmlspecialchars($link_data['prenom']) : '' ?>">
          </div>
          <div class="form-group full">
            <label for="email"><?= $tr['lbl_email'] ?></label>
            <input type="email" id="email" placeholder="sophie.dupont@email.com" autocomplete="email" inputmode="email"
              value="<?= $link_mode ? htmlspecialchars($link_data['email']) : '' ?>">
          </div>
          <div class="form-group full">
            <label for="adresse"><?= $tr['lbl_adresse'] ?></label>
            <input type="text" id="adresse" placeholder="15 rue de France, 06000 Nice" autocomplete="street-address">
          </div>
        </div>
      </div>

      <?php if ($link_mode): ?>
      <input type="hidden" id="vehicule" value="<?= (int)$link_data['vehicule_id'] ?>">
      <input type="hidden" id="date_debut" value="<?= htmlspecialchars($link_data['date_debut'] ?? '') ?>">
      <input type="hidden" id="heure_debut" value="<?= htmlspecialchars($link_data['heure_debut'] ?? '') ?>">
      <?php else: ?>
      <div class="card">
        <div class="card-title">
          <i class="fa-duotone fa-solid fa-car-side"></i>
          Véhicule loué
        </div>
        <div class="form-grid">
          <div class="form-group full">
            <label for="vehicule">Véhicule *</label>
            <select id="vehicule">
              <option value="">— Choisir un véhicule —</option>
              <?php foreach ($vehicules_list as $v): ?>
                <?php if (in_array((int)$v['id'], $vehicules_occupes_ids)) continue; ?>
                <option value="<?= $v['id'] ?>" <?= (int)$v['id'] === $preselect_vehicule_id ? 'selected' : '' ?>>
                  <?= htmlspecialchars('#' . $v['id'] . ' — ' . $v['marque'] . ' ' . $v['modele'] . ' — ' . $v['immatriculation']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group full">
            <label for="date_debut">Date *</label>
            <input type="date" id="date_debut">
          </div>
          <div class="form-group full">
            <label for="heure_debut">Heure de départ *</label>
            <input type="time" id="heure_debut" step="300">
          </div>
        </div>
      </div>
      <?php endif; ?>

      <button class="btn btn-primary" onclick="goToStep2()">
        <?= $tr['btn_to_permis'] ?>
        <i class="fa-solid fa-arrow-right"></i>
      </button>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
       ÉCRAN 2 — Photos du permis (secrétaire)
  ════════════════════════════════════════════════════════════ -->
    <div class="screen" id="screen-2">

      <?php if (!$link_mode): ?>
      <div class="card">
        <div class="card-title">
          <i class="fa-duotone fa-solid fa-credit-card"></i>
          Ticket empreinte bancaire
        </div>
        <div class="photo-area" id="photo-empreinte" onclick="openCamera('empreinte')">
          <div class="placeholder">
            <i class="fa-duotone fa-solid fa-camera" style="font-size:38px;color:var(--muted);"></i>
            <span>Appuyer pour photographier le ticket</span>
          </div>
          <img id="img-empreinte" src="" alt="Ticket empreinte">
          <button class="retake-btn" onclick="event.stopPropagation(); openCamera('empreinte')">Reprendre</button>
        </div>
        <input type="file" id="file-empreinte" accept="image/*" capture="environment">
        <div class="form-group" style="margin-top:12px;">
          <label for="dossier_empreinte">N° dossier (optionnel)</label>
          <input type="text" id="dossier_empreinte" placeholder="ex : 240311-001" autocomplete="off">
        </div>
      </div>
      <?php endif; ?>

      <div class="card">
        <div class="card-title">
          <i class="fa-duotone fa-solid fa-id-card"></i>
          <?= $tr['card_recto'] ?>
        </div>
        <div class="photo-area" id="photo-recto" onclick="openCamera('recto')">
          <div class="placeholder">
            <i class="fa-duotone fa-solid fa-camera" style="font-size:38px;color:var(--muted);"></i>
            <span><?= $tr['ph_recto'] ?></span>
          </div>
          <img id="img-recto" src="" alt="Permis recto">
          <button class="retake-btn" onclick="event.stopPropagation(); openCamera('recto')"><?= $tr['retake'] ?></button>
        </div>
        <input type="file" id="file-recto" accept="image/*" capture="environment">
      </div>

      <div class="card">
        <div class="card-title">
          <i class="fa-duotone fa-solid fa-id-card"></i>
          <?= $tr['card_verso'] ?>
        </div>
        <div class="photo-area" id="photo-verso" onclick="openCamera('verso')">
          <div class="placeholder">
            <i class="fa-duotone fa-solid fa-camera" style="font-size:38px;color:var(--muted);"></i>
            <span><?= $tr['ph_verso'] ?></span>
          </div>
          <img id="img-verso" src="" alt="Permis verso">
          <button class="retake-btn" onclick="event.stopPropagation(); openCamera('verso')"><?= $tr['retake'] ?></button>
        </div>
        <input type="file" id="file-verso" accept="image/*" capture="environment">
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
        <button class="btn btn-secondary" onclick="goToStep(1)"><i class="fa-solid fa-arrow-left"></i> <?= $tr['btn_back'] ?></button>
        <?php if ($link_mode): ?>
        <button class="btn btn-primary" onclick="goToStep(3)">
          <?= $tr['btn_continue'] ?> <i class="fa-solid fa-arrow-right"></i>
        </button>
        <?php else: ?>
        <button class="btn btn-primary" onclick="goToStep3()">
          État du véhicule <i class="fa-solid fa-arrow-right"></i>
        </button>
        <?php endif; ?>
      </div>
      <p style="text-align:center;font-size:12px;color:var(--muted);"><?= $tr['permis_optional'] ?></p>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
       ÉCRAN 3 — Caution (client) ou État des lieux avant (opérateur)
  ════════════════════════════════════════════════════════════ -->
    <div class="screen" id="screen-3">

      <?php if ($link_mode): ?>

      <!-- ─── Mode client : pré-autorisation caution Stripe ─── -->
      <div class="card" style="text-align:center;padding:32px 24px;">
        <div style="width:64px;height:64px;border-radius:50%;background:#e8f4fd;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;">
          <i class="fa-solid fa-shield-check" style="font-size:28px;color:var(--blue);"></i>
        </div>
        <h2 style="font-size:18px;margin-bottom:10px;"><?= $tr['caution_title'] ?></h2>
        <p style="font-size:14px;color:var(--muted);margin-bottom:6px;">
          <?= $tr['caution_amount'] ?>
        </p>
        <p style="font-size:13px;color:var(--muted);">
          <?= $tr['caution_nodebit'] ?>
        </p>
      </div>

      <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:10px;padding:14px 16px;margin-bottom:16px;font-size:13px;color:#856404;line-height:1.6;">
        <?= $tr['caution_warning'] ?>
      </div>

      <button class="btn btn-primary" onclick="proceedToStripe()" id="btn-proceed-stripe">
        <i class="fa-solid fa-lock"></i>
        <?= $tr['btn_stripe'] ?>
      </button>

      <button class="btn btn-secondary" style="margin-top:10px;" onclick="goToStep4()">
        <?= $tr['btn_skip'] ?>
      </button>

      <?php if ($stripe_caution_cancelled): ?>
      <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:12px 14px;margin-top:14px;font-size:13px;color:#991b1b;">
        <?= $stripe_api_error ? $tr['caution_api_err'] : $tr['caution_cancel'] ?>
        <?php if ($stripe_errmsg): ?>
        <br><code style="font-size:11px;word-break:break-all;"><?= $stripe_errmsg ?></code>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <div style="margin-top:14px;">
        <button class="btn btn-secondary" onclick="goToStep(2)">
          <i class="fa-solid fa-arrow-left"></i> <?= $tr['btn_back'] ?>
        </button>
      </div>

      <?php else: ?>

      <!-- ─── Mode opérateur : état des lieux avant ─── -->
      <!-- Historique dernière location -->
      <div id="etat-avant-history" style="display:none"></div>

      <div class="card">
        <div class="card-title"><i class="fa-duotone fa-solid fa-gauge"></i> Kilométrage départ</div>
        <div class="form-group">
          <label for="etat-avant-km">Kilométrage actuel</label>
          <input type="number" id="etat-avant-km" placeholder="ex : 12450" min="0" inputmode="numeric">
        </div>
      </div>

      <div class="card">
        <div class="card-title"><i class="fa-duotone fa-solid fa-camera"></i> Photos du véhicule</div>
        <div class="etat-photo-grid" id="photo-grid-etat-avant"></div>
        <button class="btn-add-etat" id="btn-add-etat-avant" onclick="addEtatAvantPhoto()">
          <i class="fa-solid fa-plus"></i> Ajouter une photo
        </button>
        <input type="file" id="file-etat_avant" accept="image/*" capture="environment" style="display:none">
      </div>

      <div class="card">
        <div class="card-title"><i class="fa-duotone fa-solid fa-note-sticky"></i> Observations</div>
        <div class="form-group">
          <textarea id="etat-avant-notes" placeholder="Rayures, état général, niveau de batterie, remarques…"></textarea>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
        <button class="btn btn-secondary" onclick="goToStep(2)"><i class="fa-solid fa-arrow-left"></i> Retour</button>
        <button class="btn btn-primary" onclick="goToStep4()">
          Passer au client <i class="fa-solid fa-arrow-right"></i>
        </button>
      </div>
      <p style="text-align:center;font-size:12px;color:var(--muted);">Le kilométrage et les photos sont facultatifs.</p>

      <?php endif; ?>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
       ÉCRAN 4 — Lecture du contrat (client)
  ════════════════════════════════════════════════════════════ -->
    <div class="screen" id="screen-4">

      <div class="card" style="background:linear-gradient(135deg,#e8f4fd,#f0f9ff);border-color:#b3d9f5;">
        <p style="font-size:14px;font-weight:600;color:var(--blue);"><?= $tr['greeting'] ?> <span id="display-prenom"></span>,</p>
        <p style="font-size:13px;color:var(--muted);margin-top:4px;"><?= $tr['read_before_sign'] ?></p>
      </div>

      <div class="card">
        <div class="card-title">
          <i class="fa-duotone fa-solid fa-list-check"></i>
          <?= $tr['recap_title'] ?>
        </div>
        <table style="width:100%;font-size:13px;border-collapse:collapse;">
          <tr>
            <td style="padding:5px 0;color:var(--muted);width:45%;"><?= $tr['recap_tenant'] ?></td>
            <td style="font-weight:600;" id="recap-nom"></td>
          </tr>
          <?php if (!$link_mode): ?>
          <tr>
            <td style="padding:5px 0;color:var(--muted);">Véhicule</td>
            <td id="recap-vehicule"></td>
          </tr>
          <?php endif; ?>
          <tr>
            <td style="padding:5px 0;color:var(--muted);"><?= $tr['recap_date'] ?></td>
            <td id="recap-debut"></td>
          </tr>
          <?php if (!$link_mode): ?>
          <tr>
            <td style="padding:5px 0;color:var(--muted);">N° empreinte</td>
            <td id="recap-heures"></td>
          </tr>
          <?php endif; ?>
        </table>
      </div>

      <div class="card">
        <div class="card-title">
          <i class="fa-duotone fa-solid fa-file-contract"></i>
          <?= $tr['cgv_title'] ?>
        </div>
        <div class="contract-body" id="contract-text">
          <h3>DÉCLARATION DU CLIENT / CUSTOMER DECLARATION</h3>

          <h4>DOCUMENTATION</h4>
          <p><em>(remise sous forme électronique)</em> — J'ai lu et j'accepte :</p>
          <ul>
            <li>Les <strong>Conditions Générales de Location</strong> (et je note que ma responsabilité sera engagée en
              cas de perte ou de dommage au véhicule, voir le code QR et l'adresse URL imprimés ci-dessous)</li>
            <li>Les <strong>Conditions Particulières Spécifiques au Pays</strong> (voir le code QR et l'adresse URL
              imprimés ci-dessous)</li>
            <li>Les <strong>Conditions des Véhicules Électriques</strong> (si vous louez un véhicule électrique, voir
              les Conditions Générales de Location)</li>
            <li>L'<strong>estimation des Frais</strong> (voir au recto du présent Contrat de Location)</li>
            <li>La <strong>Fiche d'État du Véhicule</strong> (qui décrit l'état du véhicule au début de la location)
            </li>
          </ul>
          <p class="contract-en"><em>DOCUMENTATION (provided electronically): I have read and accept the General Rental
              Conditions, Country-Specific Conditions, Electric Vehicle Conditions, Estimated Charges and Vehicle
              Condition Report.</em></p>

          <!-- QR CODES CGV -->
          <div
            style="display:flex;justify-content:center;gap:32px;margin:14px 0;text-align:center;font-size:12px;color:#555;">
            <div>
              <img
                src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=https%3A%2F%2Fnomadrive.fr%2FCGL_Nomadrive_FR.pdf"
                width="100" height="100" alt="CGV FR" />
              <div style="margin-top:5px;">Conditions générales (FR)</div>
              <div><a href="https://nomadrive.fr/CGL_Nomadrive_FR.pdf" target="_blank"
                  style="color:#0077b6;word-break:break-all;">nomadrive.fr/CGL_Nomadrive_FR.pdf</a></div>
            </div>
            <div>
              <img
                src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=https%3A%2F%2Fnomadrive.fr%2FRental_Terms_Nomadrive_EN.pdf"
                width="100" height="100" alt="CGV EN" />
              <div style="margin-top:5px;">Rental Terms (EN)</div>
              <div><a href="https://nomadrive.fr/Rental_Terms_Nomadrive_EN.pdf" target="_blank"
                  style="color:#0077b6;word-break:break-all;">nomadrive.fr/Rental_Terms_Nomadrive_EN.pdf</a></div>
            </div>
          </div>

          <h4>PAIEMENT ET FRAIS</h4>
          <p>J'accepte :</p>
          <ul>
            <li>De payer les <strong>Frais de Location Estimés</strong> (voir au recto du présent Contrat de Location)
            </li>
            <li>De payer les éventuels <strong>Frais Supplémentaires</strong> qui pourraient découler de la location
            </li>
            <li>De payer les éventuels coûts de gestion / indemnité liés au traitement des (i) dommages ou (ii) amendes
              pour infraction routière / mauvais stationnement ainsi que toute autre charge similaire susceptible de
              s'appliquer pendant ma location</li>
            <li>Que vous procédiez à une <strong>pré-autorisation de <?= CAUTION_MONTANT ?> €</strong> sur ma carte
              bancaire</li>
            <li>Que vous puissiez <strong>prélever de ma carte bancaire</strong> toutes sommes, frais additionnels et
              frais administratifs dont je vous serais redevable, y compris la franchise en cas de perte ou de dommage
              au véhicule, sans autre autorisation de ma part</li>
          </ul>
          <p class="contract-en"><em>PAYMENT AND CHARGES: I agree to pay the Estimated Rental Charges and any Additional
              Charges, authorise a pre-authorisation of <?= CAUTION_MONTANT ?> € on my payment card, and agree that any
              amounts owed may be charged without further authorisation.</em></p>

          <h4>INFORMATIONS DU VÉHICULE</h4>
          <p>J'accepte que :</p>
          <ul>
            <li>Vous puissiez utiliser un <strong>système embarqué</strong> à l'effet de localiser le véhicule, vérifier
              son état et sa performance (notamment le kilométrage, le niveau de carburant et autres données
              opérationnelles) ainsi que l'attitude de conduite pendant ma location pour des finalités de sûreté,
              sécurité et gestion des sinistres, et conserver ces données pour la durée nécessaire à ces finalités</li>
            <li>Vous puissiez me contacter pendant ou après ma location afin de m'alerter en cas de remontée
              d'informations du véhicule laissant supposer l'existence d'un problème de sécurité, sûreté ou opérationnel
            </li>
          </ul>
          <p class="contract-en"><em>VEHICLE INFORMATION: I agree that an onboard system may be used to locate the
              vehicle and monitor its condition and performance, and that I may be contacted if a safety or operational
              issue is detected.</em></p>
        </div>
      </div>

      <button class="btn btn-primary" onclick="goToStep5()">
        <?= $tr['btn_to_sign'] ?>
        <i class="fa-solid fa-arrow-right"></i>
      </button>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
       ÉCRAN 5 — Signature (client)
  ════════════════════════════════════════════════════════════ -->
    <div class="screen" id="screen-5">

      <div class="card">
        <div class="card-title">
          <i class="fa-duotone fa-solid fa-signature"></i>
          <?= $tr['sig_title'] ?>
        </div>
        <p style="font-size:13px;color:var(--muted);margin-bottom:14px;"><?= $tr['sig_desc'] ?></p>
        <div class="sig-wrap">
          <canvas id="signature-canvas"></canvas>
          <button class="sig-clear" onclick="clearSignature()"><?= $tr['sig_clear'] ?></button>
        </div>
        <p class="sig-hint"><?= $tr['sig_hint'] ?></p>
      </div>

      <div class="card">
        <div class="checkbox-group">
          <input type="checkbox" id="accept-terms">
          <label for="accept-terms">
            <?= $tr['accept_terms'] ?>
          </label>
        </div>
      </div>

      <button class="btn btn-primary" id="btn-submit" onclick="submitContract()" disabled>
        <i class="fa-solid fa-paper-plane"></i>
        <?= $tr['btn_submit'] ?>
      </button>
      <p style="text-align:center;font-size:12px;color:var(--muted);margin-top:10px;"><?= $tr['email_sent_to'] ?>
        <strong id="display-email"></strong>
      </p>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
       ÉCRAN 6 — Confirmation
  ════════════════════════════════════════════════════════════ -->
    <div class="screen" id="screen-6">
      <div class="card success-screen">
        <div class="success-icon">
          <i class="fa-solid fa-check" style="font-size:32px;color:#fff;"></i>
        </div>
        <h2><?= $tr['signed_title'] ?></h2>
        <p><?= $tr['thank_you'] ?> <strong id="confirm-prenom"></strong>.</p>
        <p><?= $tr['contract_sent_to'] ?><br><strong id="confirm-email"></strong></p>
        <div
          style="margin-top:28px;padding:16px;background:var(--gray);border-radius:10px;font-size:13px;color:var(--muted);">
          <?= $tr['enjoy'] ?>
        </div>
        <?php if (!$link_mode): ?>
        <button class="btn btn-secondary" style="margin-top:20px;" onclick="resetForm()">
          Nouveau contrat
        </button>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /app -->

  <!-- ── Modale caméra ─────────────────────────────────────────────────────── -->
  <div class="modal-overlay" id="camera-modal">
    <video id="camera-video" autoplay playsinline></video>
    <div class="camera-controls">
      <button class="btn-cancel-camera" onclick="closeCamera()">Annuler</button>
      <button class="btn-capture" id="btn-capture" title="Prendre la photo"></button>
    </div>
  </div>

  <!-- ── Canvas caché pour capture ─────────────────────────────────────────── -->
  <canvas id="capture-canvas" style="display:none;"></canvas>

  <!-- ── Toast notifications ───────────────────────────────────────────────── -->
  <div id="toast"></div>

  <script>
    // ─────────────────────────────────────────────────────────────────────────────
    // HISTORIQUE VÉHICULES (dernier dossier fermé par véhicule)
    const lastDossiers = <?= json_encode($last_dossiers) ?>;

    // Variables PHP injectées
    const linkMode        = <?= $link_mode ? 'true' : 'false' ?>;
    const linkCidVal      = <?= (int)$link_cid ?>;
    const linkTokenVal    = <?= json_encode($link_token) ?>;
    const linkVehicule    = <?= json_encode($link_mode ? ($link_data['vehicule'] ?? '') : '') ?>;
    const i18n = <?= json_encode([
        'nameRequired' => $tr['js_name_required'],
        'emailInvalid' => $tr['js_email_invalid'],
        'sendError'    => $tr['js_send_error'],
        'networkError' => $tr['js_network_error'],
        'btnSubmit'    => $tr['btn_submit'],
    ]) ?>;

    // STATE
    // ─────────────────────────────────────────────────────────────────────────────
    const state = {
      permisRecto: null,
      permisVerso: null,
      empreintePhoto: null,
      etatAvantPhotos: [],
      currentPhotoTarget: null,
      cameraStream: null,
      signaturePad: null,
    };

    // ─────────────────────────────────────────────────────────────────────────────
    // NAVIGATION
    // ─────────────────────────────────────────────────────────────────────────────
    function goToStep(n) {
      document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
      document.getElementById('screen-' + n).classList.add('active');

      document.querySelectorAll('.step').forEach((s, i) => {
        s.classList.remove('active', 'done');
        if (i + 1 < n) s.classList.add('done');
        if (i + 1 === n) s.classList.add('active');
      });

      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function goToStep2() {
      const nom = document.getElementById('nom').value.trim();
      const prenom = document.getElementById('prenom').value.trim();
      const email = document.getElementById('email').value.trim();
      const vehicule = document.getElementById('vehicule').value;
      const debut = document.getElementById('date_debut').value;
      const hd = document.getElementById('heure_debut').value;

      if (!nom || !prenom) { showToast(i18n.nameRequired); return; }
      if (!email || !email.includes('@')) { showToast(i18n.emailInvalid); return; }
      if (!vehicule) { showToast('Veuillez choisir un véhicule.'); return; }
      if (!debut) { showToast('Veuillez saisir la date.'); return; }
      if (!hd) { showToast("Veuillez saisir l'heure de départ."); return; }

      goToStep(2);
    }

    function goToStep3() {
      const vehiculeId = document.getElementById('vehicule').value;
      const hist = lastDossiers[vehiculeId];
      const histEl = document.getElementById('etat-avant-history');

      if (hist && (hist.etat_apres_km || hist.etat_apres_notes)) {
        const date = hist.closed_at ? hist.closed_at.substring(0, 10).split('-').reverse().join('/') : '';
        let html = `<div class="hist-card"><div class="hist-title"><i class="fa-solid fa-clock-rotate-left"></i> Dernière clôture${date ? ' — ' + date : ''}</div>`;
        if (hist.etat_apres_km) {
          html += `<div class="hist-row"><strong>Km au retour</strong>${parseInt(hist.etat_apres_km).toLocaleString('fr-FR')} km</div>`;
          // Pré-remplir le champ km si vide
          const kmField = document.getElementById('etat-avant-km');
          if (!kmField.value) kmField.value = hist.etat_apres_km;
        }
        if (hist.etat_apres_notes) {
          html += `<div class="hist-row" style="margin-top:8px"><strong>Observations / dommages</strong>${escHtml(hist.etat_apres_notes)}</div>`;
        }
        html += '</div>';
        histEl.innerHTML = html;
        histEl.style.display = '';
      } else {
        histEl.style.display = 'none';
      }

      goToStep(3);
    }

    function escHtml(str) {
      return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function goToStep4() {
      const prenom = document.getElementById('prenom').value.trim();
      const nom    = document.getElementById('nom').value.trim();
      const vehiculeSel  = document.getElementById('vehicule');
      const vehiculeText = linkVehicule || vehiculeSel?.options?.[vehiculeSel.selectedIndex]?.text || vehiculeSel?.value || '—';
      const debut = document.getElementById('date_debut').value;
      const hd    = document.getElementById('heure_debut').value;

      document.getElementById('display-prenom').textContent = prenom || 'vous';
      document.getElementById('recap-nom').textContent      = (prenom + ' ' + nom).trim();
      const recapVehicule = document.getElementById('recap-vehicule');
      if (recapVehicule) recapVehicule.textContent = vehiculeText;
      document.getElementById('recap-debut').textContent    = debut ? `${formatDate(debut)} à ${hd}` : '—';
      const empreinteEl = document.getElementById('dossier_empreinte');
      const recapHeures = document.getElementById('recap-heures');
      if (recapHeures) recapHeures.textContent = empreinteEl?.value?.trim() || '—';

      goToStep(4);
    }

    function goToStep5() {
      const email = document.getElementById('email').value.trim();
      document.getElementById('display-email').textContent = email;
      goToStep(5);
      setTimeout(initSignaturePad, 100);
    }

    function formatDate(dateStr) {
      if (!dateStr) return '';
      const [y, m, d] = dateStr.split('-');
      return `${d}/${m}/${y}`;
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // CAMERA
    // ─────────────────────────────────────────────────────────────────────────────
    function openCamera(target) {
      state.currentPhotoTarget = target;

      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        // Fallback : input file
        document.getElementById('file-' + target).click();
        return;
      }

      navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
        .then(stream => {
          state.cameraStream = stream;
          const video = document.getElementById('camera-video');
          video.srcObject = stream;
          document.getElementById('camera-modal').classList.add('open');
        })
        .catch(() => {
          // Fallback : input file
          document.getElementById('file-' + target).click();
        });
    }

    document.getElementById('btn-capture').addEventListener('click', () => {
      const video = document.getElementById('camera-video');
      const canvas = document.getElementById('capture-canvas');
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      canvas.getContext('2d').drawImage(video, 0, 0);
      const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
      applyPhoto(dataUrl);
      closeCamera();
    });

    function closeCamera() {
      if (state.cameraStream) {
        state.cameraStream.getTracks().forEach(t => t.stop());
        state.cameraStream = null;
      }
      document.getElementById('camera-modal').classList.remove('open');
    }

    // Fallback file inputs
    ['recto', 'verso', 'empreinte', 'etat_avant'].forEach(side => {
      const fileEl = document.getElementById('file-' + side);
      if (!fileEl) return;
      fileEl.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        state.currentPhotoTarget = side;
        const reader = new FileReader();
        reader.onload = e => applyPhoto(e.target.result);
        reader.readAsDataURL(file);
      });
    });

    function applyPhoto(dataUrl) {
      const side = state.currentPhotoTarget;
      if (side === 'recto') {
        state.permisRecto = dataUrl;
        document.getElementById('img-recto').src = dataUrl;
        document.getElementById('photo-recto').classList.add('has-photo');
      } else if (side === 'verso') {
        state.permisVerso = dataUrl;
        document.getElementById('img-verso').src = dataUrl;
        document.getElementById('photo-verso').classList.add('has-photo');
      } else if (side === 'empreinte') {
        state.empreintePhoto = dataUrl;
        document.getElementById('img-empreinte').src = dataUrl;
        document.getElementById('photo-empreinte').classList.add('has-photo');
      } else if (side === 'etat_avant') {
        state.etatAvantPhotos.push(dataUrl);
        renderEtatAvantGrid();
      }
    }

    function addEtatAvantPhoto() {
      if (state.etatAvantPhotos.length >= 8) { showToast('Maximum 8 photos.'); return; }
      state.currentPhotoTarget = 'etat_avant';
      if (navigator.mediaDevices?.getUserMedia) {
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
          .then(stream => {
            state.cameraStream = stream;
            document.getElementById('camera-video').srcObject = stream;
            document.getElementById('camera-modal').classList.add('open');
          })
          .catch(() => document.getElementById('file-etat_avant').click());
      } else {
        document.getElementById('file-etat_avant').click();
      }
    }

    function renderEtatAvantGrid() {
      const grid = document.getElementById('photo-grid-etat-avant');
      if (!grid) return;
      grid.innerHTML = '';
      state.etatAvantPhotos.forEach((src, i) => {
        const div = document.createElement('div');
        div.className = 'etat-photo-thumb';
        div.innerHTML = `<img src="${src}"><button class="del-btn" onclick="removeEtatAvantPhoto(${i})">✕</button>`;
        grid.appendChild(div);
      });
      const btn = document.getElementById('btn-add-etat-avant');
      if (btn) btn.style.display = state.etatAvantPhotos.length >= 8 ? 'none' : '';
    }

    function removeEtatAvantPhoto(idx) {
      state.etatAvantPhotos.splice(idx, 1);
      renderEtatAvantGrid();
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // SIGNATURE PAD
    // ─────────────────────────────────────────────────────────────────────────────
    function initSignaturePad() {
      const canvas = document.getElementById('signature-canvas');
      // Resize au ratio pixel de l'écran
      const ratio = Math.max(window.devicePixelRatio || 1, 1);
      canvas.width = canvas.offsetWidth * ratio;
      canvas.height = canvas.offsetHeight * ratio;
      canvas.getContext('2d').scale(ratio, ratio);

      state.signaturePad = new SignaturePad(canvas, {
        backgroundColor: 'rgb(255,255,255)',
        penColor: 'rgb(10,30,80)',
        minWidth: 1.5,
        maxWidth: 3.5,
      });

      // Activer le bouton submit quand la signature + la case sont cochées
      state.signaturePad.addEventListener('endStroke', checkSubmitReady);
    }

    function clearSignature() {
      if (state.signaturePad) state.signaturePad.clear();
      checkSubmitReady();
    }

    document.getElementById('accept-terms').addEventListener('change', checkSubmitReady);

    function checkSubmitReady() {
      const signed = state.signaturePad && !state.signaturePad.isEmpty();
      const checked = document.getElementById('accept-terms').checked;
      document.getElementById('btn-submit').disabled = !(signed && checked);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // SOUMISSION
    // ─────────────────────────────────────────────────────────────────────────────
    async function submitContract() {
      const btn = document.getElementById('btn-submit');
      btn.disabled = true;
      btn.innerHTML = '<div class="spinner"></div> Envoi en cours…';

      const payload = new FormData();
      payload.append('action', 'send_contract');
      payload.append('nom', document.getElementById('nom').value.trim().toUpperCase());
      payload.append('prenom', document.getElementById('prenom').value.trim());
      payload.append('email', document.getElementById('email').value.trim());
      payload.append('adresse', document.getElementById('adresse').value.trim());
      payload.append('vehicule', document.getElementById('vehicule').value);
      payload.append('date_debut', document.getElementById('date_debut').value);
      payload.append('heure_debut', document.getElementById('heure_debut').value);
      payload.append('dossier_empreinte', document.getElementById('dossier_empreinte')?.value?.trim() || '');
      payload.append('signature', state.signaturePad.toDataURL('image/png'));
      payload.append('permis_recto', state.permisRecto || '');
      payload.append('permis_verso', state.permisVerso || '');
      payload.append('empreinte_photo', state.empreintePhoto || '');
      payload.append('etat_avant_km', document.getElementById('etat-avant-km')?.value || '0');
      payload.append('etat_avant_notes', document.getElementById('etat-avant-notes')?.value?.trim() || '');
      payload.append('etat_avant_photos_json', JSON.stringify(state.etatAvantPhotos));
      const linkCid   = document.getElementById('link_cid')?.value || '';
      const linkToken = document.getElementById('link_token')?.value || '';
      if (linkCid) { payload.append('link_cid', linkCid); payload.append('link_token', linkToken); }

      try {
        const resp = await fetch('contrat.php', { method: 'POST', body: payload });
        const data = await resp.json();

        if (data.success) {
          const prenom = document.getElementById('prenom').value.trim();
          const email = document.getElementById('email').value.trim();
          document.getElementById('confirm-prenom').textContent = prenom;
          document.getElementById('confirm-email').textContent = email;
          goToStep(6);
          // Masquer la barre de progression
          document.querySelector('.steps').parentElement.style.display = 'none';
          // Redirect après confirmation
          setTimeout(() => {
            window.location.href = linkMode ? 'https://nomadrive.fr' : 'dashboard.php';
          }, 4000);
        } else {
          showToast(data.message || i18n.sendError);
          btn.disabled = false;
          btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13"/><path d="M22 2 15 22 11 13 2 9l20-7z"/></svg> ' + i18n.btnSubmit;
        }
      } catch (e) {
        showToast(i18n.networkError);
        btn.disabled = false;
        btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13"/><path d="M22 2 15 22 11 13 2 9l20-7z"/></svg> ' + i18n.btnSubmit;
        checkSubmitReady();
      }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // RESET
    // ─────────────────────────────────────────────────────────────────────────────
    function resetForm() {
      document.querySelector('form') && document.querySelector('form').reset();
      ['nom', 'prenom', 'email', 'adresse', 'date_debut', 'heure_debut', 'dossier_empreinte'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
      });
      document.getElementById('vehicule').value = '';
      document.getElementById('accept-terms').checked = false;

      // Reset photos
      state.permisRecto = state.permisVerso = state.empreintePhoto = null;
      state.etatAvantPhotos = [];
      ['recto', 'verso', 'empreinte'].forEach(s => {
        const ph = document.getElementById('photo-' + s);
        const im = document.getElementById('img-' + s);
        if (ph) ph.classList.remove('has-photo');
        if (im) im.src = '';
      });
      renderEtatAvantGrid();
      const kmEl = document.getElementById('etat-avant-km');
      const notesEl = document.getElementById('etat-avant-notes');
      if (kmEl) kmEl.value = '';
      if (notesEl) notesEl.value = '';

      // Reset signature
      if (state.signaturePad) state.signaturePad.clear();

      // Reset barre de progression
      document.querySelector('.steps').parentElement.style.display = '';

      goToStep(1);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // TOAST
    // ─────────────────────────────────────────────────────────────────────────────
    let toastTimer = null;
    function showToast(msg, type = 'error') {
      const t = document.getElementById('toast');
      t.textContent = msg;
      t.className = type === 'success' ? 'success' : '';
      t.classList.add('show');
      clearTimeout(toastTimer);
      toastTimer = setTimeout(() => t.classList.remove('show'), 3800);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // CAUTION STRIPE (link_mode)
    // ─────────────────────────────────────────────────────────────────────────────
    async function proceedToStripe() {
      const btn = document.getElementById('btn-proceed-stripe');
      if (btn) { btn.disabled = true; btn.innerHTML = '<div class="spinner"></div> Préparation…'; }

      // Sauvegarde les photos permis avant de quitter la page
      if (state.permisRecto || state.permisVerso) {
        const fd = new FormData();
        fd.append('action', 'save_permis');
        fd.append('link_cid', linkCidVal);
        fd.append('link_token', linkTokenVal);
        fd.append('permis_recto', state.permisRecto || '');
        fd.append('permis_verso', state.permisVerso || '');
        try { await fetch('contrat.php', { method: 'POST', body: fd }); } catch (_) {}
      }

      window.location.href = 'contrat.php?cid=' + linkCidVal + '&token=' + encodeURIComponent(linkTokenVal) + '&action=stripe_redirect';
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // PLANNING DU JOUR
    // ─────────────────────────────────────────────────────────────────────────────
    const planAllVehicles = <?= json_encode(array_values($planVehicles)) ?>;

    function togglePlanPanel() {
      const body = document.getElementById('plan-body');
      const btn  = document.getElementById('plan-toggle-btn');
      const collapsed = body.style.display === 'none';
      body.style.display = collapsed ? '' : 'none';
      btn.textContent    = collapsed ? 'Réduire' : 'Départ du jour';
    }

    function switchPlanTab(btn) {
      document.querySelectorAll('.plan-tab').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.plan-slot').forEach(el => el.classList.remove('active'));
      btn.classList.add('active');
      const sid = 'ps-' + btn.dataset.slot.replace(/[^a-z0-9]/gi, '-');
      const el = document.getElementById(sid);
      if (el) el.classList.add('active');
    }

    function onPlanBooking(sid, sel) {
      const opt    = sel.options[sel.selectedIndex];
      const vehSel = document.getElementById(sid + '-vehicle');
      vehSel.innerHTML = '';

      if (!opt.value) {
        vehSel.innerHTML = '<option value="">— Choisir d\'abord la résa —</option>';
        planAllVehicles.forEach(v => {
          const o = document.createElement('option');
          o.value = v.id;
          o.textContent = v.marque + ' ' + v.modele + ' — ' + v.immatriculation;
          vehSel.appendChild(o);
        });
        return;
      }

      const preIds = JSON.parse(opt.dataset.vids || '[]');
      const preSet = new Set(preIds.map(Number));

      // Pre-assigned first (selected by default)
      preIds.forEach((vid, i) => {
        const v = planAllVehicles.find(x => x.id == vid);
        if (!v) return;
        const o = document.createElement('option');
        o.value = v.id;
        o.textContent = v.marque + ' ' + v.modele + ' — ' + v.immatriculation + (preIds.length > 1 ? ` (voiture ${i+1}/${preIds.length})` : ' (pré-affectée)');
        o.selected = i === 0;
        vehSel.appendChild(o);
      });

      // Separator then remaining
      if (planAllVehicles.some(v => !preSet.has(v.id))) {
        const sep = document.createElement('option');
        sep.disabled = true;
        sep.textContent = '── Autres véhicules ──';
        vehSel.appendChild(sep);
        planAllVehicles.forEach(v => {
          if (preSet.has(v.id)) return;
          const o = document.createElement('option');
          o.value = v.id;
          o.textContent = v.marque + ' ' + v.modele + ' — ' + v.immatriculation;
          vehSel.appendChild(o);
        });
      }
    }

    function prefillFromPlan(sid) {
      const bookSel = document.getElementById(sid + '-booking');
      const vehSel  = document.getElementById(sid + '-vehicle');
      const opt     = bookSel?.options[bookSel.selectedIndex];
      if (!opt?.value) { showToast('Choisissez une réservation.'); return; }

      document.getElementById('prenom').value     = opt.dataset.fn || '';
      document.getElementById('nom').value        = (opt.dataset.ln || '').toUpperCase();
      document.getElementById('email').value      = opt.dataset.email || '';
      document.getElementById('date_debut').value = new Date().toISOString().split('T')[0];

      const slotTime = opt.dataset.slot === 'soir' ? '18:00' : opt.dataset.slot;
      document.getElementById('heure_debut').value = slotTime;

      const vehId = vehSel?.value;
      if (vehId) {
        const vSel = document.getElementById('vehicule');
        if (vSel) vSel.value = vehId;
      }

      showToast('Contrat pré-rempli !', 'success');
      goToStep(1);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // INIT — date par défaut = aujourd'hui
    // ─────────────────────────────────────────────────────────────────────────────
    (function () {
      const dateEl  = document.getElementById('date_debut');
      const heureEl = document.getElementById('heure_debut');
      if (dateEl && !dateEl.value) {
        const now = new Date();
        dateEl.value  = now.toISOString().split('T')[0];
        if (heureEl) heureEl.value = now.toTimeString().slice(0, 5);
      }

      // Positionnement initial (retour Stripe ou caution déjà autorisée)
      const initialStep = <?= (int)$initial_step ?>;
      if (initialStep === 4) goToStep4();
      else if (initialStep > 1) goToStep(initialStep);

      <?php if ($stripe_caution_cancelled): ?>
      showToast(<?= $stripe_api_error ? json_encode($tr['caution_api_err']) : json_encode($tr['caution_cancel']) ?>);
      <?php endif; ?>
    })();
  </script>
</body>

</html>