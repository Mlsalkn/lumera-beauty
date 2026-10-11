# Dış yapılandırmalar ve sınırlar

| Konu | Mevcut durum | Gerçek kullanım için gereken |
|---|---|---|
| SMTP | MAIL_DRIVER=disabled; loopback PHPMailer testi başarılı | Sağlayıcı host/port/TLS, kullanıcı/parola, doğrulanmış gönderen |
| E-posta doğrulama / sıfırlama / hatırlatma | Uygulama ve SMTP kuyruğu test edildi | Gerçek alıcı teslimatı ve sağlayıcı SPF/DKIM/DMARC ayarı |
| E-posta worker zamanlaması | CLI ve görev kurulum betiği mevcut, Windows görevi etkin değil | Yetkili hesapla bin/install-mail-task.ps1 -Install; görev geçmişi/başarısızlık takibi |
| HTTPS / public URL | localhost HTTP test edildi | Gerçek HTTPS alan adı, APP_URL, Apache TLS ve cookie Secure doğrulaması |
| İşletme iletişim telefonu | Sistem ayarlarından düzenlenir; yoksa dürüst boş durum | Yetkili yöneticinin gerçek numarayı girmesi |
| Yedek / restore | Prosedür yazıldı, boş şema import testi yapıldı | Şifreli gerçek veri yedek politikası ve ayrı ortamda restore provası |
| Çok sunuculu çalışma | Tek sunucuda dosya tabanlı hız sınırı | Ortak sayaç/session deposu ve dağıtım tasarımı |

Gerçek posta alıcısı, SMTP parolası veya işletme bilgisi uydurulmamıştır. Mail kuyruğu testleri yalnızca @example.invalid alıcıları ve 127.0.0.1 SMTP kullandı. Ödeme/tahsilat ve SMS sağlayıcısı uygulanmadı; bu özellikler teslim kapsamı olarak sunulmaz. Firefox/Safari, fiziksel cihaz, üretim yük/stres ve bağımsız penetrasyon denetimi yapılmadı. Localhost üzerindeki kullanım ile canlı işletme yayını ayrı kabul düzeyleridir.
