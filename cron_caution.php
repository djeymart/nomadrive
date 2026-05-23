<?php
// ── CRON — Envoi automatique de l'email pré-arrivée ───────────────────────────
// Lead times : 18h avant pour tours 10h et 14h, 10h avant pour tour 18h
//   Tour 10h → mail à J-1 16h00
//   Tour 14h → mail à J-1 20h00
//   Tour 18h → mail à J  08h00
// Cron : 0 * * * * php /var/www/html/nomadrive/cron_caution.php >> /var/log/nomadrive_caution.log 2>&1

$madiDir = '/var/www/html/madi.mt';
require_once $madiDir . '/vendor/autoload.php';
require_once $madiDir . '/php/fonctions.php';
require_once $madiDir . '/php/config.php';
require_once __DIR__ . '/config.php';
$db1->query("SET NAMES 'utf8mb4'");

// ── Auto-pré-enregistrement Bokun → contrats (désactivé par défaut) ───────────
// Crée un nomadrive_contrats minimal pour chaque résa Bokun confirmée J/J+1/J+2
// qui n'a pas encore de contrat lié, afin que le cron puisse leur envoyer l'email.
if (CRON_AUTO_PREREGISTER) {
    $bokun_sql = "
        SELECT nc.id, nc.first_name, nc.last_name, nc.email,
               DATE(nc.start_datetime)  AS date_debut,
               TIME(nc.start_datetime)  AS heure_debut
        FROM nomadrive_customers nc
        WHERE nc.booking_status = 'CONFIRMED'
          AND DATE(nc.start_datetime) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 2 DAY)
          AND nc.email IS NOT NULL AND nc.email != ''
          AND NOT EXISTS (
              SELECT 1 FROM nomadrive_contrats c WHERE c.bokun_booking_id = nc.id
          )
    ";
    $bookings = $db1->query($bokun_sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($bookings as $b) {
        $db1->prepare("INSERT INTO nomadrive_contrats (nom, prenom, email, date_debut, heure_debut, signature, bokun_booking_id) VALUES (?,?,?,?,?,'',?)")
            ->execute([strtoupper($b['last_name']), $b['first_name'], $b['email'], $b['date_debut'], $b['heure_debut'], $b['id']]);
        echo date('[Y-m-d H:i]') . " [AUTO] Contrat pré-créé : {$b['first_name']} {$b['last_name']} ({$b['date_debut']}).\n";
    }
    if (empty($bookings)) echo date('[Y-m-d H:i]') . " [AUTO] Aucune résa Bokun sans contrat.\n";
}

// Arrêt si l'envoi d'emails est désactivé
if (!CRON_CAUTION_ACTIVE) {
    echo date('[Y-m-d H:i]') . " Cron emails désactivé (cron_caution_active = 0).\n";
    exit;
}

// Fenêtre : contrats dont le moment d'envoi calculé est dans la dernière heure
$sql = "
    SELECT c.id AS contrat_id, c.nom, c.prenom, c.email,
           c.vehicule_id, c.vehicule, c.date_debut, c.heure_debut
    FROM nomadrive_contrats c
    WHERE c.date_debut IS NOT NULL
      AND c.heure_debut IS NOT NULL
      AND NOT EXISTS (
          SELECT 1 FROM nomadrive_stripe_cautions sc
          WHERE sc.contrat_id = c.id AND sc.email_sent_at IS NOT NULL
      )
      AND (
          /* Tour 18h00 : envoyer 10h avant = 08h00 même jour */
          (c.heure_debut >= '18:00:00'
            AND DATE_SUB(CONCAT(c.date_debut, ' ', c.heure_debut), INTERVAL 10 HOUR)
                BETWEEN DATE_SUB(NOW(), INTERVAL 1 HOUR) AND NOW())
          OR
          /* Tours avant 18h : envoyer 18h avant */
          (c.heure_debut < '18:00:00'
            AND DATE_SUB(CONCAT(c.date_debut, ' ', c.heure_debut), INTERVAL 18 HOUR)
                BETWEEN DATE_SUB(NOW(), INTERVAL 1 HOUR) AND NOW())
      )
";

$contrats = $db1->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if (empty($contrats)) {
    echo date('[Y-m-d H:i]') . " Aucun contrat à notifier.\n";
    exit;
}

function buildPreArrivalBody(
    string $firstName,
    string $whenEn,
    string $whenFr,
    string $contractUrlEn,
    string $contractUrlFr,
    string $amountStr
): string {
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
          <p style="margin:0 0 12px;font-size:18px;font-weight:700;color:#0f172a;">See you soon, {$firstName}!</p>
          <p style="margin:0 0 20px;font-size:15px;color:#334155;line-height:1.6;">Your NOMADRIVE experience is booked for <strong>{$whenEn}</strong>. Prepare your arrival in a few minutes — it only takes a click.</p>
          <div style="text-align:center;margin:0 0 20px;">
            <a href="{$contractUrlEn}" style="display:inline-block;background:#0f172a;color:#ffffff;font-size:15px;font-weight:700;padding:16px 40px;border-radius:10px;text-decoration:none;">Prepare my arrival</a>
          </div>
          <table cellpadding="0" cellspacing="0" style="width:100%;margin:0 0 8px;">
            <tr>
              <td style="padding:16px;background:#fff7ed;border-radius:12px;border:1px solid #fed7aa;">
                <p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#9a3412;">&#9888; If not completed before arriving:</p>
                <ul style="margin:0;padding-left:18px;font-size:13px;color:#7c2d12;line-height:1.7;">
                  <li>A deposit of <strong>{$amountStr} will be required on-site</strong> via our card terminal.</li>
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
          <p style="margin:0 0 12px;font-size:18px;font-weight:700;color:#0f172a;">&Agrave; bient&ocirc;t, {$firstName}&nbsp;!</p>
          <p style="margin:0 0 20px;font-size:15px;color:#334155;line-height:1.6;">Votre exp&eacute;rience NOMADRIVE est pr&eacute;vue le <strong>{$whenFr}</strong>. Pr&eacute;parez votre arriv&eacute;e en quelques minutes depuis chez vous.</p>
          <div style="text-align:center;margin:0 0 20px;">
            <a href="{$contractUrlFr}" style="display:inline-block;background:#0f172a;color:#ffffff;font-size:15px;font-weight:700;padding:16px 40px;border-radius:10px;text-decoration:none;">Pr&eacute;parer mon arriv&eacute;e</a>
          </div>
          <table cellpadding="0" cellspacing="0" style="width:100%;margin:0 0 8px;">
            <tr>
              <td style="padding:16px;background:#fff7ed;border-radius:12px;border:1px solid #fed7aa;">
                <p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#9a3412;">&#9888; Si vous n&apos;effectuez pas ces &eacute;tapes avant votre arriv&eacute;e&nbsp;:</p>
                <ul style="margin:0;padding-left:18px;font-size:13px;color:#7c2d12;line-height:1.7;">
                  <li>Une caution de <strong>{$amountStr} vous sera demand&eacute;e sur place</strong> via notre terminal.</li>
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
}

foreach ($contrats as $c) {
    $ref    = 'ND-' . str_pad($c['contrat_id'], 5, '0', STR_PAD_LEFT);
    $token  = substr(hash_hmac('sha256', (string)$c['contrat_id'], MANAGE_PASSWORD), 0, 24);
    $qs     = 'cid=' . (int)$c['contrat_id'] . '&token=' . urlencode($token);
    $baseUrl = 'https://nomadrive.fr';

    echo date('[Y-m-d H:i]') . " Traitement {$ref} ({$c['prenom']} {$c['nom']})...\n";

    // Ligne de tracking email (pas de session Stripe — créée lazily dans contrat.php à l'étape 3)
    $existing = $db1->prepare("SELECT id FROM nomadrive_stripe_cautions WHERE contrat_id = ? ORDER BY id DESC LIMIT 1");
    $existing->execute([$c['contrat_id']]);
    $caution_id = (int)($existing->fetchColumn() ?: 0);
    if (!$caution_id) {
        $db1->prepare("INSERT INTO nomadrive_stripe_cautions (contrat_id, amount, status) VALUES (?,?,'pending')")
            ->execute([$c['contrat_id'], (int)STRIPE_CAUTION_AMOUNT]);
        $caution_id = (int)$db1->lastInsertId();
    }

    // Paramètres email
    $contract_url_en = $baseUrl . '/contrat.php?' . $qs . '&lang=en';
    $contract_url_fr = $baseUrl . '/contrat.php?' . $qs . '&lang=fr';
    $amount_str      = number_format((int)STRIPE_CAUTION_AMOUNT / 100, 0, '.', '') . ' €';
    $date_fr         = $c['date_debut'] ? (new DateTime($c['date_debut']))->format('d/m/Y') : '';
    $heure_str       = !empty($c['heure_debut']) ? substr($c['heure_debut'], 0, 5) : '';
    $when_en         = $date_fr . ($heure_str ? ' at ' . $heure_str : '');
    $when_fr         = $date_fr . ($heure_str ? ' &agrave; ' . $heure_str : '');

    $body = buildPreArrivalBody(
        htmlspecialchars($c['prenom']),
        $when_en,
        $when_fr,
        $contract_url_en,
        $contract_url_fr,
        $amount_str
    );

    $nom_complet = $c['prenom'] . ' ' . $c['nom'];
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
        $mail->addAddress(MAIL_TEST_OVERRIDE ?? $c['email'], $nom_complet);
        $mail->addCC('contact@nomadrive.fr', 'NOMADRIVE');
        $mail->isHTML(true);
        $mail->Subject = "Before your NOMADRIVE experience / Avant votre expérience NOMADRIVE — {$ref}";
        $mail->Body    = $body;
        $mail->send();
        $sent = true;
    } catch (\Exception $e) {
        echo date('[Y-m-d H:i]') . " ERREUR email pour {$ref} : " . $e->getMessage() . "\n";
    }

    if ($sent) {
        $db1->prepare("UPDATE nomadrive_stripe_cautions SET email_sent_at = NOW() WHERE id = ? AND email_sent_at IS NULL")
            ->execute([$caution_id]);
        echo date('[Y-m-d H:i]') . " OK — email pré-arrivée envoyé pour {$ref}.\n";
    }
}
