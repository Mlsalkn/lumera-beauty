# Luméra Beauty — E-posta ve bakım

## SMTP yapılandırması

PHPMailer Composer ile kilitli sürümde kuruludur. Kaynak: https://github.com/PHPMailer/PHPMailer. Gerçek kurulumda yalnızca yerel .env veya işletim sistemi ortamına MAIL_DRIVER=smtp, MAIL_FROM_ADDRESS, MAIL_FROM_NAME, SMTP_HOST, SMTP_PORT, SMTP_ENCRYPTION=tls (587) veya ssl (465), SMTP_USERNAME ve SMTP_PASSWORD girilir. Sağlayıcının uygulama parolası/servis hesabını kullanın. Sertifika doğrulaması kapatılmaz; güvenilir CA deposu gerekirse php.ini openssl.cafile üzerinden tanımlanır.

MAIL_ENCRYPTION_KEY 32 rastgele baytın Base64 değeridir; yerel kurulumda oluşturuldu, kaynak veya rapora yazılmadı. Kuyruk yükleri AES-256-GCM ile şifrelenir. Bu anahtarı yedeklemeden değiştirmek bekleyen iletilerin çözülememesine yol açar. SMTP parolası, token veya ileti içeriği hata kayıtlarına yazılmaz. Sent/skipped ileti yükleri temizlenir; auth_tokens yalnızca SHA-256 token özetini tutar.

MAIL_DRIVER=disabled varsayılandır. Uygulama içi bildirimler ve randevular çalışır; yeni e-posta işi oluşturulmaz ve eski kuyruk gönderilmez. Şifre yardım formu gönderimi kapalı gösterir. EMAIL_REQUIRE_VERIFICATION=false mevcut davranışı korur. Doğrulama postaları gerçek teslimle test edildikten sonra true yapılabilir; yalnızca yeni CUSTOMER kayıtları doğrulama bekler. Önceden var olan hesaplar korunur. Doğrulama zorunlu ve SMTP kapalıysa yeni kayıt açıklayıcı mesajla engellenir.

Kayıt doğrulama bağlantısı 24 saat, şifre sıfırlama 30 dakika, e-posta değişikliği 15 dakika geçerlidir. GET hiçbir hesabı değiştirmez; CSRF korumalı POST gerekir. Yeni token önceki tokenı geçersiz kılar. Şifre sıfırlama auth_version değerini artırıp tüm eski oturumları kapatır. Üretilen bağlantılar tokenı URL fragment kısmında taşır; JS gizli forma aktarır ve adres çubuğundan kaldırır. Fragment HTTP GET isteğinde sunucuya gönderilmez. Bağlantı sayfalarında JavaScript etkin olmalıdır. Eski query-token bağlantıları geriye uyumluluk için kabul edilir; web sunucusu erişim logları ayrıca korunmalıdır.

## Kuyruk ve hatırlatmalar

Proje kökünde `C:\xampp\php\php.exe bin/process-mail.php --run --reminders` çalıştırılır. Onaylı, gelecekte ve ayarlanan 1–72 saat içindeki randevular için müşteriye hatırlatma kuyruğa eklenir. Varsayılan 24 saattir. Tercihi kapalı müşteriye hatırlatma oluşturulmaz. Aynı randevu/tarih için tekrar çalıştırma ikinci hatırlatma üretmez. İptal edilmiş, geçmiş veya tarihi değişmiş randevunun eski hatırlatması gönderim sırasında atlanır.

Kuyruk yazımı randevu/izin bildirimleriyle aynı transaction içindedir. SMTP gönderimi ayrı CLI sürecindedir; kesinti randevuyu geri almaz. Tek dispatcher advisory lock kullanır. Hatalar 5 denemeye kadar artan bekleme süresiyle tekrar denenir; sonra failed olur. Yönetici sistem ekranı sayılarını gösterir. Failed kayıtlarını çözüme göre inceleyin; şifreli payload veya SMTP sırlarını raporlara çıkarmayın. İleti SMTP tarafından kabul edilip DB güncellemesinden önce süreç kapanırsa aynı e-posta tekrar teslim edilebilir; SMTP teslimi tam olarak bir kez garantisi taşımaz. Randevu oluşturma idempotensi ve çakışma kilitleri ayrı olarak korunur.

Windows görev hazırlığı `bin/install-mail-task.ps1` dosyasındadır. Önce `-Install` olmadan kontrol edin; SMTP ve APP_URL doğrulandığında `-Install` ile LumeraBeautyMailQueue görevi 5 dakikada bir kurulabilir. Mevcut görev üzerine yazılmaz; kullanıcı oturumu açık olmalıdır. Yetki hatasında Windows görev kaydı için yönetici onayı gerekebilir. Bu bilgisayarda SMTP bilgisi olmadığından kalıcı görev kurulmadı. Üretimde sürekli çalışan hizmet/cron ve kuyruk izleme gerekir. APP_URL üretimde HTTPS olmalıdır; localhost bağlantısı uzak alıcı tarafından açılamaz.

## Yedekleme

1. Yedek hedefini htdocs/public dışında, erişimi sınırlı bir dizin olarak seçin. Var olan yedeğin üzerine yazmayın; tarihli dosya adı kullanın. Randevu/izin yazımlarını bakım penceresinde durdurun veya tutarlı InnoDB snapshot alın.
2. Yetkisi sınırlı yedekleme hesabının ayarlarını yalnızca kullanıcıya açık geçici MySQL seçenek dosyasına yazın. Komut satırına parola koymayın. Windows ACL ve diskin şifrelenmesini kullanın.
3. `C:\xampp\mysql\bin\mysqldump.exe --defaults-extra-file=C:\secure\backup-client.cnf --single-transaction --quick --skip-lock-tables --routines --triggers --events --result-file=C:\secure\backups\lumera-YYYYMMDD-HHMM.sql lumera_beauty_db` örneğini gerçek güvenli yollarla kullanın. --defaults-extra-file ilk seçenek olmalıdır. Routines/triggers/events yetkileri ayrı yedek hesabında gerekebilir; uygulama hesabına bu yetkileri vermeyin.
4. Çıkış kodu, dosya boyutu ve SHA-256 özetini kontrol edin. .env (posta şifreleme anahtarı dahil), public/uploads ve kaynak/Composer lock dosyalarını DB ile eşleşen zamanda ayrı şifreli arşivde saklayın. Bunları Git'e veya web dizinine koymayın.
5. Saklama süresi, erişim yetkisi ve geri yükleme denemesini kayıt altına alın. Yedeklerin kişisel veri içerdiğini dikkate alın.

## Geri yükleme

1. Canlı lumera_beauty_db üzerine doğrudan import yapmayın. Yeni ve açıkça test amaçlı ayrı bir veritabanı oluşturun; mevcut hedef varsa işlemi durdurun.
2. MariaDB istemcisine özel seçenek dosyasıyla bağlanın, yalnızca test hedefini seçin ve istemci `SOURCE C:/secure/backups/lumera-YYYYMMDD-HHMM.sql` komutuyla aktarın. Önce dump içinde USE/CREATE DATABASE satırlarının canlı DB'yi seçmediğini inceleyin. Test ortamında MAIL_DRIVER=disabled kullanın; testten gerçek alıcıya e-posta göndermeyin.
3. Tablo/foreign key sayısını, kritik satır sayılarını, kullanıcı hash'lerini ve dosya bağlantılarını karşılaştırın. Oturumlar ve rate-limit dosyaları taşınmaz. Yedekteki .env anahtarını yalnızca kontrollü test ortamına alın.
4. Uygulama bağlantısını ayrı test hedefinde doğrulayın. Canlıya geçiş için doğrulanmış yedek, bakım penceresi, geri dönüş planı ve açık operatör onayı gerekir. Bu aşamada canlı veritabanında geri yükleme veya sıfırlama yapılmadı.

## Güvenlik ve denetim

Yeni migration 005_system_integration.sql `bin/upgrade-system.php --apply` ile ayrı migration hesabında çalışır; DROP/TRUNCATE içermez ve tekrar çalıştırılabilir. SMTP sırları yönetici ekranında düzenlenmez/gösterilmez. Sistem ekranı hatırlatma ayarlarını, kuyruk sayısını ve son 100 audit kaydını gösterir; eski audit kayıtları saklanır. Denetim verisi yalnızca aktör, işlem, hedef ve sonuç içerir; gönderilen form içeriği saklanmaz. Bu temel DB denetimi dışarıdaki değiştirilemez SIEM kayıt sistemi değildir.

Oturum cookie'leri HttpOnly/SameSite=Lax, HTTPS üzerinde Secure; strict mode, girişte yenileme, 30 dakika boşta ve 8 saat mutlak süre sınırı vardır. Parola değişikliği ve yönetici şifre sıfırlaması oturum sürümünü değiştirir. CSRF ve rol denetimi bütün panellerde korunur. Kritik hesap işlemleri 5/15 dakika, panel işlemleri rota/kullanıcı başına 60/15 dakika ve IP kovasıyla sınırlandırılır. SMTP test modu yalnızca loopback ve example.invalid alıcılarını kabul eder.

Bekleyen token/failed kuyruk ve audit verilerini doğrudan silen otomatik bakım işi eklenmedi. Saklama politikası belirlenince geçmişi koruyan ayrı bakım işlemi kullanılmalıdır.
