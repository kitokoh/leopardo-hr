# Mini-spécification — Issue #3590

## Objectif

Trois hygiènes kiosk : (1) ne jamais committer `config.json` (token réel) ; (2) supprimer le drift `apiBaseUrl` entre `app.js` et `bridge.py` ; (3) alléger la sonde de connectivité qui téléchargeait le roster complet toutes les 15 s.

## Constat

1. `front/zkteco-kiosk/.gitignore` n'ignore que `desktop-bridge/data/` ; le README documente « copier config.example.json → config.json » (avec le vrai `kioskToken`) → scénario menant à committer le token.
2. `app.js:111` normalise `apiBaseUrl` (ajoute `/api/v1` si absent) ; `bridge.py:216` concatène brut → une config sans suffixe fonctionne pour l'UI mais 404 toute la sync.
3. `online_status()` (bridge.py:283) télécharge `/kiosks/{device}/roster` à chaque poll, appelé toutes les 15 s par l'UI → charge API inutile.

## Décision

1. Ajouter `config.json` au `.gitignore` kiosk (en plus de `desktop-bridge/data/`).
2. `SyncEngine.__init__` normalise `apiBaseUrl` (strip trailing `/`, ajoute `/api/v1` si absent) — miroir exact d'`app.js`.
3. `online_status()` sonde `GET /health` (endpoint public, léger) au lieu du roster.

## Critères d'acceptation

1. `git check-ignore config.json` renvoie le fichier depuis le répertoire kiosk.
2. `SyncEngine("https://exemple.test")` → `api_base_url == "https://exemple.test/api/v1"` ; `…/api/v1` inchangé ; trailing slash géré.
3. `online_status()` n'émet plus de requête `/roster` ; tests unitaires (7) verts : 3 globals + 4 normalisation/health.
4. `python3 -m py_compile bridge.py` OK ; `git diff --check` OK.

## Plan de retour arrière

Réversion du commit ; aucune donnée locale n'est supprimée (le `.gitignore` est additif, la normalisation est un repli).
