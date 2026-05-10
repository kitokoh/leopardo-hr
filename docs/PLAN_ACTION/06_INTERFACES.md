# 06 — INTERFACES (Web, Mobile, Kiosk)

**Objectif :** Planifier les ecrans et composants a creer sur chaque interface pour accompagner les nouveaux modules API.

---

## 1. Admin Dashboard (admin-dashboard/ — Next.js)

### Ecrans existants

- Login / Auth
- Cockpit plateforme (companies health, demandes, plans)
- Detail entreprise + abonnement

### Ecrans a creer

#### Module Paie

| Ecran | Route | Description |
|-------|-------|-------------|
| Structures salariales | `/payroll/structures` | Liste + CRUD structures et composants |
| Configuration pays | `/payroll/country-config` | Tax slabs, cotisations par pays |
| Runs de paie | `/payroll/runs` | Liste des runs, creer, calculer, valider |
| Detail run | `/payroll/runs/[id]` | Resume + liste bulletins |
| Bulletin PDF | `/payroll/runs/[id]/slips/[slipId]` | Visualiser/telecharger PDF |
| Export bancaire | `/payroll/runs/[id]/export` | Generer et telecharger virement |

#### Module Conges avances

| Ecran | Route | Description |
|-------|-------|-------------|
| Politiques conges | `/leave/policies` | CRUD politiques par type |
| Soldes conges | `/leave/balances` | Tableau des soldes par employe |
| Approbations | `/leave/approvals` | File des approbations en attente |

#### Module Contrats

| Ecran | Route | Description |
|-------|-------|-------------|
| Liste contrats | `/contracts` | Filtre par statut, type, expiration |
| Detail contrat | `/contracts/[id]` | Contrat + avenants + PDF |
| Alertes expiration | `/contracts/expiring` | Dashboard des contrats a renouveler |

#### Module Recrutement

| Ecran | Route | Description |
|-------|-------|-------------|
| Offres d'emploi | `/recruitment/postings` | CRUD offres |
| Pipeline candidats | `/recruitment/postings/[id]/pipeline` | Kanban des candidats par statut |
| Detail candidat | `/recruitment/applicants/[id]` | Profil + entretiens + notes |
| Entretiens | `/recruitment/interviews` | Calendrier des entretiens |

#### Module Formation

| Ecran | Route | Description |
|-------|-------|-------------|
| Catalogue formations | `/training/courses` | CRUD cours |
| Sessions | `/training/sessions` | Planification + inscriptions |
| Suivi completion | `/training/completion` | Taux par employe/departement |

#### Module Tracking vehicules

| Ecran | Route | Description |
|-------|-------|-------------|
| Flotte | `/fleet` | Vue d'ensemble vehicules |
| Carte live | `/fleet/map` | Carte temps reel (Leaflet/Mapbox) |
| Detail vehicule | `/fleet/vehicles/[id]` | Position, trajets, maintenance |
| Alertes | `/fleet/alerts` | Liste alertes non acquittees |
| Rapports | `/fleet/reports` | Kilometrage, carburant, maintenance |

#### Module IA

| Ecran | Route | Description |
|-------|-------|-------------|
| Chat IA | Widget flottant (toutes pages) | Interface chat avec Leo |
| Analytics IA | `/ai/analytics` | Usage, couts, outils, erreurs |
| Tool Registry | `/ai/tools` | Configuration outils IA |

#### Module Rapports

| Ecran | Route | Description |
|-------|-------|-------------|
| Dashboard RH | `/reports` | Effectifs, turnover, absenteisme |
| Rapport personnalise | `/reports/custom` | Generateur de rapport avec filtres |

#### Module Audit

| Ecran | Route | Description |
|-------|-------|-------------|
| Journal d'audit | `/audit` | Recherche et filtrage des logs |

#### Module Webhooks

| Ecran | Route | Description |
|-------|-------|-------------|
| Webhooks | `/settings/webhooks` | CRUD endpoints + test + historique |

### Composants partages a creer

- `DataTable` — Table avec tri, filtres, pagination, export CSV
- `FormBuilder` — Formulaire dynamique base sur schema
- `StatusBadge` — Badge colore selon statut
- `MetricCard` — Carte KPI (nombre, variation, tendance)
- `ChartWidget` — Graphique (Recharts/Chart.js)
- `MapWidget` — Carte interactive (Leaflet)
- `ChatWidget` — Widget IA flottant
- `PDFViewer` — Visualiseur de bulletin/contrat inline
- `ApprovalFlow` — Visualisation workflow d'approbation
- `KanbanBoard` — Vue Kanban (recrutement, taches)

### Taches

- [ ] **T-WEB-01** : Creer le layout et la navigation pour les nouveaux modules
- [ ] **T-WEB-02** : Implementer les composants partages (DataTable, MetricCard, etc.)
- [ ] **T-WEB-03** : Ecrans paie (structures, runs, bulletins, export)
- [ ] **T-WEB-04** : Ecrans conges (politiques, soldes, approbations)
- [ ] **T-WEB-05** : Ecrans contrats (liste, detail, alertes)
- [ ] **T-WEB-06** : Ecrans recrutement (offres, pipeline Kanban, entretiens)
- [ ] **T-WEB-07** : Ecrans formation (catalogue, sessions, suivi)
- [ ] **T-WEB-08** : Ecrans tracking/flotte (carte live, vehicules, alertes)
- [ ] **T-WEB-09** : Widget chat IA
- [ ] **T-WEB-10** : Ecrans rapports RH
- [ ] **T-WEB-11** : Ecrans audit + webhooks

---

## 2. Site Vitrine (web/ — Next.js)

### Existant

- Landing page multilingue (FR/EN/TR/AR)
- Pages features

### A ajouter — Blog / CMS

Le site vitrine a besoin d'un blog pour publier du contenu marketing et ameliorer le SEO.

#### Option recommandee : Blog statique avec MDX

Pas de CMS lourd. Des fichiers Markdown dans le repo, rendus par Next.js.

```
web/
  content/
    blog/
      fr/
        2026-05-10-pointage-terrain-pme.mdx
        2026-05-15-anomalies-presence.mdx
      ar/
        2026-05-10-pointage-terrain-pme.mdx
      en/
        ...
  src/
    app/
      blog/
        page.tsx                  # Liste articles
        [slug]/
          page.tsx                # Article individuel
    lib/
      blog.ts                    # Utilitaire lecture MDX
```

#### Structure d'un article

```mdx
---
title: "Comment detecter les anomalies de pointage en PME"
description: "Guide pratique pour les managers terrain"
date: "2026-05-10"
author: "Equipe Leopardo"
tags: ["pointage", "anomalies", "PME"]
locale: "fr"
image: "/blog/anomalies-cover.jpg"
---

# Contenu de l'article en MDX

Avec des composants interactifs si besoin :

<DemoVideo src="/videos/demo-anomalies.mp4" />
```

#### Composants blog

- `BlogList` — Grille d'articles avec filtres par tag et langue
- `BlogPost` — Template article avec table des matieres, partage social
- `BlogSEO` — Meta tags, Open Graph, schema.org Article
- `Newsletter` — Formulaire d'inscription newsletter (integrer Mailchimp/Brevo)
- `RelatedPosts` — Articles similaires

#### Pages supplementaires

| Page | Route | Description |
|------|-------|-------------|
| Blog | `/blog` | Liste des articles |
| Article | `/blog/[slug]` | Article individuel |
| Pricing | `/pricing` | Page pricing publique (grille 29/79/199) |
| Demo request | `/demo` | Formulaire de demande de demo |
| Case studies | `/cases` | Cas clients (quand disponibles) |
| Changelog | `/changelog` | Historique des versions public |

### Taches

- [ ] **T-VITRINE-01** : Mettre en place le systeme blog MDX (contentlayer ou @next/mdx)
- [ ] **T-VITRINE-02** : Creer les templates BlogList et BlogPost
- [ ] **T-VITRINE-03** : Creer la page Pricing publique
- [ ] **T-VITRINE-04** : Creer la page Demo request
- [ ] **T-VITRINE-05** : Ajouter le SEO (sitemap.xml, robots.txt, schema.org)
- [ ] **T-VITRINE-06** : Ecrire les 5 premiers articles de blog (FR + AR)
- [ ] **T-VITRINE-07** : Page changelog publique
- [ ] **T-VITRINE-08** : Formulaire newsletter

---

## 3. Mobile Flutter

### Ecrans existants

- Login
- Pointage (check-in/out)
- Historique pointage
- Equipe

### Ecrans a creer

| Ecran | Description | Module |
|-------|-------------|--------|
| Mes bulletins de paie | Liste + PDF viewer | Paie |
| Mes conges | Soldes + demande + historique | Conges |
| Mon contrat | Detail contrat actif | Contrats |
| Mes formations | Inscriptions + catalogue | Formation |
| Mes prets | Detail + echeancier | Prets |
| Mes notes de frais | Soumettre + historique | Frais |
| Chat IA (Leo) | Interface chat avec Leo | IA |
| Voice IA | Bouton micro pour parler a Leo | IA |
| Notifications push | Liste + actions rapides | Notifications |
| Position vehicule | Carte pour les chauffeurs | Tracking |
| Organigramme | Hierarchie visuelle | Org |

### Composants partages Flutter

- `LeoCard` — Carte UI standard
- `LeoDataTable` — Table responsive
- `LeoStatusChip` — Chip colore par statut
- `LeoEmptyState` — Etat vide avec illustration
- `LeoChatBubble` — Bulle de chat IA
- `LeoVoiceButton` — Bouton micro avec animation
- `LeoMapView` — Carte (flutter_map ou google_maps)
- `LeoPDFViewer` — Visualiseur PDF inline

### Architecture mobile

```
lib/
  models/          # Modeles Freezed
  repositories/    # Abstraction data access
  blocs/           # Bloc state management
  screens/         # Ecrans par module
    payroll/
    leave/
    contracts/
    training/
    expenses/
    ai_chat/
    fleet/
  widgets/         # Composants partages
  services/        # API client, notifications, etc.
  l10n/            # Localization
```

### Taches

- [ ] **T-MOB-01** : Ecran mes bulletins de paie + PDF viewer
- [ ] **T-MOB-02** : Ecran mes conges (soldes, demande, historique)
- [ ] **T-MOB-03** : Ecran mon contrat
- [ ] **T-MOB-04** : Ecran mes formations
- [ ] **T-MOB-05** : Ecran mes notes de frais avec camera (justificatifs)
- [ ] **T-MOB-06** : Chat IA (interface chat + integration /api/ai/chat)
- [ ] **T-MOB-07** : Voice IA (micro + pipeline audio)
- [ ] **T-MOB-08** : Notifications push (Firebase Cloud Messaging)
- [ ] **T-MOB-09** : Carte vehicule (pour chauffeurs)
- [ ] **T-MOB-10** : Organigramme visuel

---

## 4. Kiosk ZKTeco

### Existant

- Enregistrement kiosk
- Pointage biometrique

### A ajouter

| Fonctionnalite | Description |
|----------------|-------------|
| Affichage info employe | Photo, nom, departement apres pointage |
| Message du jour | Annonces entreprise sur l'ecran kiosk |
| Solde conges | Afficher le solde restant apres pointage |
| QR Code pointage | Alternative au biometrique pour visiteurs |

### Taches

- [ ] **T-KIOSK-01** : Ecran info employe post-pointage
- [ ] **T-KIOSK-02** : Systeme d'annonces sur kiosk
- [ ] **T-KIOSK-03** : Affichage solde conges
- [ ] **T-KIOSK-04** : Pointage par QR code
