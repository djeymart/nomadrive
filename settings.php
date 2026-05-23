<?php
// ── settings.php — Paramètres opérationnels NOMADRIVE ────────────────────────
// Accès : ndIsAuth (opérateur) pour voir — super-admin MADI pour modifier.
// Le super-admin arrive via madi.mt/nd_settings.php (token HMAC 5 min).

$madiDir = '/var/www/html/madi.mt';
if (!is_dir($madiDir)) $madiDir = dirname(__DIR__);
require_once $madiDir . '/vendor/autoload.php';
require_once $madiDir . '/php/fonctions.php';
require_once $madiDir . '/php/config.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/nomadrive_auth.php';
$db1->query("SET NAMES 'utf8mb4'");

// ── Token HMAC en premier (avant ndIsAuth) ────────────────────────────────────
$super_token = trim($_GET['t'] ?? '');
$super_ts    = (int)($_GET['ts'] ?? 0);
if ($super_token && $super_ts) {
    $_r = $db1->query("SELECT valeur FROM nomadrive_settings WHERE cle = 'nd_settings_secret'");
    $nd_secret_pre = ($_r && ($v = $_r->fetchColumn()) !== false) ? $v : '';
    if ($nd_secret_pre) {
        $expected = hash_hmac('sha256', 'nd_settings:' . $super_ts, $nd_secret_pre);
        if (time() - $super_ts <= 300 && hash_equals($expected, $super_token)) {
            $_SESSION['nd_settings_unlocked'] = true;
            header('Location: settings.php');
            exit;
        }
    }
}

// ── Auth : opérateur nomadrive OU super-admin MADI (session unlocked) ─────────
if (!ndIsAuth($db1) && empty($_SESSION['nd_settings_unlocked'])) {
    header('Location: dashboard.php?view=login');
    exit;
}

// ── Chargement settings ───────────────────────────────────────────────────────
$s = [];
$r = $db1->query("SELECT cle, valeur FROM nomadrive_settings");
if ($r) foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $row) $s[$row['cle']] = $row['valeur'];

$stripe_mode           = $s['stripe_mode']           ?? 'test';
$mail_test_override    = $s['mail_test_override']     ?? '';
$caution_montant_eur   = (int)($s['caution_montant_eur']   ?? 500);
$cron_caution_active   = (int)($s['cron_caution_active']   ?? 0);
$cron_auto_preregister = (int)($s['cron_auto_preregister'] ?? 0);

$is_super = !empty($_SESSION['nd_settings_unlocked']);

// ── Sauvegarde (super-admin uniquement) ──────────────────────────────────────
$flash = null;
if ($is_super && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'stripe_mode'          => in_array($_POST['stripe_mode'] ?? '', ['test', 'live']) ? $_POST['stripe_mode'] : 'test',
        'mail_test_override'   => trim($_POST['mail_test_override'] ?? ''),
        'caution_montant_eur'  => max(1, (int)($_POST['caution_montant_eur'] ?? 500)),
        'cron_caution_active'  => isset($_POST['cron_caution_active'])   ? '1' : '0',
        'cron_auto_preregister'=> isset($_POST['cron_auto_preregister']) ? '1' : '0',
    ];
    $stmt = $db1->prepare("INSERT INTO nomadrive_settings (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)");
    foreach ($fields as $k => $v) $stmt->execute([$k, (string)$v]);
    header('Location: settings.php?saved=1');
    exit;
}

if (isset($_GET['saved'])) $flash = 'Paramètres enregistrés.';
$nd_page = 'settings';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Paramètres — NOMADRIVE</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', sans-serif; background: #f4f6f8; color: #1a1a2e; min-height: 100vh; }
    .wrap { max-width: 680px; margin: 32px auto; padding: 0 20px 60px; }
    h1 { font-size: 20px; font-weight: 700; margin-bottom: 24px; }
    .flash { background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
    .card { background: #fff; border: 1px solid #dde2e9; border-radius: 14px; padding: 24px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
    .card-title { font-size: 13px; font-weight: 700; color: #0077b6; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 18px; }
    .field { margin-bottom: 16px; }
    .field:last-child { margin-bottom: 0; }
    label { display: block; font-size: 12px; font-weight: 500; color: #6b7280; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 5px; }
    input[type=text], input[type=email], input[type=number], select {
      width: 100%; padding: 10px 12px; border: 1.5px solid #dde2e9; border-radius: 8px;
      font-size: 14px; font-family: inherit; color: #1a1a2e; background: #fff; transition: border-color .2s;
    }
    input:disabled, select:disabled { background: #f9fafb; color: #9ca3af; cursor: not-allowed; }
    input:focus, select:focus { outline: none; border-color: #0077b6; }
    .hint { font-size: 12px; color: #9ca3af; margin-top: 4px; }
    .row-value { font-size: 14px; padding: 10px 0; color: #374151; }
    .toggle-row { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f3f4f6; }
    .toggle-row:last-child { border-bottom: none; padding-bottom: 0; }
    .toggle-label { font-size: 14px; font-weight: 500; }
    .toggle-desc { font-size: 12px; color: #9ca3af; margin-top: 2px; }
    .toggle { position: relative; width: 44px; height: 24px; flex-shrink: 0; }
    .toggle input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; inset: 0; background: #d1d5db; border-radius: 24px; cursor: pointer; transition: background .2s; }
    .slider.readonly { cursor: not-allowed; }
    .slider::before { content: ''; position: absolute; width: 18px; height: 18px; left: 3px; top: 3px; background: #fff; border-radius: 50%; transition: transform .2s; }
    .toggle input:checked + .slider { background: #0077b6; }
    .toggle input:checked + .slider::before { transform: translateX(20px); }
    .toggle input:disabled + .slider { background: #d1d5db; opacity: .6; cursor: not-allowed; }
    .toggle input:disabled:checked + .slider { background: #93c5fd; }
    .badge-live { display: inline-block; background: #fef3c7; color: #92400e; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px; margin-left: 8px; }
    .badge-test { display: inline-block; background: #ede9fe; color: #5b21b6; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px; margin-left: 8px; }
    .btn-save { background: #0077b6; color: #fff; border: none; padding: 14px 32px; border-radius: 10px; font-size: 15px; font-weight: 600; font-family: inherit; cursor: pointer; width: 100%; transition: opacity .2s; }
    .btn-save:hover { opacity: .88; }

    /* Lock screen */
    .lock-screen { text-align: center; padding: 60px 20px; }
    .lock-icon { width: 64px; height: 64px; background: #f3f4f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 28px; }
    .lock-screen h2 { font-size: 18px; margin-bottom: 8px; }
    .lock-screen p { font-size: 14px; color: #6b7280; line-height: 1.6; }
    .lock-screen code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
  </style>
</head>
<body>
<?php include __DIR__ . '/_navbar.php'; ?>

<div class="wrap">
  <h1>Paramètres opérationnels</h1>

  <?php if ($flash): ?>
  <div class="flash"><?= htmlspecialchars($flash) ?></div>
  <?php endif; ?>

  <?php if (!$is_super): ?>
  <!-- ── Lock screen ──────────────────────────────────────────────────────── -->
  <div class="card lock-screen">
    <div class="lock-icon">&#128274;</div>
    <h2>Accès super-admin requis</h2>
    <p>
      Vous pouvez consulter les paramètres ci-dessous, mais seul l'administrateur MADI peut les modifier.<br><br>
      Pour obtenir l'accès, connectez-vous sur <strong>madi.mt</strong> et ouvrez<br>
      <code>madi.mt/nd_settings.php</code>
    </p>
  </div>
  <?php endif; ?>

  <form method="post">

    <!-- Stripe -->
    <div class="card">
      <div class="card-title">Stripe</div>
      <div class="field">
        <label for="stripe_mode">Mode</label>
        <?php if ($is_super): ?>
        <select id="stripe_mode" name="stripe_mode">
          <option value="test" <?= $stripe_mode === 'test' ? 'selected' : '' ?>>Test</option>
          <option value="live" <?= $stripe_mode === 'live' ? 'selected' : '' ?>>Live (production)</option>
        </select>
        <?php else: ?>
        <div class="row-value">
          <?= $stripe_mode === 'live' ? '<span class="badge-live">LIVE</span>' : '<span class="badge-test">TEST</span>' ?>
        </div>
        <?php endif; ?>
        <div class="hint">
          <?php if ($stripe_mode === 'live'): ?>Les paiements sont réels.<?php else: ?>Aucun débit réel.<?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Emails -->
    <div class="card">
      <div class="card-title">Emails</div>
      <div class="field">
        <label for="mail_test_override">Redirection email (override)</label>
        <input type="email" id="mail_test_override" name="mail_test_override"
               value="<?= htmlspecialchars($mail_test_override) ?>"
               placeholder="Laisser vide en production"
               <?= $is_super ? '' : 'disabled' ?>>
        <div class="hint">Si rempli, tous les emails vont à cette adresse au lieu du client. Vider en prod.</div>
      </div>
    </div>

    <!-- Caution -->
    <div class="card">
      <div class="card-title">Caution</div>
      <div class="field">
        <label for="caution_montant_eur">Montant caution (€)</label>
        <input type="number" id="caution_montant_eur" name="caution_montant_eur"
               value="<?= $caution_montant_eur ?>" min="1" step="1"
               <?= $is_super ? '' : 'disabled' ?>>
        <div class="hint">Montant pré-autorisé sur la carte client. Utilisé pour Stripe et l'affichage.</div>
      </div>
    </div>

    <!-- Automatisations -->
    <div class="card">
      <div class="card-title">Automatisations</div>

      <div class="toggle-row">
        <div>
          <div class="toggle-label">Cron emails pré-arrivée</div>
          <div class="toggle-desc">Active l'envoi automatique de l'email J-1 avec le lien contrat/caution.</div>
        </div>
        <label class="toggle">
          <input type="checkbox" name="cron_caution_active" <?= $cron_caution_active ? 'checked' : '' ?> <?= $is_super ? '' : 'disabled' ?>>
          <span class="slider <?= $is_super ? '' : 'readonly' ?>"></span>
        </label>
      </div>

      <div class="toggle-row">
        <div>
          <div class="toggle-label">Pré-enregistrement auto Bokun</div>
          <div class="toggle-desc">Crée automatiquement un contrat depuis chaque résa Bokun confirmée (J/J+1/J+2) pour que le cron puisse envoyer l'email pré-arrivée sans action manuelle.</div>
        </div>
        <label class="toggle">
          <input type="checkbox" name="cron_auto_preregister" <?= $cron_auto_preregister ? 'checked' : '' ?> <?= $is_super ? '' : 'disabled' ?>>
          <span class="slider <?= $is_super ? '' : 'readonly' ?>"></span>
        </label>
      </div>
    </div>

    <?php if ($is_super): ?>
    <button type="submit" class="btn-save">Enregistrer les paramètres</button>
    <?php endif; ?>

  </form>
</div>
</body>
</html>
