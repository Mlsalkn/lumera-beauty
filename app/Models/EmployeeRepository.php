<?php
declare(strict_types=1);
namespace App\Models;
use App\Core\AccessDenied;
final class EmployeeRepository
{
 public static function account(int $user):array
 {
  $employee=AdminRepository::one("SELECT e.*,u.name,u.first_name,u.last_name,u.email,u.phone FROM employees e JOIN users u ON u.id=e.user_id WHERE e.user_id=? AND e.is_active=1 AND u.is_active=1 AND u.role='EMPLOYEE'",[$user]);
  if(!$employee){throw new AccessDenied('Aktif çalışan profili bulunamadı.');}return $employee;
 }
 public static function permissions(int $employee):array{return AdminRepository::one('SELECT * FROM employee_permissions WHERE employee_id=?',[$employee])??['employee_id'=>$employee,'can_confirm'=>0,'can_complete'=>1,'can_request_cancel'=>1];}
 public static function owned(int $employee,int $id):array
 {
  $row=AdminRepository::appointments('a.id=? AND a.employee_id=?',[$id,$employee],1)[0]??null;if(!$row){throw new AccessDenied('Bu randevuya erişim yetkiniz yok.');}return $row;
 }
 public static function services(int $employee):array{return AdminRepository::rows('SELECT s.* FROM employee_services es JOIN services s ON s.id=es.service_id WHERE es.employee_id=? ORDER BY s.name',[$employee]);}
 public static function hours(int $employee):array{return AdminRepository::rows('SELECT * FROM working_hours WHERE employee_id=? ORDER BY weekday,start_time',[$employee]);}
 public static function breaks(int $employee):array{return AdminRepository::rows('SELECT * FROM employee_breaks WHERE employee_id=? ORDER BY weekday,start_time',[$employee]);}
 public static function stats(int $employee,string $from,string $until):array
 {
  return AdminRepository::one("SELECT COUNT(*) total,COALESCE(SUM(status='pending'),0) pending,COALESCE(SUM(status='confirmed'),0) confirmed,COALESCE(SUM(status='completed'),0) completed,COALESCE(SUM(status='cancelled'),0) cancelled,COALESCE(SUM(CASE WHEN status='completed' THEN service_price ELSE 0 END),0) estimated FROM appointments WHERE employee_id=? AND start_at>=? AND start_at<?",[$employee,$from,$until]);
 }
}
