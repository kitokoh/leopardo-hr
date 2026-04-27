# Leopardo RH - Modele CRM simple

## Objet

Ce modele CRM est pense pour suivre les beta testeurs, les prospects et les premiers clients payants sans complexite inutile.

Le fichier pret a utiliser se trouve ici:

- [LEOPARDO_RH_CRM_TEMPLATE.csv](LEOPARDO_RH_CRM_TEMPLATE.csv)

Il peut etre:

- importe dans Notion comme base
- ouvert directement dans Excel
- utilise dans Google Sheets

## Colonnes recommandees

### Identification

- `Nom contact`
- `Entreprise`
- `Pays`
- `Secteur`
- `Nombre employes`
- `Type profil`

Valeurs conseillees pour `Type profil`:

- beta
- prospect
- client payant
- partenaire comptable

### Source et contexte

- `Source`
- `Niveau usage beta`
- `Douleur principale`
- `Temps perdu par mois`

Valeurs possibles pour `Source`:

- beta existant
- LinkedIn
- WhatsApp
- referral
- comptable partenaire
- site web

Valeurs possibles pour `Niveau usage beta`:

- chaud
- tiede
- froid
- n/a

### Pilotage commercial

- `Statut commercial`
- `Dernier contact`
- `Prochaine relance`
- `Canal principal`
- `Objection principale`
- `Offre envoyee`
- `Probabilite closing`

Valeurs conseillees pour `Statut commercial`:

- a contacter
- contacte
- a repondu
- demo proposee
- demo faite
- essai en cours
- offre envoyee
- paye
- perdu
- a relancer plus tard

Valeurs conseillees pour `Canal principal`:

- WhatsApp
- LinkedIn
- appel
- email
- demo visio

Valeurs conseillees pour `Probabilite closing`:

- faible
- moyenne
- forte

### Preuve et apprentissage

- `Temoignage disponible`
- `Notes`

Valeurs conseillees pour `Temoignage disponible`:

- oui
- non
- a demander

## Ordre de travail recommande

1. Remplir tous les beta testeurs deja connus.
2. Classer leur niveau d'usage.
3. Mettre a jour `Douleur principale` avec les mots exacts du client.
4. Mettre une `Prochaine relance` pour chaque contact actif.
5. Ne jamais laisser un contact chaud sans prochaine action.

## Vue minimale a creer dans Notion ou Excel

Si tu veux rester simple, cree 4 vues:

- `Beta chauds`
- `Essais en cours`
- `Offres envoyees`
- `Clients payants`

## Regles d'usage

- une ligne = un contact decisionnaire
- chaque conversation importante doit mettre a jour la ligne
- si un contact n'a pas de prochaine relance, il risque d'etre perdu
- les notes doivent rester courtes et actionnables

## Exemple de lecture

Un bon enregistrement doit permettre de comprendre en 10 secondes:

- qui est la personne
- quel probleme elle veut resoudre
- ou elle en est dans le cycle
- quelle est la prochaine action a faire

## Minimum vital a remplir si tu manques de temps

Si tu veux aller tres vite, remplis au minimum:

- `Nom contact`
- `Entreprise`
- `Type profil`
- `Douleur principale`
- `Statut commercial`
- `Dernier contact`
- `Prochaine relance`
- `Notes`

Avec seulement ces champs, tu peux deja piloter proprement les 30 premiers jours.
