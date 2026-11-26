# SLO — objectifs de niveau de service

**Statut** : adopté · **Date** : 2026-11-26 · **Origine** : audit architecture 2026-11-26 (issue #9983)
**Portée** : API Leopardo (tier prod), scheduler, worker de queue.

---

## 1. Pourquoi ce document existe

`docs/architecture/OBSERVABILITY.md` décrit une observabilité **réelle** : Sentry
branché, `request_id` propagé, redaction PII, `monitor:slow-queries`, sondes
`launch-observability-smoke`, endpoint `GET /api/v1/platform/observability/queues`.

Mais il fixe des **cibles par bounded context** (« p95 < 200 ms », « 5xx > 0.1 %
→ alerte ») sans jamais définir de **SLO** : un engagement mesuré, avec un budget
d'erreur et une conséquence quand il est consommé.

Sans SLO :

- on ne peut pas distinguer un système **sain** d'un système **vivant** ;
- les alertes portent sur des ressources (CPU, profondeur de queue) et non sur
  des **symptômes utilisateur** ;
- la dette de performance ne peut pas être priorisée : il n'existe aucune
  mesure de référence à laquelle la comparer.

Ce document comble ce trou. Il ne remplace pas `OBSERVABILITY.md` : il en est
la couche « engagement ».

---

## 2. Les trois SLO

### SLO-1 — Disponibilité de l'API

| Élément | Valeur |
|---|---|
| **Indicateur (SLI)** | Proportion de requêtes API non-5xx sur une fenêtre glissante de 30 jours |
| **Objectif** | **99,5 %** sur 30 jours |
| **Budget d'erreur** | 0,5 % ≈ **3 h 39 min** d'indisponibilité par mois |
| **Source de mesure** | Logs structurés (`status`, `duration_ms`) + Sentry |
| **Alerte** | Budget consommé à 50 % → ticket ; à 100 % → gel des features non critiques |
| **Exclusions** | 4xx (erreur client), maintenance planifiée annoncée 48 h avant |

**Pourquoi 99,5 % et pas 99,9 %** : le tier d'hébergement actuel (Render `free`,
spin-down documenté dans `render.yaml`) ne permet pas structurellement 99,9 %.
Annoncer 99,9 % serait un engagement non tenable. 99,5 % est atteignable et
**vérifiable** ; l'objectif monte quand le worker dédié et le plan payant seront
provisionnés (issue #9976).

### SLO-2 — Latence des parcours critiques

| Élément | Valeur |
|---|---|
| **Indicateur (SLI)** | p95 de la durée serveur sur les routes critiques |
| **Objectif** | **p95 < 400 ms**, **p99 < 1 200 ms** |
| **Routes couvertes** | `POST /api/v1/auth/login`, `POST /api/v1/attendance/check-in`, `GET /api/v1/employees`, `POST /api/v1/payroll/runs`, `GET /api/v1/health` |
| **Fenêtre** | 7 jours glissants |
| **Source de mesure** | `duration_ms` des logs structurés, agrégé par route |
| **Alerte** | p95 > 400 ms pendant 15 min → alerte ops |

**Pourquoi 400 ms et pas 200 ms** : la cible « < 200 ms » de
`OBSERVABILITY.md` est un **objectif d'ingénierie** par endpoint, pas une mesure
agrégée. Un p95 agrégé à 200 ms sur des routes incluant la paie (calcul lourd,
multi-pays) n'est pas atteignable aujourd'hui. 400 ms est la valeur mesurable
actuelle à ne pas dégrader ; 200 ms reste la cible par endpoint dans les fiches
BC.

### SLO-3 — Fraîcheur du scheduler

| Élément | Valeur |
|---|---|
| **Indicateur (SLI)** | Âge du dernier run réussi de `schedule:run` |
| **Objectif** | **< 2× l'intervalle attendu** (soit < 2 min pour un scheduler à la minute) |
| **Fenêtre** | Instantané, vérifié toutes les 5 min |
| **Source de mesure** | Table `scheduler_runs` (ou log `schedule:run` horodaté) |
| **Alerte** | Âge > 2× l'intervalle → alerte **critique** (relances, facturation et alertes carburant ne partent plus) |

**Pourquoi ce SLO est le plus important** : le scheduler tourne aujourd'hui
**dans le conteneur web** (`WEB_SCHEDULER_LOOP`, `render.yaml`), donc il s'arrête
au spin-down. Une paie non calculée ou une facture non émise est un défaut
fonctionnel **silencieux**, découvert par le client. C'est le seul SLO dont la
violation a un coût commercial direct.

---

## 3. Budget d'erreur — la règle de décision

Un budget d'erreur n'est utile que s'il **change une décision**. La règle :

| Budget consommé | Décision |
|---|---|
| < 50 % | Rythme normal : features et dette au choix de l'équipe |
| 50 – 100 % | **Priorité à la fiabilité** : toute PR qui touche un chemin critique exige un test de non-régression |
| > 100 % (SLO violé) | **Gel des features non critiques** jusqu'au retour sous le budget ; post-mortem obligatoire |

Cette règle s'applique par SLO, indépendamment.

---

## 4. Ce qui n'est PAS couvert (et pourquoi)

| Non couvert | Raison | Suivi |
|---|---|---|
| Disponibilité des apps mobiles | Dépend des stores, pas de notre infra | — |
| Latence des endpoints publics du catalogue | Cache 600 s, mesure non représentative | #6882 |
| SLO de la vitrine et du marketplace | Déploiements Vercel/Cloudflare séparés | — |
| SLO du kiosque ZKTeco | Dépend du réseau du client | — |

---

## 5. Mise en œuvre — état réel

| Élément | État au 2026-11-26 |
|---|---|
| Définition des 3 SLO | ✅ ce document |
| Mesure de SLO-1 et SLO-2 | ⚠️ **partielle** : les données existent (`duration_ms`, `status` dans les logs structurés) mais aucun agrégateur ne calcule le ratio sur 30 j |
| Mesure de SLO-3 | ❌ **absente** : aucun enregistrement horodaté du dernier run de scheduler |
| Tableau de bord | ❌ absent |
| Alertes sur symptômes | ⚠️ partielle : `queue-supervision.yml` couvre la queue, pas la latence ni la disponibilité |

**Prochaine étape concrète** : un job planifié qui agrège les logs structurés
en (a) ratio non-5xx sur 30 j, (b) p95/p99 par route critique, et écrit le
résultat dans une table `slo_snapshots`. Le tableau de bord lit cette table.
Tant que ce job n'existe pas, ces SLO sont **déclarés mais non mesurés** — et
c'est écrit ici pour que personne ne les croie surveillés.

---

## 6. Références

- `docs/architecture/OBSERVABILITY.md` — la couche instrumentation
- `api/app/Modules/Platform/Infrastructure/Services/HealthProbeService.php`
- `api/config/sentry.php`
- `.github/workflows/queue-supervision.yml`
- Issue #9983 (audit architecture 2026-11-26)
