<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{Database,Password};
use App\Models\AdminRepository as R;
final class AccountRecoveryService
{
 public function issue(int $user,string $purpose):void {
  if(!in_array($purpose,['verify','reset'],true)){throw new \InvalidArgumentException('Geçersiz işlem.');}
  if(!MailService::configured()){return;}
  $account=R::one('SELECT email,is_active FROM users WHERE id=?',[$user]);if(!$account||!$account['is_active']){return;}
  $token=bin2hex(random_bytes(32));$expires=date('Y-m-d H:i:s',time()+($purpose==='reset'?1800:86400));
  R::execute('UPDATE auth_tokens SET used_at=NOW() WHERE user_id=? AND purpose=? AND used_at IS NULL',[$user,$purpose]);
  R::execute('INSERT INTO auth_tokens(user_id,purpose,token_hash,email_snapshot,expires_at) VALUES (?,?,?,?,?)',[$user,$purpose,hash('sha256',$token),$account['email'],$expires]);$id=(int)Database::connection()->lastInsertId();
  $subject=$purpose==='reset'?'Şifrenizi sıfırlayın':'E-posta adresinizi doğrulayın';$body=$purpose==='reset'?'Bu bağlantı 30 dakika geçerlidir. Yeni şifre belirlediğinizde mevcut oturumlarınız kapatılır.':'Bu bağlantı 24 saat geçerlidir. E-posta adresinizi doğrulamak için devam edin.';
  $data=['subject'=>$subject,'body'=>$body,'link'=>\url(($purpose==='reset'?'reset-password':'verify-email').'#token='.$token),'email_snapshot'=>$account['email'],'expires_at'=>$expires,'token_id'=>$id];
  R::execute('INSERT INTO mail_outbox(user_id,event,dedupe_key,payload) VALUES (?,?,?,?)',[$user,$purpose,'token:'.$id,MailService::seal($data)]);
 }
 public function request(string $email,string $purpose):void {
  $pdo=Database::connection();$pdo->beginTransaction();try{
   $user=R::one('SELECT id FROM users WHERE email=? AND is_active=1 FOR UPDATE',[strtolower(trim($email))]);
   if($user&&($purpose==='reset'||!R::one('SELECT email_verified_at FROM user_security WHERE user_id=? AND email_verified_at IS NOT NULL',[$user['id']]))){$this->issue((int)$user['id'],$purpose);}
   $pdo->commit();
  }catch(\Throwable $e){if($pdo->inTransaction()){$pdo->rollBack();}throw$e;}
 }
 public function redeem(string $token,string $purpose,?string $password=null):void {
  if(!preg_match('/^[a-f0-9]{64}$/',$token)){throw new \DomainException('Bağlantı geçersiz veya süresi dolmuş. Yeni bir bağlantı isteyin.');}
  if($purpose==='reset'&&($password===null||!Password::isAcceptable($password))){throw new \DomainException('Şifre en az 12 karakter ve en fazla 72 bayt olmalıdır.');}
  $hash=hash('sha256',$token);$found=R::one('SELECT user_id FROM auth_tokens WHERE token_hash=? AND purpose=?',[$hash,$purpose]);
  if(!$found){throw new \DomainException('Bağlantı geçersiz veya süresi dolmuş. Yeni bir bağlantı isteyin.');}
  $pdo=Database::connection();$pdo->beginTransaction();try{
   $user=R::one('SELECT * FROM users WHERE id=? FOR UPDATE',[$found['user_id']]);$row=R::one('SELECT * FROM auth_tokens WHERE token_hash=? AND purpose=? FOR UPDATE',[$hash,$purpose]);
   if(!$user||!$user['is_active']||!$row||$row['used_at']||strtotime($row['expires_at'])<=time()||$row['email_snapshot']!==$user['email']){throw new \DomainException('Bağlantı geçersiz veya süresi dolmuş. Yeni bir bağlantı isteyin.');}
   if($purpose==='reset'){
    R::execute('UPDATE users SET password_hash=?,auth_version=auth_version+1 WHERE id=?',[Password::hash($password),$user['id']]);
    R::execute('UPDATE auth_tokens SET used_at=NOW() WHERE user_id=? AND purpose=? AND used_at IS NULL',[$user['id'],'reset']);
   }else{R::execute('INSERT INTO user_security(user_id,email_verified_at) VALUES (?,NOW()) ON DUPLICATE KEY UPDATE email_verified_at=NOW()',[$user['id']]);R::execute('UPDATE auth_tokens SET used_at=NOW() WHERE id=?',[$row['id']]);}
   $pdo->commit();
  }catch(\Throwable $e){if($pdo->inTransaction()){$pdo->rollBack();}throw$e;}
 }
}
