<?php
require_once __DIR__ . '/db_config.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? 'status';

switch ($action) {
    case 'status':
    case 'test':
        echo json_encode([
            'success' => true,
            'mode' => 'supabase',
            'message' => 'Le projet est configuré pour utiliser Supabase côté navigateur.',
            'supabase_url' => $POINTAGE_SUPABASE_URL,
            'supabase_key_configured' => !empty($POINTAGE_SUPABASE_ANON_KEY),
        ]);
        break;

    default:
        echo json_encode([
            'success' => true,
            'mode' => 'supabase',
            'message' => 'Cette application utilise directement le client Supabase dans le navigateur. Les opérations de données sont gérées côté frontend via Supabase.',
            'config' => [
                'url' => $POINTAGE_SUPABASE_URL,
                'key_configured' => !empty($POINTAGE_SUPABASE_ANON_KEY),
            ],
        ]);
        break;
}