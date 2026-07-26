# Spécification : Refonte Visuelle Globale (Design System Premium)

**Date :** 2026-07-26
**Statut :** Validé / En attente de création d'issues

## 1. Objectif (Le "WOW Effect")
L'objectif est d'appliquer une refonte visuelle massive et globale sur l'ensemble de l'écosystème Leopardo RH (Web, Mobile, Kiosk, Vitrine). Le design doit être perçu comme hyper-moderne, extrêmement cohérent entre les différentes applications, et résolument "Premium" (Glassmorphism, animations fluides, contrastes profonds, palettes soigneusement choisies). 

Le processus s'appuiera systématiquement sur **Google Stitch (MCP)** pour valider les maquettes interactives avant toute écriture de code, garantissant ainsi une mise à l'échelle d'un Design System unique.

## 2. Périmètre d'intervention (Couverture 100% des Pages)

> [!IMPORTANT]
> **Règle absolue pour la pérennité du design :** Absolument TOUTES les pages des applications web (Admin, Vitrine) et mobiles (Employé, Manager, Super-Admin) doivent faire l'objet d'une maquette Stitch avant codage. L'architecture UI doit être modulaire pour garantir qu'à l'avenir, une mise à jour du Design System dans Stitch puisse être propagée facilement au code de n'importe quelle page.

La refonte touchera 4 surfaces distinctes. Chaque surface fera l'objet d'un lot (Issue) séparé. L'Issue ne sera considérée comme clôturée que lorsque **toutes les pages** de la surface auront été traitées via le pipeline `Stitch -> Code`.

### Pilier A : Le portail Web Client (Dashboard Admin & Employé)
- **Cible :** `front/admin-dashboard/` (Vue.js) et potentiellement le portail Next.js.
- **Actions :** Remplacement des cartes plates basiques par des surfaces `glass-*`. Implémentation du mode sombre natif. Modernisation des tableaux de bord (Cockpit) et de la navigation latérale.

### Pilier B : La Vitrine Commerciale (Site Public)
- **Cible :** `front/web/` (Next.js).
- **Actions :** Création d'un Hero Banner spectaculaire. Intégration de micro-animations au scroll. Mise en valeur visuelle des 3 applications mobiles (Employé, Manager, Admin) et du Kiosque. Refonte des formulaires (Signup/Demo) pour qu'ils paraissent ultra-fluides.

### Pilier C : La Suite d'Applications Mobiles
- **Cible :** `front/mobile_apps/` (Flutter : `leopardo_employee`, `leopardo_manager`, `leopardo_platform_admin`).
- **Actions :** Utilisation intensive de `leopardo_core` pour imposer une typographie commune (Inter) et une palette cohérente. Refonte du *StartupGate* (écrans de chargement) pour un rendu d'application native premium. Refonte des tuiles (cartes d'actions rapides).

### Pilier D : L'Application Kiosque Physique
- **Cible :** `front/zkteco-kiosk/` (Tablette pointage).
- **Actions :** Design ultra-minimaliste "Dark Mode" adapté aux tablettes industrielles, avec de gros boutons d'action clairs, feedback visuel et sonore immédiat lors du pointage (succès/échec).

## 3. Lignes Directrices du Design System (Stitch)

Toutes les interfaces générées via Stitch devront respecter ces règles fondamentales :
- **Cohérence des Couleurs :** Utilisation stricte du dictionnaire `COULEURS.md` (Ex: `#10B981` pour RH, `#F59E0B` pour Finance). Interdiction formelle de hardcoder des couleurs hors Design System.
- **Composants Réutilisables :** Les boutons, inputs, et cartes doivent avoir exactement les mêmes proportions (Border radius `ROUND_EIGHT`, ombres `shadow-glass`) d'une application à l'autre.
- **Clarté Cognitive :** Pas de surcharge. Les interfaces doivent "respirer" (espacements généreux, typographie hiérarchisée).
- **Processus de Validation :** `Audit → Maquette Stitch → Validation Utilisateur → Code`.

## 4. Issues à générer

Pour structurer ce travail monumental, nous allons générer 4 Issues (une par pilier) :
1. **[Design] Refonte UI/UX Premium - Portail Web Admin/Employé**
2. **[Design] Refonte UI/UX Premium - Vitrine Commerciale Next.js**
3. **[Design] Refonte UI/UX Premium - Suite Applications Mobiles Flutter**
4. **[Design] Refonte UI/UX Premium - Application Kiosque ZKTeco**
