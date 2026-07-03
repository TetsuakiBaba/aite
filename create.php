<?php require_once __DIR__ . '/api.php'; ?>
<!doctype html>
<html lang="<?= h(current_lang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php page_head(t('create.title')); ?>
</head>
<body>
<header class="topbar">
    <a class="brand brand-icon-link" href="index.php" aria-label="aite"><img src="assets/aite-icon.png" alt="" width="30" height="30"></a>
</header>

<main class="page">
    <form class="stack" method="post" action="api.php?action=create" id="createForm">
        <input type="hidden" name="slots" id="slotsInput">
        <input type="hidden" name="lang" value="<?= h(current_lang()) ?>">

        <section class="card stack create-basics">
            <h1><?= h(t('create.heading')) ?></h1>
            <?php if (!empty($_GET['error'])): ?>
                <p class="notice error"><?= h($_GET['error']) ?></p>
            <?php endif; ?>
            <label>
                <span><?= h(t('create.event_name')) ?></span>
                <input name="title" id="eventTitle" required maxlength="120" placeholder="<?= h(t('create.event_name_placeholder')) ?>">
            </label>
            <label class="check-row">
                <input type="checkbox" id="descriptionToggle">
                <span><?= h(t('create.add_description')) ?></span>
            </label>
            <div id="descriptionPanel" hidden>
                <label>
                    <span><?= h(t('create.description')) ?></span>
                    <textarea name="description" rows="3" placeholder="<?= h(t('create.description_placeholder')) ?>" disabled></textarea>
                </label>
            </div>
            <label class="check-row">
                <input type="checkbox" id="dateOnlyToggle" name="date_only" value="1">
                <span><?= h(t('create.date_only_mode')) ?></span>
            </label>
            <p class="hint" id="dateOnlyHint" hidden><?= h(t('create.date_only_hint')) ?></p>
            <label class="check-row">
                <input type="checkbox" id="minDurationToggle" name="min_duration_enabled" value="1">
                <span><?= h(t('create.add_min_duration')) ?></span>
            </label>
            <div id="minDurationPanel" hidden>
                <label>
                    <span><?= h(t('create.min_duration')) ?></span>
                    <select name="min_duration_minutes" id="minDurationMinutes" disabled>
                        <?php for ($minutes = 10; $minutes <= 1440; $minutes += 10): ?>
                            <option value="<?= $minutes ?>"<?= $minutes === 60 ? ' selected' : '' ?>><?= $minutes ?></option>
                        <?php endfor; ?>
                    </select>
                </label>
                <p class="hint"><?= h(t('create.min_duration_hint')) ?></p>
            </div>
        </section>

        <section class="card stack locked-card" id="slotSection" aria-disabled="true">
            <div class="section-head">
                <h2><?= h(t('create.slots')) ?></h2>
                <button type="button" class="button small" id="manualToggle"><?= h(t('create.manual_mode')) ?></button>
            </div>
            <p class="notice neutral locked-message" id="slotLockedMessage"><?= h(t('create.slots_locked')) ?></p>

            <div class="calendar-head">
                <button type="button" class="icon-button" id="prevMonth" aria-label="<?= h(t('create.prev_month')) ?>"><span class="icon icon-nav-arrow-left" aria-hidden="true"></span></button>
                <strong id="monthLabel"></strong>
                <button type="button" class="icon-button" id="nextMonth" aria-label="<?= h(t('create.next_month')) ?>"><span class="icon icon-nav-arrow-right" aria-hidden="true"></span></button>
            </div>
            <div class="calendar" id="calendar"></div>

            <div class="timeline-wrap" id="timelineWrap" hidden>
                <div class="section-head">
                    <h3 id="timelineTitle"></h3>
                    <div class="timeline-actions">
                        <button type="button" class="button small secondary" id="timelineRangeToggle"
                            data-full-label="<?= h(t('create.show_full_range')) ?>"
                            data-standard-label="<?= h(t('create.show_standard_range')) ?>"></button>
                        <button type="button" class="button small secondary" id="closeTimeline"><?= h(t('create.close')) ?></button>
                    </div>
                </div>
                <p class="hint" id="timelineHint" data-time-hint="<?= h(t('create.timeline_hint')) ?>" data-date-hint="<?= h(t('create.date_only_timeline_hint')) ?>"><?= h(t('create.timeline_hint')) ?></p>
                <div class="timeline" id="timeline"></div>
            </div>

            <div class="manual" id="manualPanel" hidden>
                <label>
                    <span id="manualLabel" data-time-label="<?= h(t('create.manual_slots')) ?>" data-date-label="<?= h(t('create.manual_dates')) ?>"><?= h(t('create.manual_slots')) ?></span>
                    <textarea id="manualSlots" rows="5" placeholder="2026-07-01 9:00-10:00&#10;2026-07-02 13:00-14:00" aria-describedby="manualFormatHint manualMessage"></textarea>
                </label>
                <p class="hint manual-format-hint" id="manualFormatHint"
                    data-time-hint="<?= h(t('create.manual_slots_hint')) ?>"
                    data-date-hint="<?= h(t('create.manual_dates_hint')) ?>"><?= h(t('create.manual_slots_hint')) ?></p>
                <span class="inline-message error" id="manualMessage"></span>
                <button type="button" class="button small" id="mergeManual"><?= h(t('create.apply')) ?></button>
            </div>

            <div>
                <h3><?= h(t('create.selected')) ?></h3>
                <div class="slot-list" id="selectedSlots"></div>
            </div>
        </section>

        <button class="button primary full" type="submit" id="createSubmit" disabled><?= h(t('create.submit')) ?></button>
    </form>
</main>

<?php site_footer(); ?>
<?php js_i18n(); ?>
<script src="app.js"></script>
</body>
</html>
