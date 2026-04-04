# 🛡️ GARDE-FOUS — RÈGLES DE SURVIE DU PROJET
# Version 5.0 | 04 Avril 2026
# Ce fichier est lu par tous les agents IA et le chef de projet
# TOUT le monde doit respecter ces règles sans exception

---

## GARDE-FOU 1 — ANTI SCOPE CREEP (le plus important)

### La question magique
Avant d'ajouter quoi que ce soit, poser cette question :

> **"Est-ce que Murat (restaurateur, 8 employés) a besoin de ça pour savoir
> ce qu'il doit payer aujourd'hui ?"**

- OUI → on le fait
- NON → Phase 2. Pas de discussion.

### Liste noire MVP (features INTERDITES en Phase 1)
```
❌ Absences / Congés / Workflow approbation
❌ Paie complète / Bulletins officiels
❌ Avances sur salaire
❌ Tâches / Projets / Kanban
❌ Évaluations de performance
❌ ZKTeco / Biométrie
❌ Multi-langue (AR, EN, TR)
❌ RTL arabe
❌ Multi-pays (MA, TN, FR, TR, SN, CI)
❌ SSE / Notifications temps réel
❌ Notifications push (FCM)
❌ Export bancaire (SEPA, CIH, etc.)
❌ Import CSV employés
❌ Vue.js / Inertia / PrimeVue
❌ Redis / Horizon
❌ Mode schema Enterprise (multitenancy)
❌ 5 sous-rôles gestionnaire (rh, dept, comptable, superviseur)
❌ Photo pointage
❌ QR Code pointage
❌ Onboarding 4 étapes
❌ API publique
```

Si un agent IA tente d'implémenter un de ces items → **STOP immédiat**.
Répondre : "Hors scope MVP. Réfère-toi à PILOTAGE.md."

---

## GARDE-FOU 2 — ANTI SUR-DOCUMENTATION

### Symptômes de sur-documentation
- Tu écris un nouveau fichier .md au lieu de coder → ⚠️
- Tu crées une nouvelle version d'un document existant → ⚠️
- Tu passes plus d'1 heure sur un document sans écrire de code → ⚠️
- Le CHANGELOG a plus de lignes que le code → 🔴

### Règle
```
Maximum 30 minutes de documentation par session de code.
La documentation suit le code, jamais l'inverse.
Exception : ce fichier et PILOTAGE.md (documents de gouvernance).
```

---

## GARDE-FOU 3 — PORTE VERTE OBLIGATOIRE

### Règle
```
JAMAIS passer au prompt MVP-XX+1 si le prompt MVP-XX a des tests rouges.

Vérification : php artisan test
Résultat attendu : 0 failure, 0 error

Si des tests échouent :
1. Les corriger AVANT de continuer
2. Ne PAS commenter les tests pour les "faire passer"
3. Ne PAS créer de TODO/FIXME — corriger maintenant
```

### Escalation
Si un test est impossible à faire passer :
1. Documenter le blocage dans PILOTAGE.md → section "Blocages"
2. Demander aide au chef de projet
3. NE PAS contourner le test

---

## GARDE-FOU 4 — ISOLATION TENANT (sécurité critique)

### Règle
```
Le test TenantIsolationTest ne doit JAMAIS être rouge.
Un test d'isolation qui échoue = ARRÊT COMPLET du développement.

Si une modification fait échouer TenantIsolationTest :
1. Revert immédiat (git revert)
2. Comprendre pourquoi
3. Corriger
4. Re-tester
5. Seulement alors : continuer
```

### Code interdit dans les controllers tenant
```php
// ❌ INTERDIT — écrire ça = bug de sécurité
Employee::where('company_id', $id)->get();
DB::table('employees')->where('company_id', $companyId)->get();
$request->input('company_id'); // dans un controller tenant

// ✅ CORRECT — le scope s'en occupe automatiquement
Employee::all();
Employee::where('status', 'active')->get();
Employee::find($id); // trouvera UNIQUEMENT dans la company du user connecté
```

---

## GARDE-FOU 5 — ANTI COMPLEXITÉ PRÉMATURÉE

### Règle
```
Choisir TOUJOURS la solution la plus simple qui fonctionne.

Simple                          Au lieu de
──────────────────────────────────────────────
File cache                      Redis
Sync queue                      Async + Horizon
Blade + Alpine.js               Vue.js + Inertia
2 rôles (manager/employee)      7 rôles RBAC
DomPDF synchrone                Queue + PDF async
Session auth (web)              SPA + token
company_id WHERE (shared)       SET search_path (schema)
```

### Quand upgrader vers la solution complexe
```
Redis         → quand > 50 utilisateurs simultanés
Queue async   → quand un endpoint prend > 3 secondes
Vue.js        → quand > 5 pages web nécessaires
7 rôles       → quand les clients le demandent
Schema mode   → quand un client Enterprise paie pour
```

---

## GARDE-FOU 6 — VALIDATION MARCHÉ EN PARALLÈLE

### Règle
```
Le développement NE DOIT PAS bloquer la validation marché.
La validation marché NE DOIT PAS bloquer le développement.
Les deux avancent EN MÊME TEMPS.
```

### Checklist validation marché (responsable = Chef de projet)
```
[ ] Semaine 1 : Landing page en ligne
[ ] Semaine 2 : LinkedIn Ads lancé (50-100€)
[ ] Semaine 3 : 5+ inscriptions waiting list
[ ] Semaine 4 : 3+ interviews prospects (20 min chacune)
[ ] Semaine 6 : Décision Go/No-Go basée sur les inscriptions
[ ] Semaine 8 : Premier utilisateur beta
```

---

## GARDE-FOU 7 — ANTI DETTE TECHNIQUE SILENCIEUSE

### Choses à ne JAMAIS faire
```
❌ Copier-coller du code entre controllers (→ extraire dans un Service)
❌ Mettre de la logique métier dans un Controller (→ Service class)
❌ Valider des données dans un Controller (→ FormRequest)
❌ Hardcoder des strings (→ fichier lang/)
❌ Ignorer un warning PHPStan/Pest (→ corriger)
❌ Commiter du code avec dd(), dump(), var_dump() (→ supprimer)
❌ Commiter un .env avec des vrais mots de passe (→ .env.example)
```

---

## GARDE-FOU 8 — COMMUNICATION CLAIRE

### Template de fin de session IA

Chaque session IA DOIT finir par mettre à jour `PILOTAGE.md` :
```
1. Cocher les tickets terminés (⬜ → ✅)
2. Noter les blocages éventuels
3. Commit : "chore: update PILOTAGE.md after MVP-XX"
```

### Template de commit
```
feat(scope): ce qui a été ajouté
fix(scope): ce qui a été corrigé
test(scope): tests ajoutés/modifiés
docs(scope): documentation ajoutée

Scopes valides : init, auth, employees, attendance, estimation, pdf, web, mobile, ci
```

---

## RÉSUMÉ VISUEL

```
╔══════════════════════════════════════════════╗
║  AVANT CHAQUE ACTION, VÉRIFIER :            ║
║                                              ║
║  1. C'est dans le scope MVP ?     (GF-1)    ║
║  2. Les tests précédents passent ? (GF-3)    ║
║  3. L'isolation tenant est OK ?    (GF-4)    ║
║  4. C'est la solution simple ?     (GF-5)    ║
║                                              ║
║  Si une réponse est NON → STOP.              ║
╚══════════════════════════════════════════════╝
```
