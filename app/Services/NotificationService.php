<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\AdminRepository as R;
final class NotificationService
{
 public static function employee(int $employee,string $event,string $message,?int $appointment=null):void
 {
  $worker=R::one('SELECT user_id FROM employees WHERE id=?',[$employee]);if(!$worker){throw new \RuntimeException('Bildirim çalışanı bulunamadı.');}
  // Existing customer_id references users.id; it is the recipient for every role.
  R::execute('INSERT INTO notifications (customer_id,appointment_id,event,message) VALUES (?,?,?,?)',[$worker['user_id'],$appointment,$event,mb_substr($message,0,500)]);
  $notification=(int)\App\Core\Database::connection()->lastInsertId();
  if(in_array($event,['created','cancelled','rescheduled','leave_result','schedule'],true)){MailQueue::enqueue((int)$worker['user_id'],$event,'notification:'.$notification,'Luméra Beauty · Bildirim',$message,null,$appointment);}
 }
 public static function record(int $appointmentId,string $event,?array $before=null,?int $actor=null):void
 {
  $booking=R::one('SELECT * FROM appointments WHERE id=?',[$appointmentId]);
  if(!$booking){throw new \RuntimeException('Bildirim randevusu bulunamadı.');}
  R::execute('INSERT INTO appointment_status_history (appointment_id,actor_id,event,old_status,new_status,old_start_at,new_start_at) VALUES (?,?,?,?,?,?,?)',[$appointmentId,$actor,$event,$before['status']??null,$booking['status'],$before['start_at']??null,$booking['start_at']]);
  $employeeMessage=match($event){'created'=>'Yeni randevu atandı','confirmed'=>'Randevu onaylandı','cancelled'=>'Randevu iptal edildi','completed'=>'İşlem tamamlandı','rescheduled'=>'Randevu zamanı değişti',default=>throw new \InvalidArgumentException('Geçersiz bildirim olayı.')};
  self::employee((int)$booking['employee_id'],$event,$employeeMessage.' · #'.$appointmentId.' · '.date('d.m.Y H:i',strtotime($booking['start_at'])),$appointmentId);
  $preferences=R::one('SELECT appointment_notifications FROM customer_preferences WHERE customer_id=?',[$booking['customer_id']]);
  if($preferences&&!(bool)$preferences['appointment_notifications']){return;}
  $label=match($event){'created'=>'oluşturuldu','confirmed'=>'onaylandı','cancelled'=>'iptal edildi','completed'=>'tamamlandı','rescheduled'=>'tarihi değiştirildi',default=>throw new \InvalidArgumentException('Geçersiz bildirim olayı.')};
  $message='#'.$appointmentId.' numaralı randevunuz '.$label.'. Tarih: '.date('d.m.Y H:i',strtotime($booking['start_at']));
  R::execute('INSERT INTO notifications (customer_id,appointment_id,event,message) VALUES (?,?,?,?)',[$booking['customer_id'],$appointmentId,$event,$message]);
  $notification=(int)\App\Core\Database::connection()->lastInsertId();
  if(in_array($event,['created','cancelled','rescheduled'],true)){MailQueue::enqueue((int)$booking['customer_id'],$event,'notification:'.$notification,'Luméra Beauty · Randevu bildirimi',$message,null,$appointmentId);}
 }
}
