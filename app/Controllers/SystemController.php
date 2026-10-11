<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Csrf,View};
use App\Middleware\{AuthMiddleware,RoleMiddleware};
use App\Models\AdminRepository as R;
use App\Services\{SettingsService,MailService,RateLimiter};
final class SystemController
{
 public function page():void {
  $user=AuthMiddleware::user();if(!$user||!RoleMiddleware::authorize($user,'ADMIN')){return;}
  View::render('admin/settings',['title'=>'Sistem ayarları · Luméra','page'=>'settings','user'=>$user,'enabled'=>MailService::configured(),'hours'=>SettingsService::get('reminder_hours','24'),'reminders'=>SettingsService::get('reminders_enabled','1'),'contact'=>SettingsService::get('contact_phone',''),'queue'=>R::rows('SELECT status,COUNT(*) n FROM mail_outbox GROUP BY status'),'audit'=>R::rows('SELECT a.*,u.name FROM audit_events a LEFT JOIN users u ON u.id=a.actor_id ORDER BY a.id DESC LIMIT 100')]);
 }
 public function save():void {
  $user=AuthMiddleware::user();if(!$user||!RoleMiddleware::authorize($user,'ADMIN')){return;}
  if(!Csrf::verify($_POST['_csrf']??null)){http_response_code(403);return;}
  if((new RateLimiter())->critical('settings',(string)$user['id'],$_SERVER['REMOTE_ADDR']??'unknown',30)){http_response_code(429);return;}
  try{(new SettingsService())->save($_POST,(int)$user['id']);$_SESSION['admin_notice']='Sistem ayarları kaydedildi.';}catch(\DomainException$e){$_SESSION['admin_error']=$e->getMessage();}
  header('Location: '.\url('admin/settings'),true,303);
 }
}
