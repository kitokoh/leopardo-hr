# RUNBOOK ZKTECO CLIENT

## 1. Objet

Ce document explique comment installer et exploiter une borne de pointage ZKTeco dans une entreprise cliente avec Leopardo RH, en tenant compte des deux modes reels du terrain :

- mode connecte : la borne et le poste local synchronisent automatiquement avec l API
- mode offline : la borne continue a collecter les pointages en local puis les synchronise plus tard

Le flux metier vise est le suivant :

1. le super admin Leopardo RH cree la societe et son manager principal
2. le manager ou le RH cree les employes
3. chaque employe recoit un email d invitation pour installer l application mobile et activer son compte
4. l employe complete son profil et soumet ses donnees de biometrie
5. le manager ou le RH approuve la biometrie
6. apres approbation, l employe peut pointer sur mobile et sur la borne d entree
7. si internet est indisponible, les pointages restent stockes localement jusqu a synchronisation

## 2. Architecture recommandee chez le client

### 2.1 Composants

- 1 borne ZKTeco compatible empreinte et visage
- 1 PC local Windows dedie ou mini PC place a l accueil ou dans le local IT
- 1 routeur ou reseau local minimal
- acces internet optionnel mais recommande
- application mobile Leopardo RH pour les employes
- API Leopardo RH distante

### 2.2 Architecture logique

- la borne ZKTeco capture les evenements de pointage
- le PC local execute le pont local `zkteco-kiosk/desktop-bridge/bridge.py`
- le pont local stocke les pointages dans une base SQLite locale
- si internet est disponible, le pont envoie automatiquement les evenements a l API
- si internet est indisponible, le pont garde les evenements en attente
- le manager ou le RH peut lancer une synchronisation manuelle depuis l interface locale
- une fois synchronises, les pointages deviennent visibles dans Leopardo RH web et mobile

### 2.3 Topologie minimale conseillee

- borne ZKTeco connectee au reseau local du client
- PC local sur le meme reseau local
- PC local accessible a l equipe RH/manager
- internet facultatif pour le fonctionnement quotidien de la borne

## 3. Prerequis fonctionnels avant installation

Avant installation chez le client, verifier que :

- la societe existe dans Leopardo RH
- le manager principal peut se connecter
- le RH et les employes sont crees ou pourront l etre
- l invitation email fonctionne
- l application mobile utilise la bonne API
- les employes ont recu les consignes pour completer leur profil

## 4. Prerequis materiels et reseau

### 4.1 Materiel minimum

- borne ZKTeco avec empreinte + visage
- PC Windows 10 ou 11
- 8 Go RAM minimum
- 20 Go de disque libre minimum
- onduleur recommande si l electricite est instable

### 4.2 Reseau

- reseau local stable entre la borne et le PC
- IP fixe ou reservee DHCP recommandee pour la borne
- IP fixe ou reservee DHCP recommandee pour le PC local
- acces HTTP/HTTPS sortant vers Leopardo RH si synchro internet active

## 5. Dossiers et composants techniques

Les elements deployes se trouvent ici :

- borne / interface locale : `C:\Users\cheic\Downloads\gestionemployer\zkteco-kiosk\index.html`
- interface manager/RH locale : `C:\Users\cheic\Downloads\gestionemployer\zkteco-kiosk\admin.html`
- configuration exemple : `C:\Users\cheic\Downloads\gestionemployer\zkteco-kiosk\config.example.json`
- pont local offline-first : `C:\Users\cheic\Downloads\gestionemployer\zkteco-kiosk\desktop-bridge\bridge.py`

## 6. Installation terrain

### 6.1 Etape 1 - creer la borne dans Leopardo RH

Depuis l espace manager ou RH :

- ouvrir l espace biometrie / bornes
- creer une nouvelle borne
- recuperer :
  - `device_code`
  - `sync_token`

Ces informations seront utilisees par le pont local.

### 6.2 Etape 2 - preparer le PC local

Sur le PC local :

- installer Python 3.11 ou superieur
- copier le dossier `zkteco-kiosk`
- creer un fichier de configuration local a partir de `config.example.json`

Exemple de configuration :

```json
{
  "apiBaseUrl": "https://gestionemployerbackend.onrender.com/api/v1",
  "deviceCode": "ABC12345XY",
  "kioskToken": "TOKEN_FOURNI_LORS_DE_LA_CREATION",
  "companyName": "Entreprise Cliente",
  "locationLabel": "Entree principale",
  "defaultAction": "check_in",
  "autoSync": true,
  "syncIntervalSeconds": 300,
  "listenHost": "127.0.0.1",
  "listenPort": 8765
}
```

### 6.3 Etape 3 - lancer le pont local

Depuis le dossier `zkteco-kiosk/desktop-bridge` :

```powershell
python bridge.py ..\config.local.json
```

Le pont local :

- cree une base SQLite locale
- maintient une file de pointages
- expose des endpoints locaux `/local/*`
- synchronise automatiquement si internet est present

### 6.4 Etape 4 - ouvrir les interfaces locales

- borne locale navigateur : `zkteco-kiosk/index.html`
- console locale manager/RH : `zkteco-kiosk/admin.html`

L interface locale peut etre servie en fichier statique ou via un petit serveur HTTP local selon le contexte client.

## 7. Gestion offline

### 7.1 Comportement attendu sans internet

Si internet tombe :

- la borne continue a envoyer ses evenements au PC local
- le PC local stocke les evenements dans SQLite
- le manager ou le RH peut continuer a exploiter la borne
- aucun pointage n est perdu si le PC local reste actif et sain

### 7.2 Synchronisation differee

Quand internet revient :

- le pont local tente une synchronisation automatique si `autoSync` est active
- sinon le manager ou le RH utilise `admin.html`
- la synchronisation envoie les evenements un par un ou par lot a l API
- l API dedoublonne grace a `external_event_id`

### 7.3 Duree de retention locale recommandee

Le stockage local peut conserver plusieurs semaines ou mois, mais en pratique il faut :

- synchroniser au moins 1 fois par semaine
- exporter ou sauvegarder le PC local si le site est critique
- prevoir une verification RH de la file en attente

## 8. Workflow utilisateur complet

### 8.1 Manager / RH

- cree les collaborateurs
- suit les invitations
- approuve les demandes biometrie
- cree et maintient les bornes
- surveille les synchronisations

### 8.2 Employe

- recoit un email d invitation
- installe l application mobile
- active son compte
- complete ses donnees
- soumet sa demande biometrie
- attend la validation RH/manager
- une fois valide, pointe sur mobile ou a la borne

## 9. Biometrie et gouvernance

### 9.1 Ce qui est valide

- la biometrie n est pas activee sans approbation RH/manager
- une modification ulterieure doit aussi etre approuvee
- la borne n autorise le flux cible que pour les employes approuves

### 9.2 Limite importante

Sur mobile standard, Android/iOS ne donnent pas librement le gabarit brut de l empreinte. Leopardo RH prepare donc un flux realiste :

- mobile pour demande, consentement, profil et verification locale
- borne/lecteur entreprise pour la capture et l usage reel du pointage materiel

## 10. Donnees et securite locale

### 10.1 Donnees locales

Le pont local stocke notamment :

- file de pointages en attente
- cache roster des employes autorises
- etat de synchronisation

### 10.2 Bonnes pratiques

- proteger le PC local par mot de passe
- limiter l acces au manager, RH ou support IT
- activer Windows Update et un antivirus
- sauvegarder regulierement le dossier de donnees local si le client travaille souvent hors ligne

## 11. Exploitation quotidienne

### 11.1 Routine manager / RH

Chaque jour :

- verifier que la borne fonctionne
- verifier que le PC local est allume
- verifier le statut de sync dans `admin.html`

Chaque semaine :

- verifier qu aucun evenement n est en attente depuis trop longtemps
- lancer une synchro manuelle si necessaire
- controler que les pointages sont bien visibles cote web/mobile

### 11.2 Signes de bon fonctionnement

- la borne enregistre les passages
- la file locale n explose pas
- les evenements se vident lors de la synchro
- les employes retrouvent leurs pointages sur mobile apres synchronisation

## 12. Procedure de synchronisation manuelle

Quand internet est disponible :

1. ouvrir `admin.html`
2. verifier le statut reseau
3. lancer `Synchroniser maintenant`
4. verifier le nombre d evenements envoyes
5. controler ensuite un employe cote mobile ou web

## 13. Depannage rapide

### 13.1 La borne ne remonte plus les pointages

Verifier :

- alimentation electrique
- reseau local
- PC local allume
- pont local Python en cours d execution

### 13.2 La synchro ne part pas

Verifier :

- acces internet du PC local
- `apiBaseUrl`
- `deviceCode`
- `kioskToken`
- heure systeme correcte

### 13.3 L employe ne peut pas pointer sur borne

Verifier :

- employe actif
- biometrie approuvee
- identifiant borne correctement mappe (`matricule`, `email` ou `zkteco_id`)

## 14. Mode de deploiement recommande

Pour un client sans internet fiable, la recommandation la plus robuste est :

- borne ZKTeco + PC local + routeur local
- sync automatique si possible
- sync manuelle manager/RH si necessaire
- verification hebdomadaire minimum

Pour un client avec internet stable :

- sync automatique activee
- controle manager/RH uniquement en supervision

## 15. Checklist d installation client

- societe creee
- manager cree et actif
- RH cree si applicable
- employes invites
- borne creee dans Leopardo RH
- `device_code` recupere
- `sync_token` recupere
- PC local prepare
- `config.local.json` renseigne
- pont local lance
- test de pointage realise
- test de sync realise
- verification des pointages sur mobile/web realisee

## 16. Recommandation finale

La solution la plus professionnelle et la plus realiste pour le terrain est de considerer la borne ZKTeco comme dispositif d acquisition locale, le PC local comme tampon offline-first, et Leopardo RH comme systeme central de validation, consultation mobile et synchronisation long terme.

Cette architecture permet :

- de ne pas bloquer les clients sans internet stable
- de ne pas perdre les pointages
- de garder un controle RH/manager sur la biometrie
- de conserver une experience mobile coherente pour les employes
