<?php
// ── Webhook Sarbacane SendKit — réception des événements email ────────────────
// URL à configurer dans Sarbacane : https://nomadrive.fr/webhook_sarbacane.php
// Événements attendus : delivered, bounce, complaint, unsubscribe, open, click

$madiDir = '/var/www/html/madi.mt';
require_once $madiDir . '/vendor/autoload.php';
require_once $madiDir . '/php/fonctions.php';
require_once $madiDir . '/php/config.php';
require_once __DIR__ . '/config.php';
$db1->query("SET NAMES 'utf8mb4'");

header('Content-Type: application/json');

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (empty($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

// Sarbacane peut envoyer un tableau d'événements ou un objet unique
$events = isset($data[0]) ? $data : [$data];

foreach ($events as $event) {
    $messageId = $event['messageId'] ?? $event['message_id'] ?? $event['MessageId'] ?? null;
    $eventType = strtolower($event['event'] ?? $event['type'] ?? '');
    $email     = $event['email'] ?? $event['to'] ?? null;
    $timestamp = $event['timestamp'] ?? $event['date'] ?? null;
    $eventAt   = $timestamp ? date('Y-m-d H:i:s', is_numeric($timestamp) ? (int)$timestamp : strtotime($timestamp)) : date('Y-m-d H:i:s');

    if (!$messageId && !$email) continue;

    // Mise à jour du log email
    $db1->prepare("
        UPDATE nomadrive_email_log
        SET status        = ?,
            webhook_event = ?,
            webhook_at    = ?,
            raw_webhook   = ?
        WHERE message_id = ?
           OR (email_to = ? AND webhook_event IS NULL)
        ORDER BY sent_at DESC
        LIMIT 1
    ")->execute([$eventType, $eventType, $eventAt, json_encode($event), $messageId, $email]);
}

http_response_code(200);
echo json_encode(['ok' => true, 'processed' => count($events)]);
