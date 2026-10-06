<?php
require __DIR__ . '/inc/lib.php';
if (!cfg()) { header('Location: install.php'); exit; }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (password_verify($_POST['master'] ?? '', cfg()['master_hash'])) {
        session_regenerate_id(true); $_SESSION['auth'] = true; header('Location: index.php'); exit;
    }
    $err = 'Parola hatalı.';
}
header_html('Giriş');
reminder();
if ($err) echo '<p class="err" role="alert">' . e($err) . '</p>';
?>
<form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
<label for="master">Sistem ana parolası</label><input id="master" name="master" type="password" required autofocus>
<button type="submit">Giriş yap</button></form>
<?php footer_html();
