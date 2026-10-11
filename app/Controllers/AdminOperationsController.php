<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Csrf,Input,View};
use App\Middleware\{AuthMiddleware,RoleMiddleware};
use App\Models\{AdminRepository as R,EmployeeRepository as E};
use App\Services\EmployeeOperationsService as Operations;
final class AdminOperationsController
{
 private function authorize():?array{$user=AuthMiddleware::user();return$user&&RoleMiddleware::authorize($user,'ADMIN')?$user:null;}
 public function page():void
 {
  $user=$this->authorize();if(!$user){return;}try{
   $id=isset($_GET['employee_id'])&&$_GET['employee_id']!==''?Input::id($_GET['employee_id']):0;$status=Input::text($_GET,'status',8,false)?:'pending';if(!in_array($status,['pending','approved','rejected','all'],true)){throw new \DomainException('Geçersiz talep filtresi.');}
   $data=['user'=>$user,'page'=>'operations','title'=>'Operasyon · Luméra','employees'=>R::employees(),'selected'=>$id,'status'=>$status,'edit'=>null,'permissions'=>[],'hours'=>[],'breaks'=>[]];if($id){$data['edit']=R::one('SELECT e.*,u.name FROM employees e JOIN users u ON u.id=e.user_id WHERE e.id=?',[$id]);if(!$data['edit']){throw new \DomainException('Çalışan bulunamadı.');}$data['permissions']=E::permissions($id);$data['hours']=E::hours($id);$data['breaks']=E::breaks($id);}
   $where='1=1';$args=[];if($id){$where.=' AND q.employee_id=?';$args[]=$id;}if($status!=='all'){$where.=' AND q.status=?';$args[]=$status;}
   $data['leaveRequests']=R::rows('SELECT q.*,u.name FROM employee_leave_requests q JOIN employees e ON e.id=q.employee_id JOIN users u ON u.id=e.user_id WHERE '.$where.' ORDER BY q.id DESC LIMIT 200',$args);$operations=new Operations();foreach($data['leaveRequests']as&$q){$q['conflicts']=$q['status']==='pending'?$operations->conflicts($q):[];}unset($q);
   $data['cancelRequests']=R::rows('SELECT q.*,a.start_at,a.status appointment_status,s.name service_name,u.name employee_name,c.name customer_name FROM appointment_cancellation_requests q JOIN appointments a ON a.id=q.appointment_id JOIN services s ON s.id=a.service_id JOIN employees e ON e.id=q.employee_id JOIN users u ON u.id=e.user_id JOIN users c ON c.id=a.customer_id WHERE '.$where.' ORDER BY q.id DESC LIMIT 200',$args);
   $from=date('Y-m-01');$until=(new \DateTimeImmutable('first day of next month'))->format('Y-m-d');
   $data['distribution']=R::rows("SELECT e.id,u.name,COUNT(a.id) total,COALESCE(SUM(a.status='pending'),0) pending,COALESCE(SUM(a.status='confirmed'),0) confirmed,COALESCE(SUM(a.status='completed'),0) completed,COALESCE(SUM(CASE WHEN a.status='completed' THEN a.service_price ELSE 0 END),0) estimated FROM employees e JOIN users u ON u.id=e.user_id LEFT JOIN appointments a ON a.employee_id=e.id AND a.start_at>=? AND a.start_at<? GROUP BY e.id,u.name ORDER BY total DESC,e.id",[$from,$until]);$data['month']=date('m.Y');View::render('admin/operations',$data);
  }catch(\DomainException$e){http_response_code(422);View::render('errors/error',['title'=>'Geçersiz istek','message'=>$e->getMessage()]);}
 }
 public function mutate(string $action):void
 {
  $user=$this->authorize();if(!$user){return;}if(!Csrf::verify($_POST['_csrf']??null)){http_response_code(403);View::render('errors/error',['title'=>'403','message'=>'Form güvenlik doğrulaması başarısız.']);return;}$destination='admin/operations';
  try{$service=new Operations();switch($action){
   case 'leave-review':$service->reviewLeave(Input::id($_POST['id']??null),Input::text($_POST,'decision',8),Input::text($_POST,'response',1000,false),(int)$user['id']);break;
   case 'cancel-review':$service->reviewCancellation(Input::id($_POST['id']??null),Input::text($_POST,'decision',8),Input::text($_POST,'response',1000,false),(int)$user['id']);break;
   case 'permissions':$id=Input::id($_POST['employee_id']??null);$service->permissions($id,$_POST);$destination.='?employee_id='.$id;break;
   case 'break-add':$service->addBreak($_POST);$destination.='?employee_id='.Input::id($_POST['employee_id']??null);break;
   case 'break-remove':$id=Input::id($_POST['id']??null);$row=R::one('SELECT employee_id FROM employee_breaks WHERE id=?',[$id]);$service->removeBreak($id);$destination.='?employee_id='.$row['employee_id'];break;
   default:throw new \DomainException('İşlem bulunamadı.');
  }\App\Services\AuditService::record((int)$user['id'],$action,'operations',isset($id)?(int)$id:null);$_SESSION['admin_notice']='Operasyon işlemi tamamlandı.';}catch(\DomainException$e){$_SESSION['admin_error']=$e->getMessage();}catch(\PDOException$e){if((int)($e->errorInfo[1]??0)!==1062){throw$e;}$_SESSION['admin_error']='Aynı kayıt zaten var.';}header('Location: '.\url($destination),true,303);
 }
}
