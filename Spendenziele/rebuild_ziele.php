<?php
require_once __DIR__ . '/config.php';

// Prüfe ob es sich um einen AJAX-Request handelt
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    try {
        // Starte Transaktion
        $pdo->beginTransaction();

        // Lösche alle Einträge in der Tabelle ziele
        $pdo->exec("TRUNCATE TABLE ziele");

        // Erstelle neue Einträge basierend auf der Tabelle spenden
        $sql = "
            INSERT INTO ziele (ziel, gesamtbetrag)
            SELECT 
                ziel,
                SUM(betrag) as gesamtbetrag
            FROM spenden
            GROUP BY ziel
        ";
        
        $pdo->exec($sql);

        // Commit der Transaktion
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Tabelle ziele wurde erfolgreich neu aufgebaut'
        ]);

    } catch (Exception $e) {
        // Bei Fehler Rollback durchführen
        $pdo->rollBack();
        
        echo json_encode([
            'success' => false,
            'error' => 'Fehler beim Neubau der Tabelle: ' . $e->getMessage()
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ziele neu aufbauen</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .button {
            background-color: #4CAF50;
            border: none;
            color: white;
            padding: 15px 32px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
            margin: 20px 0;
            cursor: pointer;
            border-radius: 4px;
        }
        .button:hover {
            background-color: #45a049;
        }
        .result {
            padding: 20px;
            margin-top: 20px;
            border-radius: 4px;
        }
        .success {
            background-color: #dff0d8;
            color: #3c763d;
            border: 1px solid #d6e9c6;
        }
        .error {
            background-color: #f2dede;
            color: #a94442;
            border: 1px solid #ebccd1;
        }
    </style>
</head>
<body>
    <h1>Ziele neu aufbauen</h1>
    <p>Diese Funktion löscht die Tabelle "ziele" und baut sie neu auf, basierend auf den aktuellen Spenden.</p>
    
    <button id="rebuildButton" class="button">Ziele neu aufbauen</button>
    
    <div id="result" class="result" style="display: none;"></div>

    <script>
        document.getElementById('rebuildButton').addEventListener('click', function() {
            const button = this;
            const resultDiv = document.getElementById('result');
            
            // Button deaktivieren
            button.disabled = true;
            button.textContent = 'Wird ausgeführt...';
            
            // AJAX-Request
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'rebuild_ziele.php', true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            
            xhr.onload = function() {
                try {
                    const response = JSON.parse(xhr.responseText);
                    
                    resultDiv.style.display = 'block';
                    if (response.success) {
                        resultDiv.className = 'result success';
                        resultDiv.textContent = response.message;
                    } else {
                        resultDiv.className = 'result error';
                        resultDiv.textContent = response.error;
                    }
                } catch (e) {
                    resultDiv.style.display = 'block';
                    resultDiv.className = 'result error';
                    resultDiv.textContent = 'Fehler beim Verarbeiten der Antwort';
                }
                
                // Button wieder aktivieren
                button.disabled = false;
                button.textContent = 'Ziele neu aufbauen';
            };
            
            xhr.onerror = function() {
                resultDiv.style.display = 'block';
                resultDiv.className = 'result error';
                resultDiv.textContent = 'Netzwerkfehler aufgetreten';
                
                // Button wieder aktivieren
                button.disabled = false;
                button.textContent = 'Ziele neu aufbauen';
            };
            
            xhr.send();
        });
    </script>
</body>
</html> 