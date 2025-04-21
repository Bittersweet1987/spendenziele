<?php

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['benutzername'], $input['passwort'])) {
        echo json_encode(['error' => 'Fehlende Parameter']);
        exit;
    }
    
    $benutzername = htmlspecialchars($input['benutzername']);
    $passwort = password_hash($input['passwort'], PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO moderatoren (benutzername, passwort_hash, status) VALUES (?, ?, 'inaktiv')");
        $stmt->execute([$benutzername, $passwort]);
        echo json_encode(['success' => true, 'message' => 'Moderator hinzugefügt']);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Fehler: ' . $e->getMessage()]);
    }
}

?>
