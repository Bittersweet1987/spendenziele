<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Nur POST erlaubt']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$benutzername = $input['benutzername'] ?? '';
$passwort     = $input['passwort']     ?? '';

if (!$benutzername || !$passwort) {
    echo json_encode(['error' => 'Bitte Benutzername und Passwort angeben']);
    exit;
}

try {
    // 1) Admin-Tabelle abfragen
    $stmt = $pdo->prepare("SELECT id, passwort_hash FROM admin WHERE benutzername = ?");
    $stmt->execute([$benutzername]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($passwort, $admin['passwort_hash'])) {
        // Admin gefunden -> redirect
        $_SESSION['admin_id'] = $admin['id'];
        echo json_encode(['success' => true, 'role' => 'admin']);
        exit;
    }

    // 2) Moderator-Tabelle abfragen
    $stmt = $pdo->prepare("SELECT id, passwort_hash, status FROM moderatoren WHERE benutzername = ?");
    $stmt->execute([$benutzername]);
    $mod = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($mod && password_verify($passwort, $mod['passwort_hash'])) {
        if ($mod['status'] !== 'aktiv') {
            echo json_encode(['error' => 'Dein Moderatoren-Account ist inaktiv']);
            exit;
        }
        $_SESSION['moderator_id'] = $mod['id'];
        echo json_encode(['success' => true, 'role' => 'moderator']);
        exit;
    }

    // Falls keiner gefunden
    echo json_encode(['error' => 'Falscher Benutzername oder Passwort']);
} catch (Exception $e) {
    echo json_encode(['error' => 'Fehler: ' . $e->getMessage()]);
}
