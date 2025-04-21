<?php

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT id, benutzername, betrag, ziel, datum FROM spenden ORDER BY datum DESC");
    $spenden = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($spenden);
} catch (Exception $e) {
    echo json_encode(['error' => 'Fehler beim Abrufen: ' . $e->getMessage()]);
}
