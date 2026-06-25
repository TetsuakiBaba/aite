<?php
require_once __DIR__ . '/api.php';

$id = (string)($_GET['id'] ?? '');
$token = (string)($_GET['token'] ?? '');

if ($id !== '') {
    $event = require_admin($id, $token);
    $slots = get_slots($id);
    $responses = responses_with_answers($id);
    $summary = aggregate($id);
    $publicUrl = event_url($id);
    $adminUrl = admin_url($id, $token);
    $csvUrl = 'api.php?action=csv&id=' . rawurlencode($id) . '&token=' . rawurlencode($token);
    ?>
    <!doctype html>
    <html lang="ja">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php page_head('管理 - ' . $event['title'] . ' - aite'); ?>
    </head>
    <body>
    <header class="topbar">
        <a class="brand" href="index.php">aite</a>
    </header>

    <main class="page">
        <section class="card stack">
            <h1><?= h($event['title']) ?></h1>
            <?php if ($event['description'] !== ''): ?>
                <p class="description"><?= nl2br(h($event['description'])) ?></p>
            <?php endif; ?>
            <label>
                <span>回答URL</span>
                <input value="<?= h($publicUrl) ?>" readonly>
            </label>
            <label>
                <span>管理URL</span>
                <input value="<?= h($adminUrl) ?>" readonly>
            </label>
            <div class="actions">
                <a class="button" href="<?= h($publicUrl) ?>">回答画面を開く</a>
                <a class="button" href="<?= h($csvUrl) ?>">CSVダウンロード</a>
            </div>
        </section>

        <section class="card stack">
            <h2>集計</h2>
            <div class="result-list">
                <?php foreach ($summary as $item): ?>
                    <article class="result-row">
                        <strong><?= h(slot_label($item['slot']['slot_text'])) ?></strong>
                        <span>最も重なる時間: <strong><?= h(best_overlap_label($item)) ?></strong></span>
                        <?php if ($item['parsed']): ?>
                            <div class="overlap-chart" aria-label="回答者の重なり">
                                <div class="overlap-ticks">
                                    <?php foreach (overlap_ticks($item) as $tick): ?>
                                        <span class="<?= overlap_tick_visible($tick) ? '' : 'minor' ?>" style="left: <?= h(sprintf('%.4f', $tick['left'])) ?>%"><?= h($tick['label']) ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <div class="overlap-bar">
                                    <?php foreach (overlap_segments($item) as $segment): ?>
                                        <div class="overlap-segment <?= h(overlap_segment_class($segment)) ?>"
                                            style="width: <?= h(sprintf('%.4f', $segment['width'])) ?>%; --overlap-alpha: <?= h(overlap_segment_alpha($item, $segment)) ?>;"
                                            title="<?= h($segment['start_time'] . '-' . $segment['end_time'] . ': ' . (int)$segment['count'] . '人') ?>">
                                            <?php if ((int)$segment['count'] > 0): ?><span><?= (int)$segment['count'] ?></span><?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($item['ranges']): ?>
                            <small>
                                <?php foreach ($item['ranges'] as $range): ?>
                                    <?= h($range['name']) ?>: <?= h($range['start_time']) ?>-<?= h($range['end_time']) ?>
                                <?php endforeach; ?>
                            </small>
                        <?php else: ?>
                            <small>まだOK範囲はありません。</small>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card stack">
            <h2>回答一覧</h2>
            <?php if (!$responses): ?>
                <p class="muted">まだ回答はありません。</p>
            <?php else: ?>
                <div class="table-scroll">
                    <table>
                        <thead>
                        <tr>
                            <th>名前</th>
                            <?php foreach ($slots as $slot): ?>
                                <th><?= h(slot_label($slot['slot_text'])) ?></th>
                            <?php endforeach; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($responses as $response): ?>
                            <tr>
                                <th><?= h($response['name']) ?></th>
                                <?php foreach ($slots as $slot): ?>
                                    <td><?= h(range_label($response['ranges'][$slot['id']] ?? [])) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
    <?php site_footer(); ?>
    </body>
    </html>
    <?php
    exit;
}

$adminToken = (string)($_POST['admin_token'] ?? $_GET['admin_token'] ?? '');
$isAdmin = is_system_admin($adminToken);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_system_admin($adminToken);
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'delete_event') {
        delete_event((string)($_POST['event_id'] ?? ''));
        redirect_to('admin.php?admin_token=' . rawurlencode($adminToken) . '&deleted=1');
    }

    if ($action === 'reset_database') {
        reset_database();
        redirect_to('admin.php?admin_token=' . rawurlencode($adminToken) . '&reset=1');
    }
}

$events = $isAdmin ? list_events() : [];
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php page_head('管理者モード - aite'); ?>
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php">aite</a>
</header>

<main class="page">
    <section class="card stack">
        <h1>管理者モード</h1>
        <?php if (!$isAdmin): ?>
            <?php if ($adminToken !== ''): ?>
                <p class="notice error">管理者トークンが正しくありません。</p>
            <?php endif; ?>
            <form class="stack" method="get" action="admin.php">
                <label>
                    <span>管理者トークン</span>
                    <input name="admin_token" type="password" autocomplete="current-password" required>
                </label>
                <button class="button primary" type="submit">管理者モードを開く</button>
            </form>
        <?php else: ?>
            <?php if (!empty($_GET['deleted'])): ?>
                <p class="notice success">イベントを削除しました。</p>
            <?php endif; ?>
            <?php if (!empty($_GET['reset'])): ?>
                <p class="notice success">データベースをリセットしました。</p>
            <?php endif; ?>
            <div class="admin-summary">
                <span>イベント数: <strong><?= count($events) ?></strong></span>
                <span>自動削除: 最終更新から1ヶ月後</span>
            </div>
            <form method="post" action="admin.php" onsubmit="return confirm('すべてのイベントと回答を削除します。実行しますか？');">
                <input type="hidden" name="admin_token" value="<?= h($adminToken) ?>">
                <input type="hidden" name="action" value="reset_database">
                <button class="button danger" type="submit">データベースをリセット</button>
            </form>
        <?php endif; ?>
    </section>

    <?php if ($isAdmin): ?>
        <section class="card stack">
            <h2>作成されているイベント一覧</h2>
            <?php if (!$events): ?>
                <p class="muted">作成されているイベントはありません。</p>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="admin-table">
                        <thead>
                        <tr>
                            <th>イベント</th>
                            <th>作成日</th>
                            <th>最終更新</th>
                            <th>候補</th>
                            <th>回答</th>
                            <th>操作</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <th>
                                    <a href="<?= h(event_url($event['id'])) ?>"><?= h($event['title']) ?></a>
                                    <small><?= h($event['id']) ?></small>
                                </th>
                                <td><?= h($event['created_at']) ?></td>
                                <td><?= h($event['updated_at']) ?></td>
                                <td><?= (int)$event['slot_count'] ?></td>
                                <td><?= (int)$event['response_count'] ?></td>
                                <td>
                                    <div class="admin-actions">
                                        <a class="button small" href="<?= h(admin_url($event['id'], $event['admin_token'])) ?>">個別管理</a>
                                        <form method="post" action="admin.php" onsubmit="return confirm('このイベントを削除します。実行しますか？');">
                                            <input type="hidden" name="admin_token" value="<?= h($adminToken) ?>">
                                            <input type="hidden" name="action" value="delete_event">
                                            <input type="hidden" name="event_id" value="<?= h($event['id']) ?>">
                                            <button class="button small danger" type="submit">削除</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
<?php site_footer(); ?>
</body>
</html>
