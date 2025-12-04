<?php
require_once 'config.php';
require_once 'audit_helper.php';
requireLogin();


$county_id = $_GET['county_id'] ?? 0;
$client_id = $_GET['id'] ?? 0;
$is_edit = $client_id > 0;

// Megye lekérdezése
$stmt = $pdo->prepare("SELECT * FROM counties WHERE id = ?");
$stmt->execute([$county_id]);
$county = $stmt->fetch();

if (!$county) {
    die('Megye nem található!');
}

// Ha szerkesztés, ügyfél adatok lekérdezése
$client = null;
if ($is_edit) {
    $stmt = $pdo->prepare("
        SELECT c.*, s.name as settlement_name
        FROM clients c
        LEFT JOIN settlements s ON c.settlement_id = s.id
        WHERE c.id = ?
    ");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch();

    if (!$client) {
        die('Ügyfél nem található!');
    }

    $county_id = $client['county_id'];
}

// Új ügyfél felvitele adminoknak és ügyintézőknek is
// (Az ügyintézők által létrehozott ügyfelek jóváhagyásra várnak)

// Összes megye lekérdezése
$stmt = $pdo->query("SELECT * FROM counties ORDER BY name");
$counties = $stmt->fetchAll();

// Települések lekérdezése a kiválasztott megyéhez
$stmt = $pdo->prepare("SELECT * FROM settlements WHERE county_id = ? ORDER BY name");
$stmt->execute([$county_id]);
$settlements = $stmt->fetchAll();

// Ügyintézők lekérdezése (csak aktív felhasználók)
$stmt = $pdo->query("
    SELECT a.* FROM agents a
    INNER JOIN users u ON a.name = u.name
    WHERE u.approved = 1
    ORDER BY a.name
");
$agents = $stmt->fetchAll();

// Ha az aktuális felhasználó még nincs az agents táblában, hozzáadjuk
$current_user_in_agents = false;
foreach ($agents as $agent) {
    if ($agent['name'] === $_SESSION['name']) {
        $current_user_in_agents = true;
        break;
    }
}

if (!$current_user_in_agents) {
    // Hozzáadjuk az aktuális felhasználót az agents listához (csak megjelenítéshez)
    $agents[] = [
        'id' => 'current_user',
        'name' => $_SESSION['name'],
        'color' => '#808080' // Szürke szín az új felhasználóknak
    ];
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $selected_county_id = $_POST['county_id'] ?? $county_id;
    $settlement_id = !empty($_POST['settlement_id']) ? $_POST['settlement_id'] : null;
    $address = trim($_POST['address'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $insulation_area = !empty($_POST['insulation_area']) ? $_POST['insulation_area'] : null;
    $contract_signed = isset($_POST['contract_signed']) ? 1 : 0;
    $work_completed = isset($_POST['work_completed']) ? 1 : 0;
    $agent_id_raw = $_POST['agent_id'] ?? '';

    // Ha az ügyintéző 'current_user', akkor hozzáadjuk az agents táblához
    if ($agent_id_raw === 'current_user') {
        // Ellenőrizzük, hogy már létezik-e
        $stmt = $pdo->prepare("SELECT id FROM agents WHERE name = ?");
        $stmt->execute([$_SESSION['name']]);
        $existing_agent = $stmt->fetch();

        if ($existing_agent) {
            $agent_id = $existing_agent['id'];
        } else {
            // Hozzáadjuk az új ügyintézőt
            $stmt = $pdo->prepare("INSERT INTO agents (name, color) VALUES (?, ?)");
            $stmt->execute([$_SESSION['name'], '#808080']);
            $agent_id = $pdo->lastInsertId();
        }
    } else {
        $agent_id = !empty($agent_id_raw) ? $agent_id_raw : null;
    }
    $notes = trim($_POST['notes'] ?? '');

    // Ügyintéző szerkesztés - ellenőrizzük a védettséget
    if (!isAdmin() && $is_edit) {
        // Ellenőrizzük, hogy az ügyfélhez már van-e ügyintéző rendelve
        $stmt = $pdo->prepare("SELECT agent_id FROM clients WHERE id = ?");
        $stmt->execute([$client_id]);
        $current_client = $stmt->fetch();

        // Ha már van ügyintéző és nem az aktuális felhasználó, nem szerkeszthető
        if ($current_client['agent_id'] && $current_client['agent_id'] != $agent_id) {
            // Ellenőrizzük, hogy az aktuális felhasználó az ügyintéző-e
            $stmt = $pdo->prepare("SELECT id FROM agents WHERE name = ?");
            $stmt->execute([$_SESSION['name']]);
            $current_agent = $stmt->fetch();

            if ($current_agent && $current_agent['id'] != $current_client['agent_id']) {
                $error = 'Ez az ügyfél már egy másik ügyintézőhöz van rendelve. Nem szerkesztheted!';
            } else {
                // Automatikus lezárás/újranyitás logika (ügyintézőknél is)
                // JAVÍTÁS: contract_signed_at és closed_at kezelése
                $contract_signed_at = null;
                $closed_at = null;
                
                // Lekérjük a meglévő értékeket
                $check_stmt = $pdo->prepare("SELECT contract_signed_at, closed_at FROM clients WHERE id = ?");
                $check_stmt->execute([$client_id]);
                $existing = $check_stmt->fetch();
                
                // contract_signed_at kezelése
                if ($contract_signed) {
                    if ($existing && $existing['contract_signed_at']) {
                        $contract_signed_at = $existing['contract_signed_at'];
                    } else {
                        $contract_signed_at = date('Y-m-d H:i:s');
                    }
                }
                
                // closed_at kezelése
                if ($contract_signed && $work_completed) {
                    if ($existing && $existing['closed_at']) {
                        $closed_at = $existing['closed_at'];
                    } else {
                        $closed_at = date('Y-m-d H:i:s');
                    }
                }

                // Frissítés - JAVÍTÁS: contract_signed_at hozzáadva
                $stmt = $pdo->prepare("UPDATE clients SET agent_id = ?, contract_signed = ?, work_completed = ?, phone = ?, address = ?, insulation_area = ?, notes = ?, contract_signed_at = ?, closed_at = ? WHERE id = ?");
                $stmt->execute([$agent_id, $contract_signed, $work_completed, $phone, $address, $insulation_area, $notes, $contract_signed_at, $closed_at, $client_id]);
                $success = 'Ügyfél sikeresen frissítve!';
                header("refresh:1;url=county.php?id=$county_id");
            }
        } else {
            // Automatikus lezárás/újranyitás logika (ügyintézőknél is)
            // JAVÍTÁS: contract_signed_at és closed_at kezelése
            $contract_signed_at = null;
            $closed_at = null;
            
            // Lekérjük a meglévő értékeket
            $check_stmt = $pdo->prepare("SELECT contract_signed_at, closed_at FROM clients WHERE id = ?");
            $check_stmt->execute([$client_id]);
            $existing = $check_stmt->fetch();
            
            // contract_signed_at kezelése
            if ($contract_signed) {
                if ($existing && $existing['contract_signed_at']) {
                    $contract_signed_at = $existing['contract_signed_at'];
                } else {
                    $contract_signed_at = date('Y-m-d H:i:s');
                }
            }
            
            // closed_at kezelése
            if ($contract_signed && $work_completed) {
                if ($existing && $existing['closed_at']) {
                    $closed_at = $existing['closed_at'];
                } else {
                    $closed_at = date('Y-m-d H:i:s');
                }
            }

            // Nincs még ügyintéző vagy az aktuális felhasználó az ügyintéző
            // JAVÍTÁS: contract_signed_at hozzáadva
            $stmt = $pdo->prepare("UPDATE clients SET agent_id = ?, contract_signed = ?, work_completed = ?, phone = ?, address = ?, insulation_area = ?, notes = ?, contract_signed_at = ?, closed_at = ? WHERE id = ?");
            $stmt->execute([$agent_id, $contract_signed, $work_completed, $phone, $address, $insulation_area, $notes, $contract_signed_at, $closed_at, $client_id]);
            $success = 'Ügyfél sikeresen frissítve!';
            header("refresh:1;url=county.php?id=$county_id");
        }
    } else {
        // Validació: Kötelező mezők ellenőrzése (csak adminoknak és új ügyfélnél)
        if (empty($name)) {
            $error = 'A név megadása kötelező!';
        } elseif (empty($selected_county_id)) {
            $error = 'A megye kiválasztása kötelező!';
        } elseif (empty($settlement_id)) {
            $error = 'A település kiválasztása kötelező!';
        } elseif (empty($phone)) {
            $error = 'A telefonszám megadása kötelező!';
        } else {
            // E-mail duplikáció ellenőrzés (csak ha van e-mail cím)
            if (!empty($email)) {
                // E-mail formátum ellenőrzés
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Az e-mail cím formátuma helytelen!';
                } else {
                    // Duplikáció ellenőrzés
                    if ($is_edit) {
                        $stmt = $pdo->prepare("SELECT id FROM clients WHERE email = ? AND id != ?");
                        $stmt->execute([$email, $client_id]);
                    } else {
                        $stmt = $pdo->prepare("SELECT id FROM clients WHERE email = ?");
                        $stmt->execute([$email]);
                    }

                    if ($stmt->fetch()) {
                        $error = 'Ez az e-mail cím már használatban van egy másik ügyfélnél!';
                    }
                }
            }
        }

        if (!$error) {
            // Admin és Ügyintéző hozzáadás/szerkesztés
            if ($is_edit) {
                // Szerkesztésnél
                if (isAdmin()) {
                    // Régi adatok lekérése audit loghoz
                    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
                    $stmt->execute([$client_id]);
                    $old_data = $stmt->fetch();

                    // Automatikus lezárás/újranyitás logika
                    $closed_at = null;
                    if ($contract_signed && $work_completed) {
                        $check_stmt = $pdo->prepare("SELECT closed_at FROM clients WHERE id = ?");
                        $check_stmt->execute([$client_id]);
                        $existing = $check_stmt->fetch();

                        if ($existing && $existing['closed_at']) {
                            $closed_at = $existing['closed_at'];
                        } else {
                            $closed_at = date('Y-m-d H:i:s');
                        }
                    }

                    // Admin minden mezőt módosíthat
                    $stmt = $pdo->prepare("
                        UPDATE clients SET
                            name = ?, county_id = ?, settlement_id = ?, address = ?,
                            email = ?, phone = ?, insulation_area = ?,
                            contract_signed = ?, work_completed = ?, agent_id = ?, notes = ?,
                            closed_at = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $name, $selected_county_id, $settlement_id, $address,
                        $email, $phone, $insulation_area,
                        $contract_signed, $work_completed, $agent_id, $notes, $closed_at, $client_id
                    ]);

                    // AUDIT LOG - Frissítés
                    $new_data = [
                        'name' => $name,
                        'county_id' => $selected_county_id,
                        'settlement_id' => $settlement_id,
                        'address' => $address,
                        'email' => $email,
                        'phone' => $phone,
                        'insulation_area' => $insulation_area,
                        'contract_signed' => $contract_signed,
                        'work_completed' => $work_completed,
                        'agent_id' => $agent_id,
                        'notes' => $notes,
                        'closed_at' => $closed_at
                    ];
                    logClientUpdate($pdo, $client_id, $old_data, $new_data);

                    $success = 'Ügyfél sikeresen frissítve!';
                }

            } else {
                // Új ügyfél hozzáadása
                // JAVÍTÁS: contract_signed_at és closed_at számítása új ügyféleknél
                $contract_signed_at = null;
                $closed_at = null;
                
                if ($contract_signed) {
                    $contract_signed_at = date('Y-m-d H:i:s');
                }
                if ($contract_signed && $work_completed) {
                    $closed_at = date('Y-m-d H:i:s');
                }
                
                // Új ügyfél hozzáadása
                if (isAdmin()) {
                    // Admin által létrehozott ügyfél azonnal jóváhagyott
                    $stmt = $pdo->prepare("
                        INSERT INTO clients
                        (name, county_id, settlement_id, address, email, phone, insulation_area,
                        contract_signed, work_completed, agent_id, notes, created_by, approved, approval_status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'approved')
                    ");
                    $stmt->execute([
                        $name, $selected_county_id, $settlement_id, $address, $email, $phone, $insulation_area,
                        $contract_signed, $work_completed, $agent_id, $notes, $_SESSION['user_id']
                    ]);
    
                    $new_client_id = $pdo->lastInsertId();
    
                    // AUDIT LOG - Új ügyfél létrehozása
                    logClientCreate($pdo, $new_client_id, [
                        'name' => $name,
                        'county_id' => $selected_county_id,
                        'settlement_id' => $settlement_id,
                        'address' => $address,
                        'email' => $email,
                        'phone' => $phone,
                        'insulation_area' => $insulation_area,
                        'contract_signed' => $contract_signed,
                        'work_completed' => $work_completed,
                        'agent_id' => $agent_id,
                        'notes' => $notes,
                        'created_by' => $_SESSION['user_id'],
                        'approved' => 1,
                        'approval_status' => 'approved'
                    ]);
    
                    $success = 'Ügyfél sikeresen létrehozva!';
                } else {
                    // Ügyintéző által létrehozott ügyfél jóváhagyásra vár
                    $stmt = $pdo->prepare("
                        INSERT INTO clients
                        (name, county_id, settlement_id, address, email, phone, insulation_area,
                        contract_signed, work_completed, agent_id, notes, created_by, approved, approval_status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'pending')
                    ");
                    $stmt->execute([
                        $name, $selected_county_id, $settlement_id, $address, $email, $phone, $insulation_area,
                        $contract_signed, $work_completed, $agent_id, $notes, $_SESSION['user_id']
                    ]);
    
                    $new_client_id = $pdo->lastInsertId();
    
                    // AUDIT LOG - Új ügyfél (pending)
                    logClientCreate($pdo, $new_client_id, [
                        'name' => $name,
                        'county_id' => $selected_county_id,
                        'settlement_id' => $settlement_id,
                        'created_by' => $_SESSION['user_id'],
                        'approved' => 0,
                        'approval_status' => 'pending'
                    ]);
    
                    $success = 'Ügyfél sikeresen létrehozva! Jóváhagyásra vár az adminisztrátor által.';
                }

            }

            // Átirányítás 1 másodperc után
            header("refresh:3;url=county.php?id=$selected_county_id");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Ügyfél szerkesztése' : 'Új ügyfél'; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #e3f2fd 0%, #e8eaf6 100%);
            min-height: 100vh;
        }
        .header {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
    <link rel="stylesheet" href="improved_styles.css?v=1.0">
</head>
<body>
    <div class="header py-3 mb-4">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="mb-0"><?php echo APP_NAME; ?></h3>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted">
                        <i class="bi bi-person-circle"></i> <?php echo escape($_SESSION['name']); ?>
                        <?php if (isAdmin()): ?>
                            <span class="badge bg-primary ms-2">Admin</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="mb-4">
            <a href="county.php?id=<?php echo $county_id; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Vissza
            </a>
        </div>

        <div class="form-card p-4">
            <h4 class="mb-4">
                <?php echo $is_edit ? 'Ügyfél szerkesztése' : 'Új ügyfél hozzáadása - ' . escape($county['name']); ?>
            </h4>

            <?php if (!isAdmin() && $is_edit): ?>
                <div class="alert alert-info">
                    Ügyintézőként csak az ügyintéző, szerződéskötés és kivitelezés mezőket módosíthatod.
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo escape($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo escape($success); ?></div>
            <?php endif; ?>

            <form method="POST" id="clientForm">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Név *</label>
                        <input type="text" class="form-control" id="name" name="name"
                               value="<?php echo escape($client['name'] ?? ''); ?>"
                               <?php echo (!isAdmin() && $is_edit) ? 'readonly' : 'required'; ?>>
                    </div>

                    <?php if (isAdmin() || !$is_edit): ?>
                        <div class="col-md-6">
                            <label for="county_id" class="form-label">Megye *</label>
                            <select class="form-select" id="county_id" name="county_id" required
                                    onchange="loadSettlements(this.value)">
                                <?php foreach ($counties as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"
                                            <?php echo ($c['id'] == $county_id) ? 'selected' : ''; ?>>
                                        <?php echo escape($c['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="settlement_search" class="form-label">Település *</label>
                            <input type="text" class="form-control" id="settlement_search"
                                   placeholder="Írj be egy települést..."
                                   autocomplete="off"
                                   required
                                   value="<?php echo $client ? escape($client['settlement_name'] ?? '') : ''; ?>">
                            <input type="hidden" id="settlement_id" name="settlement_id"
                                   value="<?php echo $client['settlement_id'] ?? ''; ?>"
                                   required>
                            <div id="settlement_dropdown" class="list-group position-absolute" style="z-index: 1000; max-height: 300px; overflow-y: auto; display: none;"></div>
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label">E-mail</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?php echo escape($client['email'] ?? ''); ?>">
                        </div>
                    <?php endif; ?>

                    <!-- Ezeket a mezőket mindenki szerkesztheti -->
                    <div class="col-md-6">
                        <label for="address" class="form-label">Utca/Házszám</label>
                        <input type="text" class="form-control" id="address" name="address"
                               value="<?php echo escape($client['address'] ?? ''); ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="phone" class="form-label">Telefon *</label>
                        <input type="text" class="form-control" id="phone" name="phone"
                               required
                               value="<?php echo escape($client['phone'] ?? ''); ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="insulation_area" class="form-label">Szigetelenő terület (m²)</label>
                        <input type="number" class="form-control" id="insulation_area" name="insulation_area"
                               value="<?php echo escape($client['insulation_area'] ?? ''); ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="agent_id" class="form-label">Ügyintéző</label>
                        <select class="form-select" id="agent_id" name="agent_id">
                            <option value="">Válassz ügyintézőt</option>
                            <?php
                            // Ha ügyintéző, csak saját magát láthatja
                            if (!isAdmin()) {
                                // Csak az aktuális felhasználó megtalálása
                                foreach ($agents as $a) {
                                    if ($a['name'] === $_SESSION['name'] || $a['id'] === 'current_user') {
                                        $selected = ($client && $a['id'] == $client['agent_id']) ? 'selected' : '';
                                        echo '<option value="' . $a['id'] . '" ' . $selected . '>' . escape($a['name']) . '</option>';
                                        break;
                                    }
                                }
                            } else {
                                // Admin látja az összeset
                                foreach ($agents as $a) {
                                    $selected = ($client && $a['id'] == $client['agent_id']) ? 'selected' : '';
                                    echo '<option value="' . $a['id'] . '" ' . $selected . '>' . escape($a['name']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="contract_signed" name="contract_signed"
                                   <?php echo ($client && $client['contract_signed']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="contract_signed">
                                Szerződéskötés?
                            </label>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="work_completed" name="work_completed"
                                   <?php echo ($client && $client['work_completed']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="work_completed">
                                Kivitelezés?
                            </label>
                        </div>
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">Megjegyzés</label>
                        <textarea class="form-control" id="notes" name="notes" rows="4"><?php echo escape($client['notes'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Mentés
                    </button>
                    <a href="county.php?id=<?php echo $county_id; ?>" class="btn btn-outline-secondary">
                        Mégse
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let settlements = [];
        let currentCountyId = <?php echo $county_id; ?>;

        // Települések betöltése a megyéhez (Promise-szal)
        function loadSettlements(countyId, preserveSelection = false) {
            currentCountyId = countyId;
            return fetch('api_settlements.php?county_id=' + countyId)
                .then(response => response.json())
                .then(data => {
                    settlements = data;
                    if (!preserveSelection) {
                        document.getElementById('settlement_search').value = '';
                        document.getElementById('settlement_id').value = '';
                    }
                    return data;
                });
        }

        // Autocomplete funkció
        const searchInput = document.getElementById('settlement_search');
        const dropdown = document.getElementById('settlement_dropdown');
        const hiddenInput = document.getElementById('settlement_id');

        // Települések betöltése az oldal betöltésekor
        <?php if ($is_edit && $client && !empty($client['settlement_name'])): ?>
        // Szerkesztés mód: betöltjük a településeket, majd beállítjuk a meglévőt
        loadSettlements(currentCountyId, true).then(function() {
            const settlementName = '<?php echo addslashes($client['settlement_name']); ?>';
            const settlementId = '<?php echo $client['settlement_id']; ?>';
            document.getElementById('settlement_search').value = settlementName;
            document.getElementById('settlement_id').value = settlementId;
        });
        <?php else: ?>
        // Új ügyfél mód: csak betöltjük a településeket
        loadSettlements(currentCountyId);
        <?php endif; ?>

        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();

            if (searchTerm.length === 0) {
                dropdown.style.display = 'none';
                hiddenInput.value = '';
                return;
            }

            const filtered = settlements.filter(s =>
                s.name.toLowerCase().includes(searchTerm)
            );

            if (filtered.length === 0) {
                dropdown.style.display = 'none';
                return;
            }

            dropdown.innerHTML = '';
            filtered.forEach(settlement => {
                const item = document.createElement('a');
                item.href = '#';
                item.className = 'list-group-item list-group-item-action';
                item.textContent = settlement.name;
                item.onclick = function(e) {
                    e.preventDefault();
                    searchInput.value = settlement.name;
                    hiddenInput.value = settlement.id;
                    dropdown.style.display = 'none';
                };
                dropdown.appendChild(item);
            });

            dropdown.style.display = 'block';
        });

        // Kattintás máshova -> bezárás
        document.addEventListener('click', function(e) {
            if (e.target !== searchInput) {
                dropdown.style.display = 'none';
            }
        });

        // Megye változásakor frissítjük a településeket
        const countySelect = document.getElementById('county_id');
        if (countySelect) {
            countySelect.addEventListener('change', function() {
                loadSettlements(this.value);
            });
        }
    </script>
</body>
</html>
