<?php
require_once __DIR__ . '/config.php';

// Funktion zum Prüfen, ob ein PRIMARY KEY existiert
function hasPrimaryKey($pdo, $table) {
    try {
        $stmt = $pdo->query("SHOW KEYS FROM $table WHERE Key_name = 'PRIMARY'");
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

try {
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

    // Überprüfen und Aktualisieren der Tabellenstrukturen
    $tables = [
        'admin' => [
            'ALTER TABLE admin MODIFY benutzername VARCHAR(50) UNIQUE NOT NULL',
            'ALTER TABLE admin MODIFY passwort_hash VARCHAR(255) NOT NULL',
            'ALTER TABLE admin ADD PRIMARY KEY (id)'
        ],
        'moderatoren' => [
            'ALTER TABLE moderatoren MODIFY benutzername VARCHAR(50) UNIQUE NOT NULL',
            'ALTER TABLE moderatoren MODIFY passwort_hash VARCHAR(255) NOT NULL',
            'ALTER TABLE moderatoren MODIFY status ENUM(\'aktiv\', \'inaktiv\') NOT NULL DEFAULT \'aktiv\'',
            'ALTER TABLE moderatoren ADD PRIMARY KEY (id)'
        ],
        'spenden' => [
            'ALTER TABLE spenden MODIFY benutzername VARCHAR(255) NOT NULL',
            'ALTER TABLE spenden MODIFY betrag DECIMAL(10,2) NOT NULL',
            'ALTER TABLE spenden MODIFY ziel VARCHAR(100) NOT NULL',
            'ALTER TABLE spenden MODIFY datum DATETIME DEFAULT CURRENT_TIMESTAMP',
            'ALTER TABLE spenden ADD PRIMARY KEY (id)'
        ],
        'ziele' => [
            'ALTER TABLE ziele MODIFY ziel VARCHAR(100) NOT NULL',
            'ALTER TABLE ziele MODIFY gesamtbetrag DECIMAL(10,2) NOT NULL DEFAULT 0.00',
            'ALTER TABLE ziele MODIFY mindestbetrag DECIMAL(10,2) DEFAULT NULL',
            'ALTER TABLE ziele MODIFY abgeschlossen TINYINT(1) NOT NULL DEFAULT 0',
            'ALTER TABLE ziele MODIFY sichtbar TINYINT(1) NOT NULL DEFAULT 0',
            'ALTER TABLE ziele ADD PRIMARY KEY (id)'
        ],
        'einstellungen' => [
            'ALTER TABLE einstellungen MODIFY schluessel VARCHAR(255) NOT NULL',
            'ALTER TABLE einstellungen MODIFY wert TEXT NOT NULL',
            'ALTER TABLE einstellungen ADD PRIMARY KEY (schluessel)'
        ],
        'zeitzonen' => [
            'ALTER TABLE zeitzonen MODIFY name VARCHAR(255) NOT NULL UNIQUE',
            'ALTER TABLE zeitzonen ADD PRIMARY KEY (id)'
        ],
        'zeitraum' => [
            'ALTER TABLE zeitraum MODIFY start DATETIME NOT NULL',
            'ALTER TABLE zeitraum MODIFY ende DATETIME NOT NULL',
            'ALTER TABLE zeitraum ADD PRIMARY KEY (id)'
        ]
    ];

    foreach ($tables as $table => $alterations) {
        try {
            foreach ($alterations as $alter) {
                // Prüfen, ob es sich um einen PRIMARY KEY handelt
                if (strpos($alter, 'ADD PRIMARY KEY') !== false) {
                    // Nur ausführen, wenn noch kein PRIMARY KEY existiert
                    if (!hasPrimaryKey($pdo, $table)) {
                        $pdo->exec($alter);
                    }
                } else {
                    $pdo->exec($alter);
                }
            }
        } catch (PDOException $e) {
            // Fehler ignorieren, wenn die Spalte bereits existiert
            if (strpos($e->getMessage(), 'Duplicate column name') === false) {
                throw $e;
            }
        }
    }

    echo "Datenbank-Setup erfolgreich abgeschlossen!";
} catch (PDOException $e) {
    die("Fehler beim Einrichten der Datenbank: " . $e->getMessage());
} 