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

- **Outils** (`tools/list`) : exactement les `AIToolDefinition` du manifeste.
- **Ressources** (`resources/list`) :
  - `leopardo://tools/catalog` — le manifeste complet ;
  - `leopardo://tools/{name}/schema` — schémas d'entrée/sortie d'un outil.

## Sécurité — règle de sensibilité

La sensibilité est **déclarée par le BC propriétaire**, jamais décidée par le
serveur MCP :

| `sensitivity` | Comportement du serveur MCP |
|---|---|
| `read` | exécution directe (Laravel applique RBAC + Policy) |
| `write` / `send` | **refus** : aucun effet de bord. L'action doit passer par le flux `PendingAction` de l'API (`POST /api/v1/ai/actions/{id}/confirm`) |

Un outil déclaré sans route REST mappée est refusé (fail-closed), de même
qu'une réponse `403` de l'API.

## Régénérer le manifeste

Après tout ajout/modification d'`AIToolDefinition` :

```bash
cd api
php artisan ai:tools:manifest --output=../dev-hub/mcp-server/manifest/tools.json
```

La garde CI `dev-hub/tools/check-mcp-manifest-drift.sh` (job *Hygiene Guards*)
échoue si le manifeste versionné diverge du registre.

## Tests

```bash
npm run typecheck
npm test
```
