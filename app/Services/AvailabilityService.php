<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Input;
use App\Models\AdminRepository as R;
final class AvailabilityService
{
    public function fits(int $employee, \DateTimeImmutable $start, \DateTimeImmutable $end, ?array $hours = null): bool
    {
        if ($start->format('Y-m-d') !== $end->format('Y-m-d')) { return false; }
        $hours ??= R::rows('SELECT * FROM working_hours WHERE employee_id=? AND weekday=?',[$employee,(int)$start->format('N')]);
        $inside = false;
        foreach ($hours as $hour) {
            if ((int)$hour['weekday'] === (int)$start->format('N') && $start->format('H:i:s') >= $hour['start_time'] && $end->format('H:i:s') <= $hour['end_time']) { $inside = true; break; }
        }
        if (!$inside) { return false; }
        if (R::one('SELECT id FROM employee_breaks WHERE employee_id=? AND weekday=? AND start_time<? AND end_time>? LIMIT 1',[$employee,(int)$start->format('N'),$end->format('H:i:s'),$start->format('H:i:s')])) { return false; }
        return !R::one('SELECT id FROM employee_leaves WHERE employee_id=? AND start_at < ? AND end_at > ? LIMIT 1',[$employee,$end->format('Y-m-d H:i:s'),$start->format('Y-m-d H:i:s')]);
    }
    public function overlaps(int $employee, \DateTimeImmutable $start, \DateTimeImmutable $end, int $except = 0): bool
    {
        return (bool)R::one("SELECT id FROM appointments WHERE employee_id=? AND status IN ('pending','confirmed') AND start_at < ? AND end_at > ? AND id <> ? LIMIT 1",[$employee,$end->format('Y-m-d H:i:s'),$start->format('Y-m-d H:i:s'),$except]);
    }
    public function slots(int $employee, int $service, string $date, int $except = 0, ?int $duration = null): array
    {
        $day = Input::date($date);
        if ($day < new \DateTimeImmutable('today') || $day > new \DateTimeImmutable('today +365 days')) { throw new \DomainException('Tarih bugün ile bir yıl arasında olmalıdır.'); }
        $worker = R::one("SELECT e.id FROM employees e JOIN users u ON u.id=e.user_id JOIN employee_services es ON es.employee_id=e.id WHERE e.id=? AND es.service_id=? AND e.is_active=1 AND u.is_active=1 AND u.role='EMPLOYEE'",[$employee,$service]);
        $item = R::one('SELECT * FROM services WHERE id=? AND is_active=1',[$service]);
        if (!$worker || !$item) { throw new \DomainException('Aktif bir hizmet ve bu hizmete atanmış çalışan seçin.'); }
        $hours = R::rows('SELECT * FROM working_hours WHERE employee_id=? AND weekday=? ORDER BY start_time',[$employee,(int)$day->format('N')]);
        $from=$day->format('Y-m-d');$until=$day->modify('+1 day')->format('Y-m-d');
        $blocked=R::rows("SELECT start_at,end_at FROM appointments WHERE employee_id=? AND status IN ('pending','confirmed') AND id<>? AND start_at<? AND end_at>?",[$employee,$except,$until,$from]);
        $blocked=array_merge($blocked,R::rows('SELECT start_at,end_at FROM employee_leaves WHERE employee_id=? AND start_at<? AND end_at>?',[$employee,$until,$from]));
        foreach(R::rows('SELECT start_time,end_time FROM employee_breaks WHERE employee_id=? AND weekday=?',[$employee,(int)$day->format('N')]) as $break){$blocked[]=['start_at'=>$date.' '.$break['start_time'],'end_at'=>$date.' '.$break['end_time']];}
        $slots = []; $now = new \DateTimeImmutable();
        foreach ($hours as $hour) {
            $cursor = new \DateTimeImmutable($date.' '.$hour['start_time']); $closing = new \DateTimeImmutable($date.' '.$hour['end_time']);
            while (($end = $cursor->modify('+'.($duration ?? (int)$item['duration_minutes']).' minutes')) <= $closing) {
                $free=true;foreach($blocked as $interval){if($interval['start_at']<$end->format('Y-m-d H:i:s')&&$interval['end_at']>$cursor->format('Y-m-d H:i:s')){$free=false;break;}}
                if ($cursor > $now && $free) { $slots[] = $cursor->format('H:i'); }
                $step=(require BASE_PATH.'/config/appointments.php')['slot_minutes'];
                $cursor = $cursor->modify('+'.$step.' minutes');
            }
        }
        return array_values(array_unique($slots));
    }
}
