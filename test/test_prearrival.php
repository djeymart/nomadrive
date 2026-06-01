<?php
// ── TEST SCRIPT — À SUPPRIMER APRÈS TEST ─────────────────────────────────────
// Crée un contrat de test et envoie l'email pré-arrivée à jeremy.martinetti@gmail.com
// Stripe en mode live (selon nomadrive_settings). Le contrat créé aura signature=''.
// Nettoyage manuel dans Adminer : DELETE FROM nomadrive_contrats WHERE id = <id affiché>
//                                  DELETE FROM nomadrive_stripe_cautions WHERE contrat_id = <id>

$madiDir = '/var/www/html/madi.mt';
if (!is_dir($madiDir)) $madiDir = dirname(dirname(__DIR__));
require_once $madiDir . '/vendor/autoload.php';
require_once $madiDir . '/php/fonctions.php';
require_once $madiDir . '/php/config.php';
require_once dirname(__DIR__) . '/config.php';
$db1->query("SET NAMES 'utf8mb4'");

$test_email = 'jeremy.martinetti@gmail.com';
$tomorrow   = date('Y-m-d', strtotime('+1 day'));
$heure      = '10:00:00';

// Capture des AUTO_INCREMENT avant insertion
$ai_sql = "SELECT table_name, auto_increment FROM information_schema.tables
           WHERE table_schema = DATABASE() AND table_name IN ('nomadrive_contrats','nomadrive_stripe_cautions')";
$ai_before = [];
foreach ($db1->query($ai_sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $ai_before[$row['table_name']] = (int)$row['auto_increment'];
}

// Insertion du contrat de test
$db1->prepare("INSERT INTO nomadrive_contrats (nom, prenom, email, date_debut, heure_debut, signature) VALUES (?,?,?,?,?,'')")
    ->execute(['TEST', 'Jeremy', $test_email, $tomorrow, $heure]);
$contrat_id = (int)$db1->lastInsertId();
$ref        = 'ND-' . str_pad($contrat_id, 5, '0', STR_PAD_LEFT);

// Tracking caution
$db1->prepare("INSERT INTO nomadrive_stripe_cautions (contrat_id, amount, status) VALUES (?,?,'pending')")
    ->execute([$contrat_id, (int)STRIPE_CAUTION_AMOUNT]);
$caution_id = (int)$db1->lastInsertId();

// Lien pré-arrivée
$token           = substr(hash_hmac('sha256', (string)$contrat_id, MANAGE_PASSWORD), 0, 24);
$contract_qs     = 'cid=' . $contrat_id . '&token=' . urlencode($token);
$contract_url_en = 'https://nomadrive.fr/contrat.php?' . $contract_qs . '&lang=en';
$contract_url_fr = 'https://nomadrive.fr/contrat.php?' . $contract_qs . '&lang=fr';
$amount_str      = number_format((int)STRIPE_CAUTION_AMOUNT / 100, 0, '.', '') . ' €';
$date_fr      = (new DateTime($tomorrow))->format('d/m/Y');
$when_en      = $date_fr . ' at ' . substr($heure, 0, 5);
$when_fr      = $date_fr . ' &agrave; ' . substr($heure, 0, 5);
$first_name   = 'Jeremy';

$body = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:system-ui,-apple-system,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;">
      <tr>
        <td style="background:#0f172a;padding:28px 40px;text-align:center;">
          <img src="https://nomadrive.fr/images/logo_nomadrive.jpg" alt="NOMADRIVE" width="180" style="display:block;margin:0 auto;max-width:180px;height:auto;">
          <div style="font-size:22px;font-weight:800;color:#ffffff;letter-spacing:3px;margin-top:14px;">NOMADRIVE</div>
          <div style="font-size:12px;color:#64748b;margin-top:4px;letter-spacing:1px;">NICE &middot; C&Ocirc;TE D'AZUR</div>
        </td>
      </tr>
      <tr>
        <td style="padding:8px 40px;background:#fef3c7;text-align:center;">
          <span style="font-size:12px;font-weight:700;color:#92400e;">[EMAIL DE TEST — Stripe mode : <?= STRIPE_MODE ?>]</span>
        </td>
      </tr>
      <tr>
        <td style="padding:40px 40px 8px;">
          <p style="margin:0 0 12px;font-size:18px;font-weight:700;color:#0f172a;">See you soon, {$first_name}!</p>
          <p style="margin:0 0 20px;font-size:15px;color:#334155;line-height:1.6;">Your NOMADRIVE experience is booked for <strong>{$when_en}</strong>. Prepare your arrival in a few minutes — it only takes a click.</p>
          <div style="text-align:center;margin:0 0 20px;">
            <a href="{$contract_url_en}" style="display:inline-block;background:#0f172a;color:#ffffff;font-size:15px;font-weight:700;padding:16px 40px;border-radius:10px;text-decoration:none;">Prepare my arrival</a>
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
      <tr>
        <td style="padding:24px 40px;">
          <div style="border-top:1px solid #e2e8f0;"></div>
          <p style="text-align:center;font-size:11px;color:#94a3b8;margin:16px 0;">&mdash; Version fran&ccedil;aise ci-dessous &mdash;</p>
          <div style="border-top:1px solid #e2e8f0;"></div>
        </td>
      </tr>
      <tr>
        <td style="padding:0 40px 40px;">
          <p style="margin:0 0 12px;font-size:18px;font-weight:700;color:#0f172a;">&Agrave; bient&ocirc;t, {$first_name}&nbsp;!</p>
          <p style="margin:0 0 20px;font-size:15px;color:#334155;line-height:1.6;">Votre exp&eacute;rience NOMADRIVE est pr&eacute;vue le <strong>{$when_fr}</strong>. Pr&eacute;parez votre arriv&eacute;e en quelques minutes depuis chez vous.</p>
          <div style="text-align:center;margin:0 0 20px;">
            <a href="{$contract_url_fr}" style="display:inline-block;background:#0f172a;color:#ffffff;font-size:15px;font-weight:700;padding:16px 40px;border-radius:10px;text-decoration:none;">Pr&eacute;parer mon arriv&eacute;e</a>
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

// Envoi
$sent = false;
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
    $mail->addAddress($test_email, 'Jeremy TEST');
    $mail->addCC('contact@nomadrive.fr', 'NOMADRIVE');
    $mail->isHTML(true);
    $mail->Subject = "[TEST] Before your NOMADRIVE experience — {$ref}";
    $mail->Body    = $body;
    $mail->send();
    $sent = true;

    $db1->prepare("UPDATE nomadrive_stripe_cautions SET email_sent_at = NOW() WHERE id = ?")
        ->execute([$caution_id]);
} catch (\Exception $e) {
    echo "ERREUR SMTP : " . $e->getMessage() . "\n";
}

echo "Stripe mode   : " . STRIPE_MODE . "\n";
echo "Caution       : " . STRIPE_CAUTION_AMOUNT / 100 . " EUR\n";
echo "Contrat créé  : {$ref} (id={$contrat_id})\n";
echo "Lien EN       : {$contract_url_en}\n";
echo "Lien FR       : {$contract_url_fr}\n";
echo "Email envoyé  : " . ($sent ? "OUI à {$test_email}" : "NON") . "\n";
$ai_contrats  = $ai_before['nomadrive_contrats']          ?? $contrat_id;
$ai_cautions  = $ai_before['nomadrive_stripe_cautions']  ?? $caution_id;
echo "\nNettoyage après test :\n";
echo "  DELETE FROM nomadrive_stripe_cautions WHERE contrat_id = {$contrat_id};\n";
echo "  DELETE FROM nomadrive_dossiers         WHERE contrat_id = {$contrat_id};\n";
echo "  DELETE FROM nomadrive_contrats         WHERE id = {$contrat_id};\n";
echo "  ALTER TABLE nomadrive_contrats         AUTO_INCREMENT = {$ai_contrats};\n";
echo "  ALTER TABLE nomadrive_stripe_cautions  AUTO_INCREMENT = {$ai_cautions};\n";
