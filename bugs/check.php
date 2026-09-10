<?php
// صفحه‌ی عیب‌یابی نصب. عمداً به هیچ فایل دیگری وابسته نیست تا حتی اگر بقیه‌ی
// فایل‌ها روی این سرور اجرا نشوند، این صفحه بالا بیاید. بعد از راه‌اندازی حذفش کنید.
header('Content-Type: text/html; charset=utf-8');
$ok = function ($b) { return $b ? '<b style="color:#15803D">✓</b>' : '<b style="color:#B91C1C">✗</b>'; };
$dir = __DIR__;
$rows = [
    ['نسخه PHP (حداقل ۷.۴)', version_compare(PHP_VERSION, '7.4.0', '>='), PHP_VERSION],
    ['افزونه curl (برای تلگرام)', extension_loaded('curl'), ''],
    ['افزونه fileinfo (برای آپلود)', extension_loaded('fileinfo'), ''],
    ['افزونه mbstring', extension_loaded('mbstring'), ''],
    ['افزونه json', function_exists('json_encode'), ''],
    ['پوشه data/ قابل نوشتن', is_writable($dir . '/data'), $dir . '/data'],
    ['پوشه data/bugs/ قابل نوشتن', is_writable($dir . '/data/bugs'), ''],
    ['پوشه data/uploads/ قابل نوشتن', is_writable($dir . '/data/uploads'), ''],
    ['فایل config.php موجود', is_file($dir . '/config.php'), ''],
    ['فایل lib.php موجود', is_file($dir . '/lib.php'), ''],
    ['HTTPS فعال', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https', ''],
];
$cfgOk = null; $cfgErr = '';
if (is_file($dir . '/config.php')) {
    try { $c = include $dir . '/config.php'; $cfgOk = is_array($c);
        if ($cfgOk) {
            $rows[] = ['توکن تلگرام تنظیم شده', !empty($c['telegram_token']), ''];
            $rows[] = ['آی‌دی چنل تنظیم شده', !empty($c['telegram_chat_id']), ''];
            $rows[] = ['هش رمز ساپورت تنظیم شده', !empty($c['users']['support']['password_hash']), ''];
            $rows[] = ['هش رمز دولوپر تنظیم شده', !empty($c['users']['dev']['password_hash']), ''];
        }
    } catch (Throwable $e) { $cfgOk = false; $cfgErr = $e->getMessage(); }
    $rows[] = ['config.php بدون خطای نحوی', (bool)$cfgOk, $cfgErr];
}
$libOk = null; $libErr = '';
if (is_file($dir . '/lib.php')) {
    try { require_once $dir . '/lib.php'; $libOk = function_exists('start_session'); } catch (Throwable $e) { $libOk = false; $libErr = $e->getMessage(); }
    $rows[] = ['lib.php روی این PHP اجرا می‌شود', (bool)$libOk, $libErr];
}
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>بررسی نصب</title>
<style>body{font-family:Tahoma,sans-serif;max-width:720px;margin:40px auto;padding:0 16px;line-height:1.8}table{border-collapse:collapse;width:100%}td{padding:8px 10px;border-bottom:1px solid #ddd}td:first-child{width:30px}code{direction:ltr;display:inline-block;background:#f3f3f3;padding:1px 6px}</style></head><body>
<h2>بررسی نصب میز باگ</h2>
<table>
<?php foreach ($rows as [$label, $pass, $detail]): ?>
<tr><td><?= $ok($pass) ?></td><td><?= htmlspecialchars($label) ?></td><td><code><?= htmlspecialchars((string)$detail) ?></code></td></tr>
<?php endforeach; ?>
</table>
<p>مسیر این پوشه روی سرور: <code><?= htmlspecialchars($dir) ?></code></p>
<p>آدرس فعلی: <code><?= htmlspecialchars(($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '')) ?></code></p>
<p>اگر همه‌ی موارد ✓ است، <a href="login.php">login.php</a> را باز کنید. این فایل را بعد از راه‌اندازی حذف کنید.</p>
</body></html>
