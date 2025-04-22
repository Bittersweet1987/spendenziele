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
    ziel VARCHAR(100) NOT NULL UNIQUE,
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

-- Benenne die Spalte 'name' in 'ziel' um
SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'ziele' 
    AND COLUMN_NAME = 'name' 
    AND TABLE_SCHEMA = DATABASE());

SET @sql = IF(@columnExists > 0, 
    'ALTER TABLE `ziele` CHANGE `name` `ziel` VARCHAR(100) NOT NULL UNIQUE',
    'SELECT 1');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Anpassung der Spalten in der Tabelle 'ziele'
ALTER TABLE `ziele` MODIFY COLUMN `ziel` VARCHAR(100) NOT NULL UNIQUE;
ALTER TABLE `ziele` MODIFY COLUMN `gesamtbetrag` DECIMAL(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `ziele` MODIFY COLUMN `mindestbetrag` DECIMAL(10,2) DEFAULT NULL;
ALTER TABLE `ziele` MODIFY COLUMN `abgeschlossen` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `ziele` MODIFY COLUMN `sichtbar` TINYINT(1) NOT NULL DEFAULT 0;

-- Anpassung der Spalten in der Tabelle 'admin'
ALTER TABLE `admin` MODIFY COLUMN `benutzername` VARCHAR(50) UNIQUE NOT NULL;
ALTER TABLE `admin` MODIFY COLUMN `passwort_hash` VARCHAR(255) NOT NULL;

-- Anpassung der Spalten in der Tabelle 'moderatoren'
ALTER TABLE `moderatoren` MODIFY COLUMN `benutzername` VARCHAR(50) UNIQUE NOT NULL;
ALTER TABLE `moderatoren` MODIFY COLUMN `passwort_hash` VARCHAR(255) NOT NULL;
ALTER TABLE `moderatoren` MODIFY COLUMN `status` ENUM('aktiv', 'inaktiv') NOT NULL DEFAULT 'aktiv';

-- Anpassung der Spalten in der Tabelle 'spenden'
ALTER TABLE `spenden` MODIFY COLUMN `benutzername` VARCHAR(255) NOT NULL;
ALTER TABLE `spenden` MODIFY COLUMN `betrag` DECIMAL(10,2) NOT NULL;
ALTER TABLE `spenden` MODIFY COLUMN `ziel` VARCHAR(100) NOT NULL;
ALTER TABLE `spenden` MODIFY COLUMN `datum` DATETIME DEFAULT CURRENT_TIMESTAMP;

-- Anpassung der Spalten in der Tabelle 'einstellungen'
ALTER TABLE `einstellungen` MODIFY COLUMN `schluessel` VARCHAR(255) NOT NULL;
ALTER TABLE `einstellungen` MODIFY COLUMN `wert` TEXT NOT NULL;

-- Anpassung der Spalten in der Tabelle 'zeitzonen'
ALTER TABLE `zeitzonen` MODIFY COLUMN `name` VARCHAR(255) NOT NULL UNIQUE;

-- Anpassung der Spalten in der Tabelle 'zeitraum'
ALTER TABLE `zeitraum` MODIFY COLUMN `start` DATETIME NOT NULL;
ALTER TABLE `zeitraum` MODIFY COLUMN `ende` DATETIME NOT NULL;

-- Füge die Spalte 'ziel' hinzu, falls sie nicht existiert
SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'ziele' 
    AND COLUMN_NAME = 'ziel' 
    AND TABLE_SCHEMA = DATABASE());

SET @sql = IF(@columnExists = 0, 
    'ALTER TABLE `ziele` ADD COLUMN `ziel` VARCHAR(100) NOT NULL UNIQUE AFTER `id`',
    'SELECT 1');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Stelle die Daten aus der spenden-Tabelle wieder her
INSERT IGNORE INTO `ziele` (`ziel`)
SELECT DISTINCT `ziel` FROM `spenden`
WHERE `ziel` NOT IN (SELECT `ziel` FROM `ziele`); 