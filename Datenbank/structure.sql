-- Tabelle für Admin
CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    benutzername VARCHAR(50) UNIQUE NOT NULL,
    passwort_hash VARCHAR(255) NOT NULL
);

-- Tabelle für Moderatoren
CREATE TABLE IF NOT EXISTS moderatoren (
    id INT AUTO_INCREMENT PRIMARY KEY,
    benutzername VARCHAR(50) UNIQUE NOT NULL,
    passwort_hash VARCHAR(255) NOT NULL,
    status ENUM('aktiv', 'inaktiv') NOT NULL DEFAULT 'aktiv'
);

-- Tabelle für Spenden
CREATE TABLE IF NOT EXISTS spenden (
    id INT AUTO_INCREMENT PRIMARY KEY,
    benutzername VARCHAR(255) NOT NULL,
    betrag DECIMAL(10,2) NOT NULL,
    ziel VARCHAR(100) NOT NULL,
    datum DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Tabelle für Spendenziele
CREATE TABLE IF NOT EXISTS ziele (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ziel VARCHAR(100) NOT NULL,
    gesamtbetrag DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    mindestbetrag DECIMAL(10,2) DEFAULT NULL,
    abgeschlossen TINYINT(1) NOT NULL DEFAULT 0,
    sichtbar TINYINT(1) NOT NULL DEFAULT 0
);

-- Tabelle für Einstellungen
CREATE TABLE IF NOT EXISTS einstellungen (
    schluessel VARCHAR(255) PRIMARY KEY,
    wert TEXT NOT NULL
);

-- Tabelle für Zeitzonen
CREATE TABLE IF NOT EXISTS zeitzonen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE
);

-- Tabelle für den Spendenzeitraum
CREATE TABLE IF NOT EXISTS zeitraum (
    id INT AUTO_INCREMENT PRIMARY KEY,
    start DATETIME NOT NULL,
    ende DATETIME NOT NULL
); 