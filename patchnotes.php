<?php
require_once 'config.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Patchnotes lekérdezése
$stmt = $pdo->query("
    SELECT p.*, 
           EXISTS(
               SELECT 1 FROM patchnotes_read_status 
               WHERE user_id = $user_id AND patchnote_id = p.id
           ) as is_read
    FROM patchnotes p
    ORDER BY p.created_at DESC
");
$patchnotes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Változásnapló - <?php echo APP_NAME; ?></title>
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
        .patchnote-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border-left: 5px solid #28a745;
            transition: all 0.3s ease;
        }
        .patchnote-card:hover {
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        .patchnote-card.unread {
            border-left-color: #ffc107;
            background: #fffef7;
        }
        .patchnote-card.major {
            border-left-color: #dc3545;
        }
        .version-badge {
            background: #0d6efd;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .major-badge {
            background: #dc3545;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 8px;
        }
        .unread-badge {
            background: #ffc107;
            color: #000;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 8px;
        }
        .patchnote-content {
            white-space: pre-wrap;
            line-height: 1.6;
        }
        .patchnote-meta {
            color: #666;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="header py-3 mb-4">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="mb-0">
                    <i class="bi bi-journal-text text-success"></i> Változásnapló
                </h3>
                <div class="d-flex align-items-center gap-2 gap-md-3 flex-wrap justify-content-end">
                    <a href="index.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Vissza
                    </a>
                    <?php if (isAdmin()): ?>
                        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addPatchnoteModal">
                            <i class="bi bi-plus-circle"></i> Új bejegyzés
                        </button>
                    <?php endif; ?>
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
        <?php if (empty($patchnotes)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Még nincsenek bejegyzések a változásnaplóban.
            </div>
        <?php else: ?>
            <?php foreach ($patchnotes as $note): ?>
                <div class="patchnote-card <?php echo $note['is_read'] ? '' : 'unread'; ?> <?php echo $note['is_major'] ? 'major' : ''; ?>" 
                     data-patchnote-id="<?php echo $note['id']; ?>">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h4 class="mb-2">
                                    <span class="version-badge">v<?php echo escape($note['version']); ?></span>
                                    <?php if ($note['is_major']): ?>
                                        <span class="major-badge">FONTOS</span>
                                    <?php endif; ?>
                                    <?php if (!$note['is_read']): ?>
                                        <span class="unread-badge">ÚJ</span>
                                    <?php endif; ?>
                                </h4>
                                <h5 class="mb-0"><?php echo escape($note['title']); ?></h5>
                            </div>
                            <?php if (isAdmin()): ?>
                                <button class="btn btn-sm btn-outline-danger" onclick="deletePatchnote(<?php echo $note['id']; ?>)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="patchnote-content mb-3">
                            <?php echo nl2br(escape($note['content'])); ?>
                        </div>
                        <div class="patchnote-meta">
                            <i class="bi bi-person"></i> <?php echo escape($note['created_by']); ?> | 
                            <i class="bi bi-calendar"></i> <?php echo date('Y.m.d H:i', strtotime($note['created_at'])); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Új patchnote modal (csak adminoknak) -->
    <?php if (isAdmin()): ?>
    <div class="modal fade" id="addPatchnoteModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Új változásnapló bejegyzés</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addPatchnoteForm">
                        <div class="mb-3">
                            <label for="version" class="form-label">Verzió *</label>
                            <input type="text" class="form-control" id="version" placeholder="pl. 1.2.0" required>
                        </div>
                        <div class="mb-3">
                            <label for="title" class="form-label">Cím *</label>
                            <input type="text" class="form-control" id="title" placeholder="pl. Új chat funkció" required>
                        </div>
                        <div class="mb-3">
                            <label for="content" class="form-label">Tartalom *</label>
                            <textarea class="form-control" id="content" rows="8" placeholder="Részletes leírás a változásokról..." required></textarea>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_major">
                            <label class="form-check-label" for="is_major">
                                Fontos frissítés (popup értesítés)
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Mégse</button>
                    <button type="button" class="btn btn-success" onclick="savePatchnote()">
                        <i class="bi bi-save"></i> Mentés
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const userId = <?php echo $user_id; ?>;

        // Patchnotes olvasottnak jelölése
        function markPatchnotesAsRead() {
            const unreadPatchnotes = document.querySelectorAll('.patchnote-card.unread');
            const patchnoteIds = Array.from(unreadPatchnotes).map(el => el.dataset.patchnoteId);
            
            if (patchnoteIds.length > 0) {
                fetch('patchnotes_api.php?action=mark_read&ids=' + patchnoteIds.join(','));
            }
        }

        // Új patchnote mentése
        function savePatchnote() {
            const version = document.getElementById('version').value.trim();
            const title = document.getElementById('title').value.trim();
            const content = document.getElementById('content').value.trim();
            const is_major = document.getElementById('is_major').checked;

            if (!version || !title || !content) {
                alert('Kérlek töltsd ki az összes mezőt!');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'add_patchnote');
            formData.append('version', version);
            formData.append('title', title);
            formData.append('content', content);
            formData.append('is_major', is_major ? 1 : 0);

            fetch('patchnotes_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Hiba: ' + (data.error || 'Ismeretlen hiba'));
                }
            })
            .catch(error => {
                console.error('Hiba:', error);
                alert('Hiba a mentés során');
            });
        }

        // Patchnote törlése
        function deletePatchnote(id) {
            if (!confirm('Biztosan törölni szeretnéd ezt a bejegyzést?')) return;

            fetch('patchnotes_api.php?action=delete_patchnote&id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Hiba: ' + (data.error || 'Ismeretlen hiba'));
                    }
                });
        }

        // Oldal betöltésekor jelöljük olvasottnak
        setTimeout(markPatchnotesAsRead, 2000);
    </script>
</body>
</html>
