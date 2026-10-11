<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{Database,Input,AccessDenied};
use App\Models\{AdminRepository as R,EmployeeRepository as E};
final class EmployeeOperationsService
{
 private function interval(array $input):array
 {
  $start=Input::datetime(Input::text($input,'start_at',16));$end=Input::datetime(Input::text($input,'end_at',16));
  if($start<=new \DateTimeImmutable()||$end<=$start||$end>$start->modify('+90 days')||$start>new \DateTimeImmutable('+365 days')){throw new \DomainException('Gelecek bir yıl içinde, en fazla 90 günlük geçerli aralık seçin.');}
  return[$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s')];
 }
 public function requestLeave(int $employee,array $input):int
 {
  [$start,$end]=$this->interval($input);$reason=Input::text($input,'reason',1000);if(mb_strlen($reason)<3){throw new \DomainException('Açıklama en az 3 karakter olmalıdır.');}
  $pdo=Database::connection();$pdo->beginTransaction();try{
   if(!R::one('SELECT id FROM employees WHERE id=? AND is_active=1 FOR UPDATE',[$employee])){throw new AccessDenied('Çalışan bulunamadı.');}
   if(R::one("SELECT id FROM employee_leave_requests WHERE employee_id=? AND status IN ('pending','approved') AND start_at<? AND end_at>?",[$employee,$end,$start])){throw new \DomainException('Bu aralıkla çakışan bir izin talebiniz var.');}
   if(R::one('SELECT id FROM employee_leaves WHERE employee_id=? AND start_at<? AND end_at>?',[$employee,$end,$start])){throw new \DomainException('Bu aralıkta mevcut onaylı izin var.');}
   R::execute('INSERT INTO employee_leave_requests(employee_id,start_at,end_at,reason) VALUES (?,?,?,?)',[$employee,$start,$end,$reason]);$id=(int)$pdo->lastInsertId();$pdo->commit();return$id;
  }catch(\Throwable$e){if($pdo->inTransaction()){$pdo->rollBack();}throw$e;}
 }
 public function conflicts(array $request):array{return R::appointments("a.employee_id=? AND a.status IN ('pending','confirmed') AND a.start_at<? AND a.end_at>?",[$request['employee_id'],$request['end_at'],$request['start_at']],null,true);}
 public function reviewLeave(int $id,string $decision,string $response,int $admin):void
 {
  if(!in_array($decision,['approved','rejected'],true)){throw new \DomainException('Geçersiz karar.');}if($decision==='rejected'&&mb_strlen($response)<3){throw new \DomainException('Ret gerekçesi en az 3 karakter olmalıdır.');}
  $request=R::one('SELECT employee_id FROM employee_leave_requests WHERE id=?',[$id]);if(!$request){throw new \DomainException('Talep bulunamadı.');}
  $pdo=Database::connection();$pdo->beginTransaction();try{
   R::one('SELECT id FROM employees WHERE id=? FOR UPDATE',[$request['employee_id']]);$request=R::one('SELECT * FROM employee_leave_requests WHERE id=? FOR UPDATE',[$id]);
   if($request['status']!=='pending'){throw new \DomainException('Talep daha önce değerlendirildi.');}$leave=null;
   if($decision==='approved'){
    if(strtotime($request['start_at'])<=time()){throw new \DomainException('Başlamış izin onaylanamaz.');}
    $conflicts=$this->conflicts($request);if($conflicts){throw new \DomainException('İzin randevularla çakışıyor: #'.implode(', #',array_column($conflicts,'id')).'. Önce yönetici randevu ekranında müşteriyi bilgilendirerek randevuları çözümleyin; ardından bu talebi yeniden değerlendirin. Hiçbir randevu otomatik iptal edilmedi.');}
    if(R::one('SELECT id FROM employee_leaves WHERE employee_id=? AND start_at<? AND end_at>?',[$request['employee_id'],$request['end_at'],$request['start_at']])){throw new \DomainException('Başka onaylı izinle çakışıyor.');}
    R::execute('INSERT INTO employee_leaves(employee_id,start_at,end_at,reason) VALUES (?,?,?,?)',[$request['employee_id'],$request['start_at'],$request['end_at'],mb_substr($request['reason'],0,255)]);$leave=(int)$pdo->lastInsertId();
   }
   R::execute('UPDATE employee_leave_requests SET status=?,reviewer_id=?,reviewed_at=?,response=?,approved_leave_id=? WHERE id=?',[$decision,$admin,date('Y-m-d H:i:s'),$response?:null,$leave,$id]);
   NotificationService::employee((int)$request['employee_id'],'leave_result','#'.$id.' izin talebiniz '.($decision==='approved'?'onaylandı':'reddedildi').'. '.mb_substr($response,0,350));$pdo->commit();
  }catch(\Throwable$e){if($pdo->inTransaction()){$pdo->rollBack();}throw$e;}
 }
 public function requestCancellation(int $employee,int $appointment,string $reason):void
 {
  E::owned($employee,$appointment);if(mb_strlen($reason)<3){throw new \DomainException('İptal gerekçesi en az 3 karakter olmalıdır.');}$pdo=Database::connection();$pdo->beginTransaction();try{
   R::one('SELECT id FROM employees WHERE id=? FOR UPDATE',[$employee]);$booking=R::one('SELECT * FROM appointments WHERE id=? AND employee_id=? FOR UPDATE',[$appointment,$employee]);
   if(!E::permissions($employee)['can_request_cancel']){throw new AccessDenied('İptal talebi yetkiniz bulunmuyor.');}
   if(!$booking||!in_array($booking['status'],['pending','confirmed'],true)||strtotime($booking['end_at'])<=time()){throw new \DomainException('Randevu iptal talebine uygun değil.');}
   if(R::one("SELECT id FROM appointment_cancellation_requests WHERE appointment_id=? AND status='pending'",[$appointment])){throw new \DomainException('Bu randevu için bekleyen talep var.');}
   R::execute('INSERT INTO appointment_cancellation_requests(appointment_id,employee_id,reason) VALUES (?,?,?)',[$appointment,$employee,$reason]);$pdo->commit();
  }catch(\Throwable$e){if($pdo->inTransaction()){$pdo->rollBack();}throw$e;}
 }
 public function reviewCancellation(int $id,string $decision,string $response,int $admin):void
 {
  if(!in_array($decision,['approved','rejected'],true)){throw new \DomainException('Geçersiz karar.');}$request=R::one('SELECT * FROM appointment_cancellation_requests WHERE id=?',[$id]);if(!$request){throw new \DomainException('Talep bulunamadı.');}
  if($decision==='approved'){(new AppointmentService())->status((int)$request['appointment_id'],'cancelled',$admin,null,$id,$response);return;}
  if(mb_strlen($response)<3){throw new \DomainException('Ret gerekçesi en az 3 karakter olmalıdır.');}$pdo=Database::connection();$pdo->beginTransaction();try{
   R::one('SELECT id FROM employees WHERE id=? FOR UPDATE',[$request['employee_id']]);$request=R::one('SELECT * FROM appointment_cancellation_requests WHERE id=? FOR UPDATE',[$id]);if($request['status']!=='pending'){throw new \DomainException('Talep daha önce değerlendirildi.');}
   R::execute("UPDATE appointment_cancellation_requests SET status='rejected',reviewer_id=?,reviewed_at=?,response=? WHERE id=?",[$admin,date('Y-m-d H:i:s'),$response,$id]);NotificationService::employee((int)$request['employee_id'],'cancel_result','İptal talebiniz reddedildi · #'.$request['appointment_id'].'. '.mb_substr($response,0,300),(int)$request['appointment_id']);$pdo->commit();
  }catch(\Throwable$e){if($pdo->inTransaction()){$pdo->rollBack();}throw$e;}
 }
 public function permissions(int $employee,array $input):void
 {
  $pdo=Database::connection();$pdo->beginTransaction();try{
   if(!R::one('SELECT id FROM employees WHERE id=? FOR UPDATE',[$employee])){throw new \DomainException('Çalışan bulunamadı.');}
   R::execute('INSERT INTO employee_permissions(employee_id,can_confirm,can_complete,can_request_cancel) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE can_confirm=VALUES(can_confirm),can_complete=VALUES(can_complete),can_request_cancel=VALUES(can_request_cancel)',[$employee,isset($input['can_confirm'])?1:0,isset($input['can_complete'])?1:0,isset($input['can_request_cancel'])?1:0]);NotificationService::employee($employee,'permissions','Randevu işlem yetkileriniz yönetici tarafından kaydedildi.');$pdo->commit();
  }catch(\Throwable$e){if($pdo->inTransaction()){$pdo->rollBack();}throw$e;}
 }
 public function addBreak(array $input):void
 {
  $employee=Input::id($input['employee_id']??null);$day=Input::id($input['weekday']??null);$start=Input::text($input,'start_time',5);$end=Input::text($input,'end_time',5);$reason=Input::text($input,'reason',120);
  if($day>7||!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$start)||!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$end)||$end<=$start){throw new \DomainException('Geçerli gün ve mola saatleri seçin.');}$start.=':00';$end.=':00';
  $pdo=Database::connection();$pdo->beginTransaction();try{
   if(!R::one('SELECT id FROM employees WHERE id=? FOR UPDATE',[$employee])){throw new \DomainException('Çalışan bulunamadı.');}
   if(!R::one('SELECT id FROM working_hours WHERE employee_id=? AND weekday=? AND start_time<=? AND end_time>=?',[$employee,$day,$start,$end])){throw new \DomainException('Mola çalışma aralığı içinde olmalıdır.');}
   if(R::one('SELECT id FROM employee_breaks WHERE employee_id=? AND weekday=? AND start_time<? AND end_time>?',[$employee,$day,$end,$start])){throw new \DomainException('Başka molayla çakışıyor.');}
   if(R::one("SELECT id FROM appointments WHERE employee_id=? AND status IN ('pending','confirmed') AND end_at>? AND WEEKDAY(start_at)+1=? AND TIME(start_at)<? AND TIME(end_at)>?",[$employee,date('Y-m-d H:i:s'),$day,$end,$start])){throw new \DomainException('Mola mevcut randevularla çakışıyor. Önce randevuları çözümleyin.');}
   R::execute('INSERT INTO employee_breaks(employee_id,weekday,start_time,end_time,reason) VALUES (?,?,?,?,?)',[$employee,$day,$start,$end,$reason]);NotificationService::employee($employee,'schedule','Haftalık çalışma planınıza mola eklendi.');$pdo->commit();
  }catch(\Throwable$e){if($pdo->inTransaction()){$pdo->rollBack();}throw$e;}
 }
 public function removeBreak(int $id):void
 {
  $row=R::one('SELECT employee_id FROM employee_breaks WHERE id=?',[$id]);if(!$row){throw new \DomainException('Mola bulunamadı.');}$pdo=Database::connection();$pdo->beginTransaction();try{R::one('SELECT id FROM employees WHERE id=? FOR UPDATE',[$row['employee_id']]);R::execute('DELETE FROM employee_breaks WHERE id=?',[$id]);NotificationService::employee((int)$row['employee_id'],'schedule','Haftalık çalışma planınızdan mola kaldırıldı.');$pdo->commit();}catch(\Throwable$e){if($pdo->inTransaction()){$pdo->rollBack();}throw$e;}
 }
}
