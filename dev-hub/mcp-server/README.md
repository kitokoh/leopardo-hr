# Serveur MCP Leopardo

Serveur [Model Context Protocol](https://modelcontextprotocol.io) exposant les
outils de l'assistant Leopardo à des clients externes (IDE, CLI, agents).

Implémente l'**option 3 « pont hybride »** de l'ADR
[`0019-mcp-external-exposure-deferred.md`](../../docs/architecture/adr/0019-mcp-external-exposure-deferred.md)
et la phase 2 de
[`SOLUTION_COMMAND_ASSISTANT_MCP.md`](../../docs/specifications/SOLUTION_COMMAND_ASSISTANT_MCP.md) §12.

## Principe

Le serveur MCP **ne duplique rien** :

- il ne connaît **aucun nom d'outil en dur** — il lit le manifeste
  `manifest/tools.json`, produit par `php artisan ai:tools:manifest` depuis
  `AIToolDefinitionRegistry` (contrat A3, BC-23) ;
- il **n'accède jamais à la base** — il appelle l'API REST `/api/v1` et laisse
  Laravel rester le seul point d'autorisation (tenant → RBAC → Policy du BC →
  confirmation `PendingAction`).

```
client MCP ──stdio/HTTP──▶ leopardo-mcp ──HTTPS Bearer──▶ API Laravel /api/v1
                                │
                                └── manifest/tools.json (généré, versionné)
```

## Installation

```bash
cd dev-hub/mcp-server
npm ci
npm run build
```

## Configuration

| Variable | Option CLI | Rôle |
|---|---|---|
| `LEOPARDO_API_URL` | `--api-url` | URL de base de l'API (ex. `https://api.example.com`) |
| `LEOPARDO_API_TOKEN` | `--token` | Jeton Sanctum de l'utilisateur appelant |
| — | `--transport` | `stdio` (défaut) ou `http` |
| — | `--port` / `--host` | Écoute HTTP (défaut `8787` / `127.0.0.1`) |
| — | `--manifest` | Chemin du manifeste (défaut `manifest/tools.json`) |

Le jeton n'est **jamais** committé : il est fourni par l'environnement.

## Lancement

```bash
# stdio — clients locaux (IDE, CLI)
LEOPARDO_API_URL=https://api.example.com LEOPARDO_API_TOKEN=... node dist/index.js

# Streamable HTTP — clients distants
LEOPARDO_API_URL=https://api.example.com LEOPARDO_API_TOKEN=... \
  node dist/index.js --transport http --port 8787
# POST /mcp  ·  GET /healthz
```

## Exemple de configuration client

```json
{
  "mcpServers": {
    "leopardo": {
      "command": "node",
      "args": ["/chemin/vers/dev-hub/mcp-server/dist/index.js"],
      "env": {
        "LEOPARDO_API_URL": "https://api.example.com",
        "LEOPARDO_API_TOKEN": "…"
      }
    }
  }
}
```

## Surface exposée

- **Outils** (`tools/list`) : les `AIToolDefinition` du manifeste A3 **et** les
  outils CRUD générés depuis `api/openapi.yaml` (issue #9746) — 287 outils au
  total aujourd'hui (9 A3 + 278 générés, dont les verticales Travel et
  Restaurant depuis l'issue #9751 et 40 outils d'action depuis l'issue #9753).
- **Ressources** (`resources/list`) :
  - `leopardo://tools/catalog` — le manifeste complet ;
  - `leopardo://tools/{name}/schema` — schémas d'entrée/sortie d'un outil.

### Les deux sources d'outils

| Source | Fichier | Produit par |
|---|---|---|
| Contrat A3 | `manifest/tools.json` | `php artisan ai:tools:manifest` |
| Surface CRUD | `manifest/openapi-tools.json` | `python3 dev-hub/tools/generate-mcp-openapi-tools.py` |

**Le contrat A3 gagne toujours** en cas de collision de nom : un outil déclaré
par un BC propriétaire porte une sémantique métier que le générateur ne peut pas
déduire d'un verbe HTTP.

Le générateur n'expose pas les ~1 950 opérations de la spec : il filtre par
allowlist de tags (RH/plateforme + verticales Travel et Restaurant), par verbe
CRUD (les endpoints d'action — `/approve`,
`/lock`, `/confirm`… — restent sur `PendingAction`) et exclut `/public/*` et
`/webhooks/*`.

> 📘 Guide complet (configuration, branchement IDE, dépannage) :
> [`docs/GUIDES/GUIDE_SERVEUR_MCP.md`](../../docs/GUIDES/GUIDE_SERVEUR_MCP.md).

## Sécurité — règle de sensibilité

La sensibilité est **déclarée par le BC propriétaire**, jamais décidée par le
serveur MCP :

| `sensitivity` | Comportement du serveur MCP |
|---|---|
| `read` | exécution directe (Laravel applique RBAC + Policy) |
| `write` / `send` | **refus** : aucun effet de bord. L'action doit passer par le flux `PendingAction` de l'API (`POST /api/v1/ai/actions/{id}/confirm`) |
| `write` + `route.action: true` | **proposition** : `POST /api/v1/ai/actions/propose` crée une action en attente et renvoie son `pending_action_id` — jamais d'exécution directe (issue #9753) |

Un outil déclaré sans route REST mappée est refusé (fail-closed), de même
qu'une réponse `403` de l'API.

### Outils d'action (issue #9753)

Les endpoints d'action (`/approve`, `/publish`, `/rotate`…) sont générés avec
`--include-actions` et portent `route.action: true`. `tools/call` les route vers
`POST /api/v1/ai/actions/propose` : Laravel applique le RBAC, vérifie que
l'outil a un handler de confirmation, stocke l'action en attente et renvoie son
identifiant. **Aucun effet métier** tant qu'un humain n'a pas confirmé.

Le serveur MCP n'a aucun accès base : c'est cet endpoint qui fait le pont avec
`PendingActionStore`, sans dupliquer la logique de stockage.

## Régénérer le manifeste

Après tout ajout/modification d'`AIToolDefinition` :

```bash
cd api
php artisan ai:tools:manifest --output=../dev-hub/mcp-server/manifest/tools.json
```

Après toute modification de `api/openapi.yaml` :

```bash
python3 dev-hub/tools/generate-mcp-openapi-tools.py --include-actions
```

`--include-actions` est requis : le manifeste versionné inclut les outils
d'action (issue #9753), et la garde CI compare avec la même option.

Les gardes CI `dev-hub/tools/check-mcp-manifest-drift.sh` et
`dev-hub/tools/check-mcp-openapi-tools-drift.sh` (job *Hygiene Guards*) échouent
si un manifeste versionné diverge de sa source.

## Tests

```bash
npm run typecheck
npm test
```

La suite inclut un test d'**intégration** du transport Streamable HTTP
(`src/transports/http.integration.test.ts`, issue #9747) : vrai serveur sur port
éphémère, handshake, `tools/list`, `tools/call`, `/healthz`, 404.
