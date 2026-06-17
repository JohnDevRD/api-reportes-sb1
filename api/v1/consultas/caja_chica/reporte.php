<?php

require_once __DIR__ . '/../../../../config/conexion.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

const DATA_COLUMNS_SQL = <<<'SQL'
SELECT
    "Pago", "DocDate", "CardCode", "CardName", "CashAcct",
    "TipoDetalle", "Linea", "Descripcion", "GTotal",
    "DocumentoOrigen", "FechaOrigen",
    "OcrCode", "CommentsFactura"
FROM "VW_REPORTE_CAJA_CHICA"
SQL;

// ─── Helpers de respuesta ─────────────────────────────────────────────────────

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

// ─── Helpers de parámetros ────────────────────────────────────────────────────

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

// ─── Sanitización de filas ────────────────────────────────────────────────────

function sanitize_row(array $row): array
{
    // Columnas que deben quedar como fecha pura YYYY-MM-DD
    $dateColumns = ['DocDate', 'FechaOrigen'];

    foreach ($row as $key => $value) {
        if (!is_string($value)) {
            continue;
        }

        // 1. Eliminar bytes nulos y caracteres de control
        //    (conserva espacio, tab, LF, CR)
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

// ─── Constructor de WHERE dinámico ────────────────────────────────────────────

function build_where(array $conditions, array $params): array
{
    if (empty($conditions)) {
        return ['', []];
    }

    return [' WHERE ' . implode(' AND ', $conditions), $params];
}

// ─── Leer todos los filtros ───────────────────────────────────────────────────

$conditions = [];
$params     = [];

// — DocDate (desde / hasta) —
$desde = date_param('desde', null);
$hasta = date_param('hasta', null);

if ($desde !== null && $hasta !== null && $desde > $hasta) {
    json_response(400, [
        'ok'      => false,
        'message' => 'El parámetro desde no puede ser mayor que hasta.'
    ]);
}

if ($desde !== null && $hasta !== null) {
    $conditions[] = '"DocDate" BETWEEN ? AND ?';
    $params[]     = $desde;
    $params[]     = $hasta;
} elseif ($desde !== null) {
    $conditions[] = '"DocDate" >= ?';
    $params[]     = $desde;
} elseif ($hasta !== null) {
    $conditions[] = '"DocDate" <= ?';
    $params[]     = $hasta;
}

// — FechaOrigen (fecha_origen_desde / fecha_origen_hasta) —
$fOrigenDesde = date_param('fecha_origen_desde', null);
$fOrigenHasta = date_param('fecha_origen_hasta', null);

if ($fOrigenDesde !== null && $fOrigenHasta !== null && $fOrigenDesde > $fOrigenHasta) {
    json_response(400, [
        'ok'      => false,
        'message' => 'fecha_origen_desde no puede ser mayor que fecha_origen_hasta.'
    ]);
}

if ($fOrigenDesde !== null && $fOrigenHasta !== null) {
    $conditions[] = '"FechaOrigen" BETWEEN ? AND ?';
    $params[]     = $fOrigenDesde;
    $params[]     = $fOrigenHasta;
} elseif ($fOrigenDesde !== null) {
    $conditions[] = '"FechaOrigen" >= ?';
    $params[]     = $fOrigenDesde;
} elseif ($fOrigenHasta !== null) {
    $conditions[] = '"FechaOrigen" <= ?';
    $params[]     = $fOrigenHasta;
}

// — GTotal (sum_min / sum_max) —
$sumMin = decimal_param('sum_min');
$sumMax = decimal_param('sum_max');

if ($sumMin !== null && $sumMax !== null && $sumMin > $sumMax) {
    json_response(400, [
        'ok'      => false,
        'message' => 'sum_min no puede ser mayor que sum_max.'
    ]);
}

if ($sumMin !== null && $sumMax !== null) {
    $conditions[] = '"GTotal" BETWEEN ? AND ?';
    $params[]     = $sumMin;
    $params[]     = $sumMax;
} elseif ($sumMin !== null) {
    $conditions[] = '"GTotal" >= ?';
    $params[]     = $sumMin;
} elseif ($sumMax !== null) {
    $conditions[] = '"GTotal" <= ?';
    $params[]     = $sumMax;
}

// — Linea (entero exacto) —
$linea = filter_input(INPUT_GET, 'linea', FILTER_VALIDATE_INT);
if ($linea !== false && $linea !== null) {
    $conditions[] = '"Linea" = ?';
    $params[]     = $linea;
}

// — Filtros de texto —
$textFilters = [
    'pago'               => 'Pago',
    'card_code'          => 'CardCode',
    'card_name'          => 'CardName',           // LIKE
    'cash_acct'          => 'CashAcct',
    'tipo_detalle'       => 'TipoDetalle',
    'descripcion'        => 'Descripcion',         // LIKE
    'documento_origen'   => 'DocumentoOrigen',
    'ocr_code'           => 'OcrCode',
    'comments_factura'   => 'CommentsFactura',     // LIKE
];

$likeFields = ['CardName', 'Descripcion', 'CommentsFactura'];

foreach ($textFilters as $param => $column) {
    $val = string_param($param);

    if ($val === null) {
        continue;
    }

    if (in_array($column, $likeFields, true)) {
        $conditions[] = "\"$column\" LIKE ?";
        $params[]     = '%' . $val . '%';
    } else {
        $conditions[] = "\"$column\" = ?";
        $params[]     = $val;
    }
}

// ─── Paginación ───────────────────────────────────────────────────────────────

$page   = int_param('page', 1, 1, 100000);
$limit  = int_param('limit', 100, 1, 500);
$offset = ($page - 1) * $limit;

[$whereSql, $whereParams] = build_where($conditions, $params);

// ─── Conexión ─────────────────────────────────────────────────────────────────

$conn = get_hana_connection();

if ($conn === false) {
    json_response(500, [
        'ok'      => false,
        'message' => 'No se pudo conectar a SAP HANA.',
        'error'   => sanitize_error(odbc_errormsg())
    ]);
}

// ─── Consulta de total (siempre se ejecuta) ───────────────────────────────────

$countSql  = 'SELECT COUNT(*) AS "TOTAL" FROM "VW_REPORTE_CAJA_CHICA"' . $whereSql;
$countStmt = odbc_prepare($conn, $countSql);

if ($countStmt === false) {
    json_response(500, [
        'ok'      => false,
        'message' => 'No se pudo preparar la consulta de total.',
        'error'   => sanitize_error(odbc_errormsg($conn))
    ]);
}

if (!odbc_execute($countStmt, $whereParams)) {
    json_response(500, [
        'ok'      => false,
        'message' => 'No se pudo ejecutar la consulta de total.',
        'error'   => sanitize_error(odbc_errormsg($countStmt))
    ]);
}

$total = null;

if (odbc_fetch_row($countStmt)) {
    $total = (int) odbc_result($countStmt, 1);
}

odbc_free_result($countStmt);

// ─── Consulta de datos ────────────────────────────────────────────────────────

$dataSql = DATA_COLUMNS_SQL
    . $whereSql
    . ' ORDER BY "DocDate" DESC, "Pago", "TipoDetalle", "Linea"'
    . ' LIMIT ' . $limit . ' OFFSET ' . $offset;

$dataStmt = odbc_prepare($conn, $dataSql);

if ($dataStmt === false) {
    json_response(500, [
        'ok'      => false,
        'message' => 'No se pudo preparar la consulta del reporte.',
        'error'   => sanitize_error(odbc_errormsg($conn))
    ]);
}

if (!odbc_execute($dataStmt, $whereParams)) {
    json_response(500, [
        'ok'      => false,
        'message' => 'No se pudo ejecutar la consulta del reporte.',
        'error'   => sanitize_error(odbc_errormsg($dataStmt))
    ]);
}

$data = [];

while (($row = odbc_fetch_array($dataStmt)) !== false) {
    $data[] = sanitize_row($row); // ← limpia fechas y encoding
}

odbc_free_result($dataStmt);
odbc_close($conn);

// ─── Respuesta ────────────────────────────────────────────────────────────────

// Total de páginas global: ceil(total_registros / limit)
$pagesTotal = ($limit > 0) ? (int) ceil($total / $limit) : 1;

json_response(200, [
    'ok'               => true,
    'total'            => $total,           // Total de registros que coinciden con el filtro
    'pages_total'      => $pagesTotal,      // Total de páginas (ceil(total / limit))
    'page'             => $page,            // Página actual
    'limit'            => $limit,           // Registros por página
    'records_count'    => count($data),     // Registros devueltos en esta página
    'filters'    => [
        'desde'              => $desde,
        'hasta'              => $hasta,
        'fecha_origen_desde' => $fOrigenDesde,
        'fecha_origen_hasta' => $fOrigenHasta,
        'pago'               => string_param('pago'),
        'card_code'          => string_param('card_code'),
        'card_name'          => string_param('card_name'),
        'cash_acct'          => string_param('cash_acct'),
        'tipo_detalle'       => string_param('tipo_detalle'),
        'linea'              => $linea ?: null,
        'descripcion'        => string_param('descripcion'),
        'documento_origen'   => string_param('documento_origen'),
        'sum_min'            => $sumMin,
        'sum_max'            => $sumMax,
        'ocr_code'           => string_param('ocr_code'),
        'comments_factura'   => string_param('comments_factura'),
    ],
    'data'       => $data,
]);