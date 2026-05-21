<?php
// ── stripe_caution.php — Gestion caution Stripe (pré-autorisation) ──────────
// Actions AJAX (POST) : create | send_email | capture | cancel | status

$madiDir = '/var/www/html/madi.mt';
if (!is_dir($madiDir)) $madiDir = dirname(__DIR__);
require_once $madiDir . '/vendor/autoload.php';
require_once $madiDir . '/php/fonctions.php';
require_once $madiDir . '/php/config.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/nomadrive_auth.php';
$db1->query("SET NAMES 'utf8mb4'");

header('Content-Type: application/json');

if (!ndIsAuth($db1)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$stripeKey = STRIPE_MODE === 'live' ? STRIPE_LIVE_SECRET_KEY : STRIPE_TEST_SECRET_KEY;
\Stripe\Stripe::setApiKey($stripeKey);

$action     = $_POST['action']     ?? '';
$contrat_id = (int)($_POST['contrat_id'] ?? 0);
$caution_id = (int)($_POST['caution_id'] ?? 0);

function sendCautionEmail(array $caution, array $contrat, string $checkoutUrl): bool
{
    $ref          = 'ND-' . str_pad($contrat['id'], 5, '0', STR_PAD_LEFT);
    $nom_complet  = $contrat['prenom'] . ' ' . $contrat['nom'];
    $amount_str   = number_format($caution['amount'] / 100, 0, '.', '') . ' €';
    $contract_url = 'https://nomadrive.fr/contrat.php?cid=' . (int)$contrat['id'] . '&token=' . substr(hash_hmac('sha256', (string)$contrat['id'], MANAGE_PASSWORD), 0, 24);
    $first_name   = htmlspecialchars($contrat['prenom']);
    $date_fr      = !empty($contrat['date_debut']) ? (new DateTime($contrat['date_debut']))->format('d/m/Y') : '';
    $heure_str    = !empty($contrat['heure_debut']) ? substr($contrat['heure_debut'], 0, 5) : '';
    $when_en      = $date_fr . ($heure_str ? ' at ' . $heure_str : '');
    $when_fr      = $date_fr . ($heure_str ? ' &agrave; ' . $heure_str : '');
    $caution_url  = $checkoutUrl;

    $body = <<<HTML
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
          <p style="margin:0 0 12px;font-size:18px;font-weight:700;color:#0f172a;">See you soon, {$first_name}!</p>
          <p style="margin:0 0 24px;font-size:15px;color:#334155;line-height:1.6;">Your NOMADRIVE experience is booked for <strong>{$when_en}</strong>. Prepare your arrival in a few minutes — it only takes a click.</p>
          <div style="text-align:center;margin:0 0 20px;">
            <a href="{$contract_url}" style="display:inline-block;background:#0f172a;color:#ffffff;font-size:15px;font-weight:700;padding:16px 40px;border-radius:10px;text-decoration:none;">Prepare my arrival</a>
          </div>
          <table cellpadding="0" cellspacing="0" style="width:100%;margin:0 0 8px;">
            <tr>
              <td style="padding:16px;background:#fff7ed;border-radius:12px;border:1px solid #fed7aa;">
                <p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#9a3412;">&#9888; If not completed before arriving:</p>
                <ul style="margin:0;padding-left:18px;font-size:13px;color:#7c2d12;line-height:1.7;">
                  <li>A deposit of <strong>{$amount_str} will be required on-site</strong> via our card terminal.</li>
                  <li><strong>Only physical bank cards are accepted</strong> &mdash; no Apple Pay, Google Pay or virtual cards.</li>
                  <li>Your <strong>physical driving licence must be presented</strong> on the day.</li>
                </ul>
              </td>
            </tr>
          </table>
          <p style="margin:16px 0 0;font-size:13px;color:#94a3b8;">Questions? <a href="mailto:contact@nomadrive.fr" style="color:#0077b6;">contact@nomadrive.fr</a></p>
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
          <p style="margin:0 0 12px;font-size:18px;font-weight:700;color:#0f172a;">&Agrave; bient&ocirc;t, {$first_name}&nbsp;!</p>
          <p style="margin:0 0 24px;font-size:15px;color:#334155;line-height:1.6;">Votre exp&eacute;rience NOMADRIVE est pr&eacute;vue le <strong>{$when_fr}</strong>. Pr&eacute;parez votre arriv&eacute;e en quelques minutes depuis chez vous.</p>
          <div style="text-align:center;margin:0 0 20px;">
            <a href="{$contract_url}" style="display:inline-block;background:#0f172a;color:#ffffff;font-size:15px;font-weight:700;padding:16px 40px;border-radius:10px;text-decoration:none;">Pr&eacute;parer mon arriv&eacute;e</a>
          </div>
          <table cellpadding="0" cellspacing="0" style="width:100%;margin:0 0 8px;">
            <tr>
              <td style="padding:16px;background:#fff7ed;border-radius:12px;border:1px solid #fed7aa;">
                <p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#9a3412;">&#9888; Si vous n&apos;effectuez pas ces &eacute;tapes avant votre arriv&eacute;e&nbsp;:</p>
                <ul style="margin:0;padding-left:18px;font-size:13px;color:#7c2d12;line-height:1.7;">
                  <li>Une caution de <strong>{$amount_str} vous sera demand&eacute;e sur place</strong> via notre terminal.</li>
                  <li><strong>Seules les cartes bancaires physiques sont accept&eacute;es</strong> &mdash; pas d'Apple Pay, Google Pay ou cartes virtuelles.</li>
                  <li>Votre <strong>permis de conduire physique devra &ecirc;tre pr&eacute;sent&eacute;</strong> le jour J.</li>
                </ul>
              </td>
            </tr>
          </table>
          <p style="margin:16px 0 0;font-size:13px;color:#94a3b8;">Une question ? <a href="mailto:contact@nomadrive.fr" style="color:#0077b6;">contact@nomadrive.fr</a></p>
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

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp-sendkit.sarbacane.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'base64';
        $mail->setFrom('contact@nomadrive.fr', 'NOMADRIVE');
        $mail->addReplyTo('contact@nomadrive.fr', 'NOMADRIVE');
        // TODO: remplacer par $contrat['email'] quand les tests sont validés
        $mail->addAddress('jeremy.martinetti@gmail.com', $nom_complet);
        $mail->addCC('contact@nomadrive.fr', 'NOMADRIVE');
        $mail->isHTML(true);
        $mail->Subject = "Before your NOMADRIVE experience / Avant votre expérience NOMADRIVE — {$ref}";
        $mail->Body    = $body;
        $mail->send();
        return true;
    } catch (\Exception $e) {
        return false;
    }
}

// ── create ─────────────────────────────────────────────────────────────────────
if ($action === 'create') {
    if (!$contrat_id) {
        echo json_encode(['success' => false, 'message' => 'contrat_id manquant']);
        exit;
    }

    // Caution déjà active ?
    $existing = $db1->prepare("SELECT id, status, checkout_url FROM nomadrive_stripe_cautions WHERE contrat_id = ? AND status IN ('pending','authorized') ORDER BY id DESC LIMIT 1");
    $existing->execute([$contrat_id]);
    $exist = $existing->fetch(PDO::FETCH_ASSOC);
    if ($exist) {
        echo json_encode(['success' => true, 'caution_id' => (int)$exist['id'], 'checkout_url' => $exist['checkout_url'], 'already_exists' => true]);
        exit;
    }

    $stmtC = $db1->prepare("SELECT id, nom, prenom, email, vehicule_id, vehicule, date_debut, heure_debut FROM nomadrive_contrats WHERE id = ?");
    $stmtC->execute([$contrat_id]);
    $c = $stmtC->fetch(PDO::FETCH_ASSOC);
    if (!$c) {
        echo json_encode(['success' => false, 'message' => 'Contrat introuvable']);
        exit;
    }

    $ref          = 'ND-' . str_pad($contrat_id, 5, '0', STR_PAD_LEFT);
    $amount_cents = (int)STRIPE_CAUTION_AMOUNT; // déjà en centimes

    try {
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'mode'                 => 'payment',
            'payment_intent_data'  => [
                'capture_method' => 'manual',
                'description'    => "Caution NOMADRIVE — {$ref}",
                'metadata'       => ['contrat_id' => $contrat_id, 'ref' => $ref],
            ],
            'line_items' => [[
                'price_data' => [
                    'currency'     => 'eur',
                    'product_data' => [
                        'name'        => "Caution NOMADRIVE — {$ref}",
                        'description' => 'Pré-autorisation remboursable à la restitution du véhicule',
                    ],
                    'unit_amount' => $amount_cents,
                ],
                'quantity' => 1,
            ]],
            'customer_email' => $c['email'],
            'success_url'    => 'https://nomadrive.fr/contrat.php?cid=' . $contrat_id . '&token=' . substr(hash_hmac('sha256', (string)$contrat_id, MANAGE_PASSWORD), 0, 24) . '&caution=ok',
            'cancel_url'     => 'https://nomadrive.fr/contrat.php?cid=' . $contrat_id . '&token=' . substr(hash_hmac('sha256', (string)$contrat_id, MANAGE_PASSWORD), 0, 24) . '&caution=cancel',
            'expires_at'     => time() + 86400, // Stripe max = 24h
            'metadata'       => ['contrat_id' => $contrat_id, 'ref' => $ref],
        ]);

        $stmtD = $db1->prepare("SELECT id FROM nomadrive_dossiers WHERE contrat_id = ? AND statut = 'ouvert' LIMIT 1");
        $stmtD->execute([$contrat_id]);
        $dossier_id = $stmtD->fetchColumn() ?: null;

        $db1->prepare("INSERT INTO nomadrive_stripe_cautions (contrat_id, dossier_id, stripe_session_id, amount, currency, status, checkout_url) VALUES (?, ?, ?, ?, 'eur', 'pending', ?)")
            ->execute([$contrat_id, $dossier_id, $session->id, $amount_cents, $session->url]);
        $new_id = (int)$db1->lastInsertId();

        echo json_encode(['success' => true, 'caution_id' => $new_id, 'checkout_url' => $session->url]);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ── send_email ─────────────────────────────────────────────────────────────────
if ($action === 'send_email') {
    $stmtCa = $db1->prepare("SELECT * FROM nomadrive_stripe_cautions WHERE id = ?");
    $stmtCa->execute([$caution_id]);
    $caution = $stmtCa->fetch(PDO::FETCH_ASSOC);
    if (!$caution) {
        echo json_encode(['success' => false, 'message' => 'Caution introuvable']);
        exit;
    }

    $stmtC = $db1->prepare("SELECT id, nom, prenom, email, vehicule_id, vehicule, date_debut, heure_debut FROM nomadrive_contrats WHERE id = ?");
    $stmtC->execute([$caution['contrat_id']]);
    $c = $stmtC->fetch(PDO::FETCH_ASSOC);
    if (!$c || empty($c['email'])) {
        echo json_encode(['success' => false, 'message' => 'Email client introuvable']);
        exit;
    }

    $ok = sendCautionEmail($caution, $c, $caution['checkout_url']);
    if ($ok) {
        $db1->prepare("UPDATE nomadrive_stripe_cautions SET email_sent_at = NOW() WHERE id = ?")
            ->execute([$caution_id]);
    }
    echo json_encode(['success' => $ok, 'message' => $ok ? 'Email envoyé' : 'Erreur envoi']);
    exit;
}

// ── capture ────────────────────────────────────────────────────────────────────
if ($action === 'capture') {
    $stmtCa = $db1->prepare("SELECT * FROM nomadrive_stripe_cautions WHERE id = ? AND status = 'authorized'");
    $stmtCa->execute([$caution_id]);
    $caution = $stmtCa->fetch(PDO::FETCH_ASSOC);
    if (!$caution || !$caution['stripe_payment_intent_id']) {
        echo json_encode(['success' => false, 'message' => 'Caution non autorisée ou introuvable']);
        exit;
    }

    $capture_amount = min((int)($_POST['amount'] ?? $caution['amount']), $caution['amount']);

    try {
        $pi = \Stripe\PaymentIntent::retrieve($caution['stripe_payment_intent_id']);
        $pi->capture(['amount_to_capture' => $capture_amount]);
        $db1->prepare("UPDATE nomadrive_stripe_cautions SET status = 'captured', updated_at = NOW() WHERE id = ?")
            ->execute([$caution_id]);
        echo json_encode(['success' => true]);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ── cancel ─────────────────────────────────────────────────────────────────────
if ($action === 'cancel') {
    $stmtCa = $db1->prepare("SELECT * FROM nomadrive_stripe_cautions WHERE id = ? AND status = 'authorized'");
    $stmtCa->execute([$caution_id]);
    $caution = $stmtCa->fetch(PDO::FETCH_ASSOC);
    if (!$caution || !$caution['stripe_payment_intent_id']) {
        echo json_encode(['success' => false, 'message' => 'Caution non autorisée ou introuvable']);
        exit;
    }

    try {
        $pi = \Stripe\PaymentIntent::retrieve($caution['stripe_payment_intent_id']);
        $pi->cancel();
        $db1->prepare("UPDATE nomadrive_stripe_cautions SET status = 'canceled', updated_at = NOW() WHERE id = ?")
            ->execute([$caution_id]);
        echo json_encode(['success' => true]);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ── status ─────────────────────────────────────────────────────────────────────
if ($action === 'status') {
    $stmtCa = $db1->prepare("SELECT id, status, amount, checkout_url, email_sent_at, created_at FROM nomadrive_stripe_cautions WHERE contrat_id = ? ORDER BY id DESC LIMIT 1");
    $stmtCa->execute([$contrat_id]);
    $caution = $stmtCa->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'caution' => $caution ?: null]);
    exit;
}

// ── create_dossier ─────────────────────────────────────────────────────────────
if ($action === 'create_dossier') {
    $prenom      = trim($_POST['prenom']      ?? '');
    $nom         = trim($_POST['nom']         ?? '');
    $email       = trim($_POST['email']       ?? '');
    $vehicule_id = (int)($_POST['vehicule_id'] ?? 0);
    $vehicule    = trim($_POST['vehicule']    ?? '');
    $date_debut  = trim($_POST['date_debut']  ?? '') ?: null;
    $heure_debut = trim($_POST['heure_debut'] ?? '') ?: null;

    if (!$prenom || !$nom || !$email || !$vehicule_id) {
        echo json_encode(['success' => false, 'message' => 'Données manquantes']);
        exit;
    }

    // Anti-doublon : dossier ouvert pour ce client à cette date
    $chk = $db1->prepare("SELECT d.id FROM nomadrive_dossiers d JOIN nomadrive_contrats c ON c.id = d.contrat_id WHERE c.email = ? AND c.date_debut = ? AND d.statut = 'ouvert' LIMIT 1");
    $chk->execute([$email, $date_debut]);
    if ($chk->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'Dossier ouvert déjà existant pour ce client à cette date']);
        exit;
    }

    $db1->prepare("INSERT INTO nomadrive_contrats (nom, prenom, email, vehicule_id, vehicule, date_debut, heure_debut, signature) VALUES (?,?,?,?,?,?,?,'')")
        ->execute([$nom, $prenom, $email, $vehicule_id, $vehicule, $date_debut, $heure_debut]);
    $contrat_id = (int)$db1->lastInsertId();

    $db1->prepare("INSERT INTO nomadrive_dossiers (contrat_id, vehicule_id) VALUES (?,?)")
        ->execute([$contrat_id, $vehicule_id]);

    echo json_encode(['success' => true, 'contrat_id' => $contrat_id]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action inconnue']);
