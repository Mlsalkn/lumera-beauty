<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Csrf, Session, Validator, View};
use App\Services\{AuthService, RateLimiter};
final class AuthController
{
    public function form(string $mode): void
    {
        $user = AuthService::current();
        if ($user) { $this->redirect(AuthService::panel($user['role'])); return; }
        View::render('auth/form', ['mode' => $mode, 'title' => $mode === 'register' ? 'Üye olun · Luméra' : 'Giriş yapın · Luméra', 'errors' => [], 'old' => []]);
    }
    private function csrf(): bool
    {
        if (Csrf::verify($_POST['_csrf'] ?? null)) { return true; }
        http_response_code(403);
        View::render('errors/error', ['title' => '403', 'message' => 'Formun süresi dolmuş veya güvenlik doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.']);
        return false;
    }
    public function register(): void
    {
        if (!$this->csrf()) { return; }
        $errors = Validator::customerRegistration($_POST);
        if(env('EMAIL_REQUIRE_VERIFICATION','false')==='true'&&!\App\Services\MailService::configured()){$errors['general']='Doğrulama postası gönderimi kullanılamıyor. Üyelik için merkezle iletişime geçin.';}
        $retry=(new RateLimiter())->critical('register',is_string($_POST['email']??null)?strtolower(trim($_POST['email'])):'',$_SERVER['REMOTE_ADDR']??'unknown');
        if($retry){http_response_code(429);header('Retry-After: '.$retry);$this->renderErrors('register',['general'=>'Çok fazla kayıt denemesi. Daha sonra tekrar deneyin.']);return;}
        if (!$errors) {
            try {
                (new AuthService())->register($_POST);
                $_SESSION['notice'] = 'Üyeliğiniz oluşturuldu. '.(\App\Services\MailService::configured()?'Doğrulama bağlantısı gönderim kuyruğuna eklendi. ':'').'Giriş yapabilir veya doğrulama bağlantısı isteyebilirsiniz.';
                Session::regenerate();
                $this->redirect('login'); return;
            } catch (\PDOException $exception) {
                if ((int)($exception->errorInfo[1] ?? 0) !== 1062) { throw $exception; }
                $errors['email'] = 'Bu e-posta ile kayıt tamamlanamadı. Giriş yapmayı deneyin.';
            }
        }
        http_response_code(422);
        $this->renderErrors('register', $errors);
    }
    public function login(): void
    {
        if (!$this->csrf()) { return; }
        $email = is_string($_POST['email'] ?? null) ? $_POST['email'] : '';
        // REMOTE_ADDR is trusted; forwarded headers cannot bypass the IP bucket.
        $retry = (new RateLimiter())->consume($email, $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if ($retry > 0) {
            http_response_code(429); header('Retry-After: ' . $retry);
            $this->renderErrors('login', ['general' => 'Çok fazla giriş denemesi. Lütfen 15 dakika içinde tekrar deneyin.']); return;
        }
        $errors = Validator::login($_POST);
        if (!$errors && (new AuthService())->login($email, $_POST['password'])) {
            $user = AuthService::current();
            if ($user) { $this->redirect(AuthService::panel($user['role'])); return; }
        }
        http_response_code(422);
        $this->renderErrors('login', $errors ?: ['general' => 'E-posta veya şifre hatalı ya da hesap kullanıma kapalı.']);
    }
    public function logout(): void
    {
        if (!$this->csrf()) { return; }
        Session::destroy();
        $this->redirect('login');
    }
    private function redirect(string $path): void { header('Location: ' . \url($path), true, 303); }
    private function renderErrors(string $mode, array $errors): void
    {
        $old = [];
        foreach (['first_name','last_name','email','phone'] as $field) { $old[$field] = is_string($_POST[$field] ?? null) ? mb_substr($_POST[$field], 0, 190) : ''; }
        View::render('auth/form', ['mode' => $mode, 'title' => 'Hesabınız · Luméra', 'errors' => $errors, 'old' => $old]);
    }
}
