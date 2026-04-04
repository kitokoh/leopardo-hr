# MVP-05 — Dashboard Web (Blade + Alpine.js)
# Agent : Claude Code / Cursor
# Durée : 4-6 heures
# Prérequis : MVP-04 vert (API estimation fonctionnelle)

---

## CE QUE TU FAIS

Créer un dashboard web minimal pour le manager. 2 pages Blade + Alpine.js + Tailwind CSS.
Pas de Vue.js, pas d'Inertia, pas de SPA. Des pages serveur classiques avec de l'interactivité légère.

---

## AVANT DE COMMENCER

1. Lis `PILOTAGE.md` (5 min)
2. Vérifie : `php artisan test` → 0 failure
3. Installe Tailwind CSS pour Blade :
   ```bash
   npm install -D tailwindcss @tailwindcss/forms
   npx tailwindcss init
   ```

---

## PAGES À CRÉER

### Page 1 — Dashboard Manager (`/dashboard`)

```
URL        : /dashboard
Auth       : Manager uniquement (redirect vers login si non connecté)
Contenu    :
  ┌──────────────────────────────────────────┐
  │  Leopardo RH — {Nom Entreprise}          │
  │  Aujourd'hui : {date}                    │
  ├──────────────────────────────────────────┤
  │  Card : Employés présents   X / Y        │
  │  Card : Total estimé jour   {montant}    │
  │  Card : En retard           X employés   │
  ├──────────────────────────────────────────┤
  │  Tableau :                               │
  │  Nom | Arrivée | Départ | Heures | Dû   │
  │  --- | ------- | ------ | ------ | ---   │
  │  Ahmed  08:02    17:15    9.2h    3200DA │
  │  Sara   —        —        —       Absent │
  │  ...                                     │
  ├──────────────────────────────────────────┤
  │  [Voir détail] sur chaque ligne          │
  └──────────────────────────────────────────┘
```

### Page 2 — Détail Employé (`/employees/{id}`)

```
URL        : /employees/{id}
Auth       : Manager uniquement
Contenu    :
  ┌──────────────────────────────────────────┐
  │  ← Retour | {Nom Employé} | {Poste}     │
  ├──────────────────────────────────────────┤
  │  Infos : email, téléphone, salaire       │
  ├──────────────────────────────────────────┤
  │  Quick Estimate :                        │
  │  [Date début] [Date fin] [Calculer]      │
  │  → Résultat : X jours, Y heures, Z DA   │
  │  → [Télécharger PDF]                     │
  ├──────────────────────────────────────────┤
  │  Historique pointages (30 derniers jours):│
  │  Date | Arrivée | Départ | Heures | Dû  │
  └──────────────────────────────────────────┘
```

### Page Login (`/login`)

```
URL        : /login
Contenu    : Email + Password + Bouton Connexion
Action     : POST /login → créer session web → redirect /dashboard
```

---

## FICHIERS À CRÉER

```
app/Http/Controllers/Web/DashboardController.php
app/Http/Controllers/Web/WebAuthController.php
app/Http/Controllers/Web/WebEmployeeController.php
resources/views/layouts/app.blade.php
resources/views/auth/login.blade.php
resources/views/dashboard.blade.php
resources/views/employees/show.blade.php
resources/views/components/stat-card.blade.php
resources/views/components/attendance-table.blade.php
resources/css/app.css
routes/web.php
```

---

## DESIGN

- **Couleurs :** Fond sombre (#0f172a), cartes sombres (#1e293b), accent vert (#10b981)
- **Typo :** Google Font "Inter"
- **Cards :** Coins arrondis, ombre légère, hover effect
- **Tableau :** Alternance de lignes, statut coloré (vert=ontime, rouge=absent, orange=late)
- **Responsive :** Mobile-first, le manager peut consulter depuis son téléphone

---

## AUTH WEB (session, pas token)

Pour le dashboard web, utiliser **Laravel session auth** (pas Sanctum tokens) :
```php
// Login web
Auth::attempt(['email' => $email, 'password' => $password]);

// Middleware
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    // ...
});
```

Le TenantMiddleware s'applique aussi aux routes web (même logique : charger company depuis l'user connecté).

---

## PORTE VERTE → MVP-06

```
[ ] /login → formulaire fonctionnel
[ ] /dashboard → affiche les employés + pointages du jour + totaux
[ ] /employees/{id} → détail avec historique + quick estimate
[ ] Quick estimate interactif avec Alpine.js
[ ] Bouton "Télécharger PDF" fonctionne
[ ] Design sombre, propre, responsive
[ ] php artisan test → 0 failure (pas de régression API)
```

---

## COMMIT

```
feat(web): dashboard Blade with daily attendance + estimation
feat(web): employee detail with quick estimate + PDF download
style(web): dark theme with Tailwind CSS + Inter font
```
