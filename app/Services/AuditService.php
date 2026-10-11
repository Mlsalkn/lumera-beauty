<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\AdminRepository as R;
final class AuditService
{
 public static function record(int $actor,string $action,string $type,?int $id,string $outcome='success'):void {
  // No submitted values, passwords, tokens, request URLs or SMTP errors are stored.
  R::execute('INSERT INTO audit_events(actor_id,action,target_type,target_id,outcome) VALUES (?,?,?,?,?)',[$actor,mb_substr($action,0,80),mb_substr($type,0,40),$id,$outcome]);
 }
}
