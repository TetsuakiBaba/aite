<?php
require_once __DIR__ . '/api.php';
cleanup_expired_events();
?>
<!doctype html>
<html lang="<?= h(current_lang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php page_head('aite'); ?>
</head>
<body>
<a class="admin-corner" href="admin.php" aria-label="<?= h(t('admin.mode')) ?>" title="<?= h(t('admin.mode')) ?>"><span class="icon icon-settings" aria-hidden="true"></span></a>
<main class="home">
    <section class="hero card">
        <img class="hero-icon" src="assets/aite-icon.png" alt="" width="64" height="64">
        <h1><?= h(t('home.hero')) ?></h1>
        <p class="lead"><?= h(t('home.lead')) ?></p>
        <a class="button primary" href="create.php"><?= h(t('home.create')) ?></a>
    </section>
    <section class="recent-events" id="recentEvents" hidden>
        <h2><?= h(t('home.recent_events')) ?></h2>
        <div class="recent-event-list" id="recentEventList"></div>
    </section>
</main>
<?php site_footer(); ?>
<?php js_i18n(); ?>
<script src="app.js"></script>
</body>
</html>
