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

/**
 * Capa de acceso a SAP HANA sin ODBC.
 *
 * Envia la consulta al bridge Python (bridge/hana_bridge.py) que corre en
 * 127.0.0.1 y habla el protocolo nativo de HANA. Devuelve un array de filas
 * asociativas (columna => valor).
 *
 * @throws RuntimeException si el bridge no responde o la consulta falla.
 */
function hana_query(string $sql, array $params = []): array
{
    $url   = env_value('BRIDGE_URL', 'http://127.0.0.1:8088/query');
    $token = env_value('BRIDGE_TOKEN', '');

    $payload = json_encode([
        'sql'    => $sql,
        'params' => array_values($params),
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Bridge-Token: ' . $token,
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Error de comunicacion con el bridge HANA: ' . $err);
    }

    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if (!is_array($data)) {
        throw new RuntimeException('Respuesta invalida del bridge HANA (HTTP ' . $httpCode . ').');
    }

    if (empty($data['ok'])) {
        throw new RuntimeException($data['error'] ?? 'Error desconocido del bridge HANA.');
    }

    $columns = array_values($data['columns'] ?? []);
    $rows    = $data['rows'] ?? [];

    $result = [];

    foreach ($rows as $row) {
        $assoc = [];

        foreach ($columns as $i => $col) {
            $assoc[$col] = $row[$i] ?? null;
        }

        $result[] = $assoc;
    }

    return $result;
}

