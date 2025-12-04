<?php
require_once 'config.php';
require_once 'cache/CacheHelper.php';
requireLogin();

// Megyék lekérdezése ügyfélszámmal
// Megyék lekérdezése
$counties = $pdo->query("SELECT * FROM counties ORDER BY name")->fetchAll();

// Települések lekérdezése (ha van kiválasztott megye)
if (isset($_GET['county_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM settlements WHERE county_id = ?");
    $stmt->execute([$_GET['county_id']]);
    $settlements = $stmt->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/crm-main.css?v=1.0">
    <link rel="stylesheet" href="improved_styles.css?v=1.0">
</head>
<body>
    <div class="header py-3 mb-4">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="mb-0"><?php echo APP_NAME; ?></h3>
                <div class="d-flex align-items-center gap-2 gap-md-3 flex-wrap justify-content-end">
                    <a href="chat.php" class="btn btn-outline-primary btn-sm notification-badge position-relative" title="Chat" id="chatLink">
                        <i class="bi bi-chat-dots-fill"></i> <span class="d-none d-md-inline">Chat</span>
                        <span class="badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle" id="chatBadge" style="display: none; font-size: 0.7rem;">0</span>
                    </a>
                    <a href="patchnotes.php" class="btn btn-outline-success btn-sm notification-badge" title="Változásnapló" id="patchnotesLink">
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
        <div class="mb-4">
            <h2>Válassz megyét</h2>
            <p class="text-muted">Kattints egy megyére az ügyfelek megtekintéséhez és kezeléséhez</p>
        </div>

        <div class="row g-3">
            <?php foreach ($counties as $county): ?>
                <div class="col-md-4">
                    <a href="county.php?id=<?php echo $county['id']; ?>" class="text-decoration-none">
                        <div class="card county-card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary bg-opacity-10 rounded p-3 me-3">
                                        <i class="bi bi-geo-alt-fill text-primary fs-4"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="mb-0 text-dark"><?php echo escape($county['name']); ?></h5>
                                                <small class="text-muted">
                                                    <i class="bi bi-people-fill"></i>
                                                    <?php echo $county['client_count']; ?> ügyfél
                                                </small>
                                            </div>
                                            <span class="badge bg-success new-client-badge" data-county-id="<?php echo $county['id']; ?>" style="display: none;">0 új</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Toast container -->
    <div class="position-fixed top-0 end-0 p-3" style="z-index: 9999;">
        <div id="toastContainer"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/crm-main.js?v=1.0"></script>
</body>
</html>
