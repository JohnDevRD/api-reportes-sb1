@echo off
REM ============================================================
REM  run_bridge.bat - Arranca el bridge HANA en WINDOWS
REM  (compatible con el mismo hana_bridge.py usado en Linux)
REM
REM  Requisitos previos (una sola vez):
REM    uv venv .venv
REM    uv pip install --python .venv\Scripts\python.exe -r requirements.txt
REM ============================================================
setlocal

REM --- Configuracion del bridge (ajusta a tu entorno) ---
set HANAB1_HOST=IP_O_HOST_DEL_SERVIDOR_HANA
set HANAB1_PORT=30015
set HANA_USER=TU_USUARIO_BD
set HANA_PASSWORD=TU_CONTRASENA_BD
set BRIDGE_TOKEN=cambia-este-token-por-un-secreto-largo
set BRIDGE_LISTEN=127.0.0.1
set BRIDGE_PORT=8088

REM Usar el entorno creado con uv (.venv)
if exist ".venv\Scripts\python.exe" (
    ".venv\Scripts\python.exe" hana_bridge.py
) else (
    echo [ERROR] No existe .venv\Scripts\python.exe
    echo Ejecuta primero: uv venv .venv
    echo   y luego:        uv pip install --python .venv\Scripts\python.exe -r requirements.txt
    exit /b 1
)

endlocal