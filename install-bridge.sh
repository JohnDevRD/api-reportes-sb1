#!/usr/bin/env bash
set -euo pipefail

# ── Configuración ──────────────────────────────────────────────
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BRIDGE_SRC="$REPO_ROOT/bridge" # carpeta local del repo (ruta absoluta)
BRIDGE_DEST="/opt/hana-bridge"
SERVICE_NAME="hana-bridge.service"
ENV_FILE="/etc/hana-bridge.env"
RUN_USER="${SUDO_USER:-$USER}" # usuario real (el que ejecuta con sudo)

# ── 0. No ejecutar como root (el servicio debe correr con usuario normal) ────
if [[ $EUID -eq 0 ]]; then
  echo "❌ No ejecutes este script como root."
  echo "   Salí de root (exit o su - <usuario>) y volvelo a correr con tu usuario normal."
  echo "   El script usa sudo solito cuando necesita permisos."
  exit 1
fi

# ── 1. Verificar que estamos en la raíz del repo ───────────────
if [[ ! -d "$BRIDGE_SRC" ]]; then
  echo "❌ No se encuentra la carpeta $BRIDGE_SRC. Ejecuta el script desde la raíz del repo."
  exit 1
fi

# ── 2. Instalar uv si no existe ────────────────────────────────
if ! command -v uv &>/dev/null; then
  echo "📦 Instalando uv..."
  curl -LsSf https://astral.sh/uv/install.sh | sh
  # Asegurar que uv esté en el PATH para esta sesión
  export PATH="$HOME/.local/bin:$PATH"
else
  echo "✅ uv ya está instalado."
fi

# ── 3. Copiar archivos del bridge ─────────────────────────────
echo "📂 Copiando bridge a $BRIDGE_DEST..."
sudo mkdir -p "$BRIDGE_DEST"
sudo cp "$BRIDGE_SRC/hana_bridge.py" \
        "$BRIDGE_SRC/pyproject.toml" \
        "$BRIDGE_SRC/requirements.txt" \
        "$BRIDGE_DEST/"

# ── 4. Sincronizar dependencias con uv ────────────────────────
echo "🔄 Creando entorno e instalando dependencias (uv sync)..."
cd "$BRIDGE_DEST"
uv sync

# Asegurar propiedad del usuario real (los cp anteriores fueron con sudo/root)
sudo chown -R "$RUN_USER":"$RUN_USER" "$BRIDGE_DEST"

# ── 5. Configurar archivo de entorno ──────────────────────────
if [[ ! -f "$ENV_FILE" ]]; then
  echo "📝 Creando $ENV_FILE desde el ejemplo..."
  sudo cp "$BRIDGE_SRC/hana_bridge.env.example" "$ENV_FILE"
  sudo chmod 600 "$ENV_FILE"
  echo "⚠️  Edita $ENV_FILE con tus credenciales de SAP HANA (host, puerto, user, password, token)."
else
  echo "✅ $ENV_FILE ya existe. No se sobrescribe."
fi

# ── 6. Copiar y ajustar servicio systemd ──────────────────────
if [[ ! -f "/etc/systemd/system/$SERVICE_NAME" ]]; then
  echo "🛠️  Creando servicio systemd..."
  sudo cp "$BRIDGE_SRC/hana-bridge.service" "/etc/systemd/system/$SERVICE_NAME"

  # Ajustar ExecStart para usar el Python del entorno uv
  sudo sed -i "s|ExecStart=.*|ExecStart=$BRIDGE_DEST/.venv/bin/python $BRIDGE_DEST/hana_bridge.py|" \
       "/etc/systemd/system/$SERVICE_NAME"

  # Ajustar WorkingDirectory si es necesario
  sudo sed -i "s|WorkingDirectory=.*|WorkingDirectory=$BRIDGE_DEST|" \
       "/etc/systemd/system/$SERVICE_NAME"

  # Ajustar User/Group al usuario real (evita el hardcoded del template)
  sudo sed -i "s|^User=.*|User=$RUN_USER|" "/etc/systemd/system/$SERVICE_NAME"
  sudo sed -i "s|^Group=.*|Group=$RUN_USER|" "/etc/systemd/system/$SERVICE_NAME"

  sudo systemctl daemon-reload
else
  echo "✅ El servicio $SERVICE_NAME ya está instalado."
fi

# ── 7. Habilitar y arrancar el servicio ───────────────────────
echo "🚀 Habilitando y arrancando $SERVICE_NAME..."
sudo systemctl enable --now "$SERVICE_NAME"

# ── 8. Mostrar estado ─────────────────────────────────────────
echo "📊 Estado del servicio:"
sudo systemctl status "$SERVICE_NAME" --no-pager

echo ""
echo "✅ Bridge instalado. Revisa los logs con:"
echo "   journalctl -u $SERVICE_NAME -f"