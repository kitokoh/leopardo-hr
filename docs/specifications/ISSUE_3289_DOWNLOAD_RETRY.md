# ISSUE 3289 — Téléchargements mobiles : downloadWithRetry au lieu de dio.download direct

> Spec Kit mini-spec — issue #3289 (vague qa-expert5-2026-08-15).

## Constat

12 sites `apiClient.dio.download(...)` directs (preuves d'absence, bulletins
paie, reçus d'avances — apps employee/manager/hr) : une 404/401/503 ou un
cold-start renvoie une `DioException` brute non traduite et peut écrire une
page d'erreur JSON dans le fichier local (cache empoisonné).

## Décision

Ajouter `ApiClient.downloadWithRetry(path, savePath, {options, timeoutOverride, onRetry})` :

- retry GET idempotent (mêmes critères que `requestWithRetry` : 502/503/504,
  timeout, erreur réseau) avec backoff ;
- validation du status 2xx (`dio.download` retourne la `Response`) ;
- garde fichier vide (page d'erreur 2xx) → `EMPTY_DOWNLOAD` ;
- suppression du fichier partiel/page d'erreur sur échec (`deleteOnError: true`
  + nettoyage explicite) ;
- `dart:io` importé (déjà utilisé par `push_notification_service` / `edge_database`).

Les 12 sites passent sur `apiClient.downloadWithRetry(...)`.

## Contrat

- Succès : chemin du fichier téléchargé (non vide).
- Échec : `DioException` mappée ou `ApiException` — jamais de fichier partiel.
- Compatibilité : même signature d'options que `dio.download`.

## Validation

- `flutter analyze` des 3 apps (CI mobile).
- `rg 'apiClient\.dio\.download' front/mobile_apps` → 0 occurrence hors api_client.
