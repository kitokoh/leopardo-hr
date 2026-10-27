# Bridge d'impression cuisine — ESC/POS (REST-OS-4, #8606)

Daemon local qui imprime les tickets cuisine sur une imprimante thermique
ESC/POS (58 / 80 mm) et **accuse l'impression réelle** auprès de l'API.

Le navigateur ne garantit pas l'impression silencieuse : ce composant tourne sur
le poste cuisine (mini-PC, Raspberry Pi, service systemd) et parle directement à
l'imprimante.

## Ce que fait le daemon

| Étape | Appel API | Effet |
|-------|-----------|-------|
| 1 | `GET /restaurant/printing/bridge/jobs?branch_id=…` | liste les travaux réclamables |
| 2 | `POST …/jobs/{id}/claim` | réclame atomiquement, reçoit le flux ESC/POS (base64) |
| 3 | *(transport imprimante)* | envoie les octets à l'imprimante |
| 4 | `POST …/jobs/{id}/ack` | marque `printed` — **uniquement après envoi réussi** |
| 4' | `POST …/jobs/{id}/fail` | signale l'échec + motif, le serveur remet en file |

Garanties :

- **Aucune impression supposée** : pas d'`ack` sans envoi réussi au transport.
- **Pas de double impression après crash** : le journal local (`--state-file`)
  est écrit *avant* l'`ack`. Au redémarrage, un travail déjà imprimé est
  acquitté sans être réimprimé.
- **Reprise sur erreur** : `--max-attempts` tentatives avec backoff exponentiel,
  puis `fail` avec le statut imprimante dans le motif.
- **Statut imprimante** : interrogation temps réel ESC/POS (`DLE EOT 1`) sur
  les transports `tcp` et `usb` ; `--check` affiche le statut et sort.

## Prérequis

- Python ≥ 3.9 (aucune dépendance externe — `urllib` uniquement).
- Un nœud bridge activé côté API :
  - `RESTAURANT_PRINT_BRIDGE_ENABLED=true`
  - `RESTAURANT_PRINT_BRIDGE_TOKEN_HASH=hash('sha256', <jeton>)`
- L'identifiant de société (`X-Company-Id`) et de succursale (`branch_id`).

## Installation

```bash
sudo install -d /opt/leopardo-print-bridge
sudo install -m 0755 leopardo_print_bridge.py /opt/leopardo-print-bridge/
sudo install -d -m 0700 /etc/leopardo
```

Le jeton **ne se passe jamais en argument** (visible dans `ps`, l'historique
shell et les logs sudo) :

```bash
sudo sh -c 'printf "%s" "<JETON_BRIDGE>" > /etc/leopardo/print-bridge.token'
sudo chmod 600 /etc/leopardo/print-bridge.token
```

## Lancement manuel

```bash
# Simulation (écrit les flux dans ./printed) — aucun matériel requis
python3 leopardo_print_bridge.py \
  --api-url https://api.example.com/api/v1 \
  --company-id <UUID_SOCIETE> --branch-id <UUID_SUCCURSALE> \
  --transport file --target ./printed --once --verbose

# Imprimante réseau (port 9100)
python3 leopardo_print_bridge.py --transport tcp --target 192.168.1.50:9100 \
  --company-id <UUID_SOCIETE> --branch-id <UUID_SUCCURSALE>

# Imprimante USB (périphérique caractère Linux)
python3 leopardo_print_bridge.py --transport usb --target /dev/usb/lp0 \
  --company-id <UUID_SOCIETE> --branch-id <UUID_SUCCURSALE>

# Vérifier l'imprimante sans rien imprimer
python3 leopardo_print_bridge.py --transport tcp --target 192.168.1.50:9100 --check
```

Le jeton est lu depuis `RESTAURANT_PRINT_BRIDGE_TOKEN` ou `--token-file`.

## Service systemd

```ini
# /etc/systemd/system/leopardo-print-bridge.service
[Unit]
Description=Leopardo — bridge d'impression cuisine (ESC/POS)
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=leopardo
Environment=LEOPARDO_API_URL=https://api.example.com/api/v1
Environment=LEOPARDO_COMPANY_ID=<UUID_SOCIETE>
Environment=LEOPARDO_BRANCH_ID=<UUID_SUCCURSALE>
Environment=PRINTER_TRANSPORT=tcp
Environment=PRINTER_TARGET=192.168.1.50:9100
Environment=PRINTER_REF=kitchen
Environment=PRINTER_PAPER_WIDTH=80
Environment=RESTAURANT_PRINT_BRIDGE_TOKEN_FILE=/etc/leopardo/print-bridge.token
Environment=STATE_FILE=/var/lib/leopardo-print-bridge/state.json
ExecStart=/usr/bin/python3 /opt/leopardo-print-bridge/leopardo_print_bridge.py
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now leopardo-print-bridge
journalctl -u leopardo-print-bridge -f
```

## Tests de simulation

```bash
python3 edge/print-bridge/test_print_bridge.py
```

Les tests démarrent un serveur HTTP local qui rejoue le contrat du bridge et
vérifient : impression + `ack`, absence d'`ack` en cas d'échec imprimante,
nouvelles tentatives, travail déjà réclamé, reprise après crash sans
réimpression, payload invalide, jeton refusé, décodage du statut ESC/POS.

## Validation physique — **nécessite du matériel réel**

La simulation prouve le protocole, pas le papier. À faire sur site, avec une
imprimante thermique réelle :

1. `--check` doit renvoyer `"ok": true` (ou un drapeau explicite : `paper_out`,
   `cover_open`, `unreachable`).
2. Encaisser une commande → le ticket sort en cuisine, lisible, coupé.
3. Couper le papier / ouvrir le capot → le travail passe en `fail` avec le bon
   drapeau, puis repart en file ; remettre le papier → il s'imprime.
4. Débrancher le câble réseau pendant un service → les travaux restent en file
   côté serveur, aucun `ack` fantôme.
5. Redémarrer le daemon juste après une impression → aucun doublon.

## Dépannage

| Symptôme | Cause probable | Action |
|----------|----------------|--------|
| `403 print_bridge_disabled` | bridge non activé côté API | `RESTAURANT_PRINT_BRIDGE_ENABLED=true` |
| `401 print_bridge_token_invalid` | jeton ≠ hash configuré | recalculer `hash('sha256', jeton)` |
| `401 print_bridge_company_unknown` | `X-Company-Id` erroné | vérifier `--company-id` |
| `409 job_not_claimable` | autre terminal a réclamé | normal en multi-poste |
| `tcp_send_failed` | imprimante hors ligne / IP changée | `--check`, vérifier le bail DHCP |
| `usb_send_failed` | mauvais `/dev/usb/lp*` | `ls /dev/usb/`, permissions `lp` |
