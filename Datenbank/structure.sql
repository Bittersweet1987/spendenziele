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

-- Strukturanpassungen für bestehende Tabellen
ALTER TABLE admin MODIFY benutzername VARCHAR(50) UNIQUE NOT NULL;
ALTER TABLE admin MODIFY passwort_hash VARCHAR(255) NOT NULL;

ALTER TABLE moderatoren MODIFY benutzername VARCHAR(50) UNIQUE NOT NULL;
ALTER TABLE moderatoren MODIFY passwort_hash VARCHAR(255) NOT NULL;
ALTER TABLE moderatoren MODIFY status ENUM('aktiv', 'inaktiv') NOT NULL DEFAULT 'aktiv';

ALTER TABLE spenden MODIFY benutzername VARCHAR(255) NOT NULL;
ALTER TABLE spenden MODIFY betrag DECIMAL(10,2) NOT NULL;
ALTER TABLE spenden MODIFY ziel VARCHAR(100) NOT NULL;
ALTER TABLE spenden MODIFY datum DATETIME DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE ziele MODIFY ziel VARCHAR(100) NOT NULL;
ALTER TABLE ziele MODIFY gesamtbetrag DECIMAL(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE ziele MODIFY mindestbetrag DECIMAL(10,2) DEFAULT NULL;
ALTER TABLE ziele MODIFY abgeschlossen TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE ziele MODIFY sichtbar TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE einstellungen MODIFY schluessel VARCHAR(255) NOT NULL;
ALTER TABLE einstellungen MODIFY wert TEXT NOT NULL;

ALTER TABLE zeitzonen MODIFY name VARCHAR(255) NOT NULL UNIQUE;

ALTER TABLE zeitraum MODIFY start DATETIME NOT NULL;
ALTER TABLE zeitraum MODIFY ende DATETIME NOT NULL; 