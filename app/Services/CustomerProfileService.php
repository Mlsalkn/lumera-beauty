<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{Database,Input,Password};
use App\Models\AdminRepository as R;
final class CustomerProfileService
{
 public function update(int $customer,array $input,?array $photo=null):void
 {
  $first=Input::text($input,'first_name',60);$last=Input::text($input,'last_name',60);$phone=Input::text($input,'phone',25);
  if(mb_strlen($first)<2||mb_strlen($last)<2||mb_strlen($first.' '.$last)>120||!preg_match('/^\+?[0-9 ()-]{7,25}$/',$phone)||strlen(preg_replace('/\D/','',$phone))<7){throw new \DomainException('Ad, soyad ve telefon geçersiz.');}
  $path=null;if($photo&&($photo['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){$path=(new ImageUploadService())->store($photo);}
  $pdo=Database::connection();$pdo->beginTransaction();
  try{
   $user=R::one("SELECT id,photo_path FROM users WHERE id=? AND role='CUSTOMER' AND is_active=1 FOR UPDATE",[$customer]);if(!$user){throw new \DomainException('Hesap bulunamadı.');}
   $image=$path??$user['photo_path'];if(isset($input['remove_photo'])){$image=$path;}
   R::execute('UPDATE users SET first_name=?,last_name=?,name=?,phone=?,photo_path=? WHERE id=?',[$first,$last,$first.' '.$last,$phone,$image,$customer]);
   R::execute('INSERT INTO customer_preferences (customer_id,appointment_notifications) VALUES (?,?) ON DUPLICATE KEY UPDATE appointment_notifications=VALUES(appointment_notifications)',[$customer,isset($input['appointment_notifications'])?1:0]);
   $pdo->commit();
  }catch(\Throwable $error){if($pdo->inTransaction()){$pdo->rollBack();}if($path&&is_file(BASE_PATH.'/public/'.$path)){unlink(BASE_PATH.'/public/'.$path);}throw $error;}
 }
 public function changePassword(int $customer,array $input):int
 {
  $old=$input['current_password']??null;$new=$input['password']??null;
  if(!is_string($old)||!is_string($new)||!Password::isAcceptable($new)||$new!==($input['password_confirmation']??null)){throw new \DomainException('Yeni şifre en az 12 karakter olmalı ve tekrarı eşleşmelidir.');}
  $pdo=Database::connection();$pdo->beginTransaction();
  try{
   $user=R::one("SELECT * FROM users WHERE id=? AND role='CUSTOMER' AND is_active=1 FOR UPDATE",[$customer]);
   if(!$user||!Password::verify($old,$user['password_hash'])){throw new \DomainException('Mevcut şifre doğrulanamadı.');}
   R::execute('UPDATE users SET password_hash=?,auth_version=auth_version+1 WHERE id=?',[Password::hash($new),$customer]);
   R::execute('DELETE FROM email_change_requests WHERE customer_id=?',[$customer]);$pdo->commit();return (int)$user['auth_version']+1;
  }catch(\Throwable $error){if($pdo->inTransaction()){$pdo->rollBack();}throw $error;}
 }
}
