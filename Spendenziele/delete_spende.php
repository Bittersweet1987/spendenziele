<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Kein Zugriff']);
    exit;
}

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id']) || empty($data['id'])) {
    echo json_encode(['success' => false, 'error' => 'Ungültige Spenden-ID']);
    exit;
}

$spende_id = (int)$data['id'];

try {
    // Hole den Betrag und die Ziel der Spende vor dem Löschen
    $stmt = $pdo->prepare("SELECT betrag, ziel FROM spenden WHERE id = ?");
    $stmt->execute([$spende_id]);
    $spende = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$spende) {
        echo json_encode(['success' => false, 'error' => 'Spende nicht gefunden']);
        exit;
    }

    $betrag = $spende['betrag'];
    $ziel = $spende['ziel'];

    // Lösche die Spende
    $stmt = $pdo->prepare("DELETE FROM spenden WHERE id = ?");
    $stmt->execute([$spende_id]);

    // Aktualisiere den Gesamtbetrag der Ziel
    $stmt = $pdo->prepare("UPDATE ziele SET gesamtbetrag = gesamtbetrag - ? WHERE name = ?");
    $stmt->execute([$betrag, $ziel]);

    // Überprüfe, ob die Ziel noch Spenden hat
    $stmt = $pdo->prepare("SELECT SUM(betrag) AS gesamtbetrag FROM spenden WHERE ziel = ?");
    $stmt->execute([$ziel]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row['gesamtbetrag'] === null || $row['gesamtbetrag'] <= 0) {
        // Falls keine Spenden mehr existieren, lösche die Ziel
        $stmt = $pdo->prepare("DELETE FROM ziele WHERE name = ?");
        $stmt->execute([$ziel]);
    }

    echo json_encode(['success' => true, 'message' => 'Spende erfolgreich gelöscht']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Fehler: ' . $e->getMessage()]);
}
?>
