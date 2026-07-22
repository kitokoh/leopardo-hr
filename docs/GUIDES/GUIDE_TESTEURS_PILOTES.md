# Guide testeurs / pilotes — Leopardo RH

Date : 2026-07-22
Ticket : PA2-STR-003

Ce guide reunit en un seul endroit tout ce dont a besoin un testeur externe
ou un client pilote pour explorer Leopardo RH sans avoir a fouiller le repo :
liens applicatifs reels, comptes de demo, et scenarios de test par persona.
Il complete (sans les dupliquer) :

- `docs/GUIDES/GUIDE_LIENS_PLATEFORME_ET_COMMUNICATION.md` : liens
  d'infrastructure et variables d'environnement (reference technique interne).
- `docs/DEMO_ACCOUNTS.md` : table detaillee des comptes de demo et de leur
  `manager_role`.
- `docs/api/API_REFERENCE.md` : reference complete de l'API REST.

## 1. Acces web (aucune installation requise)

| Surface | URL | Persona attendue |
| --- | --- | --- |
| Vitrine / espace client web | <https://gestionemployer-backend.vercel.app/> | Tous (public, puis Manager/Employee apres connexion) |
| Admin plateforme (super-admin) | <https://leo-admin.pages.dev> | Super Admin uniquement |
| Documentation API publique | <https://gestionemployerbackend.onrender.com/docs> | Integrateurs / testeurs API |

L'espace client web et l'admin plateforme sont deux applications distinctes
avec deux ecrans de connexion differents : ne pas essayer un compte manager
sur `leo-admin.pages.dev`, ni le compte `admin@leopardo-rh.com` sur l'espace
client web (voir tableau des personas en section 3).

## 2. Applications mobiles (Android testeur)

Les 3 apps de lancement sont distribuees aux testeurs via Firebase App
Distribution (liens publics, pas besoin de compte Firebase pour installer) :

| App | Persona | Lien testeur Android |
| --- | --- | --- |
| Leopardo Employee | Employee | <https://appdistribution.firebase.dev/i/e2bde6595da9d96e> |
| Leopardo Manager | Manager (tous sous-roles) | <https://appdistribution.firebase.dev/i/e51102534a5dff22> |
| Leopardo Platform Admin | Super Admin | <https://appdistribution.firebase.dev/i/f37b128b1c89a006> |

Ces memes liens sont exposes publiquement sur la page vitrine
`front/web/src/app/(landing)/download/page.tsx` (bouton "Installer la version
testeur" / "Install tester build" selon la langue) — utiliser cette page si un
lien ci-dessus a expire ou a ete renouvele, elle reste la source la plus a
jour cote produit.

- `leopardo_hr` (app RH dediee, sous-role `rh` split de Manager) et
  `leopardo_platform_admin` en version iOS n'ont pas encore de lien testeur
  public a ce jour — utiliser l'app **Leopardo Manager** avec le compte
  `fatima.meziane@techcorp-algerie.dz` (`manager_role=rh`) pour tester les
  parcours RH mobiles en attendant.
- Pas de version iOS testeur publique pour aucune des 3 apps a ce jour
  (TestFlight non configure) — l'iOS se teste actuellement via `flutter run`
  en local (voir `front/mobile_apps/README.md`).

## 3. Comptes de demo (mot de passe commun : `password123`)

Table complete avec les objectifs de test recommandes par persona :

| Persona | Identifiant | Surface a tester | Objectif de test suggere |
| --- | --- | --- | --- |
| Super Admin | `admin@leopardo-rh.com` | `leo-admin.pages.dev` + app Platform Admin | Creation d'un tenant, visualisation des metriques multi-entreprises, traitement d'une demande client. |
| Manager Principal | `ahmed.benali@techcorp-algerie.dz` | Web + app Manager | Dashboard executif, export paie, readiness de lancement. |
| Manager RH | `fatima.meziane@techcorp-algerie.dz` | Web + app Manager/HR | Onboarding employe, gestion des absences, analytics de communication. |
| Manager Departement | `samir.boukhalfa@techcorp-algerie.dz` | Web + app Manager | Equipe du departement, absences d'equipe, projets/taches. |
| Manager Comptable | `lina.haddad@techcorp-algerie.dz` | Web + app Manager | Paie, export bancaire, suivi financier RH. |
| Manager Superviseur | `nassim.cheriet@techcorp-algerie.dz` | Web + app Manager + kiosk | Presence terrain, demandes d'enrolement biometrique, supervision kiosk. |
| Employee | `karim.aouad@techcorp-algerie.dz` | Web + app Employee + kiosk | Pointage GPS/QR/kiosk, consultation absences/paie, notifications. |

> Rappel important (voir `docs/DEMO_ACCOUNTS.md`) : ces comptes ne sont
> visibles via `GET /api/v1/demo-users` que si `DEMO_MODE_ENABLED=true` sur le
> backend cible (faux par defaut, y compris en production Render actuelle).
> Ils restent valides pour se connecter directement sur les identifiants
> ci-dessus meme quand l'endpoint de decouverte est desactive.

Deux entreprises de demo supplementaires (`pharmaplus-casablanca` au Maroc,
`digitalflow-tunis` en Tunisie) permettent de tester les variations
pays/devise — voir la reponse complete de `/api/v1/demo-users` pour leurs
identifiants.

## 4. Kiosk biometrique (borne d'entree)

Le kiosk (`front/zkteco-kiosk`) est une page HTML/JS statique sans backend
propre : elle appelle directement l'API Leopardo RH. Pour la tester sans
materiel biometrique physique :

1. Ouvrir `front/zkteco-kiosk/index.html` dans un navigateur (double-clic ou
   `python3 -m http.server` depuis ce dossier).
2. Utiliser le formulaire de secours "matricule" (fallback QR/texte, pas
   besoin de doigt/visage) avec l'identifiant d'un compte Employee de demo.
3. Le selecteur de langue en haut de la borne (`fr`/`en`/`tr`/`ar`, ajoute par
   PA2-I18N-013) permet de verifier le rendu multilingue et RTL (arabe) de
   l'interface kiosk elle-meme.

Aucun deploiement public de demonstration du kiosk n'existe a ce jour (borne
physique deployee uniquement chez les clients pilotes) — le test se fait en
local avec les etapes ci-dessus.

## 5. Scenarios de test recommandes (bout en bout)

Ces scenarios traversent plusieurs surfaces pour valider un parcours metier
complet plutot qu'un ecran isole :

1. **Cycle de pointage complet** : Employee pointe via mobile (GPS) le matin
   -> Manager Superviseur voit la presence en direct sur le web -> Employee
   pointe le soir via le kiosk avec son matricule -> Manager Principal
   consulte le rapport de presence du jour sur le dashboard executif web.
2. **Cycle d'absence** : Employee soumet une demande d'absence via mobile ->
   Manager RH la voit apparaitre dans sa file d'attente web -> Manager RH
   approuve -> Employee recoit une notification et voit son solde de conges
   mis a jour sur mobile.
3. **Cycle de paie** : Manager Comptable prepare/consulte un export de paie
   sur le web -> verifie les donnees de pointage/absences du mois pour
   l'Employee de demo -> genere l'export bancaire.
4. **Cycle multi-tenant** : Super Admin cree une nouvelle entreprise cliente
   depuis l'app Platform Admin -> se connecte a l'espace client web avec un
   compte de cette nouvelle entreprise pour confirmer l'isolation des
   donnees vis-a-vis de `techcorp-algerie`.
5. **Cycle multilingue** : repeter le scenario 1 ou 2 en changeant la langue
   sur chaque surface testee (web, mobile, kiosk) vers `en`, `tr`, puis `ar`
   (verifier l'affichage RTL correct en arabe) pour confirmer la coherence
   i18n de bout en bout.

## 6. Ou signaler un probleme

Reference `docs/GESTION_PROJET/RETOURS_CLIENTS_PILOTE_2026_04_22.md` pour le
format de retour attendu des clients pilotes existants ; ouvrir une issue sur
`kitokoh/leopardo-hr` pour tout bug reproductible avec les etapes ci-dessus.
