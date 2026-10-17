# 04 — UIX : expérience utilisateur et interaction

**Base :** `main` @ `e9c5f986c`
Cette note couvre l'**expérience** (structure, interaction, états, accessibilité).
Le système visuel (tokens, composants, mouvement) vit dans `05_ui_design_system.md`.

---

## 1. Principes d'expérience

| # | Principe | Application concrète |
|---|---|---|
| P1 | **Une décision par écran** | Le parcours d'onboarding n'affiche qu'une étape à la fois ; l'étape suivante n'apparaît qu'après confirmation serveur. |
| P2 | **Le parcours ne perd jamais la saisie** | Chaque étape est persistée côté serveur avant de passer à la suivante ; un retour arrière ne réinitialise rien. |
| P3 | **Aucune promesse non tenue** | Un bouton désactivé est préférable à un lien mort ; un statut « bientôt » est explicite. |
| P4 | **Le temps réel est un état, pas un message** | La connexion, la synchronisation et le nombre d'opérations en attente sont visibles en permanence sur les surfaces terrain. |
| P5 | **L'erreur dit quoi faire** | Chaque erreur expose un message métier, une action possible, et un identifiant de corrélation pour le support. |
| P6 | **Le clavier et le lecteur d'écran sont des chemins de première classe** | Aucun parcours n'est accessible uniquement par survol ou par animation. |
| P7 | **La densité suit le contexte** | Console superadmin : dense, tabulaire. Terrain (POS, KDS, kiosque) : gros boutons, contraste élevé, aucune micro-interaction. |

---

## 2. Onboarding universel — structure d'écran

### 2.1 Écran « Choix du marché »

- Deux cartes pleine hauteur, l'une International (globe), l'autre Domestique (boutique).
- Chaque carte expose **3 points de valeur concrets**, pas de slogan.
- Sous-profil affiché en second temps, dans la même carte (segmented control), pour éviter
  un troisième écran.
- Le choix est réversible tant que l'étape 2 n'est pas confirmée.

### 2.2 Écran « Créer ou rejoindre »

| Option | Libellé | Effet |
|---|---|---|
| Créer | « Créer l'espace de mon entreprise » | entre dans le parcours de création |
| Rejoindre | « Rejoindre une entreprise existante » | va à la connexion, puis à la demande d'accès |

Règle : si l'e-mail saisi correspond déjà à un compte rattaché à un tenant, l'option
« Créer » affiche un avertissement explicite et propose « Rejoindre » en action principale.

### 2.3 Assistant en 8 étapes

```
[1] Profil      [2] Entreprise   [3] Établissement   [4] Modules
[5] Données     [6] Équipe       [7] Applications    [8] Première opération
```

Chaque écran respecte la même anatomie :

```
┌─────────────────────────────────────────────────────────┐
│ Fil d'étapes : 1 ✓ · 2 ✓ · 3 ● · 4 · 5 · 6 · 7 · 8     │
├─────────────────────────────────────────────────────────┤
│ Titre de l'étape + phrase d'intention (1 ligne)         │
│                                                         │
│ [ Contenu : un seul groupe de champs ou un seul choix ]  │
│                                                         │
│ Aide contextuelle (facultative, repliable)              │
├─────────────────────────────────────────────────────────┤
│ [Retour]                        [Continuer]  (ou [Passer])│
└─────────────────────────────────────────────────────────┘
```

- **`Passer`** uniquement pour les étapes non requises (données initiales, invitations).
- **`Continuer`** désactivé tant que la validation locale échoue, mais **jamais** masqué.
- La progression est celle du **serveur** : revenir en arrière ne décrémente pas.
- Quitter et revenir : la bannière « Reprendre où vous en étiez — étape 3 sur 8 » avec
  l'horodatage de la dernière sauvegarde.

### 2.4 États d'étape

| État | Représentation |
|---|---|
| non commencée | cercle vide, libellé neutre |
| en cours | cercle plein, anneau de focus |
| validée | coche + horodatage au survol |
| ignorée | tiret, libellé « Passée » |
| bloquée | icône d'alerte + motif (« dépend du module X ») |

### 2.5 Sortie de parcours

Dernier écran = **première opération réelle** (ex. créer un produit et enregistrer une
vente test, ou créer un employé et un pointage). Le succès affiche le résultat réel
(référence de la vente, identifiant de l'employé), pas un écran de félicitations vide.

---

## 3. Écrans d'application (Mon Logiciel)

- **Grille de tuiles** filtrée par modules actifs ; une tuile = icône, nom, plateformes
  disponibles, badge de statut.
- **Badges de statut** (toujours avec texte, jamais couleur seule) :
  - `Disponible` — au moins un canal réel
  - `Accès pilote` — distribution accompagnée
  - `Non distribuée` — aucun canal
- **Fiche dépliable** : rôle, profils, fonctionnalités, prérequis, connexion, permissions,
  mise en service. `aria-expanded` / `aria-controls`.
- **Zone d'état par tenant** (à ajouter) : « configurée le … », « appareil enrôlé : 2 »,
  action directe vers l'écran concerné.
- **Aucune** tuile ne mène à une page 404.

---

## 4. Surfaces terrain (POS / KDS / kiosque)

| Contrainte | Règle |
|---|---|
| Taille de cible | ≥ 48 px, sans exception |
| Contraste | ≥ 7:1 sur le texte principal (WCAG AAA) |
| Retour d'action | immédiat et local (état du bouton), jamais une notification globale |
| Hors-ligne | bandeau persistant en haut : état + nombre d'opérations en attente + « Réessayer » |
| Erreur de synchronisation | ligne d'opération marquée, motif lisible, action « Réessayer » ou « Signaler » |
| Temps de frappe | aucune confirmation modale pour une action réversible ; confirmation obligatoire pour une action financière irréversible |

---

## 5. Console superadmin

- **Densité** : tableaux paginés côté serveur, colonnes triables, filtres persistés en URL.
- **Trois niveaux d'information** : liste → détail tenant → journal d'événements.
- **Aucune donnée métier client** en clair dans les listes : agrégats et statuts seulement.
  L'accès au détail passe par une action explicite, permissionnée et **journalisée**.
- **Diagnostic** : un panneau unique par tenant regroupant provisioning, intégrations,
  appareils, synchronisation, dernière erreur.
- **Impersonation** : bandeau permanent, non masquable, avec bouton de sortie.

---

## 6. Accessibilité

| Exigence | Mise en œuvre |
|---|---|
| Focus visible | anneau explicite sur tout élément interactif, jamais supprimé |
| Ordre de tabulation | suit l'ordre visuel ; aucune zone piégée hors dialogue |
| Dialogues | `role="dialog"`, `aria-modal`, titre lié, Échap, focus piégé puis restitué |
| Information | jamais portée par la couleur seule (icône + libellé) |
| Mouvement | `prefers-reduced-motion` neutralise rotation, fondu, parallaxe |
| Formulaires | label associé, erreur liée par `aria-describedby`, résumé d'erreurs en tête |
| Navigation clavier | tout parcours réalisable sans souris |

---

## 7. Microcopie

| Contexte | À éviter | À écrire |
|---|---|---|
| Échec réseau | « Une erreur est survenue » | « Connexion perdue. 3 opérations attendent d'être envoyées. » |
| Provisionnement en cours | « Chargement… » | « Création de votre espace — cette étape prend quelques secondes. » |
| App non distribuée | « Bientôt disponible » | « Aucun canal de distribution public aujourd'hui. Demander un accès pilote. » |
| Permission refusée | « Accès refusé » | « Votre rôle ne permet pas cette action. Demandez à un responsable. » |
| Erreur serveur | trace technique | « L'action n'a pas abouti. Réessayez ; si cela persiste, transmettez la référence ABC-123. » |

Règle : aucun jargon technique côté tenant ; le jargon reste dans la console superadmin,
accompagné d'une explication.

---

## 8. Parcours à valider sur le terrain

Voir `10_field_validation_plan.md` — chaque parcours ci-dessus donne lieu à un script
d'observation (tâche, temps, erreurs, points de blocage) avec un opérateur réel du métier.
