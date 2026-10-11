<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{Password, Session};
use App\Models\User;
final class AuthService
{
    public function register(array $input): void
    {
        // Public requests never supply a role to the model.
        $pdo=\App\Core\Database::connection();$pdo->beginTransaction();
        try{
            $id=(new User())->create(trim($input['first_name']) . ' ' . trim($input['last_name']), strtolower(trim($input['email'])), trim($input['phone']), Password::hash($input['password']), 'CUSTOMER');
            \App\Models\AdminRepository::execute('UPDATE users SET first_name=?,last_name=? WHERE id=?',[trim($input['first_name']),trim($input['last_name']),$id]);
            \App\Models\AdminRepository::execute('INSERT INTO user_security(user_id) VALUES (?)',[$id]);
            (new AccountRecoveryService())->issue($id,'verify');$pdo->commit();
        }catch(\Throwable $error){if($pdo->inTransaction()){$pdo->rollBack();}throw $error;}
    }
    public function login(string $email, string $password): bool
    {
        $model = new User();
        $user = $model->findByEmail(strtolower(trim($email)));
        // Equal bcrypt work for unknown accounts, with no stored dummy credential.
        $hash = $user['password_hash'] ?? Password::hash(bin2hex(random_bytes(24)));
        $valid = Password::verify($password, $hash);
        if (!$user || !$valid || !(bool) $user['is_active'] || !in_array($user['role'], ['CUSTOMER','EMPLOYEE','ADMIN'], true)) { return false; }
        // Existing accounts are grandfathered; only newly registered customers have this row.
        if($user['role']==='CUSTOMER'&&env('EMAIL_REQUIRE_VERIFICATION','false')==='true'){
            $security=\App\Models\AdminRepository::one('SELECT email_verified_at FROM user_security WHERE user_id=?',[$user['id']]);
            if($security&&!$security['email_verified_at']){return false;}
        }
        if (Password::needsRehash($hash) && Password::isAcceptable($password)) { $model->rehash((int)$user['id'], $hash, Password::hash($password)); }
        Session::regenerate();
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['auth_version'] = (int)$user['auth_version'];
        $_SESSION['last_activity'] = time();
        $_SESSION['authenticated_at'] = time();
        return true;
    }
    public static function current(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION['user_id'])) { return null; }
        if (time() - ($_SESSION['last_activity'] ?? 0) > 1800 || time() - ($_SESSION['authenticated_at'] ?? 0) > 28800) {
            unset($_SESSION['user_id'], $_SESSION['last_activity'], $_SESSION['authenticated_at']);
            Session::regenerate();
            return null;
        }
        // Re-read active status and role; revoked access takes effect on the next request.
        $user = (new User())->find((int)$_SESSION['user_id']);
        if (!$user || !(bool)$user['is_active'] || (int)($_SESSION['auth_version'] ?? 1) !== (int)$user['auth_version'] || !in_array($user['role'], ['CUSTOMER','EMPLOYEE','ADMIN'], true)) {
            unset($_SESSION['user_id']);
            Session::regenerate();
            return null;
        }
        $_SESSION['last_activity'] = time();
        unset($user['password_hash']);
        return $user;
    }
    public static function panel(string $role): string
    {
        return match ($role) { 'CUSTOMER' => 'customer', 'EMPLOYEE' => 'employee', 'ADMIN' => 'admin', default => 'login' };
    }
}
