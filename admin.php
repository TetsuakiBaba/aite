<?php
require_once __DIR__ . '/api.php';

$id = get_string('id');
$token = get_string('token');

if ($id !== '') {
    $event = require_admin($id, $token);
    $slots = get_slots($id);
    $responses = responses_with_answers($id);
    $summary = aggregate($id);
    $dateOnly = !empty($event['date_only']);
    $responseCount = count($responses);
    $notes = response_notes($id);
    $ranking = ranked_summary_items($summary, $dateOnly);
    $publicUrl = event_url($id);
    $adminUrl = admin_url($id, $token);
    $csvUrl = 'api.php?action=csv&id=' . rawurlencode($id) . '&token=' . rawurlencode($token);
?>
    <!doctype html>
    <html lang="<?= h(current_lang()) ?>">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php page_head(t('admin.title', $event['title'])); ?>
    </head>

    <body>
        <header class="topbar">
            <a class="brand brand-icon-link" href="index.php" aria-label="aite"><img src="assets/aite-icon.png" alt="" width="30" height="30"></a>
        </header>

        <main class="page">
            <section class="card stack">
                <h1><?= h($event['title']) ?></h1>
                <?php if ($event['description'] !== ''): ?>
                    <p class="description"><?= nl2br(linkify($event['description'])) ?></p>
                <?php endif; ?>
                <label>
                    <span><?= h(t('admin.response_url')) ?></span>
                    <span class="copy-field">
                        <input value="<?= h($publicUrl) ?>" readonly>
                        <button type="button" class="button secondary copy-url-button" data-copy-value="<?= h($publicUrl) ?>"><?= h(t('js.copy_url')) ?></button>
                    </span>
                </label>
                <label>
                    <span><?= h(t('admin.admin_url')) ?></span>
                    <span class="copy-field">
                        <input value="<?= h($adminUrl) ?>" readonly>
                        <button type="button" class="button secondary copy-url-button" data-copy-value="<?= h($adminUrl) ?>"><?= h(t('js.copy_url')) ?></button>
                    </span>
                </label>
                <div class="actions">
                    <a class="button" href="<?= h($publicUrl) ?>"><?= h(t('admin.open_response')) ?></a>
                    <a class="button" href="<?= h($csvUrl) ?>"><?= h(t('admin.csv_download')) ?></a>
                </div>
            </section>

            <section class="card stack">
                <h2><?= h(t('event.summary')) ?></h2>
                <?php render_summary_response_count($responseCount); ?>
                <?php render_summary_ranking($ranking, $dateOnly, $notes); ?>
                <?php render_response_notes($notes); ?>
                <div class="result-list">
                    <?php foreach ($summary as $item): ?>
                        <article class="result-row">
                            <strong><?= h(slot_label($item['slot']['slot_text'])) ?></strong>
                            <?php if ($dateOnly): ?>
                                <span><?= h(t('event.available_date')) ?>: <strong><?= h(t('common.person_count', (int)$item['o'])) ?></strong></span>
                            <?php else: ?>
                                <span><?= h(t('event.best_overlap')) ?><strong><?= h(best_overlap_label($item)) ?></strong></span>
                            <?php endif; ?>
                            <?php if (!$dateOnly && $item['parsed']): ?>
                                <div class="overlap-chart" aria-label="<?= h(t('event.overlap_aria')) ?>">
                                    <div class="overlap-ticks">
                                        <?php foreach (overlap_ticks($item) as $tick): ?>
                                            <span class="<?= overlap_tick_visible($tick) ? '' : 'minor' ?>" style="left: <?= h(sprintf('%.4f', $tick['left'])) ?>%"><?= h($tick['label']) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="overlap-bar">
                                        <?php foreach (overlap_segments($item) as $segment): ?>
                                            <div class="overlap-segment <?= h(overlap_segment_class($segment)) ?>"
                                                style="width: <?= h(sprintf('%.4f', $segment['width'])) ?>%; --overlap-alpha: <?= h(overlap_segment_alpha($item, $segment)) ?>;"
                                                title="<?= h($segment['start_time'] . '-' . $segment['end_time'] . ': ' . t('common.person_count', (int)$segment['count'])) ?>">
                                                <?php if ((int)$segment['count'] > 0): ?><span><?= (int)$segment['count'] ?></span><?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if ($dateOnly): ?>
                                <?php $availableNames = array_values(array_map(fn($answer) => $answer['name'], array_filter($item['answers'], fn($answer) => ($answer['status'] ?? '') === 'o'))); ?>
                                <small><?= h($availableNames ? implode(' / ', $availableNames) : t('event.no_available_dates')) ?></small>
                            <?php elseif ($item['ranges']): ?>
                                <small>
                                    <?php foreach ($item['ranges'] as $range): ?>
                                        <?= h($range['name']) ?>: <?= h($range['start_time']) ?>-<?= h($range['end_time']) ?>
                                    <?php endforeach; ?>
                                </small>
                            <?php else: ?>
                                <small><?= h(t('event.no_ok_ranges')) ?></small>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="card stack">
                <h2><?= h(t('admin.responses')) ?></h2>
                <?php if (!$responses): ?>
                    <p class="muted"><?= h(t('admin.no_responses')) ?></p>
                <?php else: ?>
                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th><?= h(t('common.name')) ?></th>
                                    <?php foreach ($slots as $slot): ?>
                                        <th><?= h(slot_label($slot['slot_text'])) ?></th>
                                    <?php endforeach; ?>
                                    <th><?= h(t('common.note')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($responses as $response): ?>
                                    <tr>
                                        <th><?= h($response['name']) ?></th>
                                        <?php foreach ($slots as $slot): ?>
                                            <td><?= $dateOnly ? h(status_label($response['answers'][$slot['id']] ?? null)) : h(range_label($response['ranges'][$slot['id']] ?? [])) ?></td>
                                        <?php endforeach; ?>
                                        <td class="note-cell"><?= nl2br(h($response['note'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </main>
        <?php site_footer(); ?>
        <?php js_i18n(); ?>
        <script>
            window.AITE_EVENT_HISTORY_ITEM = <?= json_encode(['id' => $id, 'title' => $event['title'], 'url' => $adminUrl, 'type' => 'admin', 'createdAt' => $event['created_at'] ?? null], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        </script>
        <script src="app.js"></script>
    </body>

    </html>
<?php
    exit;
}

$langQuery = 'lang=' . rawurlencode(current_lang());
$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post_string('action');

    if ($action === 'login') {
        try {
            if (system_admin_login(post_string('admin_token'))) {
                redirect_to('admin.php?' . $langQuery);
            }
            $loginError = t('admin.invalid_token');
        } catch (UserError $e) {
            $loginError = $e->getMessage();
        }
    } else {
        if (!is_system_admin_session() || !is_valid_csrf_token(post_string('csrf_token'))) {
            http_response_code(403);
            exit(h(t('admin.invalid_request')));
        }

        if ($action === 'delete_event') {
            delete_event(post_string('event_id'));
            redirect_to('admin.php?deleted=1&' . $langQuery);
        }

        if ($action === 'reset_database') {
            reset_database();
            redirect_to('admin.php?reset=1&' . $langQuery);
        }

        if ($action === 'logout') {
            system_admin_logout();
            redirect_to('admin.php?' . $langQuery);
        }
    }
}

$isAdmin = is_system_admin_session();
$csrfToken = $isAdmin ? csrf_token() : '';
$events = $isAdmin ? list_events() : [];
?>
<!doctype html>
<html lang="<?= h(current_lang()) ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php page_head(t('admin.system_title')); ?>
</head>

<body>
    <header class="topbar">
        <a class="brand brand-icon-link" href="index.php" aria-label="aite"><img src="assets/aite-icon.png" alt="" width="30" height="30"></a>
    </header>

    <main class="page">
        <section class="card stack">
            <h1><?= h(t('admin.mode')) ?></h1>
            <?php if (!$isAdmin): ?>
                <?php if ($loginError !== ''): ?>
                    <p class="notice error"><?= h($loginError) ?></p>
                <?php endif; ?>
                <form class="stack" method="post" action="admin.php">
                    <input type="hidden" name="lang" value="<?= h(current_lang()) ?>">
                    <input type="hidden" name="action" value="login">
                    <label>
                        <span><?= h(t('admin.token')) ?></span>
                        <input name="admin_token" type="password" autocomplete="current-password" required>
                    </label>
                    <button class="button primary" type="submit"><?= h(t('admin.open_mode')) ?></button>
                </form>
            <?php else: ?>
                <?php if (!empty($_GET['deleted'])): ?>
                    <p class="notice success"><?= h(t('admin.event_deleted')) ?></p>
                <?php endif; ?>
                <?php if (!empty($_GET['reset'])): ?>
                    <p class="notice success"><?= h(t('admin.database_reset')) ?></p>
                <?php endif; ?>
                <div class="admin-summary">
                    <span><?= h(t('admin.event_count')) ?><strong><?= count($events) ?></strong></span>
                    <span><?= h(t('admin.auto_delete')) ?></span>
                </div>
                <form method="post" action="admin.php" onsubmit="return confirm('<?= h(t('admin.reset_confirm')) ?>');">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="lang" value="<?= h(current_lang()) ?>">
                    <input type="hidden" name="action" value="reset_database">
                    <button class="button danger" type="submit"><?= h(t('admin.reset_database')) ?></button>
                </form>
                <form method="post" action="admin.php">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="lang" value="<?= h(current_lang()) ?>">
                    <input type="hidden" name="action" value="logout">
                    <button class="button secondary" type="submit"><?= h(t('admin.logout')) ?></button>
                </form>
            <?php endif; ?>
        </section>

        <?php if ($isAdmin): ?>
            <section class="card stack">
                <h2><?= h(t('admin.event_list')) ?></h2>
                <?php if (!$events): ?>
                    <p class="muted"><?= h(t('admin.no_events')) ?></p>
                <?php else: ?>
                    <div class="table-scroll">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th><?= h(t('admin.event')) ?></th>
                                    <th><?= h(t('admin.created_at')) ?></th>
                                    <th><?= h(t('admin.updated_at')) ?></th>
                                    <th><?= h(t('admin.slot_count')) ?></th>
                                    <th><?= h(t('admin.response_count')) ?></th>
                                    <th><?= h(t('admin.actions')) ?></th>
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
                                                <a class="button small" href="<?= h(admin_url($event['id'], $event['admin_token'])) ?>"><?= h(t('admin.event_admin')) ?></a>
                                                <form method="post" action="admin.php" onsubmit="return confirm('<?= h(t('admin.delete_confirm')) ?>');">
                                                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                    <input type="hidden" name="lang" value="<?= h(current_lang()) ?>">
                                                    <input type="hidden" name="action" value="delete_event">
                                                    <input type="hidden" name="event_id" value="<?= h($event['id']) ?>">
                                                    <button class="button small danger" type="submit"><?= h(t('admin.delete')) ?></button>
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
