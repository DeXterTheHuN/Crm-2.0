<?php
require_once 'config.php';
requireLogin();

$error = '';
$success = '';

// Felhasználó adatainak lekérdezése
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    die('Felhasználó nem található!');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $new_password_confirm = $_POST['new_password_confirm'] ?? '';
    
    // Validáció
    if (!$name) {
        $error = 'A név megadása kötelező!';
    } else {
        // Ha jelszót is változtatni akar
        if ($new_password) {
            if (!$current_password) {
                $error = 'Add meg a jelenlegi jelszavadat!';
            } elseif (!password_verify($current_password, $user['password'])) {
                $error = 'Hibás jelenlegi jelszó!';
            } elseif (strlen($new_password) < 6) {
                $error = 'Az új jelszó legalább 6 karakter hosszú legyen!';
            } elseif ($new_password !== $new_password_confirm) {
                $error = 'Az új jelszavak nem egyeznek!';
            } else {
                // Jelszó frissítése
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?");
                $stmt->execute([$name, $email, $hashed_password, $_SESSION['user_id']]);
                
                $_SESSION['name'] = $name;
                $success = 'Profil és jelszó sikeresen frissítve!';
                
                // Frissítjük a user változót
                $user['name'] = $name;
                $user['email'] = $email;
            }
        } else {
            // Csak név és email frissítése
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
            $stmt->execute([$name, $email, $_SESSION['user_id']]);
            
            $_SESSION['name'] = $name;
            $success = 'Profil sikeresen frissítve!';
            
            // Frissítjük a user változót
            $user['name'] = $name;
            $user['email'] = $email;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - <?php echo APP_NAME; ?></title>
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
        .profile-card {
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

        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="profile-card p-4">
                    <h4 class="mb-4">
                        <i class="bi bi-person-circle"></i> Profil Szerkesztése
                    </h4>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo escape($error); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo escape($success); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-4">
                            <h5 class="border-bottom pb-2">Alapadatok</h5>
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Felhasználónév</label>
                                <input type="text" class="form-control" id="username" 
                                       value="<?php echo escape($user['username']); ?>" disabled>
                                <small class="text-muted">A felhasználónév nem változtatható</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Teljes név *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo escape($user['name']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">E-mail cím</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo escape($user['email'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Szerepkör</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo $user['role'] === 'admin' ? 'Adminisztrátor' : 'Ügyintéző'; ?>" disabled>
                                <small class="text-muted">A szerepkört csak az adminisztrátor változtathatja</small>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h5 class="border-bottom pb-2">Jelszó Változtatása</h5>
                            <p class="text-muted small">Hagyd üresen, ha nem szeretnéd megváltoztatni a jelszót</p>
                            
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Jelenlegi jelszó</label>
                                <input type="password" class="form-control" id="current_password" name="current_password">
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">Új jelszó</label>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                                <small class="text-muted">Legalább 6 karakter</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password_confirm" class="form-label">Új jelszó megerősítése</label>
                                <input type="password" class="form-control" id="new_password_confirm" name="new_password_confirm">
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Mentés
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary">
                                Mégse
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
