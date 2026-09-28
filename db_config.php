<?php
function pointage_env(string $name, string $default = ''): string
{
    $value = getenv($name);

    if ($value === false || $value === '') {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? $default;
    }

    return is_string($value) ? $value : $default;
}

if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $trimmed, 2), 2, '');
        $key = trim($key);
        $value = trim($value);

        if ($key !== '') {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

$POINTAGE_SUPABASE_URL = rtrim(pointage_env('SUPABASE_URL', 'https://lffhtllvajyiyunbqlci.supabase.co'), '/');
$POINTAGE_SUPABASE_ANON_KEY = pointage_env('SUPABASE_ANON_KEY', 'sb_publishable_QunC50RUP8s7CxdBg9eDWg_DKZLFWmJ');

return [
    'url' => $POINTAGE_SUPABASE_URL,
    'anon_key' => $POINTAGE_SUPABASE_ANON_KEY,
];