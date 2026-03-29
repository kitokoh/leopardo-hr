# TUNNEL DE VENTE ET PRÉSENTATION — LEOPARDO RH
# Version 1.0 | Mars 2026

---

## 1. LANDING PAGE (Vitrine commerciale)
Située à la racine du domaine `https://leopardo-rh.com`.

### Sections requises :
- **Hero Section** : Phrase d'accroche percutante ("Simplifiez vos RH, optimisez vos performances").
- **Problématique** : "Marre des feuilles de présence papier ? Des erreurs de paie ?"
- **Solution** : Présentation des 4 piliers (Pointage, Absences, Paie, Tâches).
- **Social Proof** : Logos de clients fictifs/réels et témoignages.
- **Prix (Plans)** : Starter (Gratuit), Business, Enterprise.
- **FAQ** : Questions sur la sécurité, le biométrique ZKTeco, etc.

---

## 2. TUNNEL DE VÉRONICATION (Lead Gen)

### Parcours Prospect :
1. **CTA "Essai gratuit 14 jours"** ou "Demander une démo".
2. **Formulaire de contact léger** :
   - Nom complet
   - Email professionnel
   - Nom de l'entreprise
   - Nombre d'employés
   - Pays (pour le modèle RH)
3. **Qualification automatique** :
   - < 50 employés → Redirection vers auto-onboarding (Phase 2).
   - > 50 employés → Message "Un conseiller vous contactera sous 24h" + Email envoyé au Super Admin.

---

## 3. INTERFACE "SITE PUBLIC" DANS LARAVEL

Le site vitrine sera géré directement dans le dossier `api/` via des contrôleurs et vues Blade (SEO friendly) pour éviter de multiplier les repos.

- **URL `/`** : Accueil.
- **URL `/pricing`** : Comparatif des plans.
- **URL `/contact`** : Formulaire de support/ventes.
- **URL `/blog`** : Articles sur le droit du travail (SEO).

---

## 4. STRATÉGIE D'ACQUISITION (Funnel)

### Sources :
- **SEO** : Mots clés "Logiciel RH Algérie", "Pointage biométrique Maroc", etc.
- **LinkedIn** : Ciblage des DRH et Dirigeants d'entreprises 10-200 employés.
- **Partenariats** : Revendeurs de matériel ZKTeco.

### Conversion :
- Pixel Meta et Google Analytics installés pour le retargeting.
- Abandon de panier (si le prospect ne finit pas l'inscription) → Email de relance automatique à H+2.
