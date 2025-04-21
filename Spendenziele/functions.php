<?php
// Funktion zum Abrufen der GitHub Commit-Version
function getLatestGitHubCommit($debug = false) {
    if ($debug) {
        debugLog("=== Start: GitHub Latest Commit Abfrage ===");
    }
    
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
    
    if ($debug) {
        debugLog("Request Optionen:", $opts);
    }
    
    $context = stream_context_create($opts);
    
    $apiUrl = 'https://api.github.com/repos/Bittersweet1987/spendenziele/commits';
    if ($debug) {
        debugLog("API URL:", $apiUrl);
    }
    
    $response = @file_get_contents($apiUrl, false, $context);
    
    if ($response === false) {
        if ($debug) {
            $error = error_get_last();
            debugLog("GitHub API Fehler:", $error);
            
            // Prüfe Rate Limit
            $headers = get_headers($apiUrl, 1);
            if (isset($headers['X-RateLimit-Remaining']) && $headers['X-RateLimit-Remaining'] == 0) {
                $resetTime = isset($headers['X-RateLimit-Reset']) ? date('Y-m-d H:i:s', $headers['X-RateLimit-Reset']) : 'unbekannt';
                debugLog("Rate Limit erreicht. Reset um: " . $resetTime);
            }
        }
        
        return ['hash' => 'Fehler', 'date' => null];
    }
    
    if ($debug) {
        debugLog("GitHub API Antwort:", $response);
    }
    
    $commits = json_decode($response, true);
    if (!is_array($commits) || empty($commits)) {
        if ($debug) {
            debugLog("Ungültige API-Antwort oder keine Commits gefunden:", $commits);
        }
        return ['hash' => 'Fehler', 'date' => null];
    }
    
    $latestCommit = $commits[0];
    if ($debug) {
        debugLog("Details des neuesten Commits:", [
            'sha' => $latestCommit['sha'],
            'commit' => [
                'message' => $latestCommit['commit']['message'],
                'committer' => $latestCommit['commit']['committer']
            ]
        ]);
    }
    
    $date = null;
    if (isset($latestCommit['commit']['committer']['date'])) {
        try {
            $date = new DateTime($latestCommit['commit']['committer']['date']);
            $date->setTimezone(new DateTimeZone('Europe/Berlin'));
            if ($debug) {
                debugLog("Commit Datum geparst:", $date->format('Y-m-d H:i:s'));
            }
        } catch (Exception $e) {
            if ($debug) {
                debugLog("Datum Parsing Fehler:", $e->getMessage());
            }
        }
    }
    
    $result = [
        'hash' => substr($latestCommit['sha'], 0, 7),
        'date' => $date
    ];
    
    if ($debug) {
        debugLog("=== Ende: GitHub Latest Commit Abfrage ===", $result);
    }
    
    return $result;
} 