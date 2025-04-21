<?php
session_start();

class Installer {
    private $totalSteps = 3;
    private $currentStep = 1;
    private $configTemplate = '<?php
// Datenbankverbindung
$host = "{HOST}";
$port = "{PORT}";
$dbname = "{DBNAME}";
$username = "{USERNAME}";
$password = "{PASSWORD}";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Verbindung fehlgeschlagen: " . $e->getMessage());
}';

    public function __construct() {
        if (!isset($_GET['action'])) {
            // Debug-Log nur anzeigen, wenn keine AJAX-Anfrage
            $this->debugLog("Session Status: " . print_r($_SESSION, true));
        }
        if (isset($_GET['reset'])) {
            $this->resetInstallation();
        }
    }

    public function run() {
        // Prüfe zuerst, ob es sich um eine AJAX-Anfrage handelt
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'install_files') {
            $this->installFiles();
            return;
        }

        $this->showHeader("Installation");
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['test_connection'])) {
                $this->testDatabaseConnection();
            } elseif (isset($_POST['create_admin'])) {
                $this->createAdmin();
            }
        }

        // Zeige alle Tab-Inhalte, aber nur der aktive ist sichtbar
        ?>
        <div class="tab-content <?php echo !isset($_SESSION['db_configured']) ? 'active' : ''; ?>" id="tab1">
            <?php $this->showDatabaseTab(); ?>
        </div>
        
        <div class="tab-content <?php echo isset($_SESSION['db_configured']) && !isset($_SESSION['admin_created']) ? 'active' : ''; ?>" id="tab2">
            <?php $this->showAdminTab(); ?>
        </div>
        
        <div class="tab-content <?php echo isset($_SESSION['admin_created']) ? 'active' : ''; ?>" id="tab3">
            <?php $this->showInstallTab(); ?>
        </div>
        <?php

        $this->showFooter();
    }

    private function showDatabaseTab() {
        if (isset($_SESSION['error'])) {
            echo '<div class="message error">' . htmlspecialchars($_SESSION['error']) . '</div>';
            unset($_SESSION['error']);
        }
        if (isset($_SESSION['success'])) {
            echo '<div class="message success">' . htmlspecialchars($_SESSION['success']) . '</div>';
            unset($_SESSION['success']);
        }

        // Verwende gespeicherte Konfiguration oder vorhandene config.php
        $config = isset($_SESSION['db_config']) ? $_SESSION['db_config'] : $this->readExistingConfig();
        ?>
        <h2>Datenbankkonfiguration</h2>
        <form method="post" class="database-form">
            <div class="form-group">
                <label for="db_host">Datenbank Host:</label>
                <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($config['host'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="db_port">Datenbank Port:</label>
                <input type="text" id="db_port" name="db_port" value="<?php echo htmlspecialchars($config['port'] ?? '3306'); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="db_name">Datenbankname:</label>
                <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($config['dbname'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="db_user">Datenbank Benutzer:</label>
                <input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars($config['username'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="db_pass">Datenbank Passwort:</label>
                <input type="password" id="db_pass" name="db_pass" value="<?php echo htmlspecialchars($config['password'] ?? ''); ?>" required>
            </div>
            
            <div class="button-group">
                <button type="submit" name="test_connection" class="button">Verbindung testen</button>
                <?php if (isset($_SESSION['db_configured']) && $_SESSION['db_configured'] === true): ?>
                    <a href="?step=2" class="button button-next">Weiter</a>
                <?php endif; ?>
            </div>
        </form>
        <?php
    }

    private function showAdminTab() {
        if (isset($_SESSION['error'])) {
            echo '<div class="message error">' . htmlspecialchars($_SESSION['error']) . '</div>';
            unset($_SESSION['error']);
        }
        if (isset($_SESSION['success'])) {
            echo '<div class="message success">' . htmlspecialchars($_SESSION['success']) . '</div>';
            unset($_SESSION['success']);
        }

        $adminData = $_SESSION['admin_data'] ?? [];
        ?>
        <h2>Admin-Account erstellen</h2>
        <form method="post" class="admin-form">
            <div class="form-group">
                <label for="admin_user">Benutzername:</label>
                <input type="text" id="admin_user" name="admin_user" value="<?php echo htmlspecialchars($adminData['username'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="admin_pass">Passwort:</label>
                <input type="password" id="admin_pass" name="admin_pass" required>
            </div>
            
            <div class="button-group">
                <button type="submit" name="create_admin" class="button">Admin erstellen</button>
                <?php if (isset($_SESSION['admin_created']) && $_SESSION['admin_created'] === true): ?>
                    <a href="?step=3" class="button button-next">Weiter</a>
                <?php endif; ?>
            </div>
        </form>
        <?php
    }

    private function showInstallTab() {
        if (isset($_SESSION['error'])) {
            echo '<div class="message error">' . htmlspecialchars($_SESSION['error']) . '</div>';
            unset($_SESSION['error']);
        }
        if (isset($_SESSION['success'])) {
            echo '<div class="message success">' . htmlspecialchars($_SESSION['success']) . '</div>';
            unset($_SESSION['success']);
        }
        ?>
        <h2>Installation abschließen</h2>
        <div class="message success">
            <p>✅ Datenbank erfolgreich konfiguriert</p>
            <p>✅ Admin-Account erstellt</p>
        </div>

        <div class="admin-info">
            <h3>Ihre Admin-Zugangsdaten</h3>
            <div class="info-box">
                <p><strong>Benutzername:</strong> <?php echo htmlspecialchars($_SESSION['admin_data']['username'] ?? ''); ?></p>
                <p><strong>Passwort:</strong> <?php echo htmlspecialchars($_SESSION['admin_data']['password'] ?? ''); ?></p>
                <p class="warning"><em>⚠️ Bitte notieren Sie sich diese Zugangsdaten! Das Passwort wird aus Sicherheitsgründen nur einmal angezeigt!</em></p>
            </div>
        </div>

        <div class="installation-progress">
            <h3>Installation der Dateien</h3>
            <div id="progress-container">
                <div id="progress-bar"></div>
                <div id="progress-text">0%</div>
            </div>
            <div id="file-list"></div>
        </div>

        <button onclick="startInstallation()" id="start-install" class="button">Installation starten</button>

        <script>
        function startInstallation() {
            document.getElementById('start-install').disabled = true;
            document.getElementById('start-install').innerHTML = 'Installation läuft...';
            document.getElementById('file-list').innerHTML = '';
            
            fetch('?action=install_files', {
                method: 'POST'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Server response:', text);
                        throw new Error('Ungültige Server-Antwort: ' + text);
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    // Fortschrittsbalken auf 100%
                    document.getElementById('progress-bar').style.width = '100%';
                    document.getElementById('progress-text').innerHTML = '100%';
                    
                    // Dateien auflisten
                    if (data.files && data.files.length > 0) {
                        data.files.forEach(file => {
                            document.getElementById('file-list').innerHTML += 
                                '<p class="success">' + file + '</p>';
                        });
                    }
                    
                    document.getElementById('file-list').innerHTML += 
                        '<p class="success">✅ Installation erfolgreich abgeschlossen!</p>';
                    
                    // Redirect nach 2 Sekunden zur Login-Seite
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);
                } else {
                    throw new Error(data.error || 'Installation fehlgeschlagen');
                }
            })
            .catch(error => {
                console.error('Installation error:', error);
                document.getElementById('file-list').innerHTML += 
                    '<p class="error">❌ Fehler: ' + error.message + '</p>';
                document.getElementById('start-install').disabled = false;
                document.getElementById('start-install').innerHTML = 'Installation erneut versuchen';
                // Fortschrittsbalken zurücksetzen
                document.getElementById('progress-bar').style.width = '0%';
                document.getElementById('progress-text').innerHTML = '0%';
            });
        }
        </script>
        <?php
    }

    private function showFooter() {
        ?>
            </div>
        </body>
        </html>
        <?php
    }

    private function testDatabaseConnection() {
        $host = $_POST['db_host'] ?? '';
        $port = $_POST['db_port'] ?? '3306';
        $dbname = $_POST['db_name'] ?? '';
        $username = $_POST['db_user'] ?? '';
        $password = $_POST['db_pass'] ?? '';

        try {
            $dsn = "mysql:host=$host;port=$port";
            $pdo = new PDO($dsn, $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbname'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
            }
            
            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8";
            $pdo = new PDO($dsn, $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $configContent = str_replace(
                ['{HOST}', '{PORT}', '{DBNAME}', '{USERNAME}', '{PASSWORD}'],
                [$host, $port, $dbname, $username, $password],
                $this->configTemplate
            );
            
            if (file_put_contents('config.php', $configContent) === false) {
                throw new Exception("Konnte config.php nicht erstellen");
            }

            $_SESSION['db_configured'] = true;
            $_SESSION['success'] = "Datenbankverbindung erfolgreich hergestellt!";
            $_SESSION['db_config'] = [
                'host' => $host,
                'port' => $port,
                'dbname' => $dbname,
                'username' => $username,
                'password' => $password
            ];
        } catch (PDOException $e) {
            $_SESSION['error'] = "Datenbankfehler: " . $e->getMessage();
        } catch (Exception $e) {
            $_SESSION['error'] = "Fehler: " . $e->getMessage();
        }
        
        // Anstatt einer Weiterleitung zeigen wir die gleiche Seite mit den Werten
        $this->showDatabaseTab();
        exit();
    }

    private function createAdmin() {
        if (empty($_POST['admin_user']) || empty($_POST['admin_pass'])) {
            $_SESSION['error'] = "Bitte fülle alle Felder aus!";
            $_SESSION['admin_data'] = [
                'username' => $_POST['admin_user'] ?? ''
            ];
            return;
        }

        try {
            require_once 'config.php';
            
            $pdo->exec("CREATE TABLE IF NOT EXISTS admin (
                id INT AUTO_INCREMENT PRIMARY KEY,
                benutzername VARCHAR(255) NOT NULL UNIQUE,
                passwort_hash VARCHAR(255) NOT NULL,
                erstellt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            $username = $_POST['admin_user'];
            $password = $_POST['admin_pass'];
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO admin (benutzername, passwort_hash) VALUES (?, ?)");
            $stmt->execute([$username, $password_hash]);

            $_SESSION['admin_created'] = true;
            $_SESSION['success'] = "Admin-Account erfolgreich erstellt!";
            $_SESSION['admin_data'] = [
                'username' => $username,
                'password' => $password
            ];
        } catch (Exception $e) {
            $_SESSION['error'] = "Fehler beim Erstellen des Admin-Accounts: " . $e->getMessage();
            $_SESSION['admin_data'] = [
                'username' => $_POST['admin_user']
            ];
        }
    }

    private function installFiles() {
        if (ob_get_level()) ob_end_clean();
        ob_start();
        header('Content-Type: application/json');
            
        try {
            // GitHub Repository Informationen
            $owner = 'Bittersweet1987'; // Ihr GitHub Benutzername
            $repo = 'spendenziele'; // Name des Repositories
            $branch = 'main'; // oder 'master', je nachdem welchen Branch Sie nutzen
            $apiUrl = "https://api.github.com/repos/{$owner}/{$repo}/zipball/{$branch}";
            
            // Temporärer Ordner für den Download
            $tempFile = tempnam(sys_get_temp_dir(), 'gh_');
            $tempDir = $tempFile . '_extracted';
            
            // Download der ZIP-Datei von GitHub
            $zipContent = $this->downloadFromGitHub($apiUrl);
            if (!$zipContent) {
                throw new Exception("Konnte die Dateien nicht von GitHub herunterladen.");
            }
            
            // Speichern der ZIP-Datei
            file_put_contents($tempFile, $zipContent);
            
            // Erstellen des temporären Extraktionsordners
            if (!mkdir($tempDir, 0755, true)) {
                throw new Exception("Konnte temporären Ordner nicht erstellen.");
            }
            
            // Entpacken der ZIP-Datei
            $zip = new ZipArchive;
            if ($zip->open($tempFile) !== true) {
                throw new Exception("Konnte ZIP-Datei nicht öffnen.");
            }
            
            $zip->extractTo($tempDir);
            $zip->close();
            
            // Finden des extrahierten Verzeichnisses (GitHub fügt einen zusätzlichen Ordner hinzu)
            $extractedFolders = glob($tempDir . '/*', GLOB_ONLYDIR);
            if (empty($extractedFolders)) {
                throw new Exception("Keine Dateien im ZIP-Archiv gefunden.");
            }
            $sourceDir = $extractedFolders[0];
            
            // Kopieren der Dateien
            $fileList = [];
            $targetDir = __DIR__;
            
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $file) {
                $relativePath = substr($file->getPathname(), strlen($sourceDir) + 1);
                if ($relativePath === '') continue;
                
                $targetPath = $targetDir . DIRECTORY_SEPARATOR . $relativePath;
                
                if ($file->isDir()) {
                    if (!file_exists($targetPath)) {
                        if (!@mkdir($targetPath, 0755, true)) {
                            throw new Exception("Konnte Verzeichnis nicht erstellen: " . $relativePath);
                        }
                        $fileList[] = "✓ Verzeichnis erstellt: " . $relativePath;
                    }
                } else {
                    // Überspringe install.php und config.php
                    if (in_array(basename($file), ['install.php', 'config.php'])) {
                        continue;
                    }
                    
                    if (!@copy($file, $targetPath)) {
                        throw new Exception("Konnte Datei nicht kopieren: " . $relativePath);
                    }
                    $fileList[] = "✓ Datei kopiert: " . $relativePath;
                }
            }
            
            // Aufräumen
            @unlink($tempFile);
            $this->deleteDirectory($tempDir);
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Installation erfolgreich abgeschlossen',
                'files' => $fileList
            ]);
            
        } catch (Exception $e) {
            ob_end_clean();
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
    
    private function downloadFromGitHub($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PHP Installer');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/vnd.github.v3+json'
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("GitHub API Fehler (HTTP $httpCode)");
        }
        
        return $result;
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

    private function readExistingConfig() {
        if (file_exists('config.php')) {
            $content = file_get_contents('config.php');
            $config = [];
            
            if (preg_match('/\$host\s*=\s*"([^"]+)"/', $content, $matches)) {
                $config['host'] = $matches[1];
            }
            if (preg_match('/\$port\s*=\s*"([^"]+)"/', $content, $matches)) {
                $config['port'] = $matches[1];
            }
            if (preg_match('/\$dbname\s*=\s*"([^"]+)"/', $content, $matches)) {
                $config['dbname'] = $matches[1];
            }
            if (preg_match('/\$username\s*=\s*"([^"]+)"/', $content, $matches)) {
                $config['username'] = $matches[1];
            }
            if (preg_match('/\$password\s*=\s*"([^"]+)"/', $content, $matches)) {
                $config['password'] = $matches[1];
            }
            
            return $config;
        }
        return [];
    }

    private function debugLog($message) {
        if (isset($_GET['action']) && $_GET['action'] === 'install_files') {
            // Keine Debug-Ausgabe während der AJAX-Anfrage
            error_log($message);
            return;
        }
        echo "<script>console.log(" . json_encode($message) . ");</script>";
    }

    private function showHeader($title) {
        ?>
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Installation - <?php echo htmlspecialchars($title); ?></title>
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
                    margin-right: 5px;
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
                .tab.available {
                    opacity: 1;
                    cursor: pointer;
                }
                .tab-content {
                    display: none;
                    padding: 20px;
                    background-color: white;
                    border-radius: 0 0 4px 4px;
                }
                .tab-content.active {
                    display: block;
                }
                .form-group {
                    margin-bottom: 15px;
                }
                label {
                    display: block;
                    margin-bottom: 5px;
                    font-weight: bold;
                }
                input[type="text"],
                input[type="password"] {
                    width: 100%;
                    padding: 8px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    box-sizing: border-box;
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
                }
                .button:hover {
                    background-color: #45a049;
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
                .reset-button {
                    position: fixed;
                    top: 10px;
                    right: 10px;
                    background-color: #f44336;
                }
                .reset-button:hover {
                    background-color: #d32f2f;
                }
                .button-group {
                    display: flex;
                    gap: 10px;
                    align-items: center;
                }
                .button-next {
                    background-color: #2196F3;
                }
                .button-next:hover {
                    background-color: #1976D2;
                }
                .info-box {
                    background-color: #f8f9fa;
                    border: 1px solid #dee2e6;
                    border-radius: 4px;
                    padding: 15px;
                    margin: 15px 0;
                }
                .admin-info {
                    margin: 20px 0;
                }
                #progress-container {
                    width: 100%;
                    height: 20px;
                    background-color: #f0f0f0;
                    border-radius: 10px;
                    margin: 10px 0;
                    position: relative;
                    overflow: hidden;
                }
                #progress-bar {
                    width: 0%;
                    height: 100%;
                    background-color: #4CAF50;
                    transition: width 0.3s ease;
                }
                #progress-text {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    color: #000;
                    font-weight: bold;
                }
                #file-list {
                    margin-top: 10px;
                    max-height: 200px;
                    overflow-y: auto;
                    font-family: monospace;
                    font-size: 14px;
                }
                #file-list p {
                    margin: 5px 0;
                    padding: 5px;
                    border-radius: 3px;
                }
                #file-list p.success {
                    color: #155724;
                    background-color: #d4edda;
                    border: 1px solid #c3e6cb;
                }
                #file-list p.error {
                    color: #721c24;
                    background-color: #f8d7da;
                    border: 1px solid #f5c6cb;
                }
                .installation-progress {
                    margin: 20px 0;
                }
                .warning {
                    color: #856404;
                    background-color: #fff3cd;
                    border: 1px solid #ffeeba;
                    padding: 10px;
                    border-radius: 4px;
                    margin-top: 15px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="tabs">
                    <div class="tab <?php echo !isset($_SESSION['db_configured']) ? 'active' : 'completed'; ?>">
                        1. Datenbank
                    </div>
                    <div class="tab <?php echo isset($_SESSION['db_configured']) && !isset($_SESSION['admin_created']) ? 'active' : (isset($_SESSION['admin_created']) ? 'completed' : ''); ?>">
                        2. Admin
                    </div>
                    <div class="tab <?php echo isset($_SESSION['admin_created']) ? 'active' : ''; ?>">
                        3. Installation
                    </div>
                </div>
                <a href="?reset=1" class="button reset-button" onclick="return confirm('Sind Sie sicher? Alle Datenbanktabellen werden gelöscht und die Installation wird zurückgesetzt!');">Installation zurücksetzen</a>
        <?php
    }

    private function resetInstallation() {
        try {
            // Wenn eine Datenbankkonfiguration existiert, versuchen wir die Tabellen zu löschen
            if (file_exists('config.php')) {
                require_once 'config.php';
                
                // Liste aller Tabellen, die gelöscht werden sollen
                $tables = [
                    'admin',
                    'spendenziele',
                    'spenden',
                    // Fügen Sie hier weitere Tabellen hinzu, falls vorhanden
                ];
                
                foreach ($tables as $table) {
                    try {
                        $pdo->exec("DROP TABLE IF EXISTS `$table`");
                    } catch (Exception $e) {
                        // Fehler beim Löschen einzelner Tabellen ignorieren
                        $this->debugLog("Fehler beim Löschen der Tabelle $table: " . $e->getMessage());
                    }
                }
                
                // config.php löschen
                unlink('config.php');
            }
        } catch (Exception $e) {
            $this->debugLog("Fehler beim Zurücksetzen der Datenbank: " . $e->getMessage());
        }

        // Session zurücksetzen
        session_unset();
        session_destroy();
        session_start();
        
        // Zur ersten Seite zurückleiten
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

$installer = new Installer();
$installer->run();
?> 