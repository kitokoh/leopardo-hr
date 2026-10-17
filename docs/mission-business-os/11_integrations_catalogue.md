# 10 — Catalogue des intégrations externes

**Règle :** une intégration est déclarée `operationnelle` **uniquement** si une clé/config
réelle est présente **et** un appel réel a réussi. Sinon elle est `non_configuree` ou
`non_integree`. Aucune case ne peut être cochée « à la main ».

---

## 1. Intégrations par domaine

| Domaine | Intégration | Code / adaptateur | Statut vérifiable | Constat |
|---|---|---|---|---|
| Paiement | Passerelle carte (adaptateur générique) | `Shared/Contracts/Payments` | `non_configuree` | contrat présent, pas de clé |
| Paiement | Virement / prélèvement | `Modules/Banking` | `non_configuree` | import de relevés, pas de PSP |
| Messagerie | E-mail transactionnel | `Core/Mail` | `non_configuree` | dépend d'un transport SMTP/Mailgun configuré |
| Messagerie | SMS / OTP | service OTP (`/trial/verify`) | `partielle` | OTP implémenté ; fournisseur dépend de la config |
| Messagerie | Push mobile | Firebase Cloud Messaging | `partielle` | dépend de `FIREBASE_*` |
| Social | Publication multi-plateforme | `Modules/Marketing` + Ayrshare | `non_configuree` | `AyrshareAnalyticsProvider` présent ; clé requise |
| Social | Commentaires / réponses | `SocialCommentController` | `non_configuree` | nécessite un compte social lié |
| Stockage | Fichiers / documents | disque `local` + `Showcase` | `operationnelle` | stockage interne, aucune dépendance externe |
| Téléphonie | Pointage biométrique | `Modules/Attendance` + `zkteco-kiosk` | `partielle` | kiosque et enrollement présents ; matériel non testé |
| Fleet | Télémétrie GPS | `Modules/Fleet` + `Shared/Tracking` | `non_integree` | aucune position n'est inventée (cf. Travel #8540) |
| Cartographie | Géocodage / cartes | `Shared/Geo` | `non_configuree` | module `Geo` = stub (cf. audit Marketing #8528) |
| Comptabilité | Export comptable | `Modules/Accounting` | `operationnelle` | export interne, pas de passerelle |
| Paie | Calcul et virements | `Modules/Payroll` | `partielle` | moteur de calcul présent ; virements externes non connectés |
| IA | Assistant / génération | `Core/AI` | `partielle` | dépend d'une clé fournisseur |
| E-comm / marketplace | Marketplace voyage | `front/marketplace` | `partielle` | fonctionne entre tenants Leopardo, pas avec des places de marché tierces |
| Distribution | Firebase App Distribution | `.github/workflows/mobile-distribute.yml` | `en_echec` | 4 jobs rouges sur `main` |
| Distribution | Google Play / App Store | — | `non_integree` | aucun pipeline de publication |

---

## 2. Ce que « statut » signifie exactement

| Statut | Définition | Preuve exigée |
|---|---|---|
| `operationnelle` | appel réel réussi dans un environnement Leopardo | log ou test d'intégration vert cité |
| `partielle` | code présent, un seul chemin testé, dépendance externe non vérifiée | test cité + limite écrite |
| `non_configuree` | code présent, aucune clé/config | absence constatée dans la config |
| `non_integree` | aucun adaptateur | absence de code |
| `en_echec` | configurée mais le canal échoue | run CI rouge cité |

**Interdiction :** afficher `operationnelle` dans une surface produit sur la seule base de
la présence du code.

---

## 3. Écran superadmin — « Intégrations »

Proposition (gap S-05) : un tableau **en lecture seule**, dérivé de la configuration réelle.

| Colonne | Source |
|---|---|
| Domaine | statique |
| Intégration | statique |
| Statut | dérivé : clé présente + dernier test d'appel |
| Dernière vérification | horodatage du dernier test réussi |
| Impact tenant | quelles verticales en dépendent |
| Action | « tester la connexion » (si l'adaptateur expose un ping) |

Aucune clé n'est affichée, même partiellement ; seule sa **présence** est exposée.

---

## 4. Configuration restante pour un pilote réel

| Prérequis | Bloquant pour | Note |
|---|---|---|
| Transport e-mail transactionnel | inscription, invitations, réinitialisation | sans lui, l'OTP par e-mail ne part pas |
| Fournisseur SMS (si OTP par SMS) | inscription sans e-mail | dépend du pays |
| Clé FCM + projets Firebase par app | notifications push, distribution testeurs | 4 jobs CI à réparer |
| PSP (carte) | encaissement en ligne | les verticales encaissent en espèces aujourd'hui |
| Compte social + Ayrshare | Marketing OS | publication assistée seulement |
| Clé fournisseur IA | assistant, briefs | dégradation propre si absente |
| Certificats de signature iOS | toute app iOS | aucun canal aujourd'hui |

Aucune de ces dépenses n'est engagée : la liste est un prérequis d'exploitation, pas une
commande.
