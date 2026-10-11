<?php
declare(strict_types=1);
namespace App\Core;

final class Validator
{
    public static function customerRegistration(array $input): array
    {
        $first = is_string($input['first_name'] ?? null) ? trim($input['first_name']) : '';
        $last = is_string($input['last_name'] ?? null) ? trim($input['last_name']) : '';
        $errors = self::registration(array_replace($input, ['name' => $first . ' ' . $last]));
        foreach (['first_name' => $first, 'last_name' => $last] as $field => $value) {
            if (mb_strlen($value) < 2 || mb_strlen($value) > 60 || preg_match('/[\x00-\x1F\x7F]/u', $value)) { $errors[$field] = '2–60 karakter arasında geçerli bir ad girin.'; }
        }
        $phone = is_string($input['phone'] ?? null) ? trim($input['phone']) : '';
        if (!preg_match('/^\+?[0-9 ()-]{7,25}$/', $phone) || strlen(preg_replace('/\D/', '', $phone)) < 7) { $errors['phone'] = 'Geçerli bir telefon numarası girin.'; }
        return $errors;
    }
    /** No database access here: uniqueness and role are enforced by the future AuthService. */
    public static function registration(array $input): array
    {
        $errors = [];
        $name = is_string($input['name'] ?? null) ? trim($input['name']) : '';
        $email = is_string($input['email'] ?? null) ? trim($input['email']) : '';
        $password = is_string($input['password'] ?? null) ? $input['password'] : '';
        $confirmation = is_string($input['password_confirmation'] ?? null) ? $input['password_confirmation'] : '';
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $errors['name'] = 'Adınız 2–120 karakter olmalıdır.';
        }
        if (strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Geçerli bir e-posta adresi girin.';
        }
        if (!Password::isAcceptable($password)) {
            $errors['password'] = 'Parola en az 12 karakter ve en fazla 72 bayt olmalıdır; boşluklardan oluşamaz.';
        }
        if ($password !== $confirmation) {
            $errors['password_confirmation'] = 'Parolalar eşleşmiyor.';
        }
        return $errors;
    }

    public static function login(array $input): array
    {
        $errors = [];
        $email = is_string($input['email'] ?? null) ? trim($input['email']) : '';
        $password = $input['password'] ?? null;
        if (strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Geçerli bir e-posta adresi girin.';
        }
        // Existing hashes may use an older password policy. Do not impose registration length here.
        if (!is_string($password) || $password === '' || strlen($password) > 72 || str_contains($password, "\0")) {
            $errors['password'] = 'Geçerli bir parola girin.';
        }
        return $errors;
    }
}
