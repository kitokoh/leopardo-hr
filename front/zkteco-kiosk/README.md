# ZKTeco Kiosk Bridge

Ce dossier contient le code de la **borne d entree entreprise** et du **bridge desktop local**.

L objectif est simple :

- l employe peut pointer avec son doigt ou son visage a l entree
- si internet est present, la synchronisation vers Leopardo RH est immediate
- si internet est absent, les pointages sont gardes en local puis synchronises plus tard

## Architecture recommandee

```text
Lecteur ZKTeco / capteur visage
          |
          v
   PC local / mini-PC client
          |
          +--> bridge desktop local (Python stdlib + SQLite)
          |        |
          |        +--> stocke la file offline
          |        +--> expose une UI locale a la borne
          |        +--> synchronise avec l API quand internet revient
          |
          +--> navigateur plein ecran sur la page kiosk
```

## Parcours metier

1. Manager / RH cree l employe
2. L employe recoit un email d invitation pour l app mobile
3. L employe se connecte sur mobile
4. L employe soumet ses donnees biometrie
5. Manager / RH approuve
6. L employe peut ensuite pointer :
   - depuis son mobile
   - ou depuis la borne ZKTeco de l entreprise

## Contenu du dossier

- `index.html` : interface kiosque tactile
- `admin.html` : interface locale manager / RH pour synchroniser
- `app.js` : logique front kiosque
- `admin.js` : logique front administration locale
- `config.example.json` : configuration de depart
- `desktop-bridge/bridge.py` : serveur local offline-first

## Lancement simple

1. Copier `config.example.json` en `config.json`
2. Renseigner :
   - `apiBaseUrl`
   - `deviceCode`
   - `kioskToken`
   - `companyName`
3. Sur le PC local :

```bash
python desktop-bridge/bridge.py
```

4. Ouvrir :
   - borne : [http://127.0.0.1:8037/index.html](http://127.0.0.1:8037/index.html)
   - admin local : [http://127.0.0.1:8037/admin.html](http://127.0.0.1:8037/admin.html)

## Mode sans internet

Quand il n y a pas de connexion :

- les pointages sont sauvegardes dans `desktop-bridge/data/kiosk.db`
- l interface continue de fonctionner
- le manager ou le RH peut synchroniser plus tard depuis `admin.html`

Tu peux donc garder :

- une journee
- une semaine
- ou meme un mois de pointages

en local avant envoi vers l API.

## Integration ZKTeco

Deux modes reels sont prevus :

1. **Clavier HID / keyboard wedge**
   - le lecteur ou terminal injecte le `zkteco_id` ou le matricule
   - la borne l envoie au bridge local

2. **Bridge local / SDK**
   - un service local peut appeler :
   - `POST /local/punch`
   - ou dans le navigateur :
   - `window.ZKTecoBridge.submitIdentifier("FP-00042", "check_in", "fingerprint")`

## Important sur la biometrie

Le matériel ZKTeco reste responsable de la capture / matching brut de l empreinte ou du visage.

Leopardo RH gere ici :

- l autorisation metier
- le lien employe <-> identifiant ZKTeco
- la file offline
- la synchronisation API
- l affichage mobile une fois les donnees synchronisees

Donc oui, la borne supporte bien le doigt et le visage, mais le **matching biometrie reel** depend du terminal / SDK ZKTeco branché sur le PC local.
