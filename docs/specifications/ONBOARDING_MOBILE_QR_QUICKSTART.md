# Spec — Onboarding web : QR app mobile + premier pointage + Quick Start (< 15 employés)

> Issues : #4938 (QR + premier pointage), #4939 (Quick Start) · ADR de référence : `docs/architecture/adr/0015-onboarding-steps-canonical.md`
> Statut : **validée pour la partie frontend web** (QR + guidance + hint Quick Start) ; deltas backend listés en attente de décision.

## 1. Contexte

Le wizard d'onboarding web (data-driven, livré par #4946) affiche les 6 étapes seedées.
La conception (`24_ONBOARDING_GUIDE.md`) prévoit : télécharger l'app mobile (QR) et effectuer
un premier pointage de test. Le mode Quick Start (< 15 employés) doit réduire la friction.

## 2. Cible UX (wizard web)

### 2.1 QR d'onboarding de l'entreprise (issue #4938)
- Sur l'étape `first_checkin` (et/ou un encart dédié « Télécharger l'app mobile »), le manager
  peut afficher le **QR d'onboarding de l'entreprise** : bouton « Afficher le QR de l'entreprise »
  → `GET /company/qr-onboarding` (manager principal/rh uniquement, 403 sinon) → rendu du QR
  (canvas, lib `qrcode`, aucun appel réseau externe) depuis le champ `data.token`.
- En dessous : court texte « Scannez avec l'app mobile Leopardo pour rejoindre l'entreprise » (i18n ×4).
- Erreur 403/échec → message localisé discret, bouton réessayer ; jamais de blocage du wizard.
- L'étape `first_checkin` : texte de guidance (« Effectuez un premier pointage depuis l'app ou
  le kiosque ») + bouton « J'ai effectué le premier pointage » (complète l'étape) + « Passer »
  si optionnelle.

### 2.2 Quick Start < 15 employés (issue #4939)
- Le wizard lit `GET /onboarding/checklist` (moteur calculé) en parallèle pour obtenir
  `employees_count` (métadonnée d'étape). Si `employees_count < 15` :
  - badge « Quick Start » (i18n ×4) dans l'en-tête du wizard ;
  - les étapes optionnelles (`setup_geofence`, `setup_kiosk`) sont présentées avec un
    libellé « recommandé plus tard » et un CTA « Passer » plus visible.
- **Pas de modification du seed ni de complétion automatique** (honnêteté : l'utilisateur
  reste maître du skip).

## 3. Deltas backend requis (hors périmètre frontend, à décider)

1. **Planning par défaut Quick Start** : quand un tenant < 15 employés est créé, seed des
   plannings 08:00-17:00 Lun-Sam (endpoint ou provisionning). → issue à créer si retenue.
2. **Exposition `employees_count`** : le moteur calculé l'expose déjà dans `data.steps[].metadata` ;
   l'endpoint table (`/onboarding-setup/checklist`) ne l'expose pas — le wizard utilise le
   moteur calculé pour ce signal.
3. **URL de téléchargement des apps** : le QR pointe vers le token signé ; l'app mobile doit
   accepter ce token (comportement existant côté mobile via scan). Vérification mobile à prévoir.

## 4. Critères d'acceptation (frontend)

- [ ] Le wizard affiche le QR de l'entreprise (canvas) depuis `/company/qr-onboarding` sans dépendance runtime externe
- [ ] Guidance « premier pointage » localisée ×4 ; complétion de l'étape uniquement sur action explicite
- [ ] Badge Quick Start + CTA « Passer » renforcé quand `employees_count < 15`
- [ ] Erreurs 403/API → message localisé + retry, jamais de blocage
- [ ] lint 0, jest verts (tests composant wizard étendus), build prod OK

## 5. Hors périmètre

- Changement du seed (ADR-0015) ; planning par défaut backend (delta 1) ; UX mobile.
