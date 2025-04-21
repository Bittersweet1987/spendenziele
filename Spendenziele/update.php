<?php
// Aktiviere Fehlerberichterstattung für Debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Setze den Content-Type auf HTML
header('Content-Type: text/html; charset=utf-8');

// Grundlegende Sicherheitsüberprüfung
session_start();

// Lade die Konfigurationsdatei
require_once 'config.php';

class SystemUpdater {
    private $pdo;
    private $githubRepo = 'Bittersweet1987/spendenziele';
    private $githubBranch = 'main';
    private $githubRawBase = 'https://raw.githubusercontent.com/Bittersweet1987/spendenziele/main/Spendenziele';
    private $localBasePath;
    private $filesToCheck = [
        'php' => [
            'admin_panel.php',
            'moderator_panel.php',
            'spendenziele.php',
            'login.php',
            'setup_database.php',
            'store_donation.php',
            'get_ziele.php',
            'update_spende.php',
            'delete_ziel.php',
            'toggle_moderator.php',
            'update_mindestbetrag.php',
            'ziel_abgeschlossen.php',
            'toggle_ziel_sichtbarkeit.php',
            'moderator_login.php',
            'moderator_logout.php',
            'moderator_verwaltung.php',
            'reset_moderator_password.php',
            'reset_spenden.php',
            'save_settings.php',
            'set_zeitzone.php'
        ],
        'html' => [
            'abgeschlossene_ziele_widget.html',
            'offene_ziele_widget.html',
            'top_ziele_widget.html',
            'timer_widget.html'
        ]
    ];

    private $requiredTables = [
        'admin' => [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'benutzername' => 'VARCHAR(50) UNIQUE NOT NULL',
            'passwort_hash' => 'VARCHAR(255) NOT NULL'
        ],
        'moderatoren' => [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'benutzername' => 'VARCHAR(50) UNIQUE NOT NULL',
            'passwort_hash' => 'VARCHAR(255) NOT NULL',
            'status' => "ENUM('aktiv', 'inaktiv') NOT NULL DEFAULT 'aktiv'"
        ],
        'spenden' => [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'benutzername' => 'VARCHAR(255) NOT NULL',
            'betrag' => 'DECIMAL(10,2) NOT NULL',
            'ziel' => 'VARCHAR(100) NOT NULL',
            'datum' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'ziele' => [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'ziel' => 'VARCHAR(100) NOT NULL',
            'gesamtbetrag' => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
            'mindestbetrag' => 'DECIMAL(10,2) DEFAULT NULL',
            'abgeschlossen' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'sichtbar' => 'TINYINT(1) NOT NULL DEFAULT 0'
        ],
        'einstellungen' => [
            'schluessel' => 'VARCHAR(255) PRIMARY KEY',
            'wert' => 'TEXT NOT NULL'
        ],
        'zeitzonen' => [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'name' => 'VARCHAR(255) NOT NULL UNIQUE'
        ],
        'zeitraum' => [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'start' => 'DATETIME NOT NULL',
            'ende' => 'DATETIME NOT NULL'
        ]
    ];

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->localBasePath = __DIR__;
    }

    public function run() {
        $this->showHeader();
        
        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (isset($_POST['update_database'])) {
                    $differences = $this->checkTables();
                    $this->updateTables($differences);
                } elseif (isset($_POST['update_files'])) {
                    $this->updateFiles();
                } elseif (isset($_POST['check_files'])) {
                    $this->checkAndDisplayFiles();
                }
            }

            // Zeige Standard-Ansicht
            $this->showDashboard();

        } catch (Exception $e) {
            $this->showError($e->getMessage());
        }
        
        $this->showFooter();
    }

    private function showDashboard() {
        ?>
        <div class="dashboard">
            <div class="dashboard-item">
                <h2>1. Dateisystem-Update</h2>
                <p>Vergleicht und aktualisiert PHP und HTML Dateien mit der GitHub-Version.</p>
                <form method="post" class="action-buttons">
                    <button type="submit" name="check_files" class="button">Dateien überprüfen</button>
                </form>
            </div>

            <div class="dashboard-item">
                <h2>2. Datenbank-Update</h2>
                <p>Überprüft und aktualisiert die Datenbankstruktur.</p>
                <form method="post" class="action-buttons">
                    <button type="submit" name="update_database" class="button">Datenbank prüfen & aktualisieren</button>
                </form>
            </div>
        </div>
        <?php
    }

    private function checkAndDisplayFiles() {
        echo "<h2>Datei-Vergleich:</h2>";
        
        $differences = [];
        
        foreach ($this->filesToCheck as $type => $files) {
            foreach ($files as $file) {
                $localFile = $this->localBasePath . '/' . $file;
                $githubUrl = $this->githubRawBase . '/' . $file;
                
                // Prüfe ob lokale Datei existiert
                if (!file_exists($localFile)) {
                    $differences[$file] = [
                        'status' => 'missing',
                        'message' => 'Datei fehlt lokal'
                    ];
                    continue;
                }
                
                // Hole GitHub-Version
                $githubContent = @file_get_contents($githubUrl);
                if ($githubContent === false) {
                    $differences[$file] = [
                        'status' => 'error',
                        'message' => 'Konnte GitHub-Version nicht abrufen'
                    ];
                    continue;
                }
                
                // Vergleiche Inhalte
                $localContent = file_get_contents($localFile);
                if ($localContent !== $githubContent) {
                    $differences[$file] = [
                        'status' => 'different',
                        'message' => 'Datei unterscheidet sich von GitHub-Version'
                    ];
                }
            }
        }
        
        if (empty($differences)) {
            echo "<div class='success'><h3>✓ Alle Dateien sind aktuell</h3></div>";
            return;
        }
        
        echo "<div class='files-status'>";
        foreach ($differences as $file => $info) {
            $statusClass = $info['status'] === 'missing' ? 'missing' : ($info['status'] === 'error' ? 'error' : 'different');
            echo "<div class='file-item $statusClass'>";
            echo "<h4>$file</h4>";
            echo "<p>{$info['message']}</p>";
            echo "</div>";
        }
        echo "</div>";
        
        echo "<form method='post' style='margin-top: 20px;'>";
        echo "<button type='submit' name='update_files' class='button'>Dateien aktualisieren</button>";
        echo "</form>";
    }

    private function updateFiles() {
        echo "<h2>Aktualisiere Dateien:</h2>";
        
        foreach ($this->filesToCheck as $type => $files) {
            foreach ($files as $file) {
                $localFile = $this->localBasePath . '/' . $file;
                $githubUrl = $this->githubRawBase . '/' . $file;
                
                // Hole GitHub-Version
                $githubContent = @file_get_contents($githubUrl);
                if ($githubContent === false) {
                    echo "<div class='error'>Fehler beim Abrufen von $file von GitHub</div>";
                    continue;
                }
                
                // Erstelle Backup wenn Datei existiert
                if (file_exists($localFile)) {
                    $backupFile = $localFile . '.bak';
                    copy($localFile, $backupFile);
                }
                
                // Schreibe neue Version
                if (file_put_contents($localFile, $githubContent) !== false) {
                    echo "<div class='success'>✓ $file wurde aktualisiert</div>";
                } else {
                    echo "<div class='error'>❌ Fehler beim Aktualisieren von $file</div>";
                }
            }
        }
    }

    private function checkTables() {
        $differences = [];
        
        foreach ($this->requiredTables as $tableName => $columns) {
            // Prüfe ob Tabelle existiert
            $tableExists = $this->pdo->query("SHOW TABLES LIKE '$tableName'")->rowCount() > 0;
            
            if (!$tableExists) {
                $differences[$tableName] = ['status' => 'missing', 'columns' => $columns];
                continue;
            }
            
            // Prüfe Spalten
            $stmt = $this->pdo->query("SHOW COLUMNS FROM $tableName");
            $existingColumns = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $existingColumns[$row['Field']] = $row;
            }
            
            $columnDiffs = [];
            foreach ($columns as $columnName => $definition) {
                if (!isset($existingColumns[$columnName])) {
                    $columnDiffs[$columnName] = ['status' => 'missing', 'definition' => $definition];
                }
                // Hier könnte man noch die Spaltendefinitionen vergleichen
            }
            
            if (!empty($columnDiffs)) {
                $differences[$tableName] = ['status' => 'different', 'columns' => $columnDiffs];
            }
        }
        
        return $differences;
    }

    private function updateTables($differences) {
        foreach ($differences as $tableName => $info) {
            if ($info['status'] === 'missing') {
                // Erstelle Tabelle
                $sql = "CREATE TABLE $tableName (";
                $columnDefs = [];
                foreach ($info['columns'] as $column => $definition) {
                    $columnDefs[] = "$column $definition";
                }
                $sql .= implode(', ', $columnDefs);
                $sql .= ")";
                $this->pdo->exec($sql);
                echo "<div class='success'>Tabelle '$tableName' wurde erstellt.</div>";
            } else if ($info['status'] === 'different') {
                // Füge fehlende Spalten hinzu
                foreach ($info['columns'] as $columnName => $columnInfo) {
                    if ($columnInfo['status'] === 'missing') {
                        $sql = "ALTER TABLE $tableName ADD COLUMN $columnName {$columnInfo['definition']}";
                        $this->pdo->exec($sql);
                        echo "<div class='success'>Spalte '$columnName' wurde zu Tabelle '$tableName' hinzugefügt.</div>";
                    }
                }
            }
        }
    }

    private function showHeader() {
        ?>
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>System Update</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    max-width: 1200px;
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
                .header {
                    background-color: #2c3e50;
                    color: white;
                    padding: 20px;
                    border-radius: 8px 8px 0 0;
                    margin-bottom: 20px;
                }
                .table-status {
                    margin: 20px 0;
                    padding: 15px;
                    border-radius: 4px;
                }
                .missing {
                    background-color: #ffebee;
                    border-left: 4px solid #f44336;
                }
                .different {
                    background-color: #fff3e0;
                    border-left: 4px solid #ff9800;
                }
                .success {
                    background-color: #e8f5e9;
                    border-left: 4px solid #4caf50;
                    padding: 10px;
                    margin: 10px 0;
                }
                .error {
                    background-color: #ffebee;
                    border-left: 4px solid #f44336;
                    padding: 10px;
                    margin: 10px 0;
                }
                .button {
                    display: inline-block;
                    padding: 10px 20px;
                    background-color: #2c3e50;
                    color: white;
                    text-decoration: none;
                    border-radius: 4px;
                    border: none;
                    cursor: pointer;
                    font-size: 16px;
                    margin: 5px;
                }
                .button:hover {
                    background-color: #34495e;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 10px 0;
                }
                th, td {
                    padding: 12px;
                    text-align: left;
                    border-bottom: 1px solid #ddd;
                }
                th {
                    background-color: #f8f9fa;
                }
                .dashboard {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                    gap: 20px;
                    margin-bottom: 30px;
                }
                .dashboard-item {
                    background-color: white;
                    padding: 20px;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                .file-item {
                    margin: 10px 0;
                    padding: 15px;
                    border-radius: 4px;
                }
                .files-status {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                    gap: 15px;
                    margin: 20px 0;
                }
                .action-buttons {
                    margin-top: 15px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>System Update</h1>
                    <p>Aktualisiert Dateien und Datenbankstruktur mit der GitHub-Version</p>
                </div>
        <?php
    }

    private function showError($message) {
        echo "<div class='error'>";
        echo "<h3>❌ Fehler</h3>";
        echo "<p>" . htmlspecialchars($message) . "</p>";
        echo "</div>";
    }

    private function showFooter() {
        ?>
            </div>
        </body>
        </html>
        <?php
    }
}

try {
    $updater = new SystemUpdater($pdo);
    $updater->run();
} catch (Exception $e) {
    die("Fehler: " . $e->getMessage());
}
?> 