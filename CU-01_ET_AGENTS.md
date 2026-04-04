# CU-01 ET AGENTS — CADRE DE COLLABORATION
# Version 4.0.1 | Avril 2026

---

## FRONTEND — RÈGLES DE RESPONSABILITÉ

FRONTEND — QUI FAIT QUOI (règle définitive)

Cursor (agent principal frontend) :
  → Tout le code Vue.js dans api/resources/js/Pages/
  → Tout le code Vue.js dans api/resources/js/Components/
  → Tailwind CSS + PrimeVue
  → Inertia.js (données venant des Controllers Laravel)
  → Prérequis : CC-02 backend terminé

v0.dev (landing page uniquement) :
  → api/resources/views/landing/ (Blade + Tailwind statique)
  → Pas de logique métier — HTML/CSS uniquement
  → Livrable : composants Blade prêts à intégrer

Claude.ai (toi, dans cette conversation) :
  → Conception et revue uniquement
  → Jamais de génération de code frontend directe
  → Valide les livrables Cursor et v0.dev avant merge

Règle de conflit :
  Si Cursor et v0.dev produisent des styles contradictoires,
  Cursor a la priorité (il gère l'app, v0.dev gère la vitrine).
