<?php

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['benutzername'], $input['betrag'], $input['ziel'])) {
        echo json_encode(['error' => 'Fehlende Parameter']);
        exit;
    }
    
    $benutzername = htmlspecialchars($input['benutzername']);
    $betrag = floatval($input['betrag']);
    $ziel = htmlspecialchars($input['ziel']);
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO spenden (benutzername, betrag, ziel) VALUES (?, ?, ?)");
        $stmt->execute([$benutzername, $betrag, $ziel]);
        
        $stmt = $pdo->prepare("INSERT INTO ziele (ziel, gesamtbetrag) VALUES (?, ?) ON DUPLICATE KEY UPDATE gesamtbetrag = gesamtbetrag + ?");
        $stmt->execute([$ziel, $betrag, $betrag]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Spende gespeichert']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Fehler beim Speichern: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Ungültige Anfrage']);
}
