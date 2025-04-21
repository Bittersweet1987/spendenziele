<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Ungültige Anfrage']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['id'])) {
    echo json_encode(['error' => 'Fehlende ID']);
    exit;
}

$id = intval($input['id']);
$mindestbetrag = $input['mindestbetrag']; // kann NULL oder Float sein

try {
    // mindestbetrag == null -> Column = NULL
    // Sonst -> wert
    if (is_null($mindestbetrag)) {
        $stmt = $pdo->prepare("UPDATE ziele SET mindestbetrag = NULL WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        // speichere Float
        $stmt = $pdo->prepare("UPDATE ziele SET mindestbetrag = ? WHERE id = ?");
        $stmt->execute([$mindestbetrag, $id]);
    }
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
