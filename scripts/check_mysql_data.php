<?php
require_once __DIR__ . '/../db_config.php';

$host = getenv('MYSQL_HOST') ?: 'localhost';
$database = getenv('MYSQL_DBNAME') ?: '';
$username = getenv('MYSQL_USER') ?: '';
$password = getenv('MYSQL_PASSWORD') ?: '';
$fromDate = getenv('FROM_DATE') ?: '2026-09-01';

if ($database === '' || $username === '' || $password === '') {
    fwrite(STDERR, "Configurez MYSQL_DBNAME, MYSQL_USER et MYSQL_PASSWORD dans .env pour vérifier la source historique.\n");
    exit(1);
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, "Erreur connexion MySQL: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

$tables = [
    'agents',
    'managers',
    'pointages',
    'demandes_conges',
    'primes_retenues',
    'agent_performance_stats',
    'device_commands',
    'popup_config'
];

foreach ($tables as $table) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        echo "TABLE:$table COUNT:$count\n";
        if ($table === 'pointages') {
            $stmt = $pdo->prepare("SELECT name, date, iso_date, status FROM `$table` WHERE iso_date >= :fromDate ORDER BY iso_date DESC LIMIT 5");
            $stmt->execute([':fromDate' => $fromDate]);
            $rows = $stmt->fetchAll();
            foreach ($rows as $r) {
                echo json_encode($r, JSON_UNESCAPED_UNICODE), "\n";
            }
        }
    } catch (Throwable $e) {
        echo "TABLE:$table ERROR: " . $e->getMessage() . "\n";
    }
}
