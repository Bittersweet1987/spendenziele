<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT start, ende FROM zeitraum LIMIT 1");
    $zeitraum = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($zeitraum) {
        // In UTC gespeicherte Zeit abrufen
        $startUTC = new DateTime($zeitraum['start'], new DateTimeZone('UTC'));
        $endeUTC = new DateTime($zeitraum['ende'], new DateTimeZone('UTC'));
        
        // In Berliner Zeitzone umwandeln (automatische Sommer-/Winterzeit) 
        $startBerlin = $startUTC->setTimezone(new DateTimeZone('Europe/Berlin'));
        $endeBerlin = $endeUTC->setTimezone(new DateTimeZone('Europe/Berlin'));
        
        echo json_encode([
            'start' => $startBerlin->format('d.m.Y H:i') . ' Uhr',
            'ende' => $endeBerlin->format('d.m.Y H:i') . ' Uhr'
        ]);
    } else {
        echo json_encode(['error' => 'Kein Zeitraum gesetzt']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Fehler: ' . $e->getMessage()]);
}
?>