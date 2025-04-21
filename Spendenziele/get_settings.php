<?php
session_start();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Nicht autorisiert']);
    exit;
}

$stmt = $pdo->query("SELECT schluessel, wert FROM einstellungen");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

echo json_encode($settings);
?>
