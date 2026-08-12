# Bridge HANA (cliente Python puro)

Puente HTTP que permite a la API PHP consultar **SAP HANA sin instalar el
driver ODBC de SAP**. Corre en la **maquina local Linux**, junto a la API,
y habla con HANA por el protocolo nativo via Tailscale.

```
Cliente HTTP -> PHP API -> (HTTP 127.0.0.1:8088) -> Bridge Python -> HANA :30015
```

## Requisitos en la maquina Linux

- Python 3.8+
- Habilitar `pip` y opcionalmente un `venv`
- Extension `php-curl` en el PHP de la API (`sudo apt install php-curl`)

## Instalacion

```bash
# 1. Copiar el bridge
sudo mkdir -p /opt/hana-bridge
sudo cp hana_bridge.py requirements.txt /opt/hana-bridge/

# 2. Dependencias Python (cliente oficial de SAP, no es ODBC)
cd /opt/hana-bridge
python3 -m venv venv
venv/bin/pip install -r requirements.txt
# (alternativa pura, si no puedes usar hdbcli:  venv/bin/pip install pyhdb)

# 3. Configuracion
sudo cp hana_bridge.env.example /etc/hana-bridge.env
sudo nano /etc/hana-bridge.env     # revisa host, puerto, user, password, token
sudo chmod 600 /etc/hana-bridge.env

# 4. Servicio systemd
sudo cp hana-bridge.service /etc/systemd/system/
sudo nano /etc/systemd/system/hana-bridge.service  # ajusta User/WorkingDirectory
sudo systemctl daemon-reload
sudo systemctl enable --now hana-bridge.service

# 5. Ver estado y logs
systemctl status hana-bridge.service
journalctl -u hana-bridge.service -f
```

> Ajusta `ExecStart` en el servicio si usas el `venv`:
> `ExecStart=/opt/hana-bridge/venv/bin/python3 /opt/hana-bridge/hana_bridge.py`

## Instalacion con `uv` (recomendado)

Recomendamos **`uv`** en vez de `venv`/`pip` por su rapidez y reproducibilidad
(`uv.lock`). Pasos:

```bash
# 0. Instalar uv (script oficial, sin permisos de root)
curl -LsSf https://astral.sh/uv/install.sh | sh

# 1. Copiar el bridge
sudo mkdir -p /opt/hana-bridge
sudo cp hana_bridge.py pyproject.toml requirements.txt /opt/hana-bridge/

# 2. Crear el entorno y las dependencias con uv (leera pyproject.toml)
cd /opt/hana-bridge
uv sync        # crea .venv, instala deps y genera uv.lock

# 3. Configuracion
sudo cp hana_bridge.env.example /etc/hana-bridge.env
sudo nano /etc/hana-bridge.env     # revisa host, puerto, user, password, token
sudo chmod 600 /etc/hana-bridge.env

# 4. Servicio systemd (usa el python del entorno uv)
sudo cp hana-bridge.service /etc/systemd/system/
sudo nano /etc/systemd/system/hana-bridge.service  # ajusta User/WorkingDirectory
sudo systemctl daemon-reload
sudo systemctl enable --now hana-bridge.service

# 5. Ver estado y logs
systemctl status hana-bridge.service
journalctl -u hana-bridge.service -f
```

## Instalacion en WINDOWS

El `hana_bridge.py` es **multiplataforma** (usa libreria estandar de Python).
Los pasos en Windows son los mismos salvo el arranque (no hay `systemd`).
Requerido: Python 3, `uv` y la extension `php-curl` en la API.

```bat
REM 1. Copia la carpeta bridge a, por ejemplo, C:\bridge
REM 2. Instala uv (o via pip/winget/scoop) y crea el entorno
cd C:\bridge
uv sync        REM leera pyproject.toml, crea .venv y genera uv.lock

REM 3. Configura (opcional): crea un .env o edita run_bridge.bat
REM    (host, usuario, password, token) y guarda el mismo BRIDGE_TOKEN en .env de la API.

REM 4. Arranque manual (ventana abierta)
windows\run_bridge.bat
```

### Arranque automatico (opciones)

**A) Tarea Programada** (al iniciar sesion, u ONSTART con la cuenta adecuada):

```bat
schtasks /Create /TN "HanaBridge" ^
  /TR "C:\bridge\windows\run_bridge.bat" ^
  /SC ONLOGON /RL HIGHEST
```

**B) NSSM (servicio real de Windows)** — herramienta externa, sin ventanas:

```bat
nssm install HanaBridge "C:\bridge\.venv\Scripts\pythonw.exe" "C:\bridge\hana_bridge.py"
nssm set HanaBridge AppEnvironmentExtra HANAB1_HOST=<IP_O_HOST> HANAB1_PORT=30015 HANA_USER=<USUARIO_BD> HANA_PASSWORD=<CONTRASENA> BRIDGE_TOKEN=<TOKEN>
nssm set HanaBridge AppExit Default Restart
nssm start HanaBridge
```

La API PHP no cambia: `BRIDGE_URL=http://127.0.0.1:8088/query` funciona igual en
Windows y Linux.

## Ejecucion directa (prueba rapida)

Para correr el bridge sin systemd (por ejemplo, de forma manual para depurar):

```bash
cd /opt/hana-bridge
uv run python hana_bridge.py
```

## Prueba rapida del bridge

```bash
curl -s -X POST http://127.0.0.1:8088/query \
  -H "Content-Type: application/json" \
  -H "X-Bridge-Token: <tu-token>" \
  -d '{"sql":"SELECT CURRENT_TIMESTAMP AS now FROM DUMMY","params":[]}'
```

Debe devolver algo como:

```json
{"ok": true, "columns": ["NOW"], "rows": [["2026-08-12 16:00:00"]]}
```

## Seguridad

- Escucha **solo en 127.0.0.1** → no accesible desde la red.
- Requiere **token (`X-Bridge-Token`)** en cada peticion, que debe coincidir
  con `BRIDGE_TOKEN` del `.env` de la API.
- No expone errores SQL crudos en produccion: la API los sanea segun `APP_DEBUG`.
