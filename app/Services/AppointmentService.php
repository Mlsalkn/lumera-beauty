<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{Database, Input};
use App\Models\AdminRepository as R;
final class AppointmentService
{
    public function create(array $input, ?int $actor = null): int
    {
        $employee = Input::id($input['employee_id'] ?? null); $service = Input::id($input['service_id'] ?? null); $customer = Input::id($input['customer_id'] ?? null);
        $start = Input::datetime(Input::text($input,'start_at',16));
        $note = Input::text($input,'customer_note',1000,false);
        if ($start <= new \DateTimeImmutable() || $start > new \DateTimeImmutable('+365 days')) { throw new \DomainException('Gelecek bir yıl içinde bir zaman seçin.'); }
        $pdo = Database::connection(); $pdo->beginTransaction();
        try {
            // Every booking, schedule, leave and status mutation takes this same lock first.
            $worker = R::one('SELECT * FROM employees WHERE id=? FOR UPDATE',[$employee]);
            $workerAccount = $worker ? R::one("SELECT id FROM users WHERE id=? AND is_active=1 AND role='EMPLOYEE'",[$worker['user_id']]) : null;
            $item = R::one('SELECT * FROM services WHERE id=? FOR UPDATE',[$service]);
            $client = R::one("SELECT id FROM users WHERE id=? AND role='CUSTOMER' AND is_active=1 FOR UPDATE",[$customer]);
            if (!$worker || !$worker['is_active'] || !$workerAccount || !$item || !$item['is_active'] || !$client || !R::one('SELECT employee_id FROM employee_services WHERE employee_id=? AND service_id=?',[$employee,$service])) { throw new \DomainException('Aktif müşteri, hizmet ve atanmış çalışan seçilmelidir.'); }
            $end = $start->modify('+'.(int)$item['duration_minutes'].' minutes'); $availability = new AvailabilityService();
            if (isset($input['_expected_price']) && ((string)$input['_expected_price'] !== (string)$item['price'] || (int)($input['_expected_duration'] ?? 0) !== (int)$item['duration_minutes'])) { throw new \DomainException('Hizmet fiyatı veya süresi değişti. Lütfen yeni özeti kontrol edin.'); }
            if (!$availability->fits($employee,$start,$end)) { throw new \DomainException('Seçilen aralık çalışma saatleri dışında veya izinle çakışıyor.'); }
            if ($availability->overlaps($employee,$start,$end)) { throw new \DomainException('Çalışanın bu zaman aralığında başka bir randevusu var.'); }
            R::execute("INSERT INTO appointments (customer_id,employee_id,service_id,start_at,end_at,status,service_price) VALUES (?,?,?,?,?,'pending',?)",[$customer,$employee,$service,$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),$item['price']]);
            $id = (int)$pdo->lastInsertId();
            R::execute('UPDATE appointments SET customer_note=? WHERE id=?',[$note ?: null,$id]);
            NotificationService::record($id,'created',null,$actor);
            $pdo->commit(); return $id;
        } catch (\Throwable $error) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $error; }
    }
    public function status(int $id, string $next, ?int $actor = null, ?int $employeeScope = null, ?int $cancellationRequest = null, string $response = ''): void
    {
        if (!in_array($next,['confirmed','completed','cancelled'],true)) { throw new \DomainException('Geçersiz randevu durumu.'); }
        $existing = R::one('SELECT employee_id FROM appointments WHERE id=?',[$id]);
        if (!$existing) { throw new \DomainException('Randevu bulunamadı.'); }
        if ($employeeScope !== null && (int)$existing['employee_id'] !== $employeeScope) { throw new \App\Core\AccessDenied('Bu randevuya erişim yetkiniz yok.'); }
        $pdo = Database::connection(); $pdo->beginTransaction();
        try {
            R::one('SELECT id FROM employees WHERE id=? FOR UPDATE',[$existing['employee_id']]);
            $appointment = R::one('SELECT * FROM appointments WHERE id=? FOR UPDATE',[$id]);
            if ($employeeScope !== null) {
                $permissions=\App\Models\EmployeeRepository::permissions($employeeScope);
                $worker=R::one("SELECT e.id FROM employees e JOIN users u ON u.id=e.user_id WHERE e.id=? AND u.id=? AND e.is_active=1 AND u.is_active=1 AND u.role='EMPLOYEE'",[$employeeScope,$actor]);
                $permission=match($next){'confirmed'=>'can_confirm','completed'=>'can_complete',default=>null};
                if (!$worker || !$permission || !$permissions[$permission]) { throw new \App\Core\AccessDenied('Bu işlem için yönetici yetkisi verilmemiş.'); }
            }
            if ($cancellationRequest !== null) {
                $request=R::one('SELECT * FROM appointment_cancellation_requests WHERE id=? FOR UPDATE',[$cancellationRequest]);
                if ($next!=='cancelled' || !$request || (int)$request['appointment_id']!==$id || (int)$request['employee_id']!==(int)$appointment['employee_id'] || $request['status']!=='pending' || !R::one("SELECT id FROM users WHERE id=? AND role='ADMIN' AND is_active=1",[$actor])) { throw new \DomainException('İptal talebi artık uygun değil.'); }
            }
            $transitions = ['pending'=>['confirmed','cancelled'], 'confirmed'=>['completed','cancelled'], 'completed'=>[], 'cancelled'=>[]];
            if (!$appointment || !in_array($next,$transitions[$appointment['status']],true)) { throw new \DomainException('Bu durum geçişine izin verilmez.'); }
            $start = new \DateTimeImmutable($appointment['start_at']); $end = new \DateTimeImmutable($appointment['end_at']);
            if ($next === 'completed' && $end > new \DateTimeImmutable()) { throw new \DomainException('Randevu bitmeden tamamlandı olarak işaretlenemez.'); }
            if ($next === 'confirmed') {
                $availability = new AvailabilityService();
                if ($start <= new \DateTimeImmutable() || !$availability->fits((int)$appointment['employee_id'],$start,$end) || $availability->overlaps((int)$appointment['employee_id'],$start,$end,$id)) { throw new \DomainException('Randevu artık uygun bir zaman aralığında değil.'); }
            }
            R::execute('UPDATE appointments SET status=? WHERE id=?',[$next,$id]);
            if ($cancellationRequest !== null) {
                R::execute("UPDATE appointment_cancellation_requests SET status='approved',reviewer_id=?,reviewed_at=?,response=? WHERE id=?",[$actor,date('Y-m-d H:i:s'),$response,$cancellationRequest]);
                NotificationService::employee((int)$appointment['employee_id'],'cancel_result','İptal talebiniz onaylandı · #'.$id,$id);
            }
            NotificationService::record($id,$next,$appointment,$actor); $pdo->commit();
        } catch (\Throwable $error) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $error; }
    }
}
