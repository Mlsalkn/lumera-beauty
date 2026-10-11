<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Controller;
final class HomeController extends Controller
{
    public function index(): void {
        $data=['isHome'=>true,'title'=>'Luméra Beauty • Kendinize zaman ayırın','services'=>[],'employees'=>[],'reviews'=>[]];
        if(env('DB_USERNAME')!==''){
            $data['services']=\App\Models\AdminRepository::rows('SELECT s.*,COUNT(a.id) popularity FROM services s LEFT JOIN appointments a ON a.service_id=s.id AND a.status<>\'cancelled\' WHERE s.is_active=1 GROUP BY s.id ORDER BY popularity DESC,s.id DESC LIMIT 3');
            $data['employees']=\App\Models\AdminRepository::rows("SELECT u.name,e.title,e.photo_path FROM employees e JOIN users u ON u.id=e.user_id WHERE e.is_active=1 AND u.is_active=1 AND u.role='EMPLOYEE' ORDER BY e.id LIMIT 3");
            $data['reviews']=\App\Models\AdminRepository::rows('SELECT r.rating,r.comment,u.name FROM reviews r JOIN users u ON u.id=r.customer_id WHERE r.is_visible=1 ORDER BY r.id DESC LIMIT 3');
        }
        $this->render('home/index',$data);
    }
}
