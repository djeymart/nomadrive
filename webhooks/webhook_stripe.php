<?php
// ── webhook_stripe.php — Événements Stripe compte principal (caution) ────────
// URL à configurer dans le dashboard Stripe : https://nomadrive.fr/webhooks/webhook_stripe.php
// Événements à activer : checkout.session.completed, payment_intent.succeeded,
//                        payment_intent.canceled

$madiDir = '/var/www/html/madi.mt';
if (!is_dir($madiDir)) $madiDir = dirname(dirname(__DIR__));
require_once $madiDir . '/vendor/autoload.php';
require_once $madiDir . '/php/fonctions.php';
require_once $madiDir . '/php/config.php';
require_once dirname(__DIR__) . '/config.php';
$db1->query("SET NAMES 'utf8mb4'");

$stripeKey = STRIPE_MODE === 'live' ? NDR_STRIPE_LIVE_SECRET_KEY : NDR_STRIPE_TEST_SECRET_KEY;
\Stripe\Stripe::setApiKey($stripeKey);

$payload   = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

$webhookSecret = STRIPE_MODE === 'live' ? NDR_STRIPE_LIVE_WEBHOOK_SECRET : NDR_STRIPE_TEST_WEBHOOK_SECRET;

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit;
}

$obj = $event->data->object;

switch ($event->type) {
    case 'checkout.session.completed':
        // Pré-autorisation réussie : le client a soumis sa carte.
        // Le PaymentIntent est en status 'requires_capture'.
        if (isset($obj->payment_intent) && $obj->payment_intent) {
            $db1->prepare("
                UPDATE nomadrive_stripe_cautions
                SET status = 'authorized', stripe_payment_intent_id = ?, updated_at = NOW()
                WHERE stripe_session_id = ? AND status = 'pending'
            ")->execute([$obj->payment_intent, $obj->id]);
        }
        break;

    case 'payment_intent.succeeded':
        // Capture effective du montant
        $db1->prepare("
            UPDATE nomadrive_stripe_cautions
            SET status = 'captured', updated_at = NOW()
            WHERE stripe_payment_intent_id = ? AND status = 'authorized'
        ")->execute([$obj->id]);
        break;

    case 'payment_intent.canceled':
        $db1->prepare("
            UPDATE nomadrive_stripe_cautions
            SET status = 'canceled', updated_at = NOW()
            WHERE stripe_payment_intent_id = ?
        ")->execute([$obj->id]);
        break;
}

http_response_code(200);
echo json_encode(['received' => true]);
