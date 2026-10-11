<?php
declare(strict_types=1);
return [
 'cancel_before_minutes'=>max(0,(int)env('APPOINTMENT_CANCEL_BEFORE_MINUTES','120')),
 'reschedule_before_minutes'=>max(0,(int)env('APPOINTMENT_RESCHEDULE_BEFORE_MINUTES','120')),
 'booking_days'=>max(1,min(365,(int)env('APPOINTMENT_BOOKING_DAYS','90'))),
 'slot_minutes'=>max(5,min(60,(int)env('APPOINTMENT_SLOT_MINUTES','15'))),
];
