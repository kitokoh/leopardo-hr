# Alignement PDF Infra vs realite du depot

Date : 2026-04-25

## Objet

Cette note aligne le document `Leopardo_RH_Architecture_Deploiement.pdf` avec l'etat reel du depot a la date du 25 avril 2026.

Le PDF reste utile pour comprendre :

- la vision d'architecture,
- la cible d'hebergement,
- les flux inter-composants,
- et les choix envisages a moyen terme.

En revanche, il ne doit pas etre lu comme une photographie exacte de la production ou de la pre-production actuelles.

## Ce qui fait foi aujourd'hui

Les references a utiliser avant le PDF sont :

1. `PILOTAGE.md`
2. `CHANGELOG.md`
3. `docs/GESTION_PROJET/RUNBOOK_DEPLOY.md`
4. `docs/GESTION_PROJET/RENDER_SETUP.md`
5. `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md`

## Synthese executive

- Oui, le PDF decrit correctement la logique generale Render + Neon + GitHub Actions.
- Non, il ne correspond pas exactement a la realite operationnelle actuelle.
- Le principal probleme est qu'il melange l'etat courant et une cible future Oracle VPS plus avancee que le code et les runbooks actifs.

## Alignement point par point

| Sujet | PDF | Realite actuelle | Decision |
|---|---|---|---|
| Hebergement actif | Render + Neon en phase dev/staging, Oracle cible | Render + Neon sont toujours la realite active documentee | Garder Render + Neon comme etat courant |
| Oracle VPS | Decrit comme cible de production quasi preparee | Pas la source de verite operationnelle active | Reclasser Oracle en cible future, pas en realite courante |
| Source de verite infra | Le PDF semble autonome | Le repo dit explicitement que le PDF n'est pas canonique | Le PDF reste secondaire |
| Deploy Render | Exemple `render.yaml` | Le depot deploie via Docker/Render avec `api/Dockerfile.prod` et workflow GitHub | Le `render.yaml` du PDF est obsolete pour ce depot |
| Runtime API | Laravel sur Render | Laravel deployee via image Docker basee sur FrankenPHP | Documenter FrankenPHP comme implementation reelle |
| Healthcheck | `/api/health` ou exemple similaire | Le code expose `/api/v1/health` et le workflow l'attend explicitement | Le healthcheck canonique est `/api/v1/health` |
| CI/CD | GitHub Actions + deploy Render | Confirme par les workflows actifs | Conforme |
| Mobile Android | Build et distribution Android | Firebase App Distribution Android est active | Conforme dans l'esprit |
| Mobile iOS | Pipeline iOS/App Store detaille | Projet iOS present, mais pas de pipeline iOS equivalent actif dans le depot | Le PDF est trop avance sur iOS |
| Flutter flavors | `main_dev.dart`, `main_staging.dart`, `main_prod.dart` | Le depot s'appuie surtout sur `main.dart` + `dart-define` dans les workflows | La doc flavors est a corriger |
| Local dev Docker | API + Postgres + Redis | `api/docker-compose.yml` monte API + PostgreSQL, pas Redis | Le PDF surestime la stack locale actuelle |
| Queue/cache prod cible | Redis/queue workers prevus | Redis existe dans le code/config, mais l'operationnel actif reste plus simple | A garder comme cible, pas comme acquis partout |
| Backup | Scripts S3 type `backup-db.sh` / `test-restore.sh` | Le runbook actif s'appuie sur `scripts/backup_drill.sh` | Le runbook courant prime sur les scripts du PDF |
| Multi-tenancy | Neon/PostgreSQL avec schema tenant evoque | Le depot a deja depasse l'ancien scope MVP et utilise des schemas | La realite code prime sur les sections simplifiees du PDF |

## Ecarts a corriger dans le PDF

Les sections suivantes doivent etre revisees lors de la prochaine mise a jour du PDF :

1. Remplacer l'idee "le PDF decrit l'etat actuel" par une separation stricte :
   - etat courant,
   - cible proche,
   - cible future.
2. Remplacer la configuration `render.yaml` par l'implementation reelle basee sur :
   - `api/Dockerfile.prod`
   - `api/docker-entrypoint.sh`
   - `.github/workflows/deploy-main.yml`
3. Corriger le healthcheck vers `/api/v1/health`.
4. Corriger la partie Flutter pour decrire l'usage actuel de `dart-define`.
5. Corriger la partie local dev pour ne pas presenter Redis comme demarre par defaut si ce n'est pas le cas.
6. Rebaser la section backup/restore sur `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md`.
7. Reclasser toute la partie Oracle VPS comme trajectoire cible et non comme etat de deploiement "presque fait".
8. Revoir la partie iOS/App Store pour distinguer :
   - support technique du projet iOS,
   - pipeline CI reellement actif.

## Decision operationnelle

Jusqu'a mise a jour du PDF, toute decision infra doit etre prise a partir des fichiers suivants :

- `PILOTAGE.md`
- `docs/GESTION_PROJET/RUNBOOK_DEPLOY.md`
- `docs/GESTION_PROJET/RENDER_SETUP.md`
- `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md`

Le PDF doit etre interprete comme une reference de vision et de cadrage, pas comme un runbook.
