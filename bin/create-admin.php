<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
use App\Core\{Password, Validator};
use App\Models\User;
// JSON on standard input keeps secrets out of arguments, URLs and environment files.
$input = json_decode(stream_get_contents(STDIN), true, 16, JSON_THROW_ON_ERROR);
if (!is_array($input)) { throw new InvalidArgumentException('JSON nesnesi gerekli.'); }
$errors = Validator::registration($input);
if ($errors) { fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL); exit(1); }
$email = strtolower(trim($input['email']));
if ((new User())->findByEmail($email)) { fwrite(STDERR, "Hesap zaten mevcut; değiştirilmedi.\n"); exit(1); }
(new User())->create(trim($input['name']), $email, null, Password::hash($input['password']), 'ADMIN');
unset($input);
echo "Yönetici hesabı oluşturuldu. Parola kayıtlara yazılmadı.\n";
