<?php
session_start();
require_once 'config.php';

class Updater {
    private $currentVersion = "1.0.0"; // Aktuelle Version
    private $githubRepo = "Bittersweet1987/spendenziele";
    private $githubBranch = "main";
    private $requiredTables = [
        'spendenziele' => [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'name' => 'VARCHAR(255) NOT NULL',
            'mindestbetrag' => 'DECIMAL(10,2) DEFAULT 0',
            'sichtbar' => 'BOOLEAN DEFAULT false',
            'erledigt' => 'BOOLEAN DEFAULT false',
            'erstellt_am' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ],
        'spenden' => [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'ziel_id' => 'INT',
            'betrag' => 'DECIMAL(10,2) NOT NULL',
            'spender' => 'VARCHAR(255)',
            'nachricht' => 'TEXT',
            'erstellt_am' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ],
        'moderatoren' => [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'benutzername' => 'VARCHAR(255) NOT NULL UNIQUE',
            'passwort_hash' => 'VARCHAR(255) NOT NULL',
            'aktiv' => 'BOOLEAN DEFAULT true',
            'erstellt_am' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ],
        'einstellungen' => [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'name' => 'VARCHAR(255) NOT NULL UNIQUE',
            'wert' => 'TEXT',
            'erstellt_am' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'aktualisiert_am' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ]
    ];

    public function __construct() {
        // Prüfe Admin-Login
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            header('Location: admin_login.php');
            exit;
        }
    }

    public function run() {
        $this->showHeader();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['check_update'])) {
                $this->checkForUpdates();
            } elseif (isset($_POST['perform_update'])) {
                $this->performUpdate();
            } elseif (isset($_POST['update_database'])) {
                $this->updateDatabase();
            }
        }

        $this->showUpdateInterface();
        $this->showFooter();
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
                .version-info {
                    background-color: #f8f9fa;
                    padding: 15px;
                    border-radius: 4px;
                    margin: 15px 0;
                }
                .update-available {
                    background-color: #dff0d8;
                    color: #3c763d;
                    padding: 15px;
                    border-radius: 4px;
                    margin: 15px 0;
                }
                .no-update {
                    background-color: #d9edf7;
                    color: #31708f;
                    padding: 15px;
                    border-radius: 4px;
                    margin: 15px 0;
                }
                .error {
                    background-color: #f2dede;
                    color: #a94442;
                    padding: 15px;
                    border-radius: 4px;
                    margin: 15px 0;
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
                    font-size: 16px;
                    margin: 5px;
                }
                .button:hover {
                    background-color: #45a049;
                }
                .file-list {
                    margin: 15px 0;
                    font-family: monospace;
                }
                .file-list li {
                    padding: 5px;
                    border-bottom: 1px solid #eee;
                }
                .file-list li:last-child {
                    border-bottom: none;
                }
                .modified {
                    color: #f0ad4e;
                }
                .added {
                    color: #5cb85c;
                }
                .deleted {
                    color: #d9534f;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>System Update</h1>
        <?php
    }

    private function showUpdateInterface() {
        ?>
        <div class="version-info">
            <h2>Versionsinformationen</h2>
            <p><strong>Aktuelle Version:</strong> <?php echo $this->currentVersion; ?></p>
        </div>

        <form method="post" style="margin: 20px 0;">
            <button type="submit" name="check_update" class="button">Nach Updates suchen</button>
            <button type="submit" name="update_database" class="button">Datenbank prüfen</button>
        </form>
        <?php
    }

    private function checkForUpdates() {
        try {
            $latestVersion = $this->getLatestVersion();
            
            if (version_compare($latestVersion, $this->currentVersion, '>')) {
                echo '<div class="update-available">';
                echo "<h3>🔄 Update verfügbar!</h3>";
                echo "<p>Neue Version verfügbar: v{$latestVersion}</p>";
                echo "<p>Ihre Version: v{$this->currentVersion}</p>";
                echo '<form method="post">';
                echo '<button type="submit" name="perform_update" class="button">Update durchführen</button>';
                echo '</form>';
                echo '</div>';
                
                // Zeige Änderungen an
                $changes = $this->getChangelog();
                if (!empty($changes)) {
                    echo '<div class="file-list">';
                    echo '<h3>Änderungen:</h3>';
                    echo '<ul>';
                    foreach ($changes as $change) {
                        $class = '';
                        $icon = '';
                        switch ($change['type']) {
                            case 'modified':
                                $class = 'modified';
                                $icon = '🔄';
                                break;
                            case 'added':
                                $class = 'added';
                                $icon = '➕';
                                break;
                            case 'deleted':
                                $class = 'deleted';
                                $icon = '❌';
                                break;
                        }
                        echo "<li class='{$class}'>{$icon} {$change['file']}</li>";
                    }
                    echo '</ul>';
                    echo '</div>';
                }
            } else {
                echo '<div class="no-update">';
                echo "<h3>✓ System ist aktuell</h3>";
                echo "<p>Sie verwenden bereits die neueste Version (v{$this->currentVersion})</p>";
                echo '</div>';
            }
        } catch (Exception $e) {
            echo '<div class="error">';
            echo "<h3>❌ Fehler beim Prüfen auf Updates</h3>";
            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
            echo '</div>';
        }
    }

    private function performUpdate() {
        try {
            // Download der neuen Version
            $zipUrl = "https://github.com/{$this->githubRepo}/archive/refs/heads/{$this->githubBranch}.zip";
            $tempFile = tempnam(sys_get_temp_dir(), 'update_');
            $tempDir = $tempFile . '_extracted';

            // ZIP herunterladen
            $zipContent = file_get_contents($zipUrl);
            if ($zipContent === false) {
                throw new Exception("Konnte Update nicht herunterladen");
            }

            file_put_contents($tempFile, $zipContent);

            // ZIP entpacken
            $zip = new ZipArchive;
            if ($zip->open($tempFile) !== true) {
                throw new Exception("Konnte ZIP-Datei nicht öffnen");
            }

            mkdir($tempDir);
            $zip->extractTo($tempDir);
            $zip->close();

            // Dateien kopieren
            $sourceDir = $tempDir . '/spendenziele-' . $this->githubBranch . '/Spendenziele';
            if (!is_dir($sourceDir)) {
                throw new Exception("Update-Dateien nicht gefunden");
            }

            $this->copyFiles($sourceDir, __DIR__);

            // Aufräumen
            unlink($tempFile);
            $this->deleteDirectory($tempDir);

            // Datenbank aktualisieren
            $this->updateDatabase();

            echo '<div class="update-available">';
            echo "<h3>✓ Update erfolgreich!</h3>";
            echo "<p>Das System wurde erfolgreich aktualisiert.</p>";
            echo '</div>';

        } catch (Exception $e) {
            echo '<div class="error">';
            echo "<h3>❌ Update fehlgeschlagen</h3>";
            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
            echo '</div>';
        }
    }

    private function updateDatabase() {
        try {
            global $pdo;
            $changes = [];

            foreach ($this->requiredTables as $table => $columns) {
                // Prüfe ob Tabelle existiert
                $tableExists = $pdo->query("SHOW TABLES LIKE '$table'")->rowCount() > 0;
                
                if (!$tableExists) {
                    // Erstelle Tabelle
                    $sql = "CREATE TABLE $table (";
                    $columnDefs = [];
                    foreach ($columns as $column => $definition) {
                        $columnDefs[] = "$column $definition";
                    }
                    $sql .= implode(', ', $columnDefs);
                    $sql .= ")";
                    $pdo->exec($sql);
                    $changes[] = "Tabelle '$table' wurde erstellt";
                } else {
                    // Prüfe Spalten
                    $existingColumns = [];
                    foreach ($pdo->query("SHOW COLUMNS FROM $table") as $row) {
                        $existingColumns[$row['Field']] = $row;
                    }

                    foreach ($columns as $column => $definition) {
                        if (!isset($existingColumns[$column])) {
                            // Füge fehlende Spalte hinzu
                            $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
                            $changes[] = "Spalte '$column' wurde zu Tabelle '$table' hinzugefügt";
                        }
                    }
                }
            }

            if (empty($changes)) {
                echo '<div class="no-update">';
                echo "<h3>✓ Datenbank ist aktuell</h3>";
                echo "<p>Keine Änderungen notwendig</p>";
                echo '</div>';
            } else {
                echo '<div class="update-available">';
                echo "<h3>✓ Datenbank aktualisiert</h3>";
                echo "<ul>";
                foreach ($changes as $change) {
                    echo "<li>" . htmlspecialchars($change) . "</li>";
                }
                echo "</ul>";
                echo '</div>';
            }
        } catch (Exception $e) {
            echo '<div class="error">';
            echo "<h3>❌ Datenbankaktualisierung fehlgeschlagen</h3>";
            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
            echo '</div>';
        }
    }

    private function getLatestVersion() {
        // TODO: Implementiere GitHub API Aufruf zur Versionsprüfung
        // Für den Moment geben wir eine Testversion zurück
        return "1.0.1";
    }

    private function getChangelog() {
        // TODO: Implementiere GitHub API Aufruf für Änderungen
        // Für den Moment geben wir Testdaten zurück
        return [
            ['type' => 'modified', 'file' => 'admin_panel.php'],
            ['type' => 'added', 'file' => 'neue_funktion.php'],
            ['type' => 'deleted', 'file' => 'alte_datei.php']
        ];
    }

    private function copyFiles($source, $dest) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $targetPath = $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            
            if ($item->isDir()) {
                if (!file_exists($targetPath)) {
                    mkdir($targetPath);
                }
            } else {
                // Überspringe bestimmte Dateien
                if (in_array(basename($item), ['config.php', 'update.php'])) {
                    continue;
                }
                copy($item, $targetPath);
            }
        }
    }

    private function deleteDirectory($dir) {
        if (!file_exists($dir)) {
            return true;
        }
        if (!is_dir($dir)) {
            return unlink($dir);
        }
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            if (!$this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }
        return rmdir($dir);
    }

    private function showFooter() {
        ?>
            </div>
        </body>
        </html>
        <?php
    }
}

$updater = new Updater();
$updater->run(); 