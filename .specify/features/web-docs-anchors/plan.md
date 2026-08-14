## Plan technique
1. Inventorier les ids réels des sections de `/docs`.
2. Aligner le TOC et les liens rapides sur ces ids ; ajouter les ids manquants là où le contenu existe.
3. Corriger le lien depuis `/mobile`.
4. Ajouter un script de scan (ou commande) vérifiant les ancres internes `#*` — à intégrer dans la doc dev (ou petit test).
5. Lint + build. CHANGELOG.
