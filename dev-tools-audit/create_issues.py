#!/usr/bin/env python3
"""Crée les issues de l'audit architecture 2026-11-26 (idempotent par titre)."""
import json
import os
import urllib.request

TOKEN = os.environ["GH_TOKEN"]
REPO = "kitokoh/leopardo-hr"
API = f"https://api.github.com/repos/{REPO}"


def call(method, path, payload=None):
    data = json.dumps(payload).encode() if payload is not None else None
    req = urllib.request.Request(
        API + path,
        data=data,
        method=method,
        headers={
            "Authorization": f"token {TOKEN}",
            "Accept": "application/vnd.github+json",
            "Content-Type": "application/json",
            "User-Agent": "leopardo-audit",
        },
    )
    with urllib.request.urlopen(req) as r:
        return json.loads(r.read().decode())


ISSUES = [
    {
        "title": "[AUDIT-ARCH-01][CRITIQUE] RLS posé mais inerte : le rôle applicatif est superuser et rien ne bloque le déploiement",
        "labels": ["security", "architecture", "critical"],
        "body": """## Constat

L'ADR-9879 a posé des politiques RLS sur 8 tables (`bulk_payment_claims`, `pay_slips`, `payroll_runs`, `employees`, `absences`, `contracts`, `leave_balances`, `attendance_logs`), avec `ENABLE` + `FORCE ROW LEVEL SECURITY`.

**Mais PostgreSQL fait TOUJOURS bypasser RLS aux superusers, même avec `FORCE`.** Tant que `DB_USERNAME` est le rôle propriétaire (superuser), ces 8 politiques ne filtrent **rien**. Le dépôt le documente lui-même dans `TenantRlsStatusCommand` : « RLS est annoncé mais BYPASSÉ — c'est l'état actuel du dépôt ».

Aggravant : `php artisan tenant:rls-status` existe, sait rendre ce verdict (code 1 = bypassé), **et n'est branché dans aucun workflow ni dans `docker-entrypoint.sh`**. La seule sonde capable de dire la vérité n'est jamais exécutée.

## Pourquoi c'est critique

Une politique « posée mais inopérante » est le pire des états : elle donne l'impression d'une isolation multi-tenant qui n'existe pas. L'isolation réelle repose donc **entièrement** sur le `BelongsToCompany` global scope applicatif — une seule couche, sans filet. Un `withoutGlobalScope()` oublié (il y en a 246 dans `api/app`, baseline #7960) = fuite inter-tenant.

## Critères d'acceptation

- [ ] `tenant:rls-status --strict` est exécuté après les migrations dans `docker-entrypoint.sh` (ou dans `deploy-main.yml`), et **fait échouer le déploiement** si le rôle bypass RLS en `DEPLOY_TIER=prod`.
- [ ] `database/scripts/rls/create-app-role.sql` est appliqué sur le tier prod et `DB_USERNAME=leopardo_app` (`NOSUPERUSER NOBYPASSRLS`).
- [ ] Un test d'intégration prouve qu'une requête sans `app.current_company_id` publié ne renvoie **aucune** ligne sur `employees`.
- [ ] La documentation `docs/adr/ADR-9879-rls-tenant-isolation.md` indique l'état réel (bypassé / effectif) avec la date de bascule.

## Références

`api/app/Console/Commands/TenantRlsStatusCommand.php`, `api/database/scripts/rls/create-app-role.sql`, ADR-9879, #9896.
""",
    },
    {
        "title": "[AUDIT-ARCH-02][CRITIQUE] 523 tables portent company_id, 8 seulement sont couvertes par RLS",
        "labels": ["security", "architecture", "critical"],
        "body": """## Constat

`database/scripts/rls/inventory-company-id-tables.sh` mesure **523 tables** `shared_tenants` portant une colonne `company_id`. L'ADR-9879 en couvre **8**.

Les 515 restantes dépendent uniquement du global scope Eloquent `BelongsToCompany`. Ce scope est correct par défaut, mais il est **contournable** : 246 appels `withoutGlobalScope(s)` existent dans `api/app` (baseline #7960), et 11 modèles sont encore en `legacy_pending_review` (non qualifiés) dans `dev-hub/governance/tenant-scope-exceptions.json`.

## Pourquoi c'est critique

Le produit est vendu sur « Secure tenant isolation » (README). L'isolation est donc une **promesse commerciale**, pas seulement un choix technique. Une seule fuite inter-tenant sur des données de paie ou de santé (absences) est un incident RGPD et un incident de confiance irréversible.

## Critères d'acceptation

- [ ] Un inventaire priorisé est publié : les 50 tables les plus sensibles (paie, santé, identité, documents) classées par (a) à couvrir, (b) référence partagée, (c) bloquée par un backfill.
- [ ] Les 11 modèles `legacy_pending_review` sont qualifiés (trait ajouté ou exception justifiée).
- [ ] Chaque lot de politiques RLS livré suit le modèle de #9924 : inventaire des accès légitimes AVANT la politique, `assertNoNullCompanyId()` pour les colonnes nullables.
- [ ] La baseline `withoutGlobalScope` (246) ne croît plus : la garde #7960 est requise au merge.

## Références

`api/database/scripts/rls/inventory-company-id-tables.sh`, `dev-hub/governance/tenant-scope-exceptions.json`, #9924, #7960.
""",
    },
    {
        "title": "[AUDIT-ARCH-03][MAJEUR] La queue et le scheduler tournent dans le conteneur web : perdus à chaque spin-down",
        "labels": ["architecture", "reliability", "major"],
        "body": """## Constat

`render.yaml` documente honnêtement la situation : le service `leopardo-queue-worker` est **commenté** (bloqué par le billing du tier gratuit), et en intérim « la queue ET le scheduler tournent dans le conteneur web (`docker-entrypoint.sh`, gates `WEB_QUEUE_DRAIN` / `WEB_SCHEDULER_LOOP`, défaut on) — **perdus à chaque spin-down free-tier** ».

Le plan du web service est `free` : Render endort l'instance après inactivité. Conséquences concrètes :

- un job de paie, d'export PDF ou de réconciliation mis en queue juste avant le spin-down **ne tourne pas** jusqu'au prochain réveil ;
- le scheduler (`schedule:work`) ne déclenche pas les tâches planifiées (relances, facturation mensuelle, alertes carburant) pendant l'endormissement ;
- `queue:work` dans le conteneur web partage le CPU et la mémoire avec les requêtes HTTP : un pic de jobs dégrade la latence API.

## Pourquoi c'est majeur

Le produit vend de la **paie** et de la **facturation**. Une paie non calculée ou une facture non émise n'est pas une dégradation cosmétique : c'est un défaut fonctionnel silencieux, découvert par le client.

## Critères d'acceptation

- [ ] Un service worker dédié existe (plan payant ou alternative : worker externe, cron GitHub Actions avec credentials dédiés, ou Fly.io/Railway).
- [ ] `WEB_QUEUE_DRAIN` et `WEB_SCHEDULER_LOOP` passent à `false` sur le tier prod dès que le worker dédié est actif.
- [ ] Une alerte existe sur « profondeur de queue > N pendant > M minutes » et sur « dernier run du scheduler > 2× l'intervalle attendu ».
- [ ] `docs/infra/RENDER_TOPOLOGIE_REELLE.md` reflète la topologie cible **réellement provisionnée**, pas seulement souhaitée.

## Références

`render.yaml` (lignes 16-30, 287-330), `api/docker-entrypoint.sh`, #7649, #5578.
""",
    },
    {
        "title": "[AUDIT-ARCH-04][MAJEUR] 42 modules, 452k lignes, 155 arêtes inter-contextes : les frontières ne sont pas tenues à l'exécution",
        "labels": ["architecture", "maintainability", "major"],
        "body": """## Constat

Le backend est un **monolithe modulaire** : 42 modules DDD sous `api/app/Modules`, ~452 000 lignes de PHP, 314 routes dans `api/routes/api.php`.

La discipline est réelle et rare : `check-bounded-context-dependencies.sh` valide **155 arêtes autorisées** contre une matrice versionnée (MAT-002/#5860), et `Module Structure Validator` est un check requis au merge. C'est un point fort à souligner.

**Mais** la matrice autorise 155 arêtes entre 42 contextes, soit une moyenne de ~3,7 dépendances sortantes par module. À cette densité, le « bounded context » est une convention de dossiers plus qu'une frontière de domaine : un changement dans `Payroll` peut casser `Billing`, `HR`, `Accounting` et `Reporting` sans qu'aucun test d'intégration ne le signale.

Aggravant mesuré : plusieurs fichiers dépassent 30 Ko (`ConversationService` 39 Ko, `PlatformCompanyHealthService` 36 Ko, `AuthService` 31 Ko, `KioskController` 30 Ko). Un fichier de cette taille concentre des responsabilités que le découpage en modules était censé séparer.

## Pourquoi c'est majeur

L'objectif affiché est « capable de supporter une évolution ». Avec 155 arêtes, chaque évolution a un rayon d'impact non borné. Le coût se paie en régressions (le ratio fix/feat du dépôt est suivi précisément pour cette raison) et en temps de revue.

## Critères d'acceptation

- [ ] Un rapport de couplage est produit : nombre d'arêtes par module, modules les plus importés, cycles détectés.
- [ ] Les 5 modules les plus importés sont identifiés ; pour chacun, les dépendances qui devraient passer par un **contrat/événement** plutôt qu'un import direct sont listées.
- [ ] La matrice MAT-002 est **réduite** (objectif : -20 % d'arêtes) ou chaque arête restante porte une justification.
- [ ] Les 5 fichiers > 25 Ko sont découpés, ou une issue de découpage est ouverte par fichier avec un plan.

## Références

`dev-hub/tools/check-bounded-context-dependencies.sh`, `api/app/Modules/`, MAT-002/#5860.
""",
    },
    {
        "title": "[AUDIT-ARCH-05][MAJEUR] 1 716 erreurs PHPStan en baseline : l'analyse statique ne protège plus le code existant",
        "labels": ["quality", "maintainability", "major"],
        "body": """## Constat

Trois baselines PHPStan totalisent **1 716 erreurs** tolérées :

| Fichier | Erreurs |
|---|---|
| `api/phpstan-strict-baseline.neon` | 1 072 |
| `api/phpstan-baseline.neon` | 644 |
| `api/phpstan-modules-baseline.neon` | 6 |

`PHPStan — Strict (level 8)` est un check **requis au merge**. Mais un check requis qui valide contre une baseline de 1 716 erreurs ne garantit qu'une chose : *aucune nouvelle erreur*. Il ne dit rien de la qualité du code existant — et 1 072 erreurs en mode strict sur du code de paie et de comptabilité, c'est du typage non vérifié là où une erreur coûte de l'argent réel.

## Pourquoi c'est majeur

La baseline est un outil légitime de migration. Le problème est qu'elle est **devenue permanente** : rien ne la fait décroître. Une baseline qui ne baisse jamais n'est plus une dette temporaire, c'est un plafond de qualité accepté sans décision explicite.

## Critères d'acceptation

- [ ] Un budget de baseline est versionné (`dev-hub/governance/phpstan-baseline-budget.json` existe déjà — le rendre bloquant) : le total ne peut que **décroître**.
- [ ] Un objectif chiffré et daté est fixé (ex. -30 % en 3 mois), avec une issue de suivi.
- [ ] Les erreurs des modules **paie, comptabilité et facturation** sont traitées en priorité (ce sont celles où un type faux a un coût monétaire).
- [ ] Chaque PR qui touche un fichier de la baseline doit réduire son entrée, ou justifier pourquoi pas.

## Références

`api/phpstan-*-baseline.neon`, `dev-hub/tools/check-phpstan-baseline-budget.sh`, `.github/workflows/architecture-check.yml`.
""",
    },
    {
        "title": "[AUDIT-ARCH-06][MAJEUR] 89 workflows GitHub Actions : la saturation CI est un risque de disponibilité du projet",
        "labels": ["ci", "reliability", "major"],
        "body": """## Constat

Le dépôt contient **89 workflows** dans `.github/workflows/`. Le commentaire d'en-tête de `guards-fast.yml` documente lui-même la mesure : « 96 workflows, dont 70 mono-job, dont 44 déclenchés sur `pull_request` ; 179 runs créés/h pour 76 terminés/h ; file `queued` 99 → 140 en 30 min ».

Le regroupement en `guards-fast.yml` a déjà réduit 41 runs → 12 pour dix gardes (-71 %). C'est un bon travail. Mais 89 workflows restent, et le dépôt documente un incident réel : « les 3 checks requis portés par `architecture-check.yml` sont des jobs `needs: detect-changes`. Tant que `detect-changes` n'obtient pas de runner, ces checks n'EXISTENT PAS comme check-runs (`ABSENT`, pas `queued`), et `main` a `enforce_admins: true` → **aucune PR ne peut être mergée** ».

## Pourquoi c'est majeur

La CI n'est pas seulement lente : elle peut **bloquer toute livraison**. Un check requis absent (pas rouge, absent) est indétectable par la garde de protection de branche, qui ne voit que la liste des contexts attendus.

## Critères d'acceptation

- [ ] Un inventaire des 89 workflows est publié : déclencheur, durée médiane, coût runner, redondance avec un autre workflow.
- [ ] Les workflows mono-job restants sont regroupés par surface (comme `guards-fast.yml` l'a fait pour dix).
- [ ] Un budget de workflows est fixé et une garde empêche d'en ajouter un sans justification.
- [ ] Un test vérifie qu'aucun check **requis** ne dépend d'un job `needs:` susceptible de ne jamais obtenir de runner (ou que le job amont n'a pas de filtre `paths:`).

## Références

`.github/workflows/`, `guards-fast.yml` (en-tête), `docs/infra/02_alignement/CI_SATURATION.md`, #9827, #6928.
""",
    },
    {
        "title": "[AUDIT-ARCH-07][MAJEUR] Aucune revue obligatoire au merge : un seul mainteneur peut tout fusionner",
        "labels": ["governance", "security", "major"],
        "body": """## Constat

La protection de `main` a `required_approving_review_count: 0`. `enforce_admins: true` et 6 checks requis protègent contre le merge **accidentel**, mais pas contre un merge **délibéré** d'un changement dangereux.

Le dépôt documente ce choix comme une roadmap (« à activer quand l'équipe dépasse 2 développeurs actifs »). C'est honnête. Mais l'audit doit le nommer : sur un produit qui manipule de la **paie**, des **données de santé** (absences) et de la **facturation**, l'absence de seconde paire d'yeux sur une PR est un risque de sécurité, pas seulement de qualité.

Aggravant : `dismiss_stale_reviews: true` est configuré mais sans effet tant que le compte d'approbations requis est 0.

## Pourquoi c'est majeur

Un compte compromis (le token de cette session en est la démonstration : il a un accès `admin` complet) peut pousser du code sur `main` sans qu'aucun humain ne l'ait lu. Les checks CI ne détectent pas une backdoor volontairement écrite pour passer les checks.

## Critères d'acceptation

- [ ] `required_approving_review_count: 1` est activé, **au minimum** pour les chemins sensibles (`api/app/Core/Auth/**`, `api/app/Modules/Payroll/**`, `api/app/Modules/Billing/**`, `api/database/migrations/**`, `.github/workflows/**`) via un ruleset GitHub.
- [ ] `CODEOWNERS` déclare un propriétaire par surface sensible.
- [ ] Les tokens à privilège `admin` sont à durée de vie courte et révocables (le token de cette session doit être révoqué).
- [ ] La roadmap de `.github/BRANCH_PROTECTION_REQUIRED.md` est mise à jour avec la décision et sa date.

## Références

`.github/BRANCH_PROTECTION_REQUIRED.md` (§Roadmap), API `/branches/main/protection`.
""",
    },
    {
        "title": "[AUDIT-ARCH-08][MAJEUR] 1 918 fichiers Markdown pour 452k lignes de code : la documentation est devenue un second produit à maintenir",
        "labels": ["documentation", "maintainability", "major"],
        "body": """## Constat

Le dépôt contient **1 918 fichiers `.md`** versionnés, pour ~452 000 lignes de PHP. Le ratio est d'environ **1 document pour 235 lignes de code**.

Une partie est excellente et rare : ADR numérotés, `docs/REFERENTIEL_PRODUIT/`, runbooks, `ARCHITECTURE.md`, gardes de parité doc↔code (`check-architecture-docs-parity.sh` valide 42 modules documentés ×4). C'est un vrai point fort.

Mais le volume crée un problème mesurable : **la documentation devient elle-même une source de vérité concurrente**. L'audit en a trouvé la preuve — `.github/BRANCH_PROTECTION_REQUIRED.md` et le canonique machine déclaraient 4 checks requis alors que la réalité en avait 6, et la garde était rouge en permanence. La doc n'était pas fausse par négligence : elle était fausse parce qu'il y a trop de documents pour qu'un humain les tienne tous à jour.

## Pourquoi c'est majeur

Une documentation qui ment est pire que pas de documentation : elle fait prendre de mauvaises décisions (ici, « rejouer le canonique » aurait supprimé deux checks délibérés). Et chaque document est un coût de maintenance qui n'apparaît dans aucun budget.

## Critères d'acceptation

- [ ] Un inventaire classe les 1 918 `.md` en : (a) vivant et vérifié par une garde, (b) vivant non vérifié, (c) historique/archivé, (d) obsolète à supprimer.
- [ ] Les documents de catégorie (c) sont déplacés dans `docs/archive/` avec une bannière « non maintenu ».
- [ ] Les documents de catégorie (d) sont supprimés (avec l'accord du PM).
- [ ] Toute affirmation **machine-vérifiable** dans un document (liste de checks, liste de variables, liste de modules) est soit générée depuis le code, soit couverte par une garde de parité.

## Références

`docs/`, `dev-hub/tools/check-docs-hygiene.sh`, `check-architecture-docs-parity.sh`, #7843.
""",
    },
    {
        "title": "[AUDIT-ARCH-09][MINEUR] 44 variables documentées dans .env.example ne sont lues par aucun fichier de configuration",
        "labels": ["configuration", "maintainability", "minor"],
        "body": """## Constat

Comparaison automatique de `api/.env.example` (568 variables) avec les `env()` réellement présents dans `api/config/*.php` (524 variables) :

- **0** variable utilisée mais non documentée (bon point) ;
- **44** variables documentées mais **jamais lues** par la configuration.

Parmi elles, plusieurs sont des **réglages de sécurité ou de comportement** qu'un opérateur croit pouvoir piloter :

```
SUPER_ADMIN_PASSWORD            (lue par le seeder, pas par config/)
FORCE_SUPER_ADMIN_PASSWORD_RESET
DEPLOY_TIER
DISABLE_DEMO_SEEDING
CORS_ALLOWED_ORIGINS            (le code lit CORS_EXTRA_ORIGIN)
RATE_LIMIT_PLAN_STARTER_PER_MINUTE
RATE_LIMIT_PLAN_PROFESSIONAL_PER_MINUTE
RATE_LIMIT_PLAN_BUSINESS_PER_MINUTE
RATE_LIMIT_PLAN_TRIAL_PER_MINUTE
RATE_LIMIT_PLAN_PRO_PER_MINUTE
RATE_LIMIT_API
BACKUP_PATH / BACKUP_KEEP_LAST
DOMPDF_ENABLE_REMOTE / DOMPDF_PAPER_SIZE
RESTAURANT_STOCK_INSUFFICIENT_POLICY
```

Le cas `RATE_LIMIT_PLAN_*` est le plus parlant : `PlanCode` ne connaît que `free`, `pilot`, `operations`, `enterprise`. Les cinq variables `starter`/`professional`/`business`/`trial`/`pro` documentent des plans **qui n'existent pas** — un opérateur qui les règle ne change rien.

## Pourquoi c'est mineur mais réel

Ce n'est pas une faille, c'est une **promesse non tenue**. Un opérateur qui pose `RATE_LIMIT_PLAN_BUSINESS_PER_MINUTE=5000` croit avoir augmenté un quota ; il n'a rien fait. Le même mécanisme, appliqué à `DISABLE_DEMO_SEEDING`, était un défaut critique (traité dans le commit `fix(security)` de cette branche).

## Critères d'acceptation

- [ ] Chaque variable orpheline est soit **supprimée** de `.env.example`, soit **branchée** dans un fichier `config/`.
- [ ] Les cinq `RATE_LIMIT_PLAN_*` de plans inexistants sont supprimées (ou les plans sont ajoutés à `PlanCode` — décision produit).
- [ ] Une garde CI compare `.env.example` aux `env()` de `config/` et échoue sur toute variable documentée non lue (allow-list justifiée pour les variables lues hors `config/`, ex. `docker-entrypoint.sh`).

## Références

`api/.env.example`, `api/config/`, `api/app/Modules/Billing/Domain/Enums/PlanCode.php`.
""",
    },
    {
        "title": "[AUDIT-ARCH-10][MINEUR] Aucun SLO défini : l'observabilité mesure des ressources, pas l'expérience utilisateur",
        "labels": ["observability", "reliability", "minor"],
        "body": """## Constat

L'observabilité est réelle : Sentry est configuré (API et worker), `HealthProbeService` expose `/api/v1/health`, `restaurant_observability.php` existe, et `queue-supervision.yml` sonde la profondeur de queue.

**Mais aucun SLO n'est défini.** Aucun document ne dit : « la latence p95 de l'API doit rester sous X ms », « la disponibilité doit être 99,X % », « le taux d'erreur 5xx doit rester sous Y % ». Sans SLO :

- on ne peut pas dire si le système est **sain** ou seulement **vivant** ;
- les alertes portent sur des ressources (CPU, queue depth) et non sur des symptômes utilisateur ;
- l'objectif « API response times under 200ms p95 » du référentiel d'architecture n'est mesuré nulle part.

## Pourquoi c'est mineur

Le système fonctionne. Mais sans SLO, la première dégradation sérieuse sera découverte par un client, pas par une alerte. Et il est impossible de prioriser la dette de performance sans mesure de référence.

## Critères d'acceptation

- [ ] 3 SLO sont écrits et versionnés : disponibilité API, latence p95 des routes critiques (auth, pointage, paie), fraîcheur du scheduler.
- [ ] Chaque SLO a un budget d'erreur et une alerte associée sur le **symptôme** (taux de 5xx, p95), pas sur la ressource.
- [ ] Un tableau de bord expose ces 3 SLO.
- [ ] `docs/architecture/OBSERVABILITY.md` référence les SLO et leur emplacement.

## Références

`api/config/sentry.php`, `api/app/Modules/Platform/Infrastructure/Services/HealthProbeService.php`, `docs/architecture/OBSERVABILITY.md`, `.github/workflows/queue-supervision.yml`.
""",
    },
]


def main():
    existing = set()
    page = 1
    while True:
        try:
            batch = call("GET", f"/issues?state=all&per_page=100&page={page}")
        except Exception:
            # GitHub renvoie 422 au-delà de la dernière page.
            break
        if not batch:
            break
        for i in batch:
            existing.add(i["title"])
        if len(batch) < 100:
            break
        page += 1

    created = []
    for issue in ISSUES:
        if issue["title"] in existing:
            print(f"SKIP (existe) : {issue['title'][:70]}")
            continue
        try:
            r = call("POST", "/issues", issue)
            created.append((r["number"], r["html_url"], issue["title"]))
            print(f"OK #{r['number']} : {issue['title'][:70]}")
        except Exception as e:
            print(f"FAIL : {issue['title'][:60]} -> {e}")

    print("\n=== CREATED ===")
    for n, url, t in created:
        print(f"#{n}\t{url}\t{t}")


if __name__ == "__main__":
    main()
