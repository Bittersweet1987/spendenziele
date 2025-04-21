<?php
require_once __DIR__ . '/config.php';

$data = json_decode(file_get_contents('php://input'), true);
$timezone = $data['timezone'] ?? null;
$logoutRedirect = $data['logout_redirect'] ?? null;

try {
    if ($timezone) {
        $stmt = $pdo->prepare("REPLACE INTO einstellungen (schluessel, wert) VALUES ('zeitzone', ?)");
        $stmt->execute([$timezone]);
    }

    if ($logoutRedirect && in_array($logoutRedirect, ['spendenziele.php', 'spendenranking.php'])) {
        $stmt = $pdo->prepare("REPLACE INTO einstellungen (schluessel, wert) VALUES ('logout_redirect', ?)");
        $stmt->execute([$logoutRedirect]);
    }

    echo json_encode(["success" => true, "message" => "Einstellungen gespeichert."]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Fehler: " . $e->getMessage()]);
}
?>
