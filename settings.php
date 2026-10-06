<?php
require __DIR__ . '/inc/lib.php';
require_login();
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $c = cfg();
    if (!password_verify($_POST['old'] ?? '', $c['master_hash'])) $msg = '<p class="err" role="alert">Mevcut parola hatalı.</p>';
    elseif (strlen($_POST['new'] ?? '') < 8) $msg = '<p class="err" role="alert">Yeni parola en az 8 karakter olmalı.</p>';
    else {
        $c['master_hash'] = password_hash($_POST['new'], PASSWORD_DEFAULT);
        file_put_contents(CONFIG_FILE, "<?php\nreturn " . var_export($c, true) . ";\n");
        $msg = '<p class="ok" role="status">Sistem ana parolası güncellendi.</p>';
    }
}
header_html('Sistem ayarları');
reminder();
echo $msg;
?>
<form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
<label for="old">Mevcut ana parola</label><input id="old" name="old" type="password" required>
<label for="new">Yeni ana parola (en az 8 karakter)</label><input id="new" name="new" type="password" required minlength="8">
<button type="submit">Parolayı değiştir</button></form>
<?php footer_html();
