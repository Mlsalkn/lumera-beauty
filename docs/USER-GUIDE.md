# Kullanım kılavuzu

## Müşteri

Üye ol → ad/soyad, e-posta, telefon ve güçlü parola → Giriş yap. Hizmetleri public katalogda veya panelin Randevu al ekranında inceleyin. Hizmet, uygun uzman, tarih/saat ve özeti seçip tek onayla randevu oluşturun. Bekleyen durumu yönetici/izinli çalışan onayını bekler. Randevularım → detay; süre kuralları uygunsa tarih değiştir / iptal. Müsaitlik tekrar sunucuda doğrulanır. Favoriler ve uygulama içi bildirimler kendi hesabınıza aittir. Profilde fotoğraf/kişisel izinli alanlar ve parola güncellenir; e-posta değişimi SMTP yapılandırmasını gerektirir. Tamamlanan randevu Değerlendirmelerim'den bir kez değerlendirilir, aynı randevu seçilerek düzenlenebilir. Yeni/düzenlenen yorum moderasyon bekler; yayınlandığında adın yalnızca baş harfi gösterilir.

## Çalışan

Giriş → bugünkü/yaklaşan kendi randevuları, haftalık/aylık performans ve bildirimler. Randevular tarih/duruma göre filtrelenir. İzin varsa onay, bitiş geçtiğinde tamamlama; iptal yetkisi talep oluşturur ve yönetici karar verir. Başka çalışanın randevu kimliği erişim sağlamaz. Takvim gün/hafta/ay görünür; saat/mola/izin ve boş zamanları birlikte gösterir. İzin başlangıç/bitiş/açıklamasıyla talep edilir. Kendi profil fotoğrafı, ad, telefon, biyografi değişir; hizmet ve saat atamalarını yönetici yapar.

## Yönetici

Dashboard gerçek verilerden hesaplanır. Hizmet formunda kategori, fiyat, süre, açıklama, görsel ve aktiflik vardır. Kullanılmış hizmetin silinmesi ilişkileri bozmaz; sistem güvenli pasifleştirmeyi kullanır. Çalışan oluşturma/düzenleme; hizmet ataması, gün/saat, mola, izin ve işlem izinleri aynı randevu kurallarına bağlıdır. Çakışan plan/izin değişikliğinde gösterilen randevuları açıkça çözmeden onay verilemez. Müşteri arama, filtre, detay/geçmiş ve aktiflik yönetilir. Randevu listesi, takvim ve uygun slot ile kayıt; durum geçişleri doğrulanır. İptal ilgili müşteri/çalışan bildirimine yansır. Raporlar gerçekleşmiş tahsilat değil, completed randevu hizmet fiyatı snapshot toplamını tahmini hizmet tutarı olarak gösterir. Değerlendirmeler'de yayınla/gizle; Sistem ayarları'nda hatırlatma ve iletişim telefonu düzenlenir.

## Sunum akışı

1. Public Bakımlar/Uzmanlar ve mobil menüyü gösterin.
2. Kendi oluşturacağınız sentetik müşteriyle kayıt/giriş ve uygun randevu oluşturma.
3. Yönetici panelinde aynı randevuyu görüp onaylama; çalışan panelinde atamanın görünmesi.
4. Aynı uzman/saat çakışmasının reddi; müşteri tarih değiştirme veya iptal ve bildirimler.
5. İzin çakışmasını ve başka hesaba 403 erişim reddini gösterin.
6. Tamamlanmış sentetik işlem değerlendirmesi, moderasyon ve tahmini performans raporu.

Teslim test hesapları temizlenmiştir; pakette giriş parolası veya hazır kullanıcı yoktur. Gerçek kullanıcı hesabı üzerinde sunum için durum/parola değişikliği yapmayın. Gerçek SMTP olmadan posta gönderimi sunumu yapılmaz; loopback SMTP testinin sonucu raporda açıklanır.
