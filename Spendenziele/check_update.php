<?php
session_start();

// Setze Header für JSON-Antwort
header('Content-Type: application/json');

// Prüfe ob ein Update läuft
$commitFile = __DIR__ . '/last_commit.txt';
$currentCommit = file_exists($commitFile) ? trim(file_get_contents($commitFile)) : '';

// Hole den letzten bekannten Commit aus der Session
$sessionCommit = $_SESSION['current_commit'] ?? '';
$updateTimestamp = $_SESSION['update_timestamp'] ?? 0;

// Wenn der aktuelle Commit mit dem Session-Commit übereinstimmt
// und der Zeitstempel nicht älter als 30 Sekunden ist,
// dann wurde das Update erfolgreich durchgeführt
if ($currentCommit === $sessionCommit && $updateTimestamp > (time() - 30)) {
    echo json_encode(['status' => 'updated']);
} else {
    echo json_encode(['status' => 'pending']);
} 