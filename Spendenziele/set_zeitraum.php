<?php
session_start();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Nicht autorisiert']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['start'], $input['ende'])) {
        echo json_encode(['error' => 'Fehlende Parameter']);
        exit;
    }
    
    try {
        // Zeitzone Bangkok für die Eingabezeit
        $start = new DateTime($input['start'], new DateTimeZone('Asia/Bangkok'));
        $ende = new DateTime($input['ende'], new DateTimeZone('Asia/Bangkok'));
        
        // In UTC umwandeln für die Speicherung
        $start->setTimezone(new DateTimeZone('UTC'));
        $ende->setTimezone(new DateTimeZone('UTC'));
        
        // Überprüfen, ob ein Zeitraum existiert
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM zeitraum");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            // Falls vorhanden, aktualisieren
            $stmt = $pdo->prepare("UPDATE zeitraum SET start = ?, ende = ? WHERE id = 1");
        } else {
            // Falls nicht vorhanden, neu einfügen
            $stmt = $pdo->prepare("INSERT INTO zeitraum (id, start, ende) VALUES (1, ?, ?)");
        }
        $stmt->execute([$start->format('Y-m-d H:i:s'), $ende->format('Y-m-d H:i:s')]);
        
        echo json_encode(['success' => true, 'message' => 'Zeitraum erfolgreich gespeichert']);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Fehler: ' . $e->getMessage()]);
    }
}
?>