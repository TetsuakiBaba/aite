<?php
require_once __DIR__ . '/api.php';
$id = $_GET['id'] ?? '';
$event = get_event($id);
if (!$event) {
    http_response_code(404);
    exit('イベントが見つかりません。');
}
$slots = get_slots($id);
$promptSlots = array_map(fn($s) => ['id' => $s['id'], 'text' => $s['slot_text'], 'label' => slot_label($s['slot_text'])], $slots);
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php page_head($event['title'] . ' - aite', $event['description'] !== '' ? $event['description'] : 'AIに聞いて、貼るだけ。予定調整をもっと軽く。'); ?>
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
        <?php if (!empty($_GET['saved'])): ?>
            <p class="notice success">回答を保存しました。</p>
        <?php endif; ?>
        <?php if (!empty($_GET['error'])): ?>
            <p class="notice error"><?= h($_GET['error']) ?></p>
        <?php endif; ?>
    </section>

    <section class="card stack">
        <div class="section-head">
            <h2>AIで一括入力</h2>
            <button type="button" class="button small" id="copyPrompt" data-slots='<?= h(json_encode($promptSlots, JSON_UNESCAPED_UNICODE)) ?>'>AIに聞く用プロンプトをコピー</button>
        </div>
        <p class="hint">ChatGPTやGeminiなど、利用しているカレンダーとAI連携が取れている場合は、AIに聞く用プロンプトをコピーボタンを押してAIに指示を渡してください。AIから得られたjsonテキストを下のテキストエリアにペーストすることで、自動で予定入力ができます。</p>
        <label>
            <span>AI回答をここへ貼る</span>
            <textarea id="aiJson" rows="6" placeholder='[{"slot_id":"slot_xxx","ok_ranges":[{"start":"13:00","end":"14:00"}],"busy_events":[{"title":"定例MTG","start":"14:00","end":"15:00"}]}]'></textarea>
        </label>
        <span class="inline-message" id="aiMessage"></span>
    </section>

    <form class="card stack" method="post" action="api.php?action=response" id="responseForm">
        <input type="hidden" name="event_id" value="<?= h($id) ?>">
        <input type="hidden" name="availability" id="availabilityInput">
        <div class="identity-row">
            <label>
                <span>名前</span>
                <input name="name" id="responseName" required maxlength="80" placeholder="山田 太郎">
            </label>
            <label>
                <span>編集用パスワード</span>
                <input name="edit_password" id="editPassword" type="text" required maxlength="120" placeholder="未変更なら名前と同じ">
            </label>
            <button type="button" class="button secondary" id="loadResponse">前回回答を読み込む</button>
        </div>
        <span class="inline-message" id="editMessage"></span>

        <div class="answer-list">
            <?php foreach ($slots as $slot): ?>
                <?php $parsed = parse_slot_text($slot['slot_text']); ?>
                <article class="answer-card availability-card"
                    data-slot-id="<?= h($slot['id']) ?>"
                    data-slot-text="<?= h($slot['slot_text']) ?>"
                    data-start="<?= h((string)($parsed['start'] ?? '')) ?>"
                    data-end="<?= h((string)($parsed['end'] ?? '')) ?>">
                    <h3><?= h(slot_label($slot['slot_text'])) ?></h3>
                    <?php if ($parsed): ?>
                        <p class="hint">06-05の枠内で、白い範囲だけドラッグできます。作成済みの範囲はクリックで削除できます。</p>
                        <div class="availability-track" aria-label="<?= h(slot_label($slot['slot_text'])) ?>"></div>
                        <div class="range-list"></div>
                        <div class="ai-busy-list" hidden></div>
                    <?php else: ?>
                        <p class="notice error">この候補はドラッグ回答に対応した形式ではありません。</p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <button class="button primary full" type="submit">回答を保存</button>
    </form>

    <section class="card stack">
        <h2>集計</h2>
        <div class="result-list">
            <?php foreach (aggregate($id) as $item): ?>
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
</main>

<?php site_footer(); ?>
<script src="app.js"></script>
</body>
</html>
