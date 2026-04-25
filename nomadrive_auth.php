<?php
// ─── Authentification NOMADRIVE (partagée entre dashboard.php et contrat.php) ─
// Inclure ce fichier APRÈS session_start() et APRÈS que $db1 soit disponible.

define('ND_COOKIE_NAME',     'nd_remember');
define('ND_COOKIE_LIFETIME', 60 * 60 * 24 * 30); // 30 jours en secondes

/**
 * Vérifie si l'utilisateur est authentifié (session OU cookie remember-me).
 * Si le cookie est valide, rehydrate la session automatiquement.
 */
function ndIsAuth(PDO $db): bool {
    if (!empty($_SESSION['nd_auth'])) return true;

    $cookie = $_COOKIE[ND_COOKIE_NAME] ?? '';
    if (empty($cookie) || !preg_match('/^[a-f0-9]{64}$/', $cookie)) return false;

    $stmt = $db->prepare("SELECT id FROM nomadrive_auth_sessions WHERE token = :t AND expires_at > NOW() LIMIT 1");
    $stmt->execute([':t' => $cookie]);
    if ($stmt->fetch()) {
        $_SESSION['nd_auth'] = true;
        // Renouveler le cookie à chaque visite (sliding expiry)
        ndSetRememberCookie($cookie);
        return true;
    }

    // Cookie invalide ou expiré → le supprimer
    ndClearRememberCookie();
    return false;
}

/**
 * Crée un token remember-me, l'insère en BDD et pose le cookie.
 */
function ndCreateRememberToken(PDO $db): void {
    $token   = bin2hex(random_bytes(32)); // 64 caractères hex
    $expires = date('Y-m-d H:i:s', time() + ND_COOKIE_LIFETIME);
    $db->prepare("INSERT INTO nomadrive_auth_sessions (token, expires_at, created_at) VALUES (:t, :e, NOW())")
       ->execute([':t' => $token, ':e' => $expires]);
    ndSetRememberCookie($token);
}

/**
 * Révoque le token actuel (logout) et supprime le cookie.
 */
function ndRevokeRememberToken(PDO $db): void {
    $cookie = $_COOKIE[ND_COOKIE_NAME] ?? '';
    if (!empty($cookie)) {
        $db->prepare("DELETE FROM nomadrive_auth_sessions WHERE token = :t")->execute([':t' => $cookie]);
    }
    ndClearRememberCookie();
}

function ndSetRememberCookie(string $token): void {
    setcookie(ND_COOKIE_NAME, $token, [
        'expires'  => time() + ND_COOKIE_LIFETIME,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function ndClearRememberCookie(): void {
    setcookie(ND_COOKIE_NAME, '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true]);
}
