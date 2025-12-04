<?php
require_once 'config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/chat.css?v=1.0">
</head>
<body>
    <div class="header py-3 mb-4">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="mb-0">
                    <i class="bi bi-chat-dots-fill text-primary"></i> Chat
                </h3>
                <div class="d-flex align-items-center gap-2 gap-md-3 flex-wrap justify-content-end">
                    <a href="index.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Vissza
                    </a>
                    <a href="profile.php" class="text-decoration-none text-dark">
                        <i class="bi bi-person-circle"></i> <?php echo escape($_SESSION['name']); ?>
                        <?php if (isAdmin()): ?>
                            <span class="badge bg-primary ms-2">Admin</span>
                        <?php endif; ?>
                    </a>
                    <a href="logout.php" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-box-arrow-right"></i> <span class="d-none d-md-inline">Kijelentkezés</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="chat-container">
            <div class="chat-messages" id="chatMessages">
                <div class="text-center text-muted py-4">
                    <i class="bi bi-chat-dots fs-1"></i>
                    <p>Üzenetek betöltése...</p>
                </div>
            </div>
            <div class="chat-input-area">
                <div class="d-flex gap-2">
                    <textarea
                        class="form-control chat-input"
                        id="messageInput"
                        rows="1"
                        placeholder="Írj egy üzenetet..."
                        onkeypress="if(event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); sendMessage(); }"
                    ></textarea>
                    <button class="btn btn-primary send-btn" onclick="sendMessage()">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // PHP változók átadása a JS-nek
        const userId = <?php echo $user_id; ?>;
        const userName = '<?php echo escape($user_name); ?>';
    </script>
    <script src="assets/js/chat.js?v=1.0"></script>
</body>
</html>
