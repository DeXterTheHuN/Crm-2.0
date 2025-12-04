<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$client_id = $_GET['id'] ?? 0;

if (!$client_id) {
    echo json_encode(['success' => false, 'error' => 'Hiányzó ügyfél ID']);
    exit;
}

try {
    // Ügyfél adatok lekérdezése
    $stmt = $pdo->prepare("
        SELECT c.*, s.name as settlement_name 
        FROM clients c
        LEFT JOIN settlements s ON c.settlement_id = s.id
        WHERE c.id = ?
    ");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        echo json_encode(['success' => false, 'error' => 'Ügyfél nem található']);
        exit;
    }
    
    // Jogosultság ellenőrzése
    $current_user_agent_id = null;
    if (!isAdmin()) {
        $stmt = $pdo->prepare("SELECT id FROM agents WHERE name = ?");
        $stmt->execute([$_SESSION['name']]);
        $current_agent = $stmt->fetch();
        if ($current_agent) {
            $current_user_agent_id = $current_agent['id'];
        }
        
        // Jogosultság ellenőrzése:
        // Adminok mindig szerkeszthetnek.
        // Ügyintéző (nem admin) csak akkor, ha:
        // 1. Nincs hozzárendelve ügyintéző (agent_id NULL)
        // 2. Ő van hozzárendelve (agent_id == current_user_agent_id)
        $can_edit = isAdmin() || ($current_user_agent_id && 
                    ($client['agent_id'] === null || $client['agent_id'] == $current_user_agent_id));
        
        if (!$can_edit) {
            echo json_encode(['success' => false, 'error' => 'Nincs jogosultságod ehhez az ügyfélhez']);
            exit;
        }
    } else {
        // Adminok mindig szerkeszthetnek, nincs további ellenőrzés
    }
    
    // Megyék lekérdezése
    $stmt = $pdo->query("SELECT * FROM counties ORDER BY name");
    $counties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Települések lekérdezése a megye alapján
    $stmt = $pdo->prepare("SELECT * FROM settlements WHERE county_id = ? ORDER BY name");
    $stmt->execute([$client['county_id']]);
    $settlements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ügyintézők lekérdezése (csak aktív felhasználók)
    $stmt = $pdo->query("
        SELECT a.* FROM agents a
        INNER JOIN users u ON a.name = u.name
        WHERE u.approved = 1
        ORDER BY a.name
    ");
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'client' => $client,
        'counties' => $counties,
        'settlements' => $settlements,
        'agents' => $agents
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
