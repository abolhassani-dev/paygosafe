<?php
declare(strict_types=1);
// ساخت هش رمز عبور برای config.php
// این صفحه چیزی ذخیره نمی‌کند و اطلاعاتی نشان نمی‌دهد؛ فقط رمزی را که خودتان
// وارد می‌کنید هش می‌کند. با این حال بعد از راه‌اندازی می‌توانید حذفش کنید.
require __DIR__ . '/lib.php';
start_session();
$hash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $p = (string)($_POST['password'] ?? '');
    if (mb_strlen($p) < 8) $err = 'رمز حداقل ۸ کاراکتر باشد.';
    else $hash = password_hash($p, PASSWORD_BCRYPT);
}
page_header('ساخت هش رمز');
?>
<div class="panel setup">
  <h1>ساخت هش رمز عبور</h1>
  <p>رمز دلخواه را بنویسید، خروجی را کپی کنید و در <code>config.php</code> جلوی <code>password_hash</code> تیم مربوطه بگذارید. برای تغییر رمز هم همین کار را تکرار کنید.</p>
  <?php if (!empty($err)): ?><div class="error"><?= h($err) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <div class="field"><label>رمز عبور</label><input name="password" type="text" required minlength="8"></div>
    <button class="btn primary">ساخت هش</button>
  </form>
  <?php if ($hash): ?>
    <p style="margin-top:16px">هش:</p>
    <pre><?= h($hash) ?></pre>
  <?php endif; ?>
</div>
<?php page_footer();
