# API: Reporte de Caja Chica

## Endpoint
```
GET /api/v1/consultas/caja_chica/reporte.php
```

Este endpoint devuelve el reporte de caja chica a partir de la vista [`VW_REPORTE_CAJA_CHICA`](../../../../vistas/caja_chica/vista_reporte_caja_chica.sql) de SAP HANA.

---

## Parámetros de consulta (query string)

### Paginación y control

| Parámetro | Tipo | Obligatorio | Descripción | Valor por defecto |
|-----------|------|-------------|-------------|-------------------|
| `page` | `int` (≥ 1) | No | Número de página. | `1` |
| `limit` | `int` (1–500) | No | Registros por página. | `100` |
| `include_total` | `bool` | No | Si `true`, incluye el total de registros del filtro. | `true` |

### Filtros de fecha

| Parámetro | Columna | Tipo | Descripción |
|-----------|---------|------|-------------|
| `desde` | `DocDate` | `date` (YYYY-MM-DD) | Fecha mínima del pago. |
| `hasta` | `DocDate` | `date` (YYYY-MM-DD) | Fecha máxima del pago. |
| `fecha_origen_desde` | `FechaOrigen` | `date` (YYYY-MM-DD) | Fecha mínima del documento origen. |
| `fecha_origen_hasta` | `FechaOrigen` | `date` (YYYY-MM-DD) | Fecha máxima del documento origen. |

### Filtros de texto (coincidencia exacta)

| Parámetro | Columna | Descripción |
|-----------|---------|-------------|
| `pago` | `Pago` | Número de pago exacto. |
| `card_code` | `CardCode` | Código de cliente/proveedor exacto. |
| `cash_acct` | `CashAcct` | Cuenta de efectivo exacta. |
| `tipo_detalle` | `TipoDetalle` | Tipo de detalle exacto (`FACTURA` o `CUENTA`). |
| `documento_origen` | `DocumentoOrigen` | Número de documento origen exacto. |

### Filtros de texto (búsqueda parcial — `LIKE %...%`)

| Parámetro | Columna | Descripción |
|-----------|---------|-------------|
| `card_name` | `CardName` | Nombre del cliente/proveedor (contiene). |
| `descripcion` | `Descripcion` | Descripción del movimiento (contiene). |

### Filtros numéricos

| Parámetro | Columna | Tipo | Descripción |
|-----------|---------|------|-------------|
| `linea` | `Linea` | `int` | Número de línea exacto. |
| `sum_min` | `SumApplied` | `decimal` | Importe mínimo aplicado. |
| `sum_max` | `SumApplied` | `decimal` | Importe máximo aplicado. |

> **Validaciones**
> - Las fechas deben tener formato `YYYY-MM-DD` y ser fechas válidas en el calendario.
> - `desde` no puede ser mayor que `hasta`; ídem `fecha_origen_desde` y `fecha_origen_hasta`.
> - `sum_min` no puede ser mayor que `sum_max`.
> - Todos los filtros son opcionales y combinables entre sí.
> - Los parámetros de texto con LIKE son insensibles a mayúsculas según el _collation_ de la vista.

---

## Respuesta JSON

### Éxito (`200 OK`)
```json
{
  "ok": true,
  "total": 1234,
  "page": 1,
  "limit": 50,
  "page_total": 50,
  "filters": {
    "desde": "2026-06-01",
    "hasta": "2026-06-14",
    "fecha_origen_desde": null,
    "fecha_origen_hasta": null,
    "pago": null,
    "card_code": null,
    "card_name": "Ejemplo",
    "cash_acct": null,
    "tipo_detalle": "FACTURA",
    "linea": null,
    "descripcion": null,
    "documento_origen": null,
    "sum_min": null,
    "sum_max": null
  },
  "data": [
    {
      "Pago": "10001",
      "DocDate": "2026-06-11",
      "CardCode": "C00001",
      "CardName": "EMPRESA DE EJEMPLO SRL",
      "CashAcct": "11010201",
      "TipoDetalle": "FACTURA",
      "Linea": "0",
      "Descripcion": "Descripción de ejemplo",
      "SumApplied": "161.420000",
      "DocumentoOrigen": "20000001",
      "FechaOrigen": "2026-06-03"
    }
  ]
}
```

### Error (`4xx` o `5xx`)
```json
{
  "ok": false,
  "message": "Descripción del error",
  "error": "Detalle del error del driver ODBC (opcional)"
}
```

| Código | Causa |
|--------|-------|
| `400 Bad Request` | Parámetros inválidos, fechas con formato incorrecto, o rango invertido. |
| `500 Internal Server Error` | Falló la conexión o la ejecución de la consulta contra SAP HANA. |

---

## Campos devueltos (`data[]`)

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `Pago` | `string` | Número de pago (`DocNum`). |
| `DocDate` | `date` | Fecha del pago (`YYYY-MM-DD`). |
| `CardCode` | `string` | Código del cliente o proveedor. |
| `CardName` | `string` | Nombre del cliente o proveedor. |
| `CashAcct` | `string` | Cuenta de efectivo utilizada. |
| `TipoDetalle` | `string` | `FACTURA` o `CUENTA` según el origen del registro. |
| `Linea` | `string` | Número de línea dentro del documento. |
| `Descripcion` | `string` | Descripción del movimiento. |
| `SumApplied` | `string` | Importe aplicado (con 6 decimales). |
| `DocumentoOrigen` | `string` | Número del documento origen. |
| `FechaOrigen` | `date` | Fecha del documento origen (`YYYY-MM-DD`). Puede ser `null`. |

---

## Ejemplos de llamada

```bash
# Rango de fechas básico
curl "http://localhost/api-reportes-sb1/api/v1/consultas/caja_chica/reporte.php?desde=2026-06-01&hasta=2026-06-14&page=1&limit=50"

# Por cliente y tipo de detalle
curl ".../reporte.php?card_code=C00001&tipo_detalle=FACTURA"

# Búsqueda parcial por nombre de cliente y rango de monto
curl ".../reporte.php?card_name=Almacenes&sum_min=100&sum_max=5000"

# Por descripción parcial sin límite de total (más rápido)
curl ".../reporte.php?descripcion=combustible&include_total=false&limit=200"

# Combinado: cuenta de caja + rango de fecha origen + página 2
curl ".../reporte.php?cash_acct=11010201&fecha_origen_desde=2026-05-01&fecha_origen_hasta=2026-05-31&page=2&limit=50"
```

---

## Notas de implementación

- La conexión a SAP HANA se gestiona en `config/conexion.php` mediante `get_hana_connection()`.
- El `WHERE` se construye dinámicamente: solo se agregan las condiciones de los filtros que el cliente envía. Sin filtros, se devuelven todos los registros.
- Todas las consultas usan `odbc_prepare()` + `odbc_execute()` con parámetros posicionales (`?`) para evitar inyección SQL.
- Los campos `DocDate` y `FechaOrigen` se sanitizan en PHP para eliminar bytes nulos que ODBC puede inyectar en buffers de tipo fecha. Solo se conserva la parte `YYYY-MM-DD`.
- Todos los valores de texto se normalizan a UTF-8 con `mb_convert_encoding` para evitar problemas de codificación.
- La paginación y el conteo total son independientes; desactivar `include_total` elimina la query de `COUNT(*)` y reduce la carga en consultas grandes.

---

*Documentado el 2026-06-14 — v2*