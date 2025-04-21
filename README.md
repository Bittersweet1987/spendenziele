# 🎯 Spendenziele System

Ein System zur Verwaltung von Spendenzielen für Streamer, mit Streamer.Bot-Integration und OBS-Widgets.

## 📑 Inhaltsverzeichnis
- [Installation](#installation)
- [Konfiguration](#konfiguration)
- [Streamer.Bot Integration](#streamerbot-integration)
- [Panels](#panels)
  - [Admin-Panel](#admin-panel)
  - [Moderator-Panel](#moderator-panel)
- [OBS-Widgets](#obs-widgets)
- [Funktionsweise](#funktionsweise)
- [Wartung & Sicherheit](#wartung--sicherheit)

## 🚀 Installation

1. Laden Sie die neueste Version des Systems von GitHub herunter
2. Öffnen Sie die Installationsseite in Ihrem Browser:
   ```
   https://ihre-domain.de/pfad/zu/install.php
   ```
3. Folgen Sie dem Installationsassistenten:
   - Geben Sie Ihre Datenbankverbindungsdaten ein
   - Erstellen Sie Ihren Admin-Account
   - Die Installation richtet automatisch alle benötigten Tabellen ein

## ⚙️ Konfiguration

Die Konfiguration erfolgt über den Installationsassistenten. Nach der Installation können Sie alle Einstellungen über das Admin-Panel vornehmen.

## 🤖 Streamer.Bot Integration

1. Importieren Sie "Streamerbot_Stadtspende.sb" in Streamer.Bot
2. Doppelklicken Sie auf die Sub-Action "Set argument..."
3. Ändern Sie bei "Value" die URL zu Ihrer Installation:
   ```
   https://ihre-domain.de/pfad/zu/store_donation.php
   ```
4. Klicken Sie auf "Ok" und "Save"

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
- **Funktion:** Zeigt den Spenden-Timer an

### Top Ziele Widget
- **Datei:** `top_ziele_widget.html`
- **Funktion:** Zeigt die Top Spenden-Ziele an
- Wechselt alle 20 Sekunden zwischen 6 Zielen

### Offene Ziele Widget
- **Datei:** `offene_ziele_widget.html`
- **Funktion:** Zeigt nicht erreichte Ziele an
- Wechselt alle 10 Sekunden zwischen 5 Zielen

### Abgeschlossene Ziele Widget
- **Datei:** `abgeschlossene_ziele_widget.html`
- **Funktion:** Zeigt erreichte Ziele an
- Wechselt alle 10 Sekunden zwischen 5 Zielen

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