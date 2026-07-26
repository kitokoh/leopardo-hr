# 06 — Audit Admin Dashboard (Vue.js)

> **Quand l'utiliser :** Pour auditer le dashboard d'administration plateforme : composants, vues, API calls, design system, accessibilité.
> **Durée estimée :** Moyen (30 min)
> **Prérequis :** Être sur `main` à jour

## Instructions

```
Agis en tant qu'auditeur frontend senior pour le projet Leopardo RH situé dans c:\Users\cheic\Downloads\gestionemployer.

Commence par lire AGENTS.md (notamment les sections sur admin-dashboard et les tokens glass-*/premium-text).

Audite le dashboard admin dans front/admin-dashboard/.

Vérifie ces 8 axes :

1. VUES : Liste toutes les vues dans src/views/. Vérifie qu'elles correspondent à des routes dans le router. Identifie les vues orphelines ou les routes sans vue.

2. COMPOSANTS : Liste les composants dans src/components/. Vérifie la réutilisabilité, les props typées, les composants dupliqués.

3. DESIGN SYSTEM : Vérifie que les composants utilisent les primitives premium (tokens glass-*, premium-text, shadow-glass-*, surfaces card) et non les anciennes cartes plates (rounded-lg bg-white shadow). Signale toute régression vers l'ancien design.

4. API CALLS : Vérifie src/api/ ou src/services/. Les appels API doivent utiliser une base URL configurable (pas de localhost hardcodé), gérer les erreurs, et avoir des types TypeScript.

5. ÉTAT : Vérifie la gestion d'état (Pinia/Vuex). Identifie les états globaux mal gérés ou les fuites de mémoire potentielles.

6. COCKPIT DASHBOARD : Le DashboardView.vue doit rester une surface d'exécution admin avec : workflows de création/activation client, demandes entrantes, risques, abonnements, système et intégrations. Vérifier qu'il n'a pas été réduit à des KPI passifs.

7. BUILD & LINT : Vérifie que `npm run build` et `npm run lint` passent sans erreur. Vérifie le package.json pour les dépendances obsolètes.

8. RESPONSIVE : Vérifie que les vues principales sont responsive (mobile-friendly pour les admins en déplacement).

Produis un rapport avec 🔴🟡🟢 et crée des issues pour les 🔴.
```

## Notes

- Le dashboard admin est en Vue.js (pas React/Next.js comme la vitrine).
- Depuis v4.16.250, la refonte premium est basée sur les tokens glass-*.
- Ne pas confondre admin-dashboard (plateforme interne) et web/ (vitrine publique).
