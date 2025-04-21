<?php
session_start();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

// Admin eingeloggt?
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Nicht eingeloggt']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Ungültige Anfrage']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['oldPass'], $input['newPass'])) {
    echo json_encode(['error' => 'Fehlende Parameter']);
    exit;
}

$oldPass = $input['oldPass'];
$newPass = $input['newPass'];

// hole admin in DB
$adminID = $_SESSION['admin_id'];
$stmt = $pdo->prepare("SELECT passwort_hash FROM admin WHERE id = ?");
$stmt->execute([$adminID]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    echo json_encode(['error' => 'Admin nicht gefunden']);
    exit;
}

// check old pass
if (!password_verify($oldPass, $admin['passwort_hash'])) {
    echo json_encode(['error' => 'Altes Passwort ist falsch']);
    exit;
}

// set new pass
$newHash = password_hash($newPass, PASSWORD_DEFAULT);
$upd = $pdo->prepare("UPDATE admin SET passwort_hash = ? WHERE id = ?");
$upd->execute([$newHash, $adminID]);

echo json_encode(['success' => true]);
