<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{Database,Password};use App\Models\AdminRepository as R;
final class SampleContentService {
 public function seed():array {
  $pdo=Database::connection();$lock=R::one("SELECT GET_LOCK('lumera_sample_content',10) n");if((int)$lock['n']!==1)throw new \RuntimeException('Seed kilidi alınamadı.');
  $result=['services'=>0,'employees'=>0,'assignments'=>0];
  $catalog=[
   ['cut','Saç Kesimi ve Şekillendirme','Saç kesimi ve şekillendirme',60,'950.00','Yüz hatlarına uygun kesim, fön ve günlük şekillendirme önerileri.'],
   ['color','Kişiye Özel Saç Renklendirme','Saç boyama',150,'2800.00','Renk danışmanlığı, saç yapısına uygun boyama ve bitiş bakımı.'],
   ['makeup','Işıltılı Profesyonel Makyaj','Profesyonel makyaj',75,'2200.00','Ten hazırlığı ve yüz hatlarını tamamlayan özel gün makyajı.'],
   ['skin','Nemlendirici Cilt Ritüeli','Cilt bakımı',75,'1750.00','Nazik temizleme, bakım maskesi ve nem desteği ile kişisel bakım ritüeli.'],
   ['nails','Klasik Manikür ve El Bakımı','Manikür',45,'750.00','Tırnak şekillendirme, el bakımı ve isteğe uygun renk uygulaması.'],
   ['feet','Konfor Pedikürü','Pedikür',60,'950.00','Ayak ve tırnak bakımı, şekillendirme ve nemlendirici bitiş.'],
   ['lashes','Kaş Tasarımı ve Kirpik Bakımı','Kaş ve kirpik bakımı',60,'1300.00','Yüz oranlarına uygun kaş şekillendirme ve kirpik bakım uygulaması.'],
   ['bridal','Gelin Makyajı Deneyimi','Gelin makyajı',120,'4500.00','Gelin stiline uygun ten, göz ve dudak makyajı; kalıcı bitiş hazırlığı.'],
   ['haircare','Yoğun Nem Saç Bakımı','Saç bakımı',60,'1400.00','Saçın ihtiyacına uygun bakım maskesi, nem desteği ve şekillendirme.']
  ];
  $workers=[
   ['asli','Aslı Demir','Saç Tasarımı Uzmanı','Doğal hareketli kesimler ve kişiye uygun saç şekillendirme üzerinde çalışır.',['cut','color','haircare']],
   ['melis','Melis Kaya','Renk ve Saç Bakımı Uzmanı','Saç rengini ve bakım rutinini birlikte planlayan özenli bir yaklaşım benimser.',['color','cut','haircare']],
   ['selen','Selen Arslan','Makyaj Uzmanı','Ten görünümünü öne çıkaran zarif özel gün ve gelin makyajları tasarlar.',['makeup','bridal']],
   ['ipek','İpek Yıldız','Cilt Bakımı Uzmanı','Nazik uygulamalar ve nem odaklı kişisel bakım ritüelleri sunar.',['skin','lashes']],
   ['derya','Derya Acar','El ve Ayak Bakımı Uzmanı','Tırnak şekillendirme, manikür ve pedikürde titiz bir bakım deneyimi sunar.',['nails','feet']],
   ['ece','Ece Yalçın','Kaş ve Kirpik Uzmanı','Doğal görünümü koruyan kaş tasarımı ve kirpik bakımı üzerine odaklanır.',['lashes','makeup']],
   ['buse','Buse Şahin','Gelin Hazırlık Uzmanı','Gelin makyajı ve saç şekillendirmeyi uyumlu bir hazırlık planında birleştirir.',['bridal','makeup','cut']],
   ['nilay','Nilay Koç','Bakım Ritüelleri Uzmanı','Cilt, el ve saç bakımında sakin ve kişiye özel bir deneyim sunar.',['skin','nails','haircare']]
  ];
  try{$pdo->beginTransaction();$ids=[];
   foreach($catalog as$i=>[$key,$name,$category,$duration,$price,$description]){
    $record=R::one("SELECT entity_id FROM sample_content WHERE entity_type='service' AND seed_key=?",[$key]);
    if($record){$id=(int)$record['entity_id'];if(!R::one('SELECT id FROM services WHERE id=?',[$id]))throw new \DomainException('Örnek hizmet kaldırılmış; seed mevcut yönetici kararını değiştirmedi.');}
    else{if(R::one('SELECT id FROM services WHERE name=?',[$name]))throw new \DomainException('Örnek hizmet adı mevcut kayıtla çakışıyor; kayıt değiştirilmedi.');R::execute('INSERT INTO services(name,category,description,duration_minutes,price,image_path,is_active) VALUES (?,?,?,?,?,?,1)',[$name,$category,$description,$duration,$price,'assets/images/sample/service-'.($i+1).'.svg']);$id=(int)$pdo->lastInsertId();R::execute("INSERT INTO sample_content VALUES ('service',?,?)",[$key,$id]);$result['services']++;}$ids[$key]=$id;
   }
   foreach($workers as$i=>[$key,$name,$title,$bio,$services]){
    $record=R::one("SELECT entity_id FROM sample_content WHERE entity_type='employee' AND seed_key=?",[$key]);
    if($record){if(!R::one('SELECT id FROM employees WHERE id=?',[$record['entity_id']]))throw new \DomainException('Örnek çalışan kaldırılmış; seed tekrar oluşturmadı.');continue;}
    $email='sample-'.$key.'@example.invalid';if(R::one('SELECT id FROM users WHERE email=?',[$email]))throw new \DomainException('Örnek çalışan adresi mevcut hesapla çakışıyor.');
    R::execute("INSERT INTO users(name,email,password_hash,role,is_active) VALUES (?,?,?,'EMPLOYEE',1)",[$name,$email,Password::hash(bin2hex(random_bytes(32)))]);$user=(int)$pdo->lastInsertId();
    R::execute('INSERT INTO employees(user_id,title,bio,photo_path,is_active) VALUES (?,?,?,?,1)',[$user,$title,$bio,'assets/images/sample/expert-'.($i+1).'.svg']);$employee=(int)$pdo->lastInsertId();
    R::execute("INSERT INTO sample_content VALUES ('employee',?,?)",[$key,$employee]);R::execute('INSERT INTO employee_permissions(employee_id) VALUES (?)',[$employee]);
    foreach(range(2,7)as$weekday){R::execute('INSERT INTO working_hours(employee_id,weekday,start_time,end_time) VALUES (?,?,?,?)',[$employee,$weekday,'09:00:00','18:00:00']);R::execute('INSERT INTO employee_breaks(employee_id,weekday,start_time,end_time) VALUES (?,?,?,?)',[$employee,$weekday,'13:00:00','14:00:00']);}
    foreach($services as$service){R::execute('INSERT INTO employee_services(employee_id,service_id) VALUES (?,?)',[$employee,$ids[$service]]);$result['assignments']++;}$result['employees']++;
   }$pdo->commit();return$result;
  }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}finally{R::one("SELECT RELEASE_LOCK('lumera_sample_content') n");}
 }
}
