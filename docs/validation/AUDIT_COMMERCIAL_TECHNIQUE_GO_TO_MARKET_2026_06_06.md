# Audit commercial-technique go-to-market - 2026-06-06

## Verdict court

Le socle Leopardo RH est maintenant suffisamment avance pour une phase pilote controlee : API stable, readiness Render verte, trois apps mobiles separees, super-admin mobile, vitrine, kiosk et documentation API existent. Le risque principal n'est plus l'absence de produit, mais l'ecart entre promesse commerciale et experience d'essai immediate.

Le lancement marketing large doit donc etre conditionne a trois preuves finales, dont les deux premieres sont maintenant traitees cote produit :

1. Essai vitrine comprehensible par email : **livre en demande d'essai guidee**.
2. Platform admin capable de creer/activer un client multi-pays sans correction manuelle : **livre cote API/mobile**.
3. Kiosk et apps mobiles testees sur parcours terrain avec etats vides et erreurs lisibles : **kiosk et Compte mobile ameliores, recette device encore requise**.

## Ce qui est solide

- Backend Laravel API : version `/api/v1`, OpenAPI public, contrats frontend/API, readiness tenant, notifications, paie/avance, pointage multiple, QR onboarding, kiosk, super-admin.
- Multi-app mobile : employee, manager et platform admin sont separees, avec `leopardo_core` comme socle partage.
- Production ops : Render, Redis/queues documentes, Firebase App Distribution, readiness gate, rapports de smoke et plans 57-69.
- Vitrine : pages pricing/demo/signup/blog/docs, formulaires de capture lead, analytics et contenus multilingues partiels.
- Kiosk : surface web dediee, bridge local/offline, endpoints device et options QR/biometrie deja presentes.

## Ce qui a ete corrige dans ce lot

- `POST /api/v1/platform/companies` ne force plus `DZD` et `Africa/Algiers` pour tous les pays.
- Le backend derive maintenant `language`, `currency` et `timezone` via `CountryDefaults` quand ils ne sont pas fournis.
- Le statut initial `trial` ou `active` est accepte a la creation plateforme.
- L'app mobile platform admin remplace le champ pays libre par un select controle avec apercu devise/timezone/langue.
- La liste entreprises platform admin affiche maintenant pays + devise + plan.
- OpenAPI et contrat mobile platform admin sont alignes.
- La vitrine `/signup` ne collecte plus de mot de passe fantome : elle capture email professionnel, entreprise, role, taille d'equipe et telephone optionnel, puis annonce un retour sous 24h ouvrables.
- Le kiosk ZKTeco a ete restructure autour du geste biometrie doigt/visage, sans IDs HTML dupliques, avec fallback QR/matricule et confirmation de pointage plus claire.
- Les ecrans `Compte` employee/manager affichent une vue d'ensemble qui separe identite portable, parcours/securite, documents, QR/biometrie, notifications et session, sans ajouter de boutons non fonctionnels.

## Risques critiques

### Essai commercial

Le bouton "Tester" / "Essai gratuit" capture maintenant une demande d'essai guidee explicite. Ce n'est volontairement pas encore un compte d'essai automatiquement provisionne. Le prochain saut business sera de choisir entre :

- garder le modele assiste avec SLA commercial et instrumentation CRM ;
- ajouter un provisioning sandbox automatique avec email de verification, quota, anti-abus et creation client limitee.

### I18n

L'architecture i18n existe, mais toutes les surfaces ne sont pas encore au meme niveau. Les apps mobiles ont `leopardo_core/l10n`, la vitrine a ses dictionnaires, le backend a un catalogue, mais plusieurs ecrans gardent des textes hardcodes. Le risque est surtout commercial : incoherence de langue dans les ecrans login, compte, pricing ou demo.

### Kiosk terrain

Le kiosk est maintenant plus proche de l'usage terrain : biometrie en premier, fallback QR/matricule, offline-first conserve. Le risque restant est materiel : tester sur vrai terminal ZKTeco/SDK, bridge local, reseau degrade et roster reel.

### Experience "Mon compte"

La page compte est mieux organisee sur employee/manager. Les prochains gains doivent porter sur l'i18n progressive, les etats vides des documents/parcours et la preuve sur appareils reels.

## Recommandations prioritaires

1. Recette device : tester employee, manager, platform admin et kiosk sur appareils physiques / Firebase App Distribution.
2. Decideur business : choisir si l'essai reste assiste ou devient sandbox automatique.
3. I18n : traiter en priorite login, compte, pricing, demo, signup et notifications selon le rapport de dette.
4. Branches distantes : ne pas merger la refonte admin-dashboard sans PR/checks, car elle touche 19 fichiers UI et supprime `dev_server.log`; traiter comme lot design dedie.
5. Dependabot #700 : le PR est derriere `main`; le rebaser/mettre a jour avant toute decision, puis verifier Composer Audit.

## Positionnement commercial

Le positionnement le plus coherent reste : **OS de gestion d'entreprise mobile-first pour PME et equipes terrain**.

Arguments :

- Le produit depasse une application RH : pointage, taches, documents, validations, paie, notifications, kiosk, workflows et API.
- La valeur differenciante est terrain/mobile, pas seulement back-office.
- Le produit peut vendre aux PME qui n'ont pas d'ERP lourd, mais veulent une interface quotidienne simple.

Formule commerciale recommandee :

> Leopardo RH centralise le quotidien des equipes terrain : presence, taches, demandes, documents, paie et validations, depuis le mobile et le kiosk.

## Go / No-Go marketing

Go pilote controle si :

- creation client platform admin testee en pays non DZ ;
- une entreprise active peut recevoir son manager principal ;
- login employee/manager/super-admin fonctionne sur APK recents ;
- kiosk affiche une action de pointage lisible ;
- `/signup` annonce clairement le parcours d'essai.

No-Go marketing large si :

- le visiteur ne peut pas demarrer ou comprendre l'essai ;
- un pays non DZ affiche DZD dans les surfaces client ;
- les apps mobiles bloquent sur splash/logo ou spinner ;
- les notifications ou queues ne sont pas surveillees en production.
