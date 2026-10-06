<?php
require __DIR__ . '/inc/lib.php';
require_login();
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        $st = db()->prepare('SELECT * FROM accounts WHERE id=?'); $st->execute([(int)$_POST['account']]);
        $a = $st->fetch(); if (!$a) throw new RuntimeException('Hesap seçin.');
        if (!filter_var($_POST['to'] ?? '', FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Geçerli bir alıcı adresi girin.');
        smtp_send($a, $_POST['to'], $_POST['subject'], $_POST['body']);
        db()->prepare("INSERT INTO messages (account_id,folder,uid_key,from_addr,to_addr,subject,body,date_at) VALUES (?,?,?,?,?,?,?,NOW())")
            ->execute([$a['id'], 'sent', bin2hex(random_bytes(8)), $a['email'], $_POST['to'], $_POST['subject'], $_POST['body']]);
        $msg = '<p class="ok" role="status">Mail gönderildi.</p>';
    } catch (Throwable $ex) { $msg = '<p class="err" role="alert">' . e($ex->getMessage()) . '</p>'; }
}
header_html('Yeni mail');
echo $msg;
?>
<form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
<label for="account">Gönderen hesap</label><select id="account" name="account" required>
<?php foreach (db()->query('SELECT id,email FROM accounts') as $a) echo '<option value="' . (int)$a['id'] . '">' . e($a['email']) . '</option>'; ?></select>
<label for="to">Alıcı</label><input id="to" name="to" type="email" required>
<label for="subject">Konu</label><input id="subject" name="subject" required>
<label for="body">Mesaj</label><textarea id="body" name="body" rows="10" required></textarea>
<button type="submit">Gönder</button></form>
<?php footer_html();
