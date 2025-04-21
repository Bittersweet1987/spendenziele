<?php
require_once 'config.php';
header('Content-Type: application/json');

// Überprüfen ob ein Administrator eingeloggt ist
session_start();
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Nicht autorisiert']);
    exit;
}

if (!isset($_POST['ziel_id'])) {
    echo json_encode(['success' => false, 'error' => 'Keine Ziel-ID angegeben']);
    exit;
}

$ziel_id = intval($_POST['ziel_id']);

try {
    $pdo->beginTransaction();

    // Zuerst alle zugehörigen Spenden löschen
    $stmt = $pdo->prepare("DELETE FROM spenden WHERE ziel = (SELECT name FROM ziele WHERE id = ?)");
    $stmt->execute([$ziel_id]);

    // Dann das Ziel selbst löschen
    $stmt = $pdo->prepare("DELETE FROM ziele WHERE id = ?");
    $stmt->execute([$ziel_id]);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler: ' . $e->getMessage()]);
}
?> 