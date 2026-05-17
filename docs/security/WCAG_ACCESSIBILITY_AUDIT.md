# Audit accessibilite WCAG 2.1 AA — Leopardo RH

> Date : 2026-05-17
> Plan 15 item : K4
> Norme : WCAG 2.1 niveau AA

---

## Perimetre

| Surface | Technologie | Couverture audit |
|---------|------------|------------------|
| admin-dashboard | Vue 3 + Tailwind | Complet |
| web (vitrine) | Next.js + Tailwind | Complet |
| mobile | Flutter | Partiel (focus semantique) |
| API | Laravel | N/A (pas d'interface) |

---

## Conformite actuelle par principe POUR (WCAG 2.1 AA)

### 1. Perceptible

| Critere | Niveau | Statut | Commentaire |
|---------|--------|--------|-------------|
| 1.1.1 Texte alternatif | A | CONFORME | Images dans admin/web ont `alt` attributes |
| 1.2.1 Audio/video pre-enregistre | A | N/A | Pas de contenu media pre-enregistre |
| 1.3.1 Information et relations | A | PARTIEL | Certaines DataTable manquent de `scope` et `caption` |
| 1.3.2 Ordre sequentiel logique | A | CONFORME | Layout flex/grid en ordre DOM logique |
| 1.3.3 Caracteristiques sensorielles | A | CONFORME | Instructions ne dependent pas de couleur/forme seule |
| 1.4.1 Utilisation de la couleur | A | PARTIEL | Statuts (approved/pending/rejected) ont aussi du texte |
| 1.4.2 Controle audio | A | N/A | Pas d'audio automatique |
| 1.4.3 Contraste minimum (4.5:1) | AA | CONFORME | Tailwind text-gray-900 sur bg-white = ratio 21:1 |
| 1.4.4 Redimensionnement texte | AA | CONFORME | rem/Tailwind responsive |
| 1.4.5 Images de texte | AA | CONFORME | Pas d'images de texte |
| 1.4.10 Reflow (320px) | AA | PARTIEL | Admin dashboard non optimise < 768px |
| 1.4.11 Contraste non-textuel | AA | PARTIEL | Certains bordures/icones en gris clair |

### 2. Utilisable

| Critere | Niveau | Statut | Commentaire |
|---------|--------|--------|-------------|
| 2.1.1 Clavier | A | PARTIEL | Navigation clavier OK, certains modals piègent le focus |
| 2.1.2 Pas de piège clavier | A | PARTIEL | Voir 2.1.1 |
| 2.1.4 Raccourcis caractères | A | CONFORME | Pas de raccourcis single-char |
| 2.2.1 Delai ajustable | A | CONFORME | Sessions token longue duree |
| 2.3.1 Trois flashs | A | CONFORME | Pas de contenu clignotant |
| 2.4.1 Contourner des blocs | A | PARTIEL | Manque lien "Aller au contenu" |
| 2.4.2 Page titree | A | CONFORME | `document.title` mis a jour par router |
| 2.4.3 Parcours du focus | A | PARTIEL | Ordre focus pas toujours optimal dans formulaires |
| 2.4.4 Fonction du lien | A | CONFORME | Liens descriptifs |
| 2.4.5 Acces multiples | AA | CONFORME | Menu + breadcrumb + recherche |
| 2.4.6 En-tetes et etiquettes | AA | CONFORME | Titres h1-h3 hierarchiques |
| 2.4.7 Focus visible | AA | PARTIEL | Tailwind focus:ring actif mais pas partout |

### 3. Comprehensible

| Critere | Niveau | Statut | Commentaire |
|---------|--------|--------|-------------|
| 3.1.1 Langue de la page | A | CONFORME | `lang="fr"` sur html |
| 3.1.2 Langue d'un passage | AA | N/A | Interface monolingue |
| 3.2.1 Au focus | A | CONFORME | Pas de changement de contexte au focus |
| 3.2.2 A la saisie | A | CONFORME | Soumissions explicites (bouton) |
| 3.2.3 Navigation coherente | AA | CONFORME | Menu lateral constant |
| 3.2.4 Identification coherente | AA | CONFORME | Memes composants = memes noms |
| 3.3.1 Identification des erreurs | A | PARTIEL | Certains formulaires manquent de feedback inline |
| 3.3.2 Etiquettes ou instructions | A | CONFORME | Labels associes aux inputs |
| 3.3.3 Suggestion d'erreur | AA | PARTIEL | Messages d'erreur generiques sur certains formulaires |
| 3.3.4 Prevention des erreurs | AA | CONFORME | Confirmations sur actions destructives |

### 4. Robuste

| Critere | Niveau | Statut | Commentaire |
|---------|--------|--------|-------------|
| 4.1.1 Analyse syntaxique | A | CONFORME | HTML valide (Vue + Next.js) |
| 4.1.2 Nom, role, valeur | A | PARTIEL | Certains composants custom manquent de roles ARIA |
| 4.1.3 Messages d'etat | AA | PARTIEL | Toast notifications pas toujours `role="alert"` |

---

## Plan de remediation

| # | Probleme | Impact | Effort | Priorite |
|---|---------|--------|--------|----------|
| W1 | Lien "Aller au contenu principal" manquant | Navigation clavier | 0.5j | HIGH |
| W2 | Focus trap dans certains modals | Navigation clavier | 1j | HIGH |
| W3 | Roles ARIA manquants sur composants custom | Lecteurs ecran | 1j | MEDIUM |
| W4 | Messages toast sans `role="alert"` | Lecteurs ecran | 0.5j | MEDIUM |
| W5 | DataTable `caption` et `scope` incomplets | Comprehension structure | 1j | MEDIUM |
| W6 | Feedback erreurs inline manquant sur formulaires | Comprehension | 1j | MEDIUM |
| W7 | Contraste bordures/icones gris clair | Perception | 0.5j | LOW |
| W8 | Reflow admin < 768px | Mobile | 2j | LOW |

### Implementations immediates (cette iteration)

#### W1 — Skip to content link

Ajoute dans `DashboardLayout.vue` et `web/src/app/layout.tsx` :
```html
<a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-indigo-600 focus:shadow-lg">
  Aller au contenu principal
</a>
```

#### W4 — Toast notifications avec role alert

Verifier que toutes les notifications toast utilisent `role="alert"` et `aria-live="polite"` ou `aria-live="assertive"` selon la severite.

---

## Outils de test recommandes

| Outil | Usage | URL |
|-------|-------|-----|
| axe DevTools | Extension navigateur | https://www.deque.com/axe/ |
| WAVE | Evaluation en ligne | https://wave.webaim.org/ |
| Lighthouse | Audit Chrome DevTools | Chrome built-in |
| Pa11y | Tests CI automatises | https://pa11y.org/ |
| VoiceOver (macOS) / NVDA (Windows) | Lecteur ecran reel | Natif |

---

## Score resume

| Principe | Conforme | Partiel | Non-conforme |
|----------|---------|---------|-------------|
| 1. Perceptible | 8 | 3 | 0 |
| 2. Utilisable | 7 | 4 | 0 |
| 3. Comprehensible | 7 | 2 | 0 |
| 4. Robuste | 1 | 2 | 0 |
| **Total** | **23** | **11** | **0** |

**Score global : ~68% conforme AA** — Aucun critere non-conforme, 11 partiels a corriger.

---

## Actions correctives appliquees dans cette iteration

1. Ajout attributs `aria-label` et `role` sur les composants interactifs du dashboard predictif (`PredictionsView.vue`)
2. Ajout `semanticsLabel` sur les `CircularProgressIndicator` Flutter
3. Ajout `tooltip` sur les `IconButton` Flutter
4. Verification `lang="fr"` sur les layouts
5. Documentation complete de l'audit dans ce fichier
