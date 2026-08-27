# API: Reporte de Acuerdos Comerciales

## Endpoint
```
GET /api/v1/consultas/acuerdos_comerciales/reporte.php
```

Este endpoint devuelve el reporte de acuerdos comerciales a partir de la vista [`VW_REPORTE_ACUERDOS_COMERCIALES`](../../../../vistas/acuerdos_comerciales/VW_REPORTE_ACUERDOS_COMERCIALES.sql) de SAP HANA, que consolida notas de crédito (`ORPC`), entradas de mercancía (`OIGN`), pagos recibidos (`ORCT`) y facturas de clientes (`OINV`).

---

## Parámetros de consulta (query string)

### Paginación y control

| Parámetro | Tipo | Obligatorio | Descripción | Valor por defecto |
|-----------|------|-------------|-------------|-------------------|
| `page` | `int` (≥ 1) | No | Número de página. | `1` |
| `limit` | `int` (1–500) | No | Registros por página. | `100` |

### Filtros de fecha

| Parámetro | Columna | Tipo | Descripción |
|-----------|---------|------|-------------|
| `desde` | `DocDate` | `date` (YYYY-MM-DD) | Fecha mínima del documento. |
| `hasta` | `DocDate` | `date` (YYYY-MM-DD) | Fecha máxima del documento. |

### Filtros de texto (coincidencia exacta)

| Parámetro | Columna | Descripción |
|-----------|---------|-------------|
| `doc_num` | `DocNum` | Número de documento exacto. |
| `card_code` | `CardCode` | Código de cliente/proveedor exacto. |
| `acct_code` | `AcctCode` | Cuenta contable exacta. |
| `pey_method` | `PeyMethod` | Método de pago exacto. |
| `origen` | `Origen` | Origen exacto (`Nota de Crédito (ORPC)`, `Entrada de Mercancía (OIGN)`, `Pago Recibido (ORCT)`, `Factura (OINV)`). |

### Filtros de texto (búsqueda parcial — `LIKE %...%`)

| Parámetro | Columna | Descripción |
|-----------|---------|-------------|
| `card_name` | `CardName` | Nombre del cliente/proveedor (contiene). |
| `dscription` | `Dscription` | Descripción del detalle (contiene). |

### Filtros numéricos

| Parámetro | Columna | Tipo | Descripción |
|-----------|---------|------|-------------|
| `sum_min` | `DocTotal` | `decimal` | Importe mínimo total del documento. |
| `sum_max` | `DocTotal` | `decimal` | Importe máximo total del documento. |

> **Validaciones**
> - Las fechas deben tener formato `YYYY-MM-DD` y ser fechas válidas en el calendario.
> - `desde` no puede ser mayor que `hasta`.
> - `sum_min` no puede ser mayor que `sum_max`.
> - Todos los filtros son opcionales y combinables entre sí.
> - Los parámetros de texto con LIKE son insensibles a mayúsculas según el _collation_ de la vista.

---

## Respuesta JSON

### Éxito (`200 OK`)
```json
{
  "ok": true,
  "total": 124,
  "page": 1,
  "limit": 50,
  "pages_total": 3,
  "records_count": 2,
  "filters": {
    "desde": "2026-06-01",
    "hasta": "2026-06-14",
    "doc_num": null,
    "card_code": null,
    "card_name": "Ejemplo",
    "acct_code": "99000003",
    "dscription": null,
    "pey_method": null,
    "origen": null,
    "sum_min": null,
    "sum_max": null
  },
  "data": [
    {
      "DocDate": "2026-06-14",
      "DocNum": "300001",
      "CardCode": "C00001",
      "CardName": "Proveedor Ejemplo",
      "DocTotal": "1250.000000",
      "AcctCode": "99000003",
      "Dscription": "Servicio de mantenimiento",
      "PeyMethod": "13",
      "Origen": "Nota de Crédito (ORPC)",
      "PaidSum": "0.000000",
      "Saldo Pendiente": "1250.000000"
    },
    {
      "DocDate": "2026-06-10",
      "DocNum": "400001",
      "CardCode": null,
      "CardName": null,
      "DocTotal": "890.500000",
      "AcctCode": "99000003",
      "Dscription": "Entrada de insumos",
      "PeyMethod": null,
      "Origen": "Entrada de Mercancía (OIGN)"
    }
  ]
}
```

### Error (`4xx` o `5xx`)
```json
{
  "ok": false,
  "message": "Descripción del error",
  "error": "Detalle del error de la consulta (opcional)"
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
| `DocDate` | `date` | Fecha del documento (`YYYY-MM-DD`). |
| `DocNum` | `string` | Número de documento. |
| `CardCode` | `string` | Código del cliente/proveedor. Puede ser `null` (entrada de mercancía / pago recibido). |
| `CardName` | `string` | Nombre del cliente/proveedor. Puede ser `null`. |
| `DocTotal` | `string` | Importe total del documento (con 6 decimales). |
| `AcctCode` | `string` | Cuenta contable (p. ej. `99000001`, `99000002`, `99000003`, `99000004`). |
| `Dscription` | `string` | Descripción del detalle. |
| `PeyMethod` | `string` | Método de pago  (puede ser `null`). |
| `Origen` | `string` | Origen del registro: `Nota de Crédito (ORPC)`, `Entrada de Mercancía (OIGN)`, `Pago Recibido (ORCT)` o `Factura (OINV)`. |
| `PaidSum` | `string` | Importe pagado del documento (con 6 decimales). Puede ser `null`. |
| `Saldo Pendiente` | `string` | Saldo pendiente del documento (`DocTotal - PaidSum`, con 6 decimales). Puede ser `null`. |

---

## Ejemplos de llamada

```bash
# Rango de fechas básico
curl "http://localhost/api-reportes-sb1/api/v1/consultas/acuerdos_comerciales/reporte.php?desde=2026-06-01&hasta=2026-06-14&page=1&limit=50"

# Por cuenta contable y rango de monto
curl ".../reporte.php?acct_code=99000003&sum_min=100&sum_max=5000"

# Búsqueda parcial por nombre de cliente
curl ".../reporte.php?card_name=Almacenes"

# Por origen específico
curl ".../reporte.php?origen=Nota+de+Credito+(ORPC)"

# Combinado: cuenta + descripción parcial + página 2
curl ".../reporte.php?acct_code=99000003&dscription=insumos&page=2&limit=50"
```

---

## Notas de implementación

- La conexión a SAP HANA se hace a través del **bridge Python** (`bridge/`) usando la función `hana_query()` definida en `config/conexion.php`. No se utiliza un driver ODBC.
- El `WHERE` se construye dinámicamente: solo se agregan las condiciones de los filtros que el cliente envía. Sin filtros, se devuelven todos los registros.
- Todas las consultas se envían al bridge con parámetros posicionales (`?`) y se ejecutan como consultas parametrizadas para evitar inyección SQL.
- El resultado se ordena por `DocDate DESC` y luego por `DocNum`.
- El campo `DocDate` se sanitiza en PHP para eliminar bytes nulos que HANA puede inyectar en buffers de tipo fecha. Solo se conserva la parte `YYYY-MM-DD`.
- `CardCode`, `CardName`, `PeyMethod` pueden ser `null` en las ramas de `OIGN`/`ORCT` de la vista.
- `PaidSum` y `Saldo Pendiente` son `null` en las ramas de `ORPC`, `OIGN` y `ORCT`; solo la rama `OINV` (facturas) los calcula (`PaidSum` y `DocTotal - PaidSum`).
- Todos los valores de texto se normalizan a UTF-8 con `mb_convert_encoding` para evitar problemas de codificación.

---

*Documentado el 2026-08-26 — v2*
