# Audit statut reel PA2-KIO-001 — 2026-07-25

Statut: complete
Auteur: audit interne KiloClaw (agent)
Perimetre: ticket `PA2-KIO-001` de `02_BACKLOG_ATOMIQUE.md` / `03_GITHUB_PROJECT_IMPORT.csv` / GitHub Issue #984, verifie contre le code reel (`api/app/Modules/Attendance/Interfaces/Api/V1/KioskController.php`, `api/routes/modules/rh.php`, `api/routes/modules/integrations.php`, `front/zkteco-kiosk/desktop-bridge/bridge.py`, `api/tests/Feature/BiometricWorkflowTest.php`).

## Critere d'acceptation du ticket

> Manager provisionne device, sync token, roster, annonces ; mode offline conserve.

## Constat par critere

1. **Manager provisionne device** — Deja FAIT. `KioskController::register()` (`POST /kiosks`) est protege par `abort_unless($actor?->isManager(), 403, 'FORBIDDEN')` : seul un manager peut provisionner un nouvel appareil kiosk pour son entreprise.
2. **Sync token** — Deja FAIT. `register()` genere un `sync_token_hash` (`Hash::make(Str::random(48))`) stocke cote serveur et retourne le token en clair une seule fois dans la reponse (`sync_token` dans le payload de creation) — le manager doit le copier immediatement sur l'appareil kiosk, pattern standard token-once-visible.
3. **Roster** — Deja FAIT. `GET /kiosks/{deviceCode}/roster` (authentifie par `X-Kiosk-Token`, pas par un token utilisateur Sanctum) retourne la liste des employes actifs de l'entreprise avec leurs capacites biometriques (visage/empreinte), consommee par le bridge desktop (`bridge.py`, `sync_all()`/`SYNC_ENGINE`).
4. **Annonces** — Deja FAIT. `GET /kiosks/{deviceCode}/announcements` existe et est deja route (`integrations.php`), permettant au kiosk d'afficher les annonces entreprise sans authentification utilisateur.
5. **Mode offline conserve** — Deja FAIT. Le bridge desktop local (`front/zkteco-kiosk/desktop-bridge/bridge.py`) maintient une base SQLite locale (`punch_queue`) qui met en file les pointages (`queue_punch()`) meme sans connexion reseau, avec un statut `queued`/synchronise (`sync_status`), et un compteur de file (`queue_count()`) expose a l'UI kiosk. La synchronisation vers l'API (`POST /kiosks/{deviceCode}/sync`) est un processus asynchrone separe qui ne bloque jamais le pointage local.

**Couverture de test existante** : `api/tests/Feature/BiometricWorkflowTest.php` et `api/tests/Feature/KioskMultiEventPunchTest.php` exercent deja `POST /api/v1/kiosks` (provisioning) de bout en bout, pas seulement le code applicatif sans verification.

## Conclusion

**PA2-KIO-001 est deja FAIT**, tous les criteres d'acceptation sont satisfaits par le code existant et deja sous test, mais le ticket n'a jamais ete marque comme tel dans `02_BACKLOG_ATOMIQUE.md` ni rattache explicitement a l'issue #984. Aucun travail de code supplementaire n'etait necessaire.

## Verification

- Lecture directe de `KioskController::register()/roster()/sync()`, `routes/modules/rh.php` et `integrations.php` pour confirmer chaque route.
- Lecture directe de `bridge.py` (`punch_queue`, `queue_punch`, `queue_count`) confirmant le mode offline.
- Confirmation de la couverture de test existante (`BiometricWorkflowTest.php` exerce `POST /api/v1/kiosks`).
- Aucun test automatise supplementaire necessaire (audit documentaire, aucun code modifie).
