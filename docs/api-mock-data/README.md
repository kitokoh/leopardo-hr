# Leopardo RH API Mock Data

Ce dossier contient des exemples de réponses JSON pour les différents endpoints de l'API Leopardo RH. Ces fichiers sont générés automatiquement à partir de la spécification OpenAPI (`api/openapi.yaml`) et peuvent être utilisés par les développeurs Front-end (Web et Mobile) pour travailler sans avoir besoin de démarrer l'API Laravel.

## Structure des fichiers

Le nommage des fichiers suit la convention suivante :
`{METHODE}_{CHEMIN_ENDPOINT}_{CODE_STATUT}.json`

Par exemple :
- `post_auth_login_200.json` : Réponse pour une connexion réussie.
- `get_auth_me_200.json` : Détails du profil de l'utilisateur authentifié.
- `get_employees_200.json` : Liste des employés.

## Utilisation pour le Mocking

### 1. Développement Local (Next.js / Flutter)
Vous pouvez configurer vos services d'API pour retourner le contenu de ces fichiers lorsque vous travaillez en mode "mock".

### 2. Mock Service Worker (MSW)
Si vous utilisez MSW pour le web, vous pouvez importer ces fichiers directement dans vos handlers :

```javascript
import { rest } from 'msw'
import loginResponse from './docs/api-mock-data/post_auth_login_200.json'

export const handlers = [
  rest.post('/api/v1/auth/login', (req, res, ctx) => {
    return res(
      ctx.status(200),
      ctx.json(loginResponse)
    )
  }),
]
```

## Mise à jour des exemples

Si la spécification OpenAPI est modifiée, vous pouvez régénérer ces exemples en utilisant le script Python situé dans `tools/generate_api_examples.py`.

```bash
python3 tools/generate_api_examples.py
```

*Note : Ces fichiers sont des outils de développement et ne doivent pas être utilisés en production.*
