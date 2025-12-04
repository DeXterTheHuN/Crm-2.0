<?php
require_once 'config.php';
require_once 'audit_helper.php';
require_once 'cache/CacheHelper.php';
requireLogin();

$county_id = $_GET['id'] ?? 0;

// Megyék lekérdezése cache-ből
$counties = CacheHelper::get('counties');
if (!$counties) {
    $stmt = $pdo->query("SELECT * FROM counties ORDER BY name");
    $counties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    CacheHelper::set('counties', $counties);
}

// Települések lekérdezése cache-ből (megye alapján)
$cacheKey = 'settlements_' . $countyId;
$settlements = CacheHelper::get($cacheKey);
if (!$settlements) {
    $stmt = $pdo->prepare("SELECT * FROM settlements WHERE county_id = ? ORDER BY name");
    $stmt->execute([$countyId]);
    $settlements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    CacheHelper::set($cacheKey, $settlements);
}

if (!$county) {
    die('Megye nem található!');
}

// Pagination beállítások
$per_page = 20; // Ügyfelek oldalanként
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// Keresési, szűrési és rendezési paraméterek
$search = $_GET['search'] ?? '';
$filter_agent = $_GET['filter_agent'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'created_at';

// Alap query feltételek
$base_where = "c.county_id = ? AND c.approved = 1 AND c.closed_at IS NULL";
$params = [$county_id];

// Keresési feltétel
$search_condition = "";
if (!empty($search)) {
    $search_condition = " AND (c.name LIKE ? OR s.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Ügyintéző szűrés
$agent_condition = "";
if (!empty($filter_agent)) {
    if ($filter_agent === 'none') {
        $agent_condition = " AND c.agent_id IS NULL";
    } else {
        $agent_condition = " AND c.agent_id = ?";
        $params[] = $filter_agent;
    }
}

// Összes rekord számának lekérdezése (pagination-höz)
$count_query = "
    SELECT COUNT(*) as total
    FROM clients c
    LEFT JOIN settlements s ON c.settlement_id = s.id
    WHERE $base_where $search_condition $agent_condition
";
$count_params = $params; // Ugyanazok a paraméterek
$stmt = $pdo->prepare($count_query);
$stmt->execute($count_params);
$total_records = $stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// Rendezés
$order_by = match($sort_by) {
    'settlement_asc' => " ORDER BY s.name ASC, c.created_at DESC",
    'settlement_desc' => " ORDER BY s.name DESC, c.created_at DESC",
    default => " ORDER BY c.created_at DESC"
};

// Ügyfelek lekérdezése LIMIT-tel
$query = "
    SELECT
        c.*,
        s.name as settlement_name,
        a.name as agent_name,
        a.color as agent_color
    FROM clients c
    LEFT JOIN settlements s ON c.settlement_id = s.id
    LEFT JOIN agents a ON c.agent_id = a.id
    WHERE $base_where $search_condition $agent_condition
    $order_by
    LIMIT $per_page OFFSET $offset
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$clients = $stmt->fetchAll();

// Ügyintézők lekérdezése a szűrőhöz
$stmt = $pdo->query("
    SELECT a.*
    FROM agents a
    INNER JOIN users u ON a.name = u.name
    WHERE u.approved = 1
    ORDER BY a.name
");
$agents = $stmt->fetchAll();

// Aktuális felhasználó agent_id-jének lekérdezése
$current_user_agent_id = null;
if (!isAdmin()) {
    $stmt = $pdo->prepare("SELECT id FROM agents WHERE name = ?");
    $stmt->execute([$_SESSION['name']]);
    $current_agent = $stmt->fetch();
    if ($current_agent) {
        $current_user_agent_id = $current_agent['id'];
    }
}

// Törlés kezelése - AUDIT LOG-gal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && isAdmin()) {
    $delete_id = $_POST['delete_id'];
    
    // Előbb lekérjük az adatokat az audit log-hoz
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$delete_id]);
    $deleted_client = $stmt->fetch();
    
    $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
    $stmt->execute([$delete_id]);
    
    // Audit log
    logClientDelete($pdo, $delete_id, $deleted_client);
    
    redirect("county.php?id=$county_id&page=$page");
}

// Tömeges törlés kezelése - AUDIT LOG-gal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete']) && isAdmin()) {
    $client_ids = $_POST['client_ids'] ?? '';
    if (!empty($client_ids)) {
        $ids = explode(',', $client_ids);
        
        // Minden törölt ügyfélhez audit log
        foreach ($ids as $id) {
            $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
            $stmt->execute([$id]);
            $deleted_client = $stmt->fetch();
            if ($deleted_client) {
                logClientDelete($pdo, $id, $deleted_client);
            }
        }
        
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM clients WHERE id IN ($placeholders)");
        $stmt->execute($ids);
    }
    redirect("county.php?id=$county_id");
}

// URL helper a pagination linkekhez
function buildPaginationUrl($page, $county_id, $search, $filter_agent, $sort_by) {
    $params = [
        'id' => $county_id,
        'page' => $page
    ];
    if (!empty($search)) $params['search'] = $search;
    if (!empty($filter_agent)) $params['filter_agent'] = $filter_agent;
    if ($sort_by !== 'created_at') $params['sort_by'] = $sort_by;
    return 'county.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape($county['name']); ?> - <?php echo APP_NAME; ?></title>
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
        .table-responsive {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .agent-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        /* Nagyobb, láthatóbb checkbox-ok */
        .client-checkbox, #select-all {
            width: 20px;
            height: 20px;
            cursor: pointer;
            border: 2px solid #0d6efd;
        }
        
        .client-checkbox:checked, #select-all:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        
        /* Sor kijelölés stílus */
        .client-row {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .client-row:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transform: translateY(-1px);
        }
        
        .client-row.selected {
            background-color: rgba(13, 110, 253, 0.1) !important;
            border-left: 4px solid #0d6efd;
        }
        
        /* Zárolt sor stílus */
        .client-row.locked-row {
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        .client-row.locked-row:hover {
            box-shadow: none;
            transform: none;
        }
        
        /* Checkbox cell stílus */
        .checkbox-cell {
            width: 50px;
            text-align: center;
            vertical-align: middle;
        }
        
        /* Tömeges szerkesztés modal */
        #bulkEditModal .modal-dialog {
            max-width: 800px;
        }
        
        #bulkEditModal .modal-body {
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .bulk-edit-navigation {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .bulk-edit-navigation .client-counter {
            font-size: 1.1rem;
            font-weight: 600;
            color: #0d6efd;
        }
        
        .bulk-edit-navigation .nav-buttons {
            display: flex;
            gap: 10px;
        }
        
        #bulkEditForm .form-label {
            font-weight: 600;
            color: #495057;
        }
        
        #bulkEditForm .form-control,
        #bulkEditForm .form-select {
            border: 2px solid #dee2e6;
        }
        
        #bulkEditForm .form-control:focus,
        #bulkEditForm .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        
        /* Mobilbarát stílusok */
        @media (max-width: 768px) {
            body {
                font-size: 14px;
            }
            
            .header {
                position: sticky;
                top: 0;
                z-index: 1000;
                padding: 0.75rem 0 !important;
            }
            
            .header h3 {
                font-size: 1.1rem;
            }
            
            .header .d-flex {
                gap: 0.5rem !important;
            }
            
            .header .btn-sm {
                min-height: 44px;
                font-size: 0.85rem;
                padding: 0.4rem 0.6rem;
            }
            
            .btn {
                min-height: 44px;
                min-width: 44px;
                font-size: 0.9rem;
            }
            
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .table {
                font-size: 0.85rem;
                min-width: 100%;
            }
            
            .table th, .table td {
                padding: 0.6rem 0.4rem;
                white-space: nowrap;
            }
            
            .client-checkbox, #select-all {
                width: 22px;
                height: 22px;
            }
            
            .form-control, .form-select {
                font-size: 16px;
                min-height: 44px;
            }
            
            .modal-dialog {
                margin: 0.5rem;
                max-width: calc(100% - 1rem);
            }
            
            .bulk-actions {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .bulk-actions .btn {
                width: 100%;
            }
        }
        
        @media (max-width: 576px) {
            .container {
                padding-left: 8px;
                padding-right: 8px;
                max-width: 100%;
            }
            
            .header .container {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            .header h3 {
                font-size: 1rem;
            }
            
            .table {
                font-size: 0.8rem;
            }
            
            .table th, .table td {
                padding: 0.5rem 0.3rem;
            }
            
            .btn-sm span {
                font-size: 0.75rem;
            }
            
            .modal-body {
                padding: 1rem;
            }
        }
        
        /* Fektetett mód (landscape) */
        @media (max-width: 896px) and (orientation: landscape) {
            .header {
                padding: 0.5rem 0 !important;
            }
            
            .header h3 {
                font-size: 1rem;
            }
            
            .header .btn-sm {
                padding: 0.3rem 0.5rem;
                min-height: 38px;
                font-size: 0.8rem;
            }
            
            .container {
                max-width: 100%;
                padding-left: 10px;
                padding-right: 10px;
            }
            
            .table {
                font-size: 0.8rem;
            }
            
            .table th, .table td {
                padding: 0.4rem 0.3rem;
            }
            
            .modal-dialog {
                max-width: 90%;
            }
            
            .modal-body {
                max-height: 60vh;
                overflow-y: auto;
            }
        }
    </style>
    <link rel="stylesheet" href="improved_styles.css?v=1.0">
</head>
<body>
    <div class="header py-3 mb-4">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="mb-0"><?php echo APP_NAME; ?></h3>
                <div class="d-flex align-items-center gap-2 gap-md-3 flex-wrap justify-content-end">
                    <a href="chat.php" class="btn btn-outline-primary btn-sm position-relative" title="Chat" id="chatLink">
                        <i class="bi bi-chat-dots-fill"></i> <span class="d-none d-md-inline">Chat</span>
                        <span class="badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle" id="chatBadge" style="display: none; font-size: 0.7rem;">0</span>
                    </a>
                    <a href="patchnotes.php" class="btn btn-outline-success btn-sm" title="Változásnapló" id="patchnotesLink">
                        <i class="bi bi-journal-text"></i> <span class="d-none d-md-inline">Változások</span>
                    </a>
                    <a href="profile.php" class="text-decoration-none text-dark">
                        <i class="bi bi-person-circle"></i> <?php echo escape($_SESSION['name']); ?>
                        <?php if (isAdmin()): ?>
                            <span class="badge bg-primary ms-2">Admin</span>
                        <?php endif; ?>
                    </a>
                    <?php if (isAdmin()): ?>
                        <a href="statistics.php" class="btn btn-outline-success btn-sm" title="Statisztikák">
                            <i class="bi bi-graph-up"></i> <span class="d-none d-md-inline">Statisztikák</span>
                        </a>
                        <a href="approvals.php" class="btn btn-outline-warning btn-sm position-relative" title="Jóváhagyások" id="approvalsLink">
                            <i class="bi bi-clock-history"></i> <span class="d-none d-md-inline">Jóváhagyások</span>
                            <span class="badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle" id="approvalsBadge" style="display: none; font-size: 0.7rem;">0</span>
                        </a>
                        <a href="admin.php" class="btn btn-outline-primary btn-sm" title="Felhasználók">
                            <i class="bi bi-people-fill"></i> <span class="d-none d-md-inline">Felhasználók</span>
                        </a>
                    <?php else: ?>
                        <a href="my_requests.php" class="btn btn-outline-info btn-sm position-relative" title="Saját Kérések" id="myRequestsLink">
                            <i class="bi bi-file-earmark-text"></i> <span class="d-none d-md-inline">Saját Kérések</span>
                            <span class="badge bg-info rounded-pill position-absolute top-0 start-100 translate-middle" id="myRequestsBadge" style="display: none; font-size: 0.7rem;">0</span>
                        </a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn btn-outline-danger btn-sm" title="Kijelentkezés">
                        <i class="bi bi-box-arrow-right"></i> <span class="d-none d-md-inline">Kijelentkezés</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="mb-4 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Vissza a megyékhez
                </a>
                <h2 class="mb-0"><?php echo escape($county['name']); ?></h2>
            </div>
            <a href="client_form.php?county_id=<?php echo $county_id; ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Új ügyfél
            </a>
        </div>
        
        <!-- Keresési és Szűrési Panel -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="county.php" class="row g-3">
                    <input type="hidden" name="id" value="<?php echo $county_id; ?>">
                    
                    <div class="col-md-6">
                        <label for="search" class="form-label">
                            <i class="bi bi-search"></i> Keresés
                        </label>
                        <input type="text" class="form-control" id="search" name="search" 
                               placeholder="Név, település, telefon, e-mail..."
                               value="<?php echo escape($search); ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="filter_agent" class="form-label">
                            <i class="bi bi-funnel"></i> Szűrés ügyintéző szerint
                        </label>
                        <select class="form-select" id="filter_agent" name="filter_agent">
                            <option value="">Összes ügyintéző</option>
                            <option value="none" <?php echo $filter_agent === 'none' ? 'selected' : ''; ?>>
                                Ügyintéző nélkül
                            </option>
                            <?php foreach ($agents as $a): ?>
                                <option value="<?php echo $a['id']; ?>" 
                                        <?php echo $filter_agent == $a['id'] ? 'selected' : ''; ?>>
                                    <?php echo escape($a['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="sort_by" class="form-label">
                            <i class="bi bi-sort-alpha-down"></i> Rendezés
                        </label>
                        <select class="form-select" id="sort_by" name="sort_by">
                            <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>
                                Létrehozás dátuma
                            </option>
                            <option value="settlement_asc" <?php echo $sort_by === 'settlement_asc' ? 'selected' : ''; ?>>
                                Település (A-Z)
                            </option>
                            <option value="settlement_desc" <?php echo $sort_by === 'settlement_desc' ? 'selected' : ''; ?>>
                                Település (Z-A)
                            </option>
                        </select>
                    </div>
                    
                    <div class="col-md-12 col-lg-auto d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Keresés
                        </button>
                        <a href="county.php?id=<?php echo $county_id; ?>" class="btn btn-outline-secondary" title="Törlés">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    </div>
                </form>
                
                <?php if (!empty($search) || !empty($filter_agent)): ?>
                    <div class="mt-3">
                        <span class="badge bg-info">
                            <?php echo count($clients); ?> találat
                        </span>
                        <?php if (!empty($search)): ?>
                            <span class="badge bg-secondary">
                                Keresés: "<?php echo escape($search); ?>"
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($filter_agent)): ?>
                            <span class="badge bg-secondary">
                                <?php 
                                if ($filter_agent === 'none') {
                                    echo 'Ügyintéző nélkül';
                                } else {
                                    $agent_name = array_filter($agents, fn($a) => $a['id'] == $filter_agent);
                                    echo 'Ügyintéző: ' . escape(reset($agent_name)['name'] ?? '');
                                }
                                ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($clients)): ?>
            <div class="alert alert-info">
                Még nincsenek ügyfelek ebben a megyében.
                <?php if (isAdmin()): ?>
                    Kattints az "Új ügyfél" gombra a hozzáadáshoz.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Tömeges műveletek panel -->
            <div id="bulk-actions-panel" class="alert alert-primary d-none mb-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <strong><span id="selected-count">0</span> ügyfél kijelölve</strong>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" id="bulk-edit-btn" class="btn btn-primary">
                            <i class="bi bi-pencil"></i> Szerkesztés
                        </button>
                        <?php if (isAdmin()): ?>
                            <button type="button" id="bulk-delete-btn" class="btn btn-danger">
                                <i class="bi bi-trash"></i> Törlés
                            </button>
                        <?php endif; ?>
                        <button type="button" id="deselect-all-btn" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Kijelölés törlése
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">
                                <input type="checkbox" id="select-all" class="form-check-input" title="Mindet kijelöl">
                            </th>
                            <th>Név</th>
                            <th>Település</th>
                            <th>Cím</th>
                            <th>Telefon</th>
                            <th>E-mail</th>
                            <th class="text-center">Terület (m²)</th>
                            <th class="text-center">Szerződés</th>
                            <th class="text-center">Kivitelezés</th>
                            <th>Ügyintéző</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): 
                            // Szín generálás az ügyintéző alapján
                            $bg_color = 'transparent';
                            if ($client['agent_color']) {
                                // Hexadecimális szín konvertálása RGBA-ra 30% átlátszósággal
                                $hex = str_replace('#', '', $client['agent_color']);
                                $r = hexdec(substr($hex, 0, 2));
                                $g = hexdec(substr($hex, 2, 2));
                                $b = hexdec(substr($hex, 4, 2));
                                $bg_color = "rgba($r, $g, $b, 0.15)";
                            }
                            
                            // Szerkesztési jogosultság ellenőrzése
                            // Szerkesztési jogosultság ellenőrzése:
// Admin mindig szerkeszthet.
// Ügyintéző (nem admin) csak akkor, ha:
// 1. Nincs hozzárendelve ügyintéző (agent_id NULL)
// 2. Ő van hozzárendelve (agent_id == current_user_agent_id)
$can_edit = isAdmin() || 
            ($current_user_agent_id && 
             ($client['agent_id'] === null || $client['agent_id'] == $current_user_agent_id)
            );
                        ?>
                            <tr class="client-row <?php echo $can_edit ? '' : 'locked-row'; ?>" style="background-color: <?php echo $bg_color; ?>;" data-client-id="<?php echo $client['id']; ?>">
                                <td class="checkbox-cell" onclick="event.stopPropagation();">
                                    <?php if ($can_edit): ?>
                                        <input type="checkbox" class="client-checkbox form-check-input" value="<?php echo $client['id']; ?>" data-can-edit="1">
                                    <?php else: ?>
                                        <i class="bi bi-lock-fill text-danger" title="Másik ügyintézőhöz rendelve"></i>
                                        <input type="checkbox" class="client-checkbox form-check-input d-none" value="<?php echo $client['id']; ?>" data-can-edit="0" disabled>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo escape($client['name']); ?></strong>
                                    <?php if (!empty($client['notes'])): ?>
                                        <i class="bi bi-chat-left-text-fill text-primary ms-2" 
                                           title="Megjegyzés: <?php echo escape(mb_substr($client['notes'], 0, 100)); ?>"
                                           style="cursor: help;"></i>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo escape($client['settlement_name'] ?? '-'); ?></td>
                                <td><?php echo escape($client['address'] ?? '-'); ?></td>
                                <td><?php echo escape($client['phone'] ?? '-'); ?></td>
                                <td><?php echo escape($client['email'] ?? '-'); ?></td>
                                <td class="text-center"><?php echo $client['insulation_area'] ?? '-'; ?></td>
                                <td class="text-center">
                                    <?php if ($client['contract_signed']): ?>
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                    <?php else: ?>
                                        <span style="background-color: #dc3545; color: white; padding: 2px 6px; border-radius: 4px; display: inline-block;">
                                            <i class="bi bi-x-lg"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($client['work_completed']): ?>
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                    <?php else: ?>
                                        <span style="background-color: #dc3545; color: white; padding: 2px 6px; border-radius: 4px; display: inline-block;">
                                            <i class="bi bi-x-lg"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($client['agent_name']): ?>
                                        <span class="agent-badge" style="background-color: <?php echo escape($client['agent_color']); ?>; color: #000;">
                                            <?php echo escape($client['agent_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Pagination" class="mt-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div class="text-muted">
                            Összesen: <strong><?php echo $total_records; ?></strong> ügyfél | 
                            Oldal: <strong><?php echo $page; ?></strong> / <strong><?php echo $total_pages; ?></strong>
                        </div>
                        <ul class="pagination mb-0">
                            <!-- Első oldal -->
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo buildPaginationUrl(1, $county_id, $search, $filter_agent, $sort_by); ?>">
                                    <i class="bi bi-chevron-double-left"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo buildPaginationUrl($page - 1, $county_id, $search, $filter_agent, $sort_by); ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                            <?php endif; ?>
            
                            <!-- Oldalszámok -->
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
            
                            if ($start > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo buildPaginationUrl($i, $county_id, $search, $filter_agent, $sort_by); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
            
                            <?php if ($end < $total_pages) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?>
            
                            <!-- Utolsó oldal -->
                            <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo buildPaginationUrl($page + 1, $county_id, $search, $filter_agent, $sort_by); ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo buildPaginationUrl($total_pages, $county_id, $search, $filter_agent, $sort_by); ?>">
                                    <i class="bi bi-chevron-double-right"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </nav>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Törlés form (rejtett) -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="delete_id" id="delete_id">
    </form>
    
    <!-- Tömeges törlés form (rejtett) -->
    <form id="bulkDeleteForm" method="POST" style="display: none;">
        <input type="hidden" name="bulk_delete" value="1">
        <input type="hidden" name="client_ids" id="bulk_delete_ids">
    </form>
    
    <!-- Tömeges szerkesztés modal -->
    <div class="modal fade" id="bulkEditModal" tabindex="-1" aria-labelledby="bulkEditModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkEditModalLabel">Tömeges szerkesztés</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bezárás"></button>
                </div>
                <div class="modal-body">
                    <!-- Navigációs panel -->
                    <div class="bulk-edit-navigation">
                        <div class="client-counter">
                            <span id="currentClientIndex">1</span> / <span id="totalClientsCount">1</span>
                        </div>
                        <div class="nav-buttons">
                            <button type="button" class="btn btn-outline-primary" id="prevClientBtn">
                                <i class="bi bi-arrow-left"></i> Előző
                            </button>
                            <button type="button" class="btn btn-outline-primary" id="nextClientBtn">
                                Következő <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Betöltési állapot -->
                    <div id="bulkEditLoading" class="text-center py-5" style="display: none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Betöltés...</span>
                        </div>
                        <p class="mt-3">Betöltés...</p>
                    </div>
                    
                    <!-- Űrlap tartalom -->
                    <div id="bulkEditFormContainer">
                        <!-- Az űrlap ide kerül AJAX-szal -->
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="me-auto">
                        <span id="modifiedClientsCount" class="badge bg-warning text-dark" style="display: none;">
                            <i class="bi bi-exclamation-circle"></i> <span id="modifiedCount">0</span> módosított ügyfél
                        </span>
                    </div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Mégse</button>
                    <button type="button" class="btn btn-success" id="saveAllBulkEditBtn">
                        <i class="bi bi-save-fill"></i> Összes mentése
                    </button>
                    <button type="button" class="btn btn-primary" id="saveBulkEditBtn">
                        <i class="bi bi-save"></i> Aktuális mentése
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Felhasználói szerepkör tárolása JavaScript-ben
        const userIsAdmin = <?php echo isAdmin() ? 'true' : 'false'; ?>;
        const currentUserAgentId = <?php echo $current_user_agent_id ? $current_user_agent_id : 'null'; ?>;
        const currentUserName = '<?php echo escape($_SESSION['name']); ?>';
        
        function deleteClient(id, name) {
            if (confirm('Biztosan törölni szeretnéd ezt az ügyfelet: ' + name + '?')) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
        
        // Checkbox kezelés
        const selectAllCheckbox = document.getElementById('select-all');
        const clientCheckboxes = document.querySelectorAll('.client-checkbox');
        const clientRows = document.querySelectorAll('.client-row');
        const bulkActionsPanel = document.getElementById('bulk-actions-panel');
        const selectedCountSpan = document.getElementById('selected-count');
        const bulkEditBtn = document.getElementById('bulk-edit-btn');
        const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
        const deselectAllBtn = document.getElementById('deselect-all-btn');
        
        // Sor kattintás kezelése (bárhol a soron)
        clientRows.forEach(row => {
            row.addEventListener('click', function(e) {
                // Csak akkor engedélyezzük a sor kattintást, ha nincs lelakatolva
                if (this.classList.contains('locked-row')) {
                    return;
                }
                
                const checkbox = this.querySelector('.client-checkbox');
                
                // Ellenőrizzük, hogy a kattintás nem a checkbox-on történt-e
                if (checkbox && !e.target.classList.contains('client-checkbox')) {
                    checkbox.checked = !checkbox.checked;
                    updateRowSelection(this, checkbox.checked);
                    updateBulkActionsPanel();
                    
                    // Mindet kijelöl checkbox frissítése
                    const allChecked = Array.from(clientCheckboxes).every(cb => cb.checked);
                    if (selectAllCheckbox) {
                        selectAllCheckbox.checked = allChecked;
                    }
                }
            });
        });
        
        // Sor kijelölés vizuális frissítése
        function updateRowSelection(row, isSelected) {
            if (isSelected) {
                row.classList.add('selected');
            } else {
                row.classList.remove('selected');
            }
        }
        
        // Mindet kijelöl/töröl
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const isChecked = this.checked;
                clientCheckboxes.forEach(cb => {
                    if (!cb.disabled) {
                        cb.checked = isChecked;
                    }
                });
                // Vizuális frissítés minden sorra (csak a nem zárolt sorokra)
                clientRows.forEach(row => {
                    if (!row.classList.contains('locked-row')) {
                        updateRowSelection(row, isChecked);
                    }
                });
                updateBulkActionsPanel();
            });
        }
        
        // Egyedi checkbox-ok kezelése
        clientCheckboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                // Vizuális frissítés a sorhoz
                const row = this.closest('.client-row');
                if (row) {
                    updateRowSelection(row, this.checked);
                }
                
                updateBulkActionsPanel();
                
                // Mindet kijelöl checkbox frissítése
                const allChecked = Array.from(clientCheckboxes).every(checkbox => checkbox.checked);
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = allChecked;
                }
            });
        });
        
        // Kijelölés törlése gomb
        if (deselectAllBtn) {
            deselectAllBtn.addEventListener('click', function() {
                clientCheckboxes.forEach(cb => {
                    if (!cb.disabled) {
                        cb.checked = false;
                    }
                });
                // Vizuális frissítés minden sorra (csak a nem zárolt sorokra)
                clientRows.forEach(row => {
                    if (!row.classList.contains('locked-row')) {
                        updateRowSelection(row, false);
                    }
                });
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = false;
                }
                updateBulkActionsPanel();
            });
        }
        
        // Tömeges törlés gomb
        if (bulkDeleteBtn) {
            bulkDeleteBtn.addEventListener('click', function() {
                const selectedIds = Array.from(clientCheckboxes)
                    .filter(cb => cb.checked)
                    .map(cb => cb.value);
                
                if (selectedIds.length === 0) {
                    alert('Nincs kijelölt ügyfél!');
                    return;
                }
                
                if (confirm(`Biztosan törölni szeretnéd a kijelölt ${selectedIds.length} ügyfelet?`)) {
                    document.getElementById('bulk_delete_ids').value = selectedIds.join(',');
                    document.getElementById('bulkDeleteForm').submit();
                }
            });
        }
        
        // Tömeges szerkesztés gomb
        if (bulkEditBtn) {
            bulkEditBtn.addEventListener('click', function() {
                const selectedCheckboxes = Array.from(clientCheckboxes).filter(cb => cb.checked);
                
                if (selectedCheckboxes.length === 0) {
                    alert('Nincs kijelölt ügyfél!');
                    return;
                }
                
                // Ellenőrizzük, hogy van-e olyan ügyfél, amit nem szerkeszthet
                const cannotEdit = selectedCheckboxes.filter(cb => cb.dataset.canEdit === '0');
                if (cannotEdit.length > 0) {
                    alert('Néhány kijelölt ügyfelet nem szerkeszthetsz!');
                    return;
                }
                
                // Kijelölt ügyfél ID-k tömbje
                bulkEditClientIds = selectedCheckboxes.map(cb => cb.value);
                currentBulkEditIndex = 0;
                
                // Modal megnyitása
                const modal = new bootstrap.Modal(document.getElementById('bulkEditModal'));
                modal.show();
                
                // Első ügyfél betöltése
                loadBulkEditClient(0);
            });
        }
        
        // Panel frissítése
        function updateBulkActionsPanel() {
            const selectedCount = Array.from(clientCheckboxes).filter(cb => cb.checked).length;
            
            if (selectedCount > 0) {
                bulkActionsPanel.classList.remove('d-none');
                selectedCountSpan.textContent = selectedCount;
            } else {
                bulkActionsPanel.classList.add('d-none');
            }
        }
        
        // Tömeges szerkesztés változók
        let bulkEditClientIds = [];
        let currentBulkEditIndex = 0;
        let currentClientData = {};
        let clientsOriginalData = {}; // Eredeti adatok tárolása
        let clientsModifiedData = {}; // Módosított adatok cache
        
        // Aktuális űrlap adatok mentése cache-be
        function saveCurrentFormData() {
            const form = document.getElementById('bulkEditForm');
            if (!form) return;
            
            const clientId = document.getElementById('edit_client_id').value;
            if (!clientId) return;
            
            clientsModifiedData[clientId] = {
                name: document.getElementById('edit_name').value,
                phone: document.getElementById('edit_phone').value,
                email: document.getElementById('edit_email').value,
                address: document.getElementById('edit_address').value,
                county_id: document.getElementById('edit_county').value,
                settlement_id: document.getElementById('edit_settlement').value,
                agent_id: document.getElementById('edit_agent').value,
                insulation_area: document.getElementById('edit_insulation_area').value,
                contract_signed: document.getElementById('edit_contract_signed').checked,
                work_completed: document.getElementById('edit_work_completed').checked,
                notes: document.getElementById('edit_notes').value
            };
        }
        
        // Ügyfél betöltése AJAX-szal
        function loadBulkEditClient(index) {
            if (index < 0 || index >= bulkEditClientIds.length) return;
            
            // Aktuális űrlap mentése navigálás előtt
            saveCurrentFormData();
            
            currentBulkEditIndex = index;
            const clientId = bulkEditClientIds[index];
            
            // Számláló frissítése
            document.getElementById('currentClientIndex').textContent = index + 1;
            document.getElementById('totalClientsCount').textContent = bulkEditClientIds.length;
            
            // Navigációs gombok engedélyezése/tiltása
            document.getElementById('prevClientBtn').disabled = (index === 0);
            document.getElementById('nextClientBtn').disabled = (index === bulkEditClientIds.length - 1);
            
            // Betöltés jelzése
            document.getElementById('bulkEditLoading').style.display = 'block';
            document.getElementById('bulkEditFormContainer').style.display = 'none';
            
            // AJAX kérés
            fetch(`get_client_data.php?id=${clientId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentClientData = data.client;
                        
                        // Eredeti adatok tárolása (csak egyszer, az első betöltéskor)
                        if (!clientsOriginalData[clientId]) {
                            clientsOriginalData[clientId] = JSON.parse(JSON.stringify(data.client));
                        }
                        
                        // Ha van módosított adat, azt használjuk, különben az eredetit
                        let dataToRender = data.client;
                        if (clientsModifiedData[clientId]) {
                            // Összeolvasztjuk az eredeti adatokat a módosítottakkal
                            dataToRender = Object.assign({}, data.client, clientsModifiedData[clientId]);
                            
                            // A checkboxok esetében a cache-ben tárolt boolean értékeket vissza kell konvertálni 0/1-re a rendereléshez
                            dataToRender.contract_signed = clientsModifiedData[clientId].contract_signed ? 1 : 0;
                            dataToRender.work_completed = clientsModifiedData[clientId].work_completed ? 1 : 0;
                        }
                        
                        renderBulkEditForm(dataToRender, data.counties, data.settlements, data.agents);
                    } else {
                        alert('Hiba az ügyfél betöltése során!');
                    }
                })
                .catch(error => {
                    console.error('Hiba:', error);
                    alert('Hiba az ügyfél betöltése során!');
                })
                .finally(() => {
                    document.getElementById('bulkEditLoading').style.display = 'none';
                    document.getElementById('bulkEditFormContainer').style.display = 'block';
                });
        }
        
        // Űrlap renderelése
        function renderBulkEditForm(client, counties, settlements, agents) {
            const container = document.getElementById('bulkEditFormContainer');
            
            let html = `
                <form id="bulkEditForm">
                    <input type="hidden" id="edit_client_id" value="${client.id}">
                    
                    <div class="alert alert-info">
                        <strong>Ügyfél:</strong> ${escapeHtml(client.name)}
                    </div>
            `;
            
            // Admin látja és szerkesztheti a Név mezőt
            if (userIsAdmin) {
                html += `
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_name" class="form-label">Név *</label>
                            <input type="text" class="form-control" id="edit_name" value="${escapeHtml(client.name)}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_phone" class="form-label">Telefon</label>
                            <input type="text" class="form-control" id="edit_phone" value="${escapeHtml(client.phone || '')}">
                        </div>
                    </div>
                `;
            } else {
                // Ügyintéző nem látja a Név mezőt, csak a Telefont
                html += `
                    <input type="hidden" id="edit_name" value="${escapeHtml(client.name)}">
                    <div class="mb-3">
                        <label for="edit_phone" class="form-label">Telefon</label>
                        <input type="text" class="form-control" id="edit_phone" value="${escapeHtml(client.phone || '')}">
                    </div>
                `;
            }
            
            html += `
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_email" class="form-label">E-mail</label>
                            <input type="email" class="form-control" id="edit_email" value="${escapeHtml(client.email || '')}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_insulation_area" class="form-label">Terület (m²)</label>
                            <input type="number" class="form-control" id="edit_insulation_area" value="${client.insulation_area || ''}">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_address" class="form-label">Cím</label>
                        <input type="text" class="form-control" id="edit_address" value="${escapeHtml(client.address || '')}">
                    </div>
            `;
            
            // Admin látja és szerkesztheti a Megye és Település mezőket
            if (userIsAdmin) {
                html += `
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_county" class="form-label">Megye</label>
                            <select class="form-select" id="edit_county">
                                ${counties.map(c => `<option value="${c.id}" ${c.id == client.county_id ? 'selected' : ''}>${escapeHtml(c.name)}</option>`).join('')}
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_settlement" class="form-label">Település</label>
                            <select class="form-select" id="edit_settlement">
                                ${settlements.map(s => `<option value="${s.id}" ${s.id == client.settlement_id ? 'selected' : ''}>${escapeHtml(s.name)}</option>`).join('')}
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_agent" class="form-label">Ügyintéző</label>
                        <select class="form-select" id="edit_agent">
                            <option value="">Nincs hozzárendelve</option>
                            ${agents.map(a => `<option value="${a.id}" ${a.id == client.agent_id ? 'selected' : ''}>${escapeHtml(a.name)}</option>`).join('')}
                        </select>
                    </div>
                `;
            } else {
                // Ügyintéző nem szerkesztheti a megyét és települést, rejtett mezőként tároljuk
                html += `
                    <input type="hidden" id="edit_county" value="${client.county_id}">
                    <input type="hidden" id="edit_settlement" value="${client.settlement_id}">
                `;
                
                // Ügyintéző látja az Ügyintéző mezőt, de csak saját magát választhatja
                html += `
                    <div class="mb-3">
                        <label for="edit_agent" class="form-label">Ügyintéző</label>
                        <select class="form-select" id="edit_agent">
                            <option value="">Nincs hozzárendelve</option>
                `;
                
                // Csak a saját nevét jelenítjük meg opcióként
                if (currentUserAgentId) {
                    const isSelected = client.agent_id == currentUserAgentId ? 'selected' : '';
                    html += `<option value="${currentUserAgentId}" ${isSelected}>${escapeHtml(currentUserName)}</option>`;
                }
                
                html += `
                        </select>
                    </div>
                `;
            }
            
            html += `
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_contract_signed" ${client.contract_signed == 1 ? 'checked' : ''}>
                                <label class="form-check-label" for="edit_contract_signed">
                                    Szerződés aláírva
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_work_completed" ${client.work_completed == 1 ? 'checked' : ''}>
                                <label class="form-check-label" for="edit_work_completed">
                                    Kivitelezés befejezve
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_notes" class="form-label">Megjegyzések</label>
                        <textarea class="form-control" id="edit_notes" rows="3">${escapeHtml(client.notes || '')}</textarea>
                    </div>
                </form>
            `;
            
            container.innerHTML = html;
            
            // Űrlap változás figyelésének csatolása
            attachFormChangeListeners();
        }
        
        // HTML escape függvény
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Navigációs gombok
        document.getElementById('prevClientBtn').addEventListener('click', function() {
            if (currentBulkEditIndex > 0) {
                loadBulkEditClient(currentBulkEditIndex - 1);
            }
        });
        
        document.getElementById('nextClientBtn').addEventListener('click', function() {
            if (currentBulkEditIndex < bulkEditClientIds.length - 1) {
                loadBulkEditClient(currentBulkEditIndex + 1);
            }
        });
        
        // Módosított ügyfelek számának frissítése
        function updateModifiedCount() {
            const count = Object.keys(clientsModifiedData).length;
            const badge = document.getElementById('modifiedClientsCount');
            const countSpan = document.getElementById('modifiedCount');
            
            if (count > 0) {
                badge.style.display = 'inline-block';
                countSpan.textContent = count;
            } else {
                badge.style.display = 'none';
            }
        }
        
        // Űrlap változás figyelése
        function attachFormChangeListeners() {
            const form = document.getElementById('bulkEditForm');
            if (!form) return;
            
            form.addEventListener('input', function() {
                saveCurrentFormData();
                updateModifiedCount();
            });
            
            form.addEventListener('change', function() {
                saveCurrentFormData();
                updateModifiedCount();
            });
        }
        
        // Aktuális mentése gomb
        document.getElementById('saveBulkEditBtn').addEventListener('click', function() {
            saveCurrentFormData();
            
            const clientId = document.getElementById('edit_client_id').value;
            const formData = new FormData();
            
            formData.append('id', clientId);
            formData.append('name', document.getElementById('edit_name').value);
            formData.append('phone', document.getElementById('edit_phone').value);
            formData.append('email', document.getElementById('edit_email').value);
            formData.append('address', document.getElementById('edit_address').value);
            formData.append('county_id', document.getElementById('edit_county').value);
            formData.append('settlement_id', document.getElementById('edit_settlement').value);
            formData.append('agent_id', document.getElementById('edit_agent').value);
            formData.append('insulation_area', document.getElementById('edit_insulation_area').value);
            formData.append('contract_signed', document.getElementById('edit_contract_signed').checked ? 1 : 0);
            formData.append('work_completed', document.getElementById('edit_work_completed').checked ? 1 : 0);
            formData.append('notes', document.getElementById('edit_notes').value);
            
            // AJAX mentés
            fetch('save_client.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Töröljük a cache-ből, mert elmentettük
                    delete clientsModifiedData[clientId];
                    updateModifiedCount();
                    
                    alert('Ügyfél sikeresen mentve!');
                    
                    // Ha van még ügyfél, következőre lép
                    if (currentBulkEditIndex < bulkEditClientIds.length - 1) {
                        loadBulkEditClient(currentBulkEditIndex + 1);
                    } else {
                        // Utolsó ügyfél, bezárjuk a modal-t és frissítjük az oldalt
                        bootstrap.Modal.getInstance(document.getElementById('bulkEditModal')).hide();
                        location.reload();
                    }
                } else {
                    alert('Hiba a mentés során: ' + (data.error || 'Ismeretlen hiba'));
                }
            })
            .catch(error => {
                console.error('Hiba:', error);
                alert('Hiba a mentés során!');
            });
        });
        
        // Összes mentése gomb
        document.getElementById('saveAllBulkEditBtn').addEventListener('click', function() {
            saveCurrentFormData();
            
            const modifiedIds = Object.keys(clientsModifiedData);
            
            if (modifiedIds.length === 0) {
                alert('Nincs módosított ügyfél!');
                return;
            }
            
            if (!confirm(`Biztosan menteni szeretnéd az összes ${modifiedIds.length} módosított ügyfelet?`)) {
                return;
            }
            
            // Mentés jelzése
            const saveBtn = document.getElementById('saveAllBulkEditBtn');
            const originalText = saveBtn.innerHTML;
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Mentés...';
            
            // Összes módosított ügyfél mentése
            const savePromises = modifiedIds.map(clientId => {
                const data = clientsModifiedData[clientId];
                const formData = new FormData();
                
                formData.append('id', clientId);
                formData.append('name', data.name);
                formData.append('phone', data.phone);
                formData.append('email', data.email);
                formData.append('address', data.address);
                formData.append('county_id', data.county_id);
                formData.append('settlement_id', data.settlement_id);
                formData.append('agent_id', data.agent_id);
                formData.append('insulation_area', data.insulation_area);
                formData.append('contract_signed', data.contract_signed ? 1 : 0);
                formData.append('work_completed', data.work_completed ? 1 : 0);
                formData.append('notes', data.notes);
                
                return fetch('save_client.php', {
                    method: 'POST',
                    body: formData
                }).then(response => response.json());
            });
            
            Promise.all(savePromises)
                .then(results => {
                    const failed = results.filter(r => !r.success);
                    
                    if (failed.length === 0) {
                        alert(`Minden ügyfél sikeresen mentve! (${modifiedIds.length} db)`);
                        // Töröljük a cache-t
                        clientsModifiedData = {};
                        updateModifiedCount();
                        // Bezárjuk a modal-t és frissítjük az oldalt
                        bootstrap.Modal.getInstance(document.getElementById('bulkEditModal')).hide();
                        location.reload();
                    } else {
                        alert(`${modifiedIds.length - failed.length} ügyfél sikeresen mentve, ${failed.length} hibával.`);
                    }
                })
                .catch(error => {
                    console.error('Hiba:', error);
                    alert('Hiba történt a mentés során!');
                })
                .finally(() => {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = originalText;
                });
        });
        
        // Toast container hozzáadása
        const toastContainer = document.createElement('div');
        toastContainer.className = 'position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        toastContainer.innerHTML = '<div id="toastContainer"></div>';
        document.body.appendChild(toastContainer);
        
        let lastChatCheck = new Date().toISOString();
        
        // Toast értesítés megjelenítése
        function showToast(title, message, type = 'info', link = null) {
            const toastId = 'toast-' + Date.now();
            const bgClass = {
                'info': 'bg-primary',
                'success': 'bg-success',
                'warning': 'bg-warning',
                'danger': 'bg-danger'
            }[type] || 'bg-primary';
            
            const toastHTML = `
                <div class="toast" id="${toastId}" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header ${bgClass} text-white">
                        <i class="bi bi-bell-fill me-2"></i>
                        <strong class="me-auto">${title}</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body ${link ? 'cursor-pointer' : ''}" ${link ? `onclick="window.location.href='${link}'"` : ''}>
                        ${message}
                        ${link ? '<div class="mt-2"><small class="text-muted"><i class="bi bi-hand-index"></i> Kattints a megnyitáshoz</small></div>' : ''}
                    </div>
                </div>
            `;
            
            const container = document.getElementById('toastContainer');
            container.insertAdjacentHTML('beforeend', toastHTML);
            
            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement, { delay: 5000 });
            toast.show();
            
            toastElement.addEventListener('hidden.bs.toast', () => {
                toastElement.remove();
            });
        }
        
        // Értesítések ellenőrzése
        function checkNotifications() {
            fetch('notifications_api.php?action=get_counts')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Chat badge
                        const chatBadge = document.getElementById('chatBadge');
                        if (chatBadge) {
                            if (data.chat_unread > 0) {
                                chatBadge.textContent = data.chat_unread;
                                chatBadge.style.display = 'inline-block';
                            } else {
                                chatBadge.style.display = 'none';
                            }
                        }
                        
                        // Approvals badge (admin only)
                        const approvalsBadge = document.getElementById('approvalsBadge');
                        if (approvalsBadge && data.approvals_pending > 0) {
                            approvalsBadge.textContent = data.approvals_pending;
                            approvalsBadge.style.display = 'inline-block';
                        } else if (approvalsBadge) {
                            approvalsBadge.style.display = 'none';
                        }
                    }
                });
            
            // Approval notifications (ügyintézőknek)
            const myRequestsBadge = document.getElementById('myRequestsBadge');
            if (myRequestsBadge) {
                fetch('approval_notifications_api.php?action=get_unread')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.count > 0) {
                            myRequestsBadge.textContent = data.count;
                            myRequestsBadge.style.display = 'inline-block';
                            
                            // Toast értesítések az új döntésekről
                            data.notifications.forEach(notif => {
                                if (notif.approval_status === 'approved') {
                                    showToast(
                                        'Ügyfél Elfogadva',
                                        `Az ügyfél "${notif.client_name}" jóváhagyásra került!`,
                                        'success',
                                        'my_requests.php'
                                    );
                                } else if (notif.approval_status === 'rejected') {
                                    showToast(
                                        'Ügyfél Elutasítva',
                                        `Az ügyfél "${notif.client_name}" elutasításra került. Indok: ${notif.rejection_reason}`,
                                        'danger',
                                        'my_requests.php'
                                    );
                                }
                                
                                // Olvasottnak jelöljük a toast megjelenítése után
                                fetch('approval_notifications_api.php?action=mark_read', {
                                    method: 'POST',
                                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                    body: `notification_id=${notif.id}`
                                });
                            });
                        } else if (myRequestsBadge) {
                            myRequestsBadge.style.display = 'none';
                        }
                    });
            }
            
            // Legújabb chat üzenet ellenőrzése (toast-hoz)
            fetch(`notifications_api.php?action=get_latest_chat_message&last_check=${encodeURIComponent(lastChatCheck)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.has_new) {
                        showToast(
                            'Új üzenet',
                            `${data.message.user_name}: ${data.message.message}`,
                            'info',
                            'chat.php'
                        );
                        // Frissítjük a lastChatCheck-et az üzenet idéjére, hogy ne duplikáljon
                        lastChatCheck = data.message.created_at;
                    }
                });
        }
        
        // Ügyfél megtekintettség jelölése (amikor rákattint egy sorra)
        clientRows.forEach(row => {
            const originalClickHandler = row.onclick;
            row.addEventListener('click', function() {
                const clientId = this.dataset.clientId;
                if (clientId) {
                    fetch('notifications_api.php?action=mark_client_viewed', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'client_id=' + clientId
                    });
                }
            });
        });
        
        // Megye megnyitásakor az összes ügyfél megtekintettség jelölése
        const countyId = <?php echo $county_id; ?>;
        fetch('notifications_api.php?action=mark_county_clients_viewed', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'county_id=' + countyId
        }).then(response => response.json())
          .then(data => {
              if (data.success && data.marked_count > 0) {
                  console.log(`${data.marked_count} ügyfél megtekintettség jelölve`);
              }
          });
        
        // Oldal betöltésekor és 2 másodpercenként ellenőrzés
        checkNotifications();
        setInterval(checkNotifications, 2000);
    </script>
</body>
</html>
