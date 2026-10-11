<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\AdminRepository as R;
final class StatisticsService
{
    public function between(string $start, string $end): array
    {
        return R::one("SELECT COUNT(*) AS total, COALESCE(SUM(status='pending'),0) AS pending, COALESCE(SUM(status='confirmed'),0) AS confirmed, COALESCE(SUM(status='completed'),0) AS completed, COALESCE(SUM(status='cancelled'),0) AS cancelled, COALESCE(SUM(CASE WHEN status='completed' THEN service_price ELSE 0 END),0) AS estimated FROM appointments WHERE start_at >= ? AND start_at < ?",[$start,$end]);
    }
    public function dashboard(): array
    {
        $today = new \DateTimeImmutable('today'); $week = $today->modify('monday this week'); $month = $today->modify('first day of this month');
        return [
            'customers'=>(int)R::one("SELECT COUNT(*) AS n FROM users WHERE role='CUSTOMER'")['n'],
            'employees'=>(int)R::one('SELECT COUNT(*) AS n FROM employees')['n'],
            'services'=>(int)R::one('SELECT COUNT(*) AS n FROM services WHERE is_active=1')['n'],
            'day'=>$this->between($today->format('Y-m-d'),$today->modify('+1 day')->format('Y-m-d')),
            'week'=>$this->between($week->format('Y-m-d'),$week->modify('+7 days')->format('Y-m-d')),
            'month'=>$this->between($month->format('Y-m-d'),$month->modify('+1 month')->format('Y-m-d')),
            'all'=>R::one("SELECT COALESCE(SUM(status='pending'),0) AS pending,COALESCE(SUM(status='completed'),0) AS completed,COALESCE(SUM(CASE WHEN status='completed' THEN service_price ELSE 0 END),0) AS estimated FROM appointments"),
            'latest'=>R::appointments('1=1',[],8),
            'daily'=>$this->daily($month->format('Y-m-d'),$month->modify('+1 month')->format('Y-m-d')),
        ];
    }
    public function daily(string $start, string $end): array
    {
        return R::rows("SELECT DATE(start_at) AS day,COUNT(*) AS total,COALESCE(SUM(status='completed'),0) AS completed,COALESCE(SUM(CASE WHEN status='completed' THEN service_price ELSE 0 END),0) AS estimated FROM appointments WHERE start_at>=? AND start_at<? GROUP BY DATE(start_at) ORDER BY day",[$start,$end]);
    }
    public function reports(string $start, string $end): array
    {
        return ['totals'=>$this->between($start,$end),'daily'=>$this->daily($start,$end),
            'monthly'=>R::rows("SELECT DATE_FORMAT(start_at,'%Y-%m') AS month,COUNT(*) AS total,COALESCE(SUM(status='completed'),0) AS completed,COALESCE(SUM(CASE WHEN status='completed' THEN service_price ELSE 0 END),0) AS estimated FROM appointments WHERE start_at>=? AND start_at<? GROUP BY DATE_FORMAT(start_at,'%Y-%m') ORDER BY month",[$start,$end]),
            'popular'=>R::rows("SELECT s.name,COUNT(a.id) AS total,COALESCE(SUM(a.status='completed'),0) AS completed FROM appointments a JOIN services s ON s.id=a.service_id WHERE a.start_at>=? AND a.start_at<? AND a.status<>'cancelled' GROUP BY s.id,s.name ORDER BY total DESC,s.id LIMIT 10",[$start,$end]),
            'performance'=>R::rows("SELECT u.name,COUNT(a.id) AS total,COALESCE(SUM(a.status='completed'),0) AS completed,COALESCE(SUM(a.status='cancelled'),0) AS cancelled,COALESCE(SUM(CASE WHEN a.status='completed' THEN a.service_price ELSE 0 END),0) AS estimated FROM employees e JOIN users u ON u.id=e.user_id LEFT JOIN appointments a ON a.employee_id=e.id AND a.start_at>=? AND a.start_at<? GROUP BY e.id,u.name ORDER BY completed DESC,e.id",[$start,$end])];
    }
}
