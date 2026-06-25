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

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function site_footer(): void
{
    echo '<footer class="site-footer"><span>aite</span><a href="https://github.com/TetsuakiBaba/aite" target="_blank" rel="noopener">GitHub</a></footer>';
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

function page_head(string $title, string $description = 'AIに聞いて、貼るだけ。予定調整をもっと軽く。'): void
{
    $image = asset_url('assets/aite-ogp.svg');
    echo '<title>' . h($title) . '</title>' . "\n";
    echo '    <meta name="description" content="' . h($description) . '">' . "\n";
    echo '    <meta property="og:site_name" content="aite">' . "\n";
    echo '    <meta property="og:title" content="' . h($title) . '">' . "\n";
    echo '    <meta property="og:description" content="' . h($description) . '">' . "\n";
    echo '    <meta property="og:type" content="website">' . "\n";
    echo '    <meta property="og:url" content="' . h(current_url()) . '">' . "\n";
    echo '    <meta property="og:image" content="' . h($image) . '">' . "\n";
    echo '    <meta property="og:image:type" content="image/svg+xml">' . "\n";
    echo '    <meta property="og:image:width" content="1200">' . "\n";
    echo '    <meta property="og:image:height" content="630">' . "\n";
    echo '    <meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '    <meta name="twitter:title" content="' . h($title) . '">' . "\n";
    echo '    <meta name="twitter:description" content="' . h($description) . '">' . "\n";
    echo '    <meta name="twitter:image" content="' . h($image) . '">' . "\n";
    echo '    <link rel="icon" href="assets/aite-icon.svg" type="image/svg+xml">' . "\n";
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
    if ($startMinutes % 30 !== 0 || $endMinutes % 30 !== 0) {
        return null;
    }
    $start = intdiv($startMinutes, 30);
    $end = intdiv($endMinutes, 30);
    if ($start < 0 || $end > 48 || $end <= $start) {
        return null;
    }
    return ['date' => $m[1], 'start' => $start, 'end' => $end];
}

function time_from_index(int $index): string
{
    $minutes = $index * 30;
    return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
}

function date_label(string $date): string
{
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$dt) {
        return $date;
    }
    $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
    return $dt->format('Y/m/d') . '（' . $weekdays[(int)$dt->format('w')] . '）';
}

function slot_label(string $slotText): string
{
    $parsed = parse_slot_text($slotText);
    if (!$parsed) {
        return $slotText;
    }
    return date_label($parsed['date']) . ' ' . time_from_index($parsed['start']) . '-' . time_from_index($parsed['end']);
}

function create_event(string $title, string $description, array $slotTexts): array
{
    $pdo = db();
    $id = new_id(5);
    $token = new_id(16);
    $now = date('c');

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO events (id, title, description, admin_token, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$id, $title, $description, $token, $now, $now]);

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
                throw new RuntimeException('編集用パスワードが違います。');
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
        throw new RuntimeException('前回回答が見つかりません。');
    }
    if ($response['password_hash'] !== '' && !password_verify($password, $response['password_hash'])) {
        throw new RuntimeException('編集用パスワードが違います。');
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

    $frameStart = 12;
    $frameUnits = 48;
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
        return '重なりなし';
    }

    $labels = [];
    foreach (overlap_segments($item) as $segment) {
        if (empty($segment['outside']) && (int)$segment['count'] === $best) {
            $labels[] = $segment['start_time'] . '-' . $segment['end_time'];
        }
    }
    return implode(' / ', $labels) . ' (' . $best . '人)';
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
        exit('管理URLが正しくありません。');
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
        exit('管理者トークンが正しくありません。');
    }
}

function handle_create(): void
{
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $slotTexts = parse_slot_lines($_POST['slots'] ?? '');

    if ($title === '' || count($slotTexts) === 0) {
        redirect_to('create.php?error=' . rawurlencode('イベント名と候補日時を入力してください。'));
    }

    $event = create_event($title, $description, $slotTexts);
    redirect_to(admin_url($event['id'], $event['admin_token']));
}

function handle_response(): void
{
    $eventId = $_POST['event_id'] ?? '';
    $event = get_event($eventId);
    $name = trim($_POST['name'] ?? '');
    $password = (string)($_POST['edit_password'] ?? '');

    if (!$event || $name === '' || $password === '') {
        redirect_to('event.php?id=' . rawurlencode($eventId) . '&error=' . rawurlencode('名前と編集用パスワードを入力してください。'));
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
        redirect_to('event.php?id=' . rawurlencode($eventId) . '&error=' . rawurlencode($e->getMessage()));
    }
    redirect_to('event.php?id=' . rawurlencode($eventId) . '&saved=1');
}

function handle_load_response(): void
{
    header('Content-Type: application/json; charset=UTF-8');
    $eventId = $_POST['event_id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $password = (string)($_POST['edit_password'] ?? '');
    if (!get_event($eventId) || $name === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => '名前と編集用パスワードを入力してください。'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        echo json_encode(['ok' => true, 'ranges' => response_ranges_for_edit($eventId, $name, $password)], JSON_UNESCAPED_UNICODE);
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

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="aite_' . $eventId . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array_merge(['名前'], array_map(fn($slot) => slot_label($slot['slot_text']), $slots)));
    foreach ($responses as $response) {
        $row = [$response['name']];
        foreach ($slots as $slot) {
            $row[] = range_label($response['ranges'][$slot['id']] ?? []);
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
        echo 'エラー: ' . h($e->getMessage());
    }
}
