<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['admin_id'])) {
    die("<h3>Zugriff verweigert. Bitte <a href='admin_login.php'>einloggen</a>.</h3>");
}

// Funktion zum Abrufen der aktuellen Datenbankstruktur
function getCurrentDatabaseStructure($pdo) {
    $structure = [];
    
    // Hole alle Tabellen
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        // Hole die CREATE TABLE Anweisung
        $createTable = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        $structure[$table] = $createTable['Create Table'];
        
        // Hole detaillierte Spalteninformationen
        $columns = $pdo->query("SHOW FULL COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        $structure[$table . '_columns'] = $columns;
        
        // Hole Indexinformationen
        $indexes = $pdo->query("SHOW INDEX FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        $structure[$table . '_indexes'] = $indexes;
    }
    
    return $structure;
}

// Funktion zum Abrufen der GitHub-Struktur
function getGitHubStructure() {
    $githubBaseUrl = 'https://raw.githubusercontent.com/Bittersweet1987/spendenziele/main/Datenbank/';
    
    error_log("Debug - Versuche GitHub-Struktur zu laden von: " . $githubBaseUrl . 'structure.sql');
    
    // Setze Kontext-Optionen für die GitHub-Anfrage
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: PHP'
            ]
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ];
    $context = stream_context_create($opts);
    error_log("Debug - Stream Kontext erstellt mit Optionen: " . print_r($opts, true));
    
    // Versuche die structure.sql zu laden
    $structureSQL = @file_get_contents($githubBaseUrl . 'structure.sql', false, $context);
    
    if ($structureSQL === false) {
        $error = error_get_last();
        error_log("Debug - Fehler beim Laden der Datei: " . print_r($error, true));
        throw new Exception("Konnte Datenbankstruktur nicht von GitHub laden. URL: " . $githubBaseUrl . 'structure.sql' . 
                          "\nFehlerdetails: " . ($error ? $error['message'] : 'Unbekannter Fehler'));
    }
    
    error_log("Debug - Geladener Inhalt: " . substr($structureSQL, 0, 500) . "...");
    
    // Überprüfe ob der Inhalt tatsächlich SQL ist
    if (stripos($structureSQL, 'CREATE TABLE') === false) {
        error_log("Debug - Geladener Inhalt enthält kein 'CREATE TABLE'. Erste 1000 Zeichen: " . substr($structureSQL, 0, 1000));
        throw new Exception("Die geladene Datei enthält keine SQL CREATE TABLE Anweisungen. Erhaltener Inhalt: " . substr($structureSQL, 0, 100) . "...");
    }
    
    error_log("Debug - SQL-Struktur erfolgreich geladen");
    return $structureSQL;
}

// Funktion zum Vergleichen der Strukturen
function compareStructures($currentStructure, $githubSQL) {
    $differences = [];
    
    // Extrahiere CREATE TABLE Anweisungen aus GitHub SQL
    preg_match_all("/CREATE TABLE `([^`]+)`[^;]+;/i", $githubSQL, $matches);
    
    if (!isset($matches[0]) || !isset($matches[1])) {
        throw new Exception("Konnte keine Tabellenstrukturen aus GitHub SQL extrahieren");
    }
    
    $githubTables = array_combine($matches[1], $matches[0]);
    
    // Vergleiche existierende Tabellen
    foreach ($currentStructure as $tableName => $tableData) {
        // Überspringe die _columns und _indexes Einträge
        if (strpos($tableName, '_columns') !== false || strpos($tableName, '_indexes') !== false) {
            continue;
        }
        
        if (!isset($githubTables[$tableName])) {
            $differences[] = "Tabelle '$tableName' existiert lokal, aber nicht in GitHub";
            continue;
        }
        
        // Normalisiere die CREATE TABLE Anweisungen für den Vergleich
        $localCreate = preg_replace('/AUTO_INCREMENT=\d+/', '', $tableData);
        $localCreate = preg_replace('/\s+/', ' ', trim($localCreate));
        
        $githubCreate = preg_replace('/AUTO_INCREMENT=\d+/', '', $githubTables[$tableName]);
        $githubCreate = preg_replace('/\s+/', ' ', trim($githubCreate));
        
        if ($localCreate !== $githubCreate) {
            $differences[] = "Struktur der Tabelle '$tableName' unterscheidet sich";
            
            // Detaillierte Unterschiede anzeigen
            $localColumns = $currentStructure[$tableName . '_columns'];
            $githubColumns = extractColumns($githubCreate);
            
            foreach ($localColumns as $column) {
                $columnName = $column['Field'];
                if (!isset($githubColumns[$columnName])) {
                    $differences[] = "- Spalte '$columnName' in Tabelle '$tableName' existiert lokal, aber nicht in GitHub";
                } elseif ($githubColumns[$columnName] !== $column['Type']) {
                    $differences[] = "- Spalte '$columnName' in Tabelle '$tableName' hat unterschiedlichen Typ (Lokal: {$column['Type']}, GitHub: {$githubColumns[$columnName]})";
                }
            }
            
            foreach ($githubColumns as $columnName => $columnType) {
                $found = false;
                foreach ($localColumns as $column) {
                    if ($column['Field'] === $columnName) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $differences[] = "- Spalte '$columnName' in Tabelle '$tableName' existiert in GitHub, aber nicht lokal";
                }
            }
        }
    }
    
    // Prüfe auf neue Tabellen in GitHub
    foreach ($githubTables as $tableName => $createStatement) {
        if (!isset($currentStructure[$tableName])) {
            $differences[] = "Neue Tabelle '$tableName' in GitHub gefunden";
        }
    }
    
    return $differences;
}

// Hilfsfunktion zum Extrahieren der Spalteninformationen aus CREATE TABLE
function extractColumns($createStatement) {
    $columns = [];
    if (preg_match('/\((.*)\)/s', $createStatement, $matches)) {
        $columnDefinitions = explode(',', $matches[1]);
        foreach ($columnDefinitions as $definition) {
            $definition = trim($definition);
            if (preg_match('/`([^`]+)`\s+([^,\s]+)/', $definition, $matches)) {
                $columns[$matches[1]] = $matches[2];
            }
        }
    }
    return $columns;
}

// Hauptlogik für die Update-Seite
$error = '';
$differences = [];

if (isset($_POST['check_structure'])) {
    try {
        error_log("Debug - Starte Strukturprüfung");
        $currentStructure = getCurrentDatabaseStructure($pdo);
        error_log("Debug - Aktuelle Datenbankstruktur geladen: " . print_r(array_keys($currentStructure), true));
        
        $githubSQL = getGitHubStructure();
        error_log("Debug - GitHub-Struktur geladen, Länge: " . strlen($githubSQL));
        
        $differences = compareStructures($currentStructure, $githubSQL);
        error_log("Debug - Strukturvergleich abgeschlossen. Gefundene Unterschiede: " . print_r($differences, true));
    } catch (Exception $e) {
        error_log("Debug - Fehler aufgetreten: " . $e->getMessage() . "\nStacktrace: " . $e->getTraceAsString());
        $error = "Fehler bei der Prüfung: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update - System Update</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .steps {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        .step {
            padding: 10px 20px;
            margin-right: 4px;
            border-radius: 4px 4px 0 0;
            cursor: pointer;
            background-color: #e0e0e0;
        }
        .step.active {
            background-color: #4CAF50;
            color: white;
        }
        .error-message {
            color: #dc3545;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .success-message {
            color: #28a745;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin: 4px;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background-color: #4CAF50;
            color: white;
        }
        .btn-primary:hover {
            background-color: #45a049;
        }
        .differences-list {
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        .differences-list li {
            margin: 8px 0;
            padding-left: 20px;
            position: relative;
        }
        .differences-list li:before {
            content: "•";
            position: absolute;
            left: 0;
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="steps">
            <div class="step active">1. Datenbank Update</div>
            <div class="step">2. Dateien Update</div>
            <div class="step">3. Übersicht</div>
        </div>

        <h2>Datenbank Update</h2>
        <p>Klicken Sie auf "Prüfen", um die Datenbankstruktur mit der aktuellen Version zu vergleichen.</p>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($differences)): ?>
            <div class="differences-list">
                <h3>Gefundene Unterschiede:</h3>
                <ul>
                    <?php foreach ($differences as $diff): ?>
                        <li><?php echo htmlspecialchars($diff); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post">
            <button type="submit" name="check_structure" class="btn btn-primary">Prüfen</button>
        </form>
    </div>
</body>
</html> 