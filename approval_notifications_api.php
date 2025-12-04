<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_unread':
            // Olvasatlan értesítések lekérdezése
            $user_id = $_SESSION['user_id'];
            
            $stmt = $pdo->prepare("
                SELECT id, client_id, client_name, approval_status, rejection_reason, created_at
                FROM approval_notifications
                WHERE user_id = ? AND read_at IS NULL
                ORDER BY created_at DESC
            ");
            $stmt->execute([$user_id]);
            $notifications = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'count' => count($notifications)
            ]);
            break;
            
        case 'mark_read':
            // Értesítés olvasottnak jelölése
            $notification_id = (int)($_POST['notification_id'] ?? 0);
            $user_id = $_SESSION['user_id'];
            
            if ($notification_id) {
                $stmt = $pdo->prepare("
                    UPDATE approval_notifications 
                    SET read_at = NOW() 
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$notification_id, $user_id]);
                
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Hiányzó értesítés ID']);
            }
            break;
            
        case 'mark_all_read':
            // Összes értesítés olvasottnak jelölése
            $user_id = $_SESSION['user_id'];
            
            $stmt = $pdo->prepare("
                UPDATE approval_notifications 
                SET read_at = NOW() 
                WHERE user_id = ? AND read_at IS NULL
            ");
            $stmt->execute([$user_id]);
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Érvénytelen művelet']);
    }
    
} catch (PDOException $e) {
    error_log("Approval notifications API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Adatbázis hiba']);
}
