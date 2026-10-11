<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{AccessDenied,Csrf,Input,View};
use App\Middleware\{AuthMiddleware,RoleMiddleware};
use App\Models\{AdminRepository as R,EmployeeRepository as E};
use App\Services\{AppointmentService,EmployeeOperationsService,EmployeeCalendarService,EmployeeProfileService};
final class EmployeeController
{
 private function authorize():?array{$user=AuthMiddleware::user();return$user&&RoleMiddleware::authorize($user,'EMPLOYEE')?$user:null;}
 private function fail(\Throwable$e):void{http_response_code($e instanceof AccessDenied?403:422);View::render('errors/error',['title'=>'İşlem tamamlanamadı','message'=>$e->getMessage()]);}
 public function page(string $page):void
 {
  $user=$this->authorize();if(!$user){return;}try{
   $employee=E::account((int)$user['id']);$id=(int)$employee['id'];$data=['user'=>$user,'employee'=>$employee,'page'=>$page,'title'=>'Çalışan · Luméra','permissions'=>E::permissions($id),'unread'=>(int)R::one('SELECT COUNT(*) n FROM notifications WHERE customer_id=? AND read_at IS NULL',[$user['id']])['n']];
   $today=new \DateTimeImmutable('today');$week=$today->modify('monday this week');$month=$today->modify('first day of this month');
   switch($page){
    case 'dashboard':
     $data['stats']=['day'=>E::stats($id,$today->format('Y-m-d'),$today->modify('+1 day')->format('Y-m-d')),'week'=>E::stats($id,$week->format('Y-m-d'),$week->modify('+7 days')->format('Y-m-d')),'month'=>E::stats($id,$month->format('Y-m-d'),$month->modify('+1 month')->format('Y-m-d'))];
     $data['today']=(new EmployeeCalendarService())->day($id,$today->format('Y-m-d'));$data['upcoming']=R::appointments("a.employee_id=? AND a.status IN ('pending','confirmed') AND a.start_at>=?",[$id,date('Y-m-d H:i:s')],4,true);$data['pending']=R::appointments("a.employee_id=? AND a.status='pending'",[$id],4,true);$data['completed']=R::appointments("a.employee_id=? AND a.status='completed'",[$id],4);$data['notifications']=R::rows('SELECT * FROM notifications WHERE customer_id=? ORDER BY id DESC LIMIT 4',[$user['id']]);break;
    case 'appointments':
     $date=Input::text($_GET,'date',10,false);$status=Input::text($_GET,'status',12,false);$where='a.employee_id=?';$values=[$id];if($date!==''){$day=Input::date($date);$where.=' AND a.start_at>=? AND a.start_at<?';$values[]=$date;$values[]=$day->modify('+1 day')->format('Y-m-d');}if($status!==''){if(!in_array($status,['pending','confirmed','completed','cancelled'],true)){throw new \DomainException('Geçersiz durum filtresi.');}$where.=' AND a.status=?';$values[]=$status;}$data+=['date'=>$date,'status'=>$status,'appointments'=>R::appointments($where,$values,null,true)];break;
    case 'detail':$data['appointment']=E::owned($id,Input::id($_GET['id']??null));$data['cancellations']=R::rows('SELECT * FROM appointment_cancellation_requests WHERE appointment_id=? AND employee_id=? ORDER BY id DESC',[$data['appointment']['id'],$id]);break;
    case 'calendar':$data+=(new EmployeeCalendarService())->range($id,Input::text($_GET,'mode',5,false)?:'week',Input::text($_GET,'date',10,false)?:date('Y-m-d'));break;
    case 'leaves':$data['requests']=R::rows('SELECT * FROM employee_leave_requests WHERE employee_id=? ORDER BY id DESC',[$id]);$data['leaves']=R::rows('SELECT * FROM employee_leaves WHERE employee_id=? ORDER BY start_at DESC',[$id]);break;
    case 'profile':case 'services':$data['services']=E::services($id);$data['hours']=E::hours($id);$data['breaks']=E::breaks($id);break;
    case 'performance':
     $from=Input::text($_GET,'from',10,false)?:date('Y-m-01');$to=Input::text($_GET,'to',10,false)?:date('Y-m-d');$start=Input::date($from);$end=Input::date($to)->modify('+1 day');if($end<=$start||$start->diff($end)->days>3660){throw new \DomainException('En fazla 10 yıllık geçerli aralık seçin.');}
     $data+=['from'=>$from,'to'=>$to,'totals'=>E::stats($id,$from,$end->format('Y-m-d')),'daily'=>R::rows("SELECT DATE(start_at) day,COUNT(*) total,COALESCE(SUM(status='completed'),0) completed,COALESCE(SUM(CASE WHEN status='completed' THEN service_price ELSE 0 END),0) estimated FROM appointments WHERE employee_id=? AND start_at>=? AND start_at<? GROUP BY DATE(start_at) ORDER BY day",[$id,$from,$end->format('Y-m-d')]),'completed'=>R::appointments("a.employee_id=? AND a.status='completed' AND a.start_at>=? AND a.start_at<?",[$id,$from,$end->format('Y-m-d')],null)];break;
    case 'notifications':$data['notifications']=R::rows('SELECT * FROM notifications WHERE customer_id=? ORDER BY id DESC LIMIT 200',[$user['id']]);break;
    default:throw new \DomainException('Sayfa bulunamadı.');
   }View::render('employee/'.$page,$data);
  }catch(AccessDenied|\DomainException$e){$this->fail($e);}
 }
 public function mutate(string $action):void
 {
  $user=$this->authorize();if(!$user){return;}if(!Csrf::verify($_POST['_csrf']??null)){http_response_code(403);View::render('errors/error',['title'=>'403','message'=>'Form güvenlik doğrulaması başarısız.']);return;}
  $destination=match($action){'profile'=>'employee/profile','leave'=>'employee/leaves','notification'=>'employee/notifications',default=>'employee/appointments'};
  try{$employee=E::account((int)$user['id']);$id=(int)$employee['id'];$message='İşleminiz tamamlandı.';
   switch($action){
    case 'status':$appointment=Input::id($_POST['id']??null);E::owned($id,$appointment);(new AppointmentService())->status($appointment,Input::text($_POST,'status',12),(int)$user['id'],$id);$destination='employee/appointments/detail?id='.$appointment;break;
    case 'cancellation':$appointment=Input::id($_POST['id']??null);(new EmployeeOperationsService())->requestCancellation($id,$appointment,Input::text($_POST,'reason',1000));$message='İptal talebi yöneticinin değerlendirmesine sunuldu. Randevu henüz iptal edilmedi.';$destination='employee/appointments/detail?id='.$appointment;break;
    case 'leave':(new EmployeeOperationsService())->requestLeave($id,$_POST);$message='İzin talebiniz yönetici onayı bekliyor. Takvim henüz kapatılmadı.';break;
    case 'profile':(new EmployeeProfileService())->update((int)$user['id'],$_POST,$_FILES['photo']??null);$message='İzin verilen profil alanlarınız güncellendi.';break;
    case 'notification':$notice=Input::id($_POST['id']??null);if(!R::one('SELECT id FROM notifications WHERE id=? AND customer_id=?',[$notice,$user['id']])){throw new AccessDenied('Bu bildirime erişim yetkiniz yok.');}R::execute('UPDATE notifications SET read_at=COALESCE(read_at,?) WHERE id=? AND customer_id=?',[date('Y-m-d H:i:s'),$notice,$user['id']]);break;
    default:throw new \DomainException('İşlem bulunamadı.');
   }$_SESSION['employee_notice']=$message;
  }catch(AccessDenied$e){$this->fail($e);return;}catch(\DomainException$e){$_SESSION['employee_error']=$e->getMessage();}catch(\PDOException$e){if((int)($e->errorInfo[1]??0)!==1062){throw$e;}$_SESSION['employee_error']='Bu kayıt zaten bulunuyor veya artık uygun değil.';}
  header('Location: '.\url($destination),true,303);
 }
}
