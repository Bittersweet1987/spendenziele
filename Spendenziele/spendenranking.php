<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Spendenranking</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            text-align: center;
            padding: 20px;
            margin: 0; /* Damit position:absolute top/right funktioniert */
        }
        .top-right-login {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .login-btn {
            background: #007bff;
            color: #fff;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
        }
        .login-btn:hover {
            background: #0056b3;
        }
        /* Container für die Zuschauer-Inhalte */
        .container {
            background: white;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 8px;
            display: inline-block;
            margin-top: 60px; /* Platz für den Login-Button oben */
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
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
		
        /* Countdown */
		#countdown {
				display: flex;
				justify-content: center;
				gap: 15px;
				font-size: 20px;
				margin-top: 20px;
			}
			.countdown-item {
				display: flex;
				flex-direction: column;
				align-items: center;
			}
			.flip-tile {
				position: relative;
				background: #333;
				color: white;
				width: 60px;
				height: 80px;
				font-size: 36px;
				font-weight: bold;
				line-height: 80px;
				border-radius: 5px;
				overflow: hidden;
				text-align: center;
				perspective: 1000px;
			}
			.countdown-label {
				font-size: 14px;
				margin-top: 5px;
				color: #333;
			}
        /* Login-Modal */
        .login-modal {
            display: none;
            position: fixed;
            z-index: 999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        .login-modal-content {
            background: #fff;
            margin: 10% auto;
            padding: 20px;
            width: 300px;
            position: relative;
            border-radius: 8px;
            text-align: center;
        }
        .close {
            position: absolute;
            top: 5px;
            right: 10px;
            cursor: pointer;
            font-size: 20px;
            font-weight: bold;
        }
        .close:hover {
            color: #555;
        }
        input {
            width: 80%;
            padding: 10px;
            margin: 5px 0;
        }
        .login-submit-btn {
            background: #28a745;
            color: #fff;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
        }
        .login-submit-btn:hover {
            background: #218838;
        }
        /* Spenden-Form */
        .spenden-form {
            margin-top: 20px;
        }
        .spenden-form input, .spenden-form button {
            padding: 10px;
            margin: 5px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .spenden-form button {
            background-color: #28a745;
            color: white;
            cursor: pointer;
        }
        .spenden-form button:hover {
            background-color: #218838;
        }
    </style>
    <script>
        // Login-Modal öffnen / schließen
        function openLoginModal() {
            document.getElementById("loginModal").style.display = "block";
        }
        function closeLoginModal() {
            document.getElementById("loginModal").style.display = "none";
        }

        // Perform Login
        function performLogin() {
            const benutzername = document.getElementById("loginUser").value;
            const passwort = document.getElementById("loginPass").value;

            fetch('login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ benutzername, passwort })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (data.role === 'admin') {
                        window.location.href = 'admin_panel.php';
                    } else if (data.role === 'moderator') {
                        window.location.href = 'moderator_panel.php';
                    }
                } else {
                    alert(data.error || 'Login fehlgeschlagen');
                }
            })
            .catch(err => console.error('Fehler beim Login:', err));
        }

        // Lade ziele + mindestbetrag
        // Spendenübersicht automatisch aktualisieren
function loadziele() {
    fetch('get_ziele.php')
    .then(response => response.json())
    .then(data => {
        let tableBody = document.getElementById('ziele-tabelle');
        if (!tableBody) return;

        // Vorherige Daten speichern, um unnötige Updates zu vermeiden
        let previousData = tableBody.getAttribute("data-content") || "";
        let newData = JSON.stringify(data);

        if (newData !== previousData) { // Nur aktualisieren, wenn sich etwas geändert hat
            tableBody.innerHTML = "";
            tableBody.setAttribute("data-content", newData); // Neues Datum speichern

            data.forEach(goal => {
                let mbString = "-";
                if (goal.mindestbetrag !== null) {
                    if (parseFloat(goal.mindestbetrag) === 0) {
                        mbString = "Nicht notwendig";
                    } else {
                        mbString = goal.mindestbetrag + " €";
                    }
                }
                let row = `<tr>
                    <td>${goal.ziel}</td>
                    <td>${goal.gesamtbetrag} €</td>
                    <td>${mbString}</td>
                </tr>`;
                tableBody.innerHTML += row;
            });
        }
    })
    .catch(error => console.error("Fehler beim Laden der Städte:", error));
}

// Erstes Laden + Automatische Aktualisierung alle 5 Sekunden
setInterval(loadziele, 5000);
window.onload = function() {
    loadziele();
    loadZeitraum();
};

        // Countdown + Zeitraum
        function loadZeitraum() {
            fetch('get_zeitraum.php')
            .then(response => response.json())
            .then(data => {
                if (data.ende) {
                    document.getElementById('zeitraum').innerText = `${data.start} bis ${data.ende}`;
                    startCountdown(data.ende);
                } else {
                    document.getElementById('zeitraum').innerText = "Kein Zeitraum festgelegt.";
                    document.getElementById('countdown').innerText = "";
                }
            });
        }
        function startCountdown(endTime) {
				const countdownElement = document.getElementById('countdown');
				const endDate = new Date(endTime.replace(' Uhr', '').replace(/(\d{2})\.(\d{2})\.(\d{4}) (\d{2}):(\d{2})/, '$3-$2-$1T$4:$5:00'));

				function updateCountdown() {
                const now = new Date();
                const diff = endDate - now;

                if (diff <= 0) {
                    document.getElementById('countdown').innerHTML = "<span>Die Spendenphase ist beendet!</span>";
                    return;
                }

                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((diff % (1000 * 60)) / 1000);

                document.getElementById('days').textContent = days.toString().padStart(2, '0');
                document.getElementById('hours').textContent = hours.toString().padStart(2, '0');
                document.getElementById('minutes').textContent = minutes.toString().padStart(2, '0');
                document.getElementById('seconds').textContent = seconds.toString().padStart(2, '0');
            }
            updateCountdown();
            setInterval(updateCountdown, 1000);
			}

			window.onload = function() {
				loadZiele();
				loadZeitraum();
				setInterval(loadZiele, 5000);
			};

        // Spende abschicken
        function spenden() {
            const benutzername = document.getElementById('benutzername').value;
            const betrag = document.getElementById('betrag').value;
            const ziel = document.getElementById('ziel').value;

            fetch('add_spende.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ benutzername, betrag, ziel })
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                loadziele(); // aktualisiere
            });
        }
		
        window.onload = function() {
            loadziele();
            loadZeitraum();
        };
    </script>
</head>
<body>

<!-- Login-Button oben rechts -->
<div class="top-right-login">
    <button class="login-btn" onclick="openLoginModal()">Admin & Mod Login</button>
</div>

<div class="container">
    <h2>Aktuelle Spendenübersicht</h2>
    <p>Spendenzeitraum: <strong id="zeitraum"></strong></p>
	
    <!-- Countdown mit Labels -->
    <div id="countdown">
        <div class="countdown-item">
            <div class="flip-tile" id="days">00</div>
            <div class="countdown-label">Tage</div>
        </div>
        <div class="countdown-item">
            <div class="flip-tile" id="hours">00</div>
            <div class="countdown-label">Stunden</div>
        </div>
        <div class="countdown-item">
            <div class="flip-tile" id="minutes">00</div>
            <div class="countdown-label">Minuten</div>
        </div>
        <div class="countdown-item">
            <div class="flip-tile" id="seconds">00</div>
            <div class="countdown-label">Sekunden</div>
        </div>
    </div>

    <!-- Tabelle für Städte + mindestbetrag -->
    <table>
        <tr>
            <th>ziel</th>
            <th>Gesamtbetrag</th>
            <th>Mindestbetrag</th>
        </tr>
        <tbody id="ziele-tabelle">
            <!-- wird per JS gefüllt -->
        </tbody>
    </table>
	
	

    <!-- Spenden-Form -->
    <!-- <div class="spenden-form">
        <h3>Spende jetzt für eine ziel</h3>
        <input type="text" id="benutzername" placeholder="Dein Name" required>
        <input type="number" id="betrag" placeholder="Betrag in €" required>
        <input type="text" id="ziel" placeholder="zielname" required>
        <button onclick="spenden()">Spenden</button>
    </div> -->
</div>

<!-- MODAL für Admin/Moderator-Login -->
<div id="loginModal" class="login-modal">
    <div class="login-modal-content">
        <span class="close" onclick="closeLoginModal()">&times;</span>
        <h2>Login</h2>
        <input type="text" id="loginUser" placeholder="Benutzername" required><br>
        <input type="password" id="loginPass" placeholder="Passwort" required><br>
        <button class="login-submit-btn" onclick="performLogin()">Einloggen</button>
    </div>
</div>

</body>
</html>
