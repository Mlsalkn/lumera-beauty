<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Input;
use App\Models\AdminRepository as R;
final class EmployeeCalendarService
{
 public function day(int $employee,string $date):array
 {
  $day=Input::date($date);$next=$day->modify('+1 day')->format('Y-m-d');$hours=R::rows('SELECT * FROM working_hours WHERE employee_id=? AND weekday=? ORDER BY start_time',[$employee,(int)$day->format('N')]);
  $breaks=R::rows('SELECT * FROM employee_breaks WHERE employee_id=? AND weekday=? ORDER BY start_time',[$employee,(int)$day->format('N')]);$leaves=R::rows('SELECT * FROM employee_leaves WHERE employee_id=? AND start_at<? AND end_at>? ORDER BY start_at',[$employee,$next,$date]);$bookings=R::appointments('a.employee_id=? AND a.start_at<? AND a.end_at>?',[$employee,$next,$date],null,true);
  $blocked=[];foreach($breaks as$b){$blocked[]=[$date.' '.$b['start_time'],$date.' '.$b['end_time']];}foreach($leaves as$l){$blocked[]=[$l['start_at'],$l['end_at']];}foreach($bookings as$a){if($a['status']!=='cancelled'){$blocked[]=[$a['start_at'],$a['end_at']];}}usort($blocked,static fn($a,$b)=>strcmp($a[0],$b[0]));$free=[];
  foreach($hours as$h){$cursor=$date.' '.$h['start_time'];$close=$date.' '.$h['end_time'];foreach($blocked as[$start,$end]){if($end<=$cursor||$start>=$close){continue;}if($start>$cursor){$free[]=['start'=>substr($cursor,11,5),'end'=>substr(min($start,$close),11,5)];}$cursor=max($cursor,min($end,$close));if($cursor>=$close){break;}}if($cursor<$close){$free[]=['start'=>substr($cursor,11,5),'end'=>substr($close,11,5)];}}
  return['date'=>$date,'hours'=>$hours,'breaks'=>$breaks,'leaves'=>$leaves,'appointments'=>$bookings,'free'=>$free];
 }
 public function range(int $employee,string $mode,string $date):array
 {
  if(!in_array($mode,['day','week','month'],true)){throw new \DomainException('Geçersiz takvim görünümü.');}$selected=Input::date($date);if((int)$selected->format('Y')<2000||(int)$selected->format('Y')>2100){throw new \DomainException('Takvim tarihi 2000–2100 arasında olmalıdır.');}
  $start=match($mode){'day'=>$selected,'week'=>$selected->modify('monday this week'),'month'=>$selected->modify('first day of this month')};$end=$start->modify(match($mode){'day'=>'+1 day','week'=>'+7 days','month'=>'+1 month'});$days=[];for($d=$start;$d<$end;$d=$d->modify('+1 day')){$days[]=$this->day($employee,$d->format('Y-m-d'));}return['mode'=>$mode,'date'=>$date,'start'=>$start,'end'=>$end,'days'=>$days];
 }
}
