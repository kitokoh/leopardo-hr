# Session d'implémentation B — 2026-10-28 (soir)

> **Suite de** `DECISIONS_2026-10-28_MISSION_ISSUES.md` (session A, même journée).
> La session A a livré le socle et documenté ce qu'elle refusait de faire. Cette
> session B reprend l'état réel de `main`, rouvre les PR restées bloquées, et
> ajoute trois décisions que la session A n'avait pas eu à prendre.
>
> Même politique : on ne ferme **jamais** une issue en la déclarant implémentée
> quand le blocage n'est pas dans le dépôt (D6), et « livré dans le code » n'est
> pas « livré en prod » (D7).

---

## D8 — L'ordre des merges est un problème de dépendance, pas de priorité

**Contexte.** Neuf PR étaient ouvertes et **toutes** apparaissaient `BLOCKED`, y
compris celles qui ne touchaient ni `travel` ni OpenAPI. Le réflexe est de
merger « la plus urgente d'abord ».

Le check `Route → OpenAPI coverage guard` n'est **pas différentiel** : il compare
l'intégralité des routes de la branche à `api/openapi.yaml`
(`dev-hub/tools/check-openapi-route-coverage.py`, sans étape `git diff`). Une
branche qui ne contient pas le correctif de #8975 échoue donc **à cause d'un
défaut de `main`**, quel que soit son propre contenu.

**Décision.** Traiter **#8986 en premier**. Mesure locale sur sa branche :

```
NOUVELLES non couvertes (drift): 0
exit 0
```

Tant qu'il n'est pas mergé, chaque PR ouverte paie la dette de `main` et la file
CI se remplit de rouges qui ne veulent rien dire. Ce n'est pas une priorisation
par urgence : c'est une **fermeture de dépendance**.

**Preuve.** Avant merge de #8986 : 9 PR `BLOCKED`, garde OpenAPI `FAILURE` sur
#8981, #8983, #8984, #8987, #8988. Après : `SUCCESS` sur #8986 (run
`40717208907`).

---

## D9 — Une allowlist de dette sans plafond est un stock qui ne descend jamais

**Contexte.** Le point 3 de #7959 demande une règle CI « qui bloque tout nouvel
import cross-module ». `check-module-isolation.sh` (#5584, étendu à `app/Http`
par #8059) **le fait déjà** : toute arête absente de l'allowlist fait échouer la
PR. Le besoin exprimé était donc satisfait ; le réimplémenter aurait produit un
second garde pour la même règle.

Ce qui manquait est ailleurs : l'allowlist est un **stock** (46 arêtes dans
`module-isolation-allowlist.txt`, 24 dans `module-isolation-http-allowlist.txt`)
et **aucune mécanique n'obligeait ce stock à baisser**. Pire : retirer une arête
puis en ajouter une autre dans la même PR laissait le total inchangé — donc
vert.

**Décision.** Ajouter un **budget décroissant** (`check-module-isolation-budget.sh`) :

| Situation | Verdict |
|---|---|
| dette **>** budget | échec — arête ajoutée sans déclaration |
| dette **<** budget | échec — arête résorbée : abaisser le budget dans le **même commit** |
| dette **=** budget | vert |

La seconde ligne est la décision : un nettoyage doit devenir un **gain
verrouillé**, sinon la dette stagne à son niveau historique en donnant
l'illusion d'être sous contrôle.

**Alternative écartée.** Introduire Deptrac ou une règle PHPStan
d'architecture : cela ajoute une dépendance d'analyse statique pour mesurer un
compteur que deux `grep -c` suffisent à produire. 73 lignes de bash, zéro
dépendance, exécution en millisecondes.

**Preuve.** `bash dev-hub/tools/check-module-isolation-budget.sh` →
`46/46`, `24/24`, exit 0.

---

## D10 — Le contrôleur qui délègue déjà n'est pas de la dette : ne pas le découper pour le plaisir

**Contexte.** #7962 liste cinq contrôleurs « obèses » et demande d'extraire leur
logique vers des Actions. En ouvrant `PayrollRunController` (637 lignes), le
constat est inverse de ce que l'issue suppose : **chaque action HTTP délègue déjà**
à une Action (`CalculatePayrollRun`, `ValidatePayrollRun`, `LockPayrollRun`,
`UnlockPayrollRun`, `CancelPayrollRun`…). Ses 637 lignes sont du **mapping
HTTP** : autorisation, garde de statut, traduction d'exceptions métier en
réponses localisées, journalisation.

**Décision.** Ne pas extraire. Le critère de #7962 n'est pas un compteur de
lignes, c'est « la logique métier vit-elle hors de l'interface ». Ici elle y est
déjà. Déplacer le mapping d'exceptions vers une Action créerait une couche qui
ne sait rien faire d'autre que rendre des réponses HTTP — c'est-à-dire un
contrôleur déguisé, deux fois plus difficile à suivre.

Extractions **retenues** dans cette session, parce que du métier y vivait
réellement :

| Contrôleur | Cas d'usage extrait | Métier déplacé |
|---|---|---|
| `AttendanceController` (818 → 761) | `RequestAttendanceCorrection` | verrou de période clôturée, cohérence entrée/sortie, storage du justificatif, audit |
| `SelfServiceTrialController` (859 → 836) | `ReportTrialProvisioningStatus` | lien d'accès seulement si `ready`, `provisioned_at` parsé depuis une chaîne (#2903), validation du token |

`PayrollRunController` et `AuthController` restent tels quels : leur taille est
celle de leur contrat HTTP.

**Preuve.** `CorrectionWorkflowTest` + `CorrectionWorkflowV2Test` → 10 passed ;
`TrialProvisioningStatusTest` + `TrialStatusThrottleTest` → 8 passed ; Pint et
PHPStan level 6 verts sur les 4 fichiers touchés.

---

## D11 — `Absence` n'a pas de couches à créer : l'ADR-0031 fait autorité

**Contexte.** Le brief de mission comme le point 2 de #7959 signalent
`api/app/Modules/Absence/` sans `Application/`, `Infrastructure/` ni
`Domain/Models`, et demandent de trancher.

**Décision.** **Rien à créer.** L'ADR-0031 (`docs/architecture/adr/`, statut
Accepté, mergée par #8973) tranche : `Absence` est une **façade HTTP assumée**,
le domaine congé appartient à `Planning`. Créer des couches vides pour satisfaire
une symétrie de dossiers produirait exactement le « faux module » que l'audit
dénonce.

Ce qui a été livré à la place est le garde qui empêche la situation de se
dégrader (D9). Une frontière documentée plus un plafond de dette vaut mieux que
des dossiers vides.

---

## État de `main` à la clôture de cette partie

Voir le tableau de bord de session remis séparément : il liste chaque issue, sa
branche, sa PR et son statut. Les points non fermables par du code restent
ouverts **avec leur motif** (D6), et les issues dont le code est livré mais dont
l'exécution dépend d'une console fournisseur restent ouvertes pour la même
raison (D7).
