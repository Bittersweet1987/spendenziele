<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

class Updater {
    private $currentStep = 1;
    private $githubBaseUrl = 'https://raw.githubusercontent.com/Bittersweet1987/spendenziele/main/Datenbank/';

    public function run() {
        $this->showHeader("System Update");

        // Bestimme den aktiven Tab basierend auf GET-Parameter
        $activeTab = isset($_GET['step']) ? (int)$_GET['step'] : 1;

        // AJAX-Handler für Datenbankprüfung
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'check_database':
                    $this->checkDatabaseStructure();
                    exit;
                case 'update_database':
                    $this->updateDatabaseStructure();
                    exit;
            }
        }

        // Zeige Tabs
        ?>
        <div class="tab-content <?php echo $activeTab === 1 ? 'active' : ''; ?>" id="tab1">
            <?php $this->showDatabaseTab(); ?>
        </div>
        <?php

        $this->showFooter();
    }

    private function showHeader($title) {
        ?>
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Update - <?php echo htmlspecialchars($title); ?></title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    max-width: 800px;
                    margin: 0 auto;
                    padding: 20px;
                    background-color: #f5f5f5;
                }
                .container {
                    background-color: white;
                    padding: 20px;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                .tabs {
                    display: flex;
                    margin-bottom: 20px;
                    border-bottom: 2px solid #e0e0e0;
                }
                .tab {
                    padding: 10px 20px;
                    margin-right: 4px;
                    border-radius: 4px 4px 0 0;
                    cursor: default;
                    opacity: 0.5;
                }
                .tab.active {
                    background-color: #4CAF50;
                    color: white;
                    opacity: 1;
                }
                .tab.completed {
                    background-color: #81C784;
                    color: white;
                    opacity: 1;
                }
                .tab-content {
                    display: none;
                    padding: 20px;
                }
                .tab-content.active {
                    display: block;
                }
                .button {
                    display: inline-block;
                    padding: 10px 20px;
                    background-color: #4CAF50;
                    color: white;
                    text-decoration: none;
                    border-radius: 4px;
                    border: none;
                    cursor: pointer;
                    font-size: 14px;
                }
                .button:hover {
                    background-color: #45a049;
                }
                .button:disabled {
                    background-color: #cccccc;
                    cursor: not-allowed;
                }
                .button-next {
                    background-color: #2196F3;
                }
                .button-next:hover {
                    background-color: #1976D2;
                }
                .button-update {
                    background-color: #ff9800;
                }
                .button-update:hover {
                    background-color: #f57c00;
                }
                .message {
                    margin: 10px 0;
                    padding: 10px;
                    border-radius: 4px;
                }
                .success {
                    background-color: #e8f5e9;
                    color: #4CAF50;
                    border: 1px solid #4CAF50;
                }
                .error {
                    background-color: #ffebee;
                    color: #f44336;
                    border: 1px solid #f44336;
                }
                .info {
                    background-color: #e3f2fd;
                    color: #1976d2;
                    border: 1px solid #1976d2;
                }
                .button-group {
                    margin-top: 20px;
                    display: flex;
                    gap: 10px;
                }
                #updateStatus {
                    margin-top: 20px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="tabs">
                    <div class="tab <?php echo $this->currentStep === 1 ? 'active' : ''; ?>">1. Datenbank Update</div>
                    <div class="tab">2. Dateien Update</div>
                    <div class="tab">3. Übersicht</div>
                </div>
        <?php
    }

    private function showFooter() {
        ?>
            </div>
        </body>
        </html>
        <?php
    }

    private function showDatabaseTab() {
        ?>
        <h2>Datenbank Update</h2>
        <p>Klicken Sie auf "Prüfen", um die Datenbankstruktur mit der aktuellen Version zu vergleichen.</p>
        
        <div id="updateStatus"></div>
        
        <div class="button-group">
            <button onclick="checkDatabase()" class="button" id="checkButton">Prüfen</button>
            <button onclick="updateDatabase()" class="button button-update" id="updateButton" style="display: none;">Updaten</button>
            <a href="?step=2" class="button button-next" id="nextButton" style="display: none;">Weiter</a>
        </div>

        <script>
        function checkDatabase() {
            document.getElementById('checkButton').disabled = true;
            document.getElementById('updateStatus').innerHTML = '<div class="message info">Prüfe Datenbankstruktur...</div>';

            fetch('?action=check_database')
                .then(response => response.json())
                .then(data => {
                    if (data.needsUpdate) {
                        document.getElementById('updateStatus').innerHTML = '<div class="message info">Änderungen gefunden! Klicken Sie auf "Updaten" um die Datenbank zu aktualisieren.</div>';
                        document.getElementById('updateButton').style.display = 'inline-block';
                    } else {
                        document.getElementById('updateStatus').innerHTML = '<div class="message success">Keine Änderungen festgestellt.</div>';
                        document.getElementById('nextButton').style.display = 'inline-block';
                    }
                    document.getElementById('checkButton').disabled = false;
                })
                .catch(error => {
                    document.getElementById('updateStatus').innerHTML = '<div class="message error">Fehler bei der Prüfung: ' + error.message + '</div>';
                    document.getElementById('checkButton').disabled = false;
                });
        }

        function updateDatabase() {
            document.getElementById('updateButton').disabled = true;
            document.getElementById('updateStatus').innerHTML = '<div class="message info">Führe Update durch...</div>';

            fetch('?action=update_database')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('updateStatus').innerHTML = '<div class="message success">Update wurde erfolgreich durchgeführt!</div>';
                        document.getElementById('updateButton').style.display = 'none';
                        document.getElementById('nextButton').style.display = 'inline-block';
                    } else {
                        document.getElementById('updateStatus').innerHTML = '<div class="message error">Fehler beim Update: ' + data.error + '</div>';
                        document.getElementById('updateButton').disabled = false;
                    }
                })
                .catch(error => {
                    document.getElementById('updateStatus').innerHTML = '<div class="message error">Fehler beim Update: ' + error.message + '</div>';
                    document.getElementById('updateButton').disabled = false;
                });
        }
        </script>
        <?php
    }

    private function checkDatabaseStructure() {
        header('Content-Type: application/json');
        try {
            require_once 'config.php';
            
            // Struktur-Datei von GitHub laden
            $structureSQL = file_get_contents($this->githubBaseUrl . 'structure.sql');
            if ($structureSQL === false) {
                throw new Exception("Konnte Datenbankstruktur nicht von GitHub laden");
            }

            // Aktuelle Tabellenstruktur aus der Datenbank abrufen
            $currentTables = [];
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($tables as $table) {
                // Spalteninformationen abrufen
                $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                $currentTables[$table] = [];
                foreach ($columns as $column) {
                    $currentTables[$table][$column['Field']] = [
                        'Type' => $column['Type'],
                        'Null' => $column['Null'],
                        'Key' => $column['Key'],
                        'Default' => $column['Default'],
                        'Extra' => $column['Extra']
                    ];
                }
            }

            // Extrahiere CREATE TABLE Statements
            preg_match_all('/CREATE TABLE IF NOT EXISTS\s+`?(\w+)`?\s*\((.*?)\)/s', $structureSQL, $matches);
            $differences = [];
            
            // Vergleiche jede Tabelle und ihre Struktur
            for ($i = 0; $i < count($matches[1]); $i++) {
                $tableName = $matches[1][$i];
                $tableDefinition = $matches[2][$i];
                
                if (!isset($currentTables[$tableName])) {
                    $differences[] = "Tabelle '$tableName' fehlt und wird erstellt";
                    continue;
                }
                
                // Extrahiere Spalteninformationen
                preg_match_all('/`?(\w+)`?\s+([^,\n]+)(?:,|$)/m', $tableDefinition, $columnMatches);
                
                for ($j = 0; $j < count($columnMatches[1]); $j++) {
                    $columnName = $columnMatches[1][$j];
                    $columnDef = trim($columnMatches[2][$j]);
                    
                    // Analysiere Spaltendefinition
                    $type = preg_replace('/\s.*/', '', $columnDef);
                    $null = (stripos($columnDef, 'NOT NULL') !== false) ? 'NO' : 'YES';
                    $key = (stripos($columnDef, 'PRIMARY KEY') !== false) ? 'PRI' : '';
                    $default = null;
                    if (preg_match('/DEFAULT\s+(\S+)/', $columnDef, $defaultMatch)) {
                        $default = trim($defaultMatch[1], "'\"");
                    }
                    $extra = (stripos($columnDef, 'AUTO_INCREMENT') !== false) ? 'auto_increment' : '';

                    if (!isset($currentTables[$tableName][$columnName])) {
                        $differences[] = "Spalte '$tableName.$columnName' fehlt und wird hinzugefügt";
                    } else {
                        $current = $currentTables[$tableName][$columnName];
                        
                        // Vergleiche Spaltenattribute
                        if (strtolower($current['Type']) !== strtolower($type)) {
                            $differences[] = "Spalte '$tableName.$columnName' hat abweichenden Typ (IST: {$current['Type']}, SOLL: $type)";
                        }
                        if ($current['Null'] !== $null) {
                            $differences[] = "Spalte '$tableName.$columnName' hat abweichende NULL-Erlaubnis";
                        }
                        if ($current['Key'] !== $key && $key === 'PRI') {
                            $differences[] = "Spalte '$tableName.$columnName' fehlt Primary Key";
                        }
                        if ($current['Extra'] !== $extra && $extra === 'auto_increment') {
                            $differences[] = "Spalte '$tableName.$columnName' fehlt Auto Increment";
                        }
                    }
                }
            }

            // Extrahiere und prüfe ALTER TABLE Statements
            preg_match_all('/ALTER TABLE\s+`?(\w+)`?\s+(.+?);/i', $structureSQL, $alterMatches);
            if (!empty($alterMatches[0])) {
                for ($i = 0; $i < count($alterMatches[1]); $i++) {
                    $tableName = $alterMatches[1][$i];
                    $alterDef = $alterMatches[2][$i];
                    
                    // Prüfe MODIFY Statements
                    if (preg_match('/MODIFY\s+`?(\w+)`?\s+(.+)$/i', $alterDef, $modifyMatch)) {
                        $columnName = $modifyMatch[1];
                        $columnDef = $modifyMatch[2];
                        
                        if (isset($currentTables[$tableName][$columnName])) {
                            $current = $currentTables[$tableName][$columnName];
                            $type = preg_replace('/\s.*/', '', $columnDef);
                            
                            if (strtolower($current['Type']) !== strtolower($type)) {
                                $differences[] = "Spalte '$tableName.$columnName' muss auf Typ $type geändert werden";
                            }
                        }
                    }
                }
            }

            echo json_encode([
                'needsUpdate' => !empty($differences),
                'differences' => $differences
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function updateDatabaseStructure() {
        header('Content-Type: application/json');
        try {
            require_once 'config.php';
            
            // Struktur-Datei von GitHub laden
            $structureSQL = file_get_contents($this->githubBaseUrl . 'structure.sql');
            if ($structureSQL === false) {
                throw new Exception("Konnte Datenbankstruktur nicht von GitHub laden");
            }

            // Führe CREATE TABLE Statements aus
            preg_match_all('/CREATE TABLE IF NOT EXISTS.*?;/s', $structureSQL, $matches);
            foreach ($matches[0] as $sql) {
                $pdo->exec($sql);
            }

            // Führe ALTER TABLE Statements aus
            preg_match_all('/ALTER TABLE.*?;/s', $structureSQL, $matches);
            foreach ($matches[0] as $sql) {
                try {
                    $pdo->exec($sql);
                } catch (PDOException $e) {
                    // Ignoriere nur bestimmte Fehler
                    $errorInfo = $e->errorInfo;
                    // 1060: Duplicate column
                    // 1061: Duplicate key name
                    // 1091: Can't DROP; check that column/key exists
                    if (!in_array($errorInfo[1], [1060, 1061, 1091])) {
                        throw $e;
                    }
                }
            }

            // Standard-Daten von GitHub laden und aktualisieren
            $defaultDataSQL = file_get_contents($this->githubBaseUrl . 'default_data.sql');
            if ($defaultDataSQL === false) {
                throw new Exception("Konnte Standarddaten nicht von GitHub laden");
            }

            $statements = array_filter(
                explode(';', $defaultDataSQL),
                function($sql) { return trim($sql) != ''; }
            );
            
            foreach ($statements as $sql) {
                if (trim($sql) !== '') {
                    try {
                        $pdo->exec(trim($sql));
                    } catch (PDOException $e) {
                        // Ignoriere nur Duplikate bei INSERT IGNORE
                        if (strpos($e->getMessage(), 'Duplicate') === false) {
                            throw $e;
                        }
                    }
                }
            }

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage(), 'success' => false]);
        }
    }
}

$updater = new Updater();
$updater->run(); 