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
    // Ellenőrizzük hogy az ügyfél létezik és elutasított státuszú
    $stmt = $pdo->prepare("SELECT created_by, approval_status, contract_signed_at, closed_at FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch();

    if (!$client) {
        echo json_encode(['success' => false, 'error' => 'Ügyfél nem található']);
        exit;
    }

    // Csak a saját elutasított ügyfelét küldheti újra
    if ($client['created_by'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'error' => 'Csak a saját ügyfeleidet küldheted újra']);
        exit;
    }

    if ($client['approval_status'] !== 'rejected') {
        echo json_encode(['success' => false, 'error' => 'Csak elutasított ügyfelet küldhetsz újra']);
        exit;
    }

    // JAVÍTÁS: contract_signed_at és closed_at kezelése
    $contract_signed_at = null;
    $closed_at = null;
    
    // contract_signed_at kezelése
    if ($contract_signed) {
        if ($client['contract_signed_at']) {
            $contract_signed_at = $client['contract_signed_at'];
        } else {
            $contract_signed_at = date('Y-m-d H:i:s');
        }
    }
    
    // closed_at kezelése
    if ($contract_signed && $work_completed) {
        if ($client['closed_at']) {
            $closed_at = $client['closed_at'];
        } else {
            $closed_at = date('Y-m-d H:i:s');
        }
    }

    // Ügyfél adatainak frissítése és státusz visszaállítása pending-re
    // JAVÍTÁS: contract_signed_at és closed_at hozzáadva
    $stmt = $pdo->prepare("
        UPDATE clients
        SET name = ?,
            phone = ?,
            email = ?,
            address = ?,
            county_id = ?,
            settlement_id = ?,
            agent_id = ?,
            insulation_area = ?,
            contract_signed = ?,
            work_completed = ?,
            notes = ?,
            contract_signed_at = ?,
            closed_at = ?,
            approval_status = 'pending',
            approved = 0,
            approved_at = NULL,
            approved_by = NULL,
            updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $name,
        $phone,
        $email,
        $address,
        $county_id,
        $settlement_id ?: null,
        $agent_id ?: null,
        $insulation_area ?: null,
        $contract_signed,
        $work_completed,
        $notes,
        $contract_signed_at,
        $closed_at,
        $client_id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Ügyfél sikeresen újraküldve jóváhagyásra!'
    ]);

} catch (PDOException $e) {
    error_log("Resubmit error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Adatbázis hiba történt']);
}
?>
