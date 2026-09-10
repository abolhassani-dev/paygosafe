<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
require_role('support');

$err = null;
$old = ['name' => '', 'title' => '', 'desc' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old['name'] = trim((string)($_POST['name'] ?? ''));
    $old['title'] = trim((string)($_POST['title'] ?? ''));
    $old['desc'] = trim(str_replace("\r", '', (string)($_POST['desc'] ?? '')));
    if ($old['name'] === '' || $old['title'] === '' || $old['desc'] === '') {
        $err = 'نام، موضوع و شرح الزامی است.';
    } elseif (mb_strlen($old['name']) > 60 || mb_strlen($old['title']) > 200 || mb_strlen($old['desc']) > 3500) {
        $err = 'متن طولانی‌تر از حد مجاز است (شرح حداکثر ۳۵۰۰ کاراکتر).';
    } else {
        $id = next_id();
        [$files, $uerr] = handle_uploads($id, $_FILES['shots'] ?? []);
        if ($uerr) {
            foreach ($files as $f) @unlink(UPLOAD_DIR . '/' . $f);
            $err = $uerr;
        } else {
            $bug = [
                'id' => $id,
                'title' => $old['title'],
                'desc' => $old['desc'],
                'reporter' => $old['name'],
                'status' => 'new',
                'files' => $files,
                'created' => time(),
                'updated' => time(),
                'log' => [],
            ];
            add_event($bug, ['kind' => 'created', 'role' => 'support', 'who' => $old['name']]);
            notify_new($bug);
            $bug['log'][0]['tg'] = $bug['tg_sent'];
            save_bug($bug);
            flash('#' . fa_digits($id) . ' ثبت شد' . ($bug['tg_sent'] ? '' : ' (اعلان تلگرام ارسال نشد)'));
            redirect('bug.php?id=' . $id);
        }
    }
}
page_header('باگ جدید');
?>
<div class="crumb"><a href="index.php">لیست</a> ‹ باگ جدید</div>
<div class="panel form">
  <h1>باگ جدید</h1>
  <?php if ($err): ?><div class="error"><?= h($err) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="field"><label>نام شما <b>*</b></label><input name="name" value="<?= h($old['name']) ?>" placeholder="نام ثبت‌کننده" required maxlength="60"></div>
    <div class="field"><label>موضوع <b>*</b></label><input name="title" value="<?= h($old['title']) ?>" placeholder="عنوان کوتاه مشکل" required maxlength="200"></div>
    <div class="field"><label>شرح <b>*</b></label><textarea name="desc" style="min-height:160px" placeholder="شرح کامل مشکل با ریز جزئیات و بخش مورد نظر" required><?= h($old['desc']) ?></textarea></div>
    <div class="field"><label>اسکرین‌شات</label><input type="file" name="shots[]" accept="image/*" multiple><span class="hint">اختیاری · تا <?= fa_digits($CFG['max_files']) ?> تصویر، هر کدام حداکثر <?= fa_digits($CFG['max_file_mb']) ?> مگابایت</span></div>
    <div class="actions"><button class="btn primary">ثبت</button><a class="btn" href="index.php">انصراف</a></div>
  </form>
</div>
<?php page_footer();
