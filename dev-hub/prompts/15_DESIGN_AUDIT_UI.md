# 15 — Audit et Refonte Visuelle (Design Premium)

> **Quand l'utiliser :** Pour améliorer l'UI/UX d'une partie du projet (Vitrine, Dashboard Admin, Mobile) afin de la rendre "Premium".
> **Durée estimée :** Long (Créatif et itératif)
> **Prérequis :** Savoir utiliser l'intégration Google Stitch (MCP).

## Instructions

Tu es chargé d'auditer et d'améliorer le design d'une interface de Leopardo RH (ex: la vitrine Next.js, le dashboard Vue.js, ou une app Flutter). Le design doit être moderne, dynamique, et époustoufler l'utilisateur ("WOW effect").

1. **ANALYSE L'EXISTANT :**
   - Regarde le code actuel (composants, tokens CSS, Tailwind, couleurs).
   - Identifie les faiblesses : couleurs ternes, espacements irréguliers, manque d'animations, design trop "plat" (ex: vieilles cartes blanches avec ombres basiques).

2. **PROPOSE UN MOCKUP VISUEL AVEC STITCH MCP (OBLIGATOIRE) :**
   - N'utilise plus de simples images générées. Utilise l'outil `call_mcp_tool` pour invoquer `StitchMCP`.
   - Commence par récupérer le projet Leopardo RH existant via `list_projects` ou `get_project`.
   - Utilise `generate_screen_from_text` ou `edit_screens` pour générer une interface interactive, moderne et avec l'ADN visuel Leopardo.
   - Assure-toi que la maquette utilise les règles de design premium (glassmorphism, dégradés subtils, contrastes).
   - Présente cette maquette interactive à l'utilisateur et attends sa validation avant de coder.

3. **IMPLÉMENTATION (Après validation visuelle) :**
   - **Structure** : Utilise des composants sémantiques.
   - **Couleurs & Typographie** : Applique des palettes harmonieuses et des polices modernes (Inter, Roboto).
   - **Dynamisme** : Ajoute des micro-animations (hover states, transitions douces).
   - **Admin Dashboard** : Assure-toi d'utiliser les tokens `glass-*`, `premium-text`, `shadow-glass-*`.
   - **Mobile (Flutter)** : Utilise les tokens de `leopardo_core` (AppColors, AppTypography).

4. **FINALISATION :**
   - Vérifie que ton implémentation correspond à ton mockup validé.
   - Assure-toi que les vues restent responsives.
   - Crée une PR avec `Closes #<numero>` et inclus des descriptions textuelles claires de l'avant/après visuel.

## Notes

- Ne te contente pas d'un Minimum Viable Product (MVP) visuel. Le rendu final doit paraître "State of the Art".
- N'hésite pas à demander à l'utilisateur s'il a des préférences de couleurs ou de style pendant la phase de mockup.
