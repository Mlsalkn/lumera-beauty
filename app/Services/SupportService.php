<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Database;use App\Models\AdminRepository as R;
final class SupportService {
 public static function email():string{return 'alkanmelisa12@gmail.com';}
 public static function validate(array $input):array {
  $data=[];foreach(['name'=>[2,120],'email'=>[3,190],'subject'=>[3,160],'message'=>[10,5000]] as $key=>[$min,$max]){
   $value=$input[$key]??null;if(!is_string($value)){throw new \DomainException('Lütfen bütün alanları geçerli metinlerle doldurun.');}$value=trim($value);
   if(mb_strlen($value)<$min||mb_strlen($value)>$max||str_contains($value,chr(0))){throw new \DomainException('Ad 2–120, konu 3–160, mesaj 10–5000 karakter olmalıdır.');}$data[$key]=$value;
  }if(!filter_var($data['email'],FILTER_VALIDATE_EMAIL)){throw new \DomainException('Geçerli bir e-posta adresi yazın.');}return $data;
 }
 public function create(array $input,?int $user):string {
  $data=self::validate($input);$reference=bin2hex(random_bytes(12));
  R::execute('INSERT INTO support_tickets(reference,user_id,name,email,subject,message) VALUES (?,?,?,?,?,?)',[$reference,$user,$data['name'],strtolower($data['email']),$data['subject'],$data['message']]);return $reference;
 }
 public function status(int $id,string $status,int $actor):void {
  if(!in_array($status,['open','in_progress','resolved'],true)){throw new \DomainException('Geçersiz talep durumu.');}
  $pdo=Database::connection();$pdo->beginTransaction();try{if(!R::one('SELECT id FROM support_tickets WHERE id=? FOR UPDATE',[$id])){throw new \DomainException('Talep bulunamadı.');}R::execute('UPDATE support_tickets SET status=? WHERE id=?',[$status,$id]);AuditService::record($actor,'support-status','support',$id);$pdo->commit();}catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 }
}
