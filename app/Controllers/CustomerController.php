<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{AccessDenied,Csrf,Input,Session,View};
use App\Middleware\{AuthMiddleware,RoleMiddleware};
use App\Models\AdminRepository as R;
use App\Services\{CustomerAppointmentService as Booking,CustomerProfileService,EmailChangeService};
final class CustomerController
{
 private function authorize():?array{$user=AuthMiddleware::user();return $user&&RoleMiddleware::authorize($user,'CUSTOMER')?$user:null;}
 private function fail(\Throwable $error):void{http_response_code($error instanceof AccessDenied?403:422);View::render('errors/error',['title'=>$error instanceof AccessDenied?'403':'İşlem tamamlanamadı','message'=>$error->getMessage()]);}
 public function page(string $page):void
 {
  if($page==='book'){$visitor=\App\Services\AuthService::current();if($visitor&&in_array($visitor['role'],['ADMIN','EMPLOYEE'],true)){header('Location: '.\booking_url(),true,303);return;}}
  $user=$this->authorize();if(!$user){return;}$id=(int)$user['id'];$data=['user'=>$user,'page'=>$page,'title'=>'Hesabım · Luméra'];$booking=new Booking();
  try{
   $data['unread']=(int)R::one('SELECT COUNT(*) AS n FROM notifications WHERE customer_id=? AND read_at IS NULL',[$id])['n'];
   switch($page){
    case 'dashboard':
     $data['stats']=R::one("SELECT COUNT(*) AS total,COALESCE(SUM(status='pending'),0) AS pending,COALESCE(SUM(status='cancelled'),0) AS cancelled,COALESCE(SUM(end_at<?),0) AS past,COALESCE(SUM(start_at>=? AND status IN ('pending','confirmed')),0) AS upcoming FROM appointments WHERE customer_id=?",[date('Y-m-d H:i:s'),date('Y-m-d H:i:s'),$id]);
     $data['upcoming']=R::appointments("a.customer_id=? AND a.start_at>=? AND a.status IN ('pending','confirmed')",[$id,date('Y-m-d H:i:s')],4,true);
     $data['past']=R::appointments('a.customer_id=? AND a.end_at<?',[$id,date('Y-m-d H:i:s')],3);
     $data['favorites']=R::rows('SELECT s.* FROM favorites f JOIN services s ON s.id=f.service_id WHERE f.customer_id=? ORDER BY f.created_at DESC LIMIT 4',[$id]);
     $data['notifications']=R::rows('SELECT * FROM notifications WHERE customer_id=? ORDER BY id DESC LIMIT 4',[$id]);break;
    case 'appointments':
     $filter=Input::text($_GET,'filter',12,false)?:'upcoming';$where='a.customer_id=?';$values=[$id];
     switch($filter){case 'upcoming':$where.=" AND a.start_at>=? AND a.status IN ('pending','confirmed')";$values[]=date('Y-m-d H:i:s');break;case 'past':$where.=' AND a.end_at<=?';$values[]=date('Y-m-d H:i:s');break;case 'pending':case 'confirmed':case 'completed':case 'cancelled':$where.=' AND a.status=?';$values[]=$filter;break;case 'all':break;default:throw new \DomainException('Geçersiz filtre.');}
     $data['appointments']=R::appointments($where,$values,null,$filter==='upcoming');$data['filter']=$filter;break;
    case 'detail':
     $data['appointment']=$booking->owned($id,Input::id($_GET['id']??null));
     $data['history']=R::rows('SELECT h.* FROM appointment_status_history h JOIN appointments a ON a.id=h.appointment_id WHERE a.id=? AND a.customer_id=? ORDER BY h.id',[$data['appointment']['id'],$id]);break;
    case 'favorites':$data['favorites']=R::rows('SELECT s.* FROM favorites f JOIN services s ON s.id=f.service_id WHERE f.customer_id=? ORDER BY f.created_at DESC',[$id]);break;
    case 'notifications':$data['notifications']=R::rows('SELECT * FROM notifications WHERE customer_id=? ORDER BY id DESC LIMIT 200',[$id]);break;
    case 'profile':$data['preferences']=R::one('SELECT * FROM customer_preferences WHERE customer_id=?',[$id]);$data['mailConfigured']=EmailChangeService::configured();break;
    case 'reviews':$data['completed']=R::appointments("a.customer_id=? AND a.status='completed'",[$id],null);$data['reviews']=R::rows('SELECT r.*,s.name service_name FROM reviews r JOIN appointments a ON a.id=r.appointment_id JOIN services s ON s.id=a.service_id WHERE r.customer_id=? ORDER BY r.id DESC',[$id]);break;
    case 'email-verify':$data['token']=Input::text($_GET,'token',64,false);header('Referrer-Policy: no-referrer');break;
    case 'book':$data+=$this->wizard($id,$booking);break;
    default:throw new \DomainException('Sayfa bulunamadı.');
   }
  }catch(AccessDenied|\DomainException $error){$this->fail($error);return;}
  View::render('customer/'.$page,$data);
 }
 private function wizard(int $customer,Booking $booking):array
 {
  $step=isset($_GET['step'])?Input::id($_GET['step']):1;if($step>5){throw new \DomainException('Geçersiz adım.');}
  $data=['step'=>$step,'reschedule'=>null];
  if($step===5){$draft=$_SESSION['booking_draft']??null;if(!is_array($draft)||($draft['customer_id']??null)!==$customer||($draft['expires']??0)<=time()){throw new \DomainException('Özetin süresi doldu. Randevu seçimini tekrar başlatın.');}$data['draft']=$draft;return $data;}
  if(isset($_GET['reschedule'])){
   $existing=$booking->owned($customer,Input::id($_GET['reschedule']));if(!$booking->canModify($existing,'reschedule')){throw new \DomainException('Bu randevu için tarih değiştirme süresi dolmuş.');}
   $data['reschedule']=$existing;$data['step']=$step=max(3,$step);$service=(int)$existing['service_id'];$employee=(int)$existing['employee_id'];
  }else{$service=$step>1?Input::id($_GET['service_id']??null):0;$employee=$step>2?(($_GET['employee_id']??'')==='any'?0:Input::id($_GET['employee_id']??null)):0;}
  if($step===1){$category=Input::text($_GET,'category',100,false);$data['category']=$category;$data['services']=R::rows('SELECT * FROM services WHERE is_active=1'.($category!==''?' AND category=?':'').' ORDER BY category,name,id',$category!==''?[$category]:[]);$data['categories']=R::rows('SELECT DISTINCT category FROM services WHERE is_active=1 ORDER BY category');$data['favoriteIds']=array_map('intval',array_column(R::rows('SELECT service_id FROM favorites WHERE customer_id=?',[$customer]),'service_id'));return $data;}
  $item=R::one('SELECT * FROM services WHERE id=? AND is_active=1',[$service]);if(!$item){throw new \DomainException('Hizmet bulunamadı.');}$data['service']=$item;$data['workers']=$booking->workers($service);$data['employee']=$employee;
  if($step===2){
   foreach($data['workers'] as &$worker){
    $worker['today_slots']=(new \App\Services\AvailabilityService())->slots((int)$worker['id'],$service,date('Y-m-d'));
    $worker['services']=R::rows('SELECT s.name FROM employee_services es JOIN services s ON s.id=es.service_id WHERE es.employee_id=? AND s.is_active=1 ORDER BY s.name',[$worker['id']]);
   }unset($worker);return $data;
  }
  if($employee&&!in_array($employee,array_map('intval',array_column($data['workers'],'id')),true)){throw new \DomainException('Çalışan bu hizmet için uygun değil.');}
  if($step===3){
   $month=Input::text($_GET,'month',7,false)?:date('Y-m');$start=Input::date($month.'-01');$last=new \DateTimeImmutable('today +'.(require BASE_PATH.'/config/appointments.php')['booking_days'].' days');
   if($start>$last||$start->modify('+1 month')<=new \DateTimeImmutable('today')){throw new \DomainException('Takvim ayı rezervasyon aralığının dışında.');}
   $data+=['month'=>$month,'monthStart'=>$start,'lastDay'=>$last,'days'=>[]];
   for($day=$start;$day<$start->modify('+1 month');$day=$day->modify('+1 day')){
    $valid=$day>=new \DateTimeImmutable('today')&&$day<=$last;$slots=$valid?$booking->slots($customer,$service,$employee,$day->format('Y-m-d'),$data['reschedule']['id']??null):[];$data['days'][]=['date'=>$day,'count'=>count($slots)];
   }
  }elseif($step===4){$date=Input::text($_GET,'date',10);$data['date']=$date;$data['slots']=$booking->slots($customer,$service,$employee,$date,$data['reschedule']['id']??null);}
  return $data;
 }
 public function mutate(string $action):void
 {
  $user=$this->authorize();if(!$user){return;}$id=(int)$user['id'];
  if(!Csrf::verify($_POST['_csrf']??null)){http_response_code(403);View::render('errors/error',['title'=>'403','message'=>'Form güvenlik doğrulaması başarısız.']);return;}
  $destination=match($action){'review'=>'customer/reviews','profile','password','email-request','email-verify'=>'customer/profile','favorite'=>'customer/favorites','notification'=>'customer/notifications',default=>'customer/appointments'};
  try{
   $service=new Booking();$message='İşleminiz tamamlandı.';
   switch($action){
    case 'preview':
     if(isset($_POST['reschedule'])){
      $existing=$service->owned($id,Input::id($_POST['reschedule']));$date=Input::text($_POST,'date',10);$time=Input::text($_POST,'time',5);Input::datetime($date.' '.$time);
      $choices=$service->slots($id,(int)$existing['service_id'],(int)$existing['employee_id'],$date,(int)$existing['id']);if(empty($choices[$time])){throw new \DomainException('Bu saat artık müsait değil.');}
      $item=R::one('SELECT * FROM services WHERE id=?',[$existing['service_id']]);$item['price']=$existing['service_price'];$item['duration_minutes']=(int)((strtotime($existing['end_at'])-strtotime($existing['start_at']))/60);
      $_SESSION['booking_draft']=['customer_id'=>$id,'service'=>$item,'employee_id'=>(int)$existing['employee_id'],'workers'=>$service->workers((int)$existing['service_id']),'date'=>$date,'time'=>$time,'note'=>$existing['customer_note']??'','expires'=>time()+600,'nonce'=>bin2hex(random_bytes(24)),'reschedule'=>(int)$existing['id']];
     }else{$_SESSION['booking_draft']=$service->draft($id,$_POST);}
     header('Location: '.\url('customer/book?step=5'),true,303);return;
    case 'confirm':
     $draft=$_SESSION['booking_draft']??null;$nonce=$_POST['nonce']??null;
     if(!is_array($draft)||!is_string($nonce)||!hash_equals($draft['nonce'],$nonce)||$draft['customer_id']!==$id||$draft['expires']<=time()){throw new \DomainException('Özet geçersiz veya süresi dolmuş.');}
     if(isset($draft['reschedule'])){$bookingId=$draft['reschedule'];$service->reschedule($id,$bookingId,$draft['date'].' '.$draft['time']);$message='Randevunuzun tarihi değiştirildi; yeniden onay bekliyor.';}
     else{$bookingId=$service->confirm($id,$draft);$message='Randevunuz oluşturuldu. Randevu numaranız: #'.$bookingId;}
     unset($_SESSION['booking_draft']);$destination='customer/appointments/detail?id='.$bookingId;break;
    case 'cancel':$bookingId=Input::id($_POST['id']??null);$service->cancel($id,$bookingId);$message='Randevunuz iptal edildi.';$destination='customer/appointments/detail?id='.$bookingId;break;
    case 'profile':(new CustomerProfileService())->update($id,$_POST,$_FILES['photo']??null);$message='Profiliniz güncellendi.';break;
    case 'password':$_SESSION['auth_version']=(new CustomerProfileService())->changePassword($id,$_POST);Session::regenerate();unset($_SESSION['booking_draft']);$message='Şifreniz değiştirildi. Diğer oturumlarınız kapatıldı.';break;
    case 'email-request':(new EmailChangeService())->request($id,$_POST);$message='Doğrulama iletisi gönderim sistemine teslim edildi; adresiniz doğrulamadan değişmez.';break;
    case 'email-verify':$_SESSION['auth_version']=(new EmailChangeService())->verify($id,Input::text($_POST,'token',64));Session::regenerate();$message='E-posta adresiniz doğrulanarak değiştirildi.';break;
    case 'favorite':$add=Input::text($_POST,'add',1);if(!in_array($add,['0','1'],true)){throw new \DomainException('Geçersiz favori işlemi.');}$service->favorite($id,Input::id($_POST['service_id']??null),$add==='1');break;
    case 'review':(new \App\Services\ReviewService())->save($id,$_POST);$message='Değerlendirmeniz kaydedildi ve incelemeye alındı.';break;
    case 'notification':
     $notification=Input::id($_POST['id']??null);if(!R::one('SELECT id FROM notifications WHERE id=? AND customer_id=?',[$notification,$id])){throw new AccessDenied('Bu bildirime erişim yetkiniz yok.');}
     R::execute('UPDATE notifications SET read_at=COALESCE(read_at,?) WHERE id=? AND customer_id=?',[date('Y-m-d H:i:s'),$notification,$id]);break;
    default:throw new \DomainException('İşlem bulunamadı.');
   }
   $_SESSION['customer_notice']=$message;
  }catch(AccessDenied $error){$this->fail($error);return;}
  catch(\DomainException $error){$_SESSION['customer_error']=$error->getMessage();if(in_array($action,['preview','confirm'],true)){$destination='customer/book';}}
  catch(\PDOException $error){if((int)($error->errorInfo[1]??0)!==1062){throw $error;}$_SESSION['customer_error']='Bu kayıt artık uygun değil veya adres kullanılıyor.';}
  header('Location: '.\url($destination),true,303);
 }
}
