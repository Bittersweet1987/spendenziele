<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        echo json_encode(['error' => 'Fehlende Parameter']);
        exit;
    }
    
    $id = intval($input['id']);
    
    try {
        // Überprüfen, ob der Moderator existiert
        $checkStmt = $pdo->prepare("SELECT id FROM moderatoren WHERE id = ?");
        $checkStmt->execute([$id]);
        
        if ($checkStmt->rowCount() === 0) {
            echo json_encode(['error' => 'Moderator-ID existiert nicht.']);
            exit;
        }
        
        // Moderator löschen
        $stmt = $pdo->prepare("DELETE FROM moderatoren WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Moderator erfolgreich gelöscht']);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Fehler: ' . $e->getMessage()]);
    }
}
?>
