<?php
session_start();
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $benutzername = $_POST['benutzername'] ?? '';
    $passwort     = $_POST['passwort'] ?? '';

    if ($benutzername && $passwort) {
        // Moderator in DB suchen
        $stmt = $pdo->prepare("SELECT id, passwort_hash, status FROM moderatoren WHERE benutzername = ?");
        $stmt->execute([$benutzername]);
        $mod = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($mod && password_verify($passwort, $mod['passwort_hash'])) {
            if ($mod['status'] === 'aktiv') {
                // Moderator erfolgreich eingeloggt
                $_SESSION['moderator_id'] = $mod['id'];
                header("Location: moderator_seite.php");
                exit;
            } else {
                $error = "Dein Zugang ist inaktiv!";
            }
        } else {
            $error = "Falscher Benutzername oder Passwort";
        }
    } else {
        $error = "Bitte alle Felder ausfüllen!";
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Moderator Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .login-container {
            background: #fff;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 8px;
            text-align: center;
            width: 300px;
        }
        input {
            width: 80%;
            padding: 10px;
            margin: 10px 0;
        }
        button {
            background: #007bff;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background: #0056b3;
        }
        .error {
            color: red;
            margin: 5px 0;
        }
        label {
            display: block;
            text-align: left;
            margin-left: 10%;
        }
    </style>
</head>
<body>
<div class="login-container">
    <h2>Moderator Login</h2>
    <?php if (!empty($error)): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="POST">
        <label for="benutzername">Benutzername:</label>
        <input type="text" id="benutzername" name="benutzername" required>
        
        <label for="passwort">Passwort:</label>
        <input type="password" id="passwort" name="passwort" required>
        
        <button type="submit">Login</button>
    </form>
</div>
</body>
</html>
