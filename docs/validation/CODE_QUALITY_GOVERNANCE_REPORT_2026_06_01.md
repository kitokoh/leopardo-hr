# Code quality governance report - 2026-06-01

## Decision

Le Plan 68.3 est traite comme un audit pragmatique : corriger les incoherences qui peuvent tromper les prochains agents ou les integrateurs, puis ajouter un garde reproductible. Il ne lance pas de refonte lourde des plus gros controllers tant que le lancement n'exige pas une modification fonctionnelle directe.

## Corrections livrees

- `docs/api/README.md` pointe maintenant vers la documentation publique reelle `/docs` et la specification canonique `/docs/openapi.yaml`.
- `docs/PLAN_ACTION/00_SOMMAIRE.md` ne mentionne plus l'ancien chemin `openapi/v1.yaml`.
- `dev-hub/tools/validate-code-quality-governance.ps1` verifie les chemins canoniques API et la presence des artefacts post-67.
- Le gate release readiness integre maintenant cette preuve.

## Observations code

Les fichiers backend les plus grands restent concentres sur les zones attendues :

- `AttendanceController.php`
- `AttendanceService.php`
- `KioskController.php`
- `CommunicationService.php`
- `PaymentBatchController.php`

Decision : ne pas refactorer ces fichiers en masse maintenant. Les extractions doivent rester liees a des changements fonctionnels ou a des bugs identifies, pour eviter une regression large avant marketing.

## Risques restants

- Les gros controllers/services doivent etre reduits progressivement quand un lot touche leur domaine.
- La couverture OpenAPI exhaustive de toutes les routes non critiques reste un chantier separe.
- Les anciens documents d'archive peuvent contenir des termes historiques ; seuls les docs canoniques doivent etre bloques par le garde.

## Validation executee

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File dev-hub/tools/validate-code-quality-governance.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File dev-hub/tools/release-readiness.ps1 -Strict
```
