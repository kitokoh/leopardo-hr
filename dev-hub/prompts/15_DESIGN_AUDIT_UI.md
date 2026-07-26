# 15 — Audit et Refonte Visuelle (Design Premium)

> **Quand l'utiliser :** Pour améliorer l'UI/UX d'une partie du projet (Vitrine, Dashboard Admin, Mobile) afin de la rendre "Premium".
> **Durée estimée :** Long (Créatif et itératif)
> **Prérequis :** Savoir utiliser l'outil `generate_image` d'Antigravity.

## Instructions

Tu es chargé d'auditer et d'améliorer le design d'une interface de Leopardo RH (ex: la vitrine Next.js, le dashboard Vue.js, ou une app Flutter). Le design doit être moderne, dynamique, et époustoufler l'utilisateur ("WOW effect").

1. **ANALYSE L'EXISTANT :**
   - Regarde le code actuel (composants, tokens CSS, Tailwind, couleurs).
   - Identifie les faiblesses : couleurs ternes, espacements irréguliers, manque d'animations, design trop "plat" (ex: vieilles cartes blanches avec ombres basiques).

2. **PROPOSE UN MOCKUP VISUEL (OBLIGATOIRE) :**
   - Utilise ton outil `generate_image` pour générer une ou plusieurs propositions de la nouvelle interface.
   - Demande-toi : "Est-ce premium ? Y a-t-il du glassmorphism, un beau dark mode, des dégradés subtils ?"
   - Attends le feedback de l'utilisateur sur ces images avant de coder.

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
