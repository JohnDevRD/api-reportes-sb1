<?php

require __DIR__ . '/../../_shared.php';

const DATA_COLUMNS_SQL = <<<'SQL'
SELECT
    "DocDate", "DocNum", "CardCode", "CardName", "DocTotal",
    "AcctCode", "Dscription", "PeyMethod", "Origen",
    "PaidSum", "Saldo Pendiente"
FROM "VW_REPORTE_ACUERDOS_COMERCIALES"
SQL;

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

// — DocTotal (sum_min / sum_max) —
$sumMin = decimal_param('sum_min');
$sumMax = decimal_param('sum_max');

if ($sumMin !== null && $sumMax !== null && $sumMin > $sumMax) {
    json_response(400, [
        'ok'      => false,
        'message' => 'sum_min no puede ser mayor que sum_max.'
    ]);
}

if ($sumMin !== null && $sumMax !== null) {
    $conditions[] = '"DocTotal" BETWEEN ? AND ?';
    $params[]     = $sumMin;
    $params[]     = $sumMax;
} elseif ($sumMin !== null) {
    $conditions[] = '"DocTotal" >= ?';
    $params[]     = $sumMin;
} elseif ($sumMax !== null) {
    $conditions[] = '"DocTotal" <= ?';
    $params[]     = $sumMax;
}

// ─── Filtros de texto ────────────────────────────────────────────────────────

$textFilters = [
    'doc_num'     => 'DocNum',        // DocNum normalmente numérico; se filtra como texto
    'card_code'   => 'CardCode',
    'card_name'   => 'CardName',      // LIKE
    'acct_code'   => 'AcctCode',
    'dscription'  => 'Dscription',    // LIKE
    'pey_method'  => 'PeyMethod',
    'origen'      => 'Origen',
];

$likeFields = ['CardName', 'Dscription'];

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

[$page, $limit, $offset] = pagination_params();

[$whereSql, $whereParams] = build_where($conditions, $params);

// ─── Conexión ─────────────────────────────────────────────────────────────────
// La conexion se hace a traves del bridge HANA (hana_query) en cada consulta;
// no se mantiene un recurso ODBC persistente.

// ─── Consulta de total (siempre se ejecuta) ───────────────────────────────────

$countSql = 'SELECT COUNT(*) AS "TOTAL" FROM "VW_REPORTE_ACUERDOS_COMERCIALES"' . $whereSql;

try {
    $countRows = hana_query($countSql, $whereParams);
} catch (Throwable $e) {
    json_response(500, [
        'ok'      => false,
        'message' => 'No se pudo consultar el total.',
        'error'   => sanitize_error($e->getMessage())
    ]);
}

$total = (int) ($countRows[0]['TOTAL'] ?? 0);

// ─── Consulta de datos ────────────────────────────────────────────────────────

$dataSql = DATA_COLUMNS_SQL
    . $whereSql
    . ' ORDER BY "DocDate" DESC, "DocNum"'
    . ' LIMIT ' . $limit . ' OFFSET ' . $offset;

// Se inicializa para satisfacer al analizador estático: si hana_query()
// lanzara una excepción, json_response() termina el script (exit) y $rows
// siempre queda definido como array antes del foreach.
$rows = [];

try {
    $rows = hana_query($dataSql, $whereParams);
} catch (Throwable $e) {
    json_response(500, [
        'ok'      => false,
        'message' => 'No se pudo consultar el reporte.',
        'error'   => sanitize_error($e->getMessage())
    ]);
}

$data = [];

foreach ($rows as $row) {
    $data[] = sanitize_row($row, ['DocDate']); // ← limpia fechas y encoding
}

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
        'desde'      => $desde,
        'hasta'      => $hasta,
        'doc_num'    => string_param('doc_num'),
        'card_code'  => string_param('card_code'),
        'card_name'  => string_param('card_name'),
        'acct_code'  => string_param('acct_code'),
        'dscription' => string_param('dscription'),
        'pey_method' => string_param('pey_method'),
        'origen'     => string_param('origen'),
        'sum_min'    => $sumMin,
        'sum_max'    => $sumMax,
    ],
    'data'       => $data,
]);
