<?php
declare(strict_types=1);
// Included by run.php. Test secrets are generated in memory; never persisted as users.
use App\Core\Csrf;
use App\Core\Password;
use App\Core\Session;
use App\Core\Validator;

$password = bin2hex(random_bytes(16));
$input = ['name' => 'Test Müşteri', 'email' => 'test@example.invalid', 'password' => $password, 'password_confirmation' => $password];
$check(Validator::registration($input) === [], 'Valid registration fields accepted');
$errors = Validator::registration(['name' => [], 'email' => [], 'password' => [], 'password_confirmation' => []]);
$check(isset($errors['name'], $errors['email'], $errors['password']), 'Array injection rejected');
$errors = Validator::registration(array_replace($input, ['name' => 'A', 'email' => 'invalid', 'password_confirmation' => '']));
$check(isset($errors['name'], $errors['email'], $errors['password_confirmation']), 'Invalid name/email and password mismatch rejected');
$check(!Password::isAcceptable(str_repeat(' ', 12)), 'Whitespace-only password rejected');
$check(!Password::isAcceptable(str_repeat('a', 11)), 'Short password rejected');
$check(!Password::isAcceptable(str_repeat('a', 73)), 'Bcrypt byte limit enforced');
$check(!Password::isAcceptable(str_repeat('a', 12) . "\0"), 'Null bytes rejected');
$check(Password::isAcceptable(str_repeat('ş', 12)), 'UTF-8 password supported');
$check(!Password::isAcceptable(str_repeat('ş', 37)), 'Multibyte password byte limit enforced');
$hash = Password::hash($password);
$check($hash !== $password && Password::verify($password, $hash), 'Password hash and verify roundtrip');
$check(!Password::verify(bin2hex(random_bytes(16)), $hash), 'Wrong password rejected');
$check(!Password::needsRehash($hash), 'Current password hash policy recognized');
$check(Validator::login(['email' => $input['email'], 'password' => $password]) === [], 'Valid login input accepted');
$check(isset(Validator::login(['email' => [], 'password' => []])['password']), 'Malformed login rejected');

Session::start('lumera_test_' . bin2hex(random_bytes(6)));
$cookie = session_get_cookie_params();
$check($cookie['httponly'] && $cookie['samesite'] === 'Lax' && ini_get('session.use_strict_mode') === '1', 'Session cookie and strict-mode settings');
$token = Csrf::token();
$check(strlen($token) === 64 && Csrf::verify($token), 'Valid CSRF token accepted');
$check(Csrf::token() === $token, 'Token stable during session');
$check(!Csrf::verify(null) && !Csrf::verify([]) && !Csrf::verify(bin2hex(random_bytes(32))), 'Missing/malformed/wrong CSRF token rejected');
$check(str_contains(Csrf::field(), 'name="_csrf"'), 'Escaped CSRF hidden field');
$sessionId = session_id();
$_SESSION['test_marker'] = 'retained';
Session::regenerate();
$check(session_id() !== $sessionId && $_SESSION['test_marker'] === 'retained', 'Session ID rotation preserves state');
$check(!Csrf::verify($token) && Csrf::token() !== $token, 'Old CSRF token invalid after session rotation');
Session::destroy();
$check(session_status() !== PHP_SESSION_ACTIVE && $_SESSION === [], 'Logout session destruction');
try { Csrf::token(); $requiresSession = false; } catch (LogicException) { $requiresSession = true; }
$check($requiresSession, 'CSRF requires active session');
