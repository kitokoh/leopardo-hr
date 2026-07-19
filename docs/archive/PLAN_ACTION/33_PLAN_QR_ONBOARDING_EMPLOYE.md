# Plan 33 - QR onboarding employe et ajout manager fiable

Date : 2026-05-27

## Objectif

Rendre l'integration terrain employe/entreprise utilisable depuis les apps mobiles, sans remplacer le formulaire classique. Le QR doit aider a pre-remplir et securiser le flux, mais le manager garde la validation finale.

## Lot livre

### API

- Ajout d'un service `OnboardingQrService` qui genere des jetons QR signes, versionnes et expires.
- `GET /api/v1/me/qr-profile` expose le QR profil employe.
- `GET /api/v1/company/qr-onboarding` expose le QR entreprise pour les managers principal/RH.
- `POST /api/v1/company/qr-onboarding/scan-employee` decode un QR employe et renvoie un pre-remplissage.
- `POST /api/v1/company/qr-onboarding/create-employee` cree l'employe depuis QR avec email professionnel, salaire, date d'embauche, poste, departement et lieu.
- `POST /api/v1/me/company-qr/scan` permet a un employe de soumettre une demande d'integration via QR entreprise.

### Mobile manager

- Le bouton `Ajouter` propose maintenant deux chemins : formulaire classique ou import QR employe.
- Le flux QR pre-remplit le formulaire tout en laissant le manager verifier l'email professionnel et les donnees contractuelles.
- Le formulaire classique reste disponible et conserve les champs salaire, date d'embauche, role, poste, departement et lieu.
- La feuille se ferme apres creation et invalide les listes, sans attendre un refresh reseau bloquant.

### Mobile employee

- La page `Compte` affiche un bloc QR professionnel.
- L'employe peut copier son QR profil.
- L'employe peut coller un QR entreprise et soumettre une demande d'integration.

### Contrats

- Tests Feature sur lecture QR, creation depuis QR et rejet des jetons invalides.
- Ajout des routes QR dans `FrontendApiContractTest`.
- Mise a jour de la matrice frontend/API.

## Points de vigilance

- Le QR ne doit jamais valider automatiquement une embauche : il pre-remplit et cree une demande, le manager/RH confirme.
- Le QR employe peut porter un email personnel deja utilise ailleurs ; le manager doit pouvoir renseigner un email professionnel unique pour l'entreprise.
- Les jetons sont signes avec `APP_KEY`; une rotation de cle invalidera les QR existants, ce qui est acceptable pour un QR d'onboarding a duree courte.
- Une future version pourra remplacer le collage manuel par un vrai scanner camera si l'equipe accepte d'ajouter une dependance native (`mobile_scanner`) et les permissions store.

## Suite logique

Le prochain lot doit renforcer le dashboard manager : donnees reelles dans "A surveiller", horaires/pauses/jours feries/regles d'heures supp, et isolation stricte pour eviter qu'un manager voie les pointages d'un autre tenant ou d'une autre equipe.
