<?php
// ── CRON — Envoi automatique des emails demande d'avis ────────────────────────
// Cron suggéré : */15 * * * * php /var/www/html/nomadrive/cron_reviews.php >> /var/log/nomadrive_reviews.log 2>&1

$madiDir = '/var/www/html/madi.mt';
require_once $madiDir . '/vendor/autoload.php';
require_once $madiDir . '/php/fonctions.php';
require_once $madiDir . '/php/config.php';
require_once __DIR__ . '/config.php';
$db1->query("SET NAMES 'utf8mb4'");

// Mettre '' pour désactiver le CC NOMADRIVE une fois les tests validés
const REVIEW_CC_EMAIL = 'contact@nomadrive.fr';

const GOOGLE_REVIEW_URL = 'https://search.google.com/local/writereview?placeid=ChIJGdQvT-7bzRIRgGC_8yxIQKc';
const GYG_REVIEW_URL    = 'https://www.getyourguide.com/nice-l314/discover-the-riviera-and-nice-by-electric-vehicle-t1285889/#reviews';

function cronSendMail(string $toEmail, string $toName, string $subject, string $body, int $customerId, string $emailType): bool {
    global $db1;
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
        $mail->Body    = $body;
        $mail->send();

        $db1->prepare("
            INSERT INTO nomadrive_email_log (customer_id, email_to, email_type, subject, message_id, sent_at, status)
            VALUES (?, ?, ?, ?, ?, NOW(), 'sent')
        ")->execute([$customerId, $toEmail, $emailType, $subject, $mail->getLastMessageID()]);

        return true;
    } catch (\Exception $e) {
        echo "[ERROR] {$toEmail} : " . $e->getMessage() . "\n";
        return false;
    }
}

$now = date('Y-m-d H:i:s');
echo "[{$now}] Cron reviews démarré\n";

// ── Sync Bokun ────────────────────────────────────────────────────────────────
const BOKUN_PRODUCTS = [
    1194328 => '🏙️ City',
    1197812 => '🌊 French Riviera',
    1197894 => '🌅 Sunset',
];

function bokunHeaders(string $method, string $path): array {
    $accessKey = defined('BOKUN_ACCESS_KEY') ? BOKUN_ACCESS_KEY : '';
    $secretKey = defined('BOKUN_SECRET_KEY') ? BOKUN_SECRET_KEY : '';
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

function bokunPost(string $path, array $body): array {
    $ch = curl_init('https://api.bokun.io' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => bokunHeaders('POST', $path),
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return ['code' => $code, 'data' => json_decode($raw, true)];
}

$upsert = $db1->prepare("
    INSERT INTO nomadrive_customers
        (bokun_booking_id, confirmation_code, first_name, last_name, email,
         phone, phone_country_code, product_id, product_name, activity_date, start_datetime, end_datetime, participants, booking_status, channel, synced_at)
    VALUES
        (:bid, :code, :fn, :ln, :email, :phone, :pcc, :pid, :pname, :adate, :startdt, :enddt, :participants, :status, :channel, NOW())
    ON DUPLICATE KEY UPDATE
        first_name         = VALUES(first_name),
        last_name          = VALUES(last_name),
        email              = VALUES(email),
        phone              = VALUES(phone),
        phone_country_code = VALUES(phone_country_code),
        product_name       = VALUES(product_name),
        activity_date      = VALUES(activity_date),
        start_datetime     = VALUES(start_datetime),
        end_datetime       = VALUES(end_datetime),
        participants       = VALUES(participants),
        booking_status     = VALUES(booking_status),
        channel            = VALUES(channel),
        synced_at          = NOW()
");

$dateFrom  = date('Y-m-d', strtotime('-7 days'));
$dateTo    = date('Y-m-d', strtotime('+90 days'));
$page      = 0;
$totalHits = null;
$synced    = 0;

do {
    $resp = bokunPost('/booking.json/booking-search', [
        'startDateRange' => ['from' => $dateFrom, 'to' => $dateTo],
        'pageSize'       => 50,
        'page'           => $page,
    ]);

    if ($resp['code'] !== 200) {
        echo "[ERROR] Bokun sync page {$page} : HTTP {$resp['code']}\n";
        break;
    }

    $totalHits = $resp['data']['totalHits'] ?? 0;
    $results   = $resp['data']['items']     ?? [];
    if (empty($results)) break;

    foreach ($results as $booking) {
        $customer = $booking['customer'] ?? [];
        $pb       = ($booking['productBookings'] ?? [])[0] ?? [];
        $product  = $pb['product'] ?? [];

        $startMs    = $pb['startDateTime'] ?? $pb['startDate'] ?? null;
        $endMs      = $pb['endDateTime']  ?? null;
        $activityId = $product['id']      ?? null;

        $participants = max(1,
            (int)($pb['totalParticipants'] ?? 0)
            ?: array_sum(array_column($pb['fields']['priceCategoryBookings'] ?? [], 'quantity'))
            ?: 1
        );

        $upsert->execute([
            ':bid'          => (string)($booking['id'] ?? ''),
            ':code'         => $booking['confirmationCode'] ?? '',
            ':fn'           => $customer['firstName'] ?? '',
            ':ln'           => $customer['lastName']  ?? '',
            ':email'        => $customer['email']     ?? '',
            ':phone'        => $customer['phoneNumber'] ?? '',
            ':pcc'          => $customer['phoneNumberCountryCode'] ?? '',
            ':pid'          => $activityId,
            ':pname'        => BOKUN_PRODUCTS[$activityId] ?? $product['title'] ?? '',
            ':adate'        => $startMs ? date('Y-m-d',       intval($startMs / 1000)) : null,
            ':startdt'      => $startMs ? date('Y-m-d H:i:s', intval($startMs / 1000)) : null,
            ':enddt'        => $endMs   ? date('Y-m-d H:i:s', intval($endMs   / 1000)) : null,
            ':participants' => $participants,
            ':status'       => $pb['status'] ?? $booking['status'] ?? '',
            ':channel'      => $booking['seller']['title'] ?? $booking['channel']['title'] ?? '',
        ]);
        $synced++;
    }

    $page++;
} while (($page * 50) < $totalHits);

echo "[OK] Bokun sync : {$synced} réservation(s) — totalHits {$totalHits}\n";

// ── 1er email : 1h après la fin du tour ───────────────────────────────────────
$first = $db1->query("
    SELECT * FROM nomadrive_customers
    WHERE booking_status       = 'CONFIRMED'
      AND end_datetime         IS NOT NULL
      AND DATE_ADD(end_datetime, INTERVAL 1 HOUR)  <= NOW()
      AND DATE_ADD(end_datetime, INTERVAL 48 HOUR) >= NOW()
      AND review_requested_at  IS NULL
      AND email IS NOT NULL AND email != ''
    ORDER BY end_datetime ASC
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($first as $row) {
    $isGyg   = stripos($row['channel'] ?? '', 'getyourguide') !== false;
    $toName  = trim($row['first_name'] . ' ' . $row['last_name']);
    $date    = $row['activity_date'] ? date('d/m/Y', strtotime($row['activity_date'])) : date('d/m/Y');
    $gygBtnEn = $isGyg ? "<td><a href=\"" . GYG_REVIEW_URL . "\" style=\"display:inline-block;background:#FF5533;color:#ffffff;font-size:14px;font-weight:700;padding:14px 24px;border-radius:10px;text-decoration:none;\">&#11088; Review on GetYourGuide</a></td>" : '';
    $gygBtnFr = $isGyg ? "<td><a href=\"" . GYG_REVIEW_URL . "\" style=\"display:inline-block;background:#FF5533;color:#ffffff;font-size:14px;font-weight:700;padding:14px 24px;border-radius:10px;text-decoration:none;\">&#11088; Avis GetYourGuide</a></td>" : '';
    $body = buildReviewEmailBody($row['first_name'], htmlspecialchars($row['product_name'] ?? ''), $date, GOOGLE_REVIEW_URL, $gygBtnEn, $gygBtnFr);

    if (cronSendMail($row['email'], $toName, "How was your NOMADRIVE experience? / Votre avis nous intéresse !", $body, (int)$row['id'], 'review_request')) {
        $db1->prepare("UPDATE nomadrive_customers SET review_requested_at = NOW() WHERE id = ?")->execute([$row['id']]);
        echo "[OK] 1er email → {$row['email']} ({$toName})\n";
    }
}

// ── Relance : 24h après le 1er email ─────────────────────────────────────────
$followup = $db1->query("
    SELECT * FROM nomadrive_customers
    WHERE booking_status        = 'CONFIRMED'
      AND review_requested_at   IS NOT NULL
      AND DATE_ADD(review_requested_at, INTERVAL 24 HOUR) <= NOW()
      AND review_followup_at    IS NULL
      AND email IS NOT NULL AND email != ''
    ORDER BY review_requested_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($followup as $row) {
    $isGyg   = stripos($row['channel'] ?? '', 'getyourguide') !== false;
    $toName  = trim($row['first_name'] . ' ' . $row['last_name']);
    $date    = $row['activity_date'] ? date('d/m/Y', strtotime($row['activity_date'])) : date('d/m/Y');
    $gygBtnEn = $isGyg ? "<td><a href=\"" . GYG_REVIEW_URL . "\" style=\"display:inline-block;background:#FF5533;color:#ffffff;font-size:14px;font-weight:700;padding:14px 24px;border-radius:10px;text-decoration:none;\">&#11088; Review on GetYourGuide</a></td>" : '';
    $gygBtnFr = $isGyg ? "<td><a href=\"" . GYG_REVIEW_URL . "\" style=\"display:inline-block;background:#FF5533;color:#ffffff;font-size:14px;font-weight:700;padding:14px 24px;border-radius:10px;text-decoration:none;\">&#11088; Avis GetYourGuide</a></td>" : '';
    $body = buildReviewEmailBody($row['first_name'], htmlspecialchars($row['product_name'] ?? ''), $date, GOOGLE_REVIEW_URL, $gygBtnEn, $gygBtnFr);

    if (cronSendMail($row['email'], $toName, "A reminder — How was your NOMADRIVE experience? / Un petit rappel de votre avis", $body, (int)$row['id'], 'review_followup')) {
        $db1->prepare("UPDATE nomadrive_customers SET review_followup_at = NOW() WHERE id = ?")->execute([$row['id']]);
        echo "[OK] Relance → {$row['email']} ({$toName})\n";
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Cron reviews terminé\n";

function buildReviewEmailBody(string $firstName, string $tour, string $date, string $googleUrl, string $gygBtnEn, string $gygBtnFr): string {
    return <<<HTML
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
        <td style="padding:40px 40px 8px;">
          <p style="margin:0 0 16px;font-size:18px;font-weight:700;color:#0f172a;">Thank you {$firstName}!</p>
          <p style="margin:0 0 12px;font-size:15px;color:#334155;line-height:1.6;">We hope your <strong>{$tour}</strong> experience on <strong>{$date}</strong> was unforgettable.</p>
          <p style="margin:0 0 24px;font-size:15px;color:#334155;line-height:1.6;">If you enjoyed your tour, leaving a review makes a huge difference for us &mdash; it only takes 2 minutes!</p>
          <table cellpadding="0" cellspacing="0" style="margin-bottom:32px;">
            <tr><td style="padding-right:12px;"><a href="{$googleUrl}" style="display:inline-block;background:#1a1a2e;color:#ffffff;font-size:14px;font-weight:700;padding:14px 24px;border-radius:10px;text-decoration:none;">&#11088; Leave a Google Review</a></td>{$gygBtnEn}</tr>
          </table>
        </td>
      </tr>
      <tr>
        <td style="padding:0 40px;">
          <div style="border-top:1px solid #e2e8f0;"></div>
          <p style="text-align:center;font-size:11px;color:#94a3b8;margin:16px 0;">&mdash; Version fran&ccedil;aise ci-dessous &mdash;</p>
          <div style="border-top:1px solid #e2e8f0;"></div>
        </td>
      </tr>
      <tr>
        <td style="padding:32px 40px 8px;">
          <p style="margin:0 0 16px;font-size:18px;font-weight:700;color:#0f172a;">Merci {$firstName} !</p>
          <p style="margin:0 0 12px;font-size:15px;color:#334155;line-height:1.6;">Nous esp&eacute;rons que votre exp&eacute;rience <strong>{$tour}</strong> du <strong>{$date}</strong> a &eacute;t&eacute; m&eacute;morable.</p>
          <p style="margin:0 0 24px;font-size:15px;color:#334155;line-height:1.6;">Si vous avez appr&eacute;ci&eacute; votre tour, votre avis nous aide &eacute;norm&eacute;ment &agrave; nous faire conna&icirc;tre. Cela ne prend que 2 minutes !</p>
          <table cellpadding="0" cellspacing="0" style="margin-bottom:32px;">
            <tr><td style="padding-right:12px;"><a href="{$googleUrl}" style="display:inline-block;background:#1a1a2e;color:#ffffff;font-size:14px;font-weight:700;padding:14px 24px;border-radius:10px;text-decoration:none;">&#11088; Laisser un avis Google</a></td>{$gygBtnFr}</tr>
          </table>
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
}
