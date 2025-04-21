<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'], $input['password'])) {
        echo json_encode(['error' => 'Fehlende Parameter']);
        exit;
    }
    
    $id = intval($input['id']);
    $newPasswordHash = password_hash($input['password'], PASSWORD_DEFAULT);
    
    try {
        // Überprüfen, ob der Moderator existiert
        $checkStmt = $pdo->prepare("SELECT id FROM moderatoren WHERE id = ?");
        $checkStmt->execute([$id]);
        
        if ($checkStmt->rowCount() === 0) {
            echo json_encode(['error' => 'Moderator-ID existiert nicht.']);
            exit;
        }
        
        // Neues Passwort setzen in der Spalte 'passwort_hash'
        $stmt = $pdo->prepare("UPDATE moderatoren SET passwort_hash = ? WHERE id = ?");
        $stmt->execute([$newPasswordHash, $id]);
        
        echo json_encode(['success' => true, 'message' => 'Passwort erfolgreich geändert']);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Fehler: ' . $e->getMessage()]);
    }
}
?>
