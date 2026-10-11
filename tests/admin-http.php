<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || !in_array('--allow-test-data',$argv,true)) { exit("CLI: --allow-test-data gerekli; yalnızca bu testin oluşturduğu kayıtlar temizlenir.\n"); }
require dirname(__DIR__).'/bootstrap.php';
use App\Core\{Database, Password};
use App\Models\{AdminRepository as R, User};
use App\Services\{AppointmentService, AvailabilityService, ManagementService, StatisticsService};
$tag=bin2hex(random_bytes(10)); $password=bin2hex(random_bytes(20)); $sourceIp='127.0.'.random_int(1,254).'.'.random_int(1,254);
$jar=tempnam(sys_get_temp_dir(),'lumera-admin-'); $imageFile=tempnam(sys_get_temp_dir(),'lumera-img-'); $paths=[]; $userIds=[]; $employeeIds=[]; $serviceIds=[]; $passed=0;
$pdo=Database::connection();
$snapshot=static fn(): string => hash('sha256',json_encode(array_map(static fn($table)=>R::rows('SELECT * FROM '.$table.' ORDER BY '.($table==='employee_services' ? 'employee_id,service_id' : 'id')),['users','employees','services','employee_services','working_hours','appointments','reviews','employee_leaves']),JSON_THROW_ON_ERROR));
$before=$snapshot();
$check=static function(bool $condition,string $name) use (&$passed): void { if(!$condition){throw new RuntimeException('FAIL: '.$name);} echo "PASS: $name\n"; $passed++; };
$reject=static function(callable $action,string $name) use ($check): void { try{$action();$blocked=false;}catch(DomainException){$blocked=true;}$check($blocked,$name); };
$request=static function(string $path,?array $data=null,bool $upload=false) use ($jar,$sourceIp): array {
    $curl=curl_init(url($path)); curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>$jar,CURLOPT_COOKIEJAR=>$jar,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>30,CURLOPT_INTERFACE=>$sourceIp]);
    if($data!==null){curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$upload ? $data : http_build_query($data)]);}
    $response=curl_exec($curl); if($response===false){throw new RuntimeException(curl_error($curl));}
    $result=['status'=>curl_getinfo($curl,CURLINFO_RESPONSE_CODE),'headers'=>substr($response,0,curl_getinfo($curl,CURLINFO_HEADER_SIZE)),'body'=>substr($response,curl_getinfo($curl,CURLINFO_HEADER_SIZE))]; curl_close($curl); return $result;
};
$token=static function(array $response): string { if(!preg_match('/name="_csrf" value="([a-f0-9]{64})"/',$response['body'],$matches)){throw new RuntimeException('CSRF bulunamadı.');}return $matches[1]; };
$login=static function(string $email) use ($request,$token,$password): void { $csrf=$token($request('login')); $result=$request('login',['email'=>$email,'password'=>$password,'_csrf'=>$csrf]); if($result['status']!==303){throw new RuntimeException('Test girişi başarısız.');} };
try {
    $adminEmail='admin-'.$tag.'@example.invalid'; $customerEmail='customer-'.$tag.'@example.invalid';
    $adminId=(new User())->create('QA Yönetici '.$tag,$adminEmail,null,Password::hash($password),'ADMIN'); $userIds[]=$adminId;
    $customerId=(new User())->create('QA Müşteri '.$tag,$customerEmail,'+90 555 111 2233',Password::hash($password)); $userIds[]=$customerId;
    foreach(['admin','admin/services','admin/employees','admin/customers','admin/appointments','admin/calendar','admin/reports','admin/availability'] as $path){$check($request($path)['status']===303,'Guest denied '.$path);}
    $login($adminEmail);
    $bookingRedirect=$request('customer/book');$check($bookingRedirect['status']===303&&str_contains($bookingRedirect['headers'],'admin/appointments'),'Administrator booking navigation redirects to own appointment manager');
    $check($request('customer/book/confirm',['_csrf'=>$token($request('admin')),'nonce'=>'test'])['status']===403,'Administrator cannot submit customer booking despite navigation redirect');

    foreach(['admin','admin/services','admin/employees','admin/customers','admin/appointments','admin/calendar','admin/reports'] as $path){$check($request($path)['status']===200,'Admin page renders '.$path);}
    $csrf=$token($request('admin/services'));
    foreach(['services/save','services/delete','employees/save','employees/leave','employees/leave/remove','customers/status','appointments/create','appointments/status'] as $path){$check($request('admin/'.$path,[])['status']===403,'CSRF required '.$path);}
    $check($request('admin/services/save')['status']===405,'GET mutation blocked');
    $serviceInput=['name'=>'QA Hizmet '.$tag,'category'=>'QA Kategori','description'=>'Özenli bakım <script>alert(1)</script>','price'=>'1234.50','duration_minutes'=>'60','is_active'=>'1','_csrf'=>$csrf];
    $check($request('admin/services/save',array_replace($serviceInput,['duration_minutes'=>'-1']))['status']===303,'Invalid service handled');
    $check(!R::one('SELECT id FROM services WHERE name=?',[$serviceInput['name']]),'Invalid service writes no row');
    file_put_contents($imageFile,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jXioAAAAASUVORK5CYII='));
    $imagePost=$serviceInput; $imagePost['image']=new CURLFile($imageFile,'image/png','test.png');
    $result=$request('admin/services/save',$imagePost,true);
    $service=R::one('SELECT * FROM services WHERE name=?',[$serviceInput['name']]); if($service){$serviceId=(int)$service['id'];$serviceIds[]=$serviceId;if($service['image_path']){$paths[]=$service['image_path'];}}
    $check($result['status']===303 && (bool)$service,'Service with image created through HTTP');
    $check((bool)$service['image_path'] && $request($service['image_path'])['status']===200,'Uploaded image publicly served');
    $body=$request('admin/services?id='.$serviceId)['body']; $check(str_contains($body,'&lt;script&gt;') && !str_contains($body,'<script>alert(1)</script>'),'Stored description escaped');
    $edit=$serviceInput+['id'=>(string)$serviceId]; $edit['price']='1450.75';
    $check($request('admin/services/save',$edit)['status']===303 && R::one('SELECT price FROM services WHERE id=?',[$serviceId])['price']==='1450.75','Service edit persists price');
    $badUpload=$edit; $badUpload['image']=new CURLFile(__FILE__,'text/plain','attack.php'); $request('admin/services/save',$badUpload,true);
    $check(R::one('SELECT image_path FROM services WHERE id=?',[$serviceId])['image_path']===$service['image_path'],'Executable upload rejected without replacing image');
    $days=$starts=$ends=[];foreach(range(1,7) as $day){$days[$day]='1';$starts[$day]='09:00';$ends[$day]='18:00';}
    $employeeInput=['name'=>'QA Çalışan '.$tag,'email'=>'employee-'.$tag.'@example.invalid','phone'=>'+90 555 222 3344','title'=>'Cilt uzmanı','bio'=>'QA uzmanlık','password'=>$password,'password_confirmation'=>$password,'services'=>[(string)$serviceId],'days'=>$days,'starts'=>$starts,'ends'=>$ends,'is_active'=>'1','_csrf'=>$csrf];
    $result=$request('admin/employees/save',$employeeInput); $account=(new User())->findByEmail($employeeInput['email']);if($account){$userIds[]=(int)$account['id'];}
    $worker=$account ? R::one('SELECT * FROM employees WHERE user_id=?',[$account['id']]) : null;if($worker){$employeeId=(int)$worker['id'];$employeeIds[]=$employeeId;}
    $check($result['status']===303 && (bool)$worker && $account['role']==='EMPLOYEE','Employee and secure account created');
    $check(Password::verify($password,$account['password_hash']),'Employee password hashed');
    $check(count(R::rows('SELECT * FROM working_hours WHERE employee_id=?',[$employeeId]))===7 && (bool)R::one('SELECT * FROM employee_services WHERE employee_id=? AND service_id=?',[$employeeId,$serviceId]),'Schedule and service assignment persisted');
    $editWorker=$employeeInput;unset($editWorker['password'],$editWorker['password_confirmation']);$editWorker['id']=(string)$employeeId;$editWorker['title']='Kıdemli uzman';
    $check($request('admin/employees/save',$editWorker)['status']===303 && R::one('SELECT title FROM employees WHERE id=?',[$employeeId])['title']==='Kıdemli uzman','Employee edit without password preserves account');
    $brokenWorker=array_replace($employeeInput,['email'=>'rollback-'.$tag.'@example.invalid','services'=>['9999999999']]);
    $reject(fn()=>(new ManagementService())->saveEmployee($brokenWorker),'Invalid service assignment rolls employee transaction back');
    $check(!(new User())->findByEmail($brokenWorker['email']),'Failed employee creation leaves no orphan account');
    $check($request('admin/customers?q='.urlencode($tag).'&active=1')['status']===200,'Customer search and active filter');
    $check(str_contains($request('admin/customers?id='.$customerId)['body'],$customerEmail),'Customer detail shows contact');
    $date=date('Y-m-d',strtotime('+2 days')); $availability=$request('admin/availability?'.http_build_query(['employee_id'=>$employeeId,'service_id'=>$serviceId,'date'=>$date]));$slots=json_decode($availability['body'],true)['slots'] ?? [];
    $check($availability['status']===200 && in_array('09:00',$slots,true) && in_array('17:00',$slots,true) && !in_array('17:15',$slots,true),'Availability respects full service duration');
    $booking=['customer_id'=>(string)$customerId,'employee_id'=>(string)$employeeId,'service_id'=>(string)$serviceId,'start_at'=>$date.'T09:00','_csrf'=>$csrf];
    $result=$request('admin/appointments/create',$booking);$appointment=R::one('SELECT * FROM appointments WHERE employee_id=? AND start_at=?',[$employeeId,$date.' 09:00:00']);
    $check($result['status']===303 && (bool)$appointment && $appointment['end_at']===$date.' 10:00:00','Appointment created with computed end');
    $appointmentId=(int)$appointment['id'];$check($appointment['service_price']==='1450.75' && $appointment['status']==='pending','Booking stores price snapshot and pending status');
    $request('admin/appointments/create',array_replace($booking,['start_at'=>$date.'T09:30']));
    $check(count(R::rows('SELECT id FROM appointments WHERE employee_id=?',[$employeeId]))===1,'Overlapping interval rejected');
    $request('admin/appointments/status',['id'=>$appointmentId,'status'=>'confirmed','_csrf'=>$csrf]);$check(R::one('SELECT status FROM appointments WHERE id=?',[$appointmentId])['status']==='confirmed','Pending appointment approved');
    $request('admin/appointments/status',['id'=>$appointmentId,'status'=>'completed','_csrf'=>$csrf]);$check(R::one('SELECT status FROM appointments WHERE id=?',[$appointmentId])['status']==='confirmed','Future appointment cannot complete');
    $reject(fn()=>(new ManagementService())->addLeave(['employee_id'=>$employeeId,'start_at'=>$date.'T09:15','end_at'=>$date.'T09:45']), 'Leave overlapping booking rejected');
    $restricted=$editWorker;$restricted['starts']=array_fill(1,7,'11:00');
    $reject(fn()=>(new ManagementService())->saveEmployee($restricted),'Schedule edit cannot strand existing booking');
    $reject(fn()=>(new ManagementService())->saveEmployee(array_replace($editWorker,['services'=>[]])),'Cannot remove service assigned to active booking');
    $inactiveWorker=$editWorker;unset($inactiveWorker['is_active']);
    $reject(fn()=>(new ManagementService())->saveEmployee($inactiveWorker),'Cannot deactivate employee with active booking');
    $request('admin/appointments/status',['id'=>$appointmentId,'status'=>'cancelled','_csrf'=>$csrf]);$check(R::one('SELECT status FROM appointments WHERE id=?',[$appointmentId])['status']==='cancelled','Appointment cancelled');
    $check(in_array('09:00',(new AvailabilityService())->slots($employeeId,$serviceId,$date),true),'Cancelled appointment releases interval');
    $request('admin/appointments/status',['id'=>$appointmentId,'status'=>'confirmed','_csrf'=>$csrf]);$check(R::one('SELECT status FROM appointments WHERE id=?',[$appointmentId])['status']==='cancelled','Cancelled appointment cannot resurrect');
    $leaveInput=['employee_id'=>$employeeId,'start_at'=>$date.'T10:00','end_at'=>$date.'T12:00','reason'=>'QA izin','_csrf'=>$csrf];$request('admin/employees/leave',$leaveInput);$leave=R::one('SELECT * FROM employee_leaves WHERE employee_id=?',[$employeeId]);
    $check((bool)$leave,'Employee leave saved');$slots=(new AvailabilityService())->slots($employeeId,$serviceId,$date);$check(in_array('09:00',$slots,true) && !in_array('09:15',$slots,true) && !in_array('10:00',$slots,true) && in_array('12:00',$slots,true),'Leave blocks complete interval but allows adjacent bookings');
    $reject(fn()=>(new AppointmentService())->create(array_replace($booking,['start_at'=>$date.'T10:00'])),'Booking during leave rejected');
    $request('admin/employees/leave/remove',['id'=>$leave['id'],'_csrf'=>$csrf]);$check(!R::one('SELECT id FROM employee_leaves WHERE id=?',[$leave['id']]),'Leave removal works');
    $request('admin/customers/status',['id'=>$customerId,'active'=>'0','_csrf'=>$csrf]);$check((new User())->find($customerId)['is_active']==0,'Customer deactivation works');
    $reject(fn()=>(new AppointmentService())->create(array_replace($booking,['start_at'=>$date.'T12:00'])),'Inactive customer cannot book');
    $request('admin/customers/status',['id'=>$adminId,'active'=>'0','_csrf'=>$csrf]);$check((new User())->find($adminId)['is_active']==1,'Customer status cannot deactivate administrator');
    $request('admin/customers/status',['id'=>$customerId,'active'=>'1','_csrf'=>$csrf]);$check((new User())->find($customerId)['is_active']==1,'Customer reactivation works');
    // Equal simultaneous requests are released from one employee lock together.
    $pdo->beginTransaction();R::one('SELECT id FROM employees WHERE id=? FOR UPDATE',[$employeeId]);$children=[];
    for($i=0;$i<3;$i++){$pipes=[];$process=proc_open([PHP_BINARY,BASE_PATH.'/tests/booking-worker.php'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);if(!is_resource($process)){throw new RuntimeException('Worker açılamadı.');}fwrite($pipes[0],json_encode(array_replace($booking,['start_at'=>$date.'T13:00'])));fclose($pipes[0]);$children[]=[$process,$pipes];}
    $pdo->commit();$results=[];foreach($children as [$process,$pipes]){$results[]=stream_get_contents($pipes[1]);fclose($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[2]);$exit=proc_close($process);$check($exit===0,'Concurrent booking worker returns normally');}
    $check(count(array_filter($results,static fn($r)=>$r==='CREATED'))===1 && count(array_filter($results,static fn($r)=>$r==='BLOCKED'))===2,'Concurrent requests create exactly one appointment');
    $check(count(R::rows('SELECT id FROM appointments WHERE employee_id=? AND start_at=?',[$employeeId,$date.' 13:00:00']))===1,'No double booking in real database');
    $reject(fn()=>(new AppointmentService())->create(array_replace($booking,['start_at'=>date('Y-m-d',strtotime('-1 day')).'T09:00'])),'Past booking rejected');
    $reject(fn()=>(new AppointmentService())->create(array_replace($booking,['start_at'=>$date.'T17:30'])),'Booking cannot extend beyond closing');
    $later=(new AppointmentService())->create(array_replace($booking,['start_at'=>$date.'T14:00']));$check($later>0,'Adjacent appointment boundary accepted');
    $edit['price']='1999.00';$edit['duration_minutes']='30';$request('admin/services/save',$edit);
    $check(R::one('SELECT service_price,end_at FROM appointments WHERE id=?',[$later])===['service_price'=>'1450.75','end_at'=>$date.' 15:00:00'],'Price and duration edits preserve historical booking snapshot');
    $check(str_contains($request('admin/calendar?month='.substr($date,0,7))['body'],'QA Müşteri '.$tag),'Calendar renders actual booking');
    $check(str_contains($request('admin/customers?id='.$customerId)['body'],$serviceInput['name']),'Customer history connected to appointments');
    $past=date('Y-m-d',strtotime('-2 days'));$reportBefore=(new StatisticsService())->between($past,date('Y-m-d',strtotime('-1 day')));
    R::execute("INSERT INTO appointments (customer_id,employee_id,service_id,start_at,end_at,status,service_price) VALUES (?,?,?,?,?,'confirmed',?)",[$customerId,$employeeId,$serviceId,$past.' 09:00:00',$past.' 10:00:00','250.25']);$pastId=(int)$pdo->lastInsertId();
    $request('admin/appointments/status',['id'=>$pastId,'status'=>'completed','_csrf'=>$csrf]);$check(R::one('SELECT status FROM appointments WHERE id=?',[$pastId])['status']==='completed','Elapsed confirmed appointment completes');
    $reportAfter=(new StatisticsService())->between($past,date('Y-m-d',strtotime('-1 day')));
    $check((int)$reportAfter['completed']===(int)$reportBefore['completed']+1 && abs((float)$reportAfter['estimated']-(float)$reportBefore['estimated']-250.25)<0.001,'Reports sum only completed price snapshots');
    $check($request('admin/reports?from='.$past.'&to='.$date)['status']===200 && str_contains($request('admin/reports?from='.$past.'&to='.$date)['body'],'Tahmini hizmet tutarı'),'Reports render with accurate estimate label');
    $request('admin/services/delete',['id'=>$serviceId,'_csrf'=>$csrf]);$check(R::one('SELECT is_active FROM services WHERE id=?',[$serviceId])['is_active']==0,'Referenced service is deactivated rather than deleted');
    $check((bool)R::one('SELECT id FROM appointments WHERE id=?',[$pastId]),'Completed appointment history survives service deletion request');
    $reject(fn()=>(new AppointmentService())->create(array_replace($booking,['start_at'=>$date.'T16:00'])),'Inactive service cannot book');
    $unreferenced=(new ManagementService())->saveService(['name'=>'QA Delete '.$tag,'category'=>'QA','price'=>'0','duration_minutes'=>'30','is_active'=>'1']);$serviceIds[]=$unreferenced;
    $request('admin/services/delete',['id'=>$unreferenced,'_csrf'=>$csrf]);$check(!R::one('SELECT id FROM services WHERE id=?',[$unreferenced]),'Unreferenced service safely deleted');
    foreach(['admin/customers?id='.$adminId,'admin/reports?from=2026-02-30','admin/calendar?month=invalid','admin/availability?employee_id[]=1'] as $bad){$check($request($bad)['status']===422,'Invalid input handled '.$bad);}
    $request('logout',['_csrf'=>$csrf]);$login($customerEmail);
    foreach(['admin','admin/services','admin/employees','admin/customers','admin/appointments','admin/calendar','admin/reports','admin/availability'] as $path){$check($request($path)['status']===403,'Customer denied '.$path);}
    $customerCsrf=$token($request('customer'));
    $check($request('admin/services/delete',['id'=>$serviceId,'_csrf'=>$customerCsrf])['status']===403,'Non-admin write forbidden even with valid CSRF');
    $request('logout',['_csrf'=>$customerCsrf]);$login($employeeInput['email']);
    foreach(['admin','admin/services','admin/employees','admin/customers','admin/appointments','admin/calendar','admin/reports','admin/availability'] as $path){$check($request($path)['status']===403,'Employee denied '.$path);}
    $employeeCsrf=$token($request('employee'));
    $check($request('admin/appointments/create',array_replace($booking,['_csrf'=>$employeeCsrf]))['status']===403,'Employee cannot create admin booking');
} finally {
    if($pdo->inTransaction()){$pdo->rollBack();}
    foreach($employeeIds as $id){R::execute('DELETE n FROM notifications n JOIN appointments a ON a.id=n.appointment_id WHERE a.employee_id=?',[$id]);R::execute('DELETE h FROM appointment_status_history h JOIN appointments a ON a.id=h.appointment_id WHERE a.employee_id=?',[$id]);R::execute('DELETE FROM appointments WHERE employee_id=?',[$id]);R::execute('DELETE FROM employee_leaves WHERE employee_id=?',[$id]);R::execute('DELETE FROM working_hours WHERE employee_id=?',[$id]);R::execute('DELETE FROM employee_services WHERE employee_id=?',[$id]);R::execute('DELETE FROM employees WHERE id=?',[$id]);}
    foreach($serviceIds as $id){R::execute('DELETE FROM services WHERE id=? AND name LIKE ?',[$id,'%'.$tag]);}
    foreach($userIds as $id){R::execute('DELETE FROM notifications WHERE customer_id=?',[$id]);R::execute('DELETE FROM audit_events WHERE actor_id=?',[$id]);R::execute('DELETE FROM users WHERE id=? AND email LIKE ?',[$id,'%'.$tag.'@example.invalid']);}
    foreach($paths as $path){if(is_file(BASE_PATH.'/public/'.$path)){unlink(BASE_PATH.'/public/'.$path);}}
    foreach([$jar,$imageFile] as $file){if(is_file($file)){unlink($file);}}
    foreach(['admin-'.$tag.'@example.invalid','customer-'.$tag.'@example.invalid'] as $email){$file=BASE_PATH.'/storage/rate_limits/'.hash('sha256','email:'.$email).'.json';if(is_file($file)){unlink($file);}}
    $file=BASE_PATH.'/storage/rate_limits/'.hash('sha256','ip:'.$sourceIp).'.json';if(is_file($file)){unlink($file);}
    $check($before===$snapshot(),'All pre-existing database rows preserved');
}
echo "$passed admin module tests passed.\n";
