# Kiosk & Hardware Integration

Leopardo RH supports dedicated attendance hardware to ensure reliable tracking in physical workspaces.

## 📟 Supported Hardware
- **ZKTeco Devices:** Native integration for K40 and similar models.
- **Leopardo QR Kiosk:** Turn any tablet into a QR-based attendance station.

## 🔌 Integration Logic
Hardware devices communicate with the backend via a dedicated secure token.
- **Sync Flow:** Device → API → Tenant Schema.
- **Roster Management:** The backend pushes updated employee lists to authorized devices.

---
See [Hardware Runbook](../GESTION_PROJET/RUNBOOK_ZKTECO_CLIENT.md) for setup steps.
