<?php

function env_value(string $key, string $default = ''): string
{
    $value = getenv($key);

    if ($value === false) {
        $envFile = dirname(__DIR__) . '/.env';

        if (is_file($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($lines as $line) {
                $line = trim($line);

                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                [$envKey, $envValue] = array_pad(explode('=', $line, 2), 2, '');
                $envKey = trim($envKey);
                $envValue = trim($envValue);

                if ($envKey === $key) {
                    $value = $envValue;
                    break;
                }
            }
        }
    }

    return $value === false ? $default : $value;
}

function build_hana_connection_string(): string
{
    $driver = env_value('DB_DRIVER', '{B1CRHPROXY}');
    $serverNode = env_value('DB_SERVERNODE', 'hanab1:30013');
    $database = env_value('DB_DATABASE', 'DB_ACV');
    $databaseName = env_value('DB_DATABASE_NAME', 'NDB');

    return "DRIVER={$driver};SERVERNODE={$serverNode};DATABASE={$database};databaseName={$databaseName}";
}

function get_hana_connection()
{
    static $conn = null;

    if ($conn !== null) {
        return $conn;
    }

    $connectionString = build_hana_connection_string();
    $user = env_value('DB_USER');
    $password = env_value('DB_PASSWORD');

    $conn = odbc_connect($connectionString, $user, $password);

    return $conn;
}
