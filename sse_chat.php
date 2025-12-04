<?php
require_once 'config.php';
session_write_close();

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

$recipientId = $_GET['recipient_id'] ?? null;
if (!isset($_SESSION['user_id']) || !$recipientId) {
    echo "event: error\ndata: invalid\n\n";
    exit;
}

$lastId = 0;

while (true) {
    $stmt = $pdo->prepare("SELECT * FROM messages WHERE id > ? AND ((sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?)) ORDER BY id ASC");
    $stmt->execute([$lastId, $_SESSION['user_id'], $recipientId, $recipientId, $_SESSION['user_id']]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($messages)) {
        $lastId = end($messages)['id'];
        echo "data: " . json_encode($messages) . "\n\n";
    }
    
    ob_flush();
    flush();
    sleep(2);
    
    if (connection_aborted()) break;
}
