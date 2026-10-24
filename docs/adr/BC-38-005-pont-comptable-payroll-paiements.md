# ADR BC-38-005 — Pont comptable Payroll ↔ Paiements

- **Statut** : accepté
- **BC** : BC-38 PAIEMENTS, BC-07 PAYROLL
- **Issues** : #8713 (écart P-3), #8714 (écart P-4)
- **Complète** : ADR BC-38-002 (ledger en partie double)

## Contexte

L'audit readiness du BC-38 a relevé **deux sources de vérité comptables** qui
coexistent sans pont documenté :

| | Payroll | BC-38 Paiements |
|---|---|---|
| Table | `ledger_entries` | `payment_ledger_entries` |
| Granularité | journal **employé** | partie double **stricte** |
| Montant | `decimal(12,2)` | `bigint` (plus petite unité) |
| Solde | `balance_after` **stocké** | solde **dérivé** |
| Unité de compte | la devise, en décimal | la plus petite unité entière |

Le risque nommé par l'audit : divergence de soldes entre paie et paiement,
réconciliation impossible, double comptage lors d'un cash-out de salaire.

## Décision

### 1. Les deux journaux restent **séparés par conception**

Ce ne sont pas deux implémentations concurrentes du même objet, mais deux
journaux qui répondent à deux questions différentes :

- `ledger_entries` répond à « **combien doit-on à cet employé, et pourquoi** ».
  Il porte un solde courant par employé, ce qui suppose une lecture séquentielle
  et un ordre stable — d'où le `balance_after` stocké.
- `payment_ledger_entries` répond à « **où est l'argent, et est-il équilibré** ».
  Il est en partie double stricte, immuable, et son solde est dérivé : c'est ce
  qui rend la réconciliation possible (ADR BC-38-002).

Fusionner les deux imposerait à la paie de porter une contrepartie de trésorerie
qu'elle ne connaît pas, et au paiement de porter une notion d'employé qui n'est
pas son domaine. **La séparation est assumée, pas subie.**

### 2. Le pont est **événementiel**, jamais un import de module

La garde d'isolation (`ARCHITECTURE.md` §2, #5584) interdit à `Modules/Payroll`
d'importer `Modules/Paiements` — et réciproquement. Le pont ne peut donc pas être
un service qui appelle les deux journaux.

Le pont est un **événement de domaine** : Payroll publie l'intention
(`payment.cash_out.requested` / `payment.cash_out.completed`, BC-38 #8712), et
le consommateur qui a besoin des deux vues les recompose. Aucun module ne
connaît l'autre ; chacun connaît le contrat partagé.

### 3. Politique de conversion `decimal(12,2)` ↔ `bigint` — **explicite et unique**

Le point exact où une erreur d'arrondi devient silencieuse est la frontière
entre les deux représentations. Il n'y a donc **qu'un seul convertisseur** :
`App\Support\Money` (#8714).

- **Sens** : `Money::fromDecimal()` va du monde décimal vers la plus petite
  unité ; `Money::toDecimal()` fait le retour. Aucune conversion implicite
  ailleurs dans le dépôt.
- **Arrondi** : **demi vers l'extérieur** (`half-up away from zero`), à
  l'exposant de la devise. `0.005` DZD → `1` centime ; `-0.005` → `-1`. Cet
  arrondi est **symétrique** : arrondir un débit et un crédit de même montant
  donne des valeurs opposées, donc la partie double reste équilibrée — un
  arrondi « demi vers le haut » asymétrique casserait l'invariant.
- **Exposant** : propriété de la **devise**, pas du montant. XOF/XAF n'ont pas
  de subdivision (exposant 0), TND en a trois (millimes), le reste suit ISO 4217
  (exposant 2). Une devise hors allowlist est **refusée**, jamais convertie
  « au mieux » — même posture que #8743.
- **Exactitude** : le calcul passe par `bcmath`, jamais par un `float`
  intermédiaire. Un `float` ne représente pas exactement `0.1`, et l'erreur se
  propage à chaque frontière d'intégration.

### 4. Cohérence vérifiée par un test d'intégration

Un cash-out de salaire doit produire des écritures **cohérentes dans les deux
journaux** : le montant enregistré côté paie (décimal) et le montant posté côté
paiement (plus petite unité) doivent être la **même valeur** convertie par
`Money`, et le journal de paiement doit rester équilibré. C'est l'objet de
`PayrollPaymentLedgerBridgeTest`.

## Conséquences

- Une divergence de montant entre les deux journaux devient **détectable** : les
  deux passent par le même convertisseur, donc un écart ne peut venir que d'un
  appelant qui contourne `Money` — et ce contournement est visible en revue.
- Le `balance_after` stocké côté paie reste une seconde source de vérité
  **locale** au journal employé ; il n'est pas réconcilié avec le ledger de
  paiement, et ne doit pas l'être (ce sont deux soldes différents : dû à
  l'employé vs. position de trésorerie).
- Coût : une conversion explicite à chaque frontière. Acceptable — c'est
  précisément là que l'audit voulait de la visibilité.

## Alternatives écartées

- **Fusionner les deux journaux** : rejetée — granularités et invariants
  incompatibles (solde stocké vs dérivé, employé vs trésorerie).
- **Un service pont dans `Modules/Paiements` appelé par Payroll** : rejetée —
  viole l'isolation des modules, refusée par la garde CI (#5584).
- **Conversion implicite par cast `(int) ($amount * 100)`** : rejetée — c'est
  exactement le défaut P-4 : arrondi non spécifié, exposant supposé à 2, et
  `float` intermédiaire.
- **Arrondi « demi vers le haut »** : rejetée — asymétrique sur les négatifs,
  donc susceptible de déséquilibrer une paire débit/crédit.
