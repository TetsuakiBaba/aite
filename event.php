<?php
require_once __DIR__ . '/api.php';
$id = $_GET['id'] ?? '';
$event = get_event($id);
if (!$event) {
    http_response_code(404);
    exit(t('event.not_found'));
}
$slots = get_slots($id);
$dateOnly = !empty($event['date_only']);
$summary = aggregate($id);
$responseCount = response_count($id);
$notes = response_notes($id);
$ranking = ranked_summary_items($summary, $dateOnly);
$promptSlots = array_map(fn($s) => ['id' => $s['id'], 'text' => $s['slot_text'], 'label' => slot_label($s['slot_text'])], $slots);
?>
<!doctype html>
<html lang="<?= h(current_lang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php page_head($event['title'] . ' - aite', $event['description'] !== '' ? $event['description'] : t('app.description')); ?>
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
        <?php if (!empty($_GET['saved'])): ?>
            <p class="notice success"><?= h(t('event.saved')) ?></p>
        <?php endif; ?>
        <?php if (!empty($_GET['error'])): ?>
            <p class="notice error"><?= h($_GET['error']) ?></p>
        <?php endif; ?>
    </section>

    <section class="card stack" id="summaryCard">
        <div class="answered-notice" id="answeredNotice" hidden>
            <strong><?= h(t('event.already_answered')) ?></strong>
            <p><?= h(t('event.already_answered_hint')) ?></p>
        </div>
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
                        <?php if ($availableNames): ?>
                            <small><?= h(implode(' / ', $availableNames)) ?></small>
                        <?php else: ?>
                            <small><?= h(t('event.no_available_dates')) ?></small>
                        <?php endif; ?>
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

    <form class="card stack" method="post" action="api.php?action=response" id="responseForm">
        <input type="hidden" name="event_id" value="<?= h($id) ?>">
        <input type="hidden" name="lang" value="<?= h(current_lang()) ?>">
        <input type="hidden" name="availability" id="availabilityInput">
        <div class="identity-row">
            <label>
                <span><?= h(t('event.name')) ?></span>
                <input name="name" id="responseName" required maxlength="80" placeholder="<?= h(t('event.name_placeholder')) ?>">
            </label>
            <label>
                <span><?= h(t('event.edit_password')) ?></span>
                <input name="edit_password" id="editPassword" type="text" required maxlength="120" placeholder="<?= h(t('event.edit_password_placeholder')) ?>">
            </label>
            <button type="button" class="button secondary" id="loadResponse"><?= h(t('event.load_previous')) ?></button>
        </div>
        <div class="ai-action-row">
            <button type="button" class="button secondary small ai-open-button" id="openAiModal"><?= h(t('event.open_ai')) ?></button>
        </div>
        <span class="inline-message" id="editMessage"></span>

        <div class="answer-list" id="answerList">
            <?php if (!$dateOnly): ?>
                <p class="hint"><?= h(t('event.drag_hint')) ?></p>
                <div class="view-toggle-row">
                    <button type="button" class="button secondary small active" id="toggleRangeView" data-range-label="<?= h(t('event.show_range_only')) ?>" data-full-label="<?= h(t('event.show_full_day')) ?>"><?= h(t('event.show_full_day')) ?></button>
                </div>
            <?php else: ?>
                <p class="hint"><?= h(t('event.date_only_hint')) ?></p>
            <?php endif; ?>
            <?php foreach ($slots as $slot): ?>
                <?php $parsed = parse_slot_text($slot['slot_text']); ?>
                <?php if ($dateOnly): ?>
                    <article class="answer-card date-answer-card">
                        <label>
                            <input type="checkbox" name="answers[<?= h($slot['id']) ?>]" value="o" data-slot-id="<?= h($slot['id']) ?>">
                            <span>
                                <strong><?= h(slot_label($slot['slot_text'])) ?></strong>
                                <small><?= h(t('event.available_date')) ?></small>
                            </span>
                        </label>
                        <div class="ai-busy-list date-busy-list" data-slot-id="<?= h($slot['id']) ?>" hidden></div>
                    </article>
                <?php else: ?>
                    <article class="answer-card availability-card"
                        data-slot-id="<?= h($slot['id']) ?>"
                        data-slot-text="<?= h($slot['slot_text']) ?>"
                        data-start="<?= h((string)($parsed['start'] ?? '')) ?>"
                        data-end="<?= h((string)($parsed['end'] ?? '')) ?>">
                        <div class="availability-card-head">
                            <h3><?= h(slot_label($slot['slot_text'])) ?></h3>
                            <?php if ($parsed): ?>
                                <div class="availability-card-actions">
                                    <button type="button" class="button secondary small select-all-range" aria-label="<?= h(t('event.select_all_available_aria')) ?>"><?= h(t('event.select_all_available')) ?></button>
                                    <button type="button" class="button secondary small icon-button clear-range" aria-label="<?= h(t('event.clear_available_aria')) ?>" title="<?= h(t('event.clear_available')) ?>">
                                        <span class="icon icon-trash" aria-hidden="true"></span>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($parsed): ?>
                            <div class="availability-track" aria-label="<?= h(slot_label($slot['slot_text'])) ?>"></div>
                            <div class="ai-busy-list" hidden></div>
                        <?php else: ?>
                            <p class="notice error"><?= h(t('event.unsupported_slot')) ?></p>
                        <?php endif; ?>
                    </article>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <label class="response-note-field">
            <span><?= h(t('event.note')) ?></span>
            <textarea name="note" id="responseNote" rows="4" maxlength="2000" placeholder="<?= h(t('event.note_placeholder')) ?>" aria-describedby="responseNoteHint"></textarea>
        </label>
        <p class="hint" id="responseNoteHint"><?= h(t('event.note_hint')) ?></p>

        <button class="button primary full" type="submit" id="responseSubmit"><?= h(t('event.submit')) ?></button>
    </form>

    <div class="modal-backdrop" id="aiModal" hidden>
        <section class="card stack modal-card" role="dialog" aria-modal="true" aria-labelledby="aiModalTitle">
            <div class="section-head">
                <h2 id="aiModalTitle"><?= h(t('event.ai_heading')) ?></h2>
                <button type="button" class="button small" id="closeAiModal"><?= h(t('event.close_ai')) ?></button>
            </div>
            <button type="button" class="button secondary" id="copyPrompt" data-slots='<?= h(json_encode($promptSlots, JSON_UNESCAPED_UNICODE)) ?>'><?= h(t('event.copy_prompt')) ?></button>
            <p class="hint"><?= h(t('event.ai_hint')) ?></p>
            <label>
                <span><?= h(t('event.ai_answer')) ?></span>
                <textarea id="aiJson" rows="6" placeholder='[{"slot_id":"slot_xxx","ok_ranges":[{"start":"13:00","end":"14:00"}],"busy_events":[{"title":"<?= h(t('js.prompt_busy_title')) ?>","start":"14:00","end":"15:00"}]}]'></textarea>
            </label>
            <span class="inline-message" id="aiMessage"></span>
        </section>
    </div>
</main>

<?php site_footer(); ?>
<?php js_i18n(); ?>
<script>window.AITE_EVENT_HISTORY_ITEM=<?= json_encode(['id' => $id, 'title' => $event['title'], 'url' => event_url($id), 'type' => 'response'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;window.AITE_RESPONSE_STATE=<?= json_encode(['id' => $id, 'title' => $event['title'], 'url' => event_url($id), 'saved' => !empty($_GET['saved'])], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;window.AITE_RESPONSE_CONFIG=<?= json_encode(['minDurationUnits' => (int)($event['min_duration_units'] ?? 0), 'minDurationMinutes' => duration_units_to_minutes((int)($event['min_duration_units'] ?? 0)), 'dateOnly' => $dateOnly], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="app.js"></script>
</body>
</html>
