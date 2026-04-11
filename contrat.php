<?php
$madiDir = '/var/www/html/madi.mt';
if (!is_dir($madiDir))
  $madiDir = dirname(__DIR__); // fallback local
require_once $madiDir . '/vendor/autoload.php';
require_once $madiDir . '/php/fonctions.php';
require_once $madiDir . '/php/config.php';
$db1->query("SET NAMES 'utf8mb4'");

// ─── Chargement des véhicules ─────────────────────────────────────────────────
$vehicules_list = [];
try {
  $res = $db1->query("
        SELECT id, immatriculation, marque, modele
        FROM nomadrive_vehicules
        ORDER BY marque, immatriculation
    ");
  if ($res)
    $vehicules_list = $res->fetchAll(PDO::FETCH_ASSOC);
} catch (\Exception $e) {
  // Table absente ou erreur DB — on continue avec liste vide
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
      $vehicule = $v['marque'] . ' ' . $v['modele'] . ' — ' . $v['immatriculation'];
      break;
    }
  }
  $signature = $_POST['signature'] ?? '';    // base64 PNG
  $permis_r = $_POST['permis_recto'] ?? ''; // base64
  $permis_v = $_POST['permis_verso'] ?? ''; // base64

  // Chargement du .env — même logique qu'influencify
  $envFile = $madiDir . '/.env';
  if (!file_exists($envFile))
    $envFile = __DIR__ . '/.env';
  if (file_exists($envFile)) {
    $envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
      if (strpos($line, '#') === 0)
        continue;
      if (strpos($line, '=') !== false) {
        list($k, $v) = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v);
      }
    }
  }
  $smtpUsername = $_ENV['SMTP_USERNAME'] ?? '';
  $smtpPassword = $_ENV['SMTP_PASSWORD'] ?? '';

  if (empty($nom) || empty($prenom) || !$email || empty($signature)) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes.']);
    exit;
  }

  $date_contrat = date('d/m/Y');
  $nom_complet = htmlspecialchars("$prenom $nom");

  // ── 1. INSERT initial pour obtenir l'ID du contrat ────────────────────────
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
  $contrat_id = $db1->lastInsertId();
  $contrat_ref = 'ND-' . str_pad($contrat_id, 5, '0', STR_PAD_LEFT);
  $file_token = substr(bin2hex(random_bytes(6)), 0, 8); // token anti-enumeration

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

  $pdf_html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;color:#222;padding:20px;">
  <div style="text-align:center;margin-bottom:20px;">
    <h1 style="color:#0077b6;margin:0;font-size:22px;">NOMADRIVE</h1>
    <p style="margin:4px 0;color:#555;font-size:12px;">2 place Guynemer, 06000 Nice — contact@nomadrive.fr</p>
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
    <tr><td style="padding:4px 8px;background:#f9f9f9;width:40%;">Véhicule loué</td>
        <td style="padding:4px 8px;background:#f9f9f9;">{$vehicule}</td></tr>
    <tr><td style="padding:4px 8px;">Date de départ</td>
        <td style="padding:4px 8px;">{$date_debut} à {$heure_debut}</td></tr>
    <tr><td style="padding:4px 8px;background:#f9f9f9;">N° dossier empreinte</td>
        <td style="padding:4px 8px;background:#f9f9f9;">{$dossier_empreinte}</td></tr>
  </table>

  <!-- Ici viendra le corps du contrat allégé -->
  <div style="border:1px solid #ddd;padding:14px;margin-bottom:20px;font-size:12px;line-height:1.7;">
    <h3 style="margin-top:0;color:#0077b6;font-size:13px;">Conditions générales</h3>
    <p><em>[ Le texte du contrat allégé sera inséré ici ]</em></p>
  </div>

  <h3 style="color:#0077b6;font-size:13px;border-left:3px solid #0077b6;padding-left:8px;">Signature du locataire</h3>
  <div style="border:1px solid #ccc;padding:8px;margin-bottom:20px;text-align:center;">
    <img src="{$signature}" style="max-width:280px;max-height:130px;"/>
    <p style="margin:4px 0 0;font-size:11px;color:#888;">Signé électroniquement le {$date_contrat}</p>
  </div>

  {$permis_html}

  <hr style="margin:20px 0;border:none;border-top:1px solid #eee;"/>
  <p style="font-size:10px;color:#999;text-align:center;">NOMADRIVE — 2 place Guynemer, 06000 Nice<br>Ce document tient lieu de contrat de location signé électroniquement.</p>
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
        SET url_contrat_pdf = :pdf, url_permis_recto = :pr, url_permis_verso = :pv
        WHERE id = :id
    ");
  $upd->execute([':pdf' => $url_pdf, ':pr' => $url_permis_r, ':pv' => $url_permis_v, ':id' => $contrat_id]);

  // ── 7. Corps email HTML (léger, sans images inline) ───────────────────────
  $email_body = "
    <div style='font-family:Arial,sans-serif;color:#222;max-width:600px;margin:0 auto;padding:20px;'>
      <div style='text-align:center;margin-bottom:20px;'>
        <h1 style='color:#0077b6;margin:0;'>NOMADRIVE</h1>
        <p style='color:#555;font-size:13px;'>2 place Guynemer, 06000 Nice</p>
      </div>
      <p>Bonjour <strong>{$nom_complet}</strong>,</p>
      <p>Merci pour votre location chez NOMADRIVE. Vous trouverez en pièce jointe votre contrat de location signé (<strong>{$contrat_ref}</strong>).</p>
      <table style='width:100%;border-collapse:collapse;margin:16px 0;font-size:13px;'>
        <tr><td style='padding:5px 8px;background:#f0f8ff;width:40%;font-weight:bold;'>Véhicule</td>
            <td style='padding:5px 8px;background:#f0f8ff;'>{$vehicule}</td></tr>
        <tr><td style='padding:5px 8px;'>Date de départ</td>
            <td style='padding:5px 8px;'>{$date_debut} à {$heure_debut}</td></tr>
      </table>
      <p style='font-size:12px;color:#888;'>Ce document tient lieu de contrat signé électroniquement.</p>
      <hr style='border:none;border-top:1px solid #eee;margin:16px 0;'/>
      <p style='font-size:11px;color:#aaa;text-align:center;'>NOMADRIVE — contact@nomadrive.fr</p>
    </div>";

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

    $mail->setFrom('contact@madi.mt', 'NOMADRIVE');
    $mail->addReplyTo('contact@nomadrive.fr', 'NOMADRIVE');
    $mail->addAddress($email, $nom_complet);
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
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="robots" content="noindex,nofollow">
  <title>Contrat de location — NOMADRIVE</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <!-- Signature pad library -->
  <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
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
      padding: 0 20px 16px;
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
        grid-template-columns: 1fr;
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
  </style>
</head>

<body>
  <div class="app">

    <!-- En-tête -->
    <header>
      <div>
        <div class="logo">NOMADRIVE</div>
        <div class="subtitle">Contrat de location</div>
      </div>
    </header>

    <!-- Barre de progression -->
    <div class="progress-wrap">
      <div class="steps">
        <div class="step active" id="step-1">
          <div class="step-dot">1</div>
          <div class="step-label">Informations</div>
        </div>
        <div class="step" id="step-2">
          <div class="step-dot">2</div>
          <div class="step-label">Permis</div>
        </div>
        <div class="step" id="step-3">
          <div class="step-dot">3</div>
          <div class="step-label">Contrat</div>
        </div>
        <div class="step" id="step-4">
          <div class="step-dot">4</div>
          <div class="step-label">Signature</div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
       ÉCRAN 1 — Informations client (secrétaire)
  ════════════════════════════════════════════════════════════ -->
    <div class="screen active" id="screen-1">

      <div class="card">
        <div class="card-title">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
            <circle cx="12" cy="7" r="4" />
          </svg>
          Informations du locataire
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label for="nom">Nom *</label>
            <input type="text" id="nom" placeholder="DUPONT" autocomplete="family-name" autocapitalize="words">
          </div>
          <div class="form-group">
            <label for="prenom">Prénom *</label>
            <input type="text" id="prenom" placeholder="Sophie" autocomplete="given-name" autocapitalize="words">
          </div>
          <div class="form-group full">
            <label for="email">Email *</label>
            <input type="email" id="email" placeholder="sophie.dupont@email.com" autocomplete="email" inputmode="email">
          </div>
          <div class="form-group full">
            <label for="adresse">Adresse</label>
            <input type="text" id="adresse" placeholder="15 rue de France, 06000 Nice" autocomplete="street-address">
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-title">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="1" y="3" width="15" height="13" rx="2" />
            <path d="M16 8h6l2 4v4h-8V8Z" />
            <circle cx="5.5" cy="18.5" r="2.5" />
            <circle cx="18.5" cy="18.5" r="2.5" />
          </svg>
          Véhicule loué
        </div>
        <div class="form-grid">
          <div class="form-group full">
            <label for="vehicule">Véhicule *</label>
            <select id="vehicule">
              <option value="">— Choisir un véhicule —</option>
              <?php foreach ($vehicules_list as $v): ?>
                <option value="<?= $v['id'] ?>">
                  <?= htmlspecialchars($v['marque'] . ' ' . $v['modele'] . ' — ' . $v['immatriculation']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group full">
            <label for="date_debut">Date *</label>
            <input type="date" id="date_debut">
          </div>
          <div class="form-group">
            <label for="heure_debut">Heure de départ *</label>
            <input type="time" id="heure_debut" step="300">
          </div>
          <div class="form-group">
            <label for="dossier_empreinte">N° dossier empreinte bancaire</label>
            <input type="text" id="dossier_empreinte" placeholder="ex : 240311-001" autocomplete="off">
          </div>
        </div>
      </div>

      <button class="btn btn-primary" onclick="goToStep2()">
        Continuer — Photo du permis
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="m9 18 6-6-6-6" />
        </svg>
      </button>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
       ÉCRAN 2 — Photos du permis (secrétaire)
  ════════════════════════════════════════════════════════════ -->
    <div class="screen" id="screen-2">

      <div class="card">
        <div class="card-title">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z" />
            <circle cx="12" cy="13" r="3" />
          </svg>
          Permis de conduire — Recto
        </div>
        <div class="photo-area" id="photo-recto" onclick="openCamera('recto')">
          <div class="placeholder">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z" />
              <circle cx="12" cy="13" r="3" />
            </svg>
            <span>Appuyer pour photographier le recto</span>
          </div>
          <img id="img-recto" src="" alt="Permis recto">
          <button class="retake-btn" onclick="event.stopPropagation(); openCamera('recto')">Reprendre</button>
        </div>
        <input type="file" id="file-recto" accept="image/*" capture="environment">
      </div>

      <div class="card">
        <div class="card-title">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z" />
            <circle cx="12" cy="13" r="3" />
          </svg>
          Permis de conduire — Verso
        </div>
        <div class="photo-area" id="photo-verso" onclick="openCamera('verso')">
          <div class="placeholder">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z" />
              <circle cx="12" cy="13" r="3" />
            </svg>
            <span>Appuyer pour photographier le verso</span>
          </div>
          <img id="img-verso" src="" alt="Permis verso">
          <button class="retake-btn" onclick="event.stopPropagation(); openCamera('verso')">Reprendre</button>
        </div>
        <input type="file" id="file-verso" accept="image/*" capture="environment">
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
        <button class="btn btn-secondary" onclick="goToStep(1)">← Retour</button>
        <button class="btn btn-primary" onclick="goToStep3()">
          Passer au client →
        </button>
      </div>
      <p style="text-align:center;font-size:12px;color:var(--muted);">Les photos du permis sont facultatives mais
        recommandées.</p>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
       ÉCRAN 3 — Lecture du contrat (client)
  ════════════════════════════════════════════════════════════ -->
    <div class="screen" id="screen-3">

      <div class="card" style="background:linear-gradient(135deg,#e8f4fd,#f0f9ff);border-color:#b3d9f5;">
        <p style="font-size:14px;font-weight:600;color:var(--blue);">Bonjour <span id="display-prenom"></span>,</p>
        <p style="font-size:13px;color:var(--muted);margin-top:4px;">Veuillez lire attentivement les conditions de
          location ci-dessous avant de signer.</p>
      </div>

      <div class="card">
        <div class="card-title">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <polyline points="14,2 14,8 20,8" />
          </svg>
          Contrat de location — Récapitulatif
        </div>
        <table style="width:100%;font-size:13px;border-collapse:collapse;">
          <tr>
            <td style="padding:5px 0;color:var(--muted);width:45%;">Locataire</td>
            <td style="font-weight:600;" id="recap-nom"></td>
          </tr>
          <tr>
            <td style="padding:5px 0;color:var(--muted);">Véhicule</td>
            <td id="recap-vehicule"></td>
          </tr>
          <tr>
            <td style="padding:5px 0;color:var(--muted);">Date</td>
            <td id="recap-debut"></td>
          </tr>
          <tr>
            <td style="padding:5px 0;color:var(--muted);">N° empreinte</td>
            <td id="recap-heures"></td>
          </tr>
        </table>
      </div>

      <div class="card">
        <div class="card-title">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <polyline points="14,2 14,8 20,8" />
            <line x1="16" y1="13" x2="8" y2="13" />
            <line x1="16" y1="17" x2="8" y2="17" />
          </svg>
          Conditions générales de location
        </div>
        <div class="contract-body" id="contract-text">
          <!-- ╔══════════════════════════════════════════════════════════╗
             ║  PLACEHOLDER — remplacer par le vrai contrat allégé     ║
             ╚══════════════════════════════════════════════════════════╝ -->
          <h4>Article 1 — Objet</h4>
          <p>Le présent contrat a pour objet la location d'un véhicule électrique de type quadricycle léger par
            NOMADRIVE (ci-après « le loueur ») au locataire désigné ci-dessus.</p>

          <h4>Article 2 — Durée de la location</h4>
          <p>La location prend effet à la date et heure indiquées sur le présent contrat. Le véhicule doit être restitué
            au plus tard à la date et heure de retour convenues, sauf prolongation accordée par écrit par le loueur.</p>

          <h4>Article 3 — Conditions de conduite</h4>
          <p>Le locataire doit être titulaire d'un permis de conduire valide (catégorie AM ou B). Le véhicule ne peut
            être conduit que par le locataire désigné au contrat. Il est interdit de prêter, sous-louer ou faire
            conduire le véhicule par un tiers.</p>

          <h4>Article 4 — Utilisation du véhicule</h4>
          <p>Le locataire s'engage à utiliser le véhicule conformément au code de la route et aux instructions du
            loueur. Tout usage hors route, sur circuits ou à des fins commerciales est strictement interdit.</p>

          <h4>Article 5 — Responsabilité et dommages</h4>
          <p>Le locataire est responsable de tout dommage causé au véhicule pendant la durée de la location. En cas
            d'accident ou d'incident, le locataire doit en informer immédiatement le loueur.</p>

          <h4>Article 6 — Carburant / recharge</h4>
          <p>Le véhicule est remis avec sa batterie chargée. Le locataire s'engage à restituer le véhicule dans un état
            de charge équivalent ou à supporter les frais de recharge correspondants.</p>

          <h4>Article 7 — Tarifs et paiement</h4>
          <p>Le montant de la location est indiqué lors de la réservation. Le paiement est exigible avant la remise des
            clés.</p>

          <h4>Article 8 — Données personnelles</h4>
          <p>Les données collectées sont utilisées uniquement dans le cadre de la location et conservées conformément à
            la réglementation RGPD. Le locataire dispose d'un droit d'accès, de rectification et de suppression.</p>

          <!-- FIN PLACEHOLDER -->
        </div>
      </div>

      <button class="btn btn-primary" onclick="goToStep4()">
        Continuer vers la signature
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="m9 18 6-6-6-6" />
        </svg>
      </button>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
       ÉCRAN 4 — Signature (client)
  ════════════════════════════════════════════════════════════ -->
    <div class="screen" id="screen-4">

      <div class="card">
        <div class="card-title">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 20h9" />
            <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
          </svg>
          Votre signature
        </div>
        <p style="font-size:13px;color:var(--muted);margin-bottom:14px;">Signez dans la zone ci-dessous avec votre doigt
          ou le stylet.</p>
        <div class="sig-wrap">
          <canvas id="signature-canvas"></canvas>
          <button class="sig-clear" onclick="clearSignature()">Effacer</button>
        </div>
        <p class="sig-hint">Signez ici →</p>
      </div>

      <div class="card">
        <div class="checkbox-group">
          <input type="checkbox" id="accept-terms">
          <label for="accept-terms">
            Je certifie avoir lu et accepté les conditions générales de location. J'atteste que les informations
            fournies sont exactes.
          </label>
        </div>
      </div>

      <button class="btn btn-primary" id="btn-submit" onclick="submitContract()" disabled>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M22 2L11 13" />
          <path d="M22 2 15 22 11 13 2 9l20-7z" />
        </svg>
        Valider et envoyer le contrat
      </button>
      <p style="text-align:center;font-size:12px;color:var(--muted);margin-top:10px;">Le contrat sera envoyé par email à
        <strong id="display-email"></strong></p>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
       ÉCRAN 5 — Confirmation
  ════════════════════════════════════════════════════════════ -->
    <div class="screen" id="screen-5">
      <div class="card success-screen">
        <div class="success-icon">
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <polyline points="20 6 9 17 4 12" />
          </svg>
        </div>
        <h2>Contrat signé !</h2>
        <p>Merci <strong id="confirm-prenom"></strong>.</p>
        <p>Votre contrat a été envoyé à<br><strong id="confirm-email"></strong></p>
        <div
          style="margin-top:28px;padding:16px;background:var(--gray);border-radius:10px;font-size:13px;color:var(--muted);">
          Bonne location avec NOMADRIVE !
        </div>
        <button class="btn btn-secondary" style="margin-top:20px;" onclick="resetForm()">
          Nouveau contrat
        </button>
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
    // STATE
    // ─────────────────────────────────────────────────────────────────────────────
    const state = {
      permisRecto: null,  // base64 image
      permisVerso: null,
      currentPhotoTarget: null,  // 'recto' | 'verso'
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

      if (!nom || !prenom) { showToast('Veuillez saisir le nom et le prénom.'); return; }
      if (!email || !email.includes('@')) { showToast('Veuillez saisir un email valide.'); return; }
      if (!vehicule) { showToast('Veuillez choisir un véhicule.'); return; }
      if (!debut) { showToast('Veuillez saisir la date.'); return; }
      if (!hd) { showToast("Veuillez saisir l'heure de départ."); return; }

      goToStep(2);
    }

    function goToStep3() {
      const prenom = document.getElementById('prenom').value.trim();
      const nom = document.getElementById('nom').value.trim();
      const vehiculeSel = document.getElementById('vehicule');
      const vehiculeText = vehiculeSel.options[vehiculeSel.selectedIndex]?.text || '—';
      const debut = document.getElementById('date_debut').value;
      const hd = document.getElementById('heure_debut').value;

      document.getElementById('display-prenom').textContent = prenom || 'vous';
      document.getElementById('recap-nom').textContent = (prenom + ' ' + nom).trim();
      document.getElementById('recap-vehicule').textContent = vehiculeText;
      document.getElementById('recap-debut').textContent = debut ? `${formatDate(debut)} à ${hd}` : '—';
      document.getElementById('recap-heures').textContent = document.getElementById('dossier_empreinte').value.trim() || '—';

      goToStep(3);
    }

    function goToStep4() {
      const email = document.getElementById('email').value.trim();
      document.getElementById('display-email').textContent = email;

      goToStep(4);

      // Init signature pad (après affichage)
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

    // Fallback file input
    ['recto', 'verso'].forEach(side => {
      document.getElementById('file-' + side).addEventListener('change', function () {
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
      } else {
        state.permisVerso = dataUrl;
        document.getElementById('img-verso').src = dataUrl;
        document.getElementById('photo-verso').classList.add('has-photo');
      }
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
      payload.append('dossier_empreinte', document.getElementById('dossier_empreinte').value.trim());
      payload.append('signature', state.signaturePad.toDataURL('image/png'));
      payload.append('permis_recto', state.permisRecto || '');
      payload.append('permis_verso', state.permisVerso || '');

      try {
        const resp = await fetch('contrat.php', { method: 'POST', body: payload });
        const data = await resp.json();

        if (data.success) {
          const prenom = document.getElementById('prenom').value.trim();
          const email = document.getElementById('email').value.trim();
          document.getElementById('confirm-prenom').textContent = prenom;
          document.getElementById('confirm-email').textContent = email;
          goToStep(5);
          // Masquer la barre de progression
          document.querySelector('.steps').parentElement.style.display = 'none';
        } else {
          showToast(data.message || "Erreur lors de l'envoi.");
          btn.disabled = false;
          btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13"/><path d="M22 2 15 22 11 13 2 9l20-7z"/></svg> Valider et envoyer le contrat';
        }
      } catch (e) {
        showToast("Erreur réseau. Veuillez réessayer.");
        btn.disabled = false;
        btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13"/><path d="M22 2 15 22 11 13 2 9l20-7z"/></svg> Valider et envoyer le contrat';
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
      state.permisRecto = state.permisVerso = null;
      ['recto', 'verso'].forEach(s => {
        document.getElementById('photo-' + s).classList.remove('has-photo');
        document.getElementById('img-' + s).src = '';
      });

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
    // INIT — date par défaut = aujourd'hui
    // ─────────────────────────────────────────────────────────────────────────────
    (function () {
      const now = new Date();
      document.getElementById('date_debut').value = now.toISOString().split('T')[0];
      document.getElementById('heure_debut').value = now.toTimeString().slice(0, 5);
    })();
  </script>
</body>

</html>