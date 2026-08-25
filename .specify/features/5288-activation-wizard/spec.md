# Spec — Wizard d'activation du module Comptabilité (issue #5288)

- **Module** : `accounting` — périmètre : `api/app/Modules/Accounting/**` + routes module + `front/admin-dashboard` (vue wizard + checklist) + OpenAPI/CHANGELOG.
- **Source** : `docs/architecture/COMPTABILITE_CONCEPTION.md` §5 (endpoints, RBAC) + issue #5288.
- **Statut** : à implémenter — branche `mod/accounting/5288-activation-wizard`.

## 1. Objectif

Une entreprise vide doit pouvoir démarrer le module Comptabilité **sans
documentation, en moins de 10 minutes** : paramétrage initial guidé (identité,
devise, TVA par défaut, séries de numérotation, mentions légales, langue des
documents) puis **premier document de démonstration jetable** en quelques clics.

## 2. Contrat API

RBAC : `api.manager:comptable,principal` (même garde que `/accounting/settings`,
matrice §5). Isolation tenant : résolution par compagnie courante de la requête
(comme `AccountingSettingsController`) + `BelongsToCompany` (fail-closed #3727).

### `GET /api/v1/accounting/activation`

Retourne l'état d'activation du tenant courant (aucune écriture) :

```json
{
  "data": {
    "completed": false,
    "steps": {
      "settings": true,
      "contact": false,
      "example_invoice": false
    },
    "contact": null,
    "example_invoice": null
  }
}
```

- `steps.settings` : ligne `AccountingSettings` persistée **et** complète
  (devise + langue + ≥ 1 taux TVA + mentions non vides ou séries configurées) ;
- `steps.contact` : contact de démonstration présent (`metadata.is_sample=true`) ;
- `steps.example_invoice` : facture de démonstration présente
  (`metadata.is_example=true`) ;
- `completed` : les trois étapes sont vraies.
- `contact` / `example_invoice` : aperçus (id, name/number, statut) quand ils
  existent, sinon `null`.

### `POST /api/v1/accounting/activation`

Idempotent — exécute l'activation complète :

1. **Settings** : upsert de la ligne `AccountingSettings` avec le payload
   optionnel (mêmes règles de validation que `PUT /accounting/settings`,
   #5232) ; en l'absence de champs, les défauts pays sont persistés
   (`AccountingSettingsDefaults`).
2. **Contact de test** : crée un contact `customer` de démonstration
   (`metadata.is_sample=true`, nom localisé selon `document_language`) si aucun
   n'existe déjà.
3. **Facture d'exemple** : crée une facture `invoice` **jetable**
   (`metadata.is_example=true`, `notes` « DOCUMENT EXEMPLE », statut `draft`,
   2 lignes, numérotation concurrent-safe via `SequentialDocumentNumbering`) liée
   au contact de test, si aucune n'existe déjà.

Retour : le même statut que `GET`, plus `contact` et `example_invoice` créés
(ou existants — jamais de doublon, idempotence garantie par les marqueurs
`metadata`).

Réponse 422 : validation settings (mêmes codes que #5232).

## 3. Implémentation backend

- `Application/Actions/AccountingActivationService.php` : orchestration
  (statut, complétion, création contact/facture démo) ;
- `Interfaces/Api/V1/AccountingActivationController.php` : `show` + `complete` ;
- `Interfaces/Api/V1/Requests/CompleteActivationRequest.php` : réutilise les
  règles de `UpdateAccountingSettingsRequest` (héritage) ;
- `routes/modules/accounting.php` : 2 routes sous le groupe
  `api.manager:comptable,principal`.

Règles de données de démonstration :
- nom du contact localisé par `document_language` (fr/en/tr/ar) — défaut « fr » ;
- lignes de la facture démo : libellé localisé + montant fixe simple (calcul
  HT/TVA/TTC via le taux TVA par défaut) — données jetables, jamais utilisées
  dans des calculs métier (marquées `metadata.is_example`).

## 4. UI — wizard `/accounting/activation` (admin dashboard)

- Étapes guidées (issues #5288) : 1. Identité & langue → 2. TVA & séries →
  3. Modèle PDF & mentions → 4. Contact de test + facture d'exemple.
- Récupère `GET /accounting/activation` au montage : si `completed`, affiche
  l'état final + boutons « tout réinitialiser » (non — hors périmètre) ; sinon
  wizard.
- Soumission : `POST /accounting/activation` → toast succès + état final.
- **Check-list d'activation** : carte compacte affichée dans
  `AccountingSettingsView` tant que `completed=false` (lien vers le wizard) ;
  entrée sidebar « Démarrer la comptabilité » (comptable/principal).
- i18n ×4 : clés `accounting.activation.*` + `navigation.accountingActivation`
  (parité fr/en/tr/ar, 0 chaîne hardcodée — règle #2755).

## 5. Tests (Feature, backend)

`AccountingActivationTest` (module accounting, gate coverage ≥ 70 % #5228) :

1. GET retourne `completed=false` + étapes à faux sur entreprise vide ;
2. POST complète l'activation (settings persistés, contact sample créé,
   facture EXEMPLE créée, `completed=true`) ;
3. Idempotence : un second POST ne crée pas de doublon (1 contact, 1 facture) ;
4. Numérotation : la facture EXEMPLE porte un numéro de série (FAC-…) et le
   compteur est incrémenté ;
5. RBAC : employé ordinaire et `marketing` → 403 ; comptable/principal → 200 ;
6. Isolation tenant : l'activation de l'entreprise B n'est pas visible dans A
   (404/état vide) et ne crée rien chez A ;
7. Validation : payload settings invalide → 422 (ex. devise inconnue) ;
8. Facture démo : marquée `metadata.is_example=true`, statut `draft`, lignes
   cohérentes (subtotal HT + TVA = total TTC).

## 6. DoD

- [ ] Une entreprise vide active le module en < 10 min (recette pilote)
- [ ] Wizard testé (Feature) ; 0 chaîne hardcodée (scan CI) ; parité i18n ×4
- [ ] CI verte (phpstan strict, pint, gate accounting ≥ 70 %, openapi-check)
- [ ] CHANGELOG en tête d'[Unreleased] ; `Closes #5288` dans le body de la PR
