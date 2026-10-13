# UIX_UI_DESIGN — Design d'expérience et d'interface

**Statut :** canonique · **Base :** `main` au 2026-10-13 · **Issue :** #8444
**Chantiers actifs :** #8446 (branding), #8447 (Mon Logiciel)

## 1. Principes

1. **« Wow » maîtrisé** — la vitrine doit impressionner (héro 3D, constellation
   de solutions) sans jamais gêner la lisibilité ni la performance.
2. **Le fond avant la forme** — chaque écran répond d'abord à une tâche métier
   (« ajouter un plat », « planifier une rotation ») ; l'ornement vient après.
3. **Onboarding = mise en service** — on ne « s'inscrit » pas, on **met en
   route son entreprise**. Chaque étape produit quelque chose de réel.
4. **Cohérence 4 langues** — fr/en/ar/tr dès la conception (longueurs,
   RTL), jamais de chaîne en dur (PA2-I18N-014).
5. **Offline assumé** — l'UI montre l'état réseau/sync sans angoisser.

## 2. Identité de marque

| Élément | État | Référence |
|---|---|---|
| Logotype « Leopardo » | existant | `front/web/public/logo.png`, `assets/branding/logo-240.png` |
| Favicon | existant (basique), refonte premium proposée | `front/web/public/favicon.svg`, `assets/branding/` |
| Logo animé de chargement | **proposé** (#8446) | `assets/branding/logo-loader.svg` |
| Bannière OG | existante | `assets/branding/og-banner.png` |
| Pages 404 | existantes, i18n, sobres — enrichissement proposé | `front/web/src/app/not-found.tsx` |

Palette de référence (à consolider dans le design system) : fond ardoise
profonde `#0f172a`, texte clair `#ecfeff`, accent émeraude `#34d399`. Le
léopard est la mascotte : vitesse, précision, élégance — à décliner en
illustrations (404, empty states, loader).

## 3. Design system

- **Web** : Tailwind + composants partagés `front/web/src/modules/vitrine/`
  (Navbar, Footer, hooks de reveal) ; animations framer-motion.
- **Mobile** : package Flutter `leopardo_core` (design system, services,
  l10n arb).
- **Super-admin** : Vue 3 + composables (`front/admin-dashboard/src/`).
- Wireframes historiques : `docs/dossierdeConception/11_ux_wireframes/`.

Règles : tokens de couleurs/espacements centralisés ; un composant = une
responsabilité ; états (loading, empty, erreur, offline) systématiques.

## 4. UX de l'onboarding tenant

Cible : **logique, optimale, propre sur le fond et la forme**.

1. **Progression visible** — étapes numérotées, nommées, sauvegarde à chaque
   pas (interview idempotente).
2. **Choix du profil métier en premier** — tout le reste du parcours est
   contextualisé (vocabulaire, seeds, apps proposées).
3. **Une action = un résultat** — « Créer mon restaurant » crée vraiment le
   restaurant ; pas de formulaire décoratif.
4. **Checklist de mise en service** (#8451) — à la fin de l'interview,
   l'écran d'accueil liste ce qui reste pour être opérationnel, avec
   progression persistée.
5. **Sortie de secours** — reprendre plus tard sans rien perdre.

## 5. Espace « Mon Logiciel » (#8447)

Distribution des apps métier au tenant :

- **Hub filtré par solutions actives** : le restaurateur voit serveur,
  cuisine, POS, manager, employé ; la station-service voit ses apps ; etc.
- **Fiche app** : rôle, captures, prérequis, boutons de téléchargement
  (stores / APK / desktop), QR d'installation, lien guide.
- **Scène 3D interactive** : constellation des apps du tenant (même langage
  que le héro vitrine) — chaque app est un astre cliquable ; la scène reste
  décorative (contenu accessible sans WebGL).
- **État d'installation** : app installée/détectée, dernière sync vue.

## 6. Branding en mouvement

- **Loader de page** : logo animé (`logo-loader.svg`, SMIL — aucune dépendance
  JS) affiché pendant les transitions lourdes ; durée plafonnée, respect de
  `prefers-reduced-motion`.
- **404** : illustration léopard + message i18n + chemins de retour
  (accueil, dashboard, support) — le pattern existe déjà, enrichir le visuel.
- **Empty states** : mascotte + une action claire, jamais de page morte.

## 7. Accessibilité & performance visuelle

- Contrastes AA minimum ; focus visible ; navigation clavier complète.
- 3D et animations : `prefers-reduced-motion` respecté, fallback statique,
  chargement différé (lazy) hors du chemin critique.
- Budget : héro < 300 Ko d'assets 3D initiaux ; LCP < 2,5 s en 4G.

## 8. Checklist design par écran

- [ ] Titre et action principale évidents en < 3 s.
- [ ] États loading / empty / erreur / offline dessinés.
- [ ] i18n 4 langues (dont RTL) sans casse de mise en page.
- [ ] Responsive mobile-first ; zone tactile ≥ 44 px.
- [ ] Aucune chaîne en dur ; icônes avec label accessible.
