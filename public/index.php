<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
if(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'){header('Strict-Transport-Security: max-age=31536000');}
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; font-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
$router = new App\Core\Router();
App\Core\Session::start();
header('Cache-Control: no-store, private');
$router->get('/', [new App\Controllers\HomeController(), 'index']);
$support=new App\Controllers\SupportController();
$router->get('/support',static fn()=>(new App\Controllers\CatalogController())->page('contact'));
$router->post('/contact/support',[$support,'create']);
$router->get('/admin/support',[$support,'page']);
$router->post('/admin/support/status',[$support,'status']);
$catalog=new App\Controllers\CatalogController();
foreach(['services','experts','about','contact','reviews']as$page){$router->get('/'.$page,static fn()=>$catalog->page($page));}
$router->get('/services/detail',static fn()=>$catalog->page('service'));
$router->get('/customer/reviews',static fn()=>(new App\Controllers\CustomerController())->page('reviews'));
$router->post('/customer/reviews/save',static fn()=>(new App\Controllers\CustomerController())->mutate('review'));
$router->get('/admin/reviews',static fn()=>(new App\Controllers\AdminController())->page('reviews'));
$router->post('/admin/reviews/visibility',static fn()=>(new App\Controllers\AdminController())->mutate('review-visibility'));
$auth = new App\Controllers\AuthController();
$router->get('/register', static fn() => $auth->form('register'));
$router->post('/register', [$auth, 'register']);
$router->get('/login', static fn() => $auth->form('login'));
$router->post('/login', [$auth, 'login']);
$router->post('/logout', [$auth, 'logout']);
$security=new App\Controllers\SecurityController();
foreach(['forgot-password','reset-password','verify-email','resend-verification']as$mode){$router->get('/'.$mode,static fn()=>$security->page($mode));$router->post('/'.$mode,static fn()=>$security->mutate($mode));}
$system=new App\Controllers\SystemController();
$router->get('/admin/settings',[$system,'page']);
$router->post('/admin/settings/save',[$system,'save']);
$employee = new App\Controllers\EmployeeController();
$router->get('/employee',static fn()=>$employee->page('dashboard'));
foreach(['appointments','calendar','leaves','profile','services','performance','notifications'] as $page){$router->get('/employee/'.$page,static fn()=>$employee->page($page));}
$router->get('/employee/appointments/detail',static fn()=>$employee->page('detail'));
foreach(['appointments/status'=>'status','appointments/cancellation'=>'cancellation','leaves/request'=>'leave','profile/save'=>'profile','notifications/read'=>'notification'] as $path=>$action){$router->post('/employee/'.$path,static fn()=>$employee->mutate($action));}
$operations = new App\Controllers\AdminOperationsController();
$router->get('/admin/operations',[$operations,'page']);
foreach(['leaves/review'=>'leave-review','cancellations/review'=>'cancel-review','permissions'=>'permissions','breaks/add'=>'break-add','breaks/remove'=>'break-remove'] as $path=>$action){$router->post('/admin/operations/'.$path,static fn()=>$operations->mutate($action));}
$customer = new App\Controllers\CustomerController();
$router->get('/customer',static fn()=>$customer->page('dashboard'));
foreach(['appointments','favorites','notifications','profile','book'] as $page){$router->get('/customer/'.$page,static fn()=>$customer->page($page));}
$router->get('/customer/appointments/detail',static fn()=>$customer->page('detail'));
$router->get('/customer/email/verify',static fn()=>$customer->page('email-verify'));
foreach(['book/preview'=>'preview','book/confirm'=>'confirm','appointments/cancel'=>'cancel','profile/save'=>'profile','profile/password'=>'password','profile/email'=>'email-request','email/verify'=>'email-verify','favorites/save'=>'favorite','notifications/read'=>'notification'] as $path=>$action){$router->post('/customer/'.$path,static fn()=>$customer->mutate($action));}
$admin = new App\Controllers\AdminController();
$router->get('/admin', static fn() => $admin->page('dashboard'));
foreach (['services','employees','customers','appointments','calendar','reports'] as $page) {
    $router->get('/admin/'.$page, static fn() => $admin->page($page));
}
$router->get('/admin/availability', [$admin,'availability']);
foreach (['service-save'=>'services/save','service-delete'=>'services/delete','employee-save'=>'employees/save','leave-add'=>'employees/leave','leave-remove'=>'employees/leave/remove','customer-status'=>'customers/status','appointment-create'=>'appointments/create','appointment-status'=>'appointments/status'] as $action=>$path) {
    $router->post('/admin/'.$path, static fn() => $admin->mutate($action));
}
$config = require BASE_PATH . '/config/app.php';
$routePath=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'';
if($_SERVER['REQUEST_METHOD']==='POST'&&preg_match('~/(admin|customer|employee)(/|$)~',$routePath)&&App\Core\Csrf::verify($_POST['_csrf']??null)){
 $current=App\Services\AuthService::current();
 if($current){$budget=preg_match('~/(profile/password|profile/email|email/verify)$~',$routePath)?5:60;$retry=(new App\Services\RateLimiter())->critical('panel:'.$routePath,(string)$current['id'],$_SERVER['REMOTE_ADDR']??'unknown',$budget);if($retry){http_response_code(429);header('Retry-After: '.$retry);App\Core\View::render('errors/error',['title'=>'Biraz bekleyin','message'=>'Çok fazla işlem yapıldı. Daha sonra tekrar deneyin.']);exit;}}
}
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], parse_url($config['url'], PHP_URL_PATH) ?: '');
