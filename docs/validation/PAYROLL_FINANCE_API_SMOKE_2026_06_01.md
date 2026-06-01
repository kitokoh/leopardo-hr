# Smoke API paie / finance - 2026-06-01

## Contexte

Validation Render du lot 69.5 apres corrections #686, #688 et #689.

- API : `https://gestionemployerbackend.onrender.com/api/v1`
- Manager demo : `ahmed.benali@techcorp-algerie.dz`
- Employe demo : `karim.aouad@techcorp-algerie.dz`
- Branche source : `main`

## Resultats

| Parcours | Resultat |
|---|---|
| Creation avance employe | OK - avance #6 creee en `pending` |
| Validation manager avance | OK - `validation_status=manager_approved` |
| Declaration paiement avance | OK - `validation_status=payment_declared` |
| Confirmation reception employe | OK - `validation_status=employee_confirmed` |
| Recu avance asynchrone | OK - document `advance_receipt` disponible |
| Creation structure salariale demo | OK - structure #1 creee pour permettre le calcul de paie demo |
| Creation / calcul / validation payroll run | OK - run #2 valide, 11 bulletins generes |
| Creation payment batch | OK - batch #1 cree avec 11 items |
| Marquage paiement masse | OK - batch `paid`, documents generes en arriere-plan |
| Confirmation employe payment item | OK - item #10 confirme par Karim |
| Resume paie manager mobile | OK - `GET /payroll/mobile-summary`, 12 lignes, total restant `1125679` |
| Solde employe par manager | OK - `GET /employees/1/balance`, restant `180000` |

## Corrections livrees

- #686 : selection schema-aware des colonnes employees dans le resume paie mobile.
- #688 : reutilisation de `currentCompany()` pour eviter les erreurs `search_path` sur les soldes paie.
- #689 : alignement du parametre route `{employee}` et fallback partiel par employe dans le resume mobile.

## Decision

Verdict : **Go pour le lot 69.5**.

Le socle finance mobile-first couvre maintenant les cas critiques attendus avant lancement : avance double validation, paiement declare, confirmation employe, paiement masse, documents PDF asynchrones, resume manager et solde employe. Les donnees creees pendant le smoke restent des traces QA demo sur TechCorp.
