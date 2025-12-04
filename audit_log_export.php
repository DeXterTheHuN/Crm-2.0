<?php
// audit_log_export.php
require_once 'config.php';
requireLogin();

if (!isAdmin()) {
    die('Nincs jogosultság');
}

$filter_user = $_GET['filter_user'] ?? '';
$filter_action = $_GET['filter_action'] ?? '';
$filter_table = $_GET['filter_table'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

$where_conditions = ["1=1"];
$params = [];

if (!empty($filter_user)) { $where_conditions[] = "user_id = ?"; $params[] = $filter_user; }
if (!empty($filter_action)) { $where_conditions[] = "action = ?"; $params[] = $filter_action; }
if (!empty($filter_table)) { $where_conditions[] = "table_name = ?"; $params[] = $filter_table; }
if (!empty($filter_date_from)) { $where_conditions[] = "created_at >= ?"; $params[] = $filter_date_from . ' 00:00:00'; }
if (!empty($filter_date_to)) { $where_conditions[] = "created_at <= ?"; $params[] = $filter_date_to . ' 23:59:59'; }

$where_sql = implode(" AND ", $where_conditions);

$stmt = $pdo->prepare("SELECT * FROM audit_logs WHERE $where_sql ORDER BY created_at DESC");
$stmt->execute($params);
$logs = $stmt->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="audit_log_' . date('Y-m-d_H-i-s') . '.csv"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, ['Időpont', 'Felhasználó', 'Művelet', 'Tábla', 'Rekord ID', 'IP cím', 'Régi érték', 'Új érték']);

foreach ($logs as $log) {
    fputcsv($output, [
        $log['created_at'],
        $log['user_name'],
        $log['action'],
        $log['table_name'],
        $log['record_id'],
        $log['ip_address'],
        $log['old_values'],
        $log['new_values']
    ]);
}

fclose($output);
