# 05 — Audit Backend API (Laravel)

> **Quand l'utiliser :** Pour auditer la santé du backend Laravel : routes, controllers, models, tests, sécurité, performance, base de données.
> **Durée estimée :** Moyen (30-45 min)
> **Prérequis :** Être sur `main` à jour

## Instructions

```
Agis en tant qu'auditeur backend senior pour le projet Leopardo RH situé dans c:\Users\cheic\Downloads\gestionemployer.

Commence par lire AGENTS.md puis audite le backend dans api/.

Vérifie ces 10 axes et produis un rapport structuré :

1. ROUTES : Lis api/routes/api.php et api/routes/modules/. Compte les routes, identifie les routes non protégées par middleware auth, les routes sans controller.

2. CONTROLLERS : Liste tous les controllers dans api/app/. Vérifie qu'ils suivent la convention (injection de dépendances, pas de logique métier dans le controller, validation via FormRequest).

3. MODELS : Liste tous les models. Vérifie les relations, les fillable/guarded, les casts. Identifie les models sans migration correspondante.

4. SERVICES : Vérifie que la logique métier est dans les Services, pas dans les controllers. Identifie les services sans interface.

5. TESTS : Compte les fichiers de test dans api/tests/. Calcule le ratio test/controller. Identifie les controllers critiques sans test.

6. MIGRATIONS : Liste les 10 dernières migrations. Vérifie la cohérence (pas de migration destructive sans rollback, pas de migration qui modifie des données en production).

7. SÉCURITÉ : Exécute `composer audit` (ou vérifie le dernier résultat CI). Cherche les TODO/FIXME liés à la sécurité. Vérifie la config CORS, les rate limits, les policies d'autorisation.

8. PERFORMANCE : Cherche les N+1 queries potentielles (eager loading manquant), les jobs lourds sans queue, les requêtes sans pagination.

9. CONFIGURATION : Vérifie api/config/ (database, auth, tenancy, queue, cache, mail). Identifie les valeurs hardcodées qui devraient être en .env.

10. OPENAPI : Vérifie que api/openapi.yaml est à jour par rapport aux routes réelles.

Pour chaque problème trouvé, classe-le :
- 🔴 Critique (à corriger avant déploiement)
- 🟡 Important (à planifier)
- 🟢 Mineur (amélioration)

Pour chaque 🔴, crée automatiquement une issue GitHub.
```

## Notes

- Le backend utilise PostgreSQL multi-tenant (shared schema via `public.companies` + schemas tenant).
- `CommunicationService` est l'orchestrateur central des notifications multi-canal.
- Les modules sont dans `api/app/Modules/` (Marketing, HR, etc.).
