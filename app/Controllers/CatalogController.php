<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{View,Input};use App\Models\AdminRepository as R;
final class CatalogController
{
 public function page(string $page):void {
  $data=['title'=>'Luméra Beauty','page'=>$page];
  if($page==='services'){$data['services']=R::rows('SELECT * FROM services WHERE is_active=1 ORDER BY category,name');}
  if($page==='service'){
   try{$id=Input::id($_GET['id']??null);}catch(\DomainException){http_response_code(404);View::render('errors/error',['title'=>'404','message'=>'Hizmet bulunamadı.']);return;}
   $data['service']=R::one('SELECT * FROM services WHERE id=? AND is_active=1',[$id]);if(!$data['service']){http_response_code(404);View::render('errors/error',['title'=>'404','message'=>'Hizmet bulunamadı.']);return;}
   $data['employees']=R::rows("SELECT e.title,e.bio,e.photo_path,u.name FROM employees e JOIN users u ON u.id=e.user_id JOIN employee_services es ON es.employee_id=e.id WHERE es.service_id=? AND e.is_active=1 AND u.is_active=1 AND u.role='EMPLOYEE' ORDER BY u.name",[$id]);
  }
  if($page==='experts'){$data['employees']=R::rows("SELECT e.id,e.title,e.bio,e.photo_path,u.name FROM employees e JOIN users u ON u.id=e.user_id WHERE e.is_active=1 AND u.is_active=1 AND u.role='EMPLOYEE' ORDER BY u.name");}
  if($page==='experts'){foreach($data['employees'] as &$worker){$worker['services']=R::rows('SELECT s.name FROM employee_services es JOIN services s ON s.id=es.service_id WHERE es.employee_id=? AND s.is_active=1 ORDER BY s.name',[$worker['id']]);$worker['hours']=R::rows('SELECT weekday,start_time,end_time FROM working_hours WHERE employee_id=? ORDER BY weekday',[$worker['id']]);}unset($worker);}
  if($page==='contact'){$data['phone']=\App\Services\SettingsService::get('contact_phone','');}
  if($page==='reviews'){$data['reviews']=R::rows('SELECT r.rating,r.comment,u.name,s.name service_name FROM reviews r JOIN appointments a ON a.id=r.appointment_id JOIN services s ON s.id=a.service_id JOIN users u ON u.id=r.customer_id WHERE r.is_visible=1 ORDER BY r.id DESC LIMIT 50');}
  View::render($page==='contact'?'home/contact':($page==='reviews'?'home/reviews':($page==='about'?'home/about':'home/catalog')),$data);
 }
}
