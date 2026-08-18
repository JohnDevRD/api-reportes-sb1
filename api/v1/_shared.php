<?php

/**
 * Bootstrap compartido para las APIs de consulta (solo-GET) de este proyecto.
 *
 * Incluye la conexion al bridge HANA (hana_query) y helpers comunes:
 *  - Respuesta JSON estandar + saneo de errores segun APP_DEBUG
 *  - Validacion de parametros GET (fecha, entero, texto, decimal)
 *  - Sanitizacion de filas (bytes nulos, fechas, encoding UTF-8)
 *  - Construccion de WHERE dinamico y paginacion
 *
 * Uso en cada endpoint:
 *   require __DIR__ . '/_shared.php';
 *   // ...leer filtros, hana_query(), json_response()
 */

require_once __DIR__ . '/../../config/conexion.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');

// CORS (solo consulta/lectura)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─── Respuesta JSON ───────────────────────────────────────────────────────────

function json_response(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    $options = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

    if (filter_var(env_value('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN)) {
        $options |= JSON_PRETTY_PRINT;
    }

    echo json_encode($payload, $options);
    exit;
}

function sanitize_error(string $message): string
{
    $debug = filter_var(env_value('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);
    return $debug ? $message : 'Internal server error';
}

// ─── Validacion de parametros GET ─────────────────────────────────────────────

function date_param(string $key, ?string $default = null): ?string
{
    $value = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);

    if ($value === null || trim((string) $value) === '') {
        return $default;
    }

    $value = trim((string) $value);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        json_response(400, [
            'ok'      => false,
            'message' => "El parámetro {$key} debe tener formato YYYY-MM-DD."
        ]);
    }

    [$year, $month, $day] = array_map('intval', explode('-', $value));

    if (!checkdate($month, $day, $year)) {
        json_response(400, [
            'ok'      => false,
            'message' => "El parámetro {$key} contiene una fecha inválida."
        ]);
    }

    return $value;
}

function int_param(string $key, int $default, int $min, int $max): int
{
    $value = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT);

    if ($value === false || $value === null) {
        $value = $default;
    }

    if ($value < $min || $value > $max) {
        json_response(400, [
            'ok'      => false,
            'message' => "El parámetro {$key} debe estar entre {$min} y {$max}."
        ]);
    }

    return $value;
}

function string_param(string $key): ?string
{
    $value = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);

    if ($value === null || trim((string) $value) === '') {
        return null;
    }

    return trim((string) $value);
}

function decimal_param(string $key): ?float
{
    $value = filter_input(INPUT_GET, $key, FILTER_VALIDATE_FLOAT);

    return ($value === false || $value === null) ? null : $value;
}

// ─── Sanitizacion de filas ────────────────────────────────────────────────────

/**
 * @param array $dateColumns Columnas que deben quedar como fecha YYYY-MM-DD.
 */
function sanitize_row(array $row, array $dateColumns = []): array
{
    foreach ($row as $key => $value) {
        if (!is_string($value)) {
            continue;
        }

        // 1. Eliminar bytes nulos y caracteres de control (conserva espacio, tab, LF, CR)
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);

        // 2. Si es columna de fecha, extraer solo YYYY-MM-DD
        if (in_array($key, $dateColumns, true)) {
            if (preg_match('/(\d{4}-\d{2}-\d{2})/', $value, $m)) {
                $value = $m[1];
            }
        }

        // 3. Normalizar encoding a UTF-8
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');

        $row[$key] = $value;
    }

    return $row;
}

// ─── Construccion de WHERE dinamico ───────────────────────────────────────────

function build_where(array $conditions, array $params): array
{
    if (empty($conditions)) {
        return ['', []];
    }

    return [' WHERE ' . implode(' AND ', $conditions), $params];
}

// ─── Paginacion ───────────────────────────────────────────────────────────────

function pagination_params(int $defaultLimit = 100, int $maxLimit = 500): array
{
    $page   = int_param('page', 1, 1, 100000);
    $limit  = int_param('limit', $defaultLimit, 1, $maxLimit);
    $offset = ($page - 1) * $limit;

    return [$page, $limit, $offset];
}