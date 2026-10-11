<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{Database,Input,AccessDenied};
use App\Models\AdminRepository as R;
final class ReviewService
{
 public function save(int $customer,array $input):void {
  $id=Input::id($input['appointment_id']??null);$rating=Input::id($input['rating']??null);$comment=Input::text($input,'comment',2000);
  if($rating>5||mb_strlen($comment)<3){throw new \DomainException('1–5 arasında puan ve en az 3 karakter açıklama girin.');}
  $pdo=Database::connection();$pdo->beginTransaction();try{
   $appointment=R::one('SELECT * FROM appointments WHERE id=? FOR UPDATE',[$id]);
   if(!$appointment||(int)$appointment['customer_id']!==$customer){throw new AccessDenied('Bu randevuyu değerlendiremezsiniz.');}
   if($appointment['status']!=='completed'){throw new \DomainException('Yalnızca tamamlanan randevuları değerlendirebilirsiniz.');}
   $existing=R::one('SELECT * FROM reviews WHERE appointment_id=? FOR UPDATE',[$id]);
   if($existing&&(int)$existing['customer_id']!==$customer){throw new AccessDenied('Değerlendirme sahipliği doğrulanamadı.');}
   if($existing){R::execute('UPDATE reviews SET rating=?,comment=?,is_visible=0 WHERE id=?',[$rating,$comment,$existing['id']]);}
   else{R::execute('INSERT INTO reviews(appointment_id,customer_id,rating,comment,is_visible) VALUES (?,?,?,?,0)',[$id,$customer,$rating,$comment]);}
   $pdo->commit();
  }catch(\Throwable$e){if($pdo->inTransaction()){$pdo->rollBack();}throw$e;}
 }
 public function visibility(int $id,bool $visible,int $actor):void {
  $pdo=Database::connection();$pdo->beginTransaction();try{
   if(!R::one('SELECT id FROM reviews WHERE id=? FOR UPDATE',[$id])){throw new \DomainException('Değerlendirme bulunamadı.');}
   R::execute('UPDATE reviews SET is_visible=? WHERE id=?',[$visible?1:0,$id]);AuditService::record($actor,'review-visibility','review',$id);$pdo->commit();
  }catch(\Throwable$e){if($pdo->inTransaction()){$pdo->rollBack();}throw$e;}
 }
}
