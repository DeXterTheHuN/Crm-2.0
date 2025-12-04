<?php
require_once 'config.php';

$errorCode = $_GET['code'] ?? '404';
$errors = [
    '400' => ['title' => 'Hibás kérés', 'message' => 'A kérés nem megfelelő formátumú.'],
    '403' => ['title' => 'Hozzáférés megtagadva', 'message' => 'Nincs jogosultságod ehhez az oldalhoz.'],
    '404' => ['title' => 'Nem található', 'message' => 'A keresett oldal nem létezik.'],
    '500' => ['title' => 'Szerverhiba', 'message' => 'Váratlan hiba történt. Kérjük, próbáld újra később.'],
    'county_not_found' => ['title' => 'Megye nem található', 'message' => 'A keresett megye nem létezik.'],
    'client_not_found' => ['title' => 'Ügyfél nem található', 'message' => 'A keresett ügyfél nem létezik.'],
];

$error = $errors[$errorCode] ?? $errors['404'];
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $error['title']; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 text-center">
                <div class="card shadow-sm">
                    <div class="card-body p-5">
                        <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 4rem;"></i>
                        <h1 class="mt-4"><?php echo $error['title']; ?></h1>
                        <p class="text-muted mb-4"><?php echo $error['message']; ?></p>
                        <a href="index.php" class="btn btn-primary">
                            <i class="bi bi-house-fill"></i> Vissza a főoldalra
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
