# Operational context

## Etat de lancement

Le dernier rapport readiness indique un Go technique controle, avec reserve operationnelle: recette sur vrais appareils et testeurs reels.

Preuves importantes:

- `docs/validation/RELEASE_READINESS_REPORT_2026_06_01.md`
- `docs/validation/MOBILE_RELEASE_DEVICE_QA_2026_06_01.md`
- `docs/validation/MOBILE_RUNTIME_SMOKE_REPORT_2026_06_01.md`
- `docs/validation/PLATFORM_ADMIN_API_SMOKE_2026_06_01.md`
- `docs/PLAN_ACTION/69_PLAN_EXECUTION_LANCEMENT_MOBILE_FIRST_COMPANY_OS.md`

## Comptes demo

- Platform Admin: `admin@leopardo-rh.com` / `password123`
- Les autres comptes sont exposes par `/api/v1/demo-users`.

## Risques restants

- Recette device physique encore a produire apres chaque distribution Firebase.
- Kiosk/ZKTeco a tester sur materiel reel.
- Portail developpeur/sandbox pas encore complet.
- Stress test authentifie encore a etendre.
- Positionnement/pricing a valider par pilotes payants.

## Procedure de travail

1. Fetch `origin/main`.
2. Creer branche `codex/...`.
3. Corriger petit lot.
4. Lancer gardes locaux possibles.
5. Pousser PR.
6. Attendre GitHub Actions.
7. Merger et supprimer branche.
8. Revenir aligner `main`.

