<?php
require_once 'config.php';
require_once 'audit_helper.php';
requireLogin();
requireAdmin();

$success = '';
$error = '';

// Ügyfél jóváhagyása
if (isset($_POST['approve_client'])) {
    $client_id = (int)$_POST['client_id'];

    // Ügyfél adatok lekérdezése értesítéshez ÉS audit loghoz
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch();

    // Jóváhagyás
    $stmt = $pdo->prepare("UPDATE clients SET approved = 1, approval_status = 'approved', approved_at = NOW(), approved_by = ? WHERE id = ?");
    $stmt->execute([$_SESSION['user_id'], $client_id]);

    // AUDIT LOG - Jóváhagyás
    logClientApprove($pdo, $client_id, [
        'name' => $client['name'],
        'county_id' => $client['county_id'],
        'settlement_id' => $client['settlement_id'],
        'created_by' => $client['created_by'],
        'approved_by' => $_SESSION['user_id']
    ]);

    // Értesítés létrehozása az ügyintézőnek
    if ($client && $client['created_by']) {
        $stmt = $pdo->prepare("INSERT INTO approval_notifications (client_id, user_id, client_name, approval_status) VALUES (?, ?, ?, 'approved')");
        $stmt->execute([$client_id, $client['created_by'], $client['name']]);
    }

    $success = 'Ügyfél sikeresen jóváhagyva!';
}

// Ügyfél elutasítása indoklással
if (isset($_POST['reject_client'])) {
    $client_id = (int)$_POST['client_id'];
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');

    if (empty($rejection_reason)) {
        $error = 'Az elutasítás indoklása kötelező!';
    } else {
        // Ügyfél adatok lekérdezése értesítéshez ÉS audit loghoz
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
        $stmt->execute([$client_id]);
        $client = $stmt->fetch();

        // Elutasítás
        $stmt = $pdo->prepare("UPDATE clients SET approved = 0, approval_status = 'rejected', rejection_reason = ?, approved_at = NOW(), approved_by = ? WHERE id = ?");
        $stmt->execute([$rejection_reason, $_SESSION['user_id'], $client_id]);

        // AUDIT LOG - Elutasítás
        logClientReject($pdo, $client_id, [
            'name' => $client['name'],
            'county_id' => $client['county_id'],
            'settlement_id' => $client['settlement_id'],
            'created_by' => $client['created_by']
        ], $rejection_reason);

        // Értesítés létrehozása az ügyintézőnek
        if ($client && $client['created_by']) {
            $stmt = $pdo->prepare("INSERT INTO approval_notifications (client_id, user_id, client_name, approval_status, rejection_reason) VALUES (?, ?, ?, 'rejected', ?)");
            $stmt->execute([$client_id, $client['created_by'], $client['name'], $rejection_reason]);
        }

        $success = 'Ügyfél elutasítva!';
    }
}

// A többi rész változatlan...


// Jóváhagyásra váró ügyfelek lekérdezése
$stmt = $pdo->query("
    SELECT c.*, 
           co.name as county_name, 
           s.name as settlement_name,
           a.name as agent_name,
           u.name as creator_name
    FROM clients c
    LEFT JOIN counties co ON c.county_id = co.id
    LEFT JOIN settlements s ON c.settlement_id = s.id
    LEFT JOIN agents a ON c.agent_id = a.id
    LEFT JOIN users u ON c.created_by = u.id
    WHERE c.approval_status = 'pending'
    ORDER BY c.created_at DESC
");
$pending_clients = $stmt->fetchAll();

// Települések lekérdezése (szerkesztéshez)
$settlements_stmt = $pdo->query("SELECT * FROM settlements ORDER BY name");
$settlements = $settlements_stmt->fetchAll();

// Ügyintézők lekérdezése (szerkesztéshez)
$agents_stmt = $pdo->query("SELECT * FROM agents ORDER BY name");
$agents = $agents_stmt->fetchAll();

// Megyék lekérdezése (szerkesztéshez)
$counties_stmt = $pdo->query("SELECT * FROM counties ORDER BY name");
$counties = $counties_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jóváhagyások - <?php echo APP_NAME; ?></title>
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
        .approval-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .pending-badge {
            background-color: #ffc107;
            color: #000;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .action-buttons {
            white-space: nowrap;
        }
        .action-buttons .btn {
            margin: 2px;
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
                        <span class="badge bg-primary">Admin</span>
                    </span>
                    <a href="index.php" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-house"></i> Főoldal
                    </a>
                    <a href="admin.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-people"></i> Felhasználók
                    </a>
                    <a href="logout.php" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-box-arrow-right"></i> Kijelentkezés
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="mb-4">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Vissza a főoldalra
            </a>
        </div>

        <div class="approval-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">
                    <i class="bi bi-clock-history"></i> Jóváhagyásra Váró Ügyfelek
                </h4>
                <span class="badge bg-warning text-dark fs-6">
                    <?php echo count($pending_clients); ?> várakozik
                </span>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo escape($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo escape($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (empty($pending_clients)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Jelenleg nincsenek jóváhagyásra váró ügyfelek.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Név</th>
                                <th>Megye</th>
                                <th>Település</th>
                                <th>Telefon</th>
                                <th>E-mail</th>
                                <th>Ügyintéző</th>
                                <th>Létrehozta</th>
                                <th>Dátum</th>
                                <th style="min-width: 200px;">Műveletek</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_clients as $client): ?>
                                <tr>
                                    <td><?php echo $client['id']; ?></td>
                                    <td>
                                        <strong><?php echo escape($client['name']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo escape($client['address'] ?? '-'); ?>
                                        </small>
                                    </td>
                                    <td><?php echo escape($client['county_name']); ?></td>
                                    <td><?php echo escape($client['settlement_name']); ?></td>
                                    <td><?php echo escape($client['phone']); ?></td>
                                    <td><?php echo escape($client['email']); ?></td>
                                    <td>
                                        <?php if ($client['agent_name']): ?>
                                            <span class="badge bg-secondary"><?php echo escape($client['agent_name']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo escape($client['creator_name']); ?></td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('Y-m-d H:i', strtotime($client['created_at'])); ?>
                                        </small>
                                    </td>
                                    <td class="action-buttons">
                                        <button class="btn btn-sm btn-info" 
                                                onclick="editClient(<?php echo htmlspecialchars(json_encode($client), ENT_QUOTES, 'UTF-8'); ?>)"
                                                title="Szerkesztés">
                                            <i class="bi bi-pencil"></i> Szerkeszt
                                        </button>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                                            <button type="submit" name="approve_client" class="btn btn-sm btn-success" 
                                                    title="Jóváhagyás">
                                                <i class="bi bi-check-circle"></i> Jóváhagy
                                            </button>
                                        </form>
                                        <button class="btn btn-sm btn-danger" 
                                                onclick="showRejectModal(<?php echo $client['id']; ?>, '<?php echo escape($client['name']); ?>')"
                                                title="Elutasítás">
                                            <i class="bi bi-x-circle"></i> Elutasít
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="mt-4">
            <div class="alert alert-info">
                <strong><i class="bi bi-info-circle"></i> Tudnivalók:</strong>
                <ul class="mb-0 mt-2">
                    <li>Az ügyintézők által létrehozott ügyfelek automatikusan jóváhagyásra várnak</li>
                    <li>Szerkesztheted az ügyfél adatait jóváhagyás előtt</li>
                    <li>A jóváhagyott ügyfelek megjelennek a megyei listákban</li>
                    <li>Az elutasított ügyfeleket kötelező megindokolni - az ügyintéző látja az indoklást</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Elutasítás Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-x-circle"></i> Ügyfél Elutasítása
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="client_id" id="reject_client_id">
                        <p>Biztosan elutasítod ezt az ügyfelet: <strong id="reject_client_name"></strong>?</p>
                        <div class="mb-3">
                            <label for="rejection_reason" class="form-label">
                                <strong>Elutasítás indoklása *</strong>
                            </label>
                            <textarea class="form-control" id="rejection_reason" name="rejection_reason" 
                                      rows="4" required 
                                      placeholder="Pl: Hibás telefonszám, nem valós cím, duplikált ügyfél, stb."></textarea>
                            <small class="text-muted">Az ügyintéző látni fogja ezt az indoklást.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x"></i> Mégse
                        </button>
                        <button type="submit" name="reject_client" class="btn btn-danger">
                            <i class="bi bi-x-circle"></i> Elutasít
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Szerkesztés Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil"></i> Ügyfél Szerkesztése
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editForm">
                        <input type="hidden" id="edit_client_id">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><strong>Név *</strong></label>
                                <input type="text" class="form-control" id="edit_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><strong>Telefon</strong></label>
                                <input type="text" class="form-control" id="edit_phone">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><strong>E-mail</strong></label>
                                <input type="email" class="form-control" id="edit_email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><strong>Cím</strong></label>
                                <input type="text" class="form-control" id="edit_address">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label"><strong>Megye *</strong></label>
                                <select class="form-select" id="edit_county_id" required>
                                    <?php foreach ($counties as $county): ?>
                                        <option value="<?php echo $county['id']; ?>">
                                            <?php echo escape($county['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label"><strong>Település</strong></label>
                                <select class="form-select" id="edit_settlement_id">
                                    <option value="">- Válassz -</option>
                                    <?php foreach ($settlements as $settlement): ?>
                                        <option value="<?php echo $settlement['id']; ?>">
                                            <?php echo escape($settlement['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label"><strong>Ügyintéző</strong></label>
                                <select class="form-select" id="edit_agent_id">
                                    <option value="">- Nincs -</option>
                                    <?php foreach ($agents as $agent): ?>
                                        <option value="<?php echo $agent['id']; ?>">
                                            <?php echo escape($agent['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label"><strong>Szigetelendő terület (m²)</strong></label>
                                <input type="number" class="form-control" id="edit_insulation_area">
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="form-check mt-4">
                                    <input type="checkbox" class="form-check-input" id="edit_contract_signed" 
                                           style="width: 20px; height: 20px; border: 3px solid #0d6efd;">
                                    <label class="form-check-label ms-2" for="edit_contract_signed">
                                        Szerződés aláírva
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="form-check mt-4">
                                    <input type="checkbox" class="form-check-input" id="edit_work_completed" 
                                           style="width: 20px; height: 20px; border: 3px solid #0d6efd;">
                                    <label class="form-check-label ms-2" for="edit_work_completed">
                                        Munka befejezve
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><strong>Megjegyzések</strong></label>
                            <textarea class="form-control" id="edit_notes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x"></i> Mégse
                    </button>
                    <button type="button" class="btn btn-primary" onclick="saveEdit()">
                        <i class="bi bi-save"></i> Mentés
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let rejectModal;
        let editModal;
        
        document.addEventListener('DOMContentLoaded', function() {
            rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));
            editModal = new bootstrap.Modal(document.getElementById('editModal'));
        });
        
        function showRejectModal(clientId, clientName) {
            document.getElementById('reject_client_id').value = clientId;
            document.getElementById('reject_client_name').textContent = clientName;
            document.getElementById('rejection_reason').value = '';
            rejectModal.show();
        }
        
        function editClient(client) {
            document.getElementById('edit_client_id').value = client.id;
            document.getElementById('edit_name').value = client.name || '';
            document.getElementById('edit_phone').value = client.phone || '';
            document.getElementById('edit_email').value = client.email || '';
            document.getElementById('edit_address').value = client.address || '';
            document.getElementById('edit_county_id').value = client.county_id || '';
            document.getElementById('edit_settlement_id').value = client.settlement_id || '';
            document.getElementById('edit_agent_id').value = client.agent_id || '';
            document.getElementById('edit_insulation_area').value = client.insulation_area || '';
            document.getElementById('edit_contract_signed').checked = client.contract_signed == 1;
            document.getElementById('edit_work_completed').checked = client.work_completed == 1;
            document.getElementById('edit_notes').value = client.notes || '';
            
            editModal.show();
        }
        
        function saveEdit() {
            const clientId = document.getElementById('edit_client_id').value;
            const formData = new FormData();
            
            formData.append('id', clientId);
            formData.append('name', document.getElementById('edit_name').value);
            formData.append('phone', document.getElementById('edit_phone').value);
            formData.append('email', document.getElementById('edit_email').value);
            formData.append('address', document.getElementById('edit_address').value);
            formData.append('county_id', document.getElementById('edit_county_id').value);
            formData.append('settlement_id', document.getElementById('edit_settlement_id').value);
            formData.append('agent_id', document.getElementById('edit_agent_id').value);
            formData.append('insulation_area', document.getElementById('edit_insulation_area').value);
            formData.append('contract_signed', document.getElementById('edit_contract_signed').checked ? 1 : 0);
            formData.append('work_completed', document.getElementById('edit_work_completed').checked ? 1 : 0);
            formData.append('notes', document.getElementById('edit_notes').value);
            
            fetch('save_client.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    editModal.hide();
                    location.reload();
                } else {
                    alert('Hiba: ' + data.error);
                }
            })
            .catch(error => {
                alert('Hiba történt a mentés során!');
                console.error(error);
            });
        }
    </script>
</body>
</html>
