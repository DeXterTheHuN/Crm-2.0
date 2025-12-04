<?php
require_once 'config.php';
session_write_close();

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

if (!isset($_SESSION['user_id'])) {
    echo "event: error\ndata: unauthorized\n\n";
    exit;
}

$userId = $_SESSION['user_id'];
$lastCheck = date('Y-m-d H:i:s', strtotime('-30 seconds'));

while (true) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0 AND created_at > ?");
    $stmt->execute([$userId, $lastCheck]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "data: " . json_encode(['count' => $result['count']]) . "\n\n";
    ob_flush();
    flush();
    
    sleep(3);
    
    if (connection_aborted()) break;
}
