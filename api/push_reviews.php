<?php
/**
 * NOMADRIVE — Endpoint de réception des avis GYG (poussés depuis le Mac)
 * Appel : POST /push_reviews.php
 * Header : X-Push-Token: <token>
 * Body   : JSON { source, reviews: [...], overall_rating, total_count }
 */

$madiDir = '/var/www/html/madi.mt';
if (!is_dir($madiDir)) $madiDir = dirname(dirname(__DIR__));
require_once $madiDir . '/vendor/autoload.php';
require_once $madiDir . '/php/fonctions.php';
require_once $madiDir . '/php/config.php';
require_once dirname(__DIR__) . '/config.php';
$db1->query("SET NAMES 'utf8mb4'");

header('Content-Type: application/json');

// ── Authentification ──────────────────────────────────────────────────────────
$token         = $_SERVER['HTTP_X_PUSH_TOKEN'] ?? '';
$expectedToken = defined('REVIEWS_PUSH_TOKEN') ? REVIEWS_PUSH_TOKEN : '';

if (!$expectedToken || !hash_equals($expectedToken, $token)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

// ── Lecture du payload ────────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);
if (!$body || !isset($body['source'], $body['reviews'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_payload']);
    exit;
}

$source        = preg_replace('/[^a-z]/', '', strtolower($body['source']));
$overallRating = (float)($body['overall_rating'] ?? 0);
$totalCount    = (int)($body['total_count'] ?? 0);
$reviews       = $body['reviews'];

// ── Upsert avis ───────────────────────────────────────────────────────────────
$upsert = $db1->prepare("
    INSERT INTO nomadrive_reviews
        (source, external_review_id, author_name, author_photo_url, rating, review_text, relative_date, fetched_at)
    VALUES
        (:source, :eid, :author, :photo, :rating, :text, :reldate, NOW())
    ON DUPLICATE KEY UPDATE
        author_name   = VALUES(author_name),
        rating        = VALUES(rating),
        review_text   = VALUES(review_text),
        relative_date = VALUES(relative_date),
        fetched_at    = NOW()
");

$inserted = 0;
foreach ($reviews as $r) {
    $upsert->execute([
        ':source'  => $source,
        ':eid'     => $r['external_review_id'],
        ':author'  => $r['author_name'],
        ':photo'   => $r['author_photo_url'] ?? null,
        ':rating'  => (int)($r['rating'] ?? 5),
        ':text'    => $r['review_text'] ?? '',
        ':reldate' => $r['relative_date'] ?? null,
    ]);
    $inserted++;
}

// ── Mise à jour meta ──────────────────────────────────────────────────────────
$db1->prepare("
    INSERT INTO nomadrive_reviews_meta (source, overall_rating, total_count, last_synced_at)
    VALUES (:s, :r, :t, NOW())
    ON DUPLICATE KEY UPDATE overall_rating = :r, total_count = :t, last_synced_at = NOW()
")->execute([':s' => $source, ':r' => $overallRating, ':t' => $totalCount]);

echo json_encode(['ok' => true, 'inserted' => $inserted, 'source' => $source]);
