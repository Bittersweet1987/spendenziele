<?php
require_once 'config.php';

// Funktion zum Formatieren der Spalteninformationen
function formatColumnInfo($column) {
    $info = $column['COLUMN_NAME'] . ' (' . $column['COLUMN_TYPE'] . ')';
    if ($column['IS_NULLABLE'] === 'NO') {
        $info .= ' NOT NULL';
    }
    if ($column['COLUMN_DEFAULT'] !== null) {
        $info .= ' DEFAULT ' . $column['COLUMN_DEFAULT'];
    }
    if ($column['COLUMN_KEY'] === 'PRI') {
        $info .= ' PRIMARY KEY';
    }
    if ($column['COLUMN_KEY'] === 'UNI') {
        $info .= ' UNIQUE';
    }
    if ($column['EXTRA'] === 'auto_increment') {
        $info .= ' AUTO_INCREMENT';
    }
    return $info;
}

// Hole alle Tabellen und Spalten
$query = "SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_KEY,
    EXTRA
FROM 
    INFORMATION_SCHEMA.COLUMNS
WHERE 
    TABLE_SCHEMA = DATABASE()
ORDER BY 
    TABLE_NAME,
    ORDINAL_POSITION";

$result = $pdo->query($query);
$tables = [];

// Gruppiere Spalten nach Tabellen
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $tableName = $row['TABLE_NAME'];
    if (!isset($tables[$tableName])) {
        $tables[$tableName] = [];
    }
    $tables[$tableName][] = $row;
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datenbankstruktur</title>
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
        h1 {
            color: #333;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        .table {
            margin: 20px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .table-name {
            background-color: #f5f5f5;
            padding: 10px;
            font-weight: bold;
            border-bottom: 1px solid #ddd;
        }
        .column {
            padding: 8px 15px;
            border-bottom: 1px solid #eee;
        }
        .column:last-child {
            border-bottom: none;
        }
        .column-info {
            font-family: monospace;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Datenbankstruktur</h1>
        
        <?php foreach ($tables as $tableName => $columns): ?>
            <div class="table">
                <div class="table-name">Tabelle: <?php echo htmlspecialchars($tableName); ?></div>
                <div class="columns">
                    <?php foreach ($columns as $column): ?>
                        <div class="column">
                            <div class="column-info">
                                <?php echo htmlspecialchars(formatColumnInfo($column)); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html> 