<?php
/**
 * NOMADRIVE — Synchronisation avis GYG
 * Scrape la page publique GYG via JSON-LD (schema.org)
 * À appeler depuis index.php ou via cron
 */

function fetchGygReviews(PDO $db): array {
    $meta = $db->query("SELECT * FROM nomadrive_reviews_meta WHERE source = 'gyg' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $needsSync = !$meta || strtotime($meta['last_synced_at']) < time() - 86400; // 24h

    if (!$needsSync) {
        return ['synced' => false, 'reason' => 'cache_valid'];
    }

    $url = 'https://www.getyourguide.com/nice-l314/discover-the-riviera-and-nice-by-electric-vehicle-t1285889/';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING       => 'gzip, deflate, br',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => [
            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language: fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
            'Accept-Encoding: gzip, deflate, br',
            'Cache-Control: max-age=0',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
            'Sec-CH-UA: "Chromium";v="124", "Google Chrome";v="124", "Not-A.Brand";v="99"',
            'Sec-CH-UA-Mobile: ?0',
            'Sec-CH-UA-Platform: "macOS"',
            'DNT: 1',
        ],
    ]);
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    @curl_close($ch);

    if (!$html || $code !== 200) {
        return ['synced' => false, 'reason' => "curl_error_http_{$code}"];
    }

    // Extrait le JSON-LD Product avec les reviews
    if (!preg_match('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/s', $html, $m)) {
        return ['synced' => false, 'reason' => 'jsonld_not_found'];
    }

    // Il peut y avoir plusieurs blocs JSON-LD — on cherche celui avec "review"
    preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/s', $html, $all);
    $jsonld = null;
    foreach ($all[1] as $block) {
        $decoded = json_decode($block, true);
        if ($decoded && isset($decoded['review'])) {
            $jsonld = $decoded;
            break;
        }
    }

    if (!$jsonld) {
        return ['synced' => false, 'reason' => 'no_review_jsonld'];
    }

    $overallRating = $jsonld['aggregateRating']['ratingValue'] ?? 0;
    $totalCount    = $jsonld['aggregateRating']['reviewCount'] ?? 0;
    $inserted = 0;

    $upsert = $db->prepare("
        INSERT INTO nomadrive_reviews
            (source, external_review_id, author_name, author_photo_url, rating, review_text, relative_date, fetched_at)
        VALUES
            ('gyg', :eid, :author, NULL, :rating, :text, :reldate, NOW())
        ON DUPLICATE KEY UPDATE
            author_name  = VALUES(author_name),
            rating       = VALUES(rating),
            review_text  = VALUES(review_text),
            relative_date= VALUES(relative_date),
            fetched_at   = NOW()
    ");

    foreach ($jsonld['review'] as $r) {
        $rating = (int)($r['reviewRating']['ratingValue'] ?? 0);
        $text   = trim($r['reviewBody'] ?? '');
        $author = $r['author']['name'] ?? 'Anonyme';
        $date   = substr($r['datePublished'] ?? '', 0, 10);

        if ($rating < 5 || $text === '') continue;

        $slug = preg_replace('/[^a-z0-9]/', '_', strtolower($author));
        $eid  = "gyg_{$slug}_{$date}";

        $upsert->execute([
            ':eid'    => $eid,
            ':author' => $author === 'Voyageur·se GetYourGuide' ? 'Voyageur GYG' : $author,
            ':rating' => $rating,
            ':text'   => $text,
            ':reldate'=> gygRelativeDate($date),
        ]);
        $inserted++;
    }

    $db->prepare("
        INSERT INTO nomadrive_reviews_meta (source, overall_rating, total_count, last_synced_at)
        VALUES ('gyg', :r, :t, NOW())
        ON DUPLICATE KEY UPDATE overall_rating = :r, total_count = :t, last_synced_at = NOW()
    ")->execute([':r' => round($overallRating, 1), ':t' => $totalCount]);

    return ['synced' => true, 'inserted' => $inserted, 'total' => $totalCount, 'rating' => $overallRating];
}

function gygRelativeDate(string $isoDate): string {
    $diff = (int)((time() - strtotime($isoDate)) / 86400);
    if ($diff === 0)  return 'aujourd\'hui';
    if ($diff === 1)  return 'il y a 1 jour';
    if ($diff < 7)    return "il y a {$diff} jours";
    if ($diff < 14)   return 'il y a une semaine';
    if ($diff < 30)   return 'il y a ' . floor($diff / 7) . ' semaines';
    if ($diff < 60)   return 'il y a un mois';
    return 'il y a ' . floor($diff / 30) . ' mois';
}
