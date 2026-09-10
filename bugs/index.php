<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
require_login();

$bugs = all_bugs();
$filter = (string)($_GET['f'] ?? 'open');
$q = trim((string)($_GET['q'] ?? ''));

$c = ['open' => 0, 'info' => 0, 'test' => 0, 'closed' => 0];
foreach ($bugs as $b) {
    if (in_array($b['status'], OPEN_STATUSES, true)) $c['open']++;
    if ($b['status'] === 'info') $c['info']++;
    if ($b['status'] === 'test') $c['test']++;
    if ($b['status'] === 'closed' || $b['status'] === 'rejected') $c['closed']++;
}
$rows = array_filter($bugs, function ($b) use ($filter, $q) {
    $s = $b['status'];
    if ($filter === 'open' && !in_array($s, OPEN_STATUSES, true)) return false;
    if ($filter === 'info' && $s !== 'info') return false;
    if ($filter === 'test' && $s !== 'test') return false;
    if ($filter === 'closed' && !($s === 'closed' || $s === 'rejected')) return false;
    if ($q !== '' && mb_stripos($b['title'] . ' ' . $b['desc'] . ' ' . $b['id'] . ' ' . $b['reporter'], $q) === false) return false;
    return true;
});
$stats = [
    ['open', $c['open'], 'باز', 'var(--s-new)'],
    ['info', $c['info'], 'نیاز به اطلاعات', 'var(--s-info)'],
    ['test', $c['test'], 'آماده تست', 'var(--s-test)'],
    ['closed', $c['closed'], 'بسته / رد شده', 'var(--s-closed)'],
    ['all', count($bugs), 'همه', 'var(--muted)'],
];
page_header('لیست باگ‌ها');
?>
<div class="stats">
<?php foreach ($stats as [$k, $n, $l, $col]): ?>
  <a class="stat <?= $filter === $k ? 'on' : '' ?>" style="--c:<?= $col ?>" href="?f=<?= $k ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>"><div class="n num"><?= fa_digits($n) ?></div><div class="l"><?= $l ?></div></a>
<?php endforeach; ?>
</div>
<div class="panel">
  <form class="filters" method="get">
    <input type="hidden" name="f" value="<?= h($filter) ?>">
    <input name="q" value="<?= h($q) ?>" placeholder="جستجو در موضوع، شرح یا شماره">
    <span class="count num"><?= fa_digits(count($rows)) ?> مورد</span>
  </form>
  <div class="tbl-wrap"><table>
    <thead><tr><th>#</th><th>موضوع</th><th>وضعیت</th><th>اولویت</th><th>ثبت‌کننده</th><th>آخرین تغییر</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="6" class="empty">موردی نیست.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $b): ?>
      <tr>
        <td class="id num"><?= fa_digits($b['id']) ?></td>
        <td class="title"><a href="bug.php?id=<?= (int)$b['id'] ?>"><?= h($b['title']) ?></a><?php if (!empty($b['files'])): ?><span class="sub">📎 <?= fa_digits(count($b['files'])) ?></span><?php endif; ?></td>
        <td><?= badge($b['status']) ?></td>
        <td><?= prio_badge($b) ?></td>
        <td style="color:var(--ink2)"><?= h($b['reporter']) ?></td>
        <td class="num" style="color:var(--muted);font-size:12.5px"><?= jdate((int)$b['updated']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php page_footer();
