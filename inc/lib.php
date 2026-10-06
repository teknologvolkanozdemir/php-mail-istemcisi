<?php
session_start();
const CONFIG_FILE = __DIR__ . '/../config.php';

function cfg(): array { return is_file(CONFIG_FILE) ? require CONFIG_FILE : []; }
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $c = cfg();
        $pdo = new PDO($c['dsn'], $c['db_user'], $c['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS accounts (
        id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(255) NOT NULL,
        imap_host VARCHAR(255) NOT NULL, imap_port INT NOT NULL DEFAULT 993,
        smtp_host VARCHAR(255) NOT NULL, smtp_port INT NOT NULL DEFAULT 465,
        username VARCHAR(255) NOT NULL, password_enc TEXT NOT NULL
    ) DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL,
        folder ENUM('inbox','sent') NOT NULL, uid_key VARCHAR(255) NOT NULL,
        from_addr VARCHAR(512), to_addr VARCHAR(512), subject VARCHAR(998),
        body MEDIUMTEXT, date_at DATETIME NOT NULL,
        UNIQUE KEY uq (account_id, folder, uid_key)
    ) DEFAULT CHARSET=utf8mb4");
}

function encrypt(string $plain): string {
    $key = base64_decode(cfg()['app_key']);
    $iv = random_bytes(12);
    $ct = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $ct);
}
function decrypt(string $enc): string {
    $key = base64_decode(cfg()['app_key']);
    $raw = base64_decode($enc);
    return (string)openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
}

function csrf(): string {
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}
function check_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); exit('Geçersiz istek.'); }
}

function require_login(): void {
    if (!cfg()) { header('Location: install.php'); exit; }
    if (empty($_SESSION['auth'])) { header('Location: login.php'); exit; }
}

const SECURITY_REMINDER = 'Hatırlatma: Uygulamayı web hostinge kurduysanız kök dizinini mutlaka şifreleyin/koruyun (hosting panelinden "Dizin Parolası" / parola korumalı dizin, ve HTTPS kullanın). Aksi halde mail verileriniz herkese açık olabilir.';

function header_html(string $title): void {
    ?><!DOCTYPE html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> - PHP Mail İstemcisi</title>
<style>body{font-family:sans-serif;max-width:60rem;margin:0 auto;padding:1rem;line-height:1.5;color:#111;background:#fff}
a{color:#0033cc}a:focus,button:focus,input:focus,textarea:focus,select:focus{outline:3px solid #ff9900;outline-offset:2px}
.skip{position:absolute;left:-999px}.skip:focus{left:0;background:#fff;padding:.5rem}
label{display:block;margin-top:.8rem;font-weight:bold}input,textarea,select{width:100%;padding:.4rem;font-size:1rem;box-sizing:border-box}
button{margin-top:1rem;padding:.6rem 1rem;font-size:1rem}.warn{border:2px solid #b35900;background:#fff4e5;padding:.8rem}
.ok{border:2px solid #006600;padding:.8rem}.err{border:2px solid #b00000;padding:.8rem}table{width:100%;border-collapse:collapse}th,td{border-bottom:1px solid #999;padding:.4rem;text-align:left}</style></head><body>
<a class="skip" href="#main">İçeriğe geç</a>
<?php if (!empty($_SESSION['auth'])): ?>
<nav aria-label="Ana menü"><a href="index.php">Gelen</a> | <a href="index.php?folder=sent">Gönderilen</a> | <a href="compose.php">Yeni mail</a> | <a href="accounts.php">Hesaplar</a> | <a href="settings.php">Sistem ayarları</a> | <a href="logout.php">Çıkış</a></nav>
<?php endif; ?>
<main id="main"><h1><?= e($title) ?></h1>
<?php }
function footer_html(): void { echo '</main></body></html>'; }
function reminder(): void { echo '<p class="warn" role="note"><strong>' . e(SECURITY_REMINDER) . '</strong></p>'; }

function imap_fetch_account(array $a, int $limit = 50): int {
    $mbox = sprintf('{%s:%d/imap/ssl}INBOX', $a['imap_host'], $a['imap_port']);
    $conn = @imap_open($mbox, $a['username'], decrypt($a['password_enc']), OP_READONLY, 1);
    if (!$conn) throw new RuntimeException('IMAP bağlantısı kurulamadı: ' . imap_last_error());
    $n = imap_num_msg($conn); $count = 0;
    $st = db()->prepare("INSERT IGNORE INTO messages (account_id,folder,uid_key,from_addr,to_addr,subject,body,date_at) VALUES (?,?,?,?,?,?,?,?)");
    for ($i = $n; $i > max(0, $n - $limit); $i--) {
        $h = imap_headerinfo($conn, $i);
        $dec = fn($s) => isset($s) ? mb_decode_mimeheader($s) : '';
        $body = imap_fetchbody($conn, $i, '1');
        $struct = imap_fetchstructure($conn, $i);
        $enc = $struct->parts[0]->encoding ?? $struct->encoding ?? 0;
        $body = $enc == 3 ? base64_decode($body) : ($enc == 4 ? quoted_printable_decode($body) : $body);
        $st->execute([$a['id'], 'inbox', trim($h->message_id ?? "n$i"), $dec($h->fromaddress ?? ''), $dec($h->toaddress ?? ''),
            $dec($h->subject ?? ''), $body, date('Y-m-d H:i:s', strtotime($h->date ?? 'now'))]);
        $count += $st->rowCount();
    }
    imap_close($conn);
    return $count;
}

function smtp_send(array $a, string $to, string $subject, string $body): void {
    $host = ($a['smtp_port'] == 465 ? 'ssl://' : '') . $a['smtp_host'];
    $fp = @stream_socket_client("$host:{$a['smtp_port']}", $en, $es, 15);
    if (!$fp) throw new RuntimeException("SMTP bağlantısı kurulamadı: $es");
    $read = function () use ($fp) { $r = ''; while (($l = fgets($fp, 515)) !== false) { $r .= $l; if (($l[3] ?? ' ') === ' ') break; } return $r; };
    $cmd = function (string $c, string $ok) use ($fp, $read) {
        if ($c !== '') fwrite($fp, $c . "\r\n");
        $r = $read();
        if (strpos($r, $ok) !== 0) throw new RuntimeException('SMTP hatası: ' . trim($r));
    };
    $clean = fn($s) => str_replace(["\r", "\n"], '', $s);
    $cmd('', '220'); $cmd('EHLO localhost', '250');
    if ($a['smtp_port'] == 587) {
        $cmd('STARTTLS', '220');
        stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $cmd('EHLO localhost', '250');
    }
    $cmd('AUTH LOGIN', '334'); $cmd(base64_encode($a['username']), '334'); $cmd(base64_encode(decrypt($a['password_enc'])), '235');
    $cmd('MAIL FROM:<' . $clean($a['email']) . '>', '250'); $cmd('RCPT TO:<' . $clean($to) . '>', '250'); $cmd('DATA', '354');
    $msg = "From: " . $clean($a['email']) . "\r\nTo: " . $clean($to) . "\r\nSubject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
        . "Date: " . date('r') . "\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($body));
    fwrite($fp, $msg . "\r\n.\r\n");
    $r = $read(); if (strpos($r, '250') !== 0) throw new RuntimeException('SMTP hatası: ' . trim($r));
    fwrite($fp, "QUIT\r\n"); fclose($fp);
}
