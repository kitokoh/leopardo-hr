# Leopardo Edge

Composant local installé chez le client pour le fonctionnement **offline-first**.

## Architecture

```
[Mobiles employés]
       ↓ WiFi local
[Leopardo Edge]  ←→  [Leopardo Cloud]
       ↓
[SQLite local]
```

## Installation rapide

```bash
sudo bash install.sh \
  --node-id <UUID_DU_NODE> \
  --token   <EDGE_TOKEN>
```

Obtenez le `node-id` et le `token` depuis votre dashboard Leopardo → **Paramètres → Edge Nodes**.

## Services

| Service         | Port  | Rôle                          |
|-----------------|-------|-------------------------------|
| `edge-api`      | 7878  | API Laravel locale            |
| `edge-ui`       | 7879  | Interface web locale          |
| `edge-sync`     | —     | Daemon de synchronisation     |
| `edge-proxy`    | 80    | Reverse proxy (leopardo.local)|

## Modes de fonctionnement

| Mode       | Internet requis | Sync Cloud |
|------------|-----------------|------------|
| `cloud`    | Oui             | Temps réel |
| `hybrid`   | Optionnel        | Différée   |
| `offline`  | Non             | Jamais     |

## Commandes utiles

```bash
# Voir les logs
docker compose logs -f

# Forcer une synchronisation
docker compose exec edge-api php artisan edge:sync-daemon

# Statut des services
docker compose ps
```

## Sécurité

- Licence JWT (RS256) signée par Leopardo Cloud
- Expiration automatique (30 jours par défaut)
- Renouvellement automatique si Internet disponible
- Token Edge unique par node (révocable depuis le Cloud)
