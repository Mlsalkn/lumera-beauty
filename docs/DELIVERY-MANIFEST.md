# Teslim kapsamı

Dahil: uygulama kaynakları, public yerel varlıklar, Composer lock/vendor, migrations ve veri içermeyen tam SQL şeması, boş runtime dizinleri, .env.example, .htaccess, README, bakım/kurulum/kullanım/test belgeleri, gerçek şemadan üretilen ER ve UML/akış kaynakları, yalnız sentetik son test ekran görüntüleri.

Hariç: gerçek .env, kullanıcı fotoğrafları/yüklemeler, sessions, loglar, rate-limit sayaçları, gerçek DB yedekleri/rows, önceki aşama gerçek kullanıcı bilgisi içerebilecek raporlar, XAMPP kurucusu, geliştirme araçları ve geçici test fixture dosyaları. Bu dosyalar mevcut çalışan projeden silinmedi; teslim paketine alınmadı.

Geçici son test sunucusu, test DB/kullanıcısı, cookie/log/SMTP capture dosyaları kendi finally sürecinde kaldırılır. Mevcut projedeki storage/logs/php.log bakım kaydıdır; silinmemiştir. CWD work/backups kaynak geri dönüş kopyaları, work/tools kurulum araçları ve eski outputs çalışma çıktıları sunum paketi dışında tutulur. Bunlar kullanıcı izni olmadan topluca silinmez.

Paket bütünlüğü SHA256SUMS.txt ve ZIP SHA256 dosyasıyla kontrol edilir. ZIP içinde gerçek .env veya kullanıcı verisi yoktur. Tam şema kurulum komutu mevcut tablo varsa durur; teslim kopyası mevcut kurulumun üzerine otomatik yüklenmez.

Eski DashboardController ve admin/index.php, customer/index.php, employee/index.php başlangıç şablonları aktif Router tarafından kullanılmaz. Mevcut dosyalara zarar vermemek için tutuldu; canlı rota /dashboard görünümlerini kullanır. Tamamlanmamış aktif panel olarak değerlendirilmez.
