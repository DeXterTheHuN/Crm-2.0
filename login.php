<?php
require_once 'config.php';
require_once 'audit_helper.php';

// Ha már be van jelentkezve, átirányítás
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT id, username, password, name, role, approved FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Ellenőrizzük a jóváhagyást
            if ($user['approved'] == 0) {
                $error = 'A fiókod még nincs jóváhagyva. Kérlek, várd meg az adminisztrátor jóváhagyását!';
                // Sikertelen bejelentkezés logolása (nem jóváhagyott)
                logAudit($pdo, 'LOGIN_BLOCKED', 'users', $user['id'], null, ['reason' => 'not_approved']);
            } else {
                // Sikeres bejelentkezés
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];

                // Utolsó bejelentkezés frissítése
                $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);

                // Sikeres bejelentkezés logolása
                logLogin($pdo, $user['id'], true);

                redirect('index.php');
            }
        } else {
            $error = 'Hibás felhasználónév vagy jelszó!';
            // Sikertelen bejelentkezés logolása
            if ($user) {
                logLogin($pdo, $user['id'], false);
            } else {
                logAudit($pdo, 'LOGIN_FAILED', 'users', null, null, ['username' => $username, 'reason' => 'user_not_found']);
            }
        }
    } else {
        $error = 'Kérlek, töltsd ki az összes mezőt!';
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bejelentkezés - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            max-width: 400px;
            width: 100%;
        }
    </style>
    <link rel="stylesheet" href="improved_styles.css?v=1.0">
</head>
<body>
    <div class="container">
        <div class="login-card">
            <div class="card shadow-lg">
                <div class="card-body p-5">
                    <h2 class="text-center mb-4"><?php echo APP_NAME; ?></h2>
                    <h5 class="text-center text-muted mb-4">Bejelentkezés</h5>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo escape($error); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">Felhasználónév</label>
                            <input type="text" class="form-control" id="username" name="username" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Jelszó</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Bejelentkezés</button>
                    </form>
                    
                    <div class="mt-4 text-center">
                        <small class="text-muted">
                            Nincs még fiókod? <a href="register.php">Regisztráció</a>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
