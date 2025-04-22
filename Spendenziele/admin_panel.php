<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['admin_id'])) {
    die("<h3>Zugriff verweigert. Bitte <a href='admin_login.php'>einloggen</a>.</h3>");
}

// Funktion zum Prüfen auf Updates
function checkForUpdates() {
    // Lese den letzten bekannten Commit-Hash
    $lastKnownCommitFile = __DIR__ . '/last_commit.txt';
    $lastKnownCommit = trim(@file_get_contents($lastKnownCommitFile) ?: '');
    
    // GitHub API URL für das Repository
    $githubApiUrl = 'https://api.github.com/repos/Bittersweet1987/spendenziele/commits/main';
    
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: PHP',
                'Accept: application/vnd.github.v3+json'
            ]
        ]
    ];
    
    $context = stream_context_create($opts);
    $response = @file_get_contents($githubApiUrl, false, $context);
    
    if ($response === false) {
        return true; // Bei Fehlern Button anzeigen
    }
    
    $data = json_decode($response, true);
    if (!is_array($data) || !isset($data['sha'])) {
        return true; // Bei Fehlern Button anzeigen
    }

    // Aktuellster Commit von GitHub
    $latestCommit = trim($data['sha']);
    
    // Wenn kein letzter Commit gespeichert ist, aktuellen speichern
    if (empty($lastKnownCommit)) {
        file_put_contents($lastKnownCommitFile, $latestCommit);
        return false;
    }
    
    // Debug-Ausgabe (temporär)
    error_log("Local commit: " . $lastKnownCommit);
    error_log("Remote commit: " . $latestCommit);
    
    // Vergleiche die Commits (ersten 7 Zeichen)
    $localShort = substr($lastKnownCommit, 0, 7);
    $remoteShort = substr($latestCommit, 0, 7);
    
    return $localShort !== $remoteShort;
}

// Prüfe auf Updates
$updateAvailable = checkForUpdates();

if (!$pdo) {
    echo json_encode(['error' => 'Fehler: Datenbankverbindung nicht vorhanden']);
    exit;
}

// Logout-Weiterleitungsziel abrufen
$stmt = $pdo->prepare("SELECT wert FROM einstellungen WHERE schluessel = 'logout_redirect'");
$stmt->execute();
$logoutRedirect = $stmt->fetchColumn() ?: 'spendenziele.php'; // Standardwert: spendenziele.php

// Zeitzone aus den Einstellungen abrufen
$stmt = $pdo->query("SELECT wert FROM einstellungen WHERE schluessel = 'zeitzone'");
$zeitzone = $stmt->fetchColumn() ?: 'UTC'; // Standard: UTC

// Alle verfügbaren Zeitzonen aus der Datenbank abrufen
$stmt = $pdo->query("SELECT name FROM zeitzonen ORDER BY name ASC");
$verfuegbareZeitzonen = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Zeitraum aus der Datenbank laden und in lokale Zeitzone umrechnen
$stmt = $pdo->query("SELECT start, ende FROM zeitraum LIMIT 1");
$zeitraum = $stmt->fetch(PDO::FETCH_ASSOC);

$start = $zeitraum ? new DateTime($zeitraum['start'], new DateTimeZone('UTC')) : null;
$ende = $zeitraum ? new DateTime($zeitraum['ende'], new DateTimeZone('UTC')) : null;

if ($start) $start->setTimezone(new DateTimeZone($zeitzone));
if ($ende) $ende->setTimezone(new DateTimeZone($zeitzone));

$startFormatted = $start ? $start->format('Y-m-d\TH:i') : '';
$endeFormatted = $ende ? $ende->format('Y-m-d\TH:i') : '';

// Moderatoren abrufen
$stmt = $pdo->query("SELECT id, benutzername, status FROM moderatoren");
$moderatoren = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Spenden abrufen
$stmtSpenden = $pdo->query("SELECT id, benutzername, betrag, ziel, datum FROM spenden ORDER BY datum DESC");
$spenden = $stmtSpenden->fetchAll(PDO::FETCH_ASSOC);

// Ziele + mindestbetrag laden
try {
    // Debug: Tabellenstruktur anzeigen
    $columns = $pdo->query("SHOW COLUMNS FROM ziele");
    error_log("=== Tabellenstruktur von 'ziele' ===");
    while($column = $columns->fetch(PDO::FETCH_ASSOC)) {
        error_log(print_r($column, true));
    }
    
    // Daten abrufen
    $stmtziele = $pdo->query("SELECT * FROM ziele ORDER BY gesamtbetrag DESC");
    $ziele = $stmtziele->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Erste Zeile der Daten anzeigen
    if (!empty($ziele)) {
        error_log("=== Erste Zeile der Ziele-Daten ===");
        error_log(print_r($ziele[0], true));
    } else {
        error_log("Keine Ziele in der Datenbank gefunden!");
    }
} catch (PDOException $e) {
    error_log("Datenbankfehler: " . $e->getMessage());
}

// Debug-Ausgabe
error_log("Ziele aus der Datenbank:");
error_log(print_r($ziele, true));

// Verarbeite Update-Commit wenn vorhanden
if (isset($_POST['update_commit'])) {
    $commitFile = __DIR__ . '/last_commit.txt';
    file_put_contents($commitFile, $_POST['update_commit']);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin-Panel</title>
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
            position: relative;
        }
        
        .header-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 20px;
            position: relative;
            padding-right: 200px; /* Platz für die Buttons */
        }
        
        .header-buttons {
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            gap: 10px;
        }
        
        h1 {
            color: #333;
            margin: 0;
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #007bff;
            color: white;
        }
        
        /* Einheitliche Button-Styles */
        .btn, button {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin: 4px;
            transition: all 0.3s ease;
            font-weight: 500;
            text-transform: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .btn-orange { 
            background-color: #ff8c00; 
            color: #fff;
        }
        .btn-yellow, .btn-min { 
            background-color: #ffc107; 
            color: #000;
        }
        .btn-red, .btn-erledigt { 
            background-color: #dc3545; 
            color: #fff;
        }
        .btn-green { 
            background-color: #28a745; 
            color: #fff;
        }
        .btn-blue { 
            background-color: #007bff; 
            color: #fff;
        }
        
        .btn:hover, button:hover {
            opacity: 0.9;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .visible-btn {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            margin: 4px;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .hidden-btn {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            margin: 4px;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .visible-btn:hover, .hidden-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        /* Modal/Overlay Styling */
		.modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0; 
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            position: relative;
            border-radius: 5px;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
		.close:hover {
			color: black;
		}

        /* Zusätzliche Styles */
        .spenden-form {
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        
        .spenden-form input {
            margin-right: 10px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
			font-size: 14px;
		}
		
        .spenden-form button {
            background-color: #007bff;
            color: white;
            padding: 8px 16px;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #333;
            margin: 0;
        }

        .complete-btn {
            background-color: #0d6efd;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .complete-btn:hover {
            background-color: #0b5ed7;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-container">
            <h1>Admin-Panel</h1>
            <div class="header-buttons">
                <?php if ($updateAvailable): ?>
                <a href="update.php"><button class="btn btn-orange">🔄 Update verfügbar!</button></a>
                <?php endif; ?>
                <button class="btn btn-blue" onclick="openSettings()">⚙️ Einstellungen</button>
                <a href="admin_logout.php"><button class="btn btn-red">🚪 Logout</button></a>
            </div>
        </div>

        <!-- Einstellungen Modal -->
        <div id="settingsModal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border: 1px solid #ccc; box-shadow: 0 0 10px rgba(0,0,0,0.5); z-index: 1000;">
            <h3>Einstellungen</h3>
            <label for="timezone">Zeitzone:</label>
            <select id="timezone">
                <?php foreach ($verfuegbareZeitzonen as $tz): ?>
                    <option value="<?= htmlspecialchars($tz) ?>" <?= $tz === $zeitzone ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tz) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <br><br>
            <label for="logout_redirect">Nach Logout zu:</label>
            <select id="logout_redirect">
                <option value="spendenziele.php" <?= $logoutRedirect === 'spendenziele.php' ? 'selected' : '' ?>>Spendenziele</option>
                <option value="spendenranking.php" <?= $logoutRedirect === 'spendenranking.php' ? 'selected' : '' ?>>Spendenranking</option>
            </select>
            <br><br>
            <button class="btn btn-blue" onclick="saveSettings()">Speichern</button>
            <button class="btn btn-red" onclick="closeSettings()">Abbrechen</button>
        </div>

        <!-- Moderatoren-Verwaltung -->
        <h3>Moderatoren-Verwaltung</h3>
        <button class="btn btn-blue" onclick="addModerator()">Neuen Moderator hinzufügen</button>
        <table>
            <tr>
                <th>ID</th>
                <th>Benutzername</th>
                <th>Status</th>
                <th>Aktion</th>
            </tr>
            <?php foreach ($moderatoren as $mod): ?>
                <tr>
                    <td><?= $mod['id'] ?></td>
                    <td><?= htmlspecialchars($mod['benutzername']) ?></td>
                    <td><?= $mod['status'] ?></td>
                    <td>
                        <button class="btn <?= $mod['status'] === 'aktiv' ? 'btn-red' : 'btn-green' ?>" onclick="toggleModerator(<?= $mod['id'] ?>, '<?= $mod['status'] ?>')"><?= $mod['status'] === 'aktiv' ? 'Deaktivieren' : 'Aktivieren' ?></button>
                        <button class="btn btn-yellow" onclick="resetModeratorPassword(<?= $mod['id'] ?>)">Passwort zurücksetzen</button>
                        <button class="btn btn-red" onclick="deleteModerator(<?= $mod['id'] ?>)">Löschen</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <!-- Spendenzeitraum -->
        <h3>Spendenzeitraum festlegen (Aktuelle Zeitzone: <?= $zeitzone ?>)</h3>
        <div>
            Start: <input type="datetime-local" id="start" value="<?= $startFormatted ?>">
            Ende: <input type="datetime-local" id="ende" value="<?= $endeFormatted ?>">
            <button class="btn btn-blue" onclick="setZeitraum()">Zeitraum speichern</button>
            <button class="btn btn-red" onclick="resetSpenden()">Alle Spenden zurücksetzen</button>
        </div>

        <!-- Ziele mit Mindestbetrag -->
        <h3>Ziele & Gesamtbetrag (mit Mindestbetrag)</h3>
        <table id="zieleTable">
            <tr>
                <th>Ziel</th>
                <th>Gesamtbetrag (€)</th>
                <th>Mindestbetrag</th>
                <th>Status</th>
                <th>Aktion</th>
            </tr>
            <?php if ($ziele): ?>
                <?php foreach ($ziele as $goal):
                    $cid = $goal['id'];
                    $zielname = htmlspecialchars($goal['ziel']);
                    $cges = htmlspecialchars($goal['gesamtbetrag']);
                    $mindestbetrag = $goal['mindestbetrag'];
                    $sichtbar = isset($goal['sichtbar']) ? $goal['sichtbar'] : 0;

                    // Mindestbetrag-Formatierung
                    if (is_null($mindestbetrag)) {
                        $mbString = "-";
                    } elseif ($mindestbetrag == 0) {
                        $mbString = "Nicht notwendig";
                    } else {
                        $mbString = $mindestbetrag . " €";
                    }

                    // Status berechnen
                    $status = ($mindestbetrag !== null && $cges >= $mindestbetrag) ? "✔️ Erreicht" : "❌ Noch offen";
                    if ($goal['abgeschlossen'] == 1) {
                        $status = "✅ Abgeschlossen";
                    }

                    // Sichtbarkeits-Button (Text angepasst)
                    $sichtbarBtn = "<button class='" . ($sichtbar ? "visible-btn" : "hidden-btn") . "' onclick='toggleZielSichtbarkeit($cid)'>" 
                                . ($sichtbar ? "Verstecken" : "Anzeigen") . "</button>";

                    // Button "Als erledigt markieren" nur anzeigen, wenn das Ziel erreicht, aber noch nicht abgeschlossen ist
                    $actionButton = ($mindestbetrag !== null && $cges >= $mindestbetrag && $goal['abgeschlossen'] == 0) 
                        ? "<button class='complete-btn' onclick='markAsCompleted($cid)'>Als erledigt markieren</button>" 
                        : "";

                    // Button "Min setzen" anzeigen, wenn das Ziel noch nicht abgeschlossen ist
                    $minSetzenButton = ($goal['abgeschlossen'] == 0) 
                        ? "<button class='btn btn-yellow' onclick='setMindestbetrag($cid, \"$mindestbetrag\")'>Min setzen</button>" 
                        : "";
                ?>
                <tr>
                    <td><?= $zielname ?></td>
                    <td><?= $cges ?> €</td>
                    <td><?= $mbString ?></td>
                    <td><?= $status ?></td>
                    <td><?= $minSetzenButton ?> <?= $actionButton ?> <?= $sichtbarBtn ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5">Keine Ziele gefunden</td></tr>
            <?php endif; ?>
        </table>

        <!-- Spenden-Übersicht -->
        <h3>Spenden-Übersicht</h3>
        <div class="spenden-form">
            <input type="text" id="mod-benutzername" placeholder="Benutzername">
            <input type="number" id="mod-betrag" placeholder="Betrag (€)" step="0.01">
            <input type="text" id="mod-ziel" placeholder="Ziel">
            <button onclick="addSpende()">Spende erfassen</button>
        </div>
        <table id="spendenTable">
            <tr>
                <th>ID</th>
                <th>Benutzername</th>
                <th>Betrag (€)</th>
                <th>Ziel</th>
                <th>Datum</th>
                <th>Bearbeiten</th>
            </tr>
            <?php foreach ($spenden as $spende): ?>
                <tr>
                    <td><?= $spende['id'] ?></td>
                    <td><?= htmlspecialchars($spende['benutzername']) ?></td>
                    <td><?= htmlspecialchars($spende['betrag']) ?></td>
                    <td><?= htmlspecialchars($spende['ziel']) ?></td>
                    <td><?= $spende['datum'] ?></td>
                    <td>
                        <button class="btn btn-blue" onclick="openEditModal(<?= $spende['id'] ?>, '<?= htmlspecialchars($spende['ziel']) ?>')">Bearbeiten</button>
                        <button class="btn btn-red" onclick="deleteSpende(<?= $spende['id'] ?>)">Löschen</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- Modal für Ziel-Bearbeitung -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3>Ziel bearbeiten</h3>
            <p>Aktuelles Ziel: <span id="currentZiel"></span></p>
            <input type="hidden" id="spendeId">
            <input type="text" id="newZiel" placeholder="Neues Ziel">
            <button class="btn btn-blue" onclick="updateZiel()">Speichern</button>
            <button class="btn btn-red" onclick="closeModal()">Abbrechen</button>
        </div>
    </div>

    <script>
        function toggleZielSichtbarkeit(id) {
            fetch('toggle_ziel_sichtbarkeit.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Nur den Button und den Status des betroffenen Ziels aktualisieren
                    const row = document.querySelector(`button[onclick="toggleZielSichtbarkeit(${id})"]`).closest('tr');
                    const button = row.querySelector(`button[onclick="toggleZielSichtbarkeit(${id})"]`);
                    
                    if (button) {
                        if (data.sichtbar) {
                            button.textContent = "Verstecken";
                            button.className = "visible-btn";
                        } else {
                            button.textContent = "Anzeigen";
                            button.className = "hidden-btn";
                        }
                    }
                } else {
                    alert('Fehler: ' + data.error);
                }
            })
            .catch(err => {
                console.error('Fehler:', err);
                alert('Fehler beim Aktualisieren der Sichtbarkeit');
            });
        }

        function openSettings() {
            document.getElementById('settingsModal').style.display = 'block';
        }
        function closeSettings() {
            document.getElementById('settingsModal').style.display = 'none';
        }
        
        function saveSettings() {
            const timezone = document.getElementById('timezone').value;
            const logoutRedirect = document.getElementById('logout_redirect').value;
        
            fetch('save_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ timezone, logout_redirect: logoutRedirect })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Einstellungen gespeichert!');
                    closeSettings();
                    location.reload();
                } else {
                    alert('Fehler: ' + data.error);
                }
            })
            .catch(error => console.error('Fehler beim Speichern:', error));
        }

        // Zeitraum & Spenden-Reset
        function setZeitraum() {
            const start = document.getElementById('start').value;
            const ende = document.getElementById('ende').value;
            fetch('set_zeitraum.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ start, ende })
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                location.reload();
            });
        }
        function resetSpenden() {
            if (confirm('Bist du sicher? Alle Spenden werden gelöscht!')) {
                fetch('reset_spenden.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    location.reload();
                });
            }
        }

        // Mindestbetrag
        function setMindestbetrag(id, currentVal) {
            let neu = prompt("Gib den Mindestbetrag ein (0 = 'Nicht notwendig', leer = '-'):", currentVal || "");
            if (neu === null) return;

            let mbWert = (neu.trim() === "") ? null : parseFloat(neu);

            fetch('update_mindestbetrag.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, mindestbetrag: mbWert })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert("Mindestbetrag aktualisiert!");
                    location.reload();
                } else {
                    alert("Fehler: " + data.error);
                }
            })
            .catch(err => console.error("Fehler:", err));
        }
		
		// Spende hinzufügen
		function addSpende() {
            const benutzername = document.getElementById('mod-benutzername').value.trim();
            const betrag = parseFloat(document.getElementById('mod-betrag').value.trim());
            const ziel = document.getElementById('mod-ziel').value.trim();

            if (!benutzername || isNaN(betrag) || !ziel) {
                alert('Bitte alle Felder korrekt ausfüllen!');
                return;			   
            }
			
			 fetch('store_donation.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username: benutzername, betrag: betrag, ziel: ziel })
            })
            .then(response => response.json())					 
            .then(data => {
                alert(data.message);
                refreshTables();                                                                                                                                                
            })	  
            .catch(error => console.error('Fehler:', error));
        }
		
        // Spende löschen
		function deleteSpende(id) {
			if (!confirm("Möchtest du diese Spende wirklich löschen?")) return;

			fetch('delete_spende.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ id: id })
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					alert(data.message);
                    refreshTables();
				} else {
					alert("Fehler: " + data.error);
				}
			})
			.catch(error => console.error('Fehler:', error));
		}
		
        // Ziel bearbeiten
        function openEditModal(id, currentZiel) {
            document.getElementById("modal").style.display = "block";
            document.getElementById("spendeId").value = id;
            document.getElementById("currentZiel").innerText = currentZiel;
            document.getElementById("newZiel").value = currentZiel;                                   
        }

        function closeModal() {
            document.getElementById("modal").style.display = "none";
        }
        function updateZiel() {
            const id = document.getElementById("spendeId").value;
            const neueZiel = document.getElementById("newZiel").value;
            fetch('update_spende.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, neue_ziel: neueZiel })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Ziel erfolgreich aktualisiert');
                    closeModal();
                    refreshTables();
                } else {
                    alert('Fehler: ' + data.error);
                }
            })
            .catch(error => console.error('Fehler:', error));													 
        }

        // Moderatoren-Verwaltung
        function addModerator() {
            const benutzername = prompt("Neuen Moderator-Benutzernamen eingeben:");
            if (!benutzername) return;
            const passwort = prompt("Passwort für diesen Moderator eingeben:");
            if (!passwort) return;

            fetch('moderator_verwaltung.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ benutzername, passwort })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert("Moderator erfolgreich hinzugefügt");
                    location.reload();
                } else {
                    alert("Fehler: " + data.error);
                }
            })
            .catch(err => console.error("Fehler:", err));
        }

        function toggleModerator(id, status) {
            const newStatus = status === 'aktiv' ? 'inaktiv' : 'aktiv';
            fetch('toggle_moderator.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, newStatus: newStatus })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert("Status erfolgreich geändert!");
                    location.reload();
                } else {
                    alert("Fehler: " + data.error);
                }
            })
            .catch(err => console.error("Fehler:", err));
        }

        function resetModeratorPassword(id) {
            const newPass = prompt("Neues Passwort eingeben:");
            if (!newPass) return;
            fetch('reset_moderator_password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, password: newPass })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert("Passwort erfolgreich geändert");
                } else {
                    alert("Fehler: " + data.error);
                }
            })
            .catch(err => console.error("Fehler:", err));
        }

        function deleteModerator(id) {
            if (!confirm("Diesen Moderator wirklich löschen?")) return;
            fetch('delete_moderator.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert("Moderator gelöscht");
                    location.reload();
                } else {
                    alert("Fehler: " + data.error);
                }
            })
            .catch(err => console.error("Fehler:", err));
        }
		
		function markAsCompleted(id) {
        fetch('ziel_abgeschlossen.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
                refreshTables();
        })
        .catch(error => console.error("Fehler beim Abschließen des Ziels:", error));
		}

        // Funktion zum Aktualisieren der Ziele-Tabelle
        function updateZieleTable() {
            return fetch('get_ziele.php')
            .then(response => response.json())
            .then(data => {
                const zieleTable = document.getElementById('zieleTable');
                if (!zieleTable) return;

                // Erstelle die komplette Tabelle neu, inkl. Header
                let newTable = `
                    <tr>
                        <th>Ziel</th>
                        <th>Gesamtbetrag (€)</th>
                        <th>Mindestbetrag</th>
                        <th>Status</th>
                        <th>Aktion</th>
                    </tr>`;

                data.forEach(goal => {
                    const mindestbetrag = goal.mindestbetrag;
                    const sichtbar = parseInt(goal.sichtbar) === 1;
                    const abgeschlossen = parseInt(goal.abgeschlossen) === 1;

                    let mbString;
                    if (mindestbetrag === null) {
                        mbString = "-";
                    } else if (parseFloat(mindestbetrag) === 0) {
                        mbString = "Nicht notwendig";
                    } else {
                        mbString = mindestbetrag + " €";
                    }

                    let status;
                    if (abgeschlossen) {
                        status = "✅ Abgeschlossen";
                    } else if (mindestbetrag !== null && parseFloat(goal.gesamtbetrag) >= parseFloat(mindestbetrag)) {
                        status = "✔️ Erreicht";
                    } else {
                        status = "❌ Noch offen";
                    }

                    const sichtbarBtn = `<button class='${sichtbar ? "visible-btn" : "hidden-btn"}' onclick='toggleZielSichtbarkeit(${goal.id})'>${sichtbar ? "Verstecken" : "Anzeigen"}</button>`;
                    const actionButton = (mindestbetrag !== null && parseFloat(goal.gesamtbetrag) >= parseFloat(mindestbetrag) && !abgeschlossen) 
                        ? `<button class='complete-btn' onclick='markAsCompleted(${goal.id})'>Als erledigt markieren</button>` 
                        : "";
                    const minSetzenButton = !abgeschlossen 
                        ? `<button class='btn btn-yellow' onclick='setMindestbetrag(${goal.id}, "${mindestbetrag}")'>Min setzen</button>` 
                        : "";
                    const deleteButton = `<button class='btn btn-red' onclick='deleteZiel(${goal.id}, "${goal.ziel}")'>Löschen</button>`;

                    newTable += `
                        <tr>
                            <td>${goal.ziel}</td>
                            <td>${goal.gesamtbetrag} €</td>
                            <td>${mbString}</td>
                            <td>${status}</td>
                            <td>${minSetzenButton} ${actionButton} ${sichtbarBtn} ${deleteButton}</td>
                        </tr>`;
                });

                zieleTable.innerHTML = newTable || '<tr><td colspan="5">Keine Ziele gefunden</td></tr>';
            })
            .catch(error => console.error('Fehler beim Aktualisieren der Ziele:', error));
        }

        // Funktion zum Aktualisieren der Spenden-Tabelle
        function updateSpendenTable() {
            return fetch('get_spenden.php')
            .then(response => response.json())
            .then(data => {
                const spendenTable = document.getElementById('spendenTable');
                if (!spendenTable) return;

                // Erstelle die komplette Tabelle neu, inkl. Header
                let newTable = `
                    <tr>
                        <th>ID</th>
                        <th>Benutzername</th>
                        <th>Betrag (€)</th>
                        <th>Ziel</th>
                        <th>Datum</th>
                        <th>Bearbeiten</th>
                    </tr>`;

                data.forEach(spende => {
                    newTable += `
                        <tr>
                            <td>${spende.id}</td>
                            <td>${spende.benutzername}</td>
                            <td>${spende.betrag}</td>
                            <td>${spende.ziel}</td>
                            <td>${spende.datum}</td>
                            <td>
                                <button class="btn btn-blue" onclick="openEditModal(${spende.id}, '${spende.ziel}')">Bearbeiten</button>
                                <button class="btn btn-red" onclick="deleteSpende(${spende.id})">Löschen</button>
                            </td>
                        </tr>`;
                });

                spendenTable.innerHTML = newTable || '<tr><td colspan="6">Keine Spenden gefunden</td></tr>';
            })
            .catch(error => console.error('Fehler beim Aktualisieren der Spenden:', error));
        }

        function deleteZiel(zielId, zielName) {
            if (confirm(`Möchten Sie das Ziel "${zielName}" wirklich löschen?\nAlle zugehörigen Spenden werden ebenfalls gelöscht!`)) {
                fetch('delete_ziel.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ziel_id=${zielId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Ziel und zugehörige Spenden wurden erfolgreich gelöscht!');
                        refreshTables();
                    } else {
                        alert('Fehler beim Löschen: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Fehler:', error);
                    alert('Fehler beim Löschen des Ziels');
                });
            }
        }

        // Funktion zum Aktualisieren beider Tabellen
        function refreshTables() {
            Promise.all([updateZieleTable(), updateSpendenTable()])
                .catch(error => console.error('Fehler beim Aktualisieren der Tabellen:', error));
        }
    </script>
</body>
</html>
