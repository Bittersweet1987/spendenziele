<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// Nur Admins und Moderatoren dürfen die Sichtbarkeit ändern
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['moderator_id'])) {
    echo json_encode(['error' => 'Nicht autorisiert']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id'])) {
    echo json_encode(['error' => 'Keine Ziel-ID angegeben']);
    exit;
}

try {
    // Aktuelle Sichtbarkeit umschalten
    $stmt = $pdo->prepare("UPDATE ziele SET sichtbar = NOT sichtbar WHERE id = ?");
    $stmt->execute([$input['id']]);
    
    // Neue Sichtbarkeit zurückgeben
    $stmt = $pdo->prepare("SELECT sichtbar FROM ziele WHERE id = ?");
    $stmt->execute([$input['id']]);
    $sichtbar = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Sichtbarkeit aktualisiert',
        'sichtbar' => (bool)$sichtbar
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Fehler: ' . $e->getMessage()]);
} 