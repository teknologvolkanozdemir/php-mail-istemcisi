<?php
require __DIR__ . '/inc/lib.php';
require_login();
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (isset($_POST['delete'])) {
        $id = (int)$_POST['delete'];
        db()->prepare('DELETE FROM messages WHERE account_id=?')->execute([$id]);
        db()->prepare('DELETE FROM accounts WHERE id=?')->execute([$id]);
    } else {
        $p = $_POST;
        db()->prepare('INSERT INTO accounts (email,imap_host,imap_port,smtp_host,smtp_port,username,password_enc) VALUES (?,?,?,?,?,?,?)')
            ->execute([$p['email'], $p['imap_host'], (int)$p['imap_port'], $p['smtp_host'], (int)$p['smtp_port'], $p['username'], encrypt($p['password'])]);
        $msg = '<p class="ok" role="status">Hesap eklendi.</p>';
    }
}
header_html('Mail hesapları');
reminder();
echo $msg;
?>
<h2>Hesaplar</h2><ul>
<?php foreach (db()->query('SELECT id,email FROM accounts') as $a): ?>
<li><?= e($a['email']) ?> <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><button name="delete" value="<?= (int)$a['id'] ?>" aria-label="<?= e($a['email']) ?> hesabını sil">Sil</button></form></li>
<?php endforeach; ?></ul>
<h2>Yeni hesap ekle</h2>
<form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
<label for="email">E-posta adresi</label><input id="email" name="email" type="email" required>
<label for="username">Kullanıcı adı</label><input id="username" name="username" required>
<label for="password">Parola</label><input id="password" name="password" type="password" required autocomplete="off">
<label for="imap_host">IMAP sunucusu</label><input id="imap_host" name="imap_host" required>
<label for="imap_port">IMAP portu</label><input id="imap_port" name="imap_port" type="number" value="993" required>
<label for="smtp_host">SMTP sunucusu</label><input id="smtp_host" name="smtp_host" required>
<label for="smtp_port">SMTP portu (465 SSL / 587 STARTTLS)</label><input id="smtp_port" name="smtp_port" type="number" value="465" required>
<button type="submit">Hesap ekle</button></form>
<?php footer_html();
