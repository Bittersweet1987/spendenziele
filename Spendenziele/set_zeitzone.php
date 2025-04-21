<?php
// Fehleranzeige aktivieren für Debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/config.php';

// Überprüfung der Admin-Session
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Nicht autorisiert']);
    exit;
}

// Prüfen, ob POST-Daten vorhanden sind
$rawPostData = file_get_contents('php://input');
$input = json_decode($rawPostData, true);

if (!$input) {
    echo json_encode([
        'error' => 'Fehler beim JSON-Parsing: ' . json_last_error_msg(),
        'rawData' => $rawPostData
    ]);
    exit;
}

// Prüfen, ob die Zeitzone korrekt gesendet wurde
if (!isset($input['timezone']) || !in_array($input['timezone'], DateTimeZone::listIdentifiers())) {
    echo json_encode(['error' => 'Ungültige Zeitzone', 'received' => $input['timezone']]);
    exit;
}

$timezone = $input['timezone'];

try {
    // Prüfen, ob die Zeitzone bereits existiert
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM einstellungen WHERE schluessel = 'zeitzone'");
    $stmt->execute();
    $exists = $stmt->fetchColumn();

    if ($exists) {
        $stmt = $pdo->prepare("UPDATE einstellungen SET wert = ? WHERE schluessel = 'zeitzone'");
    } else {
        $stmt = $pdo->prepare("INSERT INTO einstellungen (schluessel, wert) VALUES ('zeitzone', ?)");
    }

    if (!$stmt->execute([$timezone])) {
        echo json_encode(['error' => 'SQL-Fehler: ' . implode(" | ", $stmt->errorInfo())]);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Zeitzone erfolgreich gespeichert', 'timezone' => $timezone]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Fehler: ' . $e->getMessage()]);
    exit;
}
?>
