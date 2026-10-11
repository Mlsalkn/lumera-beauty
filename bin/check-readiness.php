<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/bootstrap.php';
$config = [];
foreach (['APP_URL','MAIL_DRIVER','SMTP_HOST','SMTP_PORT','SMTP_ENCRYPTION',
    'SMTP_USERNAME','SMTP_PASSWORD','MAIL_FROM_ADDRESS','MAIL_ENCRYPTION_KEY'] as $name) {
    $config[$name] = env($name, $name === 'SMTP_PORT' ? '587' : ($name === 'SMTP_ENCRYPTION' ? 'tls' : ''));
}
$issues = App\Services\DeploymentReadiness::inspect($config);
foreach (['pdo_mysql','mbstring','openssl','fileinfo'] as $extension) {
    if (!extension_loaded($extension)) { $issues[] = $extension . ': PHP uzantısı eksik.'; }
}
foreach ($issues as $issue) { echo 'EKSİK: ' . $issue . PHP_EOL; }
echo "Bu kontrol gönderim, bağlantı veya veri değişikliği yapmaz. DNS/sertifika erişimi, sağlayıcı teslimi ve sürekli zamanlama ayrıca doğrulanmalıdır.\n";
if (!$issues) { echo "Yapılandırma ön kontrolü başarılı.\n"; }
exit($issues ? 1 : 0);
