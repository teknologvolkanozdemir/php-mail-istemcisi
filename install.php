<?php
require __DIR__ . '/inc/lib.php';
if (cfg()) { exit('Kurulum zaten yapılmış. Yeniden kurmak için config.php dosyasını silin.'); }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $p = $_POST;
    try {
        if (strlen($p['master'] ?? '') < 8) throw new RuntimeException('Sistem ana parolası en az 8 karakter olmalı.');
        if (empty($p['ack'])) throw new RuntimeException('Kök dizin şifreleme hatırlatmasını onaylamalısınız.');
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $p['host'], $p['dbname']);
        $c = ['dsn' => $dsn, 'db_user' => $p['user'], 'db_pass' => $p['pass'],
              'master_hash' => password_hash($p['master'], PASSWORD_DEFAULT), 'app_key' => base64_encode(random_bytes(32))];
        $pdo = new PDO($dsn, $c['db_user'], $c['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        schema($pdo);
        file_put_contents(CONFIG_FILE, "<?php\nreturn " . var_export($c, true) . ";\n");
        @chmod(CONFIG_FILE, 0600);
        header('Location: login.php'); exit;
    } catch (Throwable $ex) { $err = $ex->getMessage(); }
}
header_html('Kurulum');
reminder();
if ($err) echo '<p class="err" role="alert">' . e($err) . '</p>';
?>
<form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
<label for="host">MySQL sunucusu</label><input id="host" name="host" value="localhost" required>
<label for="dbname">Veritabanı adı</label><input id="dbname" name="dbname" required>
<label for="user">Veritabanı kullanıcısı</label><input id="user" name="user" required>
<label for="pass">Veritabanı parolası</label><input id="pass" name="pass" type="password" autocomplete="off">
<label for="master">Sistem ana parolası (en az 8 karakter)</label><input id="master" name="master" type="password" required minlength="8" autocomplete="new-password">
<label><input type="checkbox" name="ack" value="1" style="width:auto" required> Web hostinge kuruyorsam kök dizini şifreleyeceğimi (parola koruması + HTTPS) anladım.</label>
<button type="submit">Kur</button></form>
<?php footer_html();
