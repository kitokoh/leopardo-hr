# Scope pays, paie et pointage

Version: 1.0  
Date: 2026-06-13

## Objectif

Ce document fixe le scope fonctionnel attendu pour que Leopardo adapte automatiquement la langue, la devise, les regles de pointage et les calculs de paie selon le pays choisi lors de la creation du compte entreprise.

La source technique doit rester cote backend. Les clients web/mobile/kiosk consomment les defaults et les regles exposes par API au lieu de coder `DZD`, `Africa/Algiers` ou des seuils horaires en dur.

## Pays et zones a couvrir

| Code | Pays / zone | Devise principale | Timezone par defaut | Remarque |
|---|---|---|---|---|
| DZ | Algerie | DZD | Africa/Algiers | Base actuelle, doit rester compatible |
| MA | Maroc | MAD | Africa/Casablanca | Jour ferie et horaires locaux |
| TN | Tunisie | TND | Africa/Tunis | Regles proches Maghreb, devise distincte |
| FR | France | EUR | Europe/Paris | Conges, jours feries, overtime prudent |
| TR | Turquie | TRY | Europe/Istanbul | Langue TR et devise TRY |
| CEMAC | Zone CEMAC | XAF | Africa/Douala | CM, CF, TD, CG, GA, GQ; pays precis garde en sous-code |
| CEDEAO | Zone CEDEAO | XOF par defaut | Africa/Abidjan | Support d'abord pays XOF; NG/GH/CV/GM/GN/LR/SL en extension |
| CA | Canada | CAD | America/Toronto | Province obligatoire a terme pour regles fines |

## Regles minimales par pays

Chaque pays doit exposer au moins:

- langue par defaut et langues recommandees;
- devise et format monetaire;
- timezone par defaut et timezones possibles;
- jours de repos par defaut;
- frequence de paie par defaut;
- seuil journalier et hebdomadaire d'heures supplementaires;
- pause minimale configurable;
- jours feries source ou placeholder documente;
- cycle de paie autorise: journalier, hebdomadaire, mensuel;
- politique d'arrondi temps/payroll;
- niveau de confiance du modele: `production`, `pilot`, `placeholder`.

## Contrat produit

Lors de la creation entreprise:

1. L'utilisateur choisit pays, langue et ville.
2. Le backend derive devise, timezone, modele RH, jours feries et regles de paie.
3. Le platform admin web/mobile affiche ces defaults avant validation.
4. Les apps employee/manager affichent les montants dans la devise tenant.
5. Les calculs pointage, avance, solde et paie utilisent les regles tenant.

## Definition of Done technique

- `CountryDefaults` couvre tous les pays/zones ci-dessus.
- `GET /api/v1/platform/country-defaults` expose les metadonnees necessaires aux trois apps mobiles et aux deux apps web.
- OpenAPI documente pays, devise, timezone, langues et limites connues.
- La matrice frontend/API prouve les consommateurs.
- Aucun montant runtime critique n'affiche `DZD` sans devise fournie par API.
- Les tests couvrent au minimum DZ, FR, TR, CEMAC et CEDEAO.

