# api-reportes-sb1

API REST PHP para consultas y reportes desde **SAP HANA vía ODBC**. Arquitectura limpia, sin frameworks, con prepared statements y sanitización de datos.

## Stack

| Capa | Tecnología |
|------|-----------|
| Lenguaje | PHP ≥ 8.0 |
| Base de datos | SAP HANA (conector ODBC) |
| Dependencias | 0 — PHP vanilla |
| Autoload | PSR-4 via Composer (opcional) |

## Requisitos

- PHP 8.0+
- SAP HANA ODBC driver (`{B1CRHPROXY}` o compatible)
- Extensiones: `ext-odbc`, `ext-mbstring`, `ext-json`

## Instalación

```bash
git clone https://github.com/tu-org/api-reportes-sb1.git
cd api-reportes-sb1
cp .env.example .env
# Editar .env con tus credenciales SAP HANA
composer dump-autoload  # opcional
```

## Endpoints

| Método | Ruta | Descripción |
|--------|------|-------------|
| `GET` | `/api/v1/consultas/caja_chica/reporte.php` | Reporte de caja chica con filtros y paginación |

### Próximos módulos

- `clientes/` — Consulta de clientes/proveedores
- `ventas/` — Reportes de ventas
- `inventario/` — Consultas de inventario
- `reportes/` — Reportes generales

## Documentación

Cada endpoint tiene su documentación en `documentacion/v1/consultas/`.

Ver [`documentacion/v1/consultas/caja_chica/reporte.md`](documentacion/v1/consultas/caja_chica/reporte.md).

## Seguridad

- **Prepared statements** en todas las consultas — sin riesgo de inyección SQL
- **Sanitización de encoding** a UTF-8 con `mb_convert_encoding`
- **Limpieza de bytes nulos** en fechas devueltas por ODBC
- **Error reporting controlado** — no se exponen errores ODBC crudos en producción

## Licencia

MIT — ver [LICENSE](LICENSE).
