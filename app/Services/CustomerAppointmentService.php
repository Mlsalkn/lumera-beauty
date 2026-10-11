<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{Database,Input};
use App\Models\AdminRepository as R;
final class CustomerAppointmentService
{
 public function workers(int $service):array
 {
  if(!R::one('SELECT id FROM services WHERE id=? AND is_active=1',[$service])){throw new \DomainException('Aktif hizmet bulunamadı.');}
  return R::rows("SELECT e.id,e.title,e.bio,e.photo_path,u.name FROM employees e JOIN users u ON u.id=e.user_id JOIN employee_services es ON es.employee_id=e.id WHERE es.service_id=? AND e.is_active=1 AND u.is_active=1 AND u.role='EMPLOYEE' ORDER BY e.id",[$service]);
 }
 public function owned(int $customer,int $id):array
 {
  $booking=R::appointments('a.id=? AND a.customer_id=?',[$id,$customer],1)[0]??null;
  if(!$booking){throw new \App\Core\AccessDenied('Bu randevuya erişim yetkiniz yok.');}
  return $booking;
 }
 public function canModify(array $booking,string $action):bool
 {
  $rules=require BASE_PATH.'/config/appointments.php';
  return in_array($booking['status'],['pending','confirmed'],true)&&strtotime($booking['start_at'])>time()+60*$rules[$action.'_before_minutes'];
 }
 public function slots(int $customer,int $service,int $employee,string $date,?int $reschedule=null):array
 {
  $rules=require BASE_PATH.'/config/appointments.php';$day=Input::date($date);
  if($day<new \DateTimeImmutable('today')||$day>new \DateTimeImmutable('today +'.$rules['booking_days'].' days')){throw new \DomainException('Tarih izin verilen rezervasyon aralığında olmalıdır.');}
  $except=0;$duration=null;
  if($reschedule){$booking=$this->owned($customer,$reschedule);if(!$this->canModify($booking,'reschedule')){throw new \DomainException('Bu randevunun tarihi artık değiştirilemez.');}
   $except=$reschedule;$duration=(int)((strtotime($booking['end_at'])-strtotime($booking['start_at']))/60);$service=(int)$booking['service_id'];$employee=(int)$booking['employee_id'];
  }
  $workers=$this->workers($service);$choices=[];
  foreach($workers as $worker){if($employee&&$employee!==(int)$worker['id']){continue;}foreach((new AvailabilityService())->slots((int)$worker['id'],$service,$date,$except,$duration) as $time){$choices[$time][]=(int)$worker['id'];}}
  ksort($choices);return $choices;
 }
 public function draft(int $customer,array $input):array
 {
  $service=Input::id($input['service_id']??null);$employee=($input['employee_id']??'')==='any'?0:Input::id($input['employee_id']??null);
  $date=Input::text($input,'date',10);$time=Input::text($input,'time',5);$note=Input::text($input,'customer_note',1000,false);
  Input::datetime($date.' '.$time);$slots=$this->slots($customer,$service,$employee,$date);
  if(empty($slots[$time])){throw new \DomainException('Seçilen saat artık uygun değil. Başka bir saat seçin.');}
  $item=R::one('SELECT * FROM services WHERE id=? AND is_active=1',[$service]);
  if(!$item){throw new \DomainException('Hizmet bulunamadı.');}
  return ['customer_id'=>$customer,'service'=>$item,'employee_id'=>$employee,'workers'=>$this->workers($service),'date'=>$date,'time'=>$time,'note'=>$note,'expires'=>time()+600,'nonce'=>bin2hex(random_bytes(24))];
 }
 public function confirm(int $customer,array $draft):int
 {
  if(($draft['customer_id']??null)!==$customer||($draft['expires']??0)<=time()){throw new \DomainException('Randevu özeti süresi doldu. Yeni seçim yapın.');}
  $slots=$this->slots($customer,(int)$draft['service']['id'],(int)$draft['employee_id'],$draft['date']);$workers=$slots[$draft['time']]??[];
  if(!$workers){throw new \DomainException('Bu saat başka bir randevu tarafından alındı. Yeni saat seçin.');}
  foreach($workers as $worker){
   try{return (new AppointmentService())->create(['customer_id'=>$customer,'employee_id'=>$worker,'service_id'=>$draft['service']['id'],'start_at'=>$draft['date'].' '.$draft['time'],'customer_note'=>$draft['note'],'_expected_price'=>$draft['service']['price'],'_expected_duration'=>$draft['service']['duration_minutes']],$customer);}
   catch(\DomainException $error){if($draft['employee_id']){throw $error;}$last=$error;}
  }
  throw $last??new \DomainException('Müsait çalışan bulunamadı.');
 }
 public function cancel(int $customer,int $id):void
 {
  $this->modify($customer,$id,null);
 }
 public function reschedule(int $customer,int $id,string $newStart):void
 {
  $this->modify($customer,$id,Input::datetime($newStart));
 }
 private function modify(int $customer,int $id,?\DateTimeImmutable $newStart):void
 {
  $existing=$this->owned($customer,$id);$pdo=Database::connection();$pdo->beginTransaction();
  try{
   $worker=R::one('SELECT * FROM employees WHERE id=? FOR UPDATE',[$existing['employee_id']]);
   $booking=R::one('SELECT * FROM appointments WHERE id=? AND customer_id=? FOR UPDATE',[$id,$customer]);
   if(!$booking||!$this->canModify($booking,$newStart?'reschedule':'cancel')){throw new \DomainException('İptal veya tarih değişikliği için süre dolmuş ya da durum uygun değil.');}
   if($newStart){
    $rules=require BASE_PATH.'/config/appointments.php';if($newStart<=new \DateTimeImmutable()||$newStart>new \DateTimeImmutable('today +'.$rules['booking_days'].' days 23:59:59')){throw new \DomainException('Gelecek bir zaman seçin.');}
    $item=R::one('SELECT * FROM services WHERE id=? FOR UPDATE',[$booking['service_id']]);$account=R::one("SELECT id FROM users WHERE id=? AND is_active=1 AND role='EMPLOYEE'",[$worker['user_id']]);
    if(!$worker['is_active']||!$account||!$item||!$item['is_active']||!R::one('SELECT service_id FROM employee_services WHERE employee_id=? AND service_id=?',[$worker['id'],$booking['service_id']])){throw new \DomainException('Hizmet veya çalışan artık uygun değil.');}
    $duration=(int)((strtotime($booking['end_at'])-strtotime($booking['start_at']))/60);$end=$newStart->modify('+'.$duration.' minutes');$availability=new AvailabilityService();
    if(!$availability->fits((int)$worker['id'],$newStart,$end)||$availability->overlaps((int)$worker['id'],$newStart,$end,$id)){throw new \DomainException('Yeni saat uygun değil veya çakışıyor.');}
    R::execute("UPDATE appointments SET start_at=?,end_at=?,status='pending' WHERE id=?",[$newStart->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),$id]);NotificationService::record($id,'rescheduled',$booking,$customer);
   }else{R::execute("UPDATE appointments SET status='cancelled' WHERE id=?",[$id]);NotificationService::record($id,'cancelled',$booking,$customer);}
   $pdo->commit();
  }catch(\Throwable $error){if($pdo->inTransaction()){$pdo->rollBack();}throw $error;}
 }
 public function favorite(int $customer,int $service,bool $add):void
 {
  if($add){$pdo=Database::connection();$pdo->beginTransaction();try{if(!R::one('SELECT id FROM services WHERE id=? AND is_active=1 FOR UPDATE',[$service])){throw new \DomainException('Aktif hizmet bulunamadı.');}R::execute('INSERT INTO favorites (customer_id,service_id) VALUES (?,?) ON DUPLICATE KEY UPDATE service_id=VALUES(service_id)',[$customer,$service]);$pdo->commit();}catch(\Throwable $error){if($pdo->inTransaction()){$pdo->rollBack();}throw $error;}}
  else{R::execute('DELETE FROM favorites WHERE customer_id=? AND service_id=?',[$customer,$service]);}
 }
}
