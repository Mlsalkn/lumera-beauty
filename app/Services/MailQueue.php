<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Database;
use App\Models\AdminRepository as R;
final class MailQueue
{
 public static function enqueue(int $user,string $event,string $key,string $subject,string $body,?string $link=null,?int $appointment=null):void {
  if(!MailService::configured()){return;}
  R::execute('INSERT INTO mail_outbox(user_id,appointment_id,event,dedupe_key,payload) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE dedupe_key=VALUES(dedupe_key)',[$user,$appointment,$event,$key,MailService::seal(compact('subject','body','link'))]);
 }
 public function reminders(array $onlyUsers=[]):int {
  if(!MailService::configured()||SettingsService::get('reminders_enabled','1')!=='1'){return 0;}
  $hours=(int)SettingsService::get('reminder_hours','24');$n=0;
  $where=$onlyUsers?' AND a.customer_id IN ('.implode(',',array_fill(0,count($onlyUsers),'?')).')':'';
  foreach(R::rows("SELECT a.*,u.is_active FROM appointments a JOIN users u ON u.id=a.customer_id WHERE a.status='confirmed' AND a.start_at>? AND a.start_at<=? AND u.is_active=1".$where,array_merge([date('Y-m-d H:i:s'),date('Y-m-d H:i:s',time()+$hours*3600)],$onlyUsers))as$a){
   $pref=R::one('SELECT appointment_notifications FROM customer_preferences WHERE customer_id=?',[$a['customer_id']]);if($pref&&!$pref['appointment_notifications']){continue;}
   self::enqueue((int)$a['customer_id'],'reminder','reminder:'.$a['id'].':'.$a['start_at'],'Randevu hatırlatması','#'.$a['id'].' numaralı randevunuz: '.date('d.m.Y H:i',strtotime($a['start_at'])),null,(int)$a['id']);$n++;
  }return $n;
 }
 public function dispatch(int $limit=50,array $onlyUsers=[]):array {
  if(!MailService::configured()){return ['sent'=>0,'failed'=>0,'skipped'=>0,'disabled'=>true];}
  $pdo=Database::connection();$lock=R::one("SELECT GET_LOCK('lumera_mail_dispatch',0) AS n");if((int)$lock['n']!==1){throw new \RuntimeException('Gönderim zaten çalışıyor.');}
  $result=['sent'=>0,'failed'=>0,'skipped'=>0,'disabled'=>false];
  $where=$onlyUsers?' AND user_id IN ('.implode(',',array_fill(0,count($onlyUsers),'?')).')':'';
  try {foreach(R::rows("SELECT * FROM mail_outbox WHERE status='pending' AND next_attempt_at<=NOW()".$where." ORDER BY id LIMIT ".max(1,min($limit,200)),$onlyUsers)as$row){
   $user=R::one('SELECT email,is_active FROM users WHERE id=?',[$row['user_id']]);$skip=!$user||!$user['is_active'];
   if($row['event']==='reminder'){$a=R::one('SELECT status,start_at FROM appointments WHERE id=?',[$row['appointment_id']]);$skip=$skip||!$a||$a['status']!=='confirmed'||strtotime($a['start_at'])<=time()||$row['dedupe_key']!=='reminder:'.$row['appointment_id'].':'.$a['start_at'];}
   try {
    $data=MailService::unseal($row['payload']);
    if(isset($data['email_snapshot'])&&(!$user||$data['email_snapshot']!==$user['email'])){$skip=true;}
    if(isset($data['expires_at'])&&strtotime($data['expires_at'])<=time()){$skip=true;}
    if(isset($data['token_id'])){$token=R::one('SELECT used_at,expires_at FROM auth_tokens WHERE id=?',[$data['token_id']]);if(!$token||$token['used_at']||strtotime($token['expires_at'])<=time()){$skip=true;}}
    if($skip){R::execute("UPDATE mail_outbox SET status='skipped',payload='' WHERE id=?",[$row['id']]);$result['skipped']++;continue;}
    (new MailService())->send($user['email'],$data['subject'],$data['body'],$data['link']??null);
    R::execute("UPDATE mail_outbox SET status='sent',sent_at=NOW(),payload='',last_error=NULL,attempts=attempts+1 WHERE id=?",[$row['id']]);$result['sent']++;
   }catch(\Throwable $error){$attempt=(int)$row['attempts']+1;R::execute('UPDATE mail_outbox SET attempts=?,status=?,next_attempt_at=?,last_error=? WHERE id=?',[$attempt,$attempt>=5?'failed':'pending',date('Y-m-d H:i:s',time()+min(3600,60*(2**$attempt))),'SMTP_DELIVERY_FAILED',$row['id']]);$result['failed']++;}
  }}finally{R::one("SELECT RELEASE_LOCK('lumera_mail_dispatch') AS n");}return $result;
 }
}
