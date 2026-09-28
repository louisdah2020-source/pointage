<?php
$fromDate = getenv('FROM_DATE') ?: '2026-09-01';
$sourceDsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', getenv('MYSQL_HOST') ?: 'localhost', getenv('MYSQL_DBNAME') ?: '');
$sourceUser = getenv('MYSQL_USER') ?: '';
$sourcePass = getenv('MYSQL_PASSWORD') ?: '';

$supabaseUrl = getenv('SUPABASE_URL') ?: '';
$supabaseKey = getenv('SUPABASE_ANON_KEY') ?: '';

if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        putenv(trim($key) . '=' . trim($value));
    }
    $fromDate = getenv('FROM_DATE') ?: $fromDate;
    $sourceDsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', getenv('MYSQL_HOST') ?: 'localhost', getenv('MYSQL_DBNAME') ?: '');
    $sourceUser = getenv('MYSQL_USER') ?: $sourceUser;
    $sourcePass = getenv('MYSQL_PASSWORD') ?: $sourcePass;
    $supabaseUrl = getenv('SUPABASE_URL') ?: $supabaseUrl;
    $supabaseKey = getenv('SUPABASE_ANON_KEY') ?: $supabaseKey;
}

if (getenv('MYSQL_DBNAME') === false || getenv('MYSQL_DBNAME') === '' || $sourceUser === '' || $sourcePass === '' || $supabaseUrl === '' || $supabaseKey === '') {
    fwrite(STDERR, "Configurez MYSQL_DBNAME, MYSQL_USER, MYSQL_PASSWORD, SUPABASE_URL et SUPABASE_ANON_KEY dans .env ou dans l'environnement.\n");
    exit(1);
}

try {
    $pdo = new PDO($sourceDsn, $sourceUser, $sourcePass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, "Erreur connexion MySQL: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

function supabaseRequest(string $method, string $url, array $payload = []): array {
    global $supabaseKey;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $supabaseKey,
            'Authorization: Bearer ' . $supabaseKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_FAILONERROR => false,
    ]);

    if ($payload) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'http_code' => $httpCode,
        'body' => $response ?: '',
        'error' => $error,
    ];
}

function isUuid($value): bool {
    return is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
}

$tables = [
    'agents' => [
        'select' => "SELECT * FROM agents WHERE created_at >= :fromDate OR date_entree >= :fromDate OR created_at IS NULL ORDER BY created_at",
        'map' => function (array $row) {
            $safe = [];
            $safe['id'] = $row['id'] ?? null;
            $safe['created_at'] = $row['created_at'] ?? null;
            $safe['name'] = $row['name'] ?? null;
            $safe['matricule'] = $row['matricule'] ?? null;
            $safe['salaire_base'] = (float)($row['salaire_base'] ?? 0);
            $safe['is_active'] = isset($row['is_active']) ? (bool)$row['is_active'] : true;
            $safe['date_entree'] = $row['date_entree'] ?? null;
            $safe['date_sortie'] = $row['date_sortie'] ?? null;
            $safe['service'] = $row['service'] ?? null;
            $safe['type_contrat'] = $row['type_contrat'] ?? null;
            $safe['statut_paiement'] = $row['statut_paiement'] ?? 'NON_PAYE';
            return $safe;
        },
        'upsert' => ['name']
    ],
    'managers' => [
        'select' => "SELECT * FROM managers WHERE created_at >= :fromDate OR created_at IS NULL ORDER BY created_at",
        'map' => function (array $row) {
            return [
                'id' => $row['id'] ?? null,
                'created_at' => $row['created_at'] ?? null,
                'name' => $row['name'] ?? null,
                'matricule' => $row['matricule'] ?? null,
                'salaire_base' => (float)($row['salaire_base'] ?? 0),
            ];
        },
        'upsert' => ['name']
    ],
    'pointages' => [
        'select' => "SELECT * FROM pointages WHERE iso_date >= :fromDate ORDER BY iso_date DESC, id DESC",
        'map' => function (array $row) {
            return [
                'id' => $row['id'] ?? null,
                'created_at' => $row['created_at'] ?? null,
                'name' => $row['name'] ?? null,
                'date' => $row['date'] ?? null,
                'iso_date' => $row['iso_date'] ?? null,
                'arrivee' => $row['arrivee'] ?? null,
                'pause' => $row['pause'] ?? null,
                'retour' => $row['retour'] ?? null,
                'depart' => $row['depart'] ?? null,
                'status' => $row['status'] ?? null,
                'total' => (float)($row['total'] ?? 0),
                'motif' => $row['motif'] ?? null,
                'device_id' => $row['device_id'] ?? null,
                'ip_address' => $row['ip_address'] ?? null,
            ];
        },
        'upsert' => ['id']
    ],
    'demandes_conges' => [
        'select' => "SELECT * FROM demandes_conges WHERE created_at >= :fromDate OR date_debut >= :fromDate ORDER BY created_at DESC",
        'map' => function (array $row) {
            return [
                'id' => $row['id'] ?? null,
                'created_at' => $row['created_at'] ?? null,
                'agent_name' => $row['agent_name'] ?? null,
                'type' => $row['type'] ?? null,
                'date_debut' => $row['date_debut'] ?? null,
                'date_fin' => $row['date_fin'] ?? null,
                'motif' => $row['motif'] ?? null,
                'statut' => $row['statut'] ?? 'EN ATTENTE',
                'acknowledged_at' => $row['acknowledged_at'] ?? null,
            ];
        },
        'upsert' => ['id']
    ],
    'primes_retenues' => [
        'select' => "SELECT * FROM primes_retenues WHERE created_at >= :fromDate OR annee >= YEAR(:fromDate) ORDER BY created_at DESC",
        'map' => function (array $row) {
            return [
                'id' => $row['id'] ?? null,
                'created_at' => $row['created_at'] ?? null,
                'agent_name' => $row['agent_name'] ?? null,
                'mois' => (int)($row['mois'] ?? 0),
                'annee' => (int)($row['annee'] ?? 0),
                'montant_prime' => (float)($row['montant_prime'] ?? 0),
                'montant_retenue' => (float)($row['montant_retenue'] ?? 0),
            ];
        },
        'upsert' => ['agent_name', 'mois', 'annee']
    ],
    'agent_performance_stats' => [
        'select' => "SELECT * FROM agent_performance_stats WHERE created_at >= :fromDate OR date >= :fromDate ORDER BY date DESC",
        'map' => function (array $row) {
            return [
                'id' => $row['id'] ?? null,
                'created_at' => $row['created_at'] ?? null,
                'agent_name' => $row['agent_name'] ?? null,
                'date' => $row['date'] ?? null,
                'dons' => (int)($row['dons'] ?? 0),
                'refus_arg' => (int)($row['refus_arg'] ?? 0),
                'indecis' => (int)($row['indecis'] ?? 0),
                'del' => (int)($row['del'] ?? 0),
            ];
        },
        'upsert' => ['agent_name', 'date']
    ],
    'device_commands' => [
        'select' => "SELECT * FROM device_commands WHERE created_at >= :fromDate ORDER BY created_at DESC",
        'map' => function (array $row) {
            return [
                'id' => $row['id'] ?? null,
                'created_at' => $row['created_at'] ?? null,
                'device_id' => $row['device_id'] ?? null,
                'command' => $row['command'] ?? null,
                'executed' => !empty($row['executed'])
            ];
        },
        'upsert' => ['id']
    ],
    'popup_config' => [
        'select' => "SELECT * FROM popup_config WHERE updated_at >= :fromDate OR id = 1 ORDER BY id",
        'map' => function (array $row) {
            return [
                'id' => (int)($row['id'] ?? 1),
                'title' => $row['title'] ?? 'Informations Importantes',
                'content' => $row['content'] ?? null,
                'image_url' => $row['image_url'] ?? null,
                'is_active' => !empty($row['is_active']),
                'updated_at' => $row['updated_at'] ?? null,
            ];
        },
        'upsert' => ['id']
    ],
];

foreach ($tables as $table => $config) {
    $stmt = $pdo->prepare($config['select']);
    $stmt->execute([':fromDate' => $fromDate]);
    $rows = $stmt->fetchAll();

    if (!$rows) {
        echo "No rows for $table\n";
        continue;
    }

    $mapped = [];
    foreach ($rows as $row) {
        $mappedRow = $config['map']($row);
        if (isset($mappedRow['id']) && !isUuid($mappedRow['id'])) {
            unset($mappedRow['id']);
        }
        $mapped[] = $mappedRow;
    }

    $response = supabaseRequest('POST', rtrim($supabaseUrl, '/') . '/rest/v1/' . $table, $mapped);
    $status = $response['http_code'];
    $body = $response['body'];
    echo "TABLE:$table STATUS:$status ROWS:" . count($mapped) . "\n";
    echo substr($body, 0, 300) . "\n";

    if ($response['error'] !== '' || $status < 200 || $status >= 300) {
        fwrite(STDERR, "Échec de l'import de la table $table. Vérifiez les contraintes et les politiques Supabase.\n");
        exit(1);
    }
}

echo "Migration complete\n";
