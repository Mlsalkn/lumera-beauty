# Kurulum ve taşınabilirlik

## Gereksinimler

Windows XAMPP, PHP 8.2+, Apache mod_rewrite / AllowOverride All, MariaDB 10.4+; pdo_mysql, mbstring, openssl, fileinfo. HTTP testlerinde PHP curl, proc_open ve CLI curl gerekir. PHP geliştirme sunucusu Apache erişim kurallarının yerine geçmez. Composer lock kesin bağımlılık sürümünü korur; pakette vendor vardır. Yeniden kurulumda composer install --no-dev kullanılabilir.

## Yeni bilgisayarda boş kurulum

1. Paketin lumera-beauty klasörünü C:\xampp\htdocs\ altına yerleştirin. Mevcut aynı isimli klasörün üzerine körlemesine kopyalamayın.
2. Apache ve MariaDB'yi başlatın. .env.example → .env kopyası oluşturun. DB_DATABASE=lumera_beauty_db, APP_URL=http://localhost/lumera-beauty/public. Gerçek DB kullanıcı/parolasını yalnızca .env dosyasında sağlayın. MAIL_DRIVER=disabled ile başlayın; gerçek admin parolası örnek dosyada yoktur.
3. Ayrı migration hesabını MIGRATION_DB_USERNAME / MIGRATION_DB_PASSWORD ortamında sağlayın. Parolaları komut satırı argümanlarına yazmayın. Proje kökünde php bin/install-schema.php --empty-only çalıştırın. Bu komut yalnızca lumera_beauty_db yeni/boşken tam 24 tabloluk şemayı kurar. Herhangi bir tablo varsa exit 2 ile durur, tabloları değiştirmez. DDL otomatik commit olduğundan yarıda kalan yeni kurulum incelenmeden tekrarlanmaz.
4. Alternatif: yeni BOŞ seçili veritabanına database/export/lumera-schema.sql içe aktarın. Dosya yalnızca yapı içerir; kullanıcılar, randevular, gerçek hizmetler, parolalar veya sayaç değerleri içermez. Eski database/schema.sql ilk aşama şemasıdır; tam teslim için export dosyasını kullanın. Tam şema kurulunca eski upgrade komutlarını sırf kurulum için çalıştırmanız gerekmez.
5. Uygulama DB hesabına yalnızca bu veritabanında SELECT / INSERT / UPDATE / DELETE verin. Migration hesabını ortamdan kaldırın; root uygulama hesabı olarak kullanılmasın.
6. php bin/diagnose.php ile bağlantıyı kontrol edin. bin/create-admin.ps1 interaktif komutuyla kendi ad/e-posta ve iki kez gizli girilen güçlü parola ile ADMIN oluşturun. Aynı e-posta varsa hesabı veya rolünü değiştirmez. Sabit örnek hesap yoktur.
7. İsteğe bağlı php bin/seed.php --sample-services statik örnek hizmetleri yalnızca eksikse ekler. Bunlar gerçek işletme fiyatı değildir. Çalışanları panelden kendi seçtiğiniz güçlü parolalarla oluşturun; hizmetleri ve çalışma günlerini atayın.
8. http://localhost/lumera-beauty/public/login adresini açın. storage/logs, sessions, rate_limits ve uploads PHP tarafından yazılabilir olmalı; dizinler pakette boş işaretleyicilerle bulunur. Kaynak/.env HTTP ile erişilemez.

## Var olan bilgisayarda kullanım

Mevcut lumera_beauty_db ve yönetici hesabı korunmuştur. Bu bilgisayarda şema kurulumu tekrar gerekmez. Apache/MariaDB XAMPP Control Panel ile başlatılır; Windows otomatik servisi veya e-posta görevi bu teslimde etkinleştirilmemiştir. Kök .htaccess public'e yönlendirir; public dışına erişimi engeller. DocumentRoot doğrudan public olacaksa APP_URL ve Apache Directory erişim kurallarını birlikte doğrulayın.

## SMTP ve şifreli kuyruk

Gerçek sağlayıcı olmadan MAIL_DRIVER=disabled kalmalıdır. Smtp için MAIL_DRIVER=smtp, SMTP_HOST/PORT/ENCRYPTION/USERNAME/PASSWORD ve doğrulanmış MAIL_FROM_ADDRESS gerekir. MAIL_ENCRYPTION_KEY 32 rastgele byte'ın base64 karşılığıdır; php -r "echo base64_encode(random_bytes(32)), PHP_EOL;" ile üretilip yerel .env içine yazılır. Mevcut kuyruğu çözmek için aynı anahtar gereklidir; anahtarı körlemesine değiştirmeyin. EMAIL_REQUIRE_VERIFICATION yalnızca gerçek teslim denenince etkinleştirilir. Kayıt doğrulama zorunlu değilken SMTP eksikliği randevu/panel işlevlerini durdurmaz.

## Bakım / yedekleme

MAINTENANCE.md ayrıntılı yedek/geri yükleme ve posta komutlarını içerir. Gerçek veri yedeği teslim paketine konmaz. Bakımda önce yetkili şifreli veri yedeği ve kullanıcı görselleri, ayrıca ayrı güvenli .env/posta anahtarı yedeği alınır. Geri yükleme önce ayrı boş veritabanında denenir; mevcut DB üzerine otomatik DROP/TRUNCATE yapılmaz. Bu aşamada tam gerçek veri yedeği veya gerçek veriye restore uygulanmamıştır. Yalnızca 24 tabloluk yapının boş izole DB'ye kurulumu test edilmiştir.

## Yeni örnek içerik

Tam boş şemadan sonra `php bin/seed-content.php --sample-content` çalıştırın. Mevcut şemada önce `php bin/upgrade-content.php` gerekir. Migration kimliği uygulama CRUD hesabından ayrı tutulmalıdır. Seed 9 hizmet ve 8 uzman oluşturur; sabit parola veya yönetici hesabı oluşturmaz. Çalışan giriş parolasını yönetici panelinden ayrıca belirleyin. Mevcut yönetici adresi güncelleme komutu `php bin/update-admin-email.php --check`, ardından açıkça istenirse `--apply`; tam olarak bir yönetici ve adres çakışmaması gerekir. Bu işlem temiz kurulumun otomatik parçası değildir.
