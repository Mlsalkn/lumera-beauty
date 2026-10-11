<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
use App\Services\DeploymentReadiness as D;
$valid = ['APP_URL'=>'https://beauty.example.org','MAIL_DRIVER'=>'smtp',
    'SMTP_HOST'=>'smtp.example.org','SMTP_PORT'=>'587','SMTP_ENCRYPTION'=>'tls',
    'SMTP_USERNAME'=>'fixture','SMTP_PASSWORD'=>'fixture','MAIL_FROM_ADDRESS'=>'fixture@example.invalid',
    'MAIL_ENCRYPTION_KEY'=>base64_encode(random_bytes(32))];
$passed = 0;
$check = static function(bool $ok, string $label) use (&$passed): void {
    if (!$ok) { throw new RuntimeException('FAIL: '.$label); }
    $passed++; echo 'PASS: '.$label.PHP_EOL;
};
$check(D::inspect($valid) === [], 'Complete configuration accepted without network');
foreach (['http://beauty.example.org','https://localhost','https://127.0.0.1',
    'https://[::1]','https://demo.localhost','https://user:secret@beauty.example.org','invalid'] as $url) {
    $check(D::inspect(array_replace($valid,['APP_URL'=>$url])) !== [], 'Unsafe URL rejected');
}
foreach (['MAIL_DRIVER'=>'smtp_test','SMTP_HOST'=>'','SMTP_USERNAME'=>'','SMTP_PASSWORD'=>'',
    'SMTP_PORT'=>'65536','SMTP_ENCRYPTION'=>'none','MAIL_FROM_ADDRESS'=>'invalid','MAIL_ENCRYPTION_KEY'=>'bad'] as $key=>$value) {
    $issues = D::inspect(array_replace($valid,[$key=>$value]));
    $check($issues !== [], $key.' invalid configuration rejected');
}
$secret = bin2hex(random_bytes(16));
$issues = D::inspect(array_replace($valid,['APP_URL'=>'https://user:'.$secret.'@beauty.example.org']));
$check(!str_contains(implode(' ', $issues), $secret), 'Diagnostics do not expose credentials');
echo $passed." readiness tests passed (no database or mail).\n";
