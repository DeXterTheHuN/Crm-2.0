<?php
require_once 'config.php';
requireLogin();
requireAdmin();

// Újranyitás kezelése
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reopen_id'])) {
    $reopen_id = $_POST['reopen_id'];
    // Újranyitás: closed_at = NULL és pipák kivétele
    $stmt = $pdo->prepare("UPDATE clients SET closed_at = NULL, contract_signed = 0, work_completed = 0 WHERE id = ?");
    $stmt->execute([$reopen_id]);
    redirect("statistics.php?month=" . ($_GET['month'] ?? date('Y-m')));
}

// Hónap kiválasztása (alapértelmezett: aktuális hónap)
$selected_month = $_GET['month'] ?? date('Y-m');

// Hónap első és utolsó napja
$month_start = $selected_month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));

// Statisztika lekérdezése: ügyintézők szerződött ügyfelei az adott hónapban
$stmt = $pdo->prepare("
    SELECT 
        a.name as agent_name,
        u.role as user_role,
        COUNT(DISTINCT c.id) as contract_count
    FROM agents a
    LEFT JOIN users u ON a.name = u.name
    LEFT JOIN clients c ON c.agent_id = a.id 
        AND c.contract_signed = 1 
        AND c.approved = 1
        AND DATE(c.contract_signed_at) BETWEEN ? AND ?
    WHERE u.approved = 1
    GROUP BY a.id, a.name, u.role
    ORDER BY contract_count DESC, a.name ASC
");
$stmt->execute([$month_start, $month_end]);
$statistics = $stmt->fetchAll();

// Összes szerződés száma
$total_contracts = array_sum(array_column($statistics, 'contract_count'));

// Lezárt ügyfelek lekérdezése (szerződés + kivitelezés kész)
$closed_stmt = $pdo->prepare("
    SELECT 
        c.id,
        c.name,
        c.closed_at,
        co.name as county_name,
        s.name as settlement_name,
        a.name as agent_name,
        a.color as agent_color
    FROM clients c
    LEFT JOIN counties co ON c.county_id = co.id
    LEFT JOIN settlements s ON c.settlement_id = s.id
    LEFT JOIN agents a ON c.agent_id = a.id
    WHERE c.closed_at IS NOT NULL
      AND c.approved = 1
      AND DATE(c.closed_at) BETWEEN ? AND ?
    ORDER BY c.closed_at DESC
");
$closed_stmt->execute([$month_start, $month_end]);
$closed_clients = $closed_stmt->fetchAll();
$total_closed = count($closed_clients);
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statisztikák - <?php echo APP_NAME; ?></title>
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
        .stats-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .top-performer {
            background-color: #fff3cd;
        }
        .agent-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 500;
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
                    <a href="approvals.php" class="btn btn-outline-warning btn-sm">
                        <i class="bi bi-clock-history"></i> Jóváhagyások
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

        <div class="stats-card p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">
                    <i class="bi bi-graph-up"></i> Havi Szerződéskötési Statisztika
                </h4>
                <form method="GET" class="d-flex align-items-center gap-2">
                    <label for="month" class="mb-0">Hónap:</label>
                    <input type="month" name="month" id="month" class="form-control" 
                           value="<?php echo escape($selected_month); ?>" 
                           onchange="this.form.submit()">
                </form>
            </div>

            <div class="alert alert-info">
                <strong><i class="bi bi-info-circle"></i> Tudnivalók:</strong>
                <ul class="mb-0 mt-2">
                    <li>Csak azok az ügyfelek számítanak, ahol a szerződéskötés megtörtént (✓)</li>
                    <li>Az adott hónapban frissített ügyfelek szerepelnek</li>
                    <li>A statisztika minden felhasználót (admin és ügyintéző) tartalmaz</li>
                </ul>
            </div>

            <?php if (empty($statistics) || $total_contracts == 0): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> 
                    Ebben a hónapban még nem történt szerződéskötés.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Felhasználó</th>
                                <th>Szerepkör</th>
                                <th class="text-center">Szerződött Ügyfelek</th>
                                <th class="text-center">Arány (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            foreach ($statistics as $stat): 
                                $percentage = $total_contracts > 0 ? round(($stat['contract_count'] / $total_contracts) * 100, 1) : 0;
                                $is_top = $rank === 1 && $stat['contract_count'] > 0;
                            ?>
                                <tr class="<?php echo $is_top ? 'top-performer' : ''; ?>">
                                    <td>
                                        <?php if ($is_top): ?>
                                            <i class="bi bi-trophy-fill text-warning"></i>
                                        <?php else: ?>
                                            <?php echo $rank; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo escape($stat['agent_name']); ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($stat['user_role'] === 'admin'): ?>
                                            <span class="badge bg-primary">Adminisztrátor</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Ügyintéző</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success fs-6"><?php echo $stat['contract_count']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <div class="progress" style="height: 25px;">
                                            <div class="progress-bar bg-success" role="progressbar" 
                                                 style="width: <?php echo $percentage; ?>%;" 
                                                 aria-valuenow="<?php echo $percentage; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <?php echo $percentage; ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php 
                                $rank++;
                            endforeach; 
                            ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <td colspan="3"><strong>Összesen</strong></td>
                                <td class="text-center">
                                    <strong class="text-success"><?php echo $total_contracts; ?></strong>
                                </td>
                                <td class="text-center">
                                    <strong>100%</strong>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="stats-card p-3 text-center">
                    <h5 class="text-muted">Összes Szerződés</h5>
                    <h2 class="text-success"><?php echo $total_contracts; ?></h2>
                    <small class="text-muted"><?php echo date('Y. F', strtotime($selected_month)); ?></small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card p-3 text-center">
                    <h5 class="text-muted">Aktív Felhasználók</h5>
                    <h2 class="text-primary"><?php echo count(array_filter($statistics, fn($s) => $s['contract_count'] > 0)); ?></h2>
                    <small class="text-muted">Szerződéssel rendelkezők</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card p-3 text-center">
                    <h5 class="text-muted">Átlag / Felhasználó</h5>
                    <h2 class="text-info">
                        <?php 
                        $active_users = count(array_filter($statistics, fn($s) => $s['contract_count'] > 0));
                        echo $active_users > 0 ? round($total_contracts / $active_users, 1) : 0; 
                        ?>
                    </h2>
                    <small class="text-muted">Szerződés / aktív felhasználó</small>
                </div>
            </div>
        </div>

        <!-- Lezárt Ügyfelek Szekció -->
        <div class="stats-card p-4 mt-4">
            <h4 class="mb-4">
                <i class="bi bi-folder-check"></i> Lezárt Ügyfelek (Kész Projektek)
                <span class="badge bg-success ms-2"><?php echo $total_closed; ?></span>
            </h4>

            <div class="alert alert-info">
                <strong><i class="bi bi-info-circle"></i> Tudnivalók:</strong>
                <ul class="mb-0 mt-2">
                    <li>Azok az ügyfelek, akiknél <strong>mind a szerződéskötés, mind a kivitelezés</strong> megtörtént</li>
                    <li>Az adott hónapban lezárt projektek szerepelnek</li>
                    <li>A lezárás dátuma automatikusan rögzítődik</li>
                </ul>
            </div>

            <?php if (empty($closed_clients)): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> 
                    Ebben a hónapban még nem zárult le projekt.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Név</th>
                                <th>Megye</th>
                                <th>Település</th>
                                <th>Ügyintéző</th>
                                <th>Lezárás Dátuma</th>
                                <th>Műveletek</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $index = 1;
                            foreach ($closed_clients as $client): 
                            ?>
                                <tr>
                                    <td><?php echo $index++; ?></td>
                                    <td><strong><?php echo escape($client['name']); ?></strong></td>
                                    <td><?php echo escape($client['county_name'] ?? '-'); ?></td>
                                    <td><?php echo escape($client['settlement_name'] ?? '-'); ?></td>
                                    <td>
                                        <?php if ($client['agent_name']): ?>
                                            <span class="agent-badge" style="background-color: <?php echo escape($client['agent_color']); ?>; color: #000;">
                                                <?php echo escape($client['agent_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <i class="bi bi-calendar-check text-success"></i>
                                        <?php echo date('Y. m. d. H:i', strtotime($client['closed_at'])); ?>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Újra akarod nyitni ezt az ügyfelet? Vissza fog kerülni az aktív listába.');">
                                            <input type="hidden" name="reopen_id" value="<?php echo $client['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-primary" title="Újranyitás">
                                                <i class="bi bi-unlock"></i> Újranyitás
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
