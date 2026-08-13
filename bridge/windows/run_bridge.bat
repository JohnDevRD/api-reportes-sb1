@echo off
REM ============================================================
REM  run_bridge.bat - Arranca el bridge HANA en WINDOWS
REM
REM  La configuracion (host, credenciales, token) NO va aqui:
REM  se lee del archivo bridge\.bridge.env (ignorado por git).
REM  Copia bridge\.bridge.env.example a bridge\.bridge.env y rellena.
REM ============================================================
setlocal

REM Carpeta del bridge (padre de esta carpeta windows\)
for %%I in ("%~dp0..") do set "BRIDGE_DIR=%%~fI"

if not exist "%BRIDGE_DIR%\.bridge.env" (
    echo [ERROR] No existe %BRIDGE_DIR%\.bridge.env
    echo   Copia bridge\.bridge.env.example a bridge\.bridge.env y rellena tus datos.
    exit /b 1
)

if not exist "%BRIDGE_DIR%\.venv\Scripts\python.exe" (
    echo [ERROR] No existe el entorno .venv en %BRIDGE_DIR%\.venv
    echo   Ejecuta primero: cd bridge ^&^& uv sync
    exit /b 1
)

"%BRIDGE_DIR%\.venv\Scripts\python.exe" "%BRIDGE_DIR%\hana_bridge.py"
 endlocal