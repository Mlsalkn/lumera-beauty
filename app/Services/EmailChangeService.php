<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{Database,Input,Password};
use App\Models\AdminRepository as R;
final class EmailChangeService
{
 /** Optional transport is injected by tests or an installed SMTP adapter. No fake mail is sent. */
 public function __construct(private ?\Closure $sender=null){}
 public static function configured():bool{return MailService::configured();}
 public function request(int $customer,array $input):void
 {
  $email=strtolower(Input::text($input,'new_email',190));$password=$input['current_password']??null;
  if(!filter_var($email,FILTER_VALIDATE_EMAIL)||!is_string($password)){throw new \DomainException('Geçerli e-posta ve mevcut şifre gerekli.');}
  if(!$this->sender&&!self::configured()){throw new \DomainException('E-posta gönderimi yapılandırılmadı. Adresiniz değiştirilmedi ve e-posta gönderilmedi.');}
  $pdo=Database::connection();$pdo->beginTransaction();
  try{
   $user=R::one("SELECT * FROM users WHERE id=? AND role='CUSTOMER' AND is_active=1 FOR UPDATE",[$customer]);
   if(!$user||!Password::verify($password,$user['password_hash'])){throw new \DomainException('Mevcut şifre doğrulanamadı.');}
   if($email===$user['email']||R::one('SELECT id FROM users WHERE email=?',[$email])){throw new \DomainException('Bu adres için değişiklik başlatılamadı.');}
   $pending=R::one('SELECT created_at FROM email_change_requests WHERE customer_id=?',[$customer]);if($pending&&strtotime($pending['created_at'])>time()-120){throw new \DomainException('Yeni doğrulama isteği için iki dakika bekleyin.');}
   $token=bin2hex(random_bytes(32));$expires=date('Y-m-d H:i:s',time()+900);
   R::execute('INSERT INTO email_change_requests (customer_id,old_email,new_email,token_hash,expires_at,created_at) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE old_email=VALUES(old_email),new_email=VALUES(new_email),token_hash=VALUES(token_hash),expires_at=VALUES(expires_at),created_at=VALUES(created_at)',[$customer,$user['email'],$email,hash('sha256',$token),$expires,date('Y-m-d H:i:s')]);
   $link=\url('customer/email/verify#token='.$token);
   $body="Luméra Beauty e-posta değişikliği\nBağlantı 15 dakika geçerlidir. Mevcut hesabınıza giriş yapıp doğrulayın:\n".$link;
   $sent=$this->sender ? ($this->sender)($email,$body) : $this->send($email,$body);
   if(!$sent){throw new \DomainException('Doğrulama iletisi gönderilemedi. E-posta adresiniz değiştirilmedi.');}
   $pdo->commit();
  }catch(\Throwable $error){if($pdo->inTransaction()){$pdo->rollBack();}throw $error;}
 }
 private function send(string $email,string $body):bool
 {
  try{(new MailService())->send($email,'E-posta adresinizi doğrulayın',$body);return true;}catch(\Throwable $error){return false;}
 }
 public function verify(int $customer,string $token):int
 {
  if(!preg_match('/^[a-f0-9]{64}$/',$token)){throw new \DomainException('Doğrulama bağlantısı geçersiz.');}
  $pdo=Database::connection();$pdo->beginTransaction();
  try{
   $user=R::one("SELECT * FROM users WHERE id=? AND role='CUSTOMER' AND is_active=1 FOR UPDATE",[$customer]);
   $pending=R::one('SELECT * FROM email_change_requests WHERE customer_id=? FOR UPDATE',[$customer]);
   if(!$user||!$pending||$pending['old_email']!==$user['email']||strtotime($pending['expires_at'])<=time()||!hash_equals($pending['token_hash'],hash('sha256',$token))){throw new \DomainException('Doğrulama bağlantısı geçersiz veya süresi dolmuş.');}
   if(R::one('SELECT id FROM users WHERE email=? AND id<>?',[$pending['new_email'],$customer])){throw new \DomainException('Yeni adres artık kullanılamıyor.');}
   R::execute('UPDATE users SET email=?,auth_version=auth_version+1 WHERE id=?',[$pending['new_email'],$customer]);
   R::execute('INSERT INTO user_security(user_id,email_verified_at) VALUES (?,NOW()) ON DUPLICATE KEY UPDATE email_verified_at=NOW()',[$customer]);
   R::execute('DELETE FROM email_change_requests WHERE customer_id=?',[$customer]);$pdo->commit();return (int)$user['auth_version']+1;
  }catch(\Throwable $error){if($pdo->inTransaction()){$pdo->rollBack();}throw $error;}
 }
}
