<?php

require_once __DIR__ . '/config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Nicht autorisiert']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['altes_passwort'], $input['neues_passwort'])) {
        echo json_encode(['error' => 'Fehlende Parameter']);
        exit;
    }
    
    $admin_id = $_SESSION['admin_id'];
    $altes_passwort = $input['altes_passwort'];
    $neues_passwort = password_hash($input['neues_passwort'], PASSWORD_DEFAULT);
    
    try {
        // Altes Passwort prüfen
        $stmt = $pdo->prepare("SELECT passwort_hash FROM admin WHERE id = ?");
        $stmt->execute([$admin_id]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$admin || !password_verify($altes_passwort, $admin['passwort_hash'])) {
            echo json_encode(['error' => 'Altes Passwort ist falsch']);
            exit;
        }
        
        // Neues Passwort setzen
        $stmt = $pdo->prepare("UPDATE admin SET passwort_hash = ? WHERE id = ?");
        $stmt->execute([$neues_passwort, $admin_id]);
        
        echo json_encode(['success' => true, 'message' => 'Passwort erfolgreich geändert']);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Fehler: ' . $e->getMessage()]);
    }
}

?>