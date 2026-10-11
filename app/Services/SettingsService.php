<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\AdminRepository as R;
final class SettingsService
{
 public static function get(string $key,string $fallback):string {return R::one('SELECT setting_value FROM system_settings WHERE setting_key=?',[$key])['setting_value']??$fallback;}
 public function save(array $input,int $actor):void {
  $hours=$input['reminder_hours']??'';$contact=$input['contact_phone']??'';
  if(!is_string($hours)||!ctype_digit($hours)||(int)$hours<1||(int)$hours>72||!is_string($contact)||mb_strlen($contact)>40){throw new \DomainException('Hatırlatma süresi 1–72 saat, iletişim telefonu en fazla 40 karakter olmalıdır.');}
  $pdo=\App\Core\Database::connection();$pdo->beginTransaction();try{
   foreach(['reminders_enabled'=>isset($input['reminders_enabled'])?'1':'0','reminder_hours'=>$hours,'contact_phone'=>$contact]as$key=>$value){R::execute('INSERT INTO system_settings(setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',[$key,$value]);}
   AuditService::record($actor,'settings-save','settings',null);$pdo->commit();
  }catch(\Throwable $e){if($pdo->inTransaction()){$pdo->rollBack();}throw$e;}
 }
}
