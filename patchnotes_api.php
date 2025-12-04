<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

try {
    switch ($action) {
        case 'add_patchnote':
            if (!isAdmin()) {
                echo json_encode(['success' => false, 'error' => 'Nincs jogosultságod']);
                exit;
            }

            $version = trim($_POST['version'] ?? '');
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $is_major = $_POST['is_major'] ?? 0;

            if (empty($version) || empty($title) || empty($content)) {
                echo json_encode(['success' => false, 'error' => 'Minden mező kitöltése kötelező']);
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO patchnotes (version, title, content, created_by, is_major)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$version, $title, $content, $user_name, $is_major]);

            echo json_encode([
                'success' => true,
                'patchnote_id' => $pdo->lastInsertId()
            ]);
            break;

        case 'delete_patchnote':
            if (!isAdmin()) {
                echo json_encode(['success' => false, 'error' => 'Nincs jogosultságod']);
                exit;
            }

            $id = $_GET['id'] ?? 0;

            $stmt = $pdo->prepare("DELETE FROM patchnotes WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true]);
            break;

        case 'mark_read':
            $ids = $_GET['ids'] ?? '';
            $id_array = explode(',', $ids);

            foreach ($id_array as $patchnote_id) {
                if (!empty($patchnote_id)) {
                    $stmt = $pdo->prepare("
                        INSERT IGNORE INTO patchnotes_read_status (user_id, patchnote_id)
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$user_id, $patchnote_id]);
                }
            }

            echo json_encode(['success' => true]);
            break;

        case 'get_unread_count':
            // Olvasatlan patchnotes-ok száma
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as unread_count
                FROM patchnotes p
                WHERE NOT EXISTS (
                    SELECT 1 FROM patchnotes_read_status
                    WHERE user_id = ? AND patchnote_id = p.id
                )
            ");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch();

            echo json_encode([
                'success' => true,
                'unread_count' => $result['unread_count']
            ]);
            break;

        case 'get_latest_unread':
            // Legújabb olvasatlan major patchnote
            $stmt = $pdo->prepare("
                SELECT p.*
                FROM patchnotes p
                WHERE p.is_major = 1
                  AND NOT EXISTS (
                      SELECT 1 FROM patchnotes_read_status
                      WHERE user_id = ? AND patchnote_id = p.id
                  )
                ORDER BY p.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
            $patchnote = $stmt->fetch();

            echo json_encode([
                'success' => true,
                'patchnote' => $patchnote ?: null
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Érvénytelen művelet']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
