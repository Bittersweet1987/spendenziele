<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    // Starte Transaktion
    $pdo->beginTransaction();

    // Lösche alle Einträge in der Tabelle ziele
    $pdo->exec("TRUNCATE TABLE ziele");

    // Erstelle neue Einträge basierend auf der Tabelle spenden
    $sql = "
        INSERT INTO ziele (ziel, gesamtbetrag)
        SELECT 
            ziel,
            SUM(betrag) as gesamtbetrag
        FROM spenden
        GROUP BY ziel
    ";
    
    $pdo->exec($sql);

    // Commit der Transaktion
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Tabelle ziele wurde erfolgreich neu aufgebaut'
    ]);

} catch (Exception $e) {
    // Bei Fehler Rollback durchführen
    $pdo->rollBack();
    
    echo json_encode([
        'success' => false,
        'error' => 'Fehler beim Neubau der Tabelle: ' . $e->getMessage()
    ]);
} 