# Plan 70 - Market Launch 2026 Company OS

Date: 2026-06-05  
Objectif: transformer Leopardo HR en offre marche vendable, prouvable et scalable apres les Plans 67-69.

## P0 - Bloquants lancement et confiance demo

1. Corriger le mot de passe demo mobile Platform Admin: `password123`, pas `admin`.
2. Ajouter un garde CI qui bloque tout retour au mauvais mot de passe demo.
3. Re-distribuer les trois APK Firebase depuis `main` apres correction demo admin.
4. Rejouer la recette device Employee sur Android reel.
5. Rejouer la recette device Manager sur Android reel.
6. Rejouer la recette device Platform Admin sur Android reel.
7. Documenter appareil, version, SHA, capture et resultat par app.
8. Produire une video courte de login demo par app.
9. Valider que chaque app affiche une erreur lisible hors ligne.
10. Valider que Firebase/FCM indisponible ne bloque aucun lancement.
11. Valider que backend Render lent ne bloque pas l'ecran login.
12. Valider que `/api/v1/demo-users` reste accessible en prod.
13. Ajouter un smoke API quotidien demo employee/manager/platform.
14. Ajouter un badge README "demo status" ou lien vers rapport smoke.
15. Mettre a jour le guide testeur avec le bon mot de passe Platform Admin.

## P1 - Parcours vendables

16. Construire un parcours demo "PME securite privee" de bout en bout.
17. Construire un parcours demo "BTP chantier" de bout en bout.
18. Construire un parcours demo "logistique multi-sites" de bout en bout.
19. Ajouter des donnees demo sectorielles dans les seeders.
20. Ajouter des templates horaires par secteur.
21. Ajouter des templates taches terrain par secteur.
22. Ajouter des modeles de politiques absences par pays/secteur.
23. Ajouter un rapport PDF pilote 7 jours.
24. Ajouter un export CSV paie/presence pret cabinet.
25. Ajouter un onboarding manager en 5 etapes dans l'app.
26. Ajouter un onboarding employee ultra court apres invitation.
27. Ajouter une checklist super-admin pour creer un client pilote.
28. Ajouter un ecran "valeur obtenue" apres 7 jours de donnees.
29. Ajouter un cockpit support demo tenants.
30. Ajouter un script de reset propre des demos.

## P1 - Go-to-market et monetisation

31. Finaliser les offres Starter, Growth, Scale.
32. Definir limites par plan: employes, sites, modules, exports, support.
33. Definir prix tests par region: Maghreb, Afrique de l'Ouest, Europe.
34. Creer une page pricing orientee ROI.
35. Creer une page secteur "securite privee".
36. Creer une page secteur "BTP".
37. Creer une page secteur "logistique".
38. Creer un one-pager commercial par secteur.
39. Creer un script demo 15 minutes.
40. Creer un script discovery call 20 minutes.
41. Creer une matrice objections/reponses.
42. Creer un calculateur ROI simple.
43. Creer un modele de proposition pilote payant.
44. Creer une sequence email pilote 14 jours.
45. Creer une bibliotheque de captures produit.

## P2 - Produit et differenciation 2026

46. Renforcer offline-first: file d'attente locale pour actions critiques.
47. Ajouter sync status visible dans les apps mobiles.
48. Ajouter un mode "lecture seule offline" pour documents/profil.
49. Ajouter alertes anomalies paie/presence.
50. Ajouter assistant RH gouverne pour questions employees.
51. Ajouter journal d'audit IA et refus de decision opaque.
52. Ajouter politique IA par tenant.
53. Ajouter analytics adoption employe/manager.
54. Ajouter score de readiness par tenant dans platform admin mobile.
55. Ajouter exports comptables locaux prioritaires.
56. Ajouter webhooks signes production.
57. Ajouter portail developpeur sandbox.
58. Ajouter tokens API partenaires scopes.
59. Ajouter examples SDK pour workflows sectoriels.
60. Ajouter support mobile money en discovery technique.

## P2 - Operations scale

61. Etendre k6 aux endpoints authentifies critiques.
62. Publier p95/p99 par endpoint critique.
63. Mesurer cout par tenant Render/Firebase/DB.
64. Ajouter alertes budget Firebase.
65. Ajouter runbook incident mobile distribution.
66. Ajouter runbook reset demo production.
67. Ajouter dashboard support "tenant stuck".
68. Ajouter verification migrations tenant avant onboarding client.
69. Ajouter test restore backup mensuel visible dans rapport client.

## P3 - Expansion

70. Preparer marketplace open-core partenaires.
71. Preparer white-label par cabinet.
72. Preparer programme revendeurs/integrateurs.

## Critere de cloture Plan 70

- Demo admin mobile corrigee et prouvee.
- Les trois apps ont une recette device datee.
- Une offre commerciale claire existe.
- Trois secteurs cibles ont un parcours demo et un message dedie.
- Les KPI activation/adoption/revenue sont mesurables.

