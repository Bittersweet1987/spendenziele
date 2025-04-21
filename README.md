# 🎯 Spendenziele System

Ein System zur Verwaltung von Spendenzielen für Streamer, mit Streamer.Bot-Integration und OBS-Widgets.

## 📑 Inhaltsverzeichnis
- [Installation](#installation)
- [Updates](#updates)
- [Konfiguration](#konfiguration)
- [Streamer.Bot Integration](#streamerbot-integration)
- [Panels](#panels)
  - [Admin-Panel](#admin-panel)
  - [Moderator-Panel](#moderator-panel)
- [OBS-Widgets](#obs-widgets)
- [Funktionsweise](#funktionsweise)
- [Wartung & Sicherheit](#wartung--sicherheit)

## 🚀 Installation

### 🌐 Webhosting
Einen kostenlosen Webspace mit PHP und MySQL-Datenbank finden Sie hier:
[FreeHostingNoAds.net](https://freehostingnoads.net/)

Features:
- 1GB Webspace
- PHP 5.x bis 7.4.2
- MySQL 5.7
- Keine Zwangswerbung
- Kostenlose E-Mail-Adressen
- 99.9% Uptime

### 📥 Installation

1. Vorbereitung:
   - Erstellen Sie eine neue MySQL-Datenbank in Ihrem Webspace
   - Notieren Sie sich die Zugangsdaten (Datenbankname, Benutzername, Passwort)

2. Installation:
   - Laden Sie nur die Datei `install.php` auf Ihren Webspace hoch
   - Öffnen Sie die Installationsseite in Ihrem Browser:
     ```
     https://ihre-domain.de/pfad/zu/install.php
     ```

3. Folgen Sie dem Installationsassistenten:
   - Geben Sie Ihre Datenbankverbindungsdaten ein
   - Erstellen Sie Ihren Admin-Account
   - Die Installation lädt automatisch alle benötigten Dateien herunter
   - Die Datenbanktabellen werden automatisch eingerichtet
   - Nach erfolgreicher Installation werden Sie zum Admin-Panel weitergeleitet
   - Die install.php wird automatisch gelöscht

Wichtige Hinweise:
- Die config.php wird während der Installation automatisch erstellt
- Sollten Sie Ihr Admin-Passwort vergessen, müssen Sie die install.php erneut hochladen und einen neuen Admin-Account erstellen
- Ihre Datenbank-Inhalte bleiben bei Updates erhalten
- Geschützte Dateien (z.B. config.php) werden nicht überschrieben
- Es wird empfohlen, vor einem Update ein Backup zu erstellen

## 🔄 Updates

Das System verfügt über eine integrierte Update-Funktion:

1. Im Admin-Panel wird automatisch angezeigt, wenn ein Update verfügbar ist
2. Klicken Sie auf "Update durchführen", um den Update-Prozess zu starten
3. Der Update-Prozess läuft in drei Schritten ab:
   - Schritt 1: Datenbank-Update (Strukturänderungen werden automatisch erkannt und angewendet)
   - Schritt 2: Dateien-Update (Neue und geänderte Dateien werden automatisch aktualisiert)
   - Schritt 3: Abschluss und Übersicht der durchgeführten Änderungen

## ⚙️ Konfiguration

Die Konfiguration erfolgt über den Installationsassistenten. Nach der Installation können Sie alle Einstellungen über das Admin-Panel vornehmen.

## 🤖 Streamer.Bot Integration

1. Importieren Sie die Datei "Streamerbot_import.txt" von GitHub:
   - Öffnen Sie Streamer.Bot
   - Klicken Sie auf "Import" im Hauptmenü
   - Kopieren Sie den Inhalt der Datei von:
     ```
     https://github.com/Bittersweet1987/spendenziele/blob/main/Streamerbot/Streamerbot_import.txt
     ```

2. Konfigurieren Sie die Action:
   - Suchen Sie die Action "Donationziel"
   - Doppelklicken Sie auf die Sub-Action "Set argument..."
   - Ändern Sie bei "Value" die URL zu Ihrer Installation:
     ```
     https://ihre-domain.de/pfad/zu/store_donation.php
     ```
   - Klicken Sie auf "Ok" und "Save"

3. Testen der Integration:
   - Führen Sie die Action "Donationziel" testweise aus
   - Prüfen Sie im Moderator- oder Admin-Panel, ob die Testspende angekommen ist

## 🎛️ Panels

### Admin-Panel
**URL:** `https://ihre-domain.de/pfad/zu/admin_panel.php`

**Funktionen:**
- 🔧 Einstellungen verwalten
  - Zeitzone ändern
  - Admin-Passwort ändern
- 👥 Moderatoren verwalten
  - Neue Moderatoren anlegen
  - Moderatoren aktivieren/deaktivieren
  - Moderatoren löschen
  - Moderatoren-Passwörter ändern
- ⏱️ Spendenzeitraum festlegen
- 🎯 Ziele & Gesamtbetrag verwalten
- 💰 Spenden-Übersicht

### Moderator-Panel
**URL:** `https://ihre-domain.de/pfad/zu/moderator_panel.php`

**Funktionen:**
- 🔑 Eigenes Passwort ändern
- 💸 Manuelle Spenden erfassen
- ✏️ Spenden bearbeiten

## 🖥️ OBS-Widgets

### Timer Widget
- **Datei:** `timer_widget.html`
- **Einrichtung in OBS:**
  1. Fügen Sie eine neue "Browser"-Quelle hinzu
  2. Aktivieren Sie "Lokale Datei"
  3. Wählen Sie die Datei `timer_widget.html`
  4. Empfohlene Größe: 800x100 Pixel
- **Funktion:** Zeigt den aktiven Spendenzeitraum als Countdown an

### Top Ziele Widget
- **Datei:** `top_ziele_widget.html`
- **Einrichtung in OBS:**
  1. Fügen Sie eine neue "Browser"-Quelle hinzu
  2. Aktivieren Sie "Lokale Datei"
  3. Wählen Sie die Datei `top_ziele_widget.html`
  4. Empfohlene Größe: 400x600 Pixel
- **Funktion:** 
  - Zeigt die Top-Spendenziele aus dem Ranking an
  - Wechselt automatisch alle 20 Sekunden zwischen 6 Zielen
  - Zeigt Gesamtbetrag und Spender pro Ziel

### Offene Ziele Widget
- **Datei:** `offene_ziele_widget.html`
- **Einrichtung in OBS:**
  1. Fügen Sie eine neue "Browser"-Quelle hinzu
  2. Aktivieren Sie "Lokale Datei"
  3. Wählen Sie die Datei `offene_ziele_widget.html`
  4. Empfohlene Größe: 400x600 Pixel
- **Funktion:**
  - Zeigt noch nicht erreichte Spendenziele an
  - Wechselt automatisch alle 10 Sekunden zwischen 5 Zielen
  - Zeigt Fortschrittsbalken und fehlenden Betrag

### Abgeschlossene Ziele Widget
- **Datei:** `abgeschlossene_ziele_widget.html`
- **Einrichtung in OBS:**
  1. Fügen Sie eine neue "Browser"-Quelle hinzu
  2. Aktivieren Sie "Lokale Datei"
  3. Wählen Sie die Datei `abgeschlossene_ziele_widget.html`
  4. Empfohlene Größe: 400x600 Pixel
- **Funktion:**
  - Zeigt bereits erreichte, aber noch nicht durchgeführte Ziele
  - Wechselt automatisch alle 10 Sekunden zwischen 5 Zielen
  - Zeigt Gesamtbetrag und Anzahl der Spender

## 📊 Funktionsweise

### Spendenziele.php
- Zeigt alle Spendenziele mit Mindestbeträgen
- Kein Ranking-System
- Zwei Ansichten:
  - Noch nicht erreichte Ziele
  - Erreichte, aber noch nicht durchgeführte Ziele

### Spendenranking.php
- Fokus auf die meistgespendete Aktivität
- Nur die höchste Spende wird durchgeführt
- Keine Mindestbeträge

## 🛠️ Wartung & Sicherheit

### Wartung
- Die Datenbank wird automatisch aktualisiert
- Bestehende Daten bleiben bei Updates erhalten

### Sicherheit
- ⚠️ Ändern Sie die Standard-Passwörter
- 🔒 Schützen Sie die Admin- und Moderator-Panels
- 💾 Erstellen Sie regelmäßige Backups der Datenbank

## 📝 Lizenz

Dieses Projekt steht unter der MIT-Lizenz. 