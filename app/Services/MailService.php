<?php
declare(strict_types=1);
namespace App\Services;
use PHPMailer\PHPMailer\PHPMailer;
final class MailService
{
 public static function configured():bool { return in_array(env('MAIL_DRIVER','disabled'),['smtp','smtp_test'],true); }
 public static function seal(array $data):string {
  $key=base64_decode(env('MAIL_ENCRYPTION_KEY',''),true);
  if(!$key||strlen($key)!==32){throw new \RuntimeException('Posta şifreleme yapılandırması eksik.');}
  $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt(json_encode($data,JSON_THROW_ON_ERROR),'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);
  if($cipher===false){throw new \RuntimeException('Posta şifrelenemedi.');}
  return base64_encode($iv.$tag.$cipher);
 }
 public static function unseal(string $payload):array {
  $raw=base64_decode($payload,true);$key=base64_decode(env('MAIL_ENCRYPTION_KEY',''),true);
  if(!$raw||strlen($raw)<29||!$key||strlen($key)!==32){throw new \RuntimeException('Posta yükü geçersiz.');}
  $json=openssl_decrypt(substr($raw,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));
  if($json===false){throw new \RuntimeException('Posta yükü doğrulanamadı.');}
  return json_decode($json,true,512,JSON_THROW_ON_ERROR);
 }
 public function send(string $email,string $subject,string $body,?string $link=null):void {
  if(!self::configured()){throw new \RuntimeException('Posta gönderimi kapalı.');}
  $test=env('MAIL_DRIVER')==='smtp_test';$host=env('SMTP_HOST','');$secure=env('SMTP_ENCRYPTION','tls');
  if($test&&(!in_array($host,['127.0.0.1','::1'],true)||!str_ends_with(strtolower($email),'@example.invalid'))){throw new \RuntimeException('Test postası yalnızca yerel test alıcısına gönderilir.');}
  if(!$test&&(!in_array($secure,['tls','ssl'],true)||$host==='')){throw new \RuntimeException('Güvenli SMTP yapılandırması gerekli.');}
  $mail=new PHPMailer(true);$mail->isSMTP();$mail->Host=$host;$mail->Port=(int)env('SMTP_PORT','587');$mail->Timeout=10;$mail->SMTPDebug=0;
  $mail->SMTPAuth=!$test;$mail->Username=env('SMTP_USERNAME','');$mail->Password=env('SMTP_PASSWORD','');
  $mail->SMTPSecure=$test?'':$secure;$mail->SMTPAutoTLS=!$test;$mail->CharSet='UTF-8';
  $mail->setFrom(env('MAIL_FROM_ADDRESS',''),env('MAIL_FROM_NAME','Luméra Beauty'));$mail->addAddress($email);$mail->Subject=$subject;
  $mail->isHTML(true);$mail->AltBody=$body.($link?"\n".$link:'');
  ob_start();require BASE_PATH.'/resources/views/mail/message.php';$mail->Body=ob_get_clean();$mail->send();
 }
}
