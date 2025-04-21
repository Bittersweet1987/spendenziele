<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// Debugging-Informationen
$debug = [
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'get_params' => $_GET,
    'raw_input' => file_get_contents('php://input'),
    'server_vars' => $_SERVER
];

// Funktion zum Dekodieren der JSON-Daten
function getJsonData() {
    global $debug;
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Nur den donationData-Parameter verwenden
        $input = isset($_GET['donationData']) ? $_GET['donationData'] : null;
        
        // Debugging
        $debug['raw_donationData'] = $input;
        
        if ($input) {
            // URL-dekodieren
            $input = urldecode($input);
            $debug['url_decoded'] = $input;
            
            // JSON dekodieren
            $decoded = json_decode($input, true);
            $debug['json_decode_error'] = json_last_error_msg();
            $debug['decoded_data'] = $decoded;
            
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }
    } else {
        // POST-Anfrage: JSON aus Request-Body lesen
        $inputJSON = file_get_contents('php://input');
        $debug['post_data'] = $inputJSON;
        return json_decode($inputJSON, true);
    }
    return null;
}

// JSON-Daten abrufen
$input = getJsonData();

// Debugging-Ausgabe
$debug['final_input'] = $input;

if (!$input) {
    http_response_code(400);
    echo json_encode([
        "error" => "Ungültige JSON-Daten",
        "debug" => $debug
    ]);
    exit;
}

// Parameter überprüfen
if (!isset($input['username'], $input['betrag'], $input['ziel'])) {
    http_response_code(400);
    echo json_encode([
        "error" => "Fehlende Parameter",
        "received" => $input,
        "debug" => $debug
    ]);
    exit;
}

// Daten vorbereiten
$benutzername = htmlspecialchars($input['username']);
$betrag = floatval($input['betrag']);
$ziel = htmlspecialchars($input['ziel']);

try {
    $pdo->beginTransaction();

    // Spende speichern
    $stmt = $pdo->prepare("INSERT INTO spenden (benutzername, betrag, ziel) VALUES (?, ?, ?)");
    $stmt->execute([$benutzername, $betrag, $ziel]);

    // Prüfe ob das Ziel bereits existiert
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ziele WHERE ziel = ?");
    $stmt->execute([$ziel]);
    $exists = $stmt->fetchColumn();

    if ($exists == 0) {
        // Neues Ziel anlegen (standardmäßig unsichtbar)
        $stmt = $pdo->prepare("
            INSERT INTO ziele (ziel, gesamtbetrag, sichtbar)
            VALUES (?, ?, 0)
        ");
        $stmt->execute([$ziel, $betrag]);
    } else {
        // Bestehendes Ziel aktualisieren
        $stmt = $pdo->prepare("
            UPDATE ziele 
            SET gesamtbetrag = gesamtbetrag + ? 
            WHERE ziel = ?
        ");
        $stmt->execute([$betrag, $ziel]);
    }

    $pdo->commit();
    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Spende gespeichert"
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        "error" => "Fehler beim Speichern",
        "details" => $e->getMessage()
    ]);
}
