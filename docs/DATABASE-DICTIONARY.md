# Luméra Beauty — gerçek veritabanı sözlüğü

24 tablo / 32 foreign key; yalnız yapı metaverisi, veri satırı içermez.

## appointment_cancellation_requests

| Alan | Tür | NULL | Anahtar |
|---|---|---|---|
| id | bigint(20) unsigned | NO | PRI |
| appointment_id | bigint(20) unsigned | NO | MUL |
| employee_id | bigint(20) unsigned | NO | MUL |
| reason | varchar(1000) | NO |  |
| status | enum('pending','approved','rejected') | NO |  |
| reviewer_id | bigint(20) unsigned | YES | MUL |
| reviewed_at | datetime | YES |  |
| response | varchar(1000) | YES |  |
| created_at | timestamp | NO |  |

## appointment_status_history

| Alan | Tür | NULL | Anahtar |
|---|---|---|---|
| id | bigint(20) unsigned | NO | PRI |
| appointment_id | bigint(20) unsigned | NO | MUL |
| actor_id | bigint(20) unsigned | YES | MUL |
| event | varchar(30) | NO |  |
| old_status | varchar(12) | YES |  |
| new_status | varchar(12) | NO |  |
| old_start_at | datetime | YES |  |
| new_start_at | datetime | NO |  |
| created_at | timestamp | NO |  |

## appointments

| Alan | Tür | NULL | Anahtar |
|---|---|---|---|
| id | bigint(20) unsigned | NO | PRI |
| customer_id | bigint(20) unsigned | NO | MUL |
| employee_id | bigint(20) unsigned | NO | MUL |
| service_id | bigint(20) unsigned | NO | MUL |
| start_at | datetime | NO |  |
| end_at | datetime | NO |  |
| status | enum('pending','confirmed','completed','cancelled') | NO |  |
| service_price | decimal(10,2) | NO |  |
| created_at | timestamp | NO |  |
| customer_note | text | YES |  |

## audit_events

| Alan | Tür | NULL | Anahtar |
|---|---|---|---|
| id | bigint(20) unsigned | NO | PRI |
| actor_id | bigint(20) unsigned | YES | MUL |
| action | varchar(80) | NO |  |
| target_type | varchar(40) | NO |  |
| target_id | bigint(20) unsigned | YES |  |
| outcome | enum('success','denied','failure') | NO |  |
| created_at | datetime | NO |  |

## auth_tokens

| Alan | Tür | NULL | Anahtar |
|---|---|---|---|
| id | bigint(20) unsigned | NO | PRI |
| user_id | bigint(20) unsigned | NO | MUL |
| purpose | enum('verify','reset') | NO |  |
| token_hash | char(64) | NO | UNI |
| email_snapshot | varchar(190) | NO |  |
| expires_at | datetime | NO |  |
| used_at | datetime | YES |  |
| created_at | datetime | NO |  |

## customer_preferences

| Alan | Tür | NULL | Anahtar |
|---|---|---|---|
| customer_id | bigint(20) unsigned | NO | PRI |
| appointment_notifications | tinyint(1) | NO |  |

## email_change_requests

| Alan | Tür | NULL | Anahtar |
|---|---|---|---|
| id | bigint(20) unsigned | NO | PRI |
| customer_id | bigint(20) unsigned | NO | UNI |
| old_email | varchar(190) | NO |  |
| new_email | varchar(190) | NO |  |
| token_hash | char(64) | NO | UNI |
| expires_at | datetime | NO |  |
| created_at | datetime | NO |  |

## employee_breaks

| Alan | Tür | NULL | Anahtar |
|---|---|---|---|
| id | bigint(20) unsigned | NO | PRI |
| employee_id | bigint(20) unsigned | NO | MUL |
| weekday | tinyint(3) unsigned | NO |  |
| start_time | time | NO |  |
| end_time | time | NO |  |
| reason | varchar(120) | NO |  |
| created_at | timestamp | NO |  |

## employee_leave_requests

| Alan | Tür | NULL | Anahtar |
|---|---|---|---|
| id | bigint(20) unsigned | NO | PRI |
| employee_id | bigint(20) unsigned | NO | MUL |
| start_at | datetime | NO |  |
| end_at | datetime | NO |  |
| reason | varchar(1000) | NO |  |
| status | enum('pending','approved','rejected') | NO |  |
| reviewer_id | bigint(20) unsigned | YES | MUL |
| reviewed_at | datetime | YES |  |
| response | varchar(1000) | YES |  |
| approved_leave_id | bigint(20) unsigned | YES | MUL |
| created_at | timestamp | NO |  |

## employee_leaves

| Alan | Tür | NULL | Anahtar |
|---|---|---|---|
| id | bigint(20) unsigned | NO | PRI |
| employee_id | bigint(20) unsigned | NO | MUL |
| start_at | datetime | NO |  |
| end_at | datetime | NO |  |
| reason | varchar(255) | YES |  |
| created_at | timestamp | NO |  |

## employee_permissions

| Alan | Tür | NULL | Anahtar |
|---|---|---|---|
| employee_id | bigint(20) unsigned | NO | PRI |
| can_confirm | tinyint(1) | NO |  |
| can_complete | tinyint(1) | NO |  |
| can_request_cancel | tinyint(1) | NO |  |

## employee_services

| Alan | Tür | NULL | Anahtar |
|---|---|---|---|
| employee_id | bigint(20) unsigned | NO | PRI |
| service_id | bigint(20) unsigned | NO | PRI |

## employees

| Alan | Tür | NULL | Anahtar |
|---|---|---|---|
| id | bigint(20) unsigned | NO | PRI |
| user_id | bigint(20) unsigned | NO | UNI |
| title | varchar(120) | NO |  |
| bio | text | YES |  |
| photo_path | varchar(255) | YES |  |
| is_active | tinyint(1) | NO |  |

## favorites

| Alan | Tür | NULL | Anahtar |
|---|---|---|---|
| customer_id | bigint(20) unsigned | NO | PRI |
| service_id | bigint(20) unsigned | NO | PRI |
| created_at | timestamp | NO |  |

## mail_outbox

| Alan | Tür | NULL | Anahtar |
|---|---|---|---|
| id | bigint(20) unsigned | NO | PRI |
| user_id | bigint(20) unsigned | NO | MUL |
| appointment_id | bigint(20) unsigned | YES | MUL |
| dedupe_key | varchar(190) | NO | UNI |
| event | varchar(40) | NO |  |
| payload | mediumtext | NO |  |
| status | enum('pending','sent','failed','skipped') | NO | MUL |
| attempts | smallint(5) unsigned | NO |  |
| next_attempt_at | datetime | NO |  |
| created_at | datetime | NO |  |
| sent_at | datetime | YES |  |
| last_error | varchar(80) | YES |  |

## notifications

| Alan | Tür | NULL | Anahtar |
|---|---|---|---|
| id | bigint(20) unsigned | NO | PRI |
| customer_id | bigint(20) unsigned | NO | MUL |
| appointment_id | bigint(20) unsigned | YES | MUL |
| event | varchar(30) | NO |  |
| message | varchar(500) | NO |  |
| read_at | datetime | YES |  |
| created_at | timestamp | NO |  |

## reviews

| Alan | Tür | NULL | Anahtar |
|---|---|---|---|
| id | bigint(20) unsigned | NO | PRI |
| appointment_id | bigint(20) unsigned | NO | UNI |
| customer_id | bigint(20) unsigned | NO | MUL |
| rating | tinyint(3) unsigned | NO |  |
| comment | text | YES |  |
| is_visible | tinyint(1) | NO |  |
| created_at | timestamp | NO |  |

## sample_content

| Alan | Tür | NULL | Anahtar |
|---|---|---|---|
| entity_type | enum('service','employee') | NO | PRI |
| seed_key | varchar(60) | NO | PRI |
| entity_id | bigint(20) unsigned | NO |  |

## services

| Alan | Tür | NULL | Anahtar |
|---|---|---|---|
| id | bigint(20) unsigned | NO | PRI |
| name | varchar(150) | NO |  |
| description | text | YES |  |
| category | varchar(100) | YES |  |
| duration_minutes | smallint(5) unsigned | NO |  |
| price | decimal(10,2) | NO |  |
| is_active | tinyint(1) | NO |  |
| image_path | varchar(255) | YES |  |

## support_tickets

| Alan | Tür | NULL | Anahtar |
|---|---|---|---|
| id | bigint(20) unsigned | NO | PRI |
| reference | char(24) | NO | UNI |
| user_id | bigint(20) unsigned | YES | MUL |
| name | varchar(120) | NO |  |
| email | varchar(190) | NO |  |
| subject | varchar(160) | NO |  |
| message | text | NO |  |
| status | enum('open','in_progress','resolved') | NO | MUL |
| created_at | datetime | NO |  |
| updated_at | datetime | NO |  |

## system_settings

| Alan | Tür | NULL | Anahtar |
|---|---|---|---|
| setting_key | varchar(80) | NO | PRI |
| setting_value | varchar(500) | NO |  |
| updated_at | datetime | NO |  |

## user_security

| Alan | Tür | NULL | Anahtar |
|---|---|---|---|
| user_id | bigint(20) unsigned | NO | PRI |
| email_verified_at | datetime | YES |  |

## users

| Alan | Tür | NULL | Anahtar |
|---|---|---|---|
| id | bigint(20) unsigned | NO | PRI |
| name | varchar(120) | NO |  |
| email | varchar(190) | NO | UNI |
| password_hash | varchar(255) | NO |  |
| phone | varchar(25) | YES |  |
| role | enum('CUSTOMER','EMPLOYEE','ADMIN') | NO |  |
| is_active | tinyint(1) | NO |  |
| created_at | timestamp | NO |  |
| updated_at | timestamp | NO |  |
| first_name | varchar(60) | YES |  |
| last_name | varchar(60) | YES |  |
| photo_path | varchar(255) | YES |  |
| auth_version | int(10) unsigned | NO |  |

## working_hours

| Alan | Tür | NULL | Anahtar |
|---|---|---|---|
| id | bigint(20) unsigned | NO | PRI |
| employee_id | bigint(20) unsigned | NO | MUL |
| weekday | tinyint(3) unsigned | NO |  |
| start_time | time | NO |  |
| end_time | time | NO |  |

sample_content.entity_id genel registry bağlantısıdır; SQL foreign key değildir.
