<?php

require __DIR__ . '/../../_shared.php';

const DATA_COLUMNS_SQL = <<<'SQL'
SELECT
    "Pago", "DocDate", "CardCode", "CardName", "CashAcct",
    "TipoDetalle", "Linea", "GTotal",
    "DocumentoOrigen", "FechaOrigen", "TotalOrigen",
    "OcrCode", "Categoria", "CommentsFactura", "NumAtCardFactura",
    "InvType"
FROM "VW_REPORTE_CAJA_CHICA"
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
    'documento_origen'   => 'DocumentoOrigen',
    'ocr_code'           => 'OcrCode',
    'comments_factura'   => 'CommentsFactura',     // LIKE
    'categoria'          => 'Categoria',            // LIKE
    'num_at_card'        => 'NumAtCardFactura',
];

$likeFields = ['CardName', 'CommentsFactura', 'Categoria'];

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

$countSql = 'SELECT COUNT(*) AS "TOTAL" FROM "VW_REPORTE_CAJA_CHICA"' . $whereSql;

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
    . ' ORDER BY "DocDate" DESC, "Pago", "TipoDetalle", "Linea"'
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
    $data[] = sanitize_row($row, ['DocDate', 'FechaOrigen']); // ← limpia fechas y encoding
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
        'documento_origen'   => string_param('documento_origen'),
        'sum_min'            => $sumMin,
        'sum_max'            => $sumMax,
        'ocr_code'           => string_param('ocr_code'),
        'comments_factura'   => string_param('comments_factura'),
        'categoria'          => string_param('categoria'),
        'num_at_card'        => string_param('num_at_card'),
    ],
    'data'       => $data,
]);
