<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
start_session();
if (role()) redirect('index.php');

$err = null;
$blocked = throttle_blocked();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if ($blocked > 0) {
        $err = 'به دلیل تلاش‌های ناموفق، ورود برای ' . fa_digits((int)ceil($blocked / 60)) . ' دقیقه مسدود است.';
    } else {
        $u = trim((string)($_POST['username'] ?? ''));
        $p = (string)($_POST['password'] ?? '');
        $found = null;
        foreach ($CFG['users'] as $r => $acc) {
            if (hash_equals($acc['username'], $u) && $acc['password_hash'] !== '' && password_verify($p, $acc['password_hash'])) {
                $found = $r;
                break;
            }
        }
        if ($found) {
            throttle_clear();
            session_regenerate_id(true);
            $_SESSION['role'] = $found;
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            $_SESSION['last'] = time();
            $next = (string)($_POST['next'] ?? '');
            // فقط مسیرهای داخلی
            if ($next === '' || !preg_match('~^[a-z]+\.php(\?[A-Za-z0-9=&_]*)?$~', $next)) $next = 'index.php';
            redirect($next);
        }
        throttle_fail();
        usleep(400000); // کند کردن حدس زدن
        $err = 'نام کاربری یا رمز عبور اشتباه است.';
    }
}
$next = (string)($_GET['next'] ?? '');
page_header('ورود');
?>
<div class="panel login">
  <div class="brand" style="margin-bottom:18px"><span class="mark">PG</span><span><?= h($CFG['app_name']) ?></span></div>
  <h1>ورود</h1>
  <?php if ($err): ?><div class="error"><?= h($err) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= h($next) ?>">
    <div class="field"><label>نام کاربری</label><input name="username" required autofocus></div>
    <div class="field"><label>رمز عبور</label><input name="password" type="password" required></div>
    <button class="btn primary block">ورود</button>
  </form>
</div>
<?php page_footer();
