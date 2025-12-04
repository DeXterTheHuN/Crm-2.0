<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$userId = $_SESSION['user_id'];
$isAdmin = isAdmin();

try {
    switch ($action) {
        case 'get_counts':
            // Chat olvasatlan üzenetek
            $chatStmt = $pdo->prepare("
                SELECT COUNT(*) as unread_count
                FROM chat_messages cm
                LEFT JOIN chat_read_status crs ON crs.user_id = ? AND cm.id <= crs.last_read_message_id
                WHERE cm.user_id != ? AND crs.last_read_message_id IS NULL
            ");
            $chatStmt->execute([$userId, $userId]);
            $chatCount = $chatStmt->fetch()['unread_count'];
            
            // Függő jóváhagyások (csak adminoknak)
            $approvalCount = 0;
            if ($isAdmin) {
                $approvalStmt = $pdo->query("
                    SELECT COUNT(*) as pending_count
                    FROM clients
                    WHERE approved = 0
                ");
                $approvalCount = $approvalStmt->fetch()['pending_count'];
            }
            
            // Új ügyfelek (akiket még nem láttam)
            $newClientsStmt = $pdo->prepare("
                SELECT COUNT(DISTINCT c.id) as new_count
                FROM clients c
                LEFT JOIN client_views cv ON c.id = cv.client_id AND cv.user_id = ?
                WHERE c.approved = 1 
                AND c.closed_at IS NULL
                AND cv.viewed_at IS NULL
                " . (!$isAdmin ? "AND (c.agent_id = ? OR c.agent_id IS NULL)" : "")
            );
            
            if ($isAdmin) {
                $newClientsStmt->execute([$userId]);
            } else {
                $newClientsStmt->execute([$userId, $userId]);
            }
            $newClientsCount = $newClientsStmt->fetch()['new_count'];
            
            // Új ügyfelek megyénként
            $newByCountyStmt = $pdo->prepare("
                SELECT c.county_id, co.name as county_name, COUNT(DISTINCT c.id) as new_count
                FROM clients c
                JOIN counties co ON c.county_id = co.id
                LEFT JOIN client_views cv ON c.id = cv.client_id AND cv.user_id = ?
                WHERE c.approved = 1 
                AND c.closed_at IS NULL
                AND cv.viewed_at IS NULL
                " . (!$isAdmin ? "AND (c.agent_id = ? OR c.agent_id IS NULL)" : "") . "
                GROUP BY c.county_id, co.name
                HAVING new_count > 0
            ");
            
            if ($isAdmin) {
                $newByCountyStmt->execute([$userId]);
            } else {
                $newByCountyStmt->execute([$userId, $userId]);
            }
            $newByCounty = $newByCountyStmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'chat_unread' => (int)$chatCount,
                'approvals_pending' => (int)$approvalCount,
                'new_clients_total' => (int)$newClientsCount,
                'new_clients_by_county' => $newByCounty
            ]);
            break;
            
        case 'mark_client_viewed':
            $clientId = $_POST['client_id'] ?? 0;
            
            if ($clientId > 0) {
                // Ellenőrizzük, hogy a felhasználó láthatja-e az ügyfelet
                $checkStmt = $pdo->prepare("
                    SELECT id FROM clients 
                    WHERE id = ? 
                    AND approved = 1
                    " . (!$isAdmin ? "AND (agent_id = ? OR agent_id IS NULL)" : "")
                );
                
                if ($isAdmin) {
                    $checkStmt->execute([$clientId]);
                } else {
                    $checkStmt->execute([$clientId, $userId]);
                }
                
                if ($checkStmt->fetch()) {
                    // Megtekintettnek jelöljük
                    $viewStmt = $pdo->prepare("
                        INSERT INTO client_views (client_id, user_id, viewed_at)
                        VALUES (?, ?, NOW())
                        ON DUPLICATE KEY UPDATE viewed_at = NOW()
                    ");
                    $viewStmt->execute([$clientId, $userId]);
                    
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid client ID']);
            }
            break;
            
        case 'mark_county_clients_viewed':
            $countyId = $_POST['county_id'] ?? 0;
            
            if ($countyId > 0) {
                // Lekérjük az összes ügyfelet a megyében, amihez a felhasználónak joga van
                $clientsStmt = $pdo->prepare("
                    SELECT id FROM clients 
                    WHERE county_id = ? 
                    AND approved = 1
                    AND closed_at IS NULL
                    " . (!$isAdmin ? "AND (agent_id = ? OR agent_id IS NULL)" : "")
                );
                
                if ($isAdmin) {
                    $clientsStmt->execute([$countyId]);
                } else {
                    $clientsStmt->execute([$countyId, $userId]);
                }
                
                $clients = $clientsStmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (count($clients) > 0) {
                    // Tömeges beszúrás
                    $values = [];
                    $params = [];
                    foreach ($clients as $clientId) {
                        $values[] = '(?, ?, NOW())';
                        $params[] = $clientId;
                        $params[] = $userId;
                    }
                    
                    $sql = "INSERT INTO client_views (client_id, user_id, viewed_at) VALUES "
                         . implode(', ', $values)
                         . " ON DUPLICATE KEY UPDATE viewed_at = NOW()";
                    
                    $bulkStmt = $pdo->prepare($sql);
                    $bulkStmt->execute($params);
                    
                    echo json_encode(['success' => true, 'marked_count' => count($clients)]);
                } else {
                    echo json_encode(['success' => true, 'marked_count' => 0]);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid county ID']);
            }
            break;
            
        case 'get_latest_chat_message':
            // Legutóbbi üzenet lekérése (toast értesítéshez)
            $lastCheckTime = $_GET['last_check'] ?? date('Y-m-d H:i:s', strtotime('-10 seconds'));
            
            $latestStmt = $pdo->prepare("
                SELECT cm.*, 
                       (SELECT last_read_message_id FROM chat_read_status WHERE user_id = ?) as last_read_id
                FROM chat_messages cm
                WHERE cm.user_id != ?
                AND cm.created_at > ?
                ORDER BY cm.created_at DESC
                LIMIT 1
            ");
            $latestStmt->execute([$userId, $userId, $lastCheckTime]);
            $latest = $latestStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($latest && (!$latest['last_read_id'] || $latest['id'] > $latest['last_read_id'])) {
                echo json_encode([
                    'success' => true,
                    'has_new' => true,
                    'message' => [
                        'id' => $latest['id'],
                        'user_name' => $latest['user_name'],
                        'message' => mb_substr($latest['message'], 0, 50) . (mb_strlen($latest['message']) > 50 ? '...' : ''),
                        'created_at' => $latest['created_at']
                    ]
                ]);
            } else {
                echo json_encode(['success' => true, 'has_new' => false]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
