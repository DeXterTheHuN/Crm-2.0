<?php
require_once 'config.php';

// Ha már be van jelentkezve, átirányítás
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    // Validáció
    if (!$username || !$password || !$name) {
        $error = 'A felhasználónév, jelszó és név megadása kötelező!';
    } elseif (strlen($username) < 3) {
        $error = 'A felhasználónév legalább 3 karakter hosszú legyen!';
    } elseif (strlen($password) < 6) {
        $error = 'A jelszó legalább 6 karakter hosszú legyen!';
    } elseif ($password !== $password_confirm) {
        $error = 'A két jelszó nem egyezik!';
    } else {
        // Ellenőrizzük, hogy létezik-e már a felhasználónév
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->fetch()) {
            $error = 'Ez a felhasználónév már foglalt!';
        } else {
            // Regisztráció
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, name, email, role, approved) 
                VALUES (?, ?, ?, ?, 'agent', 0)
            ");
            
            try {
                $stmt->execute([$username, $hashed_password, $name, $email]);
                $success = 'Sikeres regisztráció! A fiókod jóváhagyásra vár. Az adminisztrátor jóváhagyása után bejelentkezhetsz.';
                
                // Átirányítás 10 másodperc után
                header("refresh:5;url=login.php");
            } catch (PDOException $e) {
                $error = 'Hiba történt a regisztráció során. Kérlek, próbáld újra!';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Regisztráció - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .register-card {
            max-width: 500px;
            width: 100%;
        }
    </style>
    <link rel="stylesheet" href="improved_styles.css?v=1.0">
</head>
<body>
    <div class="container">
        <div class="register-card">
            <div class="card shadow-lg">
                <div class="card-body p-5">
                    <h2 class="text-center mb-4"><?php echo APP_NAME; ?></h2>
                    <h5 class="text-center text-muted mb-4">Regisztráció</h5>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo escape($error); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?php echo escape($success); ?>
                            <br><small>Átirányítás a bejelentkezési oldalra...</small>
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="name" class="form-label">Teljes név *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo escape($_POST['name'] ?? ''); ?>" required autofocus>
                            </div>
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Felhasználónév *</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo escape($_POST['username'] ?? ''); ?>" required>
                                <small class="text-muted">Legalább 3 karakter</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">E-mail cím</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo escape($_POST['email'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Jelszó *</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <small class="text-muted">Legalább 6 karakter</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password_confirm" class="form-label">Jelszó megerősítése *</label>
                                <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">Regisztráció</button>
                        </form>
                        
                        <div class="mt-4 text-center">
                            <small class="text-muted">
                                Már van fiókod? <a href="login.php">Bejelentkezés</a>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
