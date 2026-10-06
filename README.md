# php-mail-istemcisi
PHP üzerinden mail alıp gönderebileceğiniz, MySQL tabanlı, erişilebilirlik (etiketli formlar, klavye/ekran okuyucu uyumu) gözetilerek yazılmış mail istemcisi.

- Sınırsız mail hesabı eklenebilir (IMAP/SMTP); hesap parolaları AES-256-GCM ile şifrelenerek saklanır.
- Gelen ve gönderilen mailler MySQL veritabanında saklanır.
- Sistem ana parolası kurulumda belirlenir.
- AMPPS gibi localhost ortamlarına veya web hostinge kurulabilir.

## Kurulum
1. Dosyaları sunucuya kopyalayın, boş bir MySQL veritabanı oluşturun (PHP `imap`, `openssl`, `pdo_mysql` eklentileri gerekli).
2. `install.php` sayfasını açın ve formu doldurun.
3. `login.php` ile ana parolanızla giriş yapın.

## ⚠️ Güvenlik hatırlatması
Web hostinge kuruyorsanız **kök dizini mutlaka şifreleyin** (hosting panelinde parola korumalı dizin) ve HTTPS kullanın.
Bu hatırlatma mail hesabı eklerken, sistem ayarlarını yapılandırırken ve kurulumda uygulama içinde de gösterilir.
