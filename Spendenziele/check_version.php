<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['admin_id'])) {
    die(json_encode(['error' => 'Nicht eingeloggt']));
}

// Funktion zum Abrufen der GitHub Commit-Version
function getLatestGitHubCommit() {
    // Hole den GitHub Token aus der Konfiguration
    $githubToken = defined('GITHUB_TOKEN') ? GITHUB_TOKEN : '';
    
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: PHP',
                'Accept: application/vnd.github.v3+json',
                'Authorization: token ' . $githubToken
            ]
        ]
    ];
    
    $context = stream_context_create($opts);
    
    $apiUrl = 'https://api.github.com/repos/Bittersweet1987/spendenziele/commits';
    
    $response = @file_get_contents($apiUrl, false, $context);
    
    if ($response === false) {
        return ['hash' => 'Fehler', 'date' => null];
    }
    
    $commits = json_decode($response, true);
    if (!is_array($commits) || empty($commits)) {
        return ['hash' => 'Fehler', 'date' => null];
    }
    
    $latestCommit = $commits[0];
    $date = null;
    
    if (isset($latestCommit['commit']['committer']['date'])) {
        try {
            $date = new DateTime($latestCommit['commit']['committer']['date']);
            $date->setTimezone(new DateTimeZone('Europe/Berlin'));
        } catch (Exception $e) {
            // Fehler beim Parsen des Datums
        }
    }
    
    return [
        'hash' => substr($latestCommit['sha'], 0, 7),
        'date' => $date
    ];
}

// Setze Header für JSON-Antwort
header('Content-Type: application/json');

// Hole die aktuelle Version
$lastKnownCommitFile = __DIR__ . '/last_commit.txt';
$currentCommit = file_exists($lastKnownCommitFile) ? trim(file_get_contents($lastKnownCommitFile)) : 'Keine Version gefunden';

// Hole die neueste Version von GitHub
$latestCommit = getLatestGitHubCommit();
$updateAvailable = ($currentCommit !== $latestCommit['hash']);

// Prüfe den Anfrage-Typ
$checkType = $_GET['type'] ?? 'version';

if ($checkType === 'update') {
    // Prüfe Update-Status (für update.php)
    $sessionCommit = $_SESSION['current_commit'] ?? '';
    $updateTimestamp = $_SESSION['update_timestamp'] ?? 0;

    // Wenn der aktuelle Commit mit dem Session-Commit übereinstimmt
    // und der Zeitstempel nicht älter als 30 Sekunden ist,
    // dann wurde das Update erfolgreich durchgeführt
    echo json_encode([
        'status' => ($currentCommit === $sessionCommit && $updateTimestamp > (time() - 30)) ? 'updated' : 'pending'
    ]);
    exit;
}

// Generiere das HTML für die Versions-Information
ob_start();
?>
<h3>Versions-Information</h3>
<p>
    Installierte Version: <code><?php echo htmlspecialchars($currentCommit); ?></code>
    <span class="version-status <?php echo $updateAvailable ? 'status-outdated' : 'status-current'; ?>">
        <?php echo $updateAvailable ? 'Update verfügbar' : 'Aktuell'; ?>
    </span>
</p>
<p>
    Neueste Version: <code><?php echo htmlspecialchars($latestCommit['hash']); ?></code>
    <?php if ($latestCommit['date']): ?>
        <span class="commit-date">(Erstellt am: <?php echo $latestCommit['date']->format('d.m.Y H:i'); ?> Uhr)</span>
    <?php endif; ?>
</p>
<?php if ($updateAvailable): ?>
    <form method="get" action="update.php">
        <button type="submit" class="btn btn-primary">Update durchführen</button>
    </form>
<?php endif; ?>
<?php
$html = ob_get_clean();

// Sende die Antwort
echo json_encode([
    'html' => $html,
    'current_commit' => $currentCommit,
    'latest_commit' => $latestCommit['hash'],
    'update_available' => $updateAvailable
]); 