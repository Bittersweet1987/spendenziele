<?php
session_start();

// Prüfe auf Installation abschließen
if (isset($_POST['finish_installation'])) {
    $redirect_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']) . "/admin_panel.php";
    
    // Lösche die Installationsdatei
    @unlink(__FILE__);
    
    // JavaScript-Weiterleitung als Fallback
    echo "<script>window.location.href = '" . $redirect_url . "';</script>";
    
    // Header-Weiterleitung
    header("Location: " . $redirect_url);
    exit;
}

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

        // Setze Installation als abgeschlossen wenn angefordert
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['set_complete'])) {
            $_SESSION['installation_complete'] = true;
            exit;
        }

        $this->showHeader("Installation");
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['test_connection'])) {
                $this->testDatabaseConnection();
            } elseif (isset($_POST['create_admin'])) {
                $this->createAdmin();
            } elseif (isset($_POST['create_structure'])) {
                $this->createDatabaseStructure();
            }
        }

        // Bestimme den aktiven Tab basierend auf GET-Parameter oder Session-Status
        $activeTab = isset($_GET['step']) ? (int)$_GET['step'] : 1;
        
        // Validiere den aktiven Tab basierend auf dem Installationsfortschritt
        if ($activeTab > 1 && !isset($_SESSION['db_configured'])) {
            $activeTab = 1;
        }
        if ($activeTab > 2 && !isset($_SESSION['admin_created'])) {
            $activeTab = 2;
        }
        if ($activeTab > 3 && !isset($_SESSION['db_structure_created'])) {
            $activeTab = 3;
        }
        if ($activeTab > 4 && !isset($_SESSION['installation_complete'])) {
            $activeTab = 4;
        }

        // Zeige alle Tab-Inhalte
        ?>
        <div class="tab-content <?php echo $activeTab === 1 ? 'active' : ''; ?>" id="tab1">
            <?php $this->showDatabaseTab(); ?>
        </div>
        
        <div class="tab-content <?php echo $activeTab === 2 ? 'active' : ''; ?>" id="tab2">
            <?php $this->showAdminTab(); ?>
        </div>

        <div class="tab-content <?php echo $activeTab === 3 ? 'active' : ''; ?>" id="tab3">
            <?php $this->showDatabaseStructureTab(); ?>
        </div>
        
        <div class="tab-content <?php echo $activeTab === 4 ? 'active' : ''; ?>" id="tab4">
            <?php $this->showInstallTab(); ?>
        </div>

        <div class="tab-content <?php echo $activeTab === 5 ? 'active' : ''; ?>" id="tab5">
            <?php $this->showOverviewTab(); ?>
        </div>

        <div class="tab-content <?php echo $activeTab === 6 ? 'active' : ''; ?>" id="tab6">
            <?php $this->showOBSTab(); ?>
        </div>

        <div class="tab-content <?php echo $activeTab === 7 ? 'active' : ''; ?>" id="tab7">
            <?php $this->showFinalOverviewTab(); ?>
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

        // Verwende gespeicherte Eingaben, Konfiguration oder vorhandene config.php
        $values = $_SESSION['db_input'] ?? ($_SESSION['db_config'] ?? $this->readExistingConfig());
        ?>
        <h2>Datenbankkonfiguration</h2>
        <form method="post" class="database-form">
            <div class="form-group">
                <label for="db_host">Datenbank Host:</label>
                <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($values['host'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="db_port">Datenbank Port:</label>
                <input type="text" id="db_port" name="db_port" value="<?php echo htmlspecialchars($values['port'] ?? '3306'); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="db_name">Datenbankname:</label>
                <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($values['dbname'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="db_user">Datenbank Benutzer:</label>
                <input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars($values['username'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="db_pass">Datenbank Passwort:</label>
                <input type="password" id="db_pass" name="db_pass" value="<?php echo htmlspecialchars($values['password'] ?? ''); ?>" required>
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

        ?>
        <h2>Admin-Account erstellen</h2>
        <form method="post" class="admin-form">
            <div class="form-group">
                <label for="admin_user">Benutzername:</label>
                <input type="text" id="admin_user" name="admin_user" value="<?php echo htmlspecialchars($_SESSION['admin_data']['username'] ?? ''); ?>" required>
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

        <?php if (isset($_SESSION['admin_created']) && $_SESSION['admin_created'] === true): ?>
            <div class="admin-info">
                <h3>Ihre Admin-Zugangsdaten</h3>
                <div class="info-box">
                    <p><strong>Benutzername:</strong> <?php echo htmlspecialchars($_SESSION['admin_data']['username'] ?? ''); ?></p>
                    <p><strong>Passwort:</strong> <?php echo htmlspecialchars($_SESSION['admin_data']['password'] ?? ''); ?></p>
                    <p class="warning"><em>⚠️ Bitte notieren Sie sich diese Zugangsdaten! Das Passwort wird aus Sicherheitsgründen nur einmal angezeigt!</em></p>
                </div>
            </div>
        <?php endif; ?>
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
        <h2>Dateien installieren</h2>

        <div class="installation-progress">
            <h3>Installation der Dateien</h3>
            <div id="progress-container">
                <div id="progress-bar"></div>
                <div id="progress-text">0%</div>
            </div>
            <div id="file-list"></div>
        </div>

        <div class="button-group">
            <?php if (!isset($_SESSION['installation_complete'])): ?>
                <button onclick="startInstallation()" id="start-install" class="button">Installation starten</button>
            <?php else: ?>
                <button disabled class="button">Installation abgeschlossen</button>
                <a href="?step=7" class="button button-next">Weiter</a>
            <?php endif; ?>
        </div>

        <script>
        function startInstallation() {
            // Button deaktivieren und Text ändern
            const startButton = document.getElementById('start-install');
            startButton.disabled = true;
            startButton.innerHTML = 'Installation läuft...';
            
            // Fortschrittsanzeige zurücksetzen
            const progressBar = document.getElementById('progress-bar');
            const progressText = document.getElementById('progress-text');
            const fileList = document.getElementById('file-list');
            
            progressBar.style.width = '0%';
            progressText.innerHTML = '0%';
            fileList.innerHTML = '<p>Starte Installation...</p>';
            
            // Installation starten
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
                    // Fortschrittsbalken aktualisieren
                    progressBar.style.width = '100%';
                    progressText.innerHTML = '100%';
                    
                    // Dateien auflisten
                    fileList.innerHTML = ''; // Liste leeren
                    if (data.files && data.files.length > 0) {
                        let filesProcessed = 0;
                        const totalFiles = data.files.length;
                        
                        data.files.forEach((file, index) => {
                            // Verzögerte Anzeige der Dateien für bessere Visualisierung
                            setTimeout(() => {
                                fileList.innerHTML += '<p class="success">' + file + '</p>';
                                fileList.scrollTop = fileList.scrollHeight; // Auto-scroll nach unten
                                filesProcessed++;
                                
                                // Wenn alle Dateien verarbeitet wurden
                                if (filesProcessed === totalFiles) {
                                    // Abschlussmeldung hinzufügen
                                    fileList.innerHTML += '<p class="success">✅ Installation erfolgreich abgeschlossen!</p>';
                                    fileList.scrollTop = fileList.scrollHeight;
                                    
                                    // Installation als abgeschlossen markieren
                                    fetch('?set_complete=1', {
                                        method: 'POST'
                                    })
                                    .then(() => {
                                        // Seite neu laden um den Weiter-Button anzuzeigen
                                        window.location.reload();
                                    });
                                }
                            }, index * 50);
                        });
                    }
                } else {
                    throw new Error(data.error || 'Installation fehlgeschlagen');
                }
            })
            .catch(error => {
                console.error('Installation error:', error);
                fileList.innerHTML = '<p class="error">❌ Fehler: ' + error.message + '</p>';
                startButton.disabled = false;
                startButton.innerHTML = 'Installation erneut versuchen';
                progressBar.style.width = '0%';
                progressText.innerHTML = '0%';
            });
        }
        </script>
        <?php
    }

    private function showOverviewTab() {
        ?>
        <h2>Streamerbot Integration</h2>
        
        <div class="streamerbot-setup">
            <p>Um die Spendenziele mit Streamerbot zu verbinden, müssen Sie die folgende Konfiguration in Ihren Streamerbot importieren.</p>
            
            <div class="setup-steps">
                <h3>Installationsschritte:</h3>
                <ol>
                    <li>Öffnen Sie Streamerbot</li>
                    <li>Klicken Sie auf "Import" im Hauptmenü</li>
                    <li>Wählen Sie "Import from Clipboard"</li>
                    <li>Kopieren Sie den folgenden Code (Klicken Sie auf "Code kopieren"):</li>
                </ol>
            </div>

            <div class="code-box">
                <pre id="sbCode">U0JBRR+LCAAAAAAABADVWltv4kgWfl9p/kM2q3nqddpXwCONVoFwMSQkGLDBm9HKZRfGUL40tiFmNG/7z/aP7SnfgAA9mZ5ZabalVoBTrjqX73znlKt+/u4vNze3Ho7N2x9ufqZf4Ktvehi+3j4Evhm7gb93McG3fy+kZhIvgw2VN904xptoh3HMyY16NWILP8JjdAh3x1U/2ziyNm4YF6LjCQM18e+tQuInhJQyz/VdL/G0akYqpLJfshG3tnmiuZnNEcEv/8x/uSlFmdi16cKyKbLYZhtMrS5wjGijBdPAgsQsLI7nRSSgRgOVymWPfUlwgk8Vy37HvokIpnPGmwSfSN4skti4swm8nhvFwSaFQQuTRNdGvWDfdn3n0qhL0ThRz9kESZgZxrI3zM04Nu04CmFCfDLMJDszjcDRF/TdmL4deFUEznSwAt9KNhvsx5ek8cZ1HIjQsdvfuT7HhblxqceiQ8SOpLEbTiO8Kc19Mt9unpIIAOaZvn9syWH8vRckmUq3vHTHspcHPeEoMp3chRsc2fjKZK3MQItG6rY9VW9Px/zy7pkCSwuWRbaEESPUecSIJmsxDbneYEQJc5a8EAUkNd4vdxunIVWnzvLvJVcRlUspXqIS4D8dS0/Ve+94yCJ4guHu2PdTeubbFUluH8+CHVZjwdRtWQCrGhKDargBuSJwXK1miSYnXLOP41nuT28gBEusczJipDoPYeNFm0GWyDP2YsEv7Bq2TF66ZuD/g32owZoL1hSZmiRZjFivmYxp2QJTs3mpIdRlC9frVwPIsn+khYcvP53w0jlnX3LBr9FgNuhKiankG7zANMnx2XKZuPXD66vuAhXuotfXJ9faBFGwiO+G7cnra2cDCuyCzbomvr5uxTv2TmAFTn599SIr2BAX3dnkTKFvnXOcAvF5d0Mc3/XiOLwy9d3r6xDvYnAenbEfBf7/QIerSxfyCX47Wvt04E/v/Y/SGLcCOwtka6btTV3ylc5QsgSVoEk0bDmEs3iSGGlzgmdD1tDZROXftnOvE5mzcGl3yRatLo+beBr7uF6ydu++/jAK+vZsGCLPcqYC2dtdLX4kKsG9UTL1NN/W35bWWhOtXp9YAozjpf3zjh2c6TSWpiaMmesqnSvRup29KTx9df6xPyx0pPM1ubn3Fs7T5grBs1bafJi2l30EvyFvSu3I5joa5yJejpS2Jhr6kLO702AwgnlGxbjRevuYNjugY2DohEVdsjK7U0fzOqmpd1zU1ZJ8HOjU62f6gZ7E8qQl0qfOy/jeVTLd1v2Wrm5tvR8Z46ZrzNR0rltUvntc3dffz4E9QlDrXlZaipv5abTug3/SzFc9e2noy9Smnx9YR1lr2tRV6krrPvcBjH1MG06j/2kRuPdfBmTIWp62RFMtMnSOIF/dD9yg0Il4Smu5tDx7/7gGP/rqEtbYj3lNGrT6rDlrahavpcjrxMZYCQfjJnf4Dvp1O6khDJHiq6HV0fbGrJ/MYQ2FsBA7eVLYEwwm0UfXayJd5pA/cgfjtTMHjFleJ7NzxMuJ7Wmp3ZKmiC19mc+lp6fPzsY7ZyRokd3TUmMKuPUayViX+PmsH851ia1k43Xp+9Bw7wOQQ0ciPQA+2blOEouNCR47LuTBbqJre4vv+IUfaqauRVSv7BkuejembwM+t9MiZoPWx9cB3VKIVzIX1pkPbL5DICc4iouXcfPY3gd71k8NXVrhy+vlPh83tspLa+HXFGfghjQmkF9cNNf7H8XBGvTMuWBtpIhnc71OcF7pdTZ2BjmNOIpByh9OWOLZ1OfOIMPPU5LFttsJkT98NGbrQPHULWCMNXU5GekcxFpiFXcdKt0+Af2XRgrYa1M9DWL5I4q1NhI0yMdpUMbD6MrJfKZSrvmf4eJojaExG+7nuk0yPXthaOi7K37hKAcuDX76+33zMawcrT3kLL9PkDeULufoJf6A3O5qxNAKHhkHXz6E5QM+voaXIz75tpyZfyM+qucIC/w/5FBP5cA+qBXG9nENG8Wc4xO7kk0/GJ9DfKndFZeXXOn93vgfxTDD2mk9qOw7wsaldQ717WHnS5+WSiv48oQ/iatPg4cgbbYNvc8ZvE2xUVPaMviCfu5ERrcD9V90UFf2DV5LKg6ZlfM3t/DXhxguUfttm8WiNXIBT9PVGPC0Ppvry8B9cJeh4mgHjNSUXnzAxozGqIjtPnAwX3wWWAcLB/sXkN+Zvx9gjEBz8E2u6u90OBq3pAnU3iZgj0O6ltgtx6V2zPiDTjNehVxpu4+t93Zc49QgrdfdFzF4XimdxUPYzXWxBC0G/0O/IGX5KAbmGPoMqNVV/qwLTDq2zvlQO8mcXyagH7HWl3CsHvheU7eqpo0HFd53x7kfPq5tYrc7AuQJa8yUYDCWejDvGDgKsDw61OOv+qSYm5NDRGRizxTwyf1a2Va1pFX2MSbggeqjdKXlnHccTedAz2GAfC2BeEF8m/WrNgpQT65xz+plmyifXaf25IzKXqBrQFw4ymeE4lvpcqHdao7A5xv4u0S9J0ftalCfOfisUn8l2NNCWJegg++rXqvsMVRPTpHeYaG3XNM8uh6Tk1xy1RX1C9Ri/Y2F+BCoJx5KpT7EDDiOLBGNWyG7bueksnNMhi8Tl2tDXQF9mxB32m+/RTTnBrmtgNVhAPb70Ifnfi9zI22Hh3yE9d3mFwv4Q4U8m2f9mkTsgmNw8Zf24FXfRu0+5GBUcET/uA+75C/AhVOOrfgur4GFLuxZj2vSvp2UfqQ4D/fA7YBFWovfQohZ+kioLuDDLqlBr3dky9Vc5KpcbIeTCSs6c/AbrSmGTnmk30LCyNnV36AH74P/+seYgL2Ck0x5jR2BzM565dFJvc3XViKlm+ta2FLx9ohrKlRnyD3gbi0G7IGgHyHeXhhgJ9i1MMo5ck7M47++njcnfnqXK4B/iqtEAZzb+rTY27CFnOzgGZ7iCGrPHgNvKB11afFRA/oWuv8hL26T1lMefMEeegkC/TXlqWXFT2WtBJwf8uZQX8J83YJTgBPw6B3G8v5ydehXaQ5pO+grUqjzzsuk2seEyn2Jy2K+X/HrcZ0DHGZ1Js7qTEyAu4nSOeVhFD3zNHeeIe6HvGmGFuACsJ3SfR7sG0PIvY6mKQnU2HIvCDrR8VCTXOCZvKesZItRVVMPe7VuZ2dqML6V10Czp7JW76n2mMpriPPS6q4TyLMt0sl+nsoH/0xkHrDCGROuXvp+MTY8y5Pjl1l8xY9E7vvq9mWa7wdLXb7qu1kznAmwJ59p0JOMKn5fKSzl9+a0vY5HM82zfM3L/NWLQWeiWR7UVfcoB0fl+4H7YExtbA8jM6sldB9OaB7Rng941HJO5U44GJ3H+7DXpe8LmlD/hiTvbeylOYM48W8htSOrc50iN9gc44PK50dx+2qdo+PlFGrjjr6XMI5zM8NRZzQew97SVwUk9FnAMexroIfK/XF4bnaam0Vv5fqGRX3ZNWdOjb5noHgzs9o1dSaE1kw1NMYFz4+b5lxfQq8jNUes4xhdLVa69/Ic6po9e6LfU6VN9eEqfSq+hzUnwMeUR1DxF3w+NmBPglvSEHhgZYLuBzthL9RS5Fnv6cugE8UDjv2s7+/jZ439oqdiNJvI6KnFSrNxFJ7kQJ7LcT4fcKA+XNG9TxXHw7uTE743ZktaN1a0Xkx8Laa5RmNa6JqoQn9rz5p7fd+BfYABtVsjJa/S/yVnKd2qHyJGuxMDR0EsaW0r3wlII+iBwb4lwT3an9D6qT4DnwPnK7+p1z+y40ptgL6Qk4/XWaDSNnfn9FuP0Fs/P9HeWs3tDwD3qQ2cqsNnVMR1pDX7ENfE7tlb2K/UIKbQ4+ZYmQvUTuAuqFWWX2Js/QFcl37K3rctoB9dzXltb6U5vsUw7VC9TmpOCjgEn6CuTXlwZT7kNejAQfD5UCdk5XnQXVN5+W4GYjs6jKX8earv6BDPqnZdiudD5CgtqOvPA5t8gv8pzcNlCPGDPpekRqs5UDXV1FrNNf2N9kCPmk33eg72tRTq+8rkbQL7Exf6puVxjiwOOCWoNyQHbv82v0LtTJFQ9MbJwAyeYQ+V6WsQs/tGrFXgjHmNvn90jMZzA/AVwt6vjD/0IAbUpazXozWHcq4zAv/ZsCexC/+Dr/ZWV05gPySf1iO25F+56GHKv6s8P+8DwB3kZzPrX5SuJg7e1+Zf50aCu9CH9eh7DvHcTnK050m5TH/V0wIEXFVwZMY/5fsRukeD/EjsmQo9XfOl2mNVexnxgJmqlznGS4WRbEwWz2zcjz+eHUCEG2wFXugSfOEgtTjCIGY6js3NpaPWbERkbrGKo4TEk0ArjlS/NvZk1PmZSH5OVJdqdUlAPFNryJgRJYQYWZREBgmoxtYkzNV4dPboDrvOkurJnh8/FWdIMv33XladWJ/N+PXTJde3MT3uEj5+crY1SXZ2fyudHQ/TszOtFJ9L8zPxa47NvWYuGvUaBoeZdbHGiCIrMg3RtpjGQrJsgZfrrGx+i9c49vxs+Pc6jf8tTsvxMizO3OzizG26OT9yqxy8jOMw+uHz53aywTCw5+LNZ3r1Af+rfPwuXIZnz9PbH5Pc6ovaZ35uyLaF+IXMIFmkp5h2jUESD87mBCzwDUnC6PwY+iN+5oU/2s3cx92cgDthoe+P3Pv9P8ovD2Zs/vj98bfvL/j+NE75rY/T+zrV4CU27fySxs9nlxlCcxPh+4ge410x7yhMX8mHmokkyWQFZlFjF4xYk4FFpDp8RbwgS4Jki/I3sQjkQ/2PDpT0ZyGRhmzJMjJ5BtcXEiM2aiwjC0C9PFtj6+JCblji4s9CIuI3OO0//4aHN/jGdvHNVH28MZPFjY1dH9+UlAGJcIEqbkz01zM1rYDkl8/+1umw7Pmdn/JejiQ3FmzNZCy+LjKijBeMDA0MI/ILdmFZllzH4jf69A+vZuzHb0l06VL51YrT61mEmGGE7SN5KS4mLMfnl9lOpoDHPQ9wevrjDqMosNY4HuPNtrjcdS5sERf78akwdr1yPP2luKl3uBTI5XdKbvFbGGxibNP7b1n23PF3xWWp83t/mRTaERybd7Xb7/7yy38BOxZqv7ooAAA=</pre>
                <button onclick="copySbCode()" class="button" id="copyButton">Code kopieren</button>
            </div>

            <div class="setup-steps">
                <h3>Nach dem Import:</h3>
                <ol>
                    <li>Klicken Sie doppelt auf die Sub-Action "Set argument..."</li>
                    <li>Ändern Sie bei "Value" den Wert "https://deine-domain.de/pfad/zu/store_donation.php" in diese URL:
                        <div class="url-box">
                            <code><?php 
                            $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']);
                            echo rtrim($current_url, '/') . '/store_donation.php';
                            ?></code>
                        </div>
                    </li>
                    <li>Klicken Sie auf "Ok" und dann auf "Save"</li>
                </ol>
            </div>

            <div class="button-group">
                <a href="admin_panel.php" class="button">Zum Admin-Panel</a>
                <a href="?step=6" class="button button-next" onclick="<?php $_SESSION['streamerbot_configured'] = true; ?>">Weiter</a>
            </div>
        </div>

        <style>
            .streamerbot-setup {
                max-width: 800px;
                margin: 20px auto;
            }
            .setup-steps {
                background-color: #f8f9fa;
                padding: 20px;
                border-radius: 4px;
                margin: 20px 0;
            }
            .code-box {
                background-color: #2b2b2b;
                border-radius: 4px;
                padding: 20px;
                margin: 20px 0;
                position: relative;
            }
            .code-box pre {
                color: #ffffff;
                margin: 0;
                white-space: pre-wrap;
                word-wrap: break-word;
                max-height: 300px;
                overflow-y: auto;
                font-family: monospace;
                font-size: 12px;
            }
            .url-box {
                background-color: #f8f9fa;
                padding: 10px;
                border-radius: 4px;
                margin: 10px 0;
                word-break: break-all;
            }
            #copyButton {
                position: absolute;
                top: 10px;
                right: 10px;
            }
        </style>

        <script>
        function copySbCode() {
            const codeElement = document.getElementById('sbCode');
            const textArea = document.createElement('textarea');
            textArea.value = codeElement.textContent;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            
            const copyButton = document.getElementById('copyButton');
            copyButton.innerHTML = 'Kopiert!';
            setTimeout(() => {
                copyButton.innerHTML = 'Code kopieren';
            }, 2000);
        }
        </script>
        <?php
    }

    private function showOBSTab() {
        ?>
        <h2>OBS-Widgets einrichten</h2>
        
        <div class="obs-setup">
            <p>Fügen Sie die folgenden Widgets als Browser-Quellen in OBS ein:</p>

            <div class="setup-steps">
                <h3>Allgemeine Einrichtung in OBS:</h3>
                <ol>
                    <li>Klicken Sie in OBS auf das + Symbol unter den Quellen</li>
                    <li>Wählen Sie "Browser" aus</li>
                    <li>Wählen Sie "Neue Quelle erstellen" und geben Sie einen Namen ein</li>
                    <li>Fügen Sie die jeweilige Widget-URL in das Feld "URL" ein</li>
                    <li>Passen Sie die Größe nach Bedarf an</li>
                </ol>
            </div>

            <div class="widgets-list">
                <h3>Verfügbare Widgets:</h3>
                
                <div class="widget-item">
                    <h4>Timer Widget</h4>
                    <p>Zeigt den Spenden-Timer für Ihre Aktion an.</p>
                    <div class="url-box">
                        <code><?php 
                        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']);
                        echo rtrim($current_url, '/') . '/timer_widget.html';
                        ?></code>
                    </div>
                </div>

                <div class="widget-item">
                    <h4>Top Ziele Widget</h4>
                    <p>Zeigt die Top-Spendenziele an. Wechselt automatisch alle 20 Sekunden zwischen 6 Zielen pro Seite.</p>
                    <div class="url-box">
                        <code><?php 
                        echo rtrim($current_url, '/') . '/top_ziele_widget.html';
                        ?></code>
                    </div>
                </div>

                <div class="widget-item">
                    <h4>Offene Ziele Widget</h4>
                    <p>Zeigt alle Ziele an, die den Mindestbetrag noch nicht erreicht haben. Wechselt alle 10 Sekunden zwischen 5 Zielen pro Seite.</p>
                    <div class="url-box">
                        <code><?php 
                        echo rtrim($current_url, '/') . '/offene_ziele_widget.html';
                        ?></code>
                    </div>
                </div>

                <div class="widget-item">
                    <h4>Abgeschlossene Ziele Widget</h4>
                    <p>Zeigt alle bereits erreichten Ziele an. Wechselt alle 10 Sekunden zwischen 5 Zielen pro Seite.</p>
                    <div class="url-box">
                        <code><?php 
                        echo rtrim($current_url, '/') . '/abgeschlossene_ziele_widget.html';
                        ?></code>
                    </div>
                </div>
            </div>

            <div class="button-group">
                <a href="admin_panel.php" class="button">Zum Admin-Panel</a>
                <a href="?step=7" class="button button-next" onclick="<?php $_SESSION['obs_configured'] = true; ?>">Weiter</a>
            </div>
        </div>

        <style>
            .obs-setup {
                max-width: 800px;
                margin: 20px auto;
            }
            .widgets-list {
                margin: 20px 0;
            }
            .widget-item {
                background-color: #f8f9fa;
                padding: 20px;
                border-radius: 4px;
                margin: 20px 0;
                border: 1px solid #dee2e6;
            }
            .widget-item h4 {
                color: #2c3e50;
                margin-top: 0;
                margin-bottom: 10px;
            }
            .widget-item p {
                margin-bottom: 15px;
                color: #666;
            }
            .url-box {
                background-color: #2b2b2b;
                padding: 10px;
                border-radius: 4px;
                margin: 10px 0;
            }
            .url-box code {
                color: #ffffff;
                word-break: break-all;
                font-family: monospace;
                font-size: 12px;
            }
        </style>
        <?php
    }

    private function showFinalOverviewTab() {
        ?>
        <h2>Installations-Übersicht</h2>
        
        <div class="overview-container">
            <div class="section admin-credentials">
                <h3>Admin-Zugangsdaten</h3>
                <div class="info-box">
                    <p><strong>Benutzername:</strong> <?php echo htmlspecialchars($_SESSION['admin_data']['username'] ?? ''); ?></p>
                    <p><strong>Passwort:</strong> <?php echo htmlspecialchars($_SESSION['admin_data']['password'] ?? ''); ?></p>
                    <p class="warning"><em>⚠️ Bitte notieren Sie sich diese Zugangsdaten! Dies ist die letzte Anzeige des Passworts!</em></p>
                </div>
            </div>

            <div class="section installation-status">
                <h3>Installations-Status</h3>
                <div class="checklist">
                    <div class="check-item">
                        <span class="checkmark">✓</span> Datenbank konfiguriert und verbunden
                    </div>
                    <div class="check-item">
                        <span class="checkmark">✓</span> Admin-Account erstellt
                    </div>
                    <div class="check-item">
                        <span class="checkmark">✓</span> Datenbank-Struktur angelegt
                    </div>
                    <div class="check-item">
                        <span class="checkmark">✓</span> Dateien installiert
                    </div>
                    <div class="check-item">
                        <span class="checkmark">✓</span> Streamerbot konfiguriert
                    </div>
                    <div class="check-item">
                        <span class="checkmark">✓</span> OBS-Widgets eingerichtet
                    </div>
                </div>
            </div>

            <div class="section main-pages">
                <h3>Hauptseiten</h3>
                
                <div class="page-info">
                    <h4>Spendenziele</h4>
                    <p>URL: <code><?php 
                        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']);
                        echo rtrim($current_url, '/') . '/spendenziele.php';
                    ?></code></p>
                    <ul>
                        <li>Zeigt alle Spendenziele mit Mindestbeträgen</li>
                        <li>Zwei Ansichten: Noch nicht erreichte und bereits erreichte Ziele</li>
                        <li>Ziele können nach Durchführung als "erledigt" markiert werden</li>
                        <li>Ideal für Aktionen mit festen Mindestbeträgen</li>
                    </ul>
                </div>

                <div class="page-info">
                    <h4>Spendenranking</h4>
                    <p>URL: <code><?php echo rtrim($current_url, '/') . '/spendenranking.php'; ?></code></p>
                    <ul>
                        <li>Fokussiert auf die meistgespendeten Aktivitäten</li>
                        <li>Nur die höchste Spende wird durchgeführt</li>
                        <li>Arbeitet ohne Mindestbeträge</li>
                        <li>Ideal für Wettbewerbs-basierte Spendenaktionen</li>
                    </ul>
                </div>
            </div>

            <div class="section admin-access">
                <h3>Verwaltungs-Panels</h3>
                
                <div class="panel-info">
                    <h4>Admin-Panel</h4>
                    <p>URL: <code><?php echo rtrim($current_url, '/') . '/admin_panel.php'; ?></code></p>
                    <ul>
                        <li>Einstellungen verwalten (Zeitzone, Admin-Passwort)</li>
                        <li>Moderatoren verwalten (Anlegen, Aktivieren/Deaktivieren, Löschen)</li>
                        <li>Spendenzeitraum festlegen</li>
                        <li>Ziele & Gesamtbeträge verwalten</li>
                        <li>Spenden erfassen, bearbeiten und löschen</li>
                    </ul>
                </div>

                <div class="panel-info">
                    <h4>Moderator-Panel</h4>
                    <p>URL: <code><?php echo rtrim($current_url, '/') . '/moderator_panel.php'; ?></code></p>
                    <ul>
                        <li>Eigenes Passwort ändern</li>
                        <li>Manuelle Spenden erfassen</li>
                        <li>Spendentexte bearbeiten</li>
                    </ul>
                </div>
            </div>

            <div class="button-group">
                <form method="post" onsubmit="return confirm('Sind Sie sicher? Die Installationsdatei wird gelöscht und Sie werden zum Admin-Panel weitergeleitet.');">
                    <button type="submit" name="finish_installation" class="button">Installation abschließen</button>
                </form>
            </div>
        </div>

        <style>
            .overview-container {
                max-width: 800px;
                margin: 20px auto;
            }
            .section {
                background-color: #f8f9fa;
                padding: 20px;
                border-radius: 4px;
                margin: 20px 0;
                border: 1px solid #dee2e6;
            }
            .checklist {
                margin: 15px 0;
            }
            .check-item {
                margin: 10px 0;
                display: flex;
                align-items: center;
            }
            .checkmark {
                color: #4CAF50;
                margin-right: 10px;
                font-weight: bold;
            }
            .page-info, .panel-info {
                margin: 20px 0;
                padding: 15px;
                background-color: white;
                border-radius: 4px;
                border: 1px solid #e0e0e0;
            }
            .page-info h4, .panel-info h4 {
                color: #2c3e50;
                margin-top: 0;
            }
            .page-info ul, .panel-info ul {
                margin: 10px 0;
                padding-left: 20px;
            }
            .page-info li, .panel-info li {
                margin: 5px 0;
                color: #666;
            }
            code {
                background-color: #2b2b2b;
                color: white;
                padding: 4px 8px;
                border-radius: 4px;
                font-family: monospace;
                font-size: 12px;
                word-break: break-all;
            }
        </style>
        <?php
        $_SESSION['overview_viewed'] = true;
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

        // Speichere die eingegebenen Werte in der Session
        $_SESSION['db_input'] = [
            'host' => $host,
            'port' => $port,
            'dbname' => $dbname,
            'username' => $username,
            'password' => $password
        ];

        try {
            $dsn = "mysql:host=$host;port=$port";
            $pdo = new PDO($dsn, $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbname'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
            }
            
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
            $zipUrl = "https://github.com/Bittersweet1987/spendenziele/archive/refs/heads/main.zip";
            
            // Temporärer Ordner für den Download
            $tempFile = tempnam(sys_get_temp_dir(), 'gh_');
            $tempDir = $tempFile . '_extracted';
            
            // Download der ZIP-Datei von GitHub
            $zipContent = file_get_contents($zipUrl);
            if ($zipContent === false) {
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
            
            // Finden des Spendenziele-Ordners
            $sourceDir = $tempDir . '/spendenziele-main/Spendenziele';
            if (!is_dir($sourceDir)) {
                throw new Exception("Konnte den Spendenziele-Ordner nicht finden.");
            }
            
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
                    flex-wrap: nowrap;
                }
                .tab {
                    padding: 8px 12px;
                    margin-right: 4px;
                    border-radius: 4px 4px 0 0;
                    cursor: default;
                    opacity: 0.5;
                    font-size: 13px;
                    white-space: nowrap;
                    height: 16px;
                    line-height: 16px;
                    background-color: #e0e0e0;
                    color: #333;
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
                    font-size: 14px;
                    line-height: 1.5;
                    height: 40px;
                    box-sizing: border-box;
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
                .documentation {
                    background-color: #f8f9fa;
                    padding: 20px;
                    border-radius: 4px;
                    margin: 20px 0;
                }
                .documentation h2 {
                    color: #2c3e50;
                    border-bottom: 2px solid #3498db;
                    padding-bottom: 10px;
                    margin-bottom: 20px;
                }
                .documentation pre {
                    background-color: #f1f1f1;
                    padding: 15px;
                    border-radius: 4px;
                    overflow-x: auto;
                }
                .documentation code {
                    font-family: monospace;
                }
                .table-list {
                    margin: 15px 0;
                    padding: 15px;
                    background-color: #f8f9fa;
                    border-radius: 4px;
                    border: 1px solid #dee2e6;
                }
                .table-list h4 {
                    color: #2c3e50;
                    margin-top: 10px;
                    margin-bottom: 5px;
                }
                .table-list ul {
                    list-style-type: none;
                    padding-left: 20px;
                }
                .table-list li {
                    margin: 5px 0;
                    color: #495057;
                }
                .table-list li:before {
                    content: "✓";
                    color: #28a745;
                    margin-right: 8px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="tabs">
                    <div class="tab <?php 
                        if (!isset($_GET['step']) || $_GET['step'] == '1') echo 'active';
                        else if (isset($_SESSION['db_configured'])) echo 'completed';
                    ?>">
                        1. Datenbank
                    </div>
                    <div class="tab <?php 
                        if ($_GET['step'] == '2') echo 'active';
                        else if (isset($_SESSION['admin_created'])) echo 'completed';
                    ?>">
                        2. Admin
                    </div>
                    <div class="tab <?php 
                        if ($_GET['step'] == '3') echo 'active';
                        else if (isset($_SESSION['db_structure_created'])) echo 'completed';
                    ?>">
                        3. Datenstruktur
                    </div>
                    <div class="tab <?php 
                        if ($_GET['step'] == '4') echo 'active';
                        else if (isset($_SESSION['installation_complete'])) echo 'completed';
                    ?>">
                        4. Dateien
                    </div>
                    <div class="tab <?php 
                        if ($_GET['step'] == '5') echo 'active';
                        else if (isset($_SESSION['streamerbot_configured'])) echo 'completed';
                        else if (isset($_SESSION['installation_complete'])) echo 'available';
                    ?>">
                        5. Streamerbot
                    </div>
                    <div class="tab <?php 
                        if ($_GET['step'] == '6') echo 'active';
                        else if (isset($_SESSION['obs_configured'])) echo 'completed';
                        else if (isset($_SESSION['installation_complete'])) echo 'available';
                    ?>">
                        6. OBS
                    </div>
                    <div class="tab <?php 
                        if ($_GET['step'] == '7') echo 'active';
                        else if (isset($_SESSION['overview_viewed'])) echo 'completed';
                        else if (isset($_SESSION['installation_complete'])) echo 'available';
                    ?>">
                        7. Übersicht
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
                
                // Hole alle Tabellen aus der Datenbank
                $stmt = $pdo->query("SHOW TABLES");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Deaktiviere Foreign Key Checks temporär
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                
                // Lösche alle Tabellen
                foreach ($tables as $table) {
                    try {
                        $pdo->exec("DROP TABLE IF EXISTS `$table`");
                    } catch (Exception $e) {
                        $this->debugLog("Fehler beim Löschen der Tabelle $table: " . $e->getMessage());
                    }
                }
                
                // Aktiviere Foreign Key Checks wieder
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

                // Behalte die Datenbankkonfiguration für die nächste Installation
                $_SESSION['db_config'] = [
                    'host' => $host,
                    'port' => $port,
                    'dbname' => $dbname,
                    'username' => $username,
                    'password' => $password
                ];
            }

            // Geschützte Dateien, die nicht gelöscht werden sollen
            $protectedFiles = ['install.php', 'config.php'];

            // Lösche alle Dateien im Verzeichnis außer die geschützten
            $files = glob('*');
            foreach ($files as $file) {
                if (!in_array($file, $protectedFiles) && is_file($file)) {
                    @unlink($file);
                }
            }

            // Lösche alle Unterverzeichnisse
            $dirs = glob('*', GLOB_ONLYDIR);
            foreach ($dirs as $dir) {
                $this->deleteDirectory($dir);
            }

        } catch (Exception $e) {
            $this->debugLog("Fehler beim Zurücksetzen der Installation: " . $e->getMessage());
        }

        // Session zurücksetzen, aber DB-Konfiguration behalten
        $dbConfig = $_SESSION['db_config'] ?? null;
        session_unset();
        if ($dbConfig) {
            $_SESSION['db_config'] = $dbConfig;
        }
        
        // Stelle sicher, dass der Browser keine gecachte Version anzeigt
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        
        // Hole die absolute URL der install.php
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $uri = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        $redirectUrl = $protocol . $host . $uri . '/install.php';
        
        // JavaScript für die Weiterleitung ausgeben
        echo '<script>window.location.href = "' . $redirectUrl . '";</script>';
        // Fallback für den Fall, dass JavaScript deaktiviert ist
        header("Location: " . $redirectUrl);
        exit();
    }

    private function createDatabaseStructure() {
        try {
            require_once 'config.php';
            
            // Tabelle für Admin
            $pdo->exec("CREATE TABLE IF NOT EXISTS admin (
                id INT AUTO_INCREMENT PRIMARY KEY,
                benutzername VARCHAR(50) UNIQUE NOT NULL,
                passwort_hash VARCHAR(255) NOT NULL
            )");

            // Tabelle für Moderatoren
            $pdo->exec("CREATE TABLE IF NOT EXISTS moderatoren (
                id INT AUTO_INCREMENT PRIMARY KEY,
                benutzername VARCHAR(50) UNIQUE NOT NULL,
                passwort_hash VARCHAR(255) NOT NULL,
                status ENUM('aktiv', 'inaktiv') NOT NULL DEFAULT 'aktiv'
            )");

            // Tabelle für Spenden
            $pdo->exec("CREATE TABLE IF NOT EXISTS spenden (
                id INT AUTO_INCREMENT PRIMARY KEY,
                benutzername VARCHAR(255) NOT NULL,
                betrag DECIMAL(10,2) NOT NULL,
                ziel VARCHAR(100) NOT NULL,
                datum DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            // Tabelle für Spendenziele
            $pdo->exec("CREATE TABLE IF NOT EXISTS ziele (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ziel VARCHAR(100) NOT NULL,
                gesamtbetrag DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                mindestbetrag DECIMAL(10,2) DEFAULT NULL,
                abgeschlossen TINYINT(1) NOT NULL DEFAULT 0,
                sichtbar TINYINT(1) NOT NULL DEFAULT 0
            )");

            // Tabelle für Einstellungen
            $pdo->exec("CREATE TABLE IF NOT EXISTS einstellungen (
                schluessel VARCHAR(255) PRIMARY KEY,
                wert TEXT NOT NULL
            )");

            // Tabelle für Zeitzonen
            $pdo->exec("CREATE TABLE IF NOT EXISTS zeitzonen (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL UNIQUE
            )");

            // Tabelle für den Spendenzeitraum
            $pdo->exec("CREATE TABLE IF NOT EXISTS zeitraum (
                id INT AUTO_INCREMENT PRIMARY KEY,
                start DATETIME NOT NULL,
                ende DATETIME NOT NULL
            )");

            // Standardeinstellungen einfügen
            $pdo->exec("INSERT IGNORE INTO einstellungen (schluessel, wert) VALUES 
                ('zeitzone', 'Europe/Berlin'),
                ('logout_redirect', 'spendenziele.php')
            ");

            // Standardzeitzonen einfügen
            $pdo->exec("INSERT IGNORE INTO zeitzonen (name) VALUES 
                ('America/Chicago'),
                ('America/Denver'),
                ('America/Los_Angeles'),
                ('America/Mexico_City'),
                ('America/New_York'),
                ('America/Sao_Paulo'),
                ('Asia/Bangkok'),
                ('Asia/Dubai'),
                ('Asia/Hong_Kong'),
                ('Asia/Jakarta'),
                ('Asia/Kolkata'),
                ('Asia/Seoul'),
                ('Asia/Singapore'),
                ('Asia/Tokyo'),
                ('Australia/Melbourne'),
                ('Australia/Sydney'),
                ('Europe/Athens'),
                ('Europe/Berlin'),
                ('Europe/London'),
                ('Europe/Madrid'),
                ('Europe/Paris'),
                ('Europe/Rome'),
                ('UTC')
            ");

            $_SESSION['db_structure_created'] = true;
            $_SESSION['success'] = "Datenstruktur erfolgreich erstellt!";
            return true;

        } catch (Exception $e) {
            $_SESSION['error'] = "Fehler beim Erstellen der Datenstruktur: " . $e->getMessage();
            return false;
        }
    }

    private function showDatabaseStructureTab() {
        if (isset($_SESSION['error'])) {
            echo '<div class="message error">' . htmlspecialchars($_SESSION['error']) . '</div>';
            unset($_SESSION['error']);
        }
        if (isset($_SESSION['success'])) {
            echo '<div class="message success">' . htmlspecialchars($_SESSION['success']) . '</div>';
            unset($_SESSION['success']);
        }

        ?>
        <h2>Datenstruktur anlegen</h2>
        <?php if (!isset($_SESSION['db_structure_created'])): ?>
            <p>Klicken Sie auf den Button, um die erforderlichen Tabellen in der Datenbank anzulegen.</p>
            <form method="post">
                <div class="button-group">
                    <button type="submit" name="create_structure" class="button">Datenstruktur anlegen</button>
                </div>
            </form>
        <?php else: ?>
            <div class="table-list">
                <h4>Folgende Tabellen wurden erstellt:</h4>
                <ul>
                    <li>Admin-Tabelle für Administratorzugang</li>
                    <li>Moderatoren-Tabelle für Moderatorenzugänge</li>
                    <li>Spenden-Tabelle für alle Spendeneingänge</li>
                    <li>Ziele-Tabelle für die Spendenziele</li>
                    <li>Einstellungen-Tabelle für Systemkonfiguration</li>
                    <li>Zeitzonen-Tabelle mit Standardzeitzonen</li>
                    <li>Zeitraum-Tabelle für Spendenaktionszeiträume</li>
                </ul>
                <h4>Standardeinstellungen:</h4>
                <ul>
                    <li>Zeitzone: Europe/Berlin</li>
                    <li>23 internationale Zeitzonen voreingestellt</li>
                </ul>
            </div>
            <div class="button-group">
                <a href="?step=4" class="button button-next">Weiter</a>
            </div>
        <?php endif; ?>
        <?php
    }
}

$installer = new Installer();
$installer->run();
?> 