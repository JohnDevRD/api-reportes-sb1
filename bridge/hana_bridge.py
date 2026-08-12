#!/usr/bin/env python3
"""
hana_bridge.py - Puente HTTP entre la API PHP y SAP HANA (protocolo nativo).

Corre en la MAQUINA LOCAL LINUX (junto a la API PHP) y escucha SOLO en
127.0.0.1:8088. No requiere el driver ODBC de SAP: usa hdbcli (cliente
oficial de SAP para Python) o, como respaldo, pyhdb (driver puro).

Variables de entorno:
  HANAB1_HOST     IP/host del servidor HANA  (default: 127.0.0.1)
  HANAB1_PORT     Puerto SQL de HANA        (default: 30015)
  HANA_USER       Usuario de BD
  HANA_PASSWORD   Contraseña de BD
  BRIDGE_TOKEN    Token compartido con la API (si vacio, se desactiva)
  BRIDGE_LISTEN   Interface a escuchar      (default: 127.0.0.1)
  BRIDGE_PORT     Puerto del bridge         (default: 8088)

Endpoint:
  POST /query
    Body:   {"sql": "...", "params": [...]}
    Header: X-Bridge-Token: <token>
    Respuesta: {"ok": true, "columns": [...], "rows": [...]}
    o bien:    {"ok": false, "error": "..."}
"""
import json
import os
import sys
from datetime import date, datetime, time
from decimal import Decimal
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

HANA_HOST = os.environ.get("HANAB1_HOST", "127.0.0.1")
HANA_PORT = int(os.environ.get("HANAB1_PORT", "30015"))
HANA_USER = os.environ.get("HANA_USER", "")
HANA_PASSWORD = os.environ.get("HANA_PASSWORD", "")
BRIDGE_TOKEN = os.environ.get("BRIDGE_TOKEN", "")
LISTEN_HOST = os.environ.get("BRIDGE_LISTEN", "127.0.0.1")
LISTEN_PORT = int(os.environ.get("BRIDGE_PORT", "8088"))

_conn = None


def get_connection():
    global _conn
    if _conn is not None:
        return _conn

    try:
        from hdbcli import dbapi
        driver = "hdbcli"
    except Exception:
        try:
            import pyhdb
            driver = "pyhdb"
        except Exception:
            sys.exit(
                "No se pudo cargar hdbcli ni pyhdb. "
                "Instala: pip install hdbcli  (o bien  pip install pyhdb)"
            )

    if driver == "hdbcli":
        _conn = dbapi.connect(
            HANA_USER, HANA_PASSWORD, host=HANA_HOST, port=HANA_PORT
        )
    else:
        _conn = pyhdb.connect(HANA_HOST, HANA_PORT, HANA_USER, HANA_PASSWORD)

    return _conn


def _to_jsonable(value):
    """Convierte tipos SQL/cursor a tipos JSON."""
    if value is None or isinstance(value, (str, int, float, bool)):
        return value
    if isinstance(value, (date, datetime, time, Decimal)):
        return str(value)
    if isinstance(value, (bytes, bytearray)):
        return value.decode("utf-8", "replace")
    return str(value)


def _column_names(cursor):
    names = []
    if cursor.description:
        for d in cursor.description:
            if isinstance(d, (tuple, list)):
                names.append(d[0])
            else:
                names.append(getattr(d, "name", str(d)))
    return names


def run_query(sql, params):
    conn = get_connection()
    cursor = conn.cursor()
    cursor.execute(sql, tuple(params))
    columns = _column_names(cursor)
    rows = [list(map(_to_jsonable, r)) for r in cursor.fetchall()]
    cursor.close()
    return {"columns": columns, "rows": rows}


class Handler(BaseHTTPRequestHandler):
    def log_message(self, fmt, *args):
        sys.stderr.write("%s - - %s\n" % (self.address_string(), fmt % args))

    def _send(self, code, obj):
        body = json.dumps(obj, ensure_ascii=False).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_POST(self):
        if self.path != "/query":
            return self._send(
                404, {"ok": False, "error": "Ruta no encontrada (use POST /query)"}
            )

        provided = self.headers.get("X-Bridge-Token", "")
        if BRIDGE_TOKEN and provided != BRIDGE_TOKEN:
            return self._send(401, {"ok": False, "error": "Token invalido"})

        try:
            length = int(self.headers.get("Content-Length", "0"))
            raw = self.rfile.read(length).decode("utf-8")
            payload = json.loads(raw)
            sql = payload.get("sql")
            params = payload.get("params") or []

            if not sql:
                return self._send(400, {"ok": False, "error": "Falta el campo 'sql'"})

            result = run_query(sql, params)
            result["ok"] = True
            return self._send(200, result)
        except Exception as exc:  # noqa: BLE001
            return self._send(500, {"ok": False, "error": str(exc)})


def main():
    if not HANA_USER:
        print("[aviso] Variable 'HANA_USER' no definida en el entorno")
    if not HANA_PASSWORD:
        print("[aviso] Variable 'HANA_PASSWORD' no definida en el entorno")

    server = ThreadingHTTPServer((LISTEN_HOST, LISTEN_PORT), Handler)
    print(
        "Bridge HANA escuchando en http://%s:%d/query (HANA %s:%s)"
        % (LISTEN_HOST, LISTEN_PORT, HANA_HOST, HANA_PORT)
    )
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\nDetenido.")


if __name__ == "__main__":
    main()