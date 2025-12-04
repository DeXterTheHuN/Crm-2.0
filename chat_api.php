<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

try {
    switch ($action) {
        case 'get_messages':
            $last_id = $_GET['last_id'] ?? 0;
            
            $stmt = $pdo->prepare("
                SELECT id, user_id, user_name, message, created_at
                FROM chat_messages
                WHERE id > ?
                ORDER BY created_at ASC
                LIMIT 50
            ");
            $stmt->execute([$last_id]);
            $messages = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'messages' => $messages
            ]);
            break;
            
        case 'send_message':
            $message = trim($_POST['message'] ?? '');
            
            if (empty($message)) {
                echo json_encode(['success' => false, 'error' => 'Az üzenet nem lehet üres']);
                exit;
            }
            
            if (strlen($message) > 2000) {
                echo json_encode(['success' => false, 'error' => 'Az üzenet túl hosszú (max 2000 karakter)']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO chat_messages (user_id, user_name, message)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$user_id, $user_name, $message]);
            
            echo json_encode([
                'success' => true,
                'message_id' => $pdo->lastInsertId()
            ]);
            break;
            
        case 'mark_read':
            $last_id = $_GET['last_id'] ?? 0;
            
            $stmt = $pdo->prepare("
                INSERT INTO chat_read_status (user_id, last_read_message_id, last_read_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    last_read_message_id = ?,
                    last_read_at = NOW()
            ");
            $stmt->execute([$user_id, $last_id, $last_id]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'get_unread_count':
            // Utolsó olvasott üzenet ID lekérése
            $stmt = $pdo->prepare("
                SELECT last_read_message_id
                FROM chat_read_status
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            $read_status = $stmt->fetch();
            
            $last_read_id = $read_status ? $read_status['last_read_message_id'] : 0;
            
            // Olvasatlan üzenetek száma
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as unread_count
                FROM chat_messages
                WHERE id > ? AND user_id != ?
            ");
            $stmt->execute([$last_read_id, $user_id]);
            $result = $stmt->fetch();
            
            echo json_encode([
                'success' => true,
                'unread_count' => $result['unread_count']
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Érvénytelen művelet']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
