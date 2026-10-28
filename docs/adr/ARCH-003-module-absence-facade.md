# ARCH-003 — Le module `Absence` est une façade de présentation, pas un domaine

- **Statut** : Accepté
- **Date** : 2026-10-28
- **Issue** : #7959 (point 2 — « Trancher le module Absence »)

## Contexte

L'audit structurel du 2026-09-20 relevait que `api/app/Modules/Absence/` est « creux » : ni `Application/`, ni `Infrastructure/`, ni `Domain/Models`. Son `AbsenceController` importe directement les Actions de `Modules/Planning` (`CreateAbsence`, `UpdateAbsence`, `ApproveAbsence`, `RejectAbsence`, `CancelAbsence`).

Simultanément, `dev-hub/governance/bounded-context-registry.json` déclare :

| Code | Contexte | Chemins |
|---|---|---|
| `BC-05` WORKFORCE | Attendance, Planning & Workforce | `api/app/Modules/Attendance`, `api/app/Modules/Planning` |
| `BC-06` LEAVE | Leave & Absence | `api/app/Modules/Absence` |

Le registre décrit donc `Absence` comme **frontière de contexte**, alors que le code y place le domaine dans `Planning`. C'est cette incohérence — plus que la taille du module — qui est le défaut.

## Décision

**`Absence` reste un module de présentation (façade) pour BC-06 LEAVE, dont le domaine vit dans `Planning` (BC-05).**

Plus précisément :

1. `api/app/Modules/Absence/` conserve uniquement : contrôleur HTTP, FormRequests, catalogue de support (`AbsenceDecisionToolCatalog`) et `ServiceProvider`.
2. Le domaine (`Domain/Models/Absence.php`, `AbsenceType.php`), les Actions et les événements restent dans `Planning`.
3. La dépendance `Absence → Planning` est **une arête inter-contexte autorisée, versionnée et intentionnelle** (`BC-06 → BC-05`, déjà déclarée dans `bounded-context-dependencies.json`), et non une violation d'isolation.
4. Un garde CI (`dev-hub/tools/check-absence-facade.sh`) fige cette décision : la façade ne peut pas grossir. Toute tentative d'ajouter `Application/`, `Domain/Models/` ou `Infrastructure/` sous `Modules/Absence/` fait échouer la CI avec un renvoi vers cet ADR.
5. Le registre est corrigé pour refléter la réalité : `BC-06` déclare `api/app/Modules/Absence` en rôle de façade et référence `Planning` comme porteur du domaine.

## Alternatives écartées

| Alternative | Pourquoi non |
|---|---|
| **Rapatrier le domaine dans `Absence`** | Coût élevé et risque de régression réel : `Absence`/`AbsenceType` sont des modèles persistés (tables tenant) consommés par `Planning`, `Payroll`, `Attendance` et le calendrier. Un déplacement de namespace impose une réécriture des `use` sur plusieurs modules, des fixtures de test, et des références dans les policies et seeds. Le gain est cosmétique : la frontière serait respectée « sur le papier » sans réduire le couplage réel. |
| **Supprimer le module `Absence` et router directement vers `Planning`** | Casserait les URLs `/api/v1/absences`, les FormRequests dédiés (`StoreAbsenceRequest`, `RejectAbsenceRequest`, `AbsenceIndexRequest` — validation métier non triviale) et la séparation produit « congés » vs « planning ». Coût client non justifié. |
| **Laisser tel quel sans garde** | C'est l'état actuel : rien n'empêche la façade de se remplir d'un domaine parallèle, ce qui recréerait la duplication que l'audit dénonce. |

## Conséquences

- **Positif** : la décision est explicite et machine-vérifiée ; plus d'ambiguïté sur « où vit Absence » ; aucun risque de régression sur les données.
- **Négatif** : le domaine reste physiquement dans `Planning`, donc la navigation de code pour un développeur « congés » demande un détour. C'est documenté ici et dans le registre.
- **Réversible** : si un jour `Leave` devient un vrai module (équipes séparées, cycle de release propre), le déplacement reste possible ; il faudra alors retirer le garde dans le même commit.

## Vérification

```bash
dev-hub/tools/check-absence-facade.sh                    # doit sortir 0
dev-hub/tools/check-bounded-context-dependencies.sh .    # doit sortir 0
dev-hub/tools/check-module-isolation.sh                  # doit sortir 0
```
