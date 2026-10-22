# Farm OS — UIX · UI Design

**Version :** 2.0 — 2026-10-18 · **Branche :** `bc/bc-37-farm-os`

---

## PARTIE 1 — UIX (experience utilisateur et navigation)

### 1.1 Principes d'experience

1. **Terrain d'abord** : une main libre, ecran sale, soleil, gants, reseau instable → gros controles, contraste eleve, une action principale par ecran.
2. **Sobriete des donnees** : n'afficher que les capacites activees ; un profil cerealier ne voit rien d'elevage.
3. **Verite des donnees** : chaque valeur porte sa provenance quand elle peut etre ambigue (superficie, cout, prevision).
4. **Aucune impasse** : chaque etat vide propose la prochaine action ; chaque erreur propose une sortie.
5. **Degressif** : le demarrage demande l'essentiel ; le reste est completable plus tard.
6. **Multilingue reel** : `fr`, `en`, `ar` (RTL), `tr` ; unites et devises dependantes du pays.

### 1.2 Architecture de navigation (portail client web)

```text
Tableau de bord (racine)
├── Parcelles .................... liste | carte (si geometrie)
│   └── Parcelle → [Apercu | Cultures | Operations | Observations | Documents | Equipe]
├── Calendrier / Interventions ... Aujourd'hui | Semaine | En retard
├── Intrants & stock ............. Articles | Mouvements | Alertes
├── Materiel ..................... Liste | Entretiens | Pannes | Couts
├── Elevage * .................... Lots/Animaux | Evenements | Reproduction | Sante | Productions
├── Recoltes & stockage * ........ Recoltes | Lots | Emplacements | Pertes
├── Depenses & rentabilite * ..... Depenses | Campagnes | Ventes rattachees | Marges
├── Applications * ............... Catalogue | Outils par role
├── Assistant * .................. Contexte | Questions a un conseiller
├── Synchronisation * ............ Etat | Erreurs | Conflits
└── Parametres ................... Exploitations | Activites | Outils | Unites & devise | Integrations
* = masque si la capacite correspondante n'est pas activee
```

### 1.3 Regles de navigation

- L'entree est **toujours** le tableau de bord ; le menu est **filtre par capacites**.
- Profondeur maximale : 3 niveaux.
- Toute page liste → filtre (statut, date, parcelle) → recherche → creation en action primaire.
- Retour arriere conserve (filtres compris) ; un formulaire non enregistre avertit avant sortie.
- Sur mobile, la meme information est reorganisee en cartes empilees, jamais en tableau large.
- Les modules desactives ne sont **pas** affiches grises : ils n'existent pas pour l'utilisateur, sauf dans Parametres → Outils.

### 1.4 Accessibilite et mobile

| Critere | Exigence |
|---|---|
| Cibles tactiles | ≥ 44×44 px |
| Contraste | ≥ 4.5:1 (texte), ≥ 3:1 (grands elements) |
| Focus clavier | visible partout (web) |
| Labels | explicites, jamais de placeholder seul |
| Erreurs | associees au champ + resume en tete de formulaire |
| Chargement | squelette, jamais d'ecran blanc |
| Reseau faible | images differees, page ≤ 200 Ko utile au premier rendu |
| Lecture au soleil | mode clair a contraste renforce, pas de texte gris clair |

### 1.5 Micro-copie (regles)

- Verbe a l'infinitif pour les actions : « Enregistrer l'operation », « Affecter un ouvrier ».
- Aucune promesse : « Superficie **declaree** : 2,4 ha », « Marge **estimee** (donnees incompletes : 2 postes manquants) ».
- Vocabulaire agricole localise par pays (ex. « parcelle » vs « champ ») — cle i18n dediee.
- Jamais d'anglicisme technique a l'ecran (« provisioning » → « preparation de votre espace »).

---

## PARTIE 2 — UI Design (composants et principes visuels)

### 2.1 Systeme visuel

Alignement strict sur le design system existant du depot (tokens partages) : **aucune palette, aucune typo, aucun espacement nouveau**. Farm OS consomme les tokens existants et n'ajoute qu'une **teinte d'accent agricole** si un accent non utilise est libre — sinon l'accent neutre du hub metier.

### 2.2 Composants reutilises (existants)

| Besoin | Composant/pattern existant |
|---|---|
| Enveloppe applicative | layout du portail client (RBAC) |
| Navigation metier | hub metier + rail d'outils |
| Listes | table + pagination + filtres existants |
| Formulaires | champs du design system + messages d'erreur standardises |
| Etats vides | composant d'etat vide existant |
| Badges d'etat | badge module `available/trial/locked` |
| Bandeau hors ligne | composant de connexion existant |
| Cartes KPI | cartes du tableau de bord |

### 2.3 Composants **nouveaux** (minimum strict)

| Composant | Role | Specification |
|---|---|---|
| `FarmActivityPicker` | selection multiple d'activites (onboarding + parametres) | cases a cocher en cartes, icone, description, compteur de selection, accessible clavier |
| `FarmCapabilityList` | accepter/refuser les outils recommandes | 2 groupes (recommandes / optionnels), interrupteur + raison du refus facultative |
| `ParcelCard` | parcelle en liste | nom, reference, superficie **+ provenance**, culture courante, statut, nb d'operations en cours |
| `ParcelMap` | vue carte | rendu **uniquement** si geometrie presente ; sinon etat vide explicite « Aucune geometrie enregistree » |
| `AreaValue` | affichage de superficie | force l'affichage de la provenance (`declaree`, `importee`, `mesuree`) |
| `OperationTimeline` | sequence d'interventions | groupe par jour, statut, responsable |
| `OperationSheet` | creation/edition d'operation | type, parcelle, date, responsable, intrants, notes ; 1 action primaire |
| `StockMovementForm` | entree/sortie/ajustement | quantite + unite, lot, motif obligatoire si ajustement |
| `EquipmentMeterForm` | releve de compteur | valeur + source (`manual`/`imported`) ; refuse une valeur inferieure a la precedente |
| `LivestockEventForm` | mouvement d'effectif | type, quantite, animal si individuel ; refuse un depassement d'effectif |
| `HarvestLotCard` | lot de recolte | recolte, sorti, restant, statut, coherence visible |
| `CostBreakdown` | rentabilite | barres par poste, etiquette **reel/estime/manquant** sur chaque poste |
| `OfflineBanner` | etat de synchronisation | etat + compteur + derniere erreur + bouton rejeu |
| `SyncConflictPanel` | arbitrage de conflit | valeurs locale et serveur cote a cote, choix explicite, jamais automatique |
| `FarmEmptyState` | premiere utilisation | 1 illustration legere + 1 phrase + 1 action |

### 2.4 Regles de rendu des donnees incertaines

- Une valeur declaree affiche un marqueur discret « declaree ».
- Une valeur estimee affiche « estime » + info-bulle expliquant la methode.
- Une donnee manquante affiche « — » (jamais 0 ni vide ambigu).
- Une donnee externe affiche source + date de fraicheur ; perimee → « donnee ancienne ».
- Un cout inconnu n'est **jamais** rendu comme 0.

### 2.5 Tableau de bord — composition

```text
┌─ Bonjour, [Exploitation] ────────────────── [hors ligne: 3 en attente] ─┐
│ [3 taches aujourd'hui] [2 en retard] [1 stock bas] [1 entretien]        │  ← cartes KPI (max 4)
├─────────────────────────────────────────────────────────────────────────┤
│ Taches du jour ...................... (liste courte, 5 max)             │
│ Interventions en retard ............. (rouge discret, motif)            │
│ Etat des cultures ................... (par parcelle active)             │
│ Stocks a renouveler ................. (top 3 + lien)                    │
│ Equipements a entretenir ............ (top 3)                           │
│ Elevage ............................. (uniquement si capacite)          │
│ Ventes & depenses enregistrees ...... (periode courante)                │
│ Synchronisation ..................... (en attente / erreurs / conflits) │
└─────────────────────────────────────────────────────────────────────────┘
```

Regles : **max 4 cartes KPI**, sections **masquees** si vides *sauf* la premiere utilisation (ou elles affichent un etat vide actionnable), aucune section purement decorative.

### 2.6 Bloc « Prochaines etapes » (nouveaux utilisateurs)

```text
┌ Votre espace est pret ─────────────────────────────┐
│ ☐ Creer votre premiere parcelle                    │
│ ☐ Enregistrer une activite de production           │
│ ☐ Planifier votre premiere operation               │
│ ☐ Inviter un chef d'equipe                         │
└────────────────────────────────────────────────────┘
```

Chaque ligne est cliquable et disparait une fois faite (etat persiste, tenant-scoped).
