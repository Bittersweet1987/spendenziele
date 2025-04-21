<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['id'])) {
    $stmt = $pdo->prepare("UPDATE ziele SET abgeschlossen = 1 WHERE id = ?");
    if ($stmt->execute([$data['id']])) {
        echo json_encode(["message" => "Ziel wurde als erledigt markiert!"]);
    } else {
        echo json_encode(["error" => "Fehler beim Aktualisieren"]);
    }
} else {
    echo json_encode(["error" => "Ungültige Anfrage"]);
}
