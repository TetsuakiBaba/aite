<?php
require_once __DIR__ . '/api.php';
cleanup_expired_events();
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php page_head('aite'); ?>
</head>
<body>
<a class="admin-corner" href="admin.php" aria-label="管理者モード" title="管理者モード">⚙</a>
<main class="home">
    <section class="hero card">
        <p class="logo">aite</p>
        <h1>AIに聞いて、貼るだけ。</h1>
        <p class="lead">予定調整をもっと軽く。</p>
        <a class="button primary" href="create.php">イベントを作成する</a>
    </section>
</main>
<?php site_footer(); ?>
</body>
</html>
