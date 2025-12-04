<?php
require_once 'config.php';
requireLogin();

// Csak ügyintézők láthatják a saját kéréseiket
if (isAdmin()) {
    header('Location: approvals.php');
    exit;
}

// Aktuális felhasználó által létrehozott ügyfelek lekérdezése
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT c.*, 
           co.name as county_name, 
           s.name as settlement_name,
           a.name as agent_name,
           approver.name as approver_name
    FROM clients c
    LEFT JOIN counties co ON c.county_id = co.id
    LEFT JOIN settlements s ON c.settlement_id = s.id
    LEFT JOIN agents a ON c.agent_id = a.id
    LEFT JOIN users approver ON c.approved_by = approver.id
    WHERE c.created_by = ?
    ORDER BY 
        CASE c.approval_status
            WHEN 'pending' THEN 1
            WHEN 'approved' THEN 2
            WHEN 'rejected' THEN 3
        END,
        c.created_at DESC
");
$stmt->execute([$user_id]);
$my_clients = $stmt->fetchAll();

// Státusz szerinti csoportosítás
$pending = array_filter($my_clients, fn($c) => $c['approval_status'] === 'pending');
$approved = array_filter($my_clients, fn($c) => $c['approval_status'] === 'approved');
$rejected = array_filter($my_clients, fn($c) => $c['approval_status'] === 'rejected');

// Települések lekérdezése (újraküldéshez)
$settlements_stmt = $pdo->query("SELECT * FROM settlements ORDER BY name");
$settlements = $settlements_stmt->fetchAll();

// Ügyintézők lekérdezése (újraküldéshez)
$agents_stmt = $pdo->query("SELECT * FROM agents ORDER BY name");
$agents = $agents_stmt->fetchAll();

// Megyék lekérdezése (újraküldéshez)
$counties_stmt = $pdo->query("SELECT * FROM counties ORDER BY name");
$counties = $counties_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saját Ügyfél Kérések - <?php echo APP_NAME; ?></title>
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
        .requests-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .status-badge-pending {
            background-color: #ffc107;
            color: #000;
        }
        .status-badge-approved {
            background-color: #28a745;
            color: #fff;
        }
        .status-badge-rejected {
            background-color: #dc3545;
            color: #fff;
        }
        .rejection-reason {
            background-color: #fff3cd;
            border-left: 4px solid #dc3545;
            padding: 10px;
            margin-top: 10px;
            border-radius: 4px;
        }
        .nav-tabs .nav-link {
            color: #495057;
        }
        .nav-tabs .nav-link.active {
            font-weight: bold;
        }
        .badge-count {
            font-size: 0.8em;
            margin-left: 5px;
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
                        <span class="badge bg-success">Ügyintéző</span>
                    </span>
                    <a href="index.php" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-house"></i> Főoldal
                    </a>
                    <a href="chat.php" class="btn btn-outline-info btn-sm">
                        <i class="bi bi-chat-dots"></i> Chat
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

        <div class="requests-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">
                    <i class="bi bi-file-earmark-text"></i> Saját Ügyfél Kérések
                </h4>
                <span class="text-muted">
                    Összesen: <strong><?php echo count($my_clients); ?></strong> kérés
                </span>
            </div>
            
            <!-- Fül navigáció -->
            <ul class="nav nav-tabs mb-4" id="statusTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button">
                        <i class="bi bi-clock-history"></i> Függőben
                        <span class="badge status-badge-pending badge-count"><?php echo count($pending); ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved" type="button">
                        <i class="bi bi-check-circle"></i> Elfogadva
                        <span class="badge status-badge-approved badge-count"><?php echo count($approved); ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="rejected-tab" data-bs-toggle="tab" data-bs-target="#rejected" type="button">
                        <i class="bi bi-x-circle"></i> Elutasítva
                        <span class="badge status-badge-rejected badge-count"><?php echo count($rejected); ?></span>
                    </button>
                </li>
            </ul>

            <!-- Fül tartalom -->
            <div class="tab-content" id="statusTabsContent">
                
                <!-- Függőben fül -->
                <div class="tab-pane fade show active" id="pending" role="tabpanel">
                    <?php if (empty($pending)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Jelenleg nincs függőben lévő kérésed.
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
                                        <th>Beküldve</th>
                                        <th>Státusz</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending as $client): ?>
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
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('Y-m-d H:i', strtotime($client['created_at'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge status-badge-pending">
                                                    <i class="bi bi-clock-history"></i> Jóváhagyásra vár
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Elfogadva fül -->
                <div class="tab-pane fade" id="approved" role="tabpanel">
                    <?php if (empty($approved)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Még nincs elfogadott kérésed.
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
                                        <th>Beküldve</th>
                                        <th>Jóváhagyta</th>
                                        <th>Státusz</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($approved as $client): ?>
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
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('Y-m-d H:i', strtotime($client['created_at'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo escape($client['approver_name'] ?? 'Admin'); ?>
                                                    <br>
                                                    <?php echo $client['approved_at'] ? date('Y-m-d H:i', strtotime($client['approved_at'])) : '-'; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge status-badge-approved">
                                                    <i class="bi bi-check-circle"></i> Elfogadva
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Elutasítva fül -->
                <div class="tab-pane fade" id="rejected" role="tabpanel">
                    <?php if (empty($rejected)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Nincs elutasított kérésed.
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
                                        <th>Beküldve</th>
                                        <th>Elutasította</th>
                                        <th>Státusz</th>
                                        <th>Műveletek</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rejected as $client): ?>
                                        <tr>
                                            <td><?php echo $client['id']; ?></td>
                                            <td>
                                                <strong><?php echo escape($client['name']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo escape($client['address'] ?? '-'); ?>
                                                </small>
                                                <?php if ($client['rejection_reason']): ?>
                                                    <div class="rejection-reason mt-2">
                                                        <strong><i class="bi bi-exclamation-triangle"></i> Elutasítás indoka:</strong>
                                                        <p class="mb-0 mt-1"><?php echo nl2br(escape($client['rejection_reason'])); ?></p>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo escape($client['county_name']); ?></td>
                                            <td><?php echo escape($client['settlement_name']); ?></td>
                                            <td><?php echo escape($client['phone']); ?></td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('Y-m-d H:i', strtotime($client['created_at'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo escape($client['approver_name'] ?? 'Admin'); ?>
                                                    <br>
                                                    <?php echo $client['approved_at'] ? date('Y-m-d H:i', strtotime($client['approved_at'])) : '-'; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge status-badge-rejected">
                                                    <i class="bi bi-x-circle"></i> Elutasítva
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-warning" 
                                                        onclick="resubmitClient(<?php echo htmlspecialchars(json_encode($client), ENT_QUOTES, 'UTF-8'); ?>)"
                                                        title="Szerkeszt és Újraküld">
                                                    <i class="bi bi-arrow-clockwise"></i> Újraküld
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
        
        <div class="mt-4">
            <div class="alert alert-info">
                <strong><i class="bi bi-info-circle"></i> Tudnivalók:</strong>
                <ul class="mb-0 mt-2">
                    <li><strong>Függőben:</strong> Az általad létrehozott ügyfelek, amelyek még adminisztrátori jóváhagyásra várnak</li>
                    <li><strong>Elfogadva:</strong> A jóváhagyott ügyfelek már megjelennek a megyei listákban és szerkeszthetők</li>
                    <li><strong>Elutasítva:</strong> Az elutasított ügyfelek indoklással - tanulj belőlük a jövőbeli beküldésekhez</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Újraküldés Modal -->
    <div class="modal fade" id="resubmitModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="bi bi-arrow-clockwise"></i> Ügyfél Szerkesztése és Újraküldése
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <strong><i class="bi bi-info-circle"></i> Fontos:</strong> Javítsd ki az elutasítás okát, majd küldd újra jóváhagyásra!
                    </div>
                    
                    <form id="resubmitForm">
                        <input type="hidden" id="resubmit_client_id">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><strong>Név *</strong></label>
                                <input type="text" class="form-control" id="resubmit_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><strong>Telefon</strong></label>
                                <input type="text" class="form-control" id="resubmit_phone">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><strong>E-mail</strong></label>
                                <input type="email" class="form-control" id="resubmit_email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><strong>Cím</strong></label>
                                <input type="text" class="form-control" id="resubmit_address">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label"><strong>Megye *</strong></label>
                                <select class="form-select" id="resubmit_county_id" required>
                                    <?php foreach ($counties as $county): ?>
                                        <option value="<?php echo $county['id']; ?>">
                                            <?php echo escape($county['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label"><strong>Település</strong></label>
                                <select class="form-select" id="resubmit_settlement_id">
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
                                <select class="form-select" id="resubmit_agent_id">
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
                                <input type="number" class="form-control" id="resubmit_insulation_area">
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="form-check mt-4">
                                    <input type="checkbox" class="form-check-input" id="resubmit_contract_signed" 
                                           style="width: 20px; height: 20px; border: 3px solid #0d6efd;">
                                    <label class="form-check-label ms-2" for="resubmit_contract_signed">
                                        Szerződés aláírva
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="form-check mt-4">
                                    <input type="checkbox" class="form-check-input" id="resubmit_work_completed" 
                                           style="width: 20px; height: 20px; border: 3px solid #0d6efd;">
                                    <label class="form-check-label ms-2" for="resubmit_work_completed">
                                        Munka befejezve
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><strong>Megjegyzések</strong></label>
                            <textarea class="form-control" id="resubmit_notes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x"></i> Mégse
                    </button>
                    <button type="button" class="btn btn-warning" onclick="saveResubmit()">
                        <i class="bi bi-arrow-clockwise"></i> Újraküld Jóváhagyásra
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let resubmitModal;
        
        document.addEventListener('DOMContentLoaded', function() {
            resubmitModal = new bootstrap.Modal(document.getElementById('resubmitModal'));
        });
        
        function resubmitClient(client) {
            document.getElementById('resubmit_client_id').value = client.id;
            document.getElementById('resubmit_name').value = client.name || '';
            document.getElementById('resubmit_phone').value = client.phone || '';
            document.getElementById('resubmit_email').value = client.email || '';
            document.getElementById('resubmit_address').value = client.address || '';
            document.getElementById('resubmit_county_id').value = client.county_id || '';
            document.getElementById('resubmit_settlement_id').value = client.settlement_id || '';
            document.getElementById('resubmit_agent_id').value = client.agent_id || '';
            document.getElementById('resubmit_insulation_area').value = client.insulation_area || '';
            document.getElementById('resubmit_contract_signed').checked = client.contract_signed == 1;
            document.getElementById('resubmit_work_completed').checked = client.work_completed == 1;
            document.getElementById('resubmit_notes').value = client.notes || '';
            
            resubmitModal.show();
        }
        
        function saveResubmit() {
            const clientId = document.getElementById('resubmit_client_id').value;
            const formData = new FormData();
            
            formData.append('id', clientId);
            formData.append('name', document.getElementById('resubmit_name').value);
            formData.append('phone', document.getElementById('resubmit_phone').value);
            formData.append('email', document.getElementById('resubmit_email').value);
            formData.append('address', document.getElementById('resubmit_address').value);
            formData.append('county_id', document.getElementById('resubmit_county_id').value);
            formData.append('settlement_id', document.getElementById('resubmit_settlement_id').value);
            formData.append('agent_id', document.getElementById('resubmit_agent_id').value);
            formData.append('insulation_area', document.getElementById('resubmit_insulation_area').value);
            formData.append('contract_signed', document.getElementById('resubmit_contract_signed').checked ? 1 : 0);
            formData.append('work_completed', document.getElementById('resubmit_work_completed').checked ? 1 : 0);
            formData.append('notes', document.getElementById('resubmit_notes').value);
            
            fetch('resubmit_client.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resubmitModal.hide();
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Hiba: ' + data.error);
                }
            })
            .catch(error => {
                alert('Hiba történt az újraküldés során!');
                console.error(error);
            });
        }
    </script>
</body>
</html>
