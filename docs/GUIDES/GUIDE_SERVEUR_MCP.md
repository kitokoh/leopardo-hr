# Guide — Serveur MCP Leopardo

> **Public :** développeurs du monorepo et intégrateurs externes.
> **Code :** [`dev-hub/mcp-server/`](../../dev-hub/mcp-server/README.md).
> **Décision d'architecture :** [ADR-0019](../architecture/adr/0019-mcp-external-exposure-deferred.md) (option 3 « pont hybride »).

## 1. À quoi sert ce serveur

Le serveur MCP expose les outils de l'assistant Leopardo à des **clients MCP
externes** : IDE (VS Code, Cursor), CLI, agents. Un client MCP découvre les
outils (`tools/list`), lit leurs schémas JSON, et les appelle (`tools/call`).

```
client MCP ──stdio / Streamable HTTP──▶ leopardo-mcp ──HTTPS Bearer──▶ API Laravel /api/v1
                                            │
                                            └── manifest/tools.json          (contrat A3)
                                                manifest/openapi-tools.json  (généré)
```

### Ce que le serveur ne fait PAS

C'est la partie la plus importante du guide, parce que c'est ce qui rend le
serveur sûr :

| Le serveur ne… | Parce que… |
|---|---|
| n'accède **jamais** à la base de données | Laravel reste le seul point d'autorisation (tenant → RBAC → Policy du BC) |
| ne **décide jamais** d'une autorisation | il relaie le jeton du client ; un `403` de l'API est un `403` pour le client |
| n'exécute **jamais** une action sensible | `write` / `send` sont refusés : la confirmation passe par `PendingAction` |
| ne **code aucun nom d'outil en dur** | les outils viennent des manifestes, pas du code du serveur |

## 2. Prérequis

| Outil | Version | Usage |
|---|---|---|
| Node.js | ≥ 20 | exécution du serveur |
| Python 3 + PyYAML | 3.11+ | **régénération** du manifeste OpenAPI uniquement |
| PHP 8.4 + Composer | — | **régénération** du manifeste A3 uniquement |

Le serveur lui-même ne demande que Node : ni PHP, ni Python, ni base de données.

## 3. Installation

```bash
cd dev-hub/mcp-server
npm ci
npm run build
```

Le binaire est produit dans `dist/index.js`.

## 4. Configuration

| Variable d'environnement | Option CLI | Rôle |
|---|---|---|
| `LEOPARDO_API_URL` | `--api-url` | URL de base de l'API (ex. `https://api.example.com`) |
| `LEOPARDO_API_TOKEN` | `--token` | Jeton Sanctum de l'utilisateur appelant |
| — | `--transport` | `stdio` (défaut) ou `http` |
| — | `--port` | port d'écoute HTTP (défaut `8787`) |
| — | `--host` | interface d'écoute HTTP (défaut `127.0.0.1`) |
| — | `--manifest` | manifeste A3 alternatif (défaut `manifest/tools.json`) |

> ⚠️ **Le jeton n'est jamais committé.** Il est fourni par l'environnement ou
> par `--token`, et il porte les droits de l'utilisateur qui l'a émis : un jeton
> de manager donne accès aux outils de manager.

## 5. Lancement

### stdio — clients locaux (IDE, CLI)

```bash
LEOPARDO_API_URL=https://api.example.com \
LEOPARDO_API_TOKEN="$LEOPARDO_TOKEN" \
  node dist/index.js
```

### Streamable HTTP — clients distants

```bash
LEOPARDO_API_URL=https://api.example.com \
LEOPARDO_API_TOKEN="$LEOPARDO_TOKEN" \
  node dist/index.js --transport http --port 8787
```

| Endpoint | Méthode | Rôle |
|---|---|---|
| `/mcp` | `POST` | requêtes JSON-RPC MCP (stateless : un serveur par requête) |
| `/healthz` | `GET` | sonde de disponibilité — aucune donnée métier |

Le transport est **stateless** : l'état métier vit dans Laravel, pas dans une
session MCP. Plusieurs instances peuvent donc tourner derrière un load balancer
sans affinité de session.

## 6. Brancher un client MCP

### Claude Desktop / client compatible `mcpServers`

```json
{
  "mcpServers": {
    "leopardo": {
      "command": "node",
      "args": ["/chemin/absolu/vers/dev-hub/mcp-server/dist/index.js"],
      "env": {
        "LEOPARDO_API_URL": "https://api.example.com",
        "LEOPARDO_API_TOKEN": "…"
      }
    }
  }
}
```

### VS Code (`.vscode/mcp.json`)

```json
{
  "servers": {
    "leopardo": {
      "type": "stdio",
      "command": "node",
      "args": ["${workspaceFolder}/dev-hub/mcp-server/dist/index.js"],
      "env": {
        "LEOPARDO_API_URL": "https://api.example.com",
        "LEOPARDO_API_TOKEN": "${input:leopardoToken}"
      }
    }
  }
}
```

### Client distant (Streamable HTTP)

```json
{
  "mcpServers": {
    "leopardo": {
      "type": "http",
      "url": "http://127.0.0.1:8787/mcp"
    }
  }
}
```

### Vérification en une commande

```bash
curl -s http://127.0.0.1:8787/healthz
# {"status":"ok","tools":170}
```

## 7. Les deux sources d'outils

| Source | Fichier | Produit par | Contenu |
|---|---|---|---|
| **Contrat A3** | `manifest/tools.json` | `php artisan ai:tools:manifest` | outils métier déclarés par les BC (`AIToolDefinitionRegistry`) |
| **Surface CRUD** | `manifest/openapi-tools.json` | `python3 dev-hub/tools/generate-mcp-openapi-tools.py` | outils dérivés de `api/openapi.yaml` |

**Règle de précédence : le contrat A3 gagne toujours.** Un outil déclaré par un
BC propriétaire porte une sémantique métier (et une sensibilité) que le
générateur ne peut pas deviner depuis un verbe HTTP. En cas de collision de nom,
la définition A3 est conservée.

### Périmètre de la génération OpenAPI

Le générateur n'expose **pas** les 1 950 opérations de la spec. Il applique
trois filtres :

1. **allowlist de tags** — `Employees`, `Tasks`, `Projects`, `Schedules`,
   `Attendance`, `Evaluations`, `Payroll`, `Me`, `Notifications`,
   `HR Referentials` ;
2. **verbes CRUD uniquement** — les endpoints d'action (`/approve`, `/lock`,
   `/confirm`, `/publish`…) sont exclus : ils passent par `PendingAction` ;
3. **exclusion de `/public/*` et `/webhooks/*`** — pas d'authentification
   tenant, donc hors du modèle d'autorisation.

Résultat actuel : **161 outils** (94 `read`, 67 `write`).

## 8. Régénérer les manifestes

### Manifeste A3 (après ajout/modification d'une `AIToolDefinition`)

```bash
cd api
php artisan ai:tools:manifest --output=../dev-hub/mcp-server/manifest/tools.json
```

### Manifeste OpenAPI (après toute modification de `api/openapi.yaml`)

```bash
python3 dev-hub/tools/generate-mcp-openapi-tools.py
```

Vérifier sans écrire (mode CI) :

```bash
python3 dev-hub/tools/generate-mcp-openapi-tools.py --check
```

### Gardes CI associées

| Garde | Job | Ce qu'elle attrape |
|---|---|---|
| `dev-hub/tools/check-mcp-manifest-drift.sh` | *Hygiene Guards* | manifeste A3 désynchronisé du registre |
| `dev-hub/tools/check-mcp-openapi-tools-drift.sh` | *Hygiene Guards* | manifeste OpenAPI désynchronisé de la spec |
| `mcp-server-ci.yml` | *MCP Server (typecheck + tests)* | régression de code, de schéma ou de transport |

Une PR qui modifie `api/openapi.yaml` sans régénérer le manifeste est **rouge**.
C'est volontaire : un client MCP qui reçoit un contrat périmé ne le sait pas.

## 9. Règle de sensibilité

La sensibilité est **déclarée par le BC propriétaire** (ou dérivée du verbe HTTP
pour les outils générés), jamais décidée par le serveur MCP.

| `sensitivity` | Comportement du serveur MCP |
|---|---|
| `read` | exécution directe — Laravel applique RBAC + Policy |
| `write` | **refus**, aucun effet de bord |
| `send` | **refus**, aucun effet de bord |

Pour une action `write`/`send`, le client doit passer par le flux de
confirmation de l'API :

```
POST /api/v1/ai/actions/{id}/confirm
```

Un outil déclaré sans route REST mappée est également refusé (**fail-closed**).

## 10. Dépannage

| Symptôme | Cause probable | Correctif |
|---|---|---|
| `Configuration manquante : --api-url…` (exit 2) | variables absentes | exporter `LEOPARDO_API_URL` et `LEOPARDO_API_TOKEN` |
| `Refus ou erreur API (401)` | jeton expiré ou révoqué | réémettre un jeton Sanctum |
| `Refus ou erreur API (403)` | RBAC : l'utilisateur n'a pas le droit | utiliser un jeton porteur du bon rôle |
| `Outil inconnu : '…' (fail-closed)` | nom absent des deux manifestes | vérifier `tools/list`, puis régénérer |
| `Outil '…' … sans route REST mappée` | outil A3 sans entrée `TOOL_ROUTES` | ajouter la route dans `src/tools/execute.ts` |
| `Paramètre de chemin manquant : '…'` | argument obligatoire omis | lire `inputSchema` de l'outil |
| `EADDRINUSE` au démarrage HTTP | port déjà pris | `--port 0` (port éphémère) ou choisir un port libre |
| Manifeste désynchronisé en CI | spec ou registre modifié sans régénération | relancer la commande de régénération du §8 |

## 11. Tests

```bash
cd dev-hub/mcp-server
npm run typecheck
npm test
```

La suite couvre : le client HTTP, le chargement et la fusion des manifestes, le
pont d'exécution (routes A3 et générées), les ressources MCP, et le transport
Streamable HTTP en **intégration réelle** (serveur sur port éphémère, handshake,
`tools/list`, `tools/call`, `healthz`, 404).

## 12. Références

- [`dev-hub/mcp-server/README.md`](../../dev-hub/mcp-server/README.md) — référence du paquet
- [`docs/specifications/SOLUTION_COMMAND_ASSISTANT_MCP.md`](../specifications/SOLUTION_COMMAND_ASSISTANT_MCP.md) §12 — phase 2
- [`docs/architecture/adr/0019-mcp-external-exposure-deferred.md`](../architecture/adr/0019-mcp-external-exposure-deferred.md) — décision d'exposition
- [Model Context Protocol](https://modelcontextprotocol.io) — spécification
