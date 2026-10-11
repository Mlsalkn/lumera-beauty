<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Csrf, Input, View};
use App\Middleware\{AuthMiddleware, RoleMiddleware};
use App\Models\AdminRepository as R;
use App\Services\{AppointmentService, AvailabilityService, ManagementService, StatisticsService};
final class AdminController
{
    private function authorize(): ?array
    {
        $user = AuthMiddleware::user();
        return $user && RoleMiddleware::authorize($user,'ADMIN') ? $user : null;
    }
    public function page(string $page): void
    {
        $user=$this->authorize(); if (!$user) { return; }
        $data=['user'=>$user,'page'=>$page,'title'=>'Yönetim · Luméra'];
        try {
            switch ($page) {
                case 'dashboard': $data['stats']=(new StatisticsService())->dashboard(); break;
                case 'reviews':$data['reviews']=R::rows('SELECT r.*,u.name customer_name,s.name service_name FROM reviews r JOIN users u ON u.id=r.customer_id JOIN appointments a ON a.id=r.appointment_id JOIN services s ON s.id=a.service_id ORDER BY r.id DESC LIMIT 500');break;
                case 'services':
                    $data['services']=R::services(); $data['edit']=isset($_GET['id']) ? R::one('SELECT * FROM services WHERE id=?',[Input::id($_GET['id'])]) : null;
                    if (isset($_GET['id']) && !$data['edit']) { throw new \DomainException('Hizmet bulunamadı.'); }
                    break;
                case 'employees':
                    $data['employees']=R::employees(); $data['services']=R::services(); $data['edit']=null; $data['assigned']=[]; $data['hours']=[]; $data['leaves']=[];
                    if (isset($_GET['id'])) {
                        $id=Input::id($_GET['id']); $data['edit']=R::one('SELECT e.*,u.name,u.email,u.phone FROM employees e JOIN users u ON u.id=e.user_id WHERE e.id=?',[$id]);
                        if (!$data['edit']) { throw new \DomainException('Çalışan bulunamadı.'); }
                        $data['assigned']=array_map('intval',array_column(R::rows('SELECT service_id FROM employee_services WHERE employee_id=?',[$id]),'service_id'));
                        foreach (R::rows('SELECT * FROM working_hours WHERE employee_id=? ORDER BY start_time',[$id]) as $hour) { $data['hours'][(int)$hour['weekday']]=$hour; }
                        $data['leaves']=R::rows('SELECT * FROM employee_leaves WHERE employee_id=? ORDER BY start_at DESC',[$id]);
                    }
                    break;
                case 'customers':
                    $search=Input::text($_GET,'q',100,false); $active=Input::text($_GET,'active',1,false); $where="role='CUSTOMER'"; $values=[];
                    if ($search !== '') { $where.=' AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)'; $values=array_fill(0,3,'%'.$search.'%'); }
                    if (in_array($active,['0','1'],true)) { $where.=' AND is_active=?'; $values[]=(int)$active; }
                    $count=(int)R::one('SELECT COUNT(*) AS n FROM users WHERE '.$where,$values)['n'];
                    $number=isset($_GET['p']) ? Input::id($_GET['p']) : 1; $number=min($number,max(1,(int)ceil($count/25))); $offset=($number-1)*25;
                    $data['customers']=R::rows('SELECT id,name,email,phone,is_active,created_at FROM users WHERE '.$where.' ORDER BY name,id LIMIT 25 OFFSET '.$offset,$values);
                    $data+=['search'=>$search,'active'=>$active,'number'=>$number,'count'=>$count]; $data['detail']=null;
                    if (isset($_GET['id'])) {
                        $data['detail']=R::one("SELECT id,name,email,phone,is_active,created_at FROM users WHERE id=? AND role='CUSTOMER'",[Input::id($_GET['id'])]);
                        if (!$data['detail']) { throw new \DomainException('Müşteri bulunamadı.'); }
                        $data['history']=R::appointments('a.customer_id=?',[$data['detail']['id']],null);
                    }
                    break;
                case 'appointments':
                    $date=Input::text($_GET,'date',10,false); $status=Input::text($_GET,'status',12,false); $where='1=1'; $values=[];
                    if ($date !== '') { $day=Input::date($date); $where.=' AND a.start_at>=? AND a.start_at<?'; $values[]=$date; $values[]=$day->modify('+1 day')->format('Y-m-d'); }
                    if ($status !== '') { if (!in_array($status,['pending','confirmed','completed','cancelled'],true)) { throw new \DomainException('Geçersiz durum filtresi.'); } $where.=' AND a.status=?'; $values[]=$status; }
                    $data['appointments']=R::appointments($where,$values); $data['employees']=R::employees(); $data['services']=R::services();
                    $data['customers']=R::rows("SELECT id,name,email FROM users WHERE role='CUSTOMER' AND is_active=1 ORDER BY name,id");
                    $data+=['date'=>$date,'status'=>$status];
                    break;
                case 'calendar':
                    $month=Input::text($_GET,'month',7,false) ?: date('Y-m'); $start=Input::date($month.'-01'); $end=$start->modify('+1 month');
                    $data+=['month'=>$month,'start'=>$start,'end'=>$end,'appointments'=>R::appointments('a.start_at>=? AND a.start_at<?',[$start->format('Y-m-d'),$end->format('Y-m-d')],null)]; break;
                case 'reports':
                    $from=Input::text($_GET,'from',10,false) ?: date('Y-01-01'); $to=Input::text($_GET,'to',10,false) ?: date('Y-m-d');
                    $start=Input::date($from); $end=Input::date($to)->modify('+1 day');
                    if ($end <= $start || $end->diff($start)->days>3660) { throw new \DomainException('Rapor tarih aralığı geçersiz veya 10 yıldan uzun.'); }
                    $data+=['from'=>$from,'to'=>$to,'reports'=>(new StatisticsService())->reports($from,$end->format('Y-m-d'))]; break;
                default: throw new \DomainException('Sayfa bulunamadı.');
            }
        } catch (\DomainException $error) { http_response_code(422); View::render('errors/error',['title'=>'Geçersiz istek','message'=>$error->getMessage()]); return; }
        View::render('admin/'.$page,$data);
    }
    public function mutate(string $action): void
    {
        $actor=$this->authorize(); if (!$actor) { return; }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) { http_response_code(403); View::render('errors/error',['title'=>'403','message'=>'Güvenlik doğrulaması başarısız. Formu yenileyin.']); return; }
        $destination=match($action) { 'review-visibility'=>'admin/reviews','service-save','service-delete'=>'admin/services', 'employee-save','leave-add','leave-remove'=>'admin/employees', 'customer-status'=>'admin/customers', default=>'admin/appointments' };
        try {
            $management=new ManagementService(); $message='İşlem başarıyla tamamlandı.';
            switch($action) {
                case 'service-save': $id=$management->saveService($_POST,$_FILES['image'] ?? null); $destination.='?id='.$id; break;
                case 'service-delete': $message=$management->deleteService(Input::id($_POST['id'] ?? null)); break;
                case 'review-visibility':$visible=Input::text($_POST,'visible',1);if(!in_array($visible,['0','1'],true)){throw new \DomainException('Geçersiz görünürlük.');}(new \App\Services\ReviewService())->visibility(Input::id($_POST['id']??null),$visible==='1',(int)$actor['id']);break;
                case 'employee-save': $id=$management->saveEmployee($_POST); $destination.='?id='.$id; break;
                case 'leave-add': $management->addLeave($_POST); $destination.='?id='.Input::id($_POST['employee_id'] ?? null); break;
                case 'leave-remove': $management->removeLeave(Input::id($_POST['id'] ?? null)); break;
                case 'customer-status':
                    $active=Input::text($_POST,'active',1); if (!in_array($active,['0','1'],true)) { throw new \DomainException('Geçersiz hesap durumu.'); }
                    $id=Input::id($_POST['id'] ?? null); $management->customerStatus($id,$active==='1'); $destination.='?id='.$id; break;
                case 'appointment-create': $id=(new AppointmentService())->create($_POST,(int)$actor['id']); $message='Randevu oluşturuldu (bekliyor).'; break;
                case 'appointment-status': (new AppointmentService())->status(Input::id($_POST['id'] ?? null),Input::text($_POST,'status',12),(int)$actor['id']); break;
                default: throw new \DomainException('İşlem bulunamadı.');
            }
            \App\Services\AuditService::record((int)$actor['id'],$action,'admin',isset($id)?(int)$id:null);
            if($action!=='review-visibility'){\App\Services\AuditService::record((int)$actor['id'],$action,'admin',isset($id)?(int)$id:(isset($_POST['id'])&&is_scalar($_POST['id'])&&ctype_digit((string)$_POST['id'])?(int)$_POST['id']:null));}
            $_SESSION['admin_notice']=$message;
        } catch (\DomainException $error) {
            \App\Services\AuditService::record((int)$actor['id'],$action,'admin',null,'failure');
            $_SESSION['admin_error']=$error->getMessage();
            // Retain text selections, never passwords or upload content.
            $old=$_POST; unset($old['password'],$old['password_confirmation'],$old['_csrf']); $_SESSION['admin_old']=['action'=>$action,'input'=>$old];
            if (in_array($action,['service-save','employee-save'],true) && !empty($_POST['id'])) { try { $destination.='?id='.Input::id($_POST['id']); } catch (\DomainException) {} }
        } catch (\PDOException $error) {
            if ((int)($error->errorInfo[1] ?? 0)===1062) { $_SESSION['admin_error']='Bu e-posta zaten kullanılıyor veya aynı kayıt mevcut.'; }
            else { throw $error; }
        }
        header('Location: '.\url($destination),true,303);
    }
    public function availability(): void
    {
        if (!$this->authorize()) { return; }
        header('Content-Type: application/json; charset=UTF-8');
        try { $slots=(new AvailabilityService())->slots(Input::id($_GET['employee_id'] ?? null),Input::id($_GET['service_id'] ?? null),Input::text($_GET,'date',10)); echo json_encode(['slots'=>$slots],JSON_THROW_ON_ERROR); }
        catch (\DomainException $error) { http_response_code(422); echo json_encode(['error'=>$error->getMessage()],JSON_THROW_ON_ERROR); }
    }
}
