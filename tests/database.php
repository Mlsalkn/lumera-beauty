<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
$pdo = App\Core\Database::connection();
$config = require BASE_PATH . '/config/database.php';
$passed = 0;
$check = static function (bool $condition, string $message) use (&$passed): void {
    if (!$condition) { throw new RuntimeException('FAIL: ' . $message); }
    $passed++; echo 'PASS: ' . $message . PHP_EOL;
};
$check($config['database'] === 'lumera_beauty_db', 'Correct database');
$check((bool) $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES) === false, 'Native prepared statements');
$account = $pdo->query('SELECT CURRENT_USER()')->fetchColumn();
$check($account === 'lumera_app@127.0.0.1', 'Dedicated application account');
$privileges = $pdo->prepare('SELECT PRIVILEGE_TYPE FROM information_schema.SCHEMA_PRIVILEGES WHERE TABLE_SCHEMA = ?');
$privileges->execute([$config['database']]);
$allowed = $privileges->fetchAll(PDO::FETCH_COLUMN);
sort($allowed);
$check($allowed === ['DELETE', 'INSERT', 'SELECT', 'UPDATE'], 'Application account has only CRUD schema privileges');
$tables = $pdo->prepare('SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?');
$tables->execute([$config['database']]);
$metadata = array_column($tables->fetchAll(), null, 'TABLE_NAME');
foreach(['user_security','auth_tokens','mail_outbox','system_settings','audit_events']as$table){$check(isset($metadata[$table])&&$metadata[$table]['ENGINE']==='InnoDB', $table.' InnoDB');}
foreach (['users', 'employees', 'services', 'employee_services', 'working_hours', 'appointments', 'reviews', 'employee_leaves', 'favorites', 'notifications', 'customer_preferences', 'appointment_status_history', 'email_change_requests', 'employee_permissions', 'employee_breaks', 'employee_leave_requests', 'appointment_cancellation_requests'] as $table) {
    $check(isset($metadata[$table]) && $metadata[$table]['ENGINE'] === 'InnoDB' && str_starts_with($metadata[$table]['TABLE_COLLATION'], 'utf8mb4'), $table . ' InnoDB / UTF8MB4');
}
$fk = $pdo->prepare('SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL');
$fk->execute([$config['database']]);
$relations = [];
foreach ($fk->fetchAll() as $row) { $relations[] = $row['TABLE_NAME'] . '.' . $row['COLUMN_NAME'] . '>' . $row['REFERENCED_TABLE_NAME'] . '.' . $row['REFERENCED_COLUMN_NAME']; }
foreach(['user_security.user_id>users.id','auth_tokens.user_id>users.id','mail_outbox.user_id>users.id','mail_outbox.appointment_id>appointments.id','audit_events.actor_id>users.id']as$relation){$check(in_array($relation,$relations,true),'Foreign key '.$relation);}
foreach (['employees.user_id>users.id', 'employee_services.employee_id>employees.id', 'employee_services.service_id>services.id', 'working_hours.employee_id>employees.id', 'appointments.customer_id>users.id', 'appointments.employee_id>employees.id', 'appointments.service_id>services.id', 'reviews.appointment_id>appointments.id', 'reviews.customer_id>users.id', 'employee_leaves.employee_id>employees.id'] as $relation) {
    $check(in_array($relation, $relations, true), 'Foreign key ' . $relation);
}
$columns = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');
$columns->execute([$config['database'],'services']);
$check(in_array('image_path',$columns->fetchAll(PDO::FETCH_COLUMN),true),'Service image column exists');
foreach (['users'=>['first_name','last_name','photo_path','auth_version'],'appointments'=>['customer_note']] as $table=>$required) {
    $columns->execute([$config['database'],$table]); $actual=$columns->fetchAll(PDO::FETCH_COLUMN);
    foreach($required as $column){$check(in_array($column,$actual,true),$table.'.'.$column.' exists');}
}
foreach (['favorites.customer_id>users.id','favorites.service_id>services.id','notifications.customer_id>users.id','notifications.appointment_id>appointments.id','customer_preferences.customer_id>users.id','appointment_status_history.appointment_id>appointments.id','appointment_status_history.actor_id>users.id','email_change_requests.customer_id>users.id'] as $relation) {
    $check(in_array($relation,$relations,true),'Foreign key '.$relation);
}
$sampleServices = require BASE_PATH . '/database/seeds/services.php';
foreach (['employee_permissions.employee_id>employees.id','employee_breaks.employee_id>employees.id','employee_leave_requests.employee_id>employees.id','employee_leave_requests.reviewer_id>users.id','employee_leave_requests.approved_leave_id>employee_leaves.id','appointment_cancellation_requests.employee_id>employees.id','appointment_cancellation_requests.appointment_id>appointments.id','appointment_cancellation_requests.reviewer_id>users.id'] as $relation) {
    $check(in_array($relation,$relations,true),'Foreign key '.$relation);
}
$find = $pdo->prepare('SELECT duration_minutes, price FROM services WHERE name = ?');
foreach ($sampleServices as $service) {
    $find->execute([$service['name']]); $row = $find->fetch();
    // Catalog prices and names are now editable. Existing rows must be valid, not frozen to the seed.
    $check($row === false || ((int)$row['duration_minutes'] > 0 && (float)$row['price'] >= 0), 'Valid catalog data for original sample ' . $service['name']);
}
$invalidHashes = 0;
foreach ($pdo->query('SELECT password_hash FROM users')->fetchAll() as $account) {
    if ((password_get_info($account['password_hash'])['algoName'] ?? 'unknown') === 'unknown') { $invalidHashes++; }
}
$check($invalidHashes === 0, 'Accounts contain valid password hashes');
echo $passed . " database tests passed (read-only).\n";
