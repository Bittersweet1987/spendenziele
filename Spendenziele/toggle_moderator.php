<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'], $input['newStatus'])) {
        echo json_encode(['error' => 'Fehlende Parameter']);
        exit;
    }
    
    $id = intval($input['id']);
    $newStatus = $input['newStatus'];
    
    // Überprüfen ob der neue Status gültig ist
    if (!in_array($newStatus, ['aktiv', 'inaktiv'])) {
        echo json_encode(['error' => 'Ungültiger Status']);
        exit;
    }
    
    try {
        // Überprüfen ob der Moderator existiert
        $checkStmt = $pdo->prepare("SELECT id FROM moderatoren WHERE id = ?");
        $checkStmt->execute([$id]);
        
        if ($checkStmt->rowCount() === 0) {
            echo json_encode(['error' => 'Moderator nicht gefunden']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE moderatoren SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Status erfolgreich geändert']);
        } else {
            echo json_encode(['error' => 'Kein Update durchgeführt. Überprüfe die ID.']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => 'Fehler: ' . $e->getMessage()]);
    }
}
?>
