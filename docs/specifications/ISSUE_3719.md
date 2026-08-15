# Mini-spec — Issue #3719

## Intention
Rendre l’interface web-offline compatible avec les routes EdgeSync réellement exposées et éviter qu’un contrat de santé incomplet ou un asset PWA absent ne casse l’expérience.

## Changements

| Surface | Correction |
|---|---|
| Health | Appel de `/api/v1/edge/health` au lieu de `/api/edge/health` |
| Synchronisation | Bouton désactivé honnêtement : l’API publique ne fournit pas de sync sans `nodeId` et authentification Edge |
| Contrat santé | `node_id`, `pending_sync` et `last_sync` sont optionnels ; l’UI affiche `—` lorsqu’ils sont absents |
| Service worker | Précache tolérant : chaque asset est tenté individuellement et un 404 n’annule pas l’installation |

## Critères d’acceptation

Le health cible la route versionnée réelle. Aucun appel vers `/api/edge/sync` n’est conservé. Les données absentes ne produisent jamais `undefined` à l’écran. Le service worker termine son installation même si `/index.html` ou un autre asset optionnel est absent.

## Validation

La vérification TypeScript de web-offline et `git diff --check` passent localement.
