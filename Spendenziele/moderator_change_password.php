<?php
session_start();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['moderator_id'])) {
    echo json_encode(['error' => 'Nicht eingeloggt']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['oldPass'], $input['newPass'])) {
        echo json_encode(['error' => 'Fehlende Parameter']);
        exit;
    }

    $oldPass = $input['oldPass'];
    $newPass = $input['newPass'];
    $mod_id = $_SESSION['moderator_id'];

    // Aktuelles Passwort aus DB holen
    $stmt = $pdo->prepare("SELECT passwort_hash FROM moderatoren WHERE id = ?");
    $stmt->execute([$mod_id]);
    $moderator = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$moderator) {
        echo json_encode(['error' => 'Moderator nicht gefunden']);
        exit;
    }

    // Überprüfung altes Passwort
    if (!password_verify($oldPass, $moderator['passwort_hash'])) {
        echo json_encode(['error' => 'Altes Passwort ist falsch']);
        exit;
    }

    // Neues Passwort setzen
    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE moderatoren SET passwort_hash = ? WHERE id = ?");
    $stmt->execute([$newHash, $mod_id]);

    echo json_encode(['success' => true, 'message' => 'Passwort erfolgreich geändert']);
}
