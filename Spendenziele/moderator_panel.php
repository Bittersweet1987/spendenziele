<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['moderator_id'])) {
    header("Location: moderator_login.php");
    exit;
}

require_once __DIR__ . '/config.php';

// Logout-Weiterleitungsziel abrufen
$stmt = $pdo->prepare("SELECT wert FROM einstellungen WHERE schluessel = 'logout_redirect'");
$stmt->execute();
$logoutRedirect = $stmt->fetchColumn() ?: 'spendenziele.php'; // Standardwert: spendenziele.php

// Aktueller Moderator
$mod_id = $_SESSION['moderator_id'];
$stmt = $pdo->prepare("SELECT benutzername FROM moderatoren WHERE id = ?");
$stmt->execute([$mod_id]);
$moderator = $stmt->fetch(PDO::FETCH_ASSOC);

// Ziele laden (m. mindestbetrag)
$stmtziele = $pdo->query("SELECT id, ziel, gesamtbetrag, mindestbetrag FROM ziele ORDER BY gesamtbetrag DESC");
$ziele = $stmtziele->fetchAll(PDO::FETCH_ASSOC);

// Spenden laden
$stmtSpenden = $pdo->query("SELECT id, benutzername, betrag, ziel, datum FROM spenden ORDER BY datum DESC");
$spenden = $stmtSpenden->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Moderatoren-Übersicht</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            text-align: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 8px;
            display: inline-block;
            width: 80%;
            max-width: 800px;
            position: relative;
        }
        .logout-btn {
            background: #ff0000;
            padding: 10px 15px;
            border: none;
            color: white;
            border-radius: 5px;
            cursor: pointer;
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .logout-btn:hover {
            background: #cc0000;
        }
        .btn-pass, .btn-spende {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 10px;
        }
        .btn-pass:hover, .btn-spende:hover {
            background-color: #0056b3;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #007bff;
            color: white;
        }
        .btn-edit {
            background-color: #ffc107;
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            color: white;
            cursor: pointer;
        }
        .btn-edit:hover {
            background-color: #e0a800;
        }
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
            background: white;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 50%;
            position: relative;
            text-align: center;
        }
        .close {
            color: #aaa;
            position: absolute;
            right: 15px;
            top: 5px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: black;
        }
    </style>
    <script>
        // Logout
		function logout() {
			window.location.href = '<?= htmlspecialchars($logoutRedirect) ?>';
		}

        // Passwort ändern
        function changePassword() {
            const oldPass = prompt('Gib dein aktuelles Passwort ein:');
            if (!oldPass) return;
            const newPass = prompt('Gib dein neues Passwort ein:');
            if (!newPass) return;

            fetch('moderator_change_password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ oldPass: oldPass, newPass: newPass })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Passwort erfolgreich geändert!');
                } else {
                    alert('Fehler: ' + data.error);
                }
            })
            .catch(error => console.error('Fehler:', error));
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
                location.reload();
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
                    location.reload();
                } else {
                    alert('Fehler: ' + data.error);
                }
            })
            .catch(error => console.error('Fehler:', error));
        }
    </script>
</head>
<body>
    <div class="container">
        <button class="logout-btn" onclick="logout()">Logout</button>

        <h2>Willkommen, 
            <?php if ($moderator): ?>
                <?= htmlspecialchars($moderator['benutzername']) ?>
            <?php else: ?>
                Moderator
            <?php endif; ?>
        </h2>
        <button class="btn-pass" onclick="changePassword()">Eigenes Passwort ändern</button>

		<h3>Manuelle Spende erfassen</h3>
        <input type="text" id="mod-benutzername" placeholder="Benutzername" required>
        <input type="number" step="0.01" id="mod-betrag" placeholder="Betrag (€)" required>
        <input type="text" id="mod-ziel" placeholder="Zielname" required>
        <button class="btn-spende" onclick="addSpende()">Spende erfassen</button>

        <!-- Ziele-Übersicht (m. mindestbetrag) -->
        <h3>Ziele & Gesamtbetrag</h3>
        <table>
            <tr>
                <th>Ziel</th>
                <th>Gesamtbetrag (€)</th>
                <th>Mindestbetrag</th>
            </tr>
            <?php if ($ziele): ?>
                <?php foreach($ziele as $city):
                    // mindestbetrag in passender Anzeige
                    if (is_null($city['mindestbetrag'])) {
                        $mbString = "-";
                    } elseif ($city['mindestbetrag'] == 0) {
                        $mbString = "Nicht notwendig";
                    } else {
                        $mbString = $city['mindestbetrag']." €";
                    }
                ?>
                <tr>
                    <td><?= htmlspecialchars($city['ziel']) ?></td>
                    <td><?= htmlspecialchars($city['gesamtbetrag']) ?></td>
                    <td><?= $mbString ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="3">Keine Ziele vorhanden</td></tr>
            <?php endif; ?>
        </table>

        <!-- Spenden-Übersicht -->
        <h3>Spenden-Übersicht</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>Benutzername</th>
                <th>Betrag (€)</th>
                <th>Ziel</th>
                <th>Datum</th>
                <th>Bearbeiten</th>
            </tr>
            <?php if ($spenden): ?>
                <?php foreach ($spenden as $spende): ?>
                    <tr>
                        <td><?= htmlspecialchars($spende['id']) ?></td>
                    <td><?= htmlspecialchars($spende['benutzername']) ?></td>
                    <td><?= htmlspecialchars($spende['betrag']) ?></td>
                    <td><?= htmlspecialchars($spende['ziel']) ?></td>
                    <td><?= htmlspecialchars($spende['datum']) ?></td>
                        <td>
                            <button class="btn-edit" 
                                onclick="openEditModal(<?= $spende['id'] ?>, '<?= htmlspecialchars($spende['ziel']) ?>')">
                                Bearbeiten
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6">Keine Spenden gefunden</td></tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- MODAL fürs Bearbeiten der Ziel -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Ziel bearbeiten</h2>
            <p>Aktuelle Ziel: <strong id="currentZiel"></strong></p>
            <input type="hidden" id="spendeId">
            <input type="text" id="newZiel" placeholder="Neue Ziel eingeben">
            <br><br>
            <button style="background-color: #28a745; color: white; padding: 8px 12px; border-radius: 5px; cursor: pointer;"
                    onclick="updateZiel()">
                Speichern
            </button>
        </div>
    </div>
</body>
</html>