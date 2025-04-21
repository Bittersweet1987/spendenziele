<?php
session_start();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Nicht autorisiert']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Alle Spenden und Ziele zurücksetzen
        $pdo->query("TRUNCATE TABLE spenden");
        $pdo->query("TRUNCATE TABLE ziele");
        
        echo json_encode(['success' => true, 'message' => 'Alle Spenden und Ziele wurden erfolgreich zurückgesetzt']);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Fehler: ' . $e->getMessage()]);
    }
}
?>
