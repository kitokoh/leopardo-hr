# Audit marche et produit - Leopardo HR 2026

## 1. Resume executif

Leopardo HR dispose maintenant d'un socle technique solide: API Laravel multi-tenant, apps mobiles Employee/Manager/Platform Admin, Firebase Distribution, OpenAPI, observabilite, launch-readiness, workflows CI et preuves de smoke Render. Le depot indique un **Go technique controle** autour de 93/100, avec une reserve majeure: recette terrain sur vrais appareils et validation commerciale.

Le marche HR tech 2026 pousse dans le sens de Leopardo, mais impose de mieux formuler la valeur: les acheteurs ne veulent pas "plus de modules", ils veulent moins de dispersion, moins de taches repetitives, une paie fiable, une presence terrain fiable, une experience employe simple et une IA gouvernee.

## 2. Sources marche utilisees

- S&P Global Market Intelligence, HR technology market forecast 2026: le marche reste porte par HCM, employee experience, people analytics et talent intelligence, mais les acheteurs gardent une priorite forte sur les fondamentaux operationnels: cout, conformite et performance workforce. Source: https://www.spglobal.com/market-intelligence/en/news-insights/research/2026/02/hr-tech-market-2025
- Gartner, AI in HR 2025: l'IA se diffuse dans tout le cycle employe, mais doit etre gouvernee et reliee aux objectifs business. Source: https://www.gartner.com/en/newsroom/press-releases/2025-10-16-ai-in-hr-separate-hype-from-reality-to-achieve-business-goals
- Gartner Digital Markets, HR software buyer insights 2025: investissements prevus en succession, HR analytics, LMS, talent, WFM/HCM et employee engagement; integrations et IA influencent fortement les achats. Source: https://www.gartner.com/en/digital-markets/insights/hr-software-trends-buyer-insights-2025
- Deloitte 2025 Global Human Capital Trends: l'IA transforme les organisations mais la valeur vient de l'equilibre humain/technologie, de la capacite a agir et de la redefinition des competences. Source: https://www.deloitte.com/us/en/insights/topics/talent/human-capital-trends.html
- Nucleus Research, SMB HCM Technology Value Matrix 2026: les PME attendent automatisation, analytics embarques, assistance IA et architectures unifiees autour HCM, paie, finance et workforce management. Source: https://nucleusresearch.com/research/single/smb-hcm-technology-value-matrix-2026/
- Paycom 2026 HR priorities: les organisations souffrent de workflows repetitifs, de multiples fournisseurs et de donnees HCM fragmentees. Source: https://www.paycom.com/about/press-room/automation-tech-forward-solutions-dominate-hr-priorities-for-2026-paycom-report-reveals/

## 3. Lecture marche

### Ce que le marche achete vraiment

1. Unification: moins d'outils, moins de double saisie, moins de fichiers Excel.
2. Paie et presence fiables: erreurs de paie, absences et horaires sont des douleurs immediates.
3. Mobile et self-service: les employes veulent agir sans attendre le RH.
4. Analytics actionnables: pas seulement des graphiques, mais des alertes et decisions.
5. Automatisation: workflows repetitifs, approvals, documents, notifications, onboarding.
6. Conformite: donnees RH, audit, RBAC, pays, paie, RGPD et gouvernance IA.
7. IA utile mais prudente: assistant RH, analyse anomalies, aide decisionnelle, pas de boite noire.

### Opportunite Leopardo

Leopardo est bien place sur les PME terrain: securite privee, BTP, logistique, restauration, services multi-sites, cabinets qui gerent plusieurs clients. Ces segments ont des douleurs quotidiennes plus fortes que les bureaux classiques: presence, horaires, absences, avances, documents, communication, preuve terrain.

### Risque strategique

Le risque n'est plus seulement technique. Le risque principal est le **positionnement trop large**. "Plateforme RH complete" est une categorie encombrée. "Mobile-First Company OS pour PME terrain" est plus defendable.

## 4. Audit produit a partir du depot

### Forces livrees

- API Laravel multi-tenant avec separation public/tenant.
- Apps mobiles separees Employee, Manager/RH et Platform Admin.
- Demo users Render et pages testeur/API Explorer.
- Presence, pointage GPS/timezone, multi-punch, taches, absences, avances, paie, documents PDF.
- Notifications, preferences, CommunicationService, FCM et garde runtime anti-ecran noir.
- Platform admin: login super-admin, creation client, fiche client, subscription, features, health.
- OpenAPI canonique et documentation `/docs`.
- Release readiness, observabilite, backup/restore, security docs, RBAC matrix.
- CI GitHub Actions consideree source de verite.

### Manques critiques pour lancement marche

- Recette appareil physique documentee par version Firebase, modele Android, capture et utilisateur demo.
- Parcours demo mobile Platform Admin corrige cote UI: le compte demo doit remplir `password123`.
- Packaging commercial final: offres simples, limites claires, ROI par secteur.
- Scripts de vente et objections par persona.
- Preuves de charge p95 et cout par tenant/client.
- Domaine, emails transactionnels, onboarding commercial et support.
- IA gouvernee en mode "copilote RH" avec limites, journalisation et consentement.
- Portail developpeur/sandbox partenaire encore futur.

## 5. Verdict

Leopardo peut etre positionne pour un lancement controle, mais pas encore pour une explosion client sans discipline commerciale et operations. La priorite n'est pas d'ajouter 20 modules; c'est de transformer les modules existants en parcours vendables, mesurables et prouvables.

Score actuel estime:

| Axe | Score |
|---|---:|
| Socle technique | 9/10 |
| Readiness mobile | 8/10 |
| Readiness API | 9/10 |
| Readiness operations | 8.5/10 |
| Positionnement marche | 7/10 |
| Monétisation | 6.5/10 |
| Preuves terrain | 6/10 |

Decision: **Go controle pour pilotes payants**, pas encore "scale sans reserve".

