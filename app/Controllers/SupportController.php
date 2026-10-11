<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Csrf,View,Input};use App\Middleware\{AuthMiddleware,RoleMiddleware};use App\Models\AdminRepository as R;use App\Services\{SupportService,RateLimiter,AuthService};
final class SupportController {
 public function create():void {
  if(!Csrf::verify($_POST['_csrf']??null)){http_response_code(403);View::render('errors/error',['title'=>'403','message'=>'Form güvenlik doğrulaması başarısız. Sayfayı yenileyin.']);return;}
  try{$input=SupportService::validate($_POST);$retry=(new RateLimiter())->critical('support',$input['email'],$_SERVER['REMOTE_ADDR']??'unknown',5);if($retry){http_response_code(429);header('Retry-After: '.$retry);View::render('errors/error',['title'=>'Biraz bekleyin','message'=>'Çok fazla talep oluşturuldu. Daha sonra tekrar deneyin.']);return;}
   $user=AuthService::current();(new SupportService())->create($input,$user?(int)$user['id']:null);$_SESSION['support_notice']='Talebiniz sisteme kaydedildi. Destek ekibimiz yönetim panelinden inceleyebilir. Bu işlemde e-posta gönderilmedi.';
  }catch(\DomainException $e){$_SESSION['support_error']=$e->getMessage();$_SESSION['support_old']=array_intersect_key($_POST,array_flip(['name','email','subject','message']));}
  header('Location: '.\url('contact'),true,303);
 }
 public function page():void {
  $user=AuthMiddleware::user();if(!$user||!RoleMiddleware::authorize($user,'ADMIN'))return;
  $status=$_GET['status']??'';if(!is_string($status)||!in_array($status,['','open','in_progress','resolved'],true)){http_response_code(422);View::render('errors/error',['title'=>'Geçersiz filtre','message'=>'Geçerli bir talep durumu seçin.']);return;}
  $number=$_GET['p']??'1';$number=is_string($number)&&ctype_digit($number)?max(1,min(100000,(int)$number)):1;$where=$status?' WHERE status=?':'';$args=$status?[$status]:[];
  $total=(int)R::one('SELECT COUNT(*) n FROM support_tickets'.$where,$args)['n'];$offset=($number-1)*25;
  View::render('admin/support',['title'=>'Destek talepleri · Luméra Beauty','page'=>'support','user'=>$user,'tickets'=>R::rows('SELECT * FROM support_tickets'.$where.' ORDER BY id DESC LIMIT 25 OFFSET '.$offset,$args),'filter'=>$status,'number'=>$number,'total'=>$total]);
 }
 public function status():void {
  $user=AuthMiddleware::user();if(!$user||!RoleMiddleware::authorize($user,'ADMIN'))return;if(!Csrf::verify($_POST['_csrf']??null)){http_response_code(403);return;}
  try{$status=$_POST['status']??null;if(!is_string($status))throw new \DomainException('Geçersiz durum.');(new SupportService())->status(Input::id($_POST['id']??null),$status,(int)$user['id']);$_SESSION['admin_notice']='Talep durumu güncellendi. E-posta gönderilmedi.';}catch(\DomainException $e){$_SESSION['admin_error']=$e->getMessage();}header('Location: '.\url('admin/support'),true,303);
 }
}
