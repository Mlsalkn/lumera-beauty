<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{Database, Input, Password, Validator};
use App\Models\AdminRepository as R;
final class ManagementService
{
    public function saveService(array $input, ?array $upload = null): int
    {
        $id = empty($input['id']) ? null : Input::id($input['id']);
        $name = Input::text($input,'name',150); $description = Input::text($input,'description',5000,false); $category = Input::text($input,'category',100);
        $price = Input::text($input,'price',12); $duration = Input::text($input,'duration_minutes',4);
        if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/',$price) || !ctype_digit($duration) || (int)$duration < 5 || (int)$duration > 480) { throw new \DomainException('Fiyat 0–99999999.99 ve süre 5–480 dakika arasında olmalıdır.'); }
        $active = isset($input['is_active']) ? 1 : 0;
        $image = null;
        if ($upload && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) { $image = (new ImageUploadService())->store($upload); }
        $pdo = Database::connection(); $pdo->beginTransaction();
        try {
            if ($id) {
                $existing = R::one('SELECT * FROM services WHERE id=? FOR UPDATE',[$id]);
                if (!$existing) { throw new \DomainException('Hizmet bulunamadı.'); }
                $path = $image ?? $existing['image_path'];
                if (isset($input['remove_image'])) { $path = $image; }
                R::execute('UPDATE services SET name=?,description=?,category=?,price=?,duration_minutes=?,is_active=?,image_path=? WHERE id=?',[$name,$description,$category,$price,(int)$duration,$active,$path,$id]);
            } else {
                R::execute('INSERT INTO services (name,description,category,price,duration_minutes,is_active,image_path) VALUES (?,?,?,?,?,?,?)',[$name,$description,$category,$price,(int)$duration,$active,$image]); $id = (int)$pdo->lastInsertId();
            }
            $pdo->commit(); return $id;
        } catch (\Throwable $error) { if ($pdo->inTransaction()) { $pdo->rollBack(); } if ($image && is_file(BASE_PATH.'/public/'.$image)) { unlink(BASE_PATH.'/public/'.$image); } throw $error; }
    }
    public function deleteService(int $id): string
    {
        $pdo = Database::connection(); $pdo->beginTransaction();
        try {
            if (!R::one('SELECT id FROM services WHERE id=? FOR UPDATE',[$id])) { throw new \DomainException('Hizmet bulunamadı.'); }
            if (R::one('SELECT id FROM appointments WHERE service_id=? LIMIT 1',[$id])) {
                R::execute('UPDATE services SET is_active=0 WHERE id=?',[$id]); $message = 'Randevu geçmişi bulunan hizmet silinmedi; pasife alındı.';
            } else {
                R::execute('DELETE FROM favorites WHERE service_id=?',[$id]);
                R::execute('DELETE FROM employee_services WHERE service_id=?',[$id]); R::execute('DELETE FROM services WHERE id=?',[$id]); $message = 'Randevu geçmişi olmayan hizmet silindi.';
            }
            $pdo->commit(); return $message;
        } catch (\Throwable $error) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $error; }
    }
    public function saveEmployee(array $input): int
    {
        $id = empty($input['id']) ? null : Input::id($input['id']); $name = Input::text($input,'name',120); $email = strtolower(Input::text($input,'email',190));
        $phone = Input::text($input,'phone',25,false); $title = Input::text($input,'title',120); $bio = Input::text($input,'bio',5000,false);
        if (mb_strlen($name)<2 || !filter_var($email,FILTER_VALIDATE_EMAIL) || ($phone !== '' && !preg_match('/^\+?[0-9 ()-]{7,25}$/',$phone))) { throw new \DomainException('Ad, e-posta veya telefon geçersiz.'); }
        $active = isset($input['is_active']) ? 1 : 0;
        $services = $input['services'] ?? [];
        if (!is_array($services) || count($services)>100) { throw new \DomainException('Hizmet seçimi geçersiz.'); }
        $services = array_unique(array_map([Input::class,'id'],$services));
        foreach (['days','starts','ends'] as $field) { if (isset($input[$field]) && !is_array($input[$field])) { throw new \DomainException('Çalışma planı biçimi geçersiz.'); } }
        $hours = [];
        foreach (range(1,7) as $day) {
            if (!isset($input['days'][$day])) { continue; }
            $start = $input['starts'][$day] ?? null; $end = $input['ends'][$day] ?? null;
            if (!is_string($start) || !is_string($end) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$start) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$end) || $end <= $start) { throw new \DomainException('Çalışma başlangıç ve bitiş saatleri geçersiz.'); }
            $hours[] = ['weekday'=>$day,'start_time'=>$start.':00','end_time'=>$end.':00'];
        }
        $password = $input['password'] ?? '';
        if (!is_string($password)) { throw new \DomainException('Parola alanı geçersiz.'); }
        if ((!$id || $password !== '') && !Password::isAcceptable($password)) { throw new \DomainException('Çalışan parolası en az 12 karakter, en fazla 72 bayt olmalıdır.'); }
        if ($password !== '' && $password !== ($input['password_confirmation'] ?? null)) { throw new \DomainException('Çalışan parolaları eşleşmiyor.'); }
        $hash = $password !== '' ? Password::hash($password) : null;
        $pdo = Database::connection(); $pdo->beginTransaction();
        try {
            if ($id) {
                $existing = R::one('SELECT * FROM employees WHERE id=? FOR UPDATE',[$id]);
                if (!$existing) { throw new \DomainException('Çalışan bulunamadı.'); }
                $account = R::one("SELECT * FROM users WHERE id=? AND role='EMPLOYEE' FOR UPDATE",[$existing['user_id']]);
                if (!$account) { throw new \DomainException('Çalışan hesabının rolü uyumsuz.'); }
                foreach (R::rows('SELECT * FROM employee_breaks WHERE employee_id=?',[$id]) as $break) {
                    $inside=false;foreach($hours as $hour){if($hour['weekday']==$break['weekday']&&$hour['start_time']<=$break['start_time']&&$hour['end_time']>=$break['end_time']){$inside=true;break;}}
                    if(!$inside){throw new \DomainException('Mevcut mola yeni çalışma planının dışında. Önce Operasyon ekranında molayı düzenleyin.');}
                }
                foreach (R::rows("SELECT * FROM appointments WHERE employee_id=? AND status IN ('pending','confirmed') AND end_at > ?",[$id,date('Y-m-d H:i:s')]) as $booking) {
                    if (!$active || !in_array((int)$booking['service_id'],$services,true) || !(new AvailabilityService())->fits($id,new \DateTimeImmutable($booking['start_at']),new \DateTimeImmutable($booking['end_at']),$hours)) { throw new \DomainException('Aktif randevular yeni çalışma planıyla çakışıyor. Önce ilgili randevuları yönetin.'); }
                }
                R::execute('UPDATE users SET name=?,email=?,phone=?,is_active=? WHERE id=?',[$name,$email,$phone ?: null,$active,$existing['user_id']]);
                if ($account['name']!==$name) { R::execute('UPDATE users SET first_name=NULL,last_name=NULL WHERE id=?',[$existing['user_id']]); }
                if ($hash) { R::execute('UPDATE users SET password_hash=?,auth_version=auth_version+1 WHERE id=?',[$hash,$existing['user_id']]); }
                R::execute('UPDATE employees SET title=?,bio=?,is_active=? WHERE id=?',[$title,$bio,$active,$id]);
            } else {
                R::execute("INSERT INTO users (name,email,phone,password_hash,role,is_active) VALUES (?,?,?,?,'EMPLOYEE',?)",[$name,$email,$phone ?: null,$hash,$active]); $userId = (int)$pdo->lastInsertId();
                R::execute('INSERT INTO employees (user_id,title,bio,is_active) VALUES (?,?,?,?)',[$userId,$title,$bio,$active]); $id = (int)$pdo->lastInsertId();
            }
            foreach ($services as $service) { if (!R::one('SELECT id FROM services WHERE id=?',[$service])) { throw new \DomainException('Atanacak hizmet bulunamadı.'); } }
            R::execute('DELETE FROM employee_services WHERE employee_id=?',[$id]);
            foreach ($services as $service) { R::execute('INSERT INTO employee_services (employee_id,service_id) VALUES (?,?)',[$id,$service]); }
            R::execute('DELETE FROM working_hours WHERE employee_id=?',[$id]);
            foreach ($hours as $hour) { R::execute('INSERT INTO working_hours (employee_id,weekday,start_time,end_time) VALUES (?,?,?,?)',[$id,$hour['weekday'],$hour['start_time'],$hour['end_time']]); }
            NotificationService::employee($id,'schedule','Çalışma planınız, hizmet atamalarınız ve çalışan bilgileriniz yönetici tarafından kaydedildi.');
            $pdo->commit(); return $id;
        } catch (\Throwable $error) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $error; }
    }
    public function addLeave(array $input): int
    {
        $employee = Input::id($input['employee_id'] ?? null); $start = Input::datetime(Input::text($input,'start_at',16)); $end = Input::datetime(Input::text($input,'end_at',16)); $reason = Input::text($input,'reason',255,false);
        if ($end <= $start) { throw new \DomainException('İzin bitişi başlangıçtan sonra olmalıdır.'); }
        $pdo = Database::connection(); $pdo->beginTransaction();
        try {
            if (!R::one('SELECT id FROM employees WHERE id=? FOR UPDATE',[$employee])) { throw new \DomainException('Çalışan bulunamadı.'); }
            $availability = new AvailabilityService();
            if ($availability->overlaps($employee,$start,$end)) { throw new \DomainException('İzin aktif randevuyla çakışıyor. Önce randevuyu yönetin.'); }
            if (R::one('SELECT id FROM employee_leaves WHERE employee_id=? AND start_at < ? AND end_at > ?',[$employee,$end->format('Y-m-d H:i:s'),$start->format('Y-m-d H:i:s')])) { throw new \DomainException('Aynı aralıkta başka bir izin mevcut.'); }
            R::execute('INSERT INTO employee_leaves (employee_id,start_at,end_at,reason) VALUES (?,?,?,?)',[$employee,$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),$reason]); $id = (int)$pdo->lastInsertId(); NotificationService::employee($employee,'schedule','Yönetici takviminize izin ekledi: '.$start->format('d.m.Y H:i').' – '.$end->format('d.m.Y H:i')); $pdo->commit(); return $id;
        } catch (\Throwable $error) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $error; }
    }
    public function removeLeave(int $id): void
    {
        $existing = R::one('SELECT employee_id FROM employee_leaves WHERE id=?',[$id]); if (!$existing) { throw new \DomainException('İzin bulunamadı.'); }
        $pdo=Database::connection(); $pdo->beginTransaction();
        try { R::one('SELECT id FROM employees WHERE id=? FOR UPDATE',[$existing['employee_id']]); R::execute('DELETE FROM employee_leaves WHERE id=?',[$id]); NotificationService::employee((int)$existing['employee_id'],'schedule','Yönetici takviminizden #'.$id.' iznini kaldırdı.'); $pdo->commit(); }
        catch (\Throwable $error) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $error; }
    }
    public function customerStatus(int $id, bool $active): void
    {
        if (!R::one("SELECT id FROM users WHERE id=? AND role='CUSTOMER'",[$id])) { throw new \DomainException('Müşteri bulunamadı.'); }
        R::execute("UPDATE users SET is_active=? WHERE id=? AND role='CUSTOMER'",[$active ? 1 : 0,$id]);
    }
}
