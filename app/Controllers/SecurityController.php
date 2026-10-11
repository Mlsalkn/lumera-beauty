<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Csrf,View};
use App\Services\{AccountRecoveryService,MailService,RateLimiter};
final class SecurityController
{
 public function page(string $mode,array $errors=[]):void {View::render('auth/security',['title'=>'Hesap güvenliği · Luméra','mode'=>$mode,'errors'=>$errors,'enabled'=>MailService::configured(),'token'=>is_string($_GET['token']??$_POST['token']??null)?($_GET['token']??$_POST['token']):'']);}
 public function mutate(string $mode):void {
  if(!Csrf::verify($_POST['_csrf']??null)){http_response_code(403);$this->page($mode,['Formun süresi doldu. Sayfayı yenileyin.']);return;}
  $retry=(new RateLimiter())->critical('security:'.$mode,is_string($_POST['email']??null)?strtolower(trim($_POST['email'])):hash('sha256',is_string($_POST['token']??null)?$_POST['token']:''),$_SERVER['REMOTE_ADDR']??'unknown');
  if($retry){http_response_code(429);header('Retry-After: '.$retry);$this->page($mode,['Çok fazla istek. Daha sonra tekrar deneyin.']);return;}
  try {
   $service=new AccountRecoveryService();
   if(in_array($mode,['forgot-password','resend-verification'],true)){
    if(!MailService::configured()){throw new \DomainException('E-posta gönderimi henüz yapılandırılmadı. Lütfen merkezle iletişime geçin.');}
    $email=$_POST['email']??'';if(!is_string($email)||strlen($email)>190||!filter_var($email,FILTER_VALIDATE_EMAIL)){throw new \DomainException('Geçerli bir e-posta adresi girin.');}
    $service->request($email,$mode==='forgot-password'?'reset':'verify');
    $_SESSION['notice']='Adres uygun bir hesaba aitse bağlantı gönderim kuyruğuna eklendi. Gelen kutunuzu kontrol edin.';
   }else{
    $password=$_POST['password']??null;
    if($mode==='reset-password'&&(!is_string($password)||$password!==($_POST['password_confirmation']??null))){throw new \DomainException('Şifreler aynı olmalıdır.');}
    $token=$_POST['token']??'';if(!is_string($token)){throw new \DomainException('Geçersiz bağlantı.');}
    $service->redeem($token,$mode==='reset-password'?'reset':'verify',$mode==='reset-password'?$password:null);
    $_SESSION['notice']=$mode==='reset-password'?'Şifreniz yenilendi. Yeni şifrenizle giriş yapın.':'E-posta adresiniz doğrulandı.';
   }
   header('Location: '.\url('login'),true,303);
  }catch(\DomainException $error){http_response_code(422);$this->page($mode,[$error->getMessage()]);}
 }
}
