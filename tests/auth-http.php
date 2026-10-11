<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
use App\Core\{Database, Password};
use App\Models\User;
use App\Services\RateLimiter;
if (PHP_SAPI !== 'cli' || !in_array('--allow-test-users', $argv, true)) { exit("CLI: --allow-test-users gerekli. Yalnızca testin oluşturduğu hesaplar temizlenir.\n"); }
$pdo = Database::connection();
$tag = bin2hex(random_bytes(10));
$email = 'auth-' . $tag . '@example.invalid';
$password = bin2hex(random_bytes(20));
$ids = []; $passed = 0;
$jar = tempnam(sys_get_temp_dir(), 'lumera-auth-');
$sourceIp = '127.0.' . random_int(1,254) . '.' . random_int(1,254);
$check = static function(bool $condition, string $name) use (&$passed): void { if (!$condition) { throw new RuntimeException('FAIL: ' . $name); } echo "PASS: $name\n"; $passed++; };
$request = static function(string $path, ?array $data = null) use ($jar, $sourceIp): array {
    $curl = curl_init(url($path));
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEFILE => $jar, CURLOPT_COOKIEJAR => $jar, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 15, CURLOPT_INTERFACE => $sourceIp]);
    if ($data !== null) { curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($data)]); }
    $response = curl_exec($curl);
    if ($response === false) { throw new RuntimeException(curl_error($curl)); }
    $result = ['status' => curl_getinfo($curl, CURLINFO_RESPONSE_CODE), 'headers' => substr($response, 0, curl_getinfo($curl, CURLINFO_HEADER_SIZE)), 'body' => substr($response, curl_getinfo($curl, CURLINFO_HEADER_SIZE))];
    curl_close($curl); return $result;
};
$token = static function(array $response): string { if (!preg_match('/name="_csrf" value="([a-f0-9]{64})"/', $response['body'], $matches)) { throw new RuntimeException('CSRF alanı bulunamadı.'); } return $matches[1]; };
$cookies = static function() use ($jar): string { return file_get_contents($jar); };
$input = ['first_name' => 'Deneme', 'last_name' => 'Müşteri', 'email' => $email, 'phone' => '+90 555 000 0000', 'password' => $password, 'password_confirmation' => $password, 'role' => 'ADMIN'];
try {
    $guest = $request('customer');
    $check($guest['status'] === 303, 'Guest redirected to login');
    $form = $request('register'); $csrf = $token($form);
    $check($form['status'] === 200 && !str_contains($form['body'], 'name="role"'), 'Registration form has no role selector');
    $check(str_contains($guest['headers'], 'HttpOnly') && str_contains($guest['headers'], 'SameSite=Lax'), 'Session cookie protections');
    $check($request('register', $input)['status'] === 403, 'Registration missing CSRF rejected');
    $check($request('register', $input + ['_csrf' => str_repeat('0',64)])['status'] === 403, 'Registration invalid CSRF rejected');
    $bad = array_replace($input, ['first_name' => [], 'phone' => 'bad', 'password_confirmation' => 'bad', '_csrf' => $csrf]);
    $check($request('register', $bad)['status'] === 422, 'Server validation and array injection rejected');
    $check(!(new User())->findByEmail($email), 'Invalid registration writes no account');
    $result = $request('register', $input + ['_csrf' => $csrf]);
    $created = (new User())->findByEmail($email);
    if ($created) { $ids[] = (int)$created['id']; }
    $check($result['status'] === 303 && (bool)$created, 'Customer registered in real database');
    $check($created['role'] === 'CUSTOMER', 'Injected ADMIN role ignored');
    $check($created['name'] === 'Deneme Müşteri' && $created['phone'] === $input['phone'], 'Name surname phone persisted');
    $check($created['password_hash'] !== $password && Password::verify($password, $created['password_hash']), 'Password stored as verified hash');
    $csrf = $token($request('register'));
    $check($request('register', $input + ['_csrf' => $csrf])['status'] === 422, 'Duplicate email rejected');
    $csrf = $token($request('login')); $before = $cookies();
    $check($request('login', ['email'=>$email,'password'=>$password])['status'] === 403, 'Login CSRF rejected');
    $check($request('login', ['email'=>$email,'password'=>bin2hex(random_bytes(20)),'_csrf'=>$csrf])['status'] === 422, 'Wrong password rejected');
    $result = $request('login', ['email'=>$email,'password'=>$password,'_csrf'=>$csrf]);
    $check($result['status'] === 303 && str_contains($result['headers'], '/customer'), 'Customer login redirects to own panel');
    $check($before !== $cookies(), 'Login rotates session ID');
    $panel = $request('customer'); $csrf = $token($panel);
    $check($panel['status'] === 200 && str_contains($panel['body'], 'Deneme Müşteri'), 'Customer panel displays own account');
    $check($request('admin')['status'] === 403 && $request('employee')['status'] === 403, 'Customer cannot access other roles');
    $check($request('logout')['status'] === 405, 'GET logout forbidden');
    $check($request('logout', ['_csrf'=>'wrong'])['status'] === 403 && $request('customer')['status'] === 200, 'Invalid logout CSRF preserves session');
    $check($request('logout', ['_csrf'=>$csrf])['status'] === 303 && $request('customer')['status'] === 303, 'Logout destroys authenticated session');
    foreach (['EMPLOYEE'=>'employee','ADMIN'=>'admin'] as $role=>$path) {
        $roleEmail = strtolower($role) . '-' . $tag . '@example.invalid';
        if ($role === 'ADMIN') {
            $pipes = []; $process = proc_open([PHP_BINARY, BASE_PATH . '/bin/create-admin.php'], [['pipe','r'], ['pipe','w'], ['pipe','w']], $pipes);
            if (!is_resource($process)) { throw new RuntimeException('Admin CLI başlatılamadı.'); }
            fwrite($pipes[0], json_encode(['name'=>'Deneme Yönetici','email'=>$roleEmail,'password'=>$password,'password_confirmation'=>$password])); fclose($pipes[0]);
            stream_get_contents($pipes[1]); fclose($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[2]); $exit = proc_close($process);
            $user = (new User())->findByEmail($roleEmail); if ($user) { $ids[] = (int)$user['id']; }
            $check($exit === 0 && $user['role'] === 'ADMIN', 'Secure admin CLI creates hashed account');
        } else { $ids[] = $employeeUser=(new User())->create('Deneme Çalışan',$roleEmail,null,Password::hash($password),$role); $createEmployee=$pdo->prepare('INSERT INTO employees(user_id,title,is_active) VALUES (?,?,1)'); $createEmployee->execute([$employeeUser,'QA Uzman']); }
        $csrf = $token($request('login'));
        $check($request('login',['email'=>$roleEmail,'password'=>$password,'_csrf'=>$csrf])['status'] === 303, "$role login succeeds");
        $panel = $request($path);
        $check($panel['status'] === 200, "$role own panel accessible");
        foreach (['customer','employee','admin'] as $other) { if ($other !== $path) { $check($request($other)['status'] === 403, "$role denied $other"); } }
        if ($role === 'ADMIN') {
            $updateRole = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?'); $updateRole->execute(['CUSTOMER', end($ids)]);
            $check($request('admin')['status'] === 403 && $request('customer')['status'] === 200, 'Role change takes effect without new login');
            $updateRole->execute(['ADMIN', end($ids)]);
            $update = $pdo->prepare('UPDATE users SET is_active = 0 WHERE id = ?'); $update->execute([end($ids)]);
            $check($request('admin')['status'] === 303, 'Disabled account loses existing session access');
            $csrf = $token($request('login'));
            $check($request('login',['email'=>$roleEmail,'password'=>$password,'_csrf'=>$csrf])['status'] === 422, 'Disabled account cannot log in');
        } else { $request('logout',['_csrf'=>$token($panel)]); }
    }
    $limitedEmail = 'limit-' . $tag . '@example.invalid';
    $csrf = $token($request('login'));
    for ($i=0;$i<5;$i++) { $check($request('login',['email'=>$limitedEmail,'password'=>$password,'_csrf'=>$csrf])['status'] === 422, 'Unknown login attempt ' . ($i+1)); }
    $result = $request('login',['email'=>$limitedEmail,'password'=>$password,'_csrf'=>$csrf]);
    $check($result['status'] === 429 && str_contains($result['headers'], 'Retry-After:'), 'Sixth attempt throttled with Retry-After');
    // A new cookie jar cannot reset the server-side email counter.
    file_put_contents($jar, '');
    $csrf = $token($request('login'));
    $check($request('login',['email'=>$limitedEmail,'password'=>$password,'_csrf'=>$csrf])['status'] === 429, 'New session does not bypass throttling');
    $check(!str_contains($result['body'], $password), 'Password never echoed in response');
    $check($request('../.env')['status'] === 403, 'Environment remains inaccessible');
    echo "$passed HTTP authentication tests passed.\n";
} finally {
    // Only IDs generated in this run; pre-existing users are never touched.
    foreach ($ids as $id) { $employeeDelete=$pdo->prepare('DELETE FROM employees WHERE user_id=?');$employeeDelete->execute([$id]); $delete = $pdo->prepare('DELETE FROM users WHERE id = ? AND email LIKE ?'); $delete->execute([$id, '%' . $tag . '@example.invalid']); }
    foreach ([$email, 'employee-' . $tag . '@example.invalid', 'admin-' . $tag . '@example.invalid', 'limit-' . $tag . '@example.invalid'] as $testEmail) {
        $file = BASE_PATH . '/storage/rate_limits/' . hash('sha256', 'email:' . $testEmail) . '.json';
        if (is_file($file)) { unlink($file); }
    }
    $file = BASE_PATH . '/storage/rate_limits/' . hash('sha256', 'ip:' . $sourceIp) . '.json';
    if (is_file($file)) { unlink($file); }
    if (is_file($jar)) { unlink($jar); }
}
