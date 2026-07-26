# 10 — Checklist Pré-Déploiement Production

> **Quand l'utiliser :** Avant chaque déploiement en production (Render, Vercel, Firebase App Distribution).
> **Durée estimée :** Court (15-20 min)
> **Prérequis :** Être sur `main` à jour, tous les checks CI verts

## Instructions

```
Agis en tant qu'ingénieur de déploiement pour le projet Leopardo RH situé dans c:\Users\cheic\Downloads\gestionemployer.

Commence par lire AGENTS.md.

Exécute cette checklist pré-déploiement dans l'ordre :

1. GIT : Vérifie que main est à jour avec origin/main. Pas de commits non poussés. Pas de branches non mergées critiques.

2. CI VERTE : `gh run list --branch main --limit 3` — les 3 derniers runs doivent être verts. Si rouge, STOP et exécute le prompt 03 (FIX_CI_RED).

3. PRs OUVERTES : `gh pr list --state open` — aucune PR critique ne doit rester ouverte. Les PRs de type fix/security doivent être mergées avant le deploy.

4. MIGRATIONS : Vérifie les migrations non exécutées en production. Liste les nouvelles migrations depuis le dernier deploy.

5. VARIABLES D'ENVIRONNEMENT : Compare .env.example avec les variables réellement configurées sur Render/Vercel. Identifie les nouvelles variables ajoutées récemment.

6. DÉPENDANCES : `composer audit` côté API, vérifier npm audit côté web. Aucune vulnérabilité critique non résolue.

7. DEMO SEEDER : Vérifie que DemoCompanyOnceSeeder fonctionne avec les credentials publics (admin@leopardo-rh.com / password123). Le smoke test auth doit passer.

8. ENDPOINTS CRITIQUES : Vérifie que /health, /tester-guide, /api-explorer, /api/v1/demo-users répondent correctement.

9. MOBILE : Si des apps mobiles sont impactées, vérifier que les builds Firebase App Distribution sont prêts avec les bons noms préfixés.

10. CHANGELOG : Vérifie que la version dans CHANGELOG.md correspond à ce qui va être déployé.

Produis un rapport GO / NO-GO avec la liste des vérifications passées/échouées.
Si c'est GO, indique-le clairement.
Si c'est NO-GO, liste les bloquants à résoudre avant de déployer.
```

## Notes

- Le backend est déployé sur Render.
- La vitrine web est déployée sur Vercel.
- Les apps mobiles passent par Firebase App Distribution.
- Le login demo doit fonctionner même si public.user_lookups est incomplet (fallback dans AuthService).
