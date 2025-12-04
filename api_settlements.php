<?php
require_once 'config.php';

header('Content-Type: application/json');

$county_id = $_GET['county_id'] ?? 0;

$stmt = $pdo->prepare("SELECT id, name FROM settlements WHERE county_id = ? ORDER BY name");
$stmt->execute([$county_id]);
$settlements = $stmt->fetchAll();

echo json_encode($settlements);
?>
