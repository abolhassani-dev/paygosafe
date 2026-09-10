<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Tehran');
require_once __DIR__ . '/jalali.php';

$CFG = require __DIR__ . '/config.php';
define('DATA_DIR', __DIR__ . '/data');
define('BUGS_DIR', DATA_DIR . '/bugs');
define('UPLOAD_DIR', DATA_DIR . '/uploads');

const STATUSES = [
    'new'      => 'جدید',
    'review'   => 'در حال بررسی',
    'info'     => 'نیاز به اطلاعات',
    'test'     => 'آماده تست',
    'reopen'   => 'بازگشایی شده',
    'closed'   => 'بسته شد',
    'rejected' => 'رد شد',
];
const OPEN_STATUSES = ['new', 'review', 'info', 'test', 'reopen'];
const PRIORITIES = [
    'low'      => 'کم',
    'medium'   => 'متوسط',
    'high'     => 'بالا',
    'critical' => 'بحرانی',
];
const DEV_STATUSES  = ['review', 'info', 'test', 'closed', 'rejected'];

// ---------------------------------------------------------------- session
function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
        || strpos($_SERVER['HTTP_CF_VISITOR'] ?? '', '"https"') !== false
        || ($_SERVER['SERVER_PORT'] ?? '') == 443;
}

function app_path(): string
{
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $dir === '' ? '/' : $dir . '/';
}

function start_session(): void
{
    global $CFG;
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('bugdesk');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => app_path(),
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
    $idle = (int)($CFG['session_idle_minutes'] ?? 720) * 60;
    if (!empty($_SESSION['role']) && !empty($_SESSION['last']) && (time() - $_SESSION['last']) > $idle) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['last'] = time();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}

function role(): ?string
{
    return $_SESSION['role'] ?? null;
}

function require_login(): void
{
    start_session();
    if (!role()) {
        header('Location: login.php');
        exit;
    }
}

function require_role(string $r): void
{
    require_login();
    if (role() !== $r) {
        http_response_code(403);
        exit('دسترسی ندارید.');
    }
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">';
}

function csrf_check(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('درخواست نامعتبر. صفحه را دوباره باز کنید.');
    }
}

// ---------------------------------------------------------------- login throttle
function client_ip(): string
{
    // پشت Cloudflare آی‌پی واقعی کاربر در این هدر است؛ وگرنه همه‌ی کاربران یک آی‌پی دیده می‌شوند
    $cf = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
    if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) return $cf;
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function throttle_file(): string
{
    return DATA_DIR . '/throttle.json';
}

function throttle_state(): array
{
    $f = throttle_file();
    $all = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
    // پاک کردن رکوردهای قدیمی
    foreach ($all as $ip => $st) {
        if (max($st['until'] ?? 0, $st['last'] ?? 0) < time() - 86400) unset($all[$ip]);
    }
    return $all;
}

function throttle_save(array $all): void
{
    file_put_contents(throttle_file(), json_encode($all), LOCK_EX);
}

/** ثانیه‌های باقی‌مانده از مسدودی، یا 0 */
function throttle_blocked(): int
{
    $st = throttle_state()[client_ip()] ?? null;
    if (!$st) return 0;
    return max(0, ($st['until'] ?? 0) - time());
}

function throttle_fail(): void
{
    global $CFG;
    $all = throttle_state();
    $ip = client_ip();
    $st = $all[$ip] ?? ['count' => 0, 'until' => 0];
    $st['count']++;
    $st['last'] = time();
    if ($st['count'] >= (int)$CFG['login_max_attempts']) {
        $st['until'] = time() + (int)$CFG['login_block_minutes'] * 60;
        $st['count'] = 0;
    }
    $all[$ip] = $st;
    throttle_save($all);
}

function throttle_clear(): void
{
    $all = throttle_state();
    unset($all[client_ip()]);
    throttle_save($all);
}

// ---------------------------------------------------------------- helpers
function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

function flash(?string $msg = null): ?string
{
    if ($msg !== null) {
        $_SESSION['flash'] = $msg;
        return null;
    }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}

function base_url(): string
{
    global $CFG;
    if (!empty($CFG['base_url'])) return rtrim($CFG['base_url'], '/');
    $scheme = is_https() ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(app_path(), '/');
}

function badge(string $status): string
{
    $l = STATUSES[$status] ?? $status;
    return '<span class="badge s-' . h($status) . '">' . h($l) . '</span>';
}

function priority_of(array $bug): string
{
    $p = $bug['priority'] ?? 'medium';
    return isset(PRIORITIES[$p]) ? $p : 'medium';
}

function prio_badge(array $bug): string
{
    $p = priority_of($bug);
    return '<span class="prio p-' . h($p) . '"><i></i>' . h(PRIORITIES[$p]) . '</span>';
}

// ---------------------------------------------------------------- storage (JSON files)
function bug_file(int $id): string
{
    return BUGS_DIR . '/' . $id . '.json';
}

function load_bug(int $id): ?array
{
    $f = bug_file($id);
    if (!is_file($f)) return null;
    $b = json_decode((string)file_get_contents($f), true);
    return is_array($b) ? $b : null;
}

function save_bug(array $bug): void
{
    $f = bug_file((int)$bug['id']);
    $tmp = $f . '.tmp';
    file_put_contents($tmp, json_encode($bug, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    rename($tmp, $f);
}

/** همه‌ی باگ‌ها، جدیدترین اول */
function all_bugs(): array
{
    $out = [];
    foreach (glob(BUGS_DIR . '/*.json') ?: [] as $f) {
        $b = json_decode((string)file_get_contents($f), true);
        if (is_array($b) && isset($b['id'])) $out[(int)$b['id']] = $b;
    }
    krsort($out);
    return array_values($out);
}

/** شماره‌ی بعدی، با قفل فایل که دو ثبت هم‌زمان یک شماره نگیرند */
function next_id(): int
{
    $f = DATA_DIR . '/seq.txt';
    $fp = fopen($f, 'c+');
    if (!$fp) throw new RuntimeException('cannot open seq');
    flock($fp, LOCK_EX);
    $cur = (int)stream_get_contents($fp);
    $next = $cur + 1;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, (string)$next);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $next;
}

function add_event(array &$bug, array $ev): void
{
    $ev['ts'] = time();
    $bug['log'][] = $ev;
    $bug['updated'] = $ev['ts'];
}

// ---------------------------------------------------------------- uploads
/** فایل‌های ارسالی را بررسی و ذخیره می‌کند. خروجی: [نام فایل‌ها، پیام خطا] */
function handle_uploads(int $bugId, array $files): array
{
    global $CFG;
    $saved = [];
    if (empty($files['name']) || !is_array($files['name'])) return [$saved, null];
    $max = (int)$CFG['max_files'];
    $maxBytes = (int)$CFG['max_file_mb'] * 1024 * 1024;
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $n = 0;
    foreach ($files['name'] as $i => $name) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        if ($n >= $max) return [$saved, "حداکثر {$max} تصویر مجاز است."];
        if ($files['error'][$i] !== UPLOAD_ERR_OK) return [$saved, 'خطا در آپلود فایل.'];
        if ($files['size'][$i] > $maxBytes) return [$saved, "حجم هر تصویر حداکثر {$CFG['max_file_mb']} مگابایت."];
        $tmp = $files['tmp_name'][$i];
        if (!is_uploaded_file($tmp)) return [$saved, 'فایل نامعتبر.'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
        if (!isset($allowed[$mime]) || @getimagesize($tmp) === false) return [$saved, 'فقط تصویر (JPG, PNG, WEBP, GIF) مجاز است.'];
        $fname = $bugId . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($tmp, UPLOAD_DIR . '/' . $fname)) return [$saved, 'ذخیره‌ی فایل ممکن نشد.'];
        $saved[] = $fname;
        $n++;
    }
    return [$saved, null];
}

// ---------------------------------------------------------------- telegram
function tg_ready(): bool
{
    global $CFG;
    return !empty($CFG['telegram_token']) && !empty($CFG['telegram_chat_id']);
}

/** درخواست به Bot API. مهلت کوتاه تا صفحه معطل نماند. */
function tg_api(string $method, array $params, int $timeout = 6): ?array
{
    global $CFG;
    if (empty($CFG['telegram_token'])) return null;
    $ch = curl_init('https://api.telegram.org/bot' . $CFG['telegram_token'] . '/' . $method);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 4,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    if ($res === false) return null;
    $j = json_decode($res, true);
    return (is_array($j) && !empty($j['ok'])) ? $j : null;
}

function tg_esc(string $s): string
{
    return htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');
}

/** ارسال متن. خروجی: message_id یا null */
function tg_send(string $html, ?int $replyTo = null): ?int
{
    global $CFG;
    if (!tg_ready()) return null;
    $p = ['chat_id' => $CFG['telegram_chat_id'], 'text' => $html, 'parse_mode' => 'HTML', 'disable_web_page_preview' => 'true'];
    if ($replyTo) $p['reply_to_message_id'] = $replyTo;
    $r = tg_api('sendMessage', $p);
    return $r ? (int)($r['result']['message_id'] ?? 0) ?: null : null;
}

/** ارسال تصاویر به عنوان ریپلای روی پیام باگ */
function tg_send_photos(array $files, ?int $replyTo): bool
{
    global $CFG;
    if (!tg_ready() || !$files) return false;
    $media = [];
    $p = ['chat_id' => $CFG['telegram_chat_id']];
    foreach (array_values($files) as $i => $f) {
        $path = UPLOAD_DIR . '/' . $f;
        if (!is_file($path)) continue;
        $key = 'photo' . $i;
        $media[] = ['type' => 'photo', 'media' => 'attach://' . $key];
        $p[$key] = new CURLFile($path);
    }
    if (!$media) return false;
    $p['media'] = json_encode($media);
    if ($replyTo) $p['reply_to_message_id'] = $replyTo;
    return tg_api('sendMediaGroup', $p, 25) !== null;
}

function bug_link(array $bug): string
{
    return base_url() . '/bug.php?id=' . (int)$bug['id'];
}

function who_label(array $bug, string $role, ?string $name): string
{
    return $role === 'dev' ? 'دولوپر' : (($name ?: 'ساپورت') . ' (ساپورت)');
}

/** اعلان باگ جدید: متن کامل + عکس‌ها. message_id را داخل باگ ذخیره می‌کند. */
function notify_new(array &$bug): void
{
    $pIco = ['low' => '⚪', 'medium' => '🔵', 'high' => '🟠', 'critical' => '🔴'][priority_of($bug)];
    $text = '🐞 <b>باگ #' . fa_digits($bug['id']) . ' · ' . tg_esc($bug['title']) . "</b>\n"
        . $pIco . ' اولویت: ' . PRIORITIES[priority_of($bug)] . "\n\n"
        . tg_esc($bug['desc']) . "\n\n"
        . '👤 ' . tg_esc($bug['reporter']) . " (ساپورت)\n"
        . bug_link($bug);
    $mid = tg_send($text);
    $bug['tg_message_id'] = $mid;
    $bug['tg_sent'] = $mid !== null;
    if ($mid && !empty($bug['files'])) {
        tg_send_photos($bug['files'], $mid);
    }
}

/** اعلان تغییر وضعیت یا یادداشت، به صورت ریپلای روی پیام باگ */
function notify_event(array $bug, array $ev): bool
{
    global $CFG;
    $who = who_label($bug, $ev['role'], $ev['who'] ?? null);
    $reply = $bug['tg_message_id'] ?? null;
    if ($ev['kind'] === 'status') {
        if (!in_array($ev['to'], $CFG['notify_statuses'], true)) return true; // عمداً ارسال نمی‌شود
        $ico = ['info' => '❓', 'test' => '🧪', 'reopen' => '↩️', 'review' => '👀', 'closed' => '✅', 'rejected' => '⛔'][$ev['to']] ?? '•';
        $text = $ico . ' <b>#' . fa_digits($bug['id']) . ' → ' . STATUSES[$ev['to']] . '</b>'
            . (!empty($ev['note']) ? "\n" . tg_esc($ev['note']) : '')
            . "\n\n👤 " . tg_esc($who) . "\n" . bug_link($bug);
    } else {
        if (empty($CFG['notify_notes'])) return true;
        $text = '📝 <b>یادداشت روی #' . fa_digits($bug['id']) . '</b>'
            . "\n" . tg_esc($ev['note'] ?? '')
            . "\n\n👤 " . tg_esc($who) . "\n" . bug_link($bug);
    }
    if (!$reply) {
        // پیام اصلی موجود نیست (مثلاً ارسال اولیه ناموفق بوده): موضوع را هم بنویس
        $text = '<b>' . tg_esc($bug['title']) . "</b>\n" . $text;
    }
    return tg_send($text, $reply) !== null;
}

// ---------------------------------------------------------------- layout
function page_header(string $title): void
{
    global $CFG;
    $app = $CFG['app_name'];
    $r = role();
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . ' · ' . h($app) . '</title>';
    echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700&display=swap">';
    echo '<link rel="stylesheet" href="assets/app.css?v=1"></head><body><div class="wrap">';
    if ($r) {
        echo '<div class="topbar"><a class="brand" href="index.php"><span class="mark">PG</span><span>' . h($app) . '</span></a><div class="grow"></div>';
        echo '<div class="who"><span class="dot"></span>' . ($r === 'dev' ? 'تیم دولوپر' : 'تیم ساپورت') . '</div>';
        if ($r === 'support') echo '<a class="btn primary" href="new.php">＋ باگ جدید</a>';
        echo '<a class="btn" href="index.php">لیست</a>';
        echo '<form method="post" action="logout.php" class="inline">' . csrf_field() . '<button class="btn">خروج</button></form>';
        echo '</div>';
    }
    if ($m = flash()) echo '<div class="flash">' . h($m) . '</div>';
}

function page_footer(): void
{
    echo '</div></body></html>';
}
