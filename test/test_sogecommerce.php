<?php
// ── Page de test Sogecommerce — NE PAS DEPLOYER EN PROD ──────────────────────

$madiDir = '/var/www/html/madi.mt';
if (!is_dir($madiDir)) $madiDir = dirname(dirname(__DIR__));
require_once $madiDir . '/vendor/autoload.php';
require_once $madiDir . '/php/fonctions.php';
require_once $madiDir . '/php/config.php';
require_once dirname(__DIR__) . '/config.php';

// Auth basique
if (($_GET['pwd'] ?? '') !== MANAGE_PASSWORD) {
    http_response_code(403);
    die('<h2>Accès refusé.</h2><form><input name="pwd" type="password" placeholder="Mot de passe"><button>OK</button></form>
    <script>document.querySelector("form").onsubmit=e=>{e.preventDefault();location.search="?pwd="+encodeURIComponent(document.querySelector("input").value)}</script>');
}

// ── Config Sogecommerce ───────────────────────────────────────────────────────
$sogeMode   = defined('SOGE_MODE') ? SOGE_MODE : 'test';
$isProd     = $sogeMode === 'prod';

$shopId = $isProd
    ? (defined('SOGE_PROD_SHOP_ID') ? SOGE_PROD_SHOP_ID : '')
    : (defined('SOGE_TEST_SHOP_ID') ? SOGE_TEST_SHOP_ID : '');
$key = $isProd
    ? (defined('SOGE_PROD_KEY') ? SOGE_PROD_KEY : '')
    : (defined('SOGE_TEST_KEY') ? SOGE_TEST_KEY : '');

$apiBase = 'https://api-sogecommerce.societegenerale.eu/api-payment/V4';

$cautionAmount = defined('SOGE_CAUTION_AMOUNT') ? (int)SOGE_CAUTION_AMOUNT : 50000;

// ── Helper API ────────────────────────────────────────────────────────────────
function sogePost(string $endpoint, array $payload, string $shopId, string $key, string $apiBase): array {
    $url  = $apiBase . $endpoint;
    $auth = base64_encode($shopId . ':' . $key);
    $ch   = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Basic ' . $auth,
            'Content-Type: application/json',
        ],
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    return [
        'http_code' => $code,
        'body'      => json_decode($raw, true),
        'raw'       => $raw,
        'curl_error'=> $err,
    ];
}

// ── Traitement des actions POST ───────────────────────────────────────────────
$result = null;
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action) {
    if (empty($shopId) || empty($key)) {
        $result = ['error' => 'Clés Sogecommerce non configurées dans le .env (' . $sogeMode . ')'];
    } else {
        $orderId = 'TEST-SOGE-' . date('YmdHis');

        $baseUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'nomadrive.fr') . $_SERVER['REQUEST_URI'];
        $baseUrl = strtok($baseUrl, '?') . '?pwd=' . urlencode(MANAGE_PASSWORD);

        if ($action === 'create_order_url') {
            // Crée un ordre de paiement — on récupère l'URL, on ne l'envoie pas
            $payload = [
                'amount'         => $cautionAmount,
                'currency'       => 'EUR',
                'orderId'        => $orderId,
                'captureDelay'   => -1,
                'channelDetails' => [
                    'channelType' => 'MAIL_OR_TELEPHONE_ORDER',
                ],
                'expirationDate' => date('Y-m-d\TH:i:sP', strtotime('+24 hours')),
                'successUrl'     => $baseUrl . '&soge_result=success',
                'cancelUrl'      => $baseUrl . '&soge_result=cancel',
                'ipnTargetUrl'   => 'https://nomadrive.fr/nomadrive/webhook_sogecommerce.php',
                'metadata'       => ['test' => true, 'source' => 'nomadrive_test'],
            ];
            $result = sogePost('/Charge/CreatePaymentOrder', $payload, $shopId, $key, $apiBase);
            $result['action'] = 'create_order_url';
            $result['payload_sent'] = $payload;

        } elseif ($action === 'create_order_sms') {
            // Crée un ordre de paiement + envoi SMS par Sogecommerce (option Lyra SMS requise)
            $phone = preg_replace('/[^0-9+]/', '', $_POST['phone'] ?? '');
            if (empty($phone)) {
                $result = ['error' => 'Numéro de téléphone manquant.'];
            } else {
                $payload = [
                    'amount'      => $cautionAmount,
                    'currency'    => 'EUR',
                    'orderId'     => $orderId,
                    'captureDelay'=> -1,
                    'channelDetails' => [
                        'channelType'  => 'SMS',
                        'smsDetails'   => [
                            'phoneNumber' => $phone,
                        ],
                    ],
                    'expirationDate' => date('Y-m-d\TH:i:sP', strtotime('+24 hours')),
                    'metadata'    => ['test' => true, 'source' => 'nomadrive_test'],
                ];
                $result = sogePost('/Charge/CreatePaymentOrder', $payload, $shopId, $key, $apiBase);
                $result['action'] = 'create_order_sms';
                $result['payload_sent'] = $payload;
            }

        } elseif ($action === 'get_order') {
            // Récupère le détail d'un ordre existant
            $ordId = trim($_POST['payment_order_id'] ?? '');
            if (empty($ordId)) {
                $result = ['error' => 'Identifiant de l\'ordre manquant.'];
            } else {
                $result = sogePost('/Charge/GetPaymentOrder', ['paymentOrderId' => $ordId], $shopId, $key, $apiBase);
                $result['action'] = 'get_order';
            }
        }
    }
}

// ── Status badge helper ───────────────────────────────────────────────────────
$statusBadge = function(int $code): string {
    $ok = $code >= 200 && $code < 300;
    $color = $ok ? '#16a34a' : '#dc2626';
    return "<span style='display:inline-block;background:{$color};color:#fff;border-radius:4px;padding:2px 8px;font-size:12px;font-weight:700'>HTTP {$code}</span>";
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>NOMADRIVE — Test Sogecommerce</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #0f172a; color: #e2e8f0; font-family: system-ui, sans-serif; font-size: 14px; padding: 24px; }
h1 { font-size: 18px; font-weight: 800; color: #fff; margin-bottom: 4px; }
.subtitle { font-size: 12px; color: #475569; margin-bottom: 28px; }
.mode-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; margin-left: 10px; vertical-align: middle; }
.mode-test { background: #92400e33; color: #fbbf24; border: 1px solid #92400e; }
.mode-prod { background: #16a34a33; color: #4ade80; border: 1px solid #16a34a; }
.mode-warn { background: #7f1d1d33; color: #f87171; border: 1px solid #dc2626; }
.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 20px; margin-bottom: 28px; }
.card { background: #1e293b; border-radius: 12px; padding: 20px; }
.card h2 { font-size: 13px; font-weight: 700; color: #fff; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
.card h2 .tag { font-size: 10px; font-weight: 600; background: #334155; color: #94a3b8; border-radius: 4px; padding: 1px 7px; }
label { display: block; font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 5px; }
input[type=text], input[type=tel] { width: 100%; background: #0f172a; border: 1px solid #334155; color: #e2e8f0; border-radius: 8px; padding: 9px 12px; font-size: 13px; margin-bottom: 14px; }
input:focus { outline: none; border-color: #6366f1; }
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; border-radius: 8px; border: none; font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-primary { background: #6366f1; color: #fff; }
.btn-secondary { background: #334155; color: #94a3b8; }
.btn:hover { opacity: .85; }
.result { background: #0f172a; border-radius: 10px; padding: 16px; margin-top: 20px; }
.result h3 { font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 10px; }
pre { font-size: 12px; color: #a5b4fc; white-space: pre-wrap; word-break: break-all; line-height: 1.6; }
.url-box { background: #1e293b; border: 1px solid #4ade8044; border-radius: 8px; padding: 12px 14px; margin-top: 12px; }
.url-box a { color: #4ade80; font-size: 13px; word-break: break-all; }
.url-label { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 6px; }
.alert { border-radius: 8px; padding: 12px 14px; font-size: 13px; margin-bottom: 16px; }
.alert-warn { background: #92400e22; border: 1px solid #92400e; color: #fbbf24; }
.alert-err  { background: #7f1d1d22; border: 1px solid #dc2626; color: #f87171; }
.alert-ok   { background: #14532d22; border: 1px solid #16a34a; color: #4ade80; }
.config-row { display: flex; gap: 8px; align-items: center; padding: 7px 0; border-bottom: 1px solid #1e293b; font-size: 12px; }
.config-key { color: #64748b; width: 180px; flex-shrink: 0; }
.config-val { color: #e2e8f0; font-family: monospace; }
.dot-ok  { width: 8px; height: 8px; border-radius: 50%; background: #4ade80; flex-shrink: 0; }
.dot-err { width: 8px; height: 8px; border-radius: 50%; background: #f87171; flex-shrink: 0; }
</style>
</head>
<body>

<h1>NOMADRIVE — Test Sogecommerce
    <span class="mode-badge <?= $isProd ? 'mode-prod' : 'mode-test' ?>">
        <?= strtoupper($sogeMode) ?>
    </span>
    <?php if ($isProd): ?>
    <span class="mode-badge mode-warn">PRODUCTION</span>
    <?php endif; ?>
</h1>
<p class="subtitle">Page de test interne — ne pas diffuser. Basculer SOGE_MODE dans le .env pour changer d'environnement.</p>

<?php if ($isProd): ?>
<div class="alert alert-warn">Attention : vous etes en mode PRODUCTION. Les transactions seront reelles.</div>
<?php endif; ?>

<!-- Config state -->
<div class="card" style="margin-bottom:20px">
    <h2>Configuration detectee</h2>
    <div class="config-row"><div class="config-key">Environnement</div><div class="config-val"><?= htmlspecialchars($sogeMode) ?></div></div>
    <div class="config-row">
        <div class="dot-<?= !empty($shopId) ? 'ok' : 'err' ?>"></div>
        <div class="config-key">Shop ID</div>
        <div class="config-val"><?= !empty($shopId) ? substr($shopId, 0, 4) . str_repeat('*', max(0, strlen($shopId)-4)) : '<span style="color:#f87171">Non configure</span>' ?></div>
    </div>
    <div class="config-row">
        <div class="dot-<?= !empty($key) ? 'ok' : 'err' ?>"></div>
        <div class="config-key">Cle API</div>
        <div class="config-val"><?= !empty($key) ? substr($key, 0, 6) . str_repeat('*', 12) : '<span style="color:#f87171">Non configuree</span>' ?></div>
    </div>
    <div class="config-row"><div class="config-key">Montant caution</div><div class="config-val"><?= number_format($cautionAmount / 100, 2) ?> EUR</div></div>
    <div class="config-row"><div class="config-key">Endpoint API</div><div class="config-val" style="font-size:11px"><?= htmlspecialchars($apiBase) ?></div></div>
</div>

<?php if (empty($shopId) || empty($key)): ?>
<div class="alert alert-err">
    Clés manquantes — renseigner <code>SOGE_<?= strtoupper($sogeMode) ?>_SHOP_ID</code> et <code>SOGE_<?= strtoupper($sogeMode) ?>_KEY</code> dans le <code>.env</code>.
</div>
<?php endif; ?>

<div class="grid">

    <!-- TEST 1 : Créer un ordre et récupérer l'URL -->
    <div class="card">
        <h2>Ordre de paiement — URL <span class="tag">captureDelay=-1</span></h2>
        <p style="font-size:12px;color:#64748b;margin-bottom:14px">
            Cree un ordre de paiement en pre-autorisation (empreinte). Retourne une URL — vous envoyez le SMS vous-meme.
        </p>
        <form method="POST">
            <input type="hidden" name="action" value="create_order_url">
            <button class="btn btn-primary" type="submit" <?= (empty($shopId) || empty($key)) ? 'disabled' : '' ?>>
                Creer l'ordre et obtenir l'URL
            </button>
        </form>
    </div>

    <!-- TEST 2 : Envoyer par SMS via Sogecommerce -->
    <div class="card">
        <h2>Ordre de paiement — SMS direct <span class="tag">Lyra SMS</span></h2>
        <p style="font-size:12px;color:#64748b;margin-bottom:14px">
            Cree l'ordre et demande a Sogecommerce d'envoyer le SMS. Necessite l'option Lyra SMS activee sur le compte.
        </p>
        <form method="POST">
            <input type="hidden" name="action" value="create_order_sms">
            <label for="phone">Numero de telephone (ex: +33612345678)</label>
            <input type="tel" id="phone" name="phone" placeholder="+33612345678" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            <button class="btn btn-primary" type="submit" <?= (empty($shopId) || empty($key)) ? 'disabled' : '' ?>>
                Creer et envoyer par SMS
            </button>
        </form>
    </div>

    <!-- TEST 3 : Consulter un ordre existant -->
    <div class="card">
        <h2>Consulter un ordre existant</h2>
        <p style="font-size:12px;color:#64748b;margin-bottom:14px">
            Recupere le statut et les details d'un ordre de paiement cree precedemment.
        </p>
        <form method="POST">
            <input type="hidden" name="action" value="get_order">
            <label for="payment_order_id">paymentOrderId</label>
            <input type="text" id="payment_order_id" name="payment_order_id" placeholder="ex: 6fad1d1d-852f-..." value="<?= htmlspecialchars($_POST['payment_order_id'] ?? '') ?>">
            <button class="btn btn-secondary" type="submit" <?= (empty($shopId) || empty($key)) ? 'disabled' : '' ?>>
                Consulter
            </button>
        </form>
    </div>

</div>

<?php if ($result !== null): ?>
<div class="result">
    <h3>
        Resultat — <?= htmlspecialchars($result['action'] ?? 'reponse') ?>
        <?php if (isset($result['http_code'])): echo $statusBadge((int)$result['http_code']); endif; ?>
        <?php if (isset($result['curl_error']) && $result['curl_error']): ?>
            <span style="color:#f87171;font-size:12px;margin-left:8px">cURL : <?= htmlspecialchars($result['curl_error']) ?></span>
        <?php endif; ?>
    </h3>

    <?php if (isset($result['error'])): ?>
        <div class="alert alert-err" style="margin-top:12px"><?= htmlspecialchars($result['error']) ?></div>

    <?php else:
        $body = $result['body'] ?? [];
        $status = $body['status'] ?? '';
        $answer = $body['answer'] ?? [];
        $orderUrl = $answer['paymentOrderUrl'] ?? null;
        $orderId  = $answer['paymentOrderId']  ?? null;
    ?>

        <?php if ($status === 'SUCCESS'): ?>
        <div class="alert alert-ok" style="margin-top:12px">
            Requete acceptee par Sogecommerce.
            <?php if ($orderId): ?> paymentOrderId : <strong><?= htmlspecialchars($orderId) ?></strong><?php endif; ?>
        </div>
        <?php elseif ($status === 'ERROR'): ?>
        <div class="alert alert-err" style="margin-top:12px">
            Erreur Sogecommerce : <?= htmlspecialchars(($answer['errorMessage'] ?? '') . ' (' . ($answer['errorCode'] ?? '') . ')') ?>
        </div>
        <?php endif; ?>

        <?php if ($orderUrl): ?>
        <div class="url-box">
            <div class="url-label">Lien de paiement (a envoyer par SMS)</div>
            <a href="<?= htmlspecialchars($orderUrl) ?>" target="_blank"><?= htmlspecialchars($orderUrl) ?></a>
        </div>
        <?php endif; ?>

        <?php if (!empty($result['payload_sent'])): ?>
        <h3 style="margin-top:16px">Payload envoye</h3>
        <pre><?= htmlspecialchars(json_encode($result['payload_sent'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
        <?php endif; ?>

        <h3 style="margin-top:16px">Reponse brute</h3>
        <pre><?= htmlspecialchars(json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>

    <?php endif; ?>
</div>
<?php endif; ?>

</body>
</html>
