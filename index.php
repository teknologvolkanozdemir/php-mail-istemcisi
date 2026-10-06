<?php
require __DIR__ . '/inc/lib.php';
require_login();
$folder = ($_GET['folder'] ?? 'inbox') === 'sent' ? 'sent' : 'inbox';
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $total = 0;
    foreach (db()->query('SELECT * FROM accounts') as $a) {
        try { $total += imap_fetch_account($a); } catch (Throwable $ex) { $msg .= e($a['email'] . ': ' . $ex->getMessage()) . ' '; }
    }
    $msg = ($msg ? '<p class="err" role="alert">' . $msg . '</p>' : '') . "<p class=\"ok\" role=\"status\">$total yeni mail alındı.</p>";
}
if (isset($_GET['id'])) {
    $st = db()->prepare('SELECT * FROM messages WHERE id=?'); $st->execute([(int)$_GET['id']]);
    $m = $st->fetch();
    header_html($m ? $m['subject'] : 'Mail bulunamadı');
    if ($m) echo '<p>Kimden: ' . e($m['from_addr']) . '<br>Kime: ' . e($m['to_addr']) . '<br>Tarih: ' . e($m['date_at']) . '</p><pre style="white-space:pre-wrap">' . e($m['body']) . '</pre>';
    footer_html(); exit;
}
$rows = db()->prepare('SELECT m.*, a.email FROM messages m JOIN accounts a ON a.id=m.account_id WHERE folder=? ORDER BY date_at DESC LIMIT 200');
$rows->execute([$folder]);
header_html($folder === 'inbox' ? 'Gelen kutusu' : 'Gönderilen');
echo $msg;
if ($folder === 'inbox') echo '<form method="post"><input type="hidden" name="csrf" value="' . e(csrf()) . '"><button type="submit">Yeni mailleri al</button></form>';
?>
<table><caption class="skip">Mail listesi</caption><thead><tr><th scope="col">Hesap</th><th scope="col"><?= $folder === 'inbox' ? 'Kimden' : 'Kime' ?></th><th scope="col">Konu</th><th scope="col">Tarih</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr><td><?= e($r['email']) ?></td><td><?= e($folder === 'inbox' ? $r['from_addr'] : $r['to_addr']) ?></td><td><a href="index.php?id=<?= (int)$r['id'] ?>"><?= e($r['subject'] ?: '(konu yok)') ?></a></td><td><?= e($r['date_at']) ?></td></tr>
<?php endforeach; ?></tbody></table>
<?php footer_html();
