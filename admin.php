<?php
require_once 'config.php';
requireLogin();
requireAdmin();

$success = '';
$error = '';

// Felhasználó törlése
if (isset($_GET['delete']) && $_GET['delete']) {
    $user_id = (int)$_GET['delete'];
    
    // Ne lehessen önmagát törölni
    if ($user_id == $_SESSION['user_id']) {
        $error = 'Nem törölheted a saját fiókodat!';
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
        $stmt->execute([$user_id]);
        $success = 'Felhasználó sikeresen törölve!';
    }
}

// Szerepkör változtatása
if (isset($_POST['change_role'])) {
    $user_id = (int)$_POST['user_id'];
    $new_role = $_POST['role'];
    
    if ($user_id == $_SESSION['user_id']) {
        $error = 'Nem változtathatod meg a saját szerepkörödet!';
    } elseif (in_array($new_role, ['admin', 'agent'])) {
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$new_role, $user_id]);
        $success = 'Szerepkör sikeresen megváltoztatva!';
    }
}

// Ügyintéző színének megváltoztatása
if (isset($_POST['change_color'])) {
    $agent_id = (int)$_POST['agent_id'];
    $new_color = $_POST['color'];
    
    // Szín validálás (hexadecimális formátum)
    if (preg_match('/^#[0-9A-Fa-f]{6}$/', $new_color)) {
        $stmt = $pdo->prepare("UPDATE agents SET color = ? WHERE id = ?");
        $stmt->execute([$new_color, $agent_id]);
        $success = 'Ügyintéző színe sikeresen megváltoztatva!';
    } else {
        $error = 'Helytelen színkód formátum!';
    }
}

// Felhasználó jóváhagyása
if (isset($_POST['approve_user'])) {
    $user_id = (int)$_POST['user_id'];
    $stmt = $pdo->prepare("UPDATE users SET approved = 1 WHERE id = ?");
    $stmt->execute([$user_id]);
    $success = 'Felhasználó sikeresen jóváhagyva!';
}

// Felhasználó jóváhagyásának visszavonása
if (isset($_POST['unapprove_user'])) {
    $user_id = (int)$_POST['user_id'];
    if ($user_id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("UPDATE users SET approved = 0 WHERE id = ?");
        $stmt->execute([$user_id]);
        $success = 'Felhasználó jóváhagyása visszavonva!';
    } else {
        $error = 'Nem vonhatod vissza a saját jóváhagyásodat!';
    }
}

// Összes felhasználó lekérdezése (jóváhagyásra várók elől)
$stmt = $pdo->query("SELECT * FROM users ORDER BY approved ASC, role DESC, name ASC");
$users = $stmt->fetchAll();

// Jóváhagyásra váró felhasználók száma
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE approved = 0");
$pending_count = $stmt->fetch()['count'];

// Minden felhasználó számára automatikusan létrehozzuk az agents rekordot, ha még nem létezik
$stmt = $pdo->query("SELECT id, name FROM users WHERE approved = 1");
$all_users = $stmt->fetchAll();

foreach ($all_users as $user) {
    // Ellenőrizzük, hogy létezik-e már az agents táblában
    $stmt = $pdo->prepare("SELECT id FROM agents WHERE name = ?");
    $stmt->execute([$user['name']]);
    
    if (!$stmt->fetch()) {
        // Ha még nem létezik, létrehozzuk alapértelmezett színnel
        $stmt = $pdo->prepare("INSERT INTO agents (name, color) VALUES (?, ?)");
        $stmt->execute([$user['name'], '#808080']);
    }
}

// Minden felhasználó (admin és ügyintéző) színbeállításai
$stmt = $pdo->query("
    SELECT a.*, u.role 
    FROM agents a
    INNER JOIN users u ON a.name = u.name
    WHERE u.approved = 1
    ORDER BY u.role DESC, a.name ASC
");
$agents = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Felhasználók - <?php echo APP_NAME; ?></title>
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
        .admin-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .user-row:hover {
            background-color: #f8f9fa;
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
                    <a href="profile.php" class="text-decoration-none text-dark">
                        <i class="bi bi-person-circle"></i> <?php echo escape($_SESSION['name']); ?>
                        <span class="badge bg-primary ms-2">Admin</span>
                    </a>
                    
                    <a href="audit_log.php" class="btn btn-outline-info">
                        <i class="bi bi-journal-text"></i> Audit Log
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

        <div class="admin-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">
                    <i class="bi bi-people-fill"></i> Felhasználók Kezelése
                </h4>
                <?php if ($pending_count > 0): ?>
                    <span class="badge bg-warning text-dark fs-6">
                        <i class="bi bi-clock-history"></i> <?php echo $pending_count; ?> jóváhagyásra vár
                    </span>
                <?php endif; ?>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo escape($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo escape($success); ?></div>
            <?php endif; ?>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Név</th>
                            <th>Felhasználónév</th>
                            <th>E-mail</th>
                            <th>Szerepkör</th>
                            <th>Jóváhagyás</th>
                            <th>Regisztráció</th>
                            <th>Műveletek</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr class="user-row">
                                <td><?php echo $user['id']; ?></td>
                                <td>
                                    <strong><?php echo escape($user['name']); ?></strong>
                                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                        <span class="badge bg-info text-dark">Te</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo escape($user['username']); ?></td>
                                <td><?php echo escape($user['email'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($user['role'] === 'admin'): ?>
                                        <span class="badge bg-primary">Adminisztrátor</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Ügyintéző</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['approved'] == 1): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle"></i> Jóváhagyva</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> Várakozás</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('Y-m-d', strtotime($user['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <!-- Jóváhagyás -->
                                        <?php if ($user['approved'] == 0): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="approve_user" class="btn btn-sm btn-success" 
                                                        title="Felhasználó jóváhagyása">
                                                    <i class="bi bi-check-circle"></i> Jóváhagy
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="unapprove_user" class="btn btn-sm btn-outline-warning" 
                                                        title="Jóváhagyás visszavonása"
                                                        onclick="return confirm('Biztosan visszavonod a jóváhagyást?')">
                                                    <i class="bi bi-x-circle"></i> Visszavon
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <!-- Szerepkör váltás -->
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <?php if ($user['role'] === 'admin'): ?>
                                                <input type="hidden" name="role" value="agent">
                                                <button type="submit" name="change_role" class="btn btn-sm btn-warning" 
                                                        title="Ügyintézővé alakítás"
                                                        onclick="return confirm('Biztosan ügyintézővé alakítod?')">
                                                    <i class="bi bi-arrow-down-circle"></i>
                                                </button>
                                            <?php else: ?>
                                                <input type="hidden" name="role" value="admin">
                                                <button type="submit" name="change_role" class="btn btn-sm btn-success" 
                                                        title="Adminná alakítás"
                                                        onclick="return confirm('Biztosan adminná alakítod?')">
                                                    <i class="bi bi-arrow-up-circle"></i>
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                        
                                        <!-- Törlés -->
                                        <a href="?delete=<?php echo $user['id']; ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Biztosan törölni szeretnéd ezt a felhasználót?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4">
                <div class="alert alert-info">
                    <strong><i class="bi bi-info-circle"></i> Tipp:</strong>
                    Az új ügyintézők a <a href="register.php" target="_blank">regisztrációs oldalon</a> tudnak regisztrálni.
                    Alapértelmezés szerint "Ügyintéző" szerepkört kapnak, amit itt tudsz módosítani.
                </div>
            </div>
            
            <!-- Felhasználók Színbeállítása -->
            <div class="mt-5">
                <h4 class="mb-4">
                    <i class="bi bi-palette-fill"></i> Felhasználók Színbeállítása
                </h4>
                <p class="text-muted">A felhasználókhoz rendelt színek a megyei listákban a sorok háttérszínét határozzák meg.</p>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Felhasználó Név</th>
                                <th>Szerepkör</th>
                                <th>Jelenlegi Szín</th>
                                <th>Szín Előnézet</th>
                                <th>Műveletek</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($agents as $agent): ?>
                                <tr>
                                    <td><?php echo $agent['id']; ?></td>
                                    <td><strong><?php echo escape($agent['name']); ?></strong></td>
                                    <td>
                                        <?php if ($agent['role'] === 'admin'): ?>
                                            <span class="badge bg-primary">Adminisztrátor</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Ügyintéző</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?php echo escape($agent['color'] ?? '#808080'); ?></code></td>
                                    <td>
                                        <div style="width: 50px; height: 30px; background-color: <?php echo escape($agent['color'] ?? '#808080'); ?>; border: 1px solid #ddd; border-radius: 4px;"></div>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-inline-flex align-items-center gap-2">
                                            <input type="hidden" name="agent_id" value="<?php echo $agent['id']; ?>">
                                            <input type="color" name="color" class="form-control form-control-color" 
                                                   value="<?php echo escape($agent['color'] ?? '#808080'); ?>" 
                                                   title="Válassz színt">
                                            <button type="submit" name="change_color" class="btn btn-sm btn-primary">
                                                <i class="bi bi-check-circle"></i> Mentés
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="alert alert-warning mt-3">
                    <strong><i class="bi bi-exclamation-triangle"></i> Megjegyzés:</strong>
                    A színek 15% átlátszósággal jelennek meg a listákban a jobb olvashatóság érdekében.
                    Ajánlott világos és könnyen megkülönböztethető színeket választani.
                </div>
            </div>
            
            <div class="mt-3">
                <h5>Statisztikák</h5>
                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <h3 class="text-primary"><?php echo count($users); ?></h3>
                                <p class="mb-0">Összes felhasználó</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <h3 class="text-success">
                                    <?php echo count(array_filter($users, fn($u) => $u['role'] === 'admin')); ?>
                                </h3>
                                <p class="mb-0">Adminisztrátor</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <h3 class="text-secondary">
                                    <?php echo count(array_filter($users, fn($u) => $u['role'] === 'agent')); ?>
                                </h3>
                                <p class="mb-0">Ügyintéző</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
