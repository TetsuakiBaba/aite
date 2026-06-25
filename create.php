<?php require_once __DIR__ . '/api.php'; ?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php page_head('イベント作成 - aite'); ?>
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php">aite</a>
</header>

<main class="page">
    <form class="stack" method="post" action="api.php?action=create" id="createForm">
        <input type="hidden" name="slots" id="slotsInput">

        <section class="card stack">
            <h1>イベントを作成</h1>
            <?php if (!empty($_GET['error'])): ?>
                <p class="notice error"><?= h($_GET['error']) ?></p>
            <?php endif; ?>
            <label>
                <span>イベント名</span>
                <input name="title" required maxlength="120" placeholder="例: 企画ミーティング">
            </label>
            <label>
                <span>説明</span>
                <textarea name="description" rows="3" placeholder="参加者に伝えたいこと"></textarea>
            </label>
        </section>

        <section class="card stack">
            <div class="section-head">
                <h2>候補日時</h2>
                <button type="button" class="button small" id="manualToggle">手入力モード</button>
            </div>

            <div class="calendar-head">
                <button type="button" class="icon-button" id="prevMonth" aria-label="前の月">‹</button>
                <strong id="monthLabel"></strong>
                <button type="button" class="icon-button" id="nextMonth" aria-label="次の月">›</button>
            </div>
            <div class="calendar" id="calendar"></div>

            <div class="timeline-wrap" id="timelineWrap" hidden>
                <div class="section-head">
                    <h3 id="timelineTitle"></h3>
                    <button type="button" class="button small secondary" id="closeTimeline">閉じる</button>
                </div>
                <p class="hint">06-05の枠で30分単位に横ドラッグ。作成済みブロックはクリックで削除。</p>
                <div class="timeline" id="timeline"></div>
            </div>

            <div class="manual" id="manualPanel" hidden>
                <label>
                    <span>候補日時を直接入力</span>
                    <textarea id="manualSlots" rows="5" placeholder="2026-07-01 13:00-14:00&#10;2026-07-02 10:00-12:00"></textarea>
                </label>
                <button type="button" class="button small" id="mergeManual">反映</button>
            </div>

            <div>
                <h3>選択済み</h3>
                <div class="slot-list" id="selectedSlots"></div>
            </div>
        </section>

        <button class="button primary full" type="submit">保存してURLを作成</button>
    </form>
</main>

<?php site_footer(); ?>
<script src="app.js"></script>
</body>
</html>
