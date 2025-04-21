<?php
session_start();
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $benutzername = $_POST['benutzername'] ?? '';
    $passwort = $_POST['passwort'] ?? '';
    
    if ($benutzername && $passwort) {
        $stmt = $pdo->prepare("SELECT id, passwort_hash FROM admin WHERE benutzername = ?");
        $stmt->execute([$benutzername]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && password_verify($passwort, $admin['passwort_hash'])) {
            $_SESSION['admin_id'] = $admin['id'];
            echo "<script>window.location.href='admin_panel.php';</script>";
            exit;
        } else {
            $error = "Falscher Benutzername oder Passwort";
        }
    } else {
        $error = "Bitte alle Felder ausfüllen";
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f4f4f4;
        }
        .login-container {
            background: white;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            text-align: center;
        }
        input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Admin Login</h2>
        <?php if (!empty($error)) { echo "<p style='color: red;'>$error</p>"; } ?>
        <form method="POST">
            <label for="benutzername">Benutzername:</label>
            <input type="text" id="benutzername" name="benutzername" required>
            <br>
            <label for="passwort">Passwort:</label>
            <input type="password" id="passwort" name="passwort" required>
            <br>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>