# Audit commercial-technique go-to-market - 2026-06-06

## Verdict court

Le socle Leopardo RH est maintenant suffisamment avance pour une phase pilote controlee : API stable, readiness Render verte, trois apps mobiles separees, super-admin mobile, vitrine, kiosk et documentation API existent. Le risque principal n'est plus l'absence de produit, mais l'ecart entre promesse commerciale et experience d'essai immediate.

Le lancement marketing large doit donc etre conditionne a trois preuves finales :

1. Essai vitrine reel et comprehensible par email.
2. Platform admin capable de creer/activer un client multi-pays sans correction manuelle.
3. Kiosk et apps mobiles testees sur parcours terrain avec etats vides et erreurs lisibles.

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

## Risques critiques

### Essai commercial

Le bouton "Tester" / "Essai gratuit" capture aujourd'hui une demande. Ce n'est pas encore equivalent a un compte d'essai automatiquement provisionne. Pour vendre vite, il faut choisir explicitement :

- soit essai automatique sandbox avec email de verification, quota, anti-abus et creation client limitee ;
- soit demande d'essai assistee, mais le texte doit le dire clairement.

### I18n

L'architecture i18n existe, mais toutes les surfaces ne sont pas encore au meme niveau. Les apps mobiles ont `leopardo_core/l10n`, la vitrine a ses dictionnaires, le backend a un catalogue, mais plusieurs ecrans gardent des textes hardcodes. Le risque est surtout commercial : incoherence de langue dans les ecrans login, compte, pricing ou demo.

### Kiosk terrain

Le kiosk est fonctionnel, mais encore trop "console de borne" par endroits. Pour le terrain, le premier geste doit etre biometrie/QR, avec confirmation immediate et fallback identifiant. L'UI doit masquer la complexite device autant que possible.

### Experience "Mon compte"

La page compte porte beaucoup de fonctionnalites. Il faut la reorganiser en sections lisibles : identite, securite, parcours, documents, preferences, deconnexion. C'est important parce que l'employe garde son compte apres depart d'entreprise.

## Recommandations prioritaires

1. Finaliser Lot 71.4 : transformer `/signup` en essai reel ou clarifier qu'il s'agit d'une demande d'essai.
2. Finaliser Lot 71.5 : kiosk biometrie-first, confirmation pointage, fallback offline clair.
3. Finaliser Lot 71.6 : refonte "Mon compte" employee/manager avec sections et actions visibles.
4. Lancer un inventaire i18n par surface, puis interdire les nouveaux textes hardcodes hors exceptions documentees.
5. Ajouter l'endpoint pays si plusieurs frontends doivent partager la meme liste que `CountryDefaults`.

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
