<?php
require_once 'config.php';

// Funktion zum Formatieren der Spalteninformationen
function formatColumnInfo($column) {
    $info = $column['Field'];
    $info .= " (" . $column['Type'];
    if ($column['Null'] === 'NO') {
        $info .= ", NOT NULL";
    }
    if ($column['Default'] !== null) {
        $info .= ", DEFAULT " . $column['Default'];
    }
    if ($column['Key'] === 'PRI') {
        $info .= ", PRIMARY KEY";
    } elseif ($column['Key'] === 'UNI') {
        $info .= ", UNIQUE";
    }
    if ($column['Extra'] !== '') {
        $info .= ", " . $column['Extra'];
    }
    $info .= ")";
    return $info;
}

try {
    // Verbindung zur Datenbank herstellen
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Hole alle Tabellen
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<!DOCTYPE html>
    <html lang='de'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Datenbank-Tabellen Übersicht</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
                background-color: #f4f4f4;
            }
            .container {
                max-width: 1200px;
                margin: 0 auto;
                background-color: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
            }
            .table-container {
                margin: 20px 0;
                padding: 15px;
                background-color: #f8f9fa;
                border-radius: 4px;
                border: 1px solid #dee2e6;
            }
            .table-name {
                font-size: 18px;
                font-weight: bold;
                color: #333;
                margin-bottom: 10px;
            }
            .column-list {
                list-style-type: none;
                padding: 0;
                margin: 0;
            }
            .column-item {
                padding: 8px;
                margin: 4px 0;
                background-color: white;
                border: 1px solid #dee2e6;
                border-radius: 4px;
                font-family: monospace;
            }
            .primary-key {
                background-color: #e8f5e9;
                border-color: #c8e6c9;
            }
            .unique {
                background-color: #fff3e0;
                border-color: #ffe0b2;
            }
            .not-null {
                font-weight: bold;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>Datenbank-Tabellen Übersicht</h1>";
    
    foreach ($tables as $table) {
        echo "<div class='table-container'>
                <div class='table-name'>Tabelle: $table</div>
                <ul class='column-list'>";
        
        // Hole Spalteninformationen
        $columns = $db->query("SHOW FULL COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($columns as $column) {
            $classes = [];
            if ($column['Key'] === 'PRI') {
                $classes[] = 'primary-key';
            } elseif ($column['Key'] === 'UNI') {
                $classes[] = 'unique';
            }
            if ($column['Null'] === 'NO') {
                $classes[] = 'not-null';
            }
            
            $classString = !empty($classes) ? " class='" . implode(' ', $classes) . "'" : "";
            echo "<li$classString class='column-item'>" . formatColumnInfo($column) . "</li>";
        }
        
        echo "</ul></div>";
    }
    
    echo "</div></body></html>";
    
} catch (PDOException $e) {
    echo "Fehler: " . $e->getMessage();
}
?> 