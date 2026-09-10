<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$bug = $id > 0 ? load_bug($id) : null;
if (!$bug) {
    http_response_code(404);
    page_header('پیدا نشد');
    echo '<div class="panel empty">باگ #' . fa_digits($id) . ' وجود ندارد. <a href="index.php">بازگشت به لیست</a></div>';
    page_footer();
    exit;
}
$r = role();
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $note = trim(str_replace("\r", '', (string)($_POST['note'] ?? '')));
    $action = (string)($_POST['action'] ?? '');
    if (mb_strlen($note) > 3500) {
        $err = 'یادداشت طولانی‌تر از حد مجاز است.';
    } elseif ($r === 'dev') {
        $to = (string)($_POST['status'] ?? '');
        if ($to !== '' && (!in_array($to, DEV_STATUSES, true) || $to === $bug['status'])) {
            $err = 'وضعیت نامعتبر.';
        } elseif ($to === '' && $note === '') {
            $err = 'وضعیت یا یادداشت را وارد کنید.';
        } else {
            $ev = $to !== ''
                ? ['kind' => 'status', 'role' => 'dev', 'who' => 'دولوپر', 'to' => $to, 'note' => $note]
                : ['kind' => 'note', 'role' => 'dev', 'who' => 'دولوپر', 'note' => $note];
            if ($to !== '') $bug['status'] = $to;
            add_event($bug, $ev);
            $sent = notify_event($bug, $ev);
            $bug['log'][array_key_last($bug['log'])]['tg'] = $sent;
            save_bug($bug);
            flash('ثبت شد' . ($sent ? '' : ' (اعلان تلگرام ارسال نشد)'));
            redirect('bug.php?id=' . $id);
        }
    } else { // support
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 60) {
            $err = 'نام شما الزامی است.';
        } else {
            $s = $bug['status'];
            $ev = null;
            if ($action === 'reply' && $s === 'info') {
                if ($note === '') $err = 'پاسخ خالی است.';
                else { $ev = ['kind' => 'status', 'role' => 'support', 'who' => $name, 'to' => 'review', 'note' => $note]; }
            } elseif ($action === 'close' && $s === 'test') {
                $ev = ['kind' => 'status', 'role' => 'support', 'who' => $name, 'to' => 'closed', 'note' => $note];
            } elseif ($action === 'reopen' && in_array($s, ['test', 'closed', 'rejected'], true)) {
                $ev = ['kind' => 'status', 'role' => 'support', 'who' => $name, 'to' => 'reopen', 'note' => $note];
            } elseif ($action === 'note') {
                if ($note === '') $err = 'یادداشت خالی است.';
                else { $ev = ['kind' => 'note', 'role' => 'support', 'who' => $name, 'note' => $note]; }
            } else {
                $err = 'این عملیات در وضعیت فعلی مجاز نیست.';
            }
            if ($ev) {
                if ($ev['kind'] === 'status') $bug['status'] = $ev['to'];
                add_event($bug, $ev);
                // پاسخ ساپورت به «نیاز به اطلاعات» همیشه باید به دولوپر برسد، مستقل از تنظیمات وضعیت‌ها
                $sent = ($action === 'reply')
                    ? notify_event($bug, ['kind' => 'note', 'role' => 'support', 'who' => $name, 'note' => $note])
                    : notify_event($bug, $ev);
                $bug['log'][array_key_last($bug['log'])]['tg'] = $sent;
                save_bug($bug);
                flash('ثبت شد' . ($sent ? '' : ' (اعلان تلگرام ارسال نشد)'));
                redirect('bug.php?id=' . $id);
            }
        }
    }
}

page_header('#' . $id . ' ' . $bug['title']);
?>
<div class="crumb"><a href="index.php">لیست</a> ‹ #<?= fa_digits($id) ?></div>
<div class="detail">
  <div class="panel">
    <div class="head">
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:6px"><span class="id num">#<?= fa_digits($id) ?></span><?= badge($bug['status']) ?><?= prio_badge($bug) ?></div>
      <h1><?= h($bug['title']) ?></h1>
      <div class="meta"><span><b>ثبت:</b> <?= h($bug['reporter']) ?> (ساپورت)</span><span class="num"><?= jdate((int)$bug['created']) ?></span></div>
    </div>
    <div class="section"><p><?= h($bug['desc']) ?></p></div>
    <?php if (!empty($bug['files'])): ?>
    <div class="section"><div class="shots">
      <?php foreach ($bug['files'] as $f): ?><a href="file.php?f=<?= h($f) ?>" target="_blank"><img src="file.php?f=<?= h($f) ?>" alt="اسکرین‌شات"></a><?php endforeach; ?>
    </div></div>
    <?php endif; ?>
    <div class="timeline"><h3>گفتگو</h3>
    <?php foreach ($bug['log'] as $e):
        $isDev = ($e['role'] ?? '') === 'dev';
        $what = $e['kind'] === 'created' ? 'ثبت کرد' : ($e['kind'] === 'status' ? badge($e['to']) : 'یادداشت');
    ?>
      <div class="ev"><div class="ic <?= $isDev ? 'dev' : 'sup' ?>"><?= $isDev ? 'D' : 'S' ?></div><div class="body">
        <div class="top"><b><?= h($e['who'] ?? '') ?></b><?= $isDev ? '' : '<span class="rtag">ساپورت</span>' ?><span><?= $what ?></span><?= (isset($e['tg']) && $e['tg'] === false) ? '<span class="warn">اعلان ارسال نشد</span>' : '' ?><time class="num"><?= jdate((int)$e['ts']) ?></time></div>
        <?php if (!empty($e['note'])): ?><div class="note"><?= h($e['note']) ?></div><?php endif; ?>
      </div></div>
    <?php endforeach; ?>
    </div>
  </div>

  <div class="side"><div class="panel">
    <?php if ($err): ?><div class="error"><?= h($err) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <?php if ($r === 'dev'): ?>
        <div class="field"><label>وضعیت</label><select name="status">
          <option value="">بدون تغییر (<?= STATUSES[$bug['status']] ?>)</option>
          <?php foreach (DEV_STATUSES as $k): if ($k === $bug['status']) continue; ?><option value="<?= $k ?>"><?= STATUSES[$k] ?></option><?php endforeach; ?>
        </select></div>
        <div class="field"><label>یادداشت</label><textarea name="note" style="min-height:90px" placeholder="توضیح برای تیم ساپورت"></textarea></div>
        <div class="stack"><button class="btn primary">ثبت</button></div>
      <?php else:
        $s = $bug['status']; ?>
        <?php if ($s === 'info'): ?><p class="hintline" style="--fgc:var(--s-info)">دولوپر منتظر پاسخ شماست</p>
        <?php elseif ($s === 'test'): ?><p class="hintline" style="--fgc:var(--s-test)">آماده تست</p><?php endif; ?>
        <div class="field"><label>نام شما <b>*</b></label><input name="name" placeholder="نام ثبت‌کننده" required maxlength="60"></div>
        <div class="field"><label>یادداشت</label><textarea name="note" style="min-height:90px" placeholder="پاسخ، نتیجه تست یا اطلاعات تکمیلی"></textarea></div>
        <div class="stack">
        <?php if ($s === 'info'): ?>
          <button class="btn primary" name="action" value="reply">ارسال پاسخ</button>
        <?php elseif ($s === 'test'): ?>
          <button class="btn ok" name="action" value="close">✓ تأیید و بستن</button>
          <button class="btn warn" name="action" value="reopen">↩ بازگشایی</button>
        <?php elseif ($s === 'closed' || $s === 'rejected'): ?>
          <button class="btn warn" name="action" value="reopen">↩ بازگشایی</button>
        <?php else: ?>
          <button class="btn primary" name="action" value="note">ثبت یادداشت</button>
        <?php endif; ?>
        </div>
      <?php endif; ?>
    </form>
  </div></div>
</div>
<?php page_footer();
