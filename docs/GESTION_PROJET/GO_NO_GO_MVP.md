# GO / NO-GO MVP — LEOPARDO RH

## 1. Objet du document

Ce document sert de cadre de decision pour statuer si Leopardo RH a atteint un niveau suffisant pour etre considere comme un MVP exploitable.

Il ne remplace pas:

- le cahier des charges
- le dossier de reponse au cahier des charges
- les runbooks d'exploitation
- les rapports QA / CI

Il sert a repondre a une question simple:

- peut-on considerer le produit assez stable, coherent et utile pour entrer en phase suivante?

## 2. Definition du MVP dans ce contexte

Dans le contexte Leopardo RH, le MVP ne signifie pas "produit complet".
Il signifie:

- un noyau fonctionnel clair
- une execution technique fiable
- une valeur metier immediate
- un niveau de risque acceptable pour une premiere utilisation reelle encadree

Le MVP est donc atteint si:

- les parcours coeur fonctionnent de bout en bout
- les roles cibles principaux peuvent utiliser l'application sans blocage majeur
- le backend et le mobile sont deployables
- les incidents restants ne remettent pas en cause la valeur essentielle du produit

## 3. Perimetre MVP retenu

Le perimetre MVP realiste a date couvre les briques suivantes.

### 3.1 Backend / API

- authentification API
- recuperation de session (`auth/me`)
- logout
- gestion de base des employes
- pointage presence:
  - statut du jour
  - check-in
  - check-out
  - historique
- estimation journaliere
- estimation rapide
- generation PDF de recu
- healthcheck
- contexte multitenant et isolation minimale

### 3.2 Web

- login web
- logout web
- dashboard manager
- consultation des donnees de base
- parcours de supervision / consultation

### 3.3 Mobile

- login
- restauration de session
- consultation du statut du jour
- pointage check-in / check-out
- historique
- logout
- connexion a l'API Render en environnement reel

## 4. Conditions minimales de passage en GO MVP

Le passage en GO MVP suppose que les criteres suivants soient consideres comme vrais.

### 4.1 Valeur metier

- un manager peut se connecter et acceder aux fonctions coeur
- un employe peut se connecter et consulter/utiliser ses fonctions mobiles coeur
- la presence est exploitable
- le systeme produit deja une valeur RH mesurable

### 4.2 Stabilite technique

- un deploiement backend reproductible existe
- les migrations passent sans intervention manuelle
- la base de donnees n'exige pas de manipulation artisanale recurrente
- le mobile release peut se connecter a la vraie API
- les erreurs majeures sont journalisees et compréhensibles

### 4.3 Qualite fonctionnelle

- les principaux parcours ne presentent pas de bug bloquant
- les tests critiques passent
- les contrats backend/mobile critiques sont coherents
- les comptes de test permettent une recette fiable

### 4.4 Gouvernance minimale

- changelog a jour
- documentation de test disponible
- documentation de deploy / rollback disponible
- decision GO / NO-GO tracable

## 5. Grille GO / NO-GO MVP

La decision doit etre prise sur la base de la grille ci-dessous.

### 5.1 Statut possible

- **GO MVP**
  - le produit peut passer en validation terrain / beta encadree / adoption pilote
- **GO MVP sous reserve**
  - le produit est exploitable, mais avec une liste courte d'actions correctives non bloquantes
- **NO-GO MVP**
  - au moins un risque majeur empeche encore l'usage reel du produit

### 5.2 Regle de decision

- **GO MVP** si:
  - aucun blocant P0 / P1 n'est ouvert sur le perimetre coeur
  - les parcours coeur sont verifies
  - l'architecture de deploy est stable
  - le mobile et le backend sont coherents
- **GO MVP sous reserve** si:
  - les parcours coeur sont utilisables
  - il reste des defauts non bloquants ou des zones de couverture incomplete
  - les reserves sont listees, bornees et planifiees
- **NO-GO MVP** si:
  - le login, la session, la presence, le deploy ou l'isolation tenant restent instables
  - ou si une action manuelle fragile est indispensable avant chaque utilisation

## 6. Criteres fonctionnels de validation

### 6.1 Authentification

Les points suivants doivent etre vrais:

- login API operationnel
- login web operationnel
- login mobile operationnel
- `auth/me` operationnel
- logout operationnel
- refus des mauvais identifiants
- refus des comptes inactifs / archives / suspendus selon regles metier

### 6.2 Presence

- statut du jour accessible
- check-in operationnel
- check-out operationnel
- historique exploitable
- comportement coherent si aucun pointage n'existe encore

### 6.3 Employes

- consultation des employes selon role
- respect RBAC minimal
- isolation inter-tenant validee

### 6.4 Estimation

- estimation journaliere
- estimation rapide
- PDF de recu

### 6.5 Mobile

- connexion reelle a l'API
- gestion de session
- ecran principal accessible apres login
- historique presence consultable
- experience sans spinner infini sur parcours nominal

## 7. Criteres techniques de validation

### 7.1 Backend

- pipeline GitHub Actions exploitable
- tests backend critiques executes
- audit Composer exploitable
- gouvernance CI non bloquante hors regressions reelles

### 7.2 Deploiement

- build Docker OK
- bootstrap Render fiable
- migrations publiques et tenant robustes
- seeders de base idempotents
- rollback documente

### 7.3 Mobile

- build mobile release/staging possible
- distribution Firebase App Distribution possible
- tests IA / QA guidee possibles avec comptes de test
- permissions Android release coherentes avec l'usage reseau

## 8. Criteres de qualite par gravite

### 8.1 Niveau bloquant MVP

Un bug est bloquant MVP s'il empeche:

- de se connecter
- de restaurer une session
- de deployer proprement
- de pointer
- d'acceder au coeur metier
- de garantir un minimum d'isolation / securite

### 8.2 Niveau acceptable avant phase suivante

Peuvent rester ouverts si encadres:

- bugs cosmetiques
- imperfections UI non bloquantes
- messages d'erreur a polir
- documentation perfectible
- scenarios non coeur encore non automatises

## 9. Etat de lecture recommande a date

Sur la base des travaux recents, Leopardo RH semble relever du statut suivant:

- **GO MVP sous reserve**, avec objectif de passer en **GO MVP** apres validation finale de stabilite `main` + deploy + mobile release

### 9.1 Pourquoi on est proche du GO

- backend et mobile se connectent
- flux critiques ont ete renforces
- CI existe et couvre les zones centrales
- deploy Render a ete fortement durci
- mobile Flutter est aligne sur l'API reelle

### 9.2 Pourquoi il reste une reserve avant GO plein

- le flux de merge / conflits recurrent avec `main` montre un besoin de stabilisation de branche
- les scenarios metier au-dela du coeur MVP ne sont pas tous automatises
- une derniere verification `main` + deploy + build mobile release est souhaitable pour cloturer proprement la decision

## 10. Checklist de decision GO / NO-GO

### 10.1 Produit

- [ ] Login manager OK
- [ ] Login employe OK
- [ ] Login web admin/manager OK
- [ ] Presence du jour OK
- [ ] Check-in OK
- [ ] Check-out OK
- [ ] Historique OK
- [ ] Logout OK

### 10.2 Technique

- [ ] Build backend OK
- [ ] Deploy Render OK
- [ ] Healthcheck OK
- [ ] Migrations OK
- [ ] Seeders OK
- [ ] Build mobile release/staging OK
- [ ] Connexion mobile a l'API reelle OK

### 10.3 Qualite

- [ ] Checks GitHub requis OK
- [ ] Scenarios backend critiques verifies
- [ ] Scenarios mobile critiques verifies
- [ ] Aucun bug P0/P1 ouvert sur le perimetre MVP

### 10.4 Gouvernance

- [ ] CHANGELOG a jour
- [ ] Rapport QA disponible
- [ ] Runbook deploy disponible
- [ ] Runbook rollback disponible
- [ ] Decision GO / NO-GO archivee

## 11. Decision type a enregistrer

### Option A — GO MVP

Decision:

- Leopardo RH est considere comme MVP valide.

Conditions:

- perimetre MVP coeur disponible
- stabilite suffisante
- aucun blocant majeur sur le coeur produit

Suites:

- lancement beta/pilote
- collecte feedback
- priorisation phase suivante

### Option B — GO MVP sous reserve

Decision:

- Leopardo RH est autorise a passer en usage pilote limite, avec reserves explicites.

Reserves:

- lister ici les correctifs restants
- leur proprietaire
- leur echeance

Suites:

- correction rapide des reserves
- relecture GO finale

### Option C — NO-GO MVP

Decision:

- Leopardo RH ne passe pas encore en etape suivante.

Motifs:

- lister les blocants reellement incompatibles avec l'usage pilote

Suites:

- plan de correction court
- nouvelle revue de passage

## 12. Etape suivante si GO

Si le GO MVP est prononce, la suite logique n'est pas "fin du projet".
C'est le passage vers une nouvelle phase:

- beta terrain
- stabilisation `main`
- priorisation phase 2
- enrichissement metier:
  - conges
  - paie
  - notifications
  - reporting
  - finance
  - workflows RH etendus

## 13. Conclusion

Le moment ou l'on peut dire que "le MVP est bon" est celui ou:

- le coeur du produit rend deja le service attendu
- les flux critiques sont fiables
- le niveau de risque restant est acceptable
- et l'usage reel ne depend plus d'une assistance technique permanente

Le passage a l'etape suivante est donc justifie non pas quand tout est fini, mais quand:

- la valeur coeur est la
- la stabilite minimale est atteinte
- et la phase suivante apporte plus de valeur que la poursuite de micro-corrections sans fin
