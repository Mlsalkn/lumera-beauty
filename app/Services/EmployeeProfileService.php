<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{Database,Input,AccessDenied};
use App\Models\{AdminRepository as R,EmployeeRepository as E};
final class EmployeeProfileService
{
 public function update(int $user,array $input,?array $upload=null):void
 {
  $employee=E::account($user);$first=Input::text($input,'first_name',60);$last=Input::text($input,'last_name',60);$phone=Input::text($input,'phone',25);$bio=Input::text($input,'bio',5000,false);
  if(mb_strlen($first)<2||mb_strlen($last)<2||mb_strlen($first.' '.$last)>120||!preg_match('/^\+?[0-9 ()-]{7,25}$/',$phone)||strlen(preg_replace('/\D/','',$phone))<7){throw new \DomainException('Ad, soyad ve telefon geçersiz.');}
  $path=null;if($upload&&($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){$path=(new ImageUploadService())->store($upload);}$pdo=Database::connection();$pdo->beginTransaction();try{
   $row=R::one('SELECT * FROM employees WHERE id=? AND user_id=? AND is_active=1 FOR UPDATE',[$employee['id'],$user]);$account=R::one("SELECT id FROM users WHERE id=? AND role='EMPLOYEE' AND is_active=1 FOR UPDATE",[$user]);if(!$row||!$account){throw new AccessDenied('Çalışan profili artık uygun değil.');}
   $photo=$path??$row['photo_path'];if(isset($input['remove_photo'])){$photo=$path;}
   R::execute('UPDATE users SET name=?,first_name=?,last_name=?,phone=?,photo_path=? WHERE id=?',[$first.' '.$last,$first,$last,$phone,$photo,$user]);R::execute('UPDATE employees SET bio=?,photo_path=? WHERE id=?',[$bio,$photo,$row['id']]);$pdo->commit();
  }catch(\Throwable$e){if($pdo->inTransaction()){$pdo->rollBack();}if($path&&is_file(BASE_PATH.'/public/'.$path)){unlink(BASE_PATH.'/public/'.$path);}throw$e;}
 }
}
