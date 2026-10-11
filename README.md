# Luméra Beauty

**Melisa Alkan** tarafından geliştirilen güzellik merkezi yönetimi ve online randevu sistemi.

Luméra Beauty; müşteri, çalışan ve yönetici süreçlerini tek bir web uygulamasında birleştirir. Krem ve adaçayı yeşili tonlarındaki responsive arayüz, katmanlı PHP MVC mimarisi ve MariaDB/MySQL veritabanıyla çalışır.

## Özellikler

- **Müşteri paneli:** üyelik, giriş, hizmet ve uzman seçimi, uygun saatleri görüntüleme, randevu oluşturma ve geçmiş takibi.
- **Yönetici paneli:** hizmet, çalışan, müşteri ve randevu yönetimi; çalışma takvimi, izin talepleri, raporlar, sistem ayarları ve destek talepleri.
- **Çalışan paneli:** atanmış randevular, çalışma takvimi, izin talepleri, profil ve performans bilgileri.
- **Değerlendirmeler:** tamamlanan randevular için yorum ve yönetici moderasyonu.
- **Bildirimler:** uygulama içi bildirimler ve yapılandırılabilir SMTP e-posta altyapısı.
- **Güvenlik:** rol bazlı erişim, PDO prepared statements, CSRF koruması, sunucu tarafı doğrulama, güvenli parola saklama ve hız sınırlaması.

## Teknolojiler

PHP 8.2 · MariaDB/MySQL · PDO · HTML5 · CSS3 · JavaScript · Bootstrap · PHPMailer · Composer · XAMPP

Backend katmanlı ve modüler MVC yapısındadır. Node.js uygulamayı çalıştırmak için gerekli değildir.

## Kurulum

1. PHP 8.2, Composer, Apache ve MariaDB/MySQL ortamını hazırlayın. Windows için XAMPP kullanılabilir.
2. Depoyu Apache'nin web dizinindeki `lumera-beauty` klasörüne klonlayın.
3. Proje dizininde bağımlılıkları kurun:

   ```bash
   composer install --no-dev
   ```

4. `.env.example` dosyasından yerel `.env` oluşturun. Uygulama adresini ve veritabanı bağlantı bilgilerini kendi ortamınıza göre düzenleyin. Varsayılan temiz kurulum veritabanı `lumera_beauty_db` adındadır.
5. Ayrı kurulum hesabı için `MIGRATION_DB_USERNAME` ve `MIGRATION_DB_PASSWORD` ortam değişkenlerini ayarlayın. Yeni, boş veritabanına şemayı kurun:

   ```bash
   php bin/install-schema.php --empty-only
   php bin/seed-content.php --sample-content
   ```

   Kurulum komutu mevcut tablolar bulursa durur. Çalışan bir veritabanında temiz kurulum komutunu kullanmayın. Güncelleme ve ayrıntılı bağlantı adımları için [kurulum kılavuzuna](docs/INSTALLATION.md) bakın.

6. Windows PowerShell üzerinden yönetici hesabını oluşturun:

   ```powershell
   .\bin\create-admin.ps1
   ```

   Ad, e-posta ve parola güvenli şekilde istenir. Hazır yönetici hesabı veya sabit parola bulunmaz.

7. Apache ve MariaDB'yi başlatın. Varsayılan adres: [http://localhost/lumera-beauty/public/](http://localhost/lumera-beauty/public/). Farklı bir klasör adı kullanırsanız `APP_URL` ve Apache yönlendirme yollarını da uyarlayın.

Uygulama veritabanı hesabını yalnız gereken CRUD yetkileriyle kullanın; kurulum hesabını günlük uygulama hesabından ayrı tutun. GitHub deposunda `vendor/` bulunmaz; bağımlılıklar Composer ile yüklenir.

## Örnek içerik

Seed komutu **8 kurgusal uzman, 9 hizmet ve 20 uzman–hizmet eşleşmesi** oluşturur. Tekrar çalıştırıldığında kopya kayıt eklemez. Uzmanlar çalışma saatleriyle randevu sistemine bağlıdır.

Uzman görselleri özgün illüstrasyonlardır. Gösterilen fiyatlar örnek katalog değerleridir; gerçek işletme tarifesi değildir. Veritabanı şeması 24 tablo içerir ve dışa aktarılan SQL dosyasında kullanıcı verisi bulunmaz.

## Testler

Temel kontroller:

```bash
php tests/run.php
php tests/database.php
```

Yönetici ve müşteri HTTP senaryoları:

```bash
php tests/admin-http.php --allow-test-data
php tests/customer-http.php --allow-test-data
```

Veri değiştiren testleri ayrı test ortamında, sırayla çalıştırın. Testler kendi geçici kayıtlarını temizler. SMTP testlerinde gerçek alıcı kullanmayın. Test ortamı, ek gruplar ve doğrulama sınırları [test raporunda](docs/TEST-REPORT.md) açıklanmıştır; kaynak sürümün geçmiş sonuçları güncel kopyada tekrar çalıştırılmış sonuçlar olarak değerlendirilmemelidir.

## E-posta ve kullanım sınırları

Varsayılan `MAIL_DRIVER=disabled` ayarında gerçek e-posta gönderilmez; uygulama içi bildirimler çalışır. Destek formu talepleri veritabanına kaydeder, e-posta gönderilmiş gibi başarı mesajı göstermez.

Gerçek e-posta gönderimi için SMTP; canlı yayın için HTTPS, alan adı ve zamanlayıcı yapılandırması gerekir. Ödeme/tahsilat entegrasyonu yoktur; hizmet fiyatlarına dayalı raporlar gerçek tahsilat olarak sunulmaz.

## Dokümantasyon

- [Mimari, ER diyagramı, kullanım senaryoları ve güvenlik](docs/PROJECT-DOCUMENTATION.md)
- [Veritabanı sözlüğü](docs/DATABASE-DICTIONARY.md)
- [Kurulum kılavuzu](docs/INSTALLATION.md)
- [Kullanım kılavuzu](docs/USER-GUIDE.md)
- [Test raporu](docs/TEST-REPORT.md)
- [Dış servis yapılandırmaları](docs/EXTERNAL-CONFIGURATION.md)
- [Bakım ve yedekleme](MAINTENANCE.md)

## İletişim

**Melisa Alkan** · [alkanmelisa12@gmail.com](mailto:alkanmelisa12@gmail.com)

Parolalar, özel `.env` dosyaları, gerçek kullanıcı verileri, oturumlar ve kullanıcı yüklemeleri depoya dahil edilmez.
