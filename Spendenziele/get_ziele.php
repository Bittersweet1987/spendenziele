<?php
require_once __DIR__ . '/config.php';

try {
    $stmt = $pdo->query("SELECT * FROM ziele ORDER BY gesamtbetrag DESC");
    $ziele = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // JSONP Callback
    $callback = isset($_GET['callback']) ? $_GET['callback'] : false;
    $json = json_encode($ziele);
    
    if ($callback) {
        header('Content-Type: application/javascript');
        echo $callback . '(' . $json . ');';
    } else {
        header('Content-Type: application/json');
        echo $json;
    }
} catch (Exception $e) {
    $error = ['error' => 'Fehler beim Abrufen der Ziele'];
    $callback = isset($_GET['callback']) ? $_GET['callback'] : false;
    if ($callback) {
        header('Content-Type: application/javascript');
        echo $callback . '(' . json_encode($error) . ');';
    } else {
        header('Content-Type: application/json');
        echo json_encode($error);
    }
}