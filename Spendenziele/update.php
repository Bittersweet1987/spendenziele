<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['admin_id'])) {
    die("<h3>Zugriff verweigert. Bitte <a href='admin_login.php'>einloggen</a>.</h3>");
}

// Funktion zum Abrufen der aktuellen Commit-Version und Datum
function getCurrentCommit() {
    debugLog("=== Start: Lokale Version Abfrage ===");
    
    $commitFile = __DIR__ . '/last_commit.txt';
    if (!file_exists($commitFile)) {
        debugLog("last_commit.txt nicht gefunden in: " . __DIR__);
        return ['hash' => 'Keine Version gefunden', 'date' => null];
    }
    
    $hash = trim(file_get_contents($commitFile));
    if (empty($hash)) {
        debugLog("last_commit.txt ist leer");
        return ['hash' => 'Keine Version gefunden', 'date' => null];
    }
    
    debugLog("Gefundener Hash in last_commit.txt: " . $hash);
    
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: PHP',
                'Accept: application/vnd.github.v3+json',
                'Authorization: token ' . (defined('GITHUB_TOKEN') ? GITHUB_TOKEN : '')
            ]
        ]
    ];
    $context = stream_context_create($opts);
    
    $apiUrl = 'https://api.github.com/repos/Bittersweet1987/spendenziele/commits/' . $hash;
    debugLog("API URL für Commit-Details: " . $apiUrl);
    
    $response = @file_get_contents($apiUrl, false, $context);
    
    if ($response === false) {
        $error = error_get_last();
        debugLog("GitHub API Fehler für Commit " . $hash . ":", $error);
        return ['hash' => substr($hash, 0, 7), 'date' => null];
    }
    
    debugLog("GitHub API Antwort für Commit-Details:", $response);
    
    $data = json_decode($response, true);
    if (!$data || !isset($data['commit']['committer']['date'])) {
        debugLog("Ungültige API-Antwort für Commit " . $hash . ":", $data);
        return ['hash' => substr($hash, 0, 7), 'date' => null];
    }
    
    try {
        $date = new DateTime($data['commit']['committer']['date']);
        $date->setTimezone(new DateTimeZone('Europe/Berlin'));
        debugLog("Commit Datum geparst: " . $date->format('Y-m-d H:i:s'));
        
        $result = [
            'hash' => substr($hash, 0, 7),
            'date' => $date
        ];
        
        debugLog("=== Ende: Lokale Version Abfrage ===", $result);
        return $result;
    } catch (Exception $e) {
        debugLog("Datum Parsing Fehler: " . $e->getMessage());
        return ['hash' => substr($hash, 0, 7), 'date' => null];
    }
}

function debugLog($message, $data = null) {
    if (!isset($_SESSION['debug_log'])) {
        $_SESSION['debug_log'] = [];
    }
    
    $logMessage = "[" . date('Y-m-d H:i:s') . "] " . $message;
    if ($data !== null) {
        if (is_string($data) && strlen($data) > 1000) {
            // Teile lange Strings in kleinere Chunks
            $chunks = str_split($data, 1000);
            $logMessage .= "\nDATA (Teil 1/" . count($chunks) . "):\n" . $chunks[0];
            for ($i = 1; $i < count($chunks); $i++) {
                $_SESSION['debug_log'][] = "[FORTSETZUNG] DATA (Teil " . ($i + 1) . "/" . count($chunks) . "):\n" . $chunks[$i];
            }
        } else if (is_array($data) || is_object($data)) {
            $logMessage .= "\n" . print_r($data, true);
        } else {
            $logMessage .= "\n" . print_r($data, true);
        }
    }
    
    $_SESSION['debug_log'][] = $logMessage;
}

// Funktion zum Abrufen der GitHub Commit-Version
function getLatestGitHubCommit($force = false) {
    debugLog("=== Start: GitHub Latest Commit Abfrage ===");
    
    // Hole den GitHub Token aus der Konfiguration
    $githubToken = defined('GITHUB_TOKEN') ? GITHUB_TOKEN : '';
    
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: PHP',
                'Accept: application/vnd.github.v3+json',
                'Authorization: token ' . $githubToken
            ]
        ]
    ];
    
    debugLog("Request Optionen:", $opts);
    $context = stream_context_create($opts);
    
    $apiUrl = 'https://api.github.com/repos/Bittersweet1987/spendenziele/commits';
    debugLog("API URL:", $apiUrl);
    
    $response = @file_get_contents($apiUrl, false, $context);
    
    if ($response === false) {
        $error = error_get_last();
        debugLog("GitHub API Fehler:", $error);
        
        // Prüfe Rate Limit
        $headers = get_headers($apiUrl, 1);
        if (isset($headers['X-RateLimit-Remaining']) && $headers['X-RateLimit-Remaining'] == 0) {
            $resetTime = isset($headers['X-RateLimit-Reset']) ? date('Y-m-d H:i:s', $headers['X-RateLimit-Reset']) : 'unbekannt';
            debugLog("Rate Limit erreicht. Reset um: " . $resetTime);
        }
        
        return ['hash' => 'Fehler', 'date' => null];
    }
    
    debugLog("GitHub API Antwort:", $response);
    
    // Log nur die ersten paar Commits für bessere Übersichtlichkeit
    $commits = json_decode($response, true);
    if (is_array($commits)) {
        $logCommits = array_slice($commits, 0, 3); // Zeige nur die ersten 3 Commits
        debugLog("Erste 3 Commits von GitHub:", $logCommits);
    } else {
        debugLog("Ungültige API-Antwort:", $commits);
        return ['hash' => 'Fehler', 'date' => null];
    }
    
    if (empty($commits)) {
        debugLog("Keine Commits gefunden");
        return ['hash' => 'Fehler', 'date' => null];
    }
    
    $latestCommit = $commits[0];
    debugLog("Details des neuesten Commits:", [
        'sha' => $latestCommit['sha'],
        'commit' => [
            'message' => $latestCommit['commit']['message'],
            'committer' => $latestCommit['commit']['committer']
        ]
    ]);
    
    $date = null;
    if (isset($latestCommit['commit']['committer']['date'])) {
        try {
            $date = new DateTime($latestCommit['commit']['committer']['date']);
            $date->setTimezone(new DateTimeZone('Europe/Berlin'));
            debugLog("Commit Datum geparst:", $date->format('Y-m-d H:i:s'));
        } catch (Exception $e) {
            debugLog("Datum Parsing Fehler:", $e->getMessage());
        }
    }
    
    $result = [
        'hash' => substr($latestCommit['sha'], 0, 7),
        'date' => $date
    ];
    
    debugLog("=== Ende: GitHub Latest Commit Abfrage ===", $result);
    return $result;
}

// Funktion zum Abrufen der detaillierten Datenbankstruktur
function getDetailedDatabaseStructure($pdo) {
    debugLog("=== Start: Lokale Datenbankstruktur Abruf ===");
    $structure = [];
    
    // Hole alle Tabellen
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    debugLog("Gefundene lokale Tabellen:", $tables);
    
    foreach ($tables as $table) {
        debugLog("\nAnalysiere Tabelle: " . $table);
        
        // Hole detaillierte Spalteninformationen
        $columns = $pdo->query("SHOW FULL COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        debugLog("Spalten für " . $table . ":", $columns);
        
        // Hole CREATE TABLE Statement
        $createTable = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        debugLog("CREATE Statement für " . $table . ":", $createTable);
        
        $structure[$table] = [
            'columns' => $columns,
            'create_statement' => $createTable['Create Table']
        ];
    }
    
    debugLog("=== Ende: Lokale Datenbankstruktur ===", $structure);
    return $structure;
}

// Funktion zum Parsen der GitHub SQL-Struktur
function parseGitHubSQL($sql) {
    debugLog("\n=== Start: SQL Parsing ===");
    debugLog("Input SQL:", $sql);
    
    $structure = [];
    
    // Entferne Kommentare und leere Zeilen
    $sql = preg_replace('/--[^\n]*\n/', "\n", $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    debugLog("\nSQL nach Kommentarentfernung:", $sql);
    
    // Teile die SQL-Anweisungen
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    debugLog("\nAnzahl gefundener SQL Statements:", count($statements));
    
    foreach ($statements as $statement) {
        if (empty($statement)) continue;
        
        debugLog("\nVerarbeite Statement:", $statement);
        
        // Suche nach CREATE TABLE Statements
        if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([^`\s]+)`?\s*\((.*)\)/is', $statement, $matches)) {
            $tableName = trim($matches[1], '`');
            $tableDefinition = $matches[2];
            
            debugLog("\nGefundene Tabelle:", $tableName);
            debugLog("Tabellen-Definition:", $tableDefinition);
            
            // Parse Spalten
            $columnLines = array_map('trim', preg_split('/,(?![^(]*\))/', $tableDefinition));
            $columns = [];
            
            foreach ($columnLines as $line) {
                if (empty(trim($line))) continue;
                
                debugLog("\nVerarbeite Spalten-Definition:", $line);
                
                if (preg_match('/^`?([^`]+)`?\s+(.+)$/i', $line, $colMatch)) {
                    $columnName = trim($colMatch[1], '`');
                    $definition = trim($colMatch[2]);
                    
                    // Extrahiere Typ
                    preg_match('/^(\w+(?:\([^)]+\))?)/i', $definition, $typeMatch);
                    $type = isset($typeMatch[1]) ? strtoupper($typeMatch[1]) : '';
                    
                    // Prüfe auf NULL/NOT NULL
                    $isNull = (stripos($definition, 'NOT NULL') === false) ? 'YES' : 'NO';
                    
                    // Prüfe auf Default-Wert
                    $default = null;
                    if (preg_match('/DEFAULT\s+([^,\s]+)/i', $definition, $defaultMatch)) {
                        $default = trim($defaultMatch[1], "'\"");
                    }
                    
                    // Prüfe auf Extra-Attribute
                    $extra = '';
                    if (stripos($definition, 'AUTO_INCREMENT') !== false) {
                        $extra = 'auto_increment';
                    }
                    
                    // Prüfe auf Schlüssel
                    $key = '';
                    if (stripos($line, 'PRIMARY KEY') !== false) {
                        $key = 'PRI';
                    } elseif (stripos($line, 'UNIQUE') !== false) {
                        $key = 'UNI';
                    }
                    
                    $columns[] = [
                        'Field' => $columnName,
                        'Type' => $type,
                        'Null' => $isNull,
                        'Key' => $key,
                        'Default' => $default,
                        'Extra' => $extra,
                        'Collation' => (stripos($type, 'VARCHAR') !== false || stripos($type, 'TEXT') !== false) ? 'utf8mb4_general_ci' : ''
                    ];
                    
                    debugLog("Geparste Spalten-Details:", end($columns));
                }
            }
            
            if (!empty($columns)) {
                $structure[$tableName] = [
                    'columns' => $columns,
                    'create_statement' => $statement
                ];
            }
        }
    }
    
    debugLog("\n=== Ende: SQL Parsing ===");
    debugLog("Finale geparste Struktur:", $structure);
    return $structure;
}

// Funktion zum Vergleichen von Strukturen
function compareStructures($local, $github) {
    debugLog("\n=== Start: Struktur-Vergleich ===");
    debugLog("\nLokale Struktur:", $local);
    debugLog("\nGitHub Struktur:", $github);
    
    $differences = [];
    
    // Vergleiche Tabellen
    foreach ($local as $tableName => $tableData) {
        debugLog("\nVergleiche Tabelle: " . $tableName);
        
        if (!isset($github[$tableName])) {
            debugLog("Tabelle fehlt in GitHub:", $tableName);
            $differences[$tableName] = [
                'status' => 'missing_github',
                'message' => 'Tabelle existiert nicht mehr in der GitHub-Version'
            ];
            continue;
        }
        
        // Vergleiche Spalten
        debugLog("\nVergleiche Spalten für Tabelle: " . $tableName);
        debugLog("Lokale Spalten:", $tableData['columns']);
        debugLog("GitHub Spalten:", $github[$tableName]['columns']);
        
        $columnDiffs = [];
        foreach ($tableData['columns'] as $column) {
            $columnName = $column['Field'];
            debugLog("\nPrüfe Spalte: " . $columnName);
            
            // Suche entsprechende Spalte in GitHub
            $githubColumn = null;
            foreach ($github[$tableName]['columns'] as $gc) {
                if ($gc['Field'] === $columnName) {
                    $githubColumn = $gc;
                    break;
                }
            }
            
            if ($githubColumn === null) {
                debugLog("Spalte fehlt in GitHub Version:", $columnName);
                $columnDiffs[$columnName] = [
                    'type' => 'removed',
                    'message' => "Spalte existiert nicht mehr in der GitHub-Version"
                ];
                continue;
            }
            
            // Vergleiche Spalten-Details
            $attributeDiffs = [];
            foreach (['Type', 'Null', 'Default', 'Extra'] as $attr) {
                if ($column[$attr] !== $githubColumn[$attr]) {
                    debugLog("Unterschied gefunden in Attribut " . $attr . ":", [
                        'lokal' => $column[$attr],
                        'github' => $githubColumn[$attr]
                    ]);
                    $attributeDiffs[] = "$attr: {$column[$attr]} → {$githubColumn[$attr]}";
                }
            }
            
            if (!empty($attributeDiffs)) {
                $columnDiffs[$columnName] = [
                    'type' => 'modified',
                    'differences' => $attributeDiffs
                ];
            }
        }
        
        // Prüfe auf neue Spalten in GitHub
        foreach ($github[$tableName]['columns'] as $githubColumn) {
            $columnName = $githubColumn['Field'];
            $exists = false;
            foreach ($tableData['columns'] as $lc) {
                if ($lc['Field'] === $columnName) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                debugLog("Neue Spalte in GitHub gefunden:", $columnName);
                $columnDiffs[$columnName] = [
                    'type' => 'added',
                    'message' => "Neue Spalte in der GitHub-Version"
                ];
            }
        }
        
        if (!empty($columnDiffs)) {
            $differences[$tableName] = [
                'status' => 'modified',
                'columns' => $columnDiffs
            ];
        }
    }
    
    // Prüfe auf neue Tabellen in GitHub
    foreach ($github as $tableName => $tableData) {
        if (!isset($local[$tableName])) {
            debugLog("Neue Tabelle in GitHub gefunden:", $tableName);
            $differences[$tableName] = [
                'status' => 'missing_local',
                'message' => 'Tabelle fehlt in der lokalen Datenbank'
            ];
        }
    }
    
    debugLog("\n=== Ende: Struktur-Vergleich ===");
    debugLog("Gefundene Unterschiede:", $differences);
    return $differences;
}

function normalizeType($type) {
    // Entferne Längenangaben bei INT
    $type = preg_replace('/INT\(\d+\)/', 'INT', strtoupper($type));
    
    // Normalisiere DECIMAL Angaben
    $type = preg_replace('/DECIMAL\(\d+,\d+\)/', 'DECIMAL', $type);
    
    // Normalisiere TINYINT(1) zu BOOLEAN
    $type = preg_replace('/TINYINT\(1\)/', 'TINYINT(1)', $type);
    
    return $type;
}

// Funktion zum Ausführen der Struktur-Updates
function applyStructureUpdates($pdo) {
    $results = [
        'tables' => [
            'added' => [],
            'updated' => [],
            'unchanged' => []
        ],
        'columns' => [
            'added' => [],
            'updated' => [],
            'unchanged' => []
        ],
        'errors' => []
    ];
    
    try {
        debugLog("=== Start: Datenbankstruktur-Update ===");
        
        // Hole die aktuelle GitHub-Struktur
        $url = 'https://raw.githubusercontent.com/Bittersweet1987/spendenziele/main/Datenbank/structure.sql';
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: PHP',
                    'Accept: text/plain'
                ]
            ]
        ];
        $context = stream_context_create($opts);
        debugLog("Lade SQL-Struktur von GitHub:", $url);
        
        $githubSQL = @file_get_contents($url, false, $context);
        
        if ($githubSQL === false) {
            $error = error_get_last();
            debugLog("Fehler beim Laden der SQL-Struktur:", $error);
            throw new Exception("Konnte SQL-Datei nicht von GitHub laden: " . ($error['message'] ?? 'Unbekannter Fehler'));
        }
        
        debugLog("SQL-Struktur geladen:", $githubSQL);
        
        // Führe die SQL-Anweisungen aus
        $statements = array_filter(array_map('trim', explode(';', $githubSQL)));
        debugLog("Gefundene SQL-Statements:", $statements);
        
        foreach ($statements as $statement) {
            if (empty($statement)) continue;
            
            // Suche nach CREATE TABLE Statements
            if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([^`\s]+)`?\s*\((.*)\)/is', $statement, $matches)) {
                $tableName = trim($matches[1], '`');
                $tableDefinition = $matches[2];
                
                // Prüfe ob Tabelle existiert
                $tableExists = $pdo->query("SHOW TABLES LIKE '$tableName'")->rowCount() > 0;
                
                if (!$tableExists) {
                    // Erstelle neue Tabelle
                    $pdo->exec($statement);
                    $results['tables']['added'][] = $tableName;
                } else {
                    // Hole aktuelle Spalten
                    $currentColumns = $pdo->query("SHOW COLUMNS FROM `$tableName`")->fetchAll(PDO::FETCH_ASSOC);
                    $currentColumnNames = array_column($currentColumns, 'Field');
                    
                    // Parse neue Spalten
                    $columnLines = array_map('trim', preg_split('/,(?![^(]*\))/', $tableDefinition));
                    $newColumns = [];
                    
                    foreach ($columnLines as $line) {
                        if (empty(trim($line))) continue;
                        if (preg_match('/^`?([^`]+)`?\s+(.+)$/i', $line, $colMatch)) {
                            $columnName = trim($colMatch[1], '`');
                            $newColumns[$columnName] = $line;
                        }
                    }
                    
                    $hasChanges = false;
                    
                    // Prüfe auf neue oder geänderte Spalten
                    foreach ($newColumns as $columnName => $definition) {
                        if (!in_array($columnName, $currentColumnNames)) {
                            // Neue Spalte
                            try {
                                $sql = "ALTER TABLE `$tableName` ADD COLUMN $definition";
                                $pdo->exec($sql);
                                $results['columns']['added'][] = "$tableName.$columnName";
                                $hasChanges = true;
                            } catch (PDOException $e) {
                                // Ignoriere Duplikat-Fehler
                                if ($e->getCode() !== '42S21') {
                                    throw $e;
                                }
                                $results['columns']['unchanged'][] = "$tableName.$columnName";
                            }
                        } else {
                            // Vergleiche Spaltendefinition
                            $currentColumn = array_filter($currentColumns, function($col) use ($columnName) {
                                return $col['Field'] === $columnName;
                            });
                            $currentColumn = reset($currentColumn);
                            
                            // Normalisiere Definitionen für Vergleich
                            $normalizedCurrent = strtoupper(preg_replace('/\s+/', ' ', $currentColumn['Type'] . ' ' . 
                                ($currentColumn['Null'] === 'NO' ? 'NOT NULL' : '') . ' ' .
                                ($currentColumn['Default'] !== null ? 'DEFAULT ' . $currentColumn['Default'] : '') . ' ' .
                                $currentColumn['Extra']));
                            
                            $normalizedNew = strtoupper(preg_replace('/\s+/', ' ', $definition));
                            
                            if ($normalizedCurrent !== $normalizedNew) {
                                // Spalte aktualisieren
                                try {
                                    $sql = "ALTER TABLE `$tableName` MODIFY COLUMN $definition";
                                    $pdo->exec($sql);
                                    $results['columns']['updated'][] = "$tableName.$columnName";
                                    $hasChanges = true;
                                } catch (PDOException $e) {
                                    // Ignoriere Duplikat-Fehler
                                    if ($e->getCode() !== '42S21') {
                                        throw $e;
                                    }
                                    $results['columns']['unchanged'][] = "$tableName.$columnName";
                                }
                            } else {
                                $results['columns']['unchanged'][] = "$tableName.$columnName";
                            }
                        }
                    }
                    
                    if ($hasChanges) {
                        $results['tables']['updated'][] = $tableName;
                    } else {
                        $results['tables']['unchanged'][] = $tableName;
                    }
                }
            }
        }
        
    } catch (Exception $e) {
        $results['errors'][] = "Allgemeiner Fehler: " . $e->getMessage();
    }
    
    return $results;
}

// Funktion zum Aktualisieren der Dateien
function updateFiles() {
    $results = [
        'added' => [],
        'updated' => [],
        'removed' => [],
        'errors' => []
    ];
    
    // Liste der geschützten Dateien
    $protectedFiles = [
        'config.php',
        'last_commit.txt'
    ];
    
    try {
        // Hole den GitHub Token aus der Konfiguration
        $githubToken = defined('GITHUB_TOKEN') ? GITHUB_TOKEN : '';
        
        // Hole die Liste der Dateien aus dem Spendenziele-Verzeichnis von GitHub
        $url = 'https://api.github.com/repos/Bittersweet1987/spendenziele/contents/Spendenziele';
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: PHP',
                    'Accept: application/vnd.github.v3+json',
                    'Authorization: token ' . $githubToken
                ]
            ]
        ];
        $context = stream_context_create($opts);
        
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            $error = error_get_last();
            throw new Exception("Konnte Dateiliste nicht von GitHub laden: " . ($error['message'] ?? 'Unbekannter Fehler'));
        }
        
        $files = json_decode($response, true);
        if (!is_array($files)) {
            throw new Exception("Ungültige Antwort von GitHub");
        }
        
        // Hole alle lokalen Dateien (nur im Hauptverzeichnis)
        $localFiles = array_filter(scandir(__DIR__), function($file) {
            return is_file(__DIR__ . '/' . $file) && $file !== '.' && $file !== '..';
        });
        
        // Verarbeite jede Datei
        foreach ($files as $file) {
            if ($file['type'] !== 'file') continue;
            
            $fileName = basename($file['name']); // Stelle sicher, dass wir nur den Dateinamen ohne Pfad verwenden
            $filePath = __DIR__ . '/' . $fileName; // Speichere direkt im Hauptverzeichnis
            
            // Überspringe geschützte Dateien
            if (in_array($fileName, $protectedFiles)) {
                continue;
            }
            
            // Hole den Inhalt der Datei
            $content = @file_get_contents($file['download_url'], false, $context);
            if ($content === false) {
                $error = error_get_last();
                $results['errors'][] = "Konnte Datei nicht laden: " . $fileName . " (" . ($error['message'] ?? 'Unbekannter Fehler') . ")";
                continue;
            }
            
            // Schreibe Datei direkt ins Hauptverzeichnis
            if (file_put_contents($filePath, $content) === false) {
                $results['errors'][] = "Konnte Datei nicht speichern: " . $fileName;
                continue;
            }
            
            // Prüfe ob Datei neu ist oder aktualisiert wurde
            if (!in_array($fileName, $localFiles)) {
                $results['added'][] = $fileName;
            } else {
                $results['updated'][] = $fileName;
            }
            
            // Entferne Datei aus lokaler Liste
            $key = array_search($fileName, $localFiles);
            if ($key !== false) {
                unset($localFiles[$key]);
            }
        }
        
        // Entferne nicht mehr existierende Dateien
        foreach ($localFiles as $file) {
            // Überspringe geschützte Dateien
            if (in_array($file, $protectedFiles) || $file === '.' || $file === '..') {
                continue;
            }
            
            $filePath = __DIR__ . '/' . $file;
            if (is_file($filePath)) {
                if (@unlink($filePath)) {
                    $results['removed'][] = $file;
                } else {
                    $results['errors'][] = "Konnte Datei nicht löschen: " . $file;
                }
            }
        }
        
    } catch (Exception $e) {
        $results['errors'][] = $e->getMessage();
    }
    
    return $results;
}

// Hauptlogik
$error = '';
$status = '';
$updateResults = null;
$fileUpdateResults = null;
$currentCommit = getCurrentCommit();
$latestCommit = getLatestGitHubCommit(true);
$currentStep = isset($_GET['step']) ? (int)$_GET['step'] : 1;

if (isset($_POST['apply_updates']) && $currentStep === 1) {
    $updateResults = applyStructureUpdates($pdo);
    
    if (empty($updateResults['errors'])) {
        $status = "Update erfolgreich durchgeführt";
        $_SESSION['update_results'] = $updateResults;
        $_SESSION['step1_completed'] = true;
    } else {
        $error = "Fehler beim Update: " . implode(", ", $updateResults['errors']);
    }
}

if ($currentStep === 2) {
    // Bereinige alte Ergebnisse beim Start von Schritt 2
    unset($_SESSION['file_update_results']);
    unset($fileUpdateResults);
}

if (isset($_POST['update_files']) && $currentStep === 2) {
    $fileUpdateResults = updateFiles();
    
    if (empty($fileUpdateResults['errors'])) {
        $status = "Datei-Update erfolgreich durchgeführt";
        $_SESSION['file_update_results'] = $fileUpdateResults;
        $_SESSION['step2_completed'] = true;
    } else {
        $error = "Fehler beim Datei-Update: " . implode(", ", $fileUpdateResults['errors']);
    }
}

// Aktualisiere die Commit-Informationen NUR in Schritt 3
if ($currentStep === 3 && isset($latestCommit['hash'])) {
    file_put_contents(__DIR__ . '/last_commit.txt', $latestCommit['hash']);
    $_SESSION['current_commit'] = $latestCommit['hash'];
    $_SESSION['update_timestamp'] = time();
}

// Lade gespeicherte Ergebnisse aus der Session
if (!isset($updateResults) && isset($_SESSION['update_results'])) {
    $updateResults = $_SESSION['update_results'];
}
if (!isset($fileUpdateResults) && isset($_SESSION['file_update_results'])) {
    $fileUpdateResults = $_SESSION['file_update_results'];
}

// Validiere den aktiven Tab basierend auf dem Installationsfortschritt
if ($currentStep > 1 && !isset($_SESSION['step1_completed'])) {
    header('Location: ?step=1');
    exit;
}
if ($currentStep > 2 && !isset($_SESSION['step2_completed'])) {
    header('Location: ?step=2');
    exit;
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
            background-color: #007bff;
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
            background-color: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background-color: #0056b3;
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
        .version-info {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .version-info p {
            margin: 5px 0;
        }
        .version-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 0.9em;
            margin-left: 10px;
        }
        .status-current {
            background-color: #28a745;
            color: white;
        }
        .status-outdated {
            background-color: #dc3545;
            color: white;
        }
        .commit-date {
            color: #666;
            font-size: 0.9em;
            margin-left: 10px;
        }
        .status-message {
            background-color: #e8f5e9;
            border: 1px solid #c8e6c9;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            color: #2e7d32;
        }
        .loading {
            display: none;
            margin: 10px 0;
        }
        .loading.active {
            display: block;
        }
        .structure-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 14px;
        }
        .structure-table th,
        .structure-table td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .structure-table th {
            background-color: #f5f5f5;
        }
        .table-name {
            font-size: 16px;
            font-weight: bold;
            padding: 10px 0;
            margin-top: 20px;
        }
        .status-equal {
            background-color: #e8f5e9;
        }
        .status-different {
            background-color: #ffebee;
        }
        .column-details {
            font-family: monospace;
            white-space: pre-wrap;
        }
        .diff-highlight {
            background-color: #fff3cd;
            padding: 2px 4px;
            border-radius: 2px;
        }
        .accordion-header {
            background-color: #f8f9fa;
            padding: 10px;
            cursor: pointer;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            margin-top: 10px;
        }
        .accordion-header:hover {
            background-color: #e9ecef;
        }
        .accordion-content {
            display: none;
            padding: 15px;
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 4px 4px;
        }
        .accordion-header.active {
            border-radius: 4px 4px 0 0;
        }
        .differences-summary {
            margin: 20px 0;
            padding: 15px;
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }
        .no-differences {
            color: #28a745;
            font-weight: bold;
        }
        .has-differences {
            color: #dc3545;
            font-weight: bold;
        }
        .debug-log {
            background-color: #1e1e1e;
            color: #00ff00;
            font-family: monospace;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            white-space: pre-wrap;
            max-height: 500px;
            overflow-y: auto;
        }
        
        .debug-log-container {
            margin: 20px 0;
        }
        
        .debug-log-toggle {
            background-color: #4CAF50;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 10px;
        }
        
        .debug-log-toggle:hover {
            background-color: #45a049;
        }
        .sql-results {
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        .sql-statement {
            font-family: monospace;
            white-space: pre-wrap;
            margin: 10px 0;
            padding: 10px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .sql-success {
            border-left: 4px solid #28a745;
        }
        .sql-error {
            border-left: 4px solid #dc3545;
        }
        .sql-message {
            margin-top: 5px;
            font-size: 0.9em;
        }
        .sql-success .sql-message {
            color: #28a745;
        }
        .sql-error .sql-message {
            color: #dc3545;
        }
        .file-results {
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        .file-list {
            margin: 10px 0;
            padding: 10px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .file-added {
            color: #28a745;
        }
        .file-updated {
            color: #ffc107;
        }
        .file-removed {
            color: #dc3545;
        }
        .no-changes {
            text-align: center;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 4px;
            margin: 20px 0;
        }
        .no-changes h3 {
            margin-top: 0;
            margin-bottom: 10px;
        }
        .no-changes p {
            margin: 0;
        }
        .summary {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .summary ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .summary li {
            margin: 5px 0;
            font-weight: bold;
        }
        .update-summary {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .summary-section {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .summary-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .summary-section h4 {
            margin-top: 0;
            color: #495057;
        }
        
        .summary-section ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .summary-section li {
            margin: 5px 0;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="steps">
            <div class="step <?php echo $currentStep === 1 ? 'active' : ''; ?>">1. Datenbank Update</div>
            <div class="step <?php echo $currentStep === 2 ? 'active' : ''; ?>">2. Dateien Update</div>
            <div class="step <?php echo $currentStep === 3 ? 'active' : ''; ?>">3. Übersicht</div>
        </div>

        <div class="version-info">
            <h3>Versions-Information</h3>
            <p>
                Installierte Version: 
                <code><?php echo htmlspecialchars($currentCommit['hash']); ?></code>
                <?php if ($currentCommit['date']): ?>
                    <span class="commit-date">
                        (Erstellt am: <?php echo $currentCommit['date']->format('d.m.Y H:i'); ?> Uhr)
                    </span>
                <?php endif; ?>
                <span class="version-status <?php echo ($currentCommit['hash'] === $latestCommit['hash']) ? 'status-current' : 'status-outdated'; ?>">
                    <?php echo ($currentCommit['hash'] === $latestCommit['hash']) ? 'Aktuell' : 'Update verfügbar'; ?>
                </span>
            </p>
            <p>
                Neueste Version: 
                <code><?php echo htmlspecialchars($latestCommit['hash']); ?></code>
                <?php if ($latestCommit['date']): ?>
                    <span class="commit-date">
                        (Erstellt am: <?php echo $latestCommit['date']->format('d.m.Y H:i'); ?> Uhr)
                    </span>
                <?php endif; ?>
            </p>
        </div>

        <?php if ($currentStep === 1): ?>
            <h2>Datenbank Update</h2>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($status): ?>
                <div class="success-message"><?php echo htmlspecialchars($status); ?></div>
            <?php endif; ?>

            <?php if (!isset($_POST['apply_updates'])): ?>
                <form method="post">
                    <button type="submit" name="apply_updates" class="btn btn-primary">Datenbank aktualisieren</button>
                </form>
            <?php endif; ?>

            <?php if (isset($updateResults)): ?>
                <div class="sql-results">
                    <?php if (empty($updateResults['tables']['added']) && empty($updateResults['tables']['updated']) && 
                             empty($updateResults['columns']['added']) && empty($updateResults['columns']['updated'])): ?>
                        <div class="no-changes">
                            <h3>Keine Änderungen erforderlich</h3>
                            <p>Die Datenbankstruktur ist bereits auf dem neuesten Stand.</p>
                        </div>
                    <?php else: ?>
                        <?php if (!empty($updateResults['tables']['added'])): ?>
                            <h3>Neue Tabellen:</h3>
                            <div class="file-list">
                                <?php foreach ($updateResults['tables']['added'] as $table): ?>
                                    <div class="file-added">+ <?php echo htmlspecialchars($table); ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($updateResults['tables']['updated'])): ?>
                            <h3>Aktualisierte Tabellen:</h3>
                            <div class="file-list">
                                <?php foreach ($updateResults['tables']['updated'] as $table): ?>
                                    <div class="file-updated">~ <?php echo htmlspecialchars($table); ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($updateResults['columns']['added'])): ?>
                            <h3>Neue Spalten:</h3>
                            <div class="file-list">
                                <?php foreach ($updateResults['columns']['added'] as $column): ?>
                                    <div class="file-added">+ <?php echo htmlspecialchars($column); ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($updateResults['columns']['updated'])): ?>
                            <h3>Aktualisierte Spalten:</h3>
                            <div class="file-list">
                                <?php foreach ($updateResults['columns']['updated'] as $column): ?>
                                    <div class="file-updated">~ <?php echo htmlspecialchars($column); ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($updateResults['columns']['unchanged'])): ?>
                            <h3>Unveränderte Spalten:</h3>
                            <div class="file-list">
                                <?php foreach ($updateResults['columns']['unchanged'] as $column): ?>
                                    <div class="file-unchanged">= <?php echo htmlspecialchars($column); ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if (empty($updateResults['errors'])): ?>
                    <form method="get">
                        <button type="submit" class="btn btn-primary" name="step" value="2">Weiter</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

        <?php elseif ($currentStep === 2): ?>
            <h2>Dateien Update</h2>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($status): ?>
                <div class="success-message"><?php echo htmlspecialchars($status); ?></div>
            <?php endif; ?>

            <?php if (!isset($_POST['update_files'])): ?>
                <form method="post">
                    <button type="submit" name="update_files" class="btn btn-primary">Update durchführen</button>
                </form>
            <?php endif; ?>

            <?php if (isset($fileUpdateResults)): ?>
                <div class="file-results">
                    <?php if (empty($fileUpdateResults['added']) && empty($fileUpdateResults['updated']) && empty($fileUpdateResults['removed'])): ?>
                        <div class="no-changes">
                            <h3>Keine Änderungen erforderlich</h3>
                            <p>Alle Dateien sind bereits auf dem neuesten Stand.</p>
                        </div>
                    <?php else: ?>
                        <div class="summary">
                            <h3>Zusammenfassung:</h3>
                            <ul>
                                <?php if (!empty($fileUpdateResults['added'])): ?>
                                    <li>Neue Dateien: <?php echo count($fileUpdateResults['added']); ?></li>
                                <?php endif; ?>
                                <?php if (!empty($fileUpdateResults['updated'])): ?>
                                    <li>Aktualisierte Dateien: <?php echo count($fileUpdateResults['updated']); ?></li>
                                <?php endif; ?>
                                <?php if (!empty($fileUpdateResults['removed'])): ?>
                                    <li>Gelöschte Dateien: <?php echo count($fileUpdateResults['removed']); ?></li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <?php if (!empty($fileUpdateResults['added'])): ?>
                            <h3>Neue Dateien:</h3>
                            <div class="file-list">
                                <?php foreach ($fileUpdateResults['added'] as $file): ?>
                                    <div class="file-added">+ <?php echo htmlspecialchars($file); ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($fileUpdateResults['updated'])): ?>
                            <h3>Aktualisierte Dateien:</h3>
                            <div class="file-list">
                                <?php foreach ($fileUpdateResults['updated'] as $file): ?>
                                    <div class="file-updated">~ <?php echo htmlspecialchars($file); ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($fileUpdateResults['removed'])): ?>
                            <h3>Gelöschte Dateien:</h3>
                            <div class="file-list">
                                <?php foreach ($fileUpdateResults['removed'] as $file): ?>
                                    <div class="file-removed">- <?php echo htmlspecialchars($file); ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if (empty($fileUpdateResults['errors'])): ?>
                    <form method="get">
                        <button type="submit" class="btn btn-primary" name="step" value="3">Weiter</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

        <?php elseif ($currentStep === 3): ?>
            <?php
            // Aktualisiere die Commit-Informationen sofort bei Aufruf von Schritt 3
            if (isset($latestCommit['hash'])) {
                file_put_contents(__DIR__ . '/last_commit.txt', $latestCommit['hash']);
                $_SESSION['current_commit'] = $latestCommit['hash'];
                $_SESSION['update_timestamp'] = time();
            }
            ?>
            <script>
            // Funktion zum Prüfen des Update-Status
            function checkUpdateStatus() {
                fetch('check_version.php?type=update')
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'updated') {
                            // Wenn das Update abgeschlossen ist, Seite neu laden
                            window.location.reload();
                        }
                    })
                    .catch(error => console.error('Fehler beim Prüfen des Update-Status:', error));
            }

            // Prüfe alle 10 Sekunden den Status
            setInterval(checkUpdateStatus, 10000);
            </script>

            <h2>Übersicht</h2>
            
            <?php if (isset($updateResults) || isset($fileUpdateResults)): ?>
                <div class="update-summary">
                    <h3>Zusammenfassung des Updates</h3>
                    
                    <?php if (isset($updateResults)): ?>
                        <div class="summary-section">
                            <h4>Datenbank-Update:</h4>
                            <?php if (empty($updateResults['tables']['added']) && empty($updateResults['tables']['updated']) && 
                                     empty($updateResults['columns']['added']) && empty($updateResults['columns']['updated'])): ?>
                                <p>Keine Änderungen an der Datenbankstruktur erforderlich.</p>
                            <?php else: ?>
                                <ul>
                                    <?php if (!empty($updateResults['tables']['added'])): ?>
                                        <li>Neue Tabellen: <?php echo count($updateResults['tables']['added']); ?></li>
                                    <?php endif; ?>
                                    <?php if (!empty($updateResults['tables']['updated'])): ?>
                                        <li>Aktualisierte Tabellen: <?php echo count($updateResults['tables']['updated']); ?></li>
                                    <?php endif; ?>
                                    <?php if (!empty($updateResults['columns']['added'])): ?>
                                        <li>Neue Spalten: <?php echo count($updateResults['columns']['added']); ?></li>
                                    <?php endif; ?>
                                    <?php if (!empty($updateResults['columns']['updated'])): ?>
                                        <li>Aktualisierte Spalten: <?php echo count($updateResults['columns']['updated']); ?></li>
                                    <?php endif; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($fileUpdateResults)): ?>
                        <div class="summary-section">
                            <h4>Datei-Update:</h4>
                            <?php if (empty($fileUpdateResults['added']) && empty($fileUpdateResults['updated']) && empty($fileUpdateResults['removed'])): ?>
                                <p>Keine Änderungen an den Dateien erforderlich.</p>
                            <?php else: ?>
                                <ul>
                                    <?php if (!empty($fileUpdateResults['added'])): ?>
                                        <li>Neue Dateien: <?php echo count($fileUpdateResults['added']); ?></li>
                                    <?php endif; ?>
                                    <?php if (!empty($fileUpdateResults['updated'])): ?>
                                        <li>Aktualisierte Dateien: <?php echo count($fileUpdateResults['updated']); ?></li>
                                    <?php endif; ?>
                                    <?php if (!empty($fileUpdateResults['removed'])): ?>
                                        <li>Gelöschte Dateien: <?php echo count($fileUpdateResults['removed']); ?></li>
                                    <?php endif; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="admin_panel.php">
                <input type="hidden" name="update_commit" value="<?php echo htmlspecialchars($latestCommit['hash']); ?>">
                <button type="submit" class="btn btn-primary">Zurück zum Adminmenü</button>
            </form>
            
            <?php
            // Speichere die Login-Informationen temporär
            $admin_id = $_SESSION['admin_id'] ?? null;
            
            // Bereinige die Session nach erfolgreichem Update
            unset($_SESSION['update_results']);
            unset($_SESSION['file_update_results']);
            unset($_SESSION['step1_completed']);
            unset($_SESSION['step2_completed']);
            unset($_SESSION['current_step']);
            
            // Stelle die Login-Informationen wieder her
            if ($admin_id !== null) {
                $_SESSION['admin_id'] = $admin_id;
            }
            ?>
        <?php endif; ?>
    </div>
</body>
</html> 