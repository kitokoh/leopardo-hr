# Guide employe — Leopardo RH

L **employe simple** (role `employee`, sans sous-role manager) utilise
uniquement l **app mobile** Leopardo RH. L espace web ne contient pas de
dashboard employe : ca fait partie des choix APV v2 (L.02bis) — l interface
pointage + suivi heures doit tenir dans la poche, sur Android et iOS.

Si vous ouvrez `https://<domaine>/login` avec un compte employe, vous etes
redirige vers la page `/mobile` qui vous rappelle comment telecharger l app.

## Activation du compte

1. Vous recevez un email de la part de votre RH / manager.
2. Ouvrir le lien `https://<domaine>/activate/<token>` depuis votre telephone.
3. Definir un mot de passe.
4. Installer Leopardo RH (Play Store / App Store — liens sur `/mobile`).
5. Se connecter dans l app avec email + mot de passe.

## Home conversationnelle

L ecran d accueil de l app est la **Home**. On y retrouve :

- Une salutation contextuelle (bonjour Nom, selon l heure).
- Une banniere Leo (placeholder tant que `leo_ai` n est pas active pour
  votre societe).
- Une grille d actions rapides :
  - **Pointer** — check-in / check-out immediat.
  - **Mon mois** — vos heures normales, heures sup, brut, deductions, net.
  - **Historique** — tous vos pointages passes.
  - **Parametres** — langue, mot de passe, deconnexion.
- Une barre de chat (desactivee tant que Leo IA n est pas active).

## Pointer

Sur l ecran **Pointer** :

- Bouton **Check-in** au debut du service.
- Bouton **Check-out** en fin de service. L app detecte automatiquement si
  vous ouvrez un nouveau jour ou cloturez le jour en cours.
- Retard / depart anticipe : les statuts (`late`, `early_leave`, `half_day`)
  sont calcules automatiquement selon la planification definie par votre RH.

Si votre societe utilise un kiosque biometrique, le pointage peut se faire
directement sur le kiosque — les deux entrees se synchronisent.

## Mon mois

Ecran **Mon mois** : pour chaque mois, un selecteur en haut + :

- Heures travaillees totales.
- Heures supplementaires.
- Salaire brut estime.
- Deductions (securite sociale, IRG).
- Salaire net estime.
- Detail par jour (pointages, heures, statut).

Les montants sont **estimes** — le bulletin de paie officiel reste emis par
votre RH.

## Historique

Liste paginee de tous vos pointages, filtrable par mois. Chaque entree montre :

- Date et heure du check-in / check-out.
- Statut (present, retard, absent, mi-journee, depart anticipe).
- Source (app mobile, kiosque).

## Support

Probleme de connexion, pointage manque, estimation paie incorrecte ?
Contacter votre manager / RH. L app ne contient pas de chat support : la
communication passe par l entreprise.
