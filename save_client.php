<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Érvénytelen kérés']);
    exit;
}

$client_id = $_POST['id'] ?? 0;
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$address = trim($_POST['address'] ?? '');
$county_id = $_POST['county_id'] ?? 0;
$settlement_id = $_POST['settlement_id'] ?? 0;
$agent_id = $_POST['agent_id'] ?? null;
$insulation_area = $_POST['insulation_area'] ?? null;
// A JS most már 0-t vagy 1-et küld, ezért közvetlenül kiolvassuk
$contract_signed = $_POST['contract_signed'] ?? 0;
$work_completed = $_POST['work_completed'] ?? 0;
$notes = trim($_POST['notes'] ?? '');

// Validáció
if (empty($name)) {
    echo json_encode(['success' => false, 'error' => 'A név megadása kötelező']);
    exit;
}

if (!$client_id) {
    echo json_encode(['success' => false, 'error' => 'Hiányzó ügyfél ID']);
    exit;
}

try {
    // Jogosultság ellenőrzése
    $stmt = $pdo->prepare("SELECT agent_id FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch();
    
    if (!$client) {
        echo json_encode(['success' => false, 'error' => 'Ügyfél nem található']);
        exit;
    }
    
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
    }
    
    // Automatikus lezárás/újranyitás logika (adminoknak és ügyintézőknek is)
    $closed_at = null;
    $contract_signed_at = null;
    
    // Ellenőrizzük a jelenlegi állapotot
    $check_stmt = $pdo->prepare("SELECT closed_at, contract_signed, contract_signed_at FROM clients WHERE id = ?");
    $check_stmt->execute([$client_id]);
    $existing = $check_stmt->fetch();
    
    // Szerződés aláírás dátumának kezelése
    if ($contract_signed) {
        if ($existing && $existing['contract_signed_at']) {
            // Már van szerződés dátum, megtartjuk
            $contract_signed_at = $existing['contract_signed_at'];
        } elseif (!$existing['contract_signed']) {
            // Most lett bepipálva először, új dátumot adunk
            $contract_signed_at = date('Y-m-d H:i:s');
        }
    }
    // Ha kivették a pipát, contract_signed_at = NULL
    
    if ($contract_signed && $work_completed) {
        if ($existing && $existing['closed_at']) {
            // Már le van zárva, megtartjuk a régi dátumot
            $closed_at = $existing['closed_at'];
        } else {
            // Még nincs lezárva, új dátumot adunk
            $closed_at = date('Y-m-d H:i:s');
        }
    }
    // Ha valamelyik pipa nincs bejelolve, closed_at = NULL (újranyitás - csak adminoknak)
    
    // Üres agent_id kezelése
    if (empty($agent_id)) {
        $agent_id = null;
    }
    
    // Üres insulation_area kezelése
    if (empty($insulation_area)) {
        $insulation_area = null;
    }
    
    // Frissítés
    if (isAdmin()) {
        $stmt = $pdo->prepare("
            UPDATE clients SET 
                name = ?, county_id = ?, settlement_id = ?, address = ?, 
                email = ?, phone = ?, insulation_area = ?, 
                contract_signed = ?, work_completed = ?, agent_id = ?, notes = ?,
                closed_at = ?, contract_signed_at = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $name, $county_id, $settlement_id, $address, 
            $email, $phone, $insulation_area, 
            $contract_signed, $work_completed, $agent_id, $notes, $closed_at, $contract_signed_at, $client_id
        ]);
    } else {
        // Ügyintézők csak bizonyos mezőket módosíthatnak
        
        // Ügyintéző csak a saját ID-jét vagy NULL-t mentheti
        $allowed_agent_id = null;
        if ($agent_id == $current_user_agent_id || empty($agent_id)) {
            $allowed_agent_id = $agent_id;
        } else {
            // Ha megpróbál más ügyintézőt beállítani, hagyjuk az eredeti értéket
            $allowed_agent_id = $client['agent_id'];
        }
        
        $stmt = $pdo->prepare("
            UPDATE clients SET 
                phone = ?, email = ?, address = ?, notes = ?,
                insulation_area = ?, agent_id = ?,
                contract_signed = ?, work_completed = ?, closed_at = ?, contract_signed_at = ?
            WHERE id = ?
        ");
        $stmt->execute([$phone, $email, $address, $notes, $insulation_area, $allowed_agent_id, $contract_signed, $work_completed, $closed_at, $contract_signed_at, $client_id]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Ügyfél sikeresen frissítve']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
