<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['id'], $input['neue_ziel'])) {
        echo json_encode(['error' => 'Fehlende Parameter']);
        exit;
    }

    $id = intval($input['id']);
    $neue_ziel = htmlspecialchars($input['neue_ziel']);

    if (empty($neue_ziel)) {
        echo json_encode(['error' => 'Das Ziel darf nicht leer sein']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Alte ziel und Betrag ermitteln
        $stmt = $pdo->prepare("SELECT betrag, ziel FROM spenden WHERE id = ?");
        $stmt->execute([$id]);
        $spende = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($spende) {
            $betrag = $spende['betrag'];
            $alte_ziel = $spende['ziel'];

            // Spende aktualisieren
            $stmt = $pdo->prepare("UPDATE spenden SET ziel = ? WHERE id = ?");
            $stmt->execute([$neue_ziel, $id]);

            // Alten Betrag von der alten ziel abziehen
            $stmt = $pdo->prepare("UPDATE ziele SET gesamtbetrag = gesamtbetrag - ? WHERE ziel = ?");
            $stmt->execute([$betrag, $alte_ziel]);

            // Neuen Betrag zur neuen ziel hinzufügen
            $stmt = $pdo->prepare("
                INSERT INTO ziele (ziel, gesamtbetrag)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE gesamtbetrag = gesamtbetrag + ?
            ");
            $stmt->execute([$neue_ziel, $betrag, $betrag]);

            // Leere Ziele löschen
            $stmt = $pdo->prepare("DELETE FROM ziele WHERE gesamtbetrag = 0 AND ziel NOT IN (SELECT DISTINCT ziel FROM spenden)");
            $stmt->execute();
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Spende aktualisiert & Ziele synchronisiert']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Fehler beim Aktualisieren', 'details' => $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Ungültige Anfrage']);
}
