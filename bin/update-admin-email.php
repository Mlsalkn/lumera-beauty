<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!in_array($argv[1]??'', ['--check','--apply'],true))exit("CLI: --check veya --apply gerekli.\n");
require dirname(__DIR__).'/bootstrap.php';
use App\Core\Database;use App\Models\AdminRepository as R;
$email='alkanmelisa12@gmail.com';$pdo=Database::connection();$pdo->beginTransaction();
try{
 $admins=R::rows("SELECT * FROM users WHERE role='ADMIN' ORDER BY id FOR UPDATE");if(count($admins)!==1){throw new DomainException('Tek yönetici hesabı bulunamadı. Hiçbir hesap değiştirilmedi.');}$admin=$admins[0];
 if(R::one('SELECT id FROM users WHERE email=? AND id<>? FOR UPDATE',[$email,$admin['id']])){throw new DomainException('Yeni adres başka hesapta kayıtlı. Hiçbir hesap değiştirilmedi.');}
 if($argv[1]==='--check'){$pdo->rollBack();echo "Tek mevcut yönetici; e-posta çakışması yok.\n";exit;}
 if(strtolower($admin['email'])!==$email){
  R::execute('UPDATE users SET email=?,auth_version=auth_version+1 WHERE id=?',[$email,$admin['id']]);
  R::execute('INSERT INTO user_security(user_id,email_verified_at) VALUES (?,NULL) ON DUPLICATE KEY UPDATE email_verified_at=NULL',[$admin['id']]);
  R::execute('UPDATE auth_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL',[$admin['id']]);
  App\Services\AuditService::record((int)$admin['id'],'admin-email-update','user',(int)$admin['id']);
 }
 $after=R::one('SELECT * FROM users WHERE id=?',[$admin['id']]);if(!hash_equals($admin['password_hash'],$after['password_hash'])||$admin['role']!==$after['role']||$admin['is_active']!==$after['is_active'])throw new RuntimeException('Hesap koruma kontrolü başarısız.');
 $pdo->commit();echo "Mevcut yönetici e-postası güncel. Aynı hesap, rol ve parola hash'i korundu. Yeni adres otomatik doğrulanmadı. E-posta gönderilmedi.\n";
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof DomainException){fwrite(STDERR,$e->getMessage()."\n");exit(2);}throw$e;}
