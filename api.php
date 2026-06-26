<?php
declare(strict_types=1);

const DB_FILE = __DIR__ . '/data/aite.sqlite';
const ADMIN_TOKEN_FILE = __DIR__ . '/data/admin_token.txt';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dir = dirname(DB_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    init_db($pdo);
    return $pdo;
}

function init_db(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS events (
            id TEXT PRIMARY KEY,
            title TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT '',
            admin_token TEXT NOT NULL,
            min_duration_units INTEGER NOT NULL DEFAULT 0,
            date_only INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS slots (
            id TEXT PRIMARY KEY,
            event_id TEXT NOT NULL,
            slot_text TEXT NOT NULL,
            sort_order INTEGER NOT NULL,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
        );
        CREATE TABLE IF NOT EXISTS responses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_id TEXT NOT NULL,
            name TEXT NOT NULL,
            password_hash TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            UNIQUE(event_id, name),
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
        );
        CREATE TABLE IF NOT EXISTS answers (
            response_id INTEGER NOT NULL,
            slot_id TEXT NOT NULL,
            status TEXT NOT NULL CHECK(status IN ('o', 'maybe', 'x')),
            PRIMARY KEY (response_id, slot_id),
            FOREIGN KEY (response_id) REFERENCES responses(id) ON DELETE CASCADE,
            FOREIGN KEY (slot_id) REFERENCES slots(id) ON DELETE CASCADE
        );
        CREATE TABLE IF NOT EXISTS answer_ranges (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            response_id INTEGER NOT NULL,
            slot_id TEXT NOT NULL,
            start_time TEXT NOT NULL,
            end_time TEXT NOT NULL,
            start_index INTEGER NOT NULL,
            end_index INTEGER NOT NULL,
            FOREIGN KEY (response_id) REFERENCES responses(id) ON DELETE CASCADE,
            FOREIGN KEY (slot_id) REFERENCES slots(id) ON DELETE CASCADE
        );
    ");

    if (!db_has_column($pdo, 'responses', 'password_hash')) {
        $pdo->exec("ALTER TABLE responses ADD COLUMN password_hash TEXT NOT NULL DEFAULT ''");
    }

    if (!db_has_column($pdo, 'events', 'updated_at')) {
        $pdo->exec("ALTER TABLE events ADD COLUMN updated_at TEXT NOT NULL DEFAULT ''");
        $pdo->exec("UPDATE events SET updated_at = created_at WHERE updated_at = ''");
    }

    if (!db_has_column($pdo, 'events', 'min_duration_units')) {
        $pdo->exec("ALTER TABLE events ADD COLUMN min_duration_units INTEGER NOT NULL DEFAULT 0");
    }

    if (!db_has_column($pdo, 'events', 'date_only')) {
        $pdo->exec("ALTER TABLE events ADD COLUMN date_only INTEGER NOT NULL DEFAULT 0");
    }

    migrate_answer_ranges_to_current_unit($pdo);
}

function db_has_column(PDO $pdo, string $table, string $columnName): bool
{
    $columns = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll();
    foreach ($columns as $column) {
        if (($column['name'] ?? '') === $columnName) {
            return true;
        }
    }
    return false;
}

function time_text_to_index(string $time): ?int
{
    if (!preg_match('/^(\d{2}):(\d{2})$/', $time, $m)) {
        return null;
    }
    $minutes = (int)$m[1] * 60 + (int)$m[2];
    if ($minutes < 0 || $minutes > 1440 || $minutes % 10 !== 0) {
        return null;
    }
    return intdiv($minutes, 10);
}

function migrate_answer_ranges_to_current_unit(PDO $pdo): void
{
    $stmt = $pdo->query('
        SELECT ar.id, ar.start_time, ar.end_time, ar.start_index, ar.end_index, s.slot_text
        FROM answer_ranges ar
        JOIN slots s ON s.id = ar.slot_id
    ');
    $update = $pdo->prepare('UPDATE answer_ranges SET start_index = ?, end_index = ? WHERE id = ?');
    foreach ($stmt->fetchAll() as $range) {
        $slot = parse_slot_text((string)$range['slot_text']);
        $absoluteStart = time_text_to_index((string)$range['start_time']);
        $absoluteEnd = time_text_to_index((string)$range['end_time']);
        if (!$slot || $absoluteStart === null || $absoluteEnd === null) {
            continue;
        }
        $start = $absoluteStart - (int)$slot['start'];
        $end = $absoluteEnd - (int)$slot['start'];
        if ($start < 0 || $end > ((int)$slot['end'] - (int)$slot['start']) || $end <= $start) {
            continue;
        }
        if ((int)$range['start_index'] !== $start || (int)$range['end_index'] !== $end) {
            $update->execute([$start, $end, (int)$range['id']]);
        }
    }
}

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function translations(): array
{
    return [
        'ja' => [
            'app.description' => 'AIに聞いて、貼るだけ。予定調整をもっと軽く。',
            'footer.disclaimer' => '免責事項',
            'disclaimer.title' => '免責事項',
            'disclaimer.body_1' => 'aiteは日程調整を支援するためのツールです。回答内容、AIから貼り付けた情報、共有リンクの取り扱いは、利用者の責任で確認してください。',
            'disclaimer.body_2' => '予定の正確性、参加可否、第三者のサービスやAIの出力、データの保存・消失に起因する損害について、aiteは保証しません。重要な予定は参加者間で最終確認してください。',
            'disclaimer.close' => '閉じる',
            'admin.mode' => '管理者モード',
            'home.hero' => 'AIに聞いて、貼るだけ。',
            'home.lead' => '予定調整をもっと軽く。',
            'home.create' => 'イベントを作成する',
            'home.recent_events' => '最近開いたイベント',
            'home.history_response' => '回答者リンク',
            'home.history_admin' => '作成者リンク',
            'create.title' => 'イベント作成 - aite',
            'create.heading' => 'イベントを作成',
            'create.event_name' => 'イベント名',
            'create.event_name_placeholder' => '例: 企画ミーティング',
            'create.description' => '説明',
            'create.add_description' => '説明を追記',
            'create.description_placeholder' => '参加者に伝えたいこと',
            'create.add_min_duration' => '最低必要時間を設定',
            'create.min_duration' => '最低必要時間（分）',
            'create.min_duration_hint' => '回答者はこの時間より短いOK範囲を入力できません。',
            'create.date_only_mode' => '日程だけ確認する',
            'create.date_only_hint' => 'ONにすると時間指定なしで候補日だけを選び、回答者は空いている日だけを回答します。',
            'create.slots' => '候補日時',
            'create.slots_locked' => 'まずイベント名を入力してください。',
            'create.manual_mode' => '手入力モード',
            'create.prev_month' => '前の月',
            'create.next_month' => '次の月',
            'create.close' => '閉じる',
            'create.timeline_hint' => '06-05の枠で10分単位に横ドラッグ。作成済みブロックはクリックで削除。',
            'create.date_only_timeline_hint' => '候補にしたい日をクリックしてください。作成済みの日付はもう一度クリックで削除できます。',
            'create.manual_slots' => '候補日時を直接入力',
            'create.manual_dates' => '候補日を直接入力',
            'create.apply' => '反映',
            'create.selected' => '選択済み',
            'create.submit' => '保存してURLを作成',
            'event.not_found' => 'イベントが見つかりません。',
            'event.saved' => '回答を保存しました。',
            'event.already_answered' => 'あなたはすでに回答済みです。',
            'event.already_answered_hint' => '集計を確認できます。回答の修正が必要な場合だけ、名前と編集用パスワードを入力して前回回答を読み込んでください。',
            'event.ai_heading' => 'AIで一括入力',
            'event.open_ai' => 'AIで一括入力',
            'event.close_ai' => '閉じる',
            'event.copy_prompt' => 'AIに聞く用プロンプトをコピー',
            'event.ai_hint' => 'ChatGPTやGeminiなど、利用しているカレンダーとAI連携が取れている場合は、AIに聞く用プロンプトをコピーボタンを押してAIに指示を渡してください。AIから得られたjsonテキストを下のテキストエリアにペーストすることで、自動で予定入力ができます。',
            'event.ai_answer' => 'AI回答をここへ貼る',
            'event.name' => '名前',
            'event.name_placeholder' => '山田 太郎',
            'event.edit_password' => '編集用パスワード',
            'event.edit_password_placeholder' => '未変更なら名前と同じ',
            'event.load_previous' => '前回回答を読み込む',
            'event.show_range_only' => '候補範囲だけ表示',
            'event.show_full_day' => '06-05表示に戻す',
            'event.drag_hint' => '白い範囲だけドラッグできます。作成済みの範囲はクリックで削除できます。',
            'event.date_only_hint' => '空いている日程を選択してください。',
            'event.available_date' => '空いている',
            'event.unsupported_slot' => 'この候補はドラッグ回答に対応した形式ではありません。',
            'event.submit' => '回答を保存',
            'event.summary' => '集計',
            'event.best_overlap' => '最も重なる時間: ',
            'event.overlap_aria' => '回答者の重なり',
            'event.no_ok_ranges' => 'まだOK範囲はありません。',
            'event.no_available_dates' => 'まだ空いている日程の回答はありません。',
            'admin.title' => '管理 - %s - aite',
            'admin.response_url' => '回答URL',
            'admin.admin_url' => '管理URL',
            'admin.open_response' => '回答画面を開く',
            'admin.csv_download' => 'CSVダウンロード',
            'admin.responses' => '回答一覧',
            'admin.no_responses' => 'まだ回答はありません。',
            'admin.system_title' => '管理者モード - aite',
            'admin.invalid_token' => '管理者トークンが正しくありません。',
            'admin.token' => '管理者トークン',
            'admin.open_mode' => '管理者モードを開く',
            'admin.event_deleted' => 'イベントを削除しました。',
            'admin.database_reset' => 'データベースをリセットしました。',
            'admin.event_count' => 'イベント数: ',
            'admin.auto_delete' => '自動削除: 最終更新から1ヶ月後',
            'admin.reset_confirm' => 'すべてのイベントと回答を削除します。実行しますか？',
            'admin.reset_database' => 'データベースをリセット',
            'admin.event_list' => '作成されているイベント一覧',
            'admin.no_events' => '作成されているイベントはありません。',
            'admin.event' => 'イベント',
            'admin.created_at' => '作成日',
            'admin.updated_at' => '最終更新',
            'admin.slot_count' => '候補',
            'admin.response_count' => '回答',
            'admin.actions' => '操作',
            'admin.event_admin' => '個別管理',
            'admin.delete_confirm' => 'このイベントを削除します。実行しますか？',
            'admin.delete' => '削除',
            'common.name' => '名前',
            'common.error' => 'エラー: ',
            'common.person_count' => '%d人',
            'common.no_overlap' => '重なりなし',
            'error.wrong_password' => '編集用パスワードが違います。',
            'error.previous_not_found' => '前回回答が見つかりません。',
            'error.invalid_admin_url' => '管理URLが正しくありません。',
            'error.create_required' => 'イベント名と候補日時を入力してください。',
            'error.slot_30_minute_required' => '候補日時は YYYY-MM-DD HH:MM-HH:MM 形式で、開始・終了時刻を10分刻みにしてください。',
            'error.min_duration_required' => 'OK範囲は最低%d分以上にしてください。',
            'error.min_duration_invalid' => '最低必要時間は10分刻みで入力してください。',
            'error.slot_min_duration_required' => '候補日時は最低必要時間以上にしてください。',
            'error.date_required' => '候補日は YYYY-MM-DD 形式で入力してください。',
            'error.response_required' => '名前と編集用パスワードを入力してください。',
            'js.weekdays_short' => '日,月,火,水,木,金,土',
            'js.year_suffix' => '年',
            'js.month_suffix' => '月',
            'js.change_start' => '開始時間を変更',
            'js.change_end' => '終了時間を変更',
            'js.no_slots' => 'まだ候補日時がありません。',
            'js.create_slot_required' => '候補日時を1つ以上入力してください。',
            'js.slot_30_minute_required' => '時間は10分刻みにしてください。',
            'js.busy_default_title' => '予定あり',
            'js.ai_busy_title' => 'AIが確認した予定（保存されません）',
            'js.date_busy_title' => 'AIが確認した予定（判断材料）',
            'js.no_ok_selected' => 'OK範囲は未選択です。',
            'js.min_duration_required' => 'OK範囲は最低%d分以上にしてください。',
            'js.slot_min_duration_required' => '候補日時は最低必要時間以上にしてください。',
            'js.date_required' => '候補日は YYYY-MM-DD 形式で入力してください。',
            'js.load_failed' => '読み込みに失敗しました。',
            'js.previous_loaded' => '前回回答を読み込みました。',
            'js.prompt_busy_title' => '予定名',
            'js.prompt_intro' => '以下の候補日時について、私の予定表を確認してください。',
            'js.prompt_ok_ranges' => '参加可能な時間帯だけを ok_ranges に入れてください。',
            'js.prompt_available_dates' => '空いている候補日だけを status: "o" にしてください。空いていない日は status: "x" にしてください。',
            'js.prompt_date_busy_events' => '完全に空いていない日でも、予定名から参加可能そうな場合は status は "x" のまま、busy_events に予定名 title、開始 start、終了 end を入れて判断材料として残してください。',
            'js.prompt_min_duration' => 'ok_ranges は必ず%d分以上の範囲だけにしてください。',
            'js.prompt_status_o' => '参加可能なら o',
            'js.prompt_partial' => '候補時間の一部だけ参加可能な場合は、その範囲だけを返してください。',
            'js.prompt_status_maybe' => '未定なら maybe',
            'js.prompt_busy_events' => '候補時間内に入っている参加できない予定は、すべて busy_events に予定名 title、開始 start、終了 end を入れてください。',
            'js.prompt_status_x' => '参加できないなら x',
            'js.prompt_hhmm' => 'start と end は必ず HH:MM 形式にしてください。',
            'js.prompt_empty' => '参加可能な時間がなければ ok_ranges は空配列にしてください。予定がなければ busy_events は空配列にしてください。',
            'js.prompt_json_only' => '必ずJSONだけを返してください。',
            'js.prompt_slots' => '候補日時',
            'js.copied' => 'コピーしました。',
            'js.copy_url' => 'URLをコピー',
            'js.json_array_error' => '配列JSONではありません。',
            'js.no_applicable_items' => '反映できる候補がありませんでした。',
            'js.ai_applied_ranges_busy' => '%d件のOK範囲、%d件の予定を自動反映しました。',
            'js.ai_applied_count' => '%d件自動反映しました。',
            'js.check_json' => 'JSONを確認してください: %s',
            'js.delete_history' => '履歴から削除',
            'js.history_response' => '回答者リンク',
            'js.history_admin' => '作成者リンク',
        ],
        'en' => [
            'app.description' => 'Ask AI, paste the answer, and schedule with less friction.',
            'footer.disclaimer' => 'Disclaimer',
            'disclaimer.title' => 'Disclaimer',
            'disclaimer.body_1' => 'aite is a tool that helps with scheduling. You are responsible for checking response data, information pasted from AI, and the handling of shared links.',
            'disclaimer.body_2' => 'aite does not guarantee schedule accuracy, availability, third-party services or AI output, or protection from data loss. Confirm important plans directly with all participants.',
            'disclaimer.close' => 'Close',
            'admin.mode' => 'Admin mode',
            'home.hero' => 'Ask AI, then paste.',
            'home.lead' => 'Make scheduling lighter.',
            'home.create' => 'Create an event',
            'home.recent_events' => 'Recent events',
            'home.history_response' => 'Response link',
            'home.history_admin' => 'Creator link',
            'create.title' => 'Create event - aite',
            'create.heading' => 'Create event',
            'create.event_name' => 'Event name',
            'create.event_name_placeholder' => 'Example: Planning meeting',
            'create.description' => 'Description',
            'create.add_description' => 'Add a description',
            'create.description_placeholder' => 'Notes for participants',
            'create.add_min_duration' => 'Set minimum required time',
            'create.min_duration' => 'Minimum required time (minutes)',
            'create.min_duration_hint' => 'Respondents cannot enter available ranges shorter than this.',
            'create.date_only_mode' => 'Check dates only',
            'create.date_only_hint' => 'When enabled, choose candidate dates without times. Respondents only select dates when they are available.',
            'create.slots' => 'Candidate times',
            'create.slots_locked' => 'Enter an event name first.',
            'create.manual_mode' => 'Manual entry',
            'create.prev_month' => 'Previous month',
            'create.next_month' => 'Next month',
            'create.close' => 'Close',
            'create.timeline_hint' => 'Drag horizontally in 10-minute steps on the 06-05 timeline. Click an existing block to delete it.',
            'create.date_only_timeline_hint' => 'Click dates to add them as candidates. Click an existing date again to remove it.',
            'create.manual_slots' => 'Enter candidate times directly',
            'create.manual_dates' => 'Enter candidate dates directly',
            'create.apply' => 'Apply',
            'create.selected' => 'Selected',
            'create.submit' => 'Save and create URLs',
            'event.not_found' => 'Event not found.',
            'event.saved' => 'Your response has been saved.',
            'event.already_answered' => 'You have already responded.',
            'event.already_answered_hint' => 'You can review the summary. If you need to edit your response, enter your name and edit password, then load your previous response.',
            'event.ai_heading' => 'Fill with AI',
            'event.open_ai' => 'Fill with AI',
            'event.close_ai' => 'Close',
            'event.copy_prompt' => 'Copy prompt for AI',
            'event.ai_hint' => 'If your calendar is connected to an AI assistant such as ChatGPT or Gemini, copy the prompt and ask it to check your schedule. Paste the JSON returned by the AI below to fill your availability automatically.',
            'event.ai_answer' => 'Paste AI response here',
            'event.name' => 'Name',
            'event.name_placeholder' => 'Jane Doe',
            'event.edit_password' => 'Edit password',
            'event.edit_password_placeholder' => 'Defaults to your name if unchanged',
            'event.load_previous' => 'Load previous response',
            'event.show_range_only' => 'Show candidate range only',
            'event.show_full_day' => 'Back to 06-05 view',
            'event.drag_hint' => 'Drag only within the white range. Click an existing range to delete it.',
            'event.date_only_hint' => 'Select the dates when you are available.',
            'event.available_date' => 'Available',
            'event.unsupported_slot' => 'This candidate time cannot be answered with drag selection.',
            'event.submit' => 'Save response',
            'event.summary' => 'Summary',
            'event.best_overlap' => 'Best overlap: ',
            'event.overlap_aria' => 'Participant overlap',
            'event.no_ok_ranges' => 'No available ranges yet.',
            'event.no_available_dates' => 'No available-date responses yet.',
            'admin.title' => 'Admin - %s - aite',
            'admin.response_url' => 'Response URL',
            'admin.admin_url' => 'Admin URL',
            'admin.open_response' => 'Open response page',
            'admin.csv_download' => 'Download CSV',
            'admin.responses' => 'Responses',
            'admin.no_responses' => 'No responses yet.',
            'admin.system_title' => 'Admin mode - aite',
            'admin.invalid_token' => 'The admin token is incorrect.',
            'admin.token' => 'Admin token',
            'admin.open_mode' => 'Open admin mode',
            'admin.event_deleted' => 'The event has been deleted.',
            'admin.database_reset' => 'The database has been reset.',
            'admin.event_count' => 'Events: ',
            'admin.auto_delete' => 'Auto delete: 1 month after the last update',
            'admin.reset_confirm' => 'Delete all events and responses. Continue?',
            'admin.reset_database' => 'Reset database',
            'admin.event_list' => 'Created events',
            'admin.no_events' => 'No events have been created.',
            'admin.event' => 'Event',
            'admin.created_at' => 'Created',
            'admin.updated_at' => 'Last updated',
            'admin.slot_count' => 'Slots',
            'admin.response_count' => 'Responses',
            'admin.actions' => 'Actions',
            'admin.event_admin' => 'Event admin',
            'admin.delete_confirm' => 'Delete this event. Continue?',
            'admin.delete' => 'Delete',
            'common.name' => 'Name',
            'common.error' => 'Error: ',
            'common.person_count' => '%d people',
            'common.no_overlap' => 'No overlap',
            'error.wrong_password' => 'The edit password is incorrect.',
            'error.previous_not_found' => 'No previous response was found.',
            'error.invalid_admin_url' => 'The admin URL is invalid.',
            'error.create_required' => 'Enter an event name and at least one candidate time.',
            'error.slot_30_minute_required' => 'Candidate times must use YYYY-MM-DD HH:MM-HH:MM format with start and end times on 10-minute increments.',
            'error.min_duration_required' => 'Available ranges must be at least %d minutes.',
            'error.min_duration_invalid' => 'Enter the minimum required time in 10-minute increments.',
            'error.slot_min_duration_required' => 'Candidate times must be at least the minimum required time.',
            'error.date_required' => 'Candidate dates must use YYYY-MM-DD format.',
            'error.response_required' => 'Enter your name and edit password.',
            'js.weekdays_short' => 'Sun,Mon,Tue,Wed,Thu,Fri,Sat',
            'js.year_suffix' => '',
            'js.month_suffix' => '',
            'js.change_start' => 'Change start time',
            'js.change_end' => 'Change end time',
            'js.no_slots' => 'No candidate times yet.',
            'js.create_slot_required' => 'Enter at least one candidate time.',
            'js.slot_30_minute_required' => 'Use 10-minute increments for times.',
            'js.busy_default_title' => 'Busy',
            'js.ai_busy_title' => 'Events found by AI (not saved)',
            'js.date_busy_title' => 'Events found by AI (for review)',
            'js.no_ok_selected' => 'No available range selected.',
            'js.min_duration_required' => 'Available ranges must be at least %d minutes.',
            'js.slot_min_duration_required' => 'Candidate times must be at least the minimum required time.',
            'js.date_required' => 'Use YYYY-MM-DD format for candidate dates.',
            'js.load_failed' => 'Failed to load.',
            'js.previous_loaded' => 'Loaded the previous response.',
            'js.prompt_busy_title' => 'Event name',
            'js.prompt_intro' => 'Please check my calendar for the following candidate times.',
            'js.prompt_ok_ranges' => 'Put only the available time ranges in ok_ranges.',
            'js.prompt_available_dates' => 'Use status: "o" only for candidate dates when I am available. Use status: "x" for unavailable dates.',
            'js.prompt_date_busy_events' => 'Even if a date is not fully open, when the calendar event title suggests I may still be able to attend, keep status as "x" and include that event in busy_events with title, start, and end so I can review it.',
            'js.prompt_min_duration' => 'Every ok_ranges entry must be at least %d minutes long.',
            'js.prompt_status_o' => 'Use o if I am available.',
            'js.prompt_partial' => 'If I am available for only part of a candidate time, return only that range.',
            'js.prompt_status_maybe' => 'Use maybe if I am tentative.',
            'js.prompt_busy_events' => 'For every unavailable event inside a candidate time, include title, start, and end in busy_events.',
            'js.prompt_status_x' => 'Use x if I am unavailable.',
            'js.prompt_hhmm' => 'start and end must use HH:MM format.',
            'js.prompt_empty' => 'If there is no available time, use an empty ok_ranges array. If there are no busy events, use an empty busy_events array.',
            'js.prompt_json_only' => 'Return JSON only.',
            'js.prompt_slots' => 'Candidate times',
            'js.copied' => 'Copied.',
            'js.copy_url' => 'Copy URL',
            'js.json_array_error' => 'The JSON is not an array.',
            'js.no_applicable_items' => 'No applicable candidate times were found.',
            'js.ai_applied_ranges_busy' => 'Applied %d available ranges and %d busy events.',
            'js.ai_applied_count' => 'Applied %d items.',
            'js.check_json' => 'Check the JSON: %s',
            'js.delete_history' => 'Remove from history',
            'js.history_response' => 'Response link',
            'js.history_admin' => 'Creator link',
        ],
    ];
}

function current_lang(): string
{
    $requested = strtolower((string)($_GET['lang'] ?? $_POST['lang'] ?? ''));
    if (in_array($requested, ['ja', 'en'], true)) {
        return $requested;
    }

    $header = strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    if ($header === '') {
        return 'ja';
    }

    $bestLang = 'en';
    $bestQ = -1.0;
    foreach (explode(',', $header) as $part) {
        $pieces = array_map('trim', explode(';', $part));
        $lang = substr($pieces[0] ?? '', 0, 2);
        if (!in_array($lang, ['ja', 'en'], true)) {
            continue;
        }
        $q = 1.0;
        foreach (array_slice($pieces, 1) as $piece) {
            if (str_starts_with($piece, 'q=')) {
                $q = (float)substr($piece, 2);
            }
        }
        if ($q > $bestQ) {
            $bestLang = $lang;
            $bestQ = $q;
        }
    }
    return $bestLang;
}

function t(string $key, mixed ...$args): string
{
    $all = translations();
    $lang = current_lang();
    $text = $all[$lang][$key] ?? $all['ja'][$key] ?? $key;
    return $args ? sprintf($text, ...$args) : $text;
}

function js_i18n(): void
{
    $lang = current_lang();
    $strings = translations()[$lang];
    $flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    echo '<script>window.AITE_LANG=' . json_encode($lang, $flags) . ';window.AITE_I18N=' . json_encode($strings, $flags) . ';</script>' . "\n";
}

function site_footer(): void
{
    $disclaimer = h(t('footer.disclaimer'));
    $title = h(t('disclaimer.title'));
    $body1 = h(t('disclaimer.body_1'));
    $body2 = h(t('disclaimer.body_2'));
    $close = h(t('disclaimer.close'));

    echo <<<HTML
<footer class="site-footer">
    <span>aite</span>
    <a href="https://github.com/TetsuakiBaba/aite" target="_blank" rel="noopener">GitHub</a>
    <a href="#disclaimer" data-disclaimer-open>{$disclaimer}</a>
</footer>
<div class="modal-backdrop disclaimer-backdrop" id="disclaimerModal" hidden>
    <section class="disclaimer-modal" role="dialog" aria-modal="true" aria-labelledby="disclaimerTitle">
        <div class="section-head">
            <h2 id="disclaimerTitle">{$title}</h2>
            <button type="button" class="icon-button disclaimer-close" data-disclaimer-close aria-label="{$close}" title="{$close}"><span class="icon icon-xmark" aria-hidden="true"></span></button>
        </div>
        <p>{$body1}</p>
        <p>{$body2}</p>
    </section>
</div>
<script>
(() => {
    const modal = document.getElementById('disclaimerModal');
    const open = document.querySelector('[data-disclaimer-open]');
    const close = modal?.querySelector('[data-disclaimer-close]');
    if (!modal || !open || !close) return;
    const hide = () => { modal.hidden = true; };
    open.addEventListener('click', (event) => {
        event.preventDefault();
        modal.hidden = false;
        close.focus();
    });
    close.addEventListener('click', hide);
    modal.addEventListener('click', (event) => {
        if (event.target === modal) hide();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) hide();
    });
})();
</script>
HTML;
}

function asset_url(string $path): string
{
    return base_url() . '/' . ltrim($path, '/');
}

function current_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    return $scheme . '://' . $host . $uri;
}

function page_head(string $title, ?string $description = null): void
{
    $description ??= t('app.description');
    $image = asset_url('assets/aite-ogp.png');
    echo '<title>' . h($title) . '</title>' . "\n";
    echo '    <meta name="description" content="' . h($description) . '">' . "\n";
    echo '    <meta property="og:site_name" content="aite">' . "\n";
    echo '    <meta property="og:title" content="' . h($title) . '">' . "\n";
    echo '    <meta property="og:description" content="' . h($description) . '">' . "\n";
    echo '    <meta property="og:type" content="website">' . "\n";
    echo '    <meta property="og:url" content="' . h(current_url()) . '">' . "\n";
    echo '    <meta property="og:image" content="' . h($image) . '">' . "\n";
    echo '    <meta property="og:image:type" content="image/png">' . "\n";
    echo '    <meta property="og:image:width" content="1536">' . "\n";
    echo '    <meta property="og:image:height" content="1024">' . "\n";
    echo '    <meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '    <meta name="twitter:title" content="' . h($title) . '">' . "\n";
    echo '    <meta name="twitter:description" content="' . h($description) . '">' . "\n";
    echo '    <meta name="twitter:image" content="' . h($image) . '">' . "\n";
    echo '    <link rel="icon" href="assets/aite-icon.png" type="image/png">' . "\n";
    echo '    <link rel="stylesheet" href="style.css">';
}

function new_id(int $bytes = 8): string
{
    return bin2hex(random_bytes($bytes));
}

function base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
    return $scheme . '://' . $host . ($path === '' ? '' : $path);
}

function event_url(string $id): string
{
    return base_url() . '/event.php?id=' . rawurlencode($id);
}

function admin_url(string $id, string $token): string
{
    return base_url() . '/admin.php?id=' . rawurlencode($id) . '&token=' . rawurlencode($token);
}

function system_admin_url(string $token): string
{
    return base_url() . '/admin.php?admin_token=' . rawurlencode($token);
}

function redirect_to(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function get_event(string $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM events WHERE id = ?');
    $stmt->execute([$id]);
    $event = $stmt->fetch();
    return $event ?: null;
}

function list_events(): array
{
    $stmt = db()->query('
        SELECT
            e.*,
            COUNT(DISTINCT s.id) AS slot_count,
            COUNT(DISTINCT r.id) AS response_count
        FROM events e
        LEFT JOIN slots s ON s.event_id = e.id
        LEFT JOIN responses r ON r.event_id = e.id
        GROUP BY e.id
        ORDER BY e.updated_at DESC, e.created_at DESC
    ');
    return $stmt->fetchAll();
}

function delete_event(string $eventId): bool
{
    $stmt = db()->prepare('DELETE FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    return $stmt->rowCount() > 0;
}

function reset_database(): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM answer_ranges');
        $pdo->exec('DELETE FROM answers');
        $pdo->exec('DELETE FROM responses');
        $pdo->exec('DELETE FROM slots');
        $pdo->exec('DELETE FROM events');
        $pdo->exec("DELETE FROM sqlite_sequence WHERE name IN ('responses', 'answer_ranges')");
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function cleanup_expired_events(): int
{
    $cutoff = (new DateTimeImmutable('now'))->modify('-1 month')->format(DateTimeInterface::ATOM);
    $stmt = db()->prepare('DELETE FROM events WHERE updated_at < ?');
    $stmt->execute([$cutoff]);
    return $stmt->rowCount();
}

function touch_event(string $eventId): void
{
    $stmt = db()->prepare('UPDATE events SET updated_at = ? WHERE id = ?');
    $stmt->execute([date('c'), $eventId]);
}

function get_slots(string $eventId): array
{
    $stmt = db()->prepare('SELECT * FROM slots WHERE event_id = ? ORDER BY sort_order, slot_text');
    $stmt->execute([$eventId]);
    return $stmt->fetchAll();
}

function parse_slot_lines(string $text): array
{
    $seen = [];
    $slots = [];
    foreach (preg_split('/\R/u', $text) as $line) {
        $line = preg_replace('/\s+/u', ' ', trim($line));
        if ($line === '' || isset($seen[$line])) {
            continue;
        }
        $seen[$line] = true;
        $slots[] = $line;
    }
    return $slots;
}

function normalize_status(string $value): ?string
{
    $v = strtolower(trim($value));
    $v = str_replace(['　', '。'], [' ', ''], $v);
    return match ($v) {
        'o', 'ok', 'yes', 'available', '○', '◯', '〇' => 'o',
        'maybe', 'tentative', '△', '未定' => 'maybe',
        'x', 'no', 'unavailable', '×', '✕', '✖' => 'x',
        default => null,
    };
}

function parse_slot_text(string $text): ?array
{
    if (!preg_match('/^(\d{4}-\d{2}-\d{2})\s+(\d{2}):(\d{2})-(\d{2}):(\d{2})$/', trim($text), $m)) {
        return null;
    }
    $startMinutes = (int)$m[2] * 60 + (int)$m[3];
    $endMinutes = (int)$m[4] * 60 + (int)$m[5];
    if ($startMinutes % 10 !== 0 || $endMinutes % 10 !== 0) {
        return null;
    }
    $start = intdiv($startMinutes, 10);
    $end = intdiv($endMinutes, 10);
    if ($start < 0 || $end > 144 || $end <= $start) {
        return null;
    }
    return ['date' => $m[1], 'start' => $start, 'end' => $end];
}

function parse_date_slot_text(string $text): ?array
{
    $date = trim($text);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$dt || $dt->format('Y-m-d') !== $date) {
        return null;
    }
    return ['date' => $date];
}

function time_from_index(int $index): string
{
    $minutes = $index * 10;
    return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
}

function duration_units_to_minutes(int $units): int
{
    return $units * 10;
}

function date_label(string $date): string
{
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$dt) {
        return $date;
    }
    if (current_lang() === 'en') {
        return $dt->format('Y/m/d') . ' (' . $dt->format('D') . ')';
    }
    $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
    return $dt->format('Y/m/d') . '（' . $weekdays[(int)$dt->format('w')] . '）';
}

function slot_label(string $slotText): string
{
    $parsed = parse_slot_text($slotText);
    if ($parsed) {
        return date_label($parsed['date']) . ' ' . time_from_index($parsed['start']) . '-' . time_from_index($parsed['end']);
    }
    $dateOnly = parse_date_slot_text($slotText);
    if ($dateOnly) {
        return date_label($dateOnly['date']);
    }
    return $slotText;
}

function create_event(string $title, string $description, array $slotTexts, int $minDurationUnits = 0, bool $dateOnly = false): array
{
    $pdo = db();
    $id = new_id(5);
    $token = new_id(16);
    $now = date('c');

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO events (id, title, description, admin_token, min_duration_units, date_only, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$id, $title, $description, $token, $dateOnly ? 0 : max(0, $minDurationUnits), $dateOnly ? 1 : 0, $now, $now]);

        $slotStmt = $pdo->prepare('INSERT INTO slots (id, event_id, slot_text, sort_order) VALUES (?, ?, ?, ?)');
        foreach ($slotTexts as $i => $slotText) {
            $slotStmt->execute(['slot_' . new_id(6), $id, $slotText, $i]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return ['id' => $id, 'admin_token' => $token];
}

function save_response(string $eventId, string $name, string $password, array $answers = [], array $ranges = []): void
{
    $pdo = db();
    $event = get_event($eventId);
    $minDurationUnits = max(0, (int)($event['min_duration_units'] ?? 0));
    $slots = get_slots($eventId);
    $slotMap = [];
    foreach ($slots as $slot) {
        $parsed = parse_slot_text($slot['slot_text']);
        $slotMap[$slot['id']] = ['slot' => $slot, 'parsed' => $parsed];
    }
    $validSlotIds = array_flip(array_keys($slotMap));
    $now = date('c');

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id, password_hash FROM responses WHERE event_id = ? AND name = ?');
        $stmt->execute([$eventId, $name]);
        $existing = $stmt->fetch();

        if ($existing) {
            if ($existing['password_hash'] !== '' && !password_verify($password, $existing['password_hash'])) {
                throw new RuntimeException(t('error.wrong_password'));
            }
            $responseId = (int)$existing['id'];
            $passwordHash = $existing['password_hash'] !== '' ? $existing['password_hash'] : password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE responses SET password_hash = ?, updated_at = ? WHERE id = ?');
            $stmt->execute([$passwordHash, $now, $responseId]);
            $stmt = $pdo->prepare('DELETE FROM answers WHERE response_id = ?');
            $stmt->execute([$responseId]);
            $stmt = $pdo->prepare('DELETE FROM answer_ranges WHERE response_id = ?');
            $stmt->execute([$responseId]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO responses (event_id, name, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$eventId, $name, password_hash($password, PASSWORD_DEFAULT), $now, $now]);
            $responseId = (int)$pdo->lastInsertId();
        }

        $stmt = $pdo->prepare('INSERT INTO answers (response_id, slot_id, status) VALUES (?, ?, ?)');
        foreach ($answers as $slotId => $status) {
            $normalized = normalize_status((string)$status);
            if (!isset($validSlotIds[$slotId]) || $normalized === null) {
                continue;
            }
            $stmt->execute([$responseId, $slotId, $normalized]);
        }

        $rangeStmt = $pdo->prepare('
            INSERT INTO answer_ranges (response_id, slot_id, start_time, end_time, start_index, end_index)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $okSlotIds = [];
        foreach ($ranges as $range) {
            if (!is_array($range)) {
                continue;
            }
            $slotId = (string)($range['slot_id'] ?? '');
            $start = (int)($range['start'] ?? -1);
            $end = (int)($range['end'] ?? -1);
            $slotInfo = $slotMap[$slotId] ?? null;
            if (!$slotInfo || !$slotInfo['parsed']) {
                continue;
            }
            $duration = $slotInfo['parsed']['end'] - $slotInfo['parsed']['start'];
            if ($start < 0 || $end > $duration || $end <= $start) {
                continue;
            }
            if ($minDurationUnits > 0 && ($end - $start) < $minDurationUnits) {
                throw new RuntimeException(t('error.min_duration_required', duration_units_to_minutes($minDurationUnits)));
            }
            $absoluteStart = $slotInfo['parsed']['start'] + $start;
            $absoluteEnd = $slotInfo['parsed']['start'] + $end;
            $rangeStmt->execute([
                $responseId,
                $slotId,
                time_from_index($absoluteStart),
                time_from_index($absoluteEnd),
                $start,
                $end,
            ]);
            $okSlotIds[$slotId] = true;
        }

        $answerStmt = $pdo->prepare('INSERT OR REPLACE INTO answers (response_id, slot_id, status) VALUES (?, ?, ?)');
        foreach (array_keys($okSlotIds) as $slotId) {
            $answerStmt->execute([$responseId, $slotId, 'o']);
        }

        touch_event($eventId);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function aggregate(string $eventId): array
{
    $slots = get_slots($eventId);
    $slotMap = [];
    foreach ($slots as $slot) {
        $parsed = parse_slot_text($slot['slot_text']);
        $duration = $parsed ? $parsed['end'] - $parsed['start'] : 0;
        $slotMap[$slot['id']] = [
            'slot' => $slot,
            'parsed' => $parsed,
            'o' => 0,
            'maybe' => 0,
            'x' => 0,
            'answers' => [],
            'ranges' => [],
            'counts' => array_fill(0, max(0, $duration), 0),
            'best' => 0,
        ];
    }

    $stmt = db()->prepare('
        SELECT r.name, a.slot_id, a.status
        FROM responses r
        LEFT JOIN answers a ON a.response_id = r.id
        WHERE r.event_id = ?
        ORDER BY r.updated_at, r.name
    ');
    $stmt->execute([$eventId]);
    foreach ($stmt->fetchAll() as $row) {
        if (!$row['slot_id'] || !isset($slotMap[$row['slot_id']])) {
            continue;
        }
        $status = $row['status'];
        $slotMap[$row['slot_id']][$status]++;
        $slotMap[$row['slot_id']]['answers'][] = ['name' => $row['name'], 'status' => $status];
    }

    $stmt = db()->prepare('
        SELECT r.name, ar.slot_id, ar.start_time, ar.end_time, ar.start_index, ar.end_index
        FROM responses r
        JOIN answer_ranges ar ON ar.response_id = r.id
        WHERE r.event_id = ?
        ORDER BY r.updated_at, r.name, ar.start_index
    ');
    $stmt->execute([$eventId]);
    foreach ($stmt->fetchAll() as $row) {
        if (!isset($slotMap[$row['slot_id']])) {
            continue;
        }
        $slotMap[$row['slot_id']]['ranges'][] = [
            'name' => $row['name'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
        ];
        for ($i = (int)$row['start_index']; $i < (int)$row['end_index']; $i++) {
            if (isset($slotMap[$row['slot_id']]['counts'][$i])) {
                $slotMap[$row['slot_id']]['counts'][$i]++;
                $slotMap[$row['slot_id']]['best'] = max($slotMap[$row['slot_id']]['best'], $slotMap[$row['slot_id']]['counts'][$i]);
            }
        }
    }

    $items = array_values($slotMap);
    usort($items, function ($a, $b) {
        if ($a['best'] !== $b['best']) {
            return $b['best'] <=> $a['best'];
        }
        if ($a['o'] !== $b['o']) {
            return $b['o'] <=> $a['o'];
        }
        if ($a['maybe'] !== $b['maybe']) {
            return $b['maybe'] <=> $a['maybe'];
        }
        if ($a['x'] !== $b['x']) {
            return $a['x'] <=> $b['x'];
        }
        return $a['slot']['sort_order'] <=> $b['slot']['sort_order'];
    });
    return $items;
}

function responses_with_answers(string $eventId): array
{
    $stmt = db()->prepare('SELECT * FROM responses WHERE event_id = ? ORDER BY updated_at DESC, name');
    $stmt->execute([$eventId]);
    $responses = $stmt->fetchAll();

    $stmt = db()->prepare('
        SELECT r.id AS response_id, a.slot_id, a.status
        FROM responses r
        JOIN answers a ON a.response_id = r.id
        WHERE r.event_id = ?
    ');
    $stmt->execute([$eventId]);
    $answerMap = [];
    foreach ($stmt->fetchAll() as $answer) {
        $answerMap[(int)$answer['response_id']][$answer['slot_id']] = $answer['status'];
    }

    $stmt = db()->prepare('
        SELECT r.id AS response_id, ar.slot_id, ar.start_time, ar.end_time, ar.start_index, ar.end_index
        FROM responses r
        JOIN answer_ranges ar ON ar.response_id = r.id
        WHERE r.event_id = ?
        ORDER BY ar.start_index
    ');
    $stmt->execute([$eventId]);
    $rangeMap = [];
    foreach ($stmt->fetchAll() as $range) {
        $rangeMap[(int)$range['response_id']][$range['slot_id']][] = $range;
    }

    foreach ($responses as &$response) {
        $response['answers'] = $answerMap[(int)$response['id']] ?? [];
        $response['ranges'] = $rangeMap[(int)$response['id']] ?? [];
    }
    return $responses;
}

function response_ranges_for_edit(string $eventId, string $name, string $password): array
{
    $stmt = db()->prepare('SELECT id, password_hash FROM responses WHERE event_id = ? AND name = ?');
    $stmt->execute([$eventId, $name]);
    $response = $stmt->fetch();
    if (!$response) {
        throw new RuntimeException(t('error.previous_not_found'));
    }
    if ($response['password_hash'] !== '' && !password_verify($password, $response['password_hash'])) {
        throw new RuntimeException(t('error.wrong_password'));
    }

    $stmt = db()->prepare('
        SELECT slot_id, start_index, end_index
        FROM answer_ranges
        WHERE response_id = ?
        ORDER BY slot_id, start_index
    ');
    $stmt->execute([(int)$response['id']]);
    $ranges = [];
    foreach ($stmt->fetchAll() as $range) {
        $ranges[] = [
            'slot_id' => $range['slot_id'],
            'start' => (int)$range['start_index'],
            'end' => (int)$range['end_index'],
        ];
    }
    return $ranges;
}

function response_answers_for_edit(string $eventId, string $name, string $password): array
{
    $stmt = db()->prepare('SELECT id, password_hash FROM responses WHERE event_id = ? AND name = ?');
    $stmt->execute([$eventId, $name]);
    $response = $stmt->fetch();
    if (!$response) {
        throw new RuntimeException(t('error.previous_not_found'));
    }
    if ($response['password_hash'] !== '' && !password_verify($password, $response['password_hash'])) {
        throw new RuntimeException(t('error.wrong_password'));
    }

    $stmt = db()->prepare('SELECT slot_id, status FROM answers WHERE response_id = ?');
    $stmt->execute([(int)$response['id']]);
    $answers = [];
    foreach ($stmt->fetchAll() as $answer) {
        $answers[] = [
            'slot_id' => $answer['slot_id'],
            'status' => $answer['status'],
        ];
    }
    return $answers;
}

function range_label(array $ranges): string
{
    $labels = [];
    foreach ($ranges as $range) {
        $labels[] = $range['start_time'] . '-' . $range['end_time'];
    }
    return implode(' / ', $labels);
}

function overlap_segments(array $item): array
{
    if (empty($item['parsed'])) {
        return [];
    }

    $frameStart = 36;
    $frameUnits = 144;
    $counts = array_fill(0, $frameUnits, 0);
    $outside = array_fill(0, $frameUnits, true);
    $slotStart = (int)$item['parsed']['start'];
    $slotEnd = (int)$item['parsed']['end'];

    for ($i = $slotStart; $i < $slotEnd; $i++) {
        $visual = ($i - $frameStart + $frameUnits) % $frameUnits;
        $outside[$visual] = false;
        $counts[$visual] = (int)($item['counts'][$i - $slotStart] ?? 0);
    }

    $segments = [];
    $start = 0;
    $currentCount = $counts[0];
    $currentOutside = $outside[0];

    for ($i = 1; $i <= $frameUnits; $i++) {
        $nextCount = $counts[$i] ?? null;
        $nextOutside = $outside[$i] ?? null;
        if ($i < $frameUnits && $nextCount === $currentCount && $nextOutside === $currentOutside) {
            continue;
        }
        $absStart = ($frameStart + $start) % $frameUnits;
        $absEnd = ($frameStart + $i) % $frameUnits;
        if ($i === $frameUnits) {
            $absEnd = $frameStart;
        }
        $segments[] = [
            'start' => $start,
            'end' => $i,
            'count' => $currentCount,
            'outside' => $currentOutside,
            'start_time' => time_from_index($absStart),
            'end_time' => time_from_index($absEnd),
            'width' => (($i - $start) / $frameUnits * 100),
        ];
        $start = $i;
        $currentCount = $nextCount;
        $currentOutside = $nextOutside;
    }

    return $segments;
}

function best_overlap_label(array $item): string
{
    $best = (int)($item['best'] ?? 0);
    if ($best <= 0) {
        return t('common.no_overlap');
    }

    $labels = [];
    foreach (overlap_segments($item) as $segment) {
        if (empty($segment['outside']) && (int)$segment['count'] === $best) {
            $labels[] = $segment['start_time'] . '-' . $segment['end_time'];
        }
    }
    return implode(' / ', $labels) . ' (' . t('common.person_count', $best) . ')';
}

function overlap_ticks(array $item): array
{
    $frameUnits = 24;
    $ticks = [];
    for ($i = 0; $i < $frameUnits; $i++) {
        $hour = (6 + $i) % 24;
        $ticks[] = [
            'label' => $i === 18 ? '24' : sprintf('%02d', $hour),
            'left' => ($i / $frameUnits * 100),
        ];
    }
    return $ticks;
}

function overlap_tick_step(): int
{
    return 2;
}

function overlap_segment_class(array $segment): string
{
    if (!empty($segment['outside'])) {
        return 'outside';
    }
    if ((int)$segment['count'] === 0) {
        return 'empty';
    }
    return '';
}

function overlap_segment_alpha(array $item, array $segment): string
{
    $count = (int)$segment['count'];
    if (!empty($segment['outside']) || $count === 0 || (int)$item['best'] <= 0) {
        return '0.060';
    }
    return sprintf('%.3f', 0.18 + (0.62 * $count / (int)$item['best']));
}

function overlap_tick_visible(array $tick): bool
{
    $label = $tick['label'];
    if ($label === '06' || $label === '24' || $label === '05') {
        return true;
    }
    return ((int)$label % overlap_tick_step()) === 0;
}

function status_label(?string $status): string
{
    return match ($status) {
        'o' => '○',
        'maybe' => '△',
        'x' => '×',
        default => '',
    };
}

function require_admin(string $eventId, string $token): array
{
    $event = get_event($eventId);
    if (!$event || !hash_equals($event['admin_token'], $token)) {
        http_response_code(403);
        exit(t('error.invalid_admin_url'));
    }
    return $event;
}

function system_admin_token(): string
{
    $envToken = trim((string)getenv('AITE_ADMIN_TOKEN'));
    if ($envToken !== '') {
        return $envToken;
    }

    $dir = dirname(ADMIN_TOKEN_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    if (is_file(ADMIN_TOKEN_FILE)) {
        $token = trim((string)file_get_contents(ADMIN_TOKEN_FILE));
        if ($token !== '') {
            return $token;
        }
    }

    $token = new_id(24);
    file_put_contents(ADMIN_TOKEN_FILE, $token . PHP_EOL, LOCK_EX);
    @chmod(ADMIN_TOKEN_FILE, 0600);
    return $token;
}

function is_system_admin(string $token): bool
{
    return $token !== '' && hash_equals(system_admin_token(), $token);
}

function require_system_admin(string $token): void
{
    if (!is_system_admin($token)) {
        http_response_code(403);
        exit(t('admin.invalid_token'));
    }
}

function handle_create(): void
{
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $slotTexts = parse_slot_lines($_POST['slots'] ?? '');
    $dateOnly = !empty($_POST['date_only']);
    $minDurationUnits = 0;
    if (!$dateOnly && !empty($_POST['min_duration_enabled'])) {
        $minutes = (int)($_POST['min_duration_minutes'] ?? 0);
        if ($minutes < 10 || $minutes > 1440 || $minutes % 10 !== 0) {
            redirect_to('create.php?error=' . rawurlencode(t('error.min_duration_invalid')) . '&lang=' . rawurlencode(current_lang()));
        }
        $minDurationUnits = intdiv($minutes, 10);
    }

    if ($title === '' || count($slotTexts) === 0) {
        redirect_to('create.php?error=' . rawurlencode(t('error.create_required')));
    }

    foreach ($slotTexts as $slotText) {
        if ($dateOnly) {
            if (!parse_date_slot_text($slotText)) {
                redirect_to('create.php?error=' . rawurlencode(t('error.date_required')) . '&lang=' . rawurlencode(current_lang()));
            }
            continue;
        }
        $parsed = parse_slot_text($slotText);
        if (!$parsed) {
            redirect_to('create.php?error=' . rawurlencode(t('error.slot_30_minute_required')) . '&lang=' . rawurlencode(current_lang()));
        }
        if ($minDurationUnits > 0 && ((int)$parsed['end'] - (int)$parsed['start']) < $minDurationUnits) {
            redirect_to('create.php?error=' . rawurlencode(t('error.slot_min_duration_required')) . '&lang=' . rawurlencode(current_lang()));
        }
    }

    $event = create_event($title, $description, $slotTexts, $minDurationUnits, $dateOnly);
    redirect_to(admin_url($event['id'], $event['admin_token']) . '&lang=' . rawurlencode(current_lang()));
}

function handle_response(): void
{
    $eventId = $_POST['event_id'] ?? '';
    $event = get_event($eventId);
    $name = trim($_POST['name'] ?? '');
    $password = (string)($_POST['edit_password'] ?? '');

    if (!$event || $name === '' || $password === '') {
        redirect_to('event.php?id=' . rawurlencode($eventId) . '&error=' . rawurlencode(t('error.response_required')) . '&lang=' . rawurlencode(current_lang()));
    }

    $ranges = [];
    $rangeJson = $_POST['availability'] ?? '';
    if (is_string($rangeJson) && $rangeJson !== '') {
        $decoded = json_decode($rangeJson, true);
        if (is_array($decoded)) {
            $ranges = $decoded;
        }
    }

    try {
        save_response($eventId, $name, $password, $_POST['answers'] ?? [], $ranges);
    } catch (RuntimeException $e) {
        redirect_to('event.php?id=' . rawurlencode($eventId) . '&error=' . rawurlencode($e->getMessage()) . '&lang=' . rawurlencode(current_lang()));
    }
    redirect_to('event.php?id=' . rawurlencode($eventId) . '&saved=1&lang=' . rawurlencode(current_lang()));
}

function handle_load_response(): void
{
    header('Content-Type: application/json; charset=UTF-8');
    $eventId = $_POST['event_id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $password = (string)($_POST['edit_password'] ?? '');
    if (!get_event($eventId) || $name === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => t('error.response_required')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        echo json_encode(['ok' => true, 'ranges' => response_ranges_for_edit($eventId, $name, $password), 'answers' => response_answers_for_edit($eventId, $name, $password)], JSON_UNESCAPED_UNICODE);
    } catch (RuntimeException $e) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

function handle_csv(): void
{
    $eventId = $_GET['id'] ?? '';
    $token = $_GET['token'] ?? '';
    $event = require_admin($eventId, $token);
    $slots = get_slots($eventId);
    $responses = responses_with_answers($eventId);
    $dateOnly = !empty($event['date_only']);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="aite_' . $eventId . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array_merge([t('common.name')], array_map(fn($slot) => slot_label($slot['slot_text']), $slots)));
    foreach ($responses as $response) {
        $row = [$response['name']];
        foreach ($slots as $slot) {
            $row[] = $dateOnly
                ? status_label($response['answers'][$slot['id']] ?? null)
                : range_label($response['ranges'][$slot['id']] ?? []);
        }
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'api.php') {
    try {
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        match ($action) {
            'create' => handle_create(),
            'response' => handle_response(),
            'load_response' => handle_load_response(),
            'csv' => handle_csv(),
            default => exit('Unknown action'),
        };
    } catch (Throwable $e) {
        http_response_code(500);
        echo t('common.error') . h($e->getMessage());
    }
}
