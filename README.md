# api-reportes-sb1

API REST PHP para consultas y reportes desde **SAP HANA**. Arquitectura limpia,
sin frameworks, con consultas parametrizadas y sanitización de datos.

La conexión a la base NO usa un driver ODBC: se hace a través de un **bridge
Python** (`bridge/`) que habla el protocolo nativo de HANA y que puede correr
en **Windows o Linux**, en la misma máquina que la API.

## Stack

| Capa | Tecnología |
|------|-----------|
| Lenguaje | PHP ≥ 8.0 |
| Base de datos | SAP HANA (vía bridge Python con `hdbcli`/`pyhdb`) |
| Bridge | Python 3 + `uv` (servicio HTTP local en `127.0.0.1:8088`) |
| Dependencias PHP | 0 frameworks — PHP vanilla |

## Requisitos

- PHP 8.0+
- Extensiones: `ext-mbstring`, `ext-json`, `ext-curl`
- Python 3.8+ y `uv` (para el bridge), en Windows o Linux
- Acceso de red al puerto SQL de HANA (p. ej. `3<instancia>15`)

## Instalación

```bash
git clone https://github.com/tu-org/api-reportes-sb1.git
cd api-reportes-sb1
cp .env.example .env
# Editar .env con tus credenciales SAP HANA y la config del bridge
composer install          # o: composer dump-autoload
```

### Bridge HANA (paso aparte)

Instala y arranca el bridge antes de usar la API. Ver
[`bridge/README.md`](bridge/README.md) para Linux y Windows:

```bash
cd bridge
uv sync
# Según el sistema, arranca el servicio (systemd, NSSM, Tarea Programada o
# "uv run python hana_bridge.py" de forma manual).
```

La API espera el bridge en `BRIDGE_URL` (por defecto `http://127.0.0.1:8088/query`)
y le envía las consultas vía HTTP con un token (`BRIDGE_TOKEN`), por lo que las
credenciales de la base solo se guardan en el entorno/local del bridge, nunca en
la API.

## Endpoints

| Método | Ruta | Descripción |
|--------|------|-------------|
| `GET` | `/api/v1/consultas/caja_chica/reporte.php` | Reporte de caja chica con filtros y paginación |
| `GET` | `/api/v1/consultas/acuerdos_comerciales/reporte.php` | Reporte de acuerdos comerciales con filtros y paginación |

### Próximos módulos

- `clientes/` — Consulta de clientes/proveedores
- `ventas/` — Reportes de ventas
- `inventario/` — Consultas de inventario
- `reportes/` — Reportes generales

## Documentación

Cada endpoint tiene su documentación en `documentacion/v1/consultas/`.

Ver [`documentacion/v1/consultas/caja_chica/reporte.md`](documentacion/v1/consultas/caja_chica/reporte.md).
Ver [`documentacion/v1/consultas/acuerdos_comerciales/reporte.md`](documentacion/v1/consultas/acuerdos_comerciales/reporte.md).

## Estructura de carpetas

```
api/v1/_shared.php                 ← bootstrap común de las APIs de consulta
api/v1/consultas/<modulo>/<api>.php ← endpoints (solo-GET)
vistas/<modulo>/<VISTA>.sql         ← vistas SQL de SAP HANA
documentacion/v1/consultas/<modulo>/<api>.md
```

`api/v1/_shared.php` concentra todo lo común de una API de consulta: CORS,
respuesta JSON estándar (`json_response`), saneo de errores según `APP_DEBUG`,
validación de parámetros (`date_param`, `int_param`, `string_param`,
`decimal_param`), sanitización de filas (`sanitize_row`) y paginación
(`pagination_params`).

## Cómo agregar una API de consulta

1. **Vista SQL**: crea `vistas/<modulo>/VW_<NOMBRE>.sql` con la consulta del
   reporte en HANA.
2. **Endpoint**: crea `api/v1/consultas/<modulo>/<api>.php` que comience con
   `require __DIR__ . '/../../_shared.php';` y use los helpers de `_shared.php`
   (leer filtros → `build_where` → `hana_query` → `json_response`).
3. **Documentación**: crea `documentacion/v1/consultas/<modulo>/<api>.md` con
   parámetros, ejemplo de `curl` y ejemplo de respuesta.
4. **README**: agrega la fila en la tabla de Endpoints.

Con eso cada API nueva es solo 1 vista + 1 archivo + su doc, sin repetir
infraestructura.

## Seguridad

- **Consultas parametrizadas** en todas las consultas — sin riesgo de inyección SQL
- **Sanitización de encoding** a UTF-8 con `mb_convert_encoding`
- **Limpieza de bytes nulos** en fechas devueltas por HANA
- **Error reporting controlado** — no se exponen errores SQL crudos en producción
- El bridge escucha **solo en `127.0.0.1`** con token; las credenciales de la
  base no viajan a la API ni se versionan (`.env` está en `.gitignore`)

## Licencia

MIT — ver [LICENSE](LICENSE).
