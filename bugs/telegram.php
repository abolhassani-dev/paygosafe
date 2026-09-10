<?php
declare(strict_types=1);
// بررسی اتصال تلگرام و پیدا کردن آی‌دی گروه (فقط دولوپر)
require __DIR__ . '/lib.php';
require_role('dev');

$msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['do'] ?? '') === 'test') {
        $mid = tg_send('✅ اتصال میز باگ به تلگرام برقرار است.');
        $msg = $mid ? 'پیام آزمایشی ارسال شد.' : 'ارسال ناموفق. توکن و آی‌دی چت را بررسی کنید.';
    }
}
$me = tg_api('getMe', []);
$updates = tg_api('getUpdates', ['limit' => 50, 'allowed_updates' => json_encode(['message', 'channel_post', 'my_chat_member'])]);
$chats = [];
foreach ($updates['result'] ?? [] as $u) {
    $c = $u['message']['chat'] ?? $u['channel_post']['chat'] ?? $u['my_chat_member']['chat'] ?? null;
    if ($c && isset($c['id'])) $chats[$c['id']] = $c;
}
page_header('تنظیمات تلگرام');
?>
<div class="panel setup">
  <h1>اتصال تلگرام</h1>
  <?php if ($msg): ?><div class="flash"><?= h($msg) ?></div><?php endif; ?>
  <p><b>توکن ربات:</b> <?= empty($CFG['telegram_token']) ? '<span style="color:var(--s-rejected)">تنظیم نشده</span>' : ($me ? 'معتبر · @' . h($me['result']['username'] ?? '') : '<span style="color:var(--s-rejected)">نامعتبر یا دسترسی به تلگرام ممکن نیست</span>') ?></p>
  <p><b>آی‌دی چت:</b> <?= empty($CFG['telegram_chat_id']) ? '<span style="color:var(--s-rejected)">تنظیم نشده</span>' : '<code>' . h((string)$CFG['telegram_chat_id']) . '</code>' ?></p>

  <?php if (tg_ready()): ?>
  <form method="post" style="margin:12px 0"><?= csrf_field() ?><input type="hidden" name="do" value="test"><button class="btn primary">ارسال پیام آزمایشی</button></form>
  <?php endif; ?>

  <h2 style="font-size:15px;margin-top:24px">پیدا کردن آی‌دی چنل</h2>
  <ol>
    <li>ربات را در چنل «ادمین» کنید (با دسترسی ارسال پیام).</li>
    <li>یک پیام دلخواه در چنل بفرستید.</li>
    <li>این صفحه را رفرش کنید. چنل در جدول زیر می‌آید؛ آی‌دی آن را در <code>config.php</code> بگذارید.</li>
  </ol>
  <?php if ($chats): ?>
  <table style="min-width:0"><thead><tr><th>نام</th><th>نوع</th><th>آی‌دی</th></tr></thead><tbody>
    <?php foreach ($chats as $c): ?><tr><td><?= h($c['title'] ?? ($c['username'] ?? '')) ?></td><td><?= h($c['type'] ?? '') ?></td><td><code><?= h((string)$c['id']) ?></code></td></tr><?php endforeach; ?>
  </tbody></table>
  <?php else: ?>
  <p class="hint">هنوز چتی دیده نشده.</p>
  <?php endif; ?>
</div>
<?php page_footer();
