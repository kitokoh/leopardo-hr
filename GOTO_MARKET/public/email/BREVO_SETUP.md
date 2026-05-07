# Brevo Setup - Acquisition et Pilote

## Listes

- `Prospects - GTM`
- `Leads - Checklist Pointage`
- `Demos - Planifiees`
- `Pilotes - 14 jours`
- `Clients - Payants`

## Attributs contact

```text
FIRSTNAME
LASTNAME
COMPANY
COUNTRY
LANGUAGE
SEGMENT
EMPLOYEE_COUNT
SOURCE
STATUS
PILOT_START_DATE
PILOT_END_DATE
PLAN_INTEREST
```

## Automations

### Lead magnet

Trigger : formulaire checklist.

Action :

- envoyer checklist ;
- attendre 1 jour ;
- proposer demo ;
- si clic demo, tag `demo_intent`.

### Pilote 14 jours

Trigger : statut `pilot_started`.

Action :

- J0 bienvenue ;
- J1 premier pointage ;
- J3 premiers signaux ;
- J7 milieu pilote ;
- J10 rapport ;
- J13 bilan ;
- J14 conversion ;
- J17 question ouverte.

## Regle

Ne pas automatiser a l'aveugle.

Toute sequence doit ramener vers une conversation humaine si le prospect repond, clique plusieurs fois ou demande une demo.
