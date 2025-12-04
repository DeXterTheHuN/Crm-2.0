<?php
/**
 * Audit Log Helper Functions
 */

function logAudit($pdo, $action, $table_name, $record_id = null, $old_values = null, $new_values = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, user_name, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $_SESSION['name'] ?? 'System',
            $action,
            $table_name,
            $record_id,
            $old_values ? json_encode($old_values, JSON_UNESCAPED_UNICODE) : null,
            $new_values ? json_encode($new_values, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Audit log error: " . $e->getMessage());
        return false;
    }
}

function logClientCreate($pdo, $client_id, $client_data) {
    logAudit($pdo, 'CREATE', 'clients', $client_id, null, $client_data);
}

function logClientUpdate($pdo, $client_id, $old_data, $new_data) {
    logAudit($pdo, 'UPDATE', 'clients', $client_id, $old_data, $new_data);
}

function logClientDelete($pdo, $client_id, $client_data) {
    logAudit($pdo, 'DELETE', 'clients', $client_id, $client_data, null);
}

function logClientApprove($pdo, $client_id, $client_data) {
    logAudit($pdo, 'APPROVE', 'clients', $client_id, null, $client_data);
}

function logClientReject($pdo, $client_id, $client_data, $reason = null) {
    $data = $client_data;
    $data['rejection_reason'] = $reason;
    logAudit($pdo, 'REJECT', 'clients', $client_id, null, $data);
}

function logUserCreate($pdo, $user_id, $user_data) {
    unset($user_data['password']); // Ne tároljuk a jelszót
    logAudit($pdo, 'CREATE', 'users', $user_id, null, $user_data);
}

function logUserUpdate($pdo, $user_id, $old_data, $new_data) {
    unset($old_data['password'], $new_data['password']);
    logAudit($pdo, 'UPDATE', 'users', $user_id, $old_data, $new_data);
}

function logUserDelete($pdo, $user_id, $user_data) {
    unset($user_data['password']);
    logAudit($pdo, 'DELETE', 'users', $user_id, $user_data, null);
}

function logLogin($pdo, $user_id, $success = true) {
    logAudit($pdo, $success ? 'LOGIN' : 'LOGIN_FAILED', 'users', $user_id, null, ['success' => $success]);
}

function logLogout($pdo, $user_id) {
    logAudit($pdo, 'LOGOUT', 'users', $user_id, null, null);
}

function logRoleChange($pdo, $user_id, $old_role, $new_role) {
    logAudit($pdo, 'ROLE_CHANGE', 'users', $user_id, ['role' => $old_role], ['role' => $new_role]);
}
