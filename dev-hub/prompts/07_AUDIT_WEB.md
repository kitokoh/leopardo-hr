# 07 — Audit Vitrine Web (Next.js)

> **Quand l'utiliser :** Pour auditer la vitrine commerciale / portail manager : SEO, performance, liens morts, images cassées, formulaires, i18n.
> **Durée estimée :** Moyen (30 min)
> **Prérequis :** Être sur `main` à jour

## Instructions

```
Agis en tant qu'auditeur web senior pour le projet Leopardo RH situé dans c:\Users\cheic\Downloads\gestionemployer.

Commence par lire AGENTS.md (sections vitrine, SEO, /signup, /download, liens commerciaux).

Audite la vitrine dans front/web/.

Vérifie ces 9 axes :

1. PAGES : Liste toutes les pages dans app/ (ou pages/). Vérifie les routes, les layouts, les pages orphelines.

2. SEO : Vérifie les meta tags (title, description, og:image) sur chaque page. Vérifie la structure heading (un seul h1 par page). Vérifie le sitemap et robots.txt.

3. IMAGES & AVATARS : Vérifie que toutes les images référencées dans le code existent réellement dans public/. Cherche les avatars, logos et brand images cassés. Commande utile : `grep -rn '.webp\|.png\|.jpg' front/web/src/data/` puis vérifier que chaque fichier existe.

4. LIENS COMMERCIAUX : Vérifie que la navigation et le footer gardent les liens réels (/blog, /guides/rh-startup, /pricing, /demo, /contact). Aucun CTA ne doit pointer vers # ou une route API relative.

5. FORMULAIRES : Vérifie /signup (demande d'essai guidée, PAS de mot de passe), le hero email-only (source=hero_email_trial), et /contact. Tous doivent poster vers /api/forms/signup.

6. PAGE /DOWNLOAD : Vérifie que les liens d'apps mobiles utilisent les variables NEXT_PUBLIC_LEOPARDO_*. Pas de liens morts #android-* ou #ios-*.

7. I18N : Vérifie les traductions FR/EN/TR/AR. Cherche les mojibake (???, Ø, Ù), les clés manquantes, les textes non traduits.

8. PERFORMANCE : Vérifie le score Lighthouse (si disponible en CI). Vérifie le bundle size, les images non optimisées, les fonts non préchargées.

9. BUILD : Exécute mentalement le build Next.js. Vérifie next.config.ts, les variables d'environnement requises, les erreurs TypeScript.

Produis un rapport avec 🔴🟡🟢 et crée des issues pour les 🔴.
```

## Notes

- La vitrine est en Next.js (pas Vue.js comme l'admin dashboard).
- /signup est une demande d'essai guidée, PAS une création de compte.
- Les CTA d'essai doivent pointer vers /signup ou /demo, jamais vers /auth/signup.
- La section OperationalProofSection vend les 3 apps mobiles, 2 apps web, kiosk/biométrie et API.
