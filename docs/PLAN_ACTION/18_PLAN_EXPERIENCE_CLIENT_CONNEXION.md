# 18 - Plan experience client connexion et espace moderne

Date : 2026-05-21

## Objectif

Garantir qu'un client peut reellement se connecter, comprendre son espace, acceder aux features de son plan et vivre une experience moderne, fluide et rassurante sur web, mobile et admin.

## Lot 18.1 - Contrat connexion client reel

- [ ] Verifier le parcours complet : vitrine -> login -> dashboard manager.
- [ ] Tester identifiants valides, mauvais mot de passe, compte inactif, compte sans tenant, session expiree.
- [ ] Garantir les redirections par role : manager principal, RH, comptable, employe, super admin.
- [ ] Ajouter un smoke E2E preview qui valide login client + affichage des donnees dashboard non vides.
- [ ] Documenter les variables d'environnement requises : API base, URL vitrine, URL admin, credentials demo/staging.

## Lot 18.2 - Acces features par plan et par role

- [ ] Afficher dans l'espace client les modules disponibles selon `features`/plan.
- [ ] Bloquer proprement les modules non inclus avec message upgrade, jamais avec 404 confuse.
- [ ] Verifier les features critiques : employees, attendance, absences, payroll, reports, billing, integrations.
- [ ] Ajouter tests API + UI sur feature accessible, feature interdite, feature en trial.

## Lot 18.3 - Modernisation login UX

- [ ] Harmoniser les pages login web client, admin plateforme, mobile et kiosque.
- [ ] Ajouter et tester : afficher/masquer mot de passe, etat loading, erreurs lisibles, recuperation mot de passe, acces demo si autorise.
- [ ] Optimiser responsive mobile, contraste, navigation clavier, focus visible et ARIA.
- [ ] Eviter les pages marketing dans le login : priorite a l'action, confiance, securite et clarte.

## Lot 18.4 - Premiere experience apres connexion

- [ ] Dashboard manager : etat de l'entreprise, actions prioritaires, onboarding incomplet, donnees RH recentes.
- [ ] Employe : pointage, absences, bulletins, notifications, langue.
- [ ] Super admin : sante plateforme, demandes clients, tenants a risque.
- [ ] Kiosque : etat appareil, synchro, mode offline clair.

## Lot 18.5 - Qualite et observabilite UX

- [ ] Mesurer temps login -> dashboard utilisable.
- [ ] Ajouter tracking evenements : login_success, login_failed, dashboard_loaded, feature_blocked, demo_user_selected.
- [ ] Ajouter captures E2E Playwright sur login et dashboard.
- [ ] Definir seuils Lighthouse/Web Vitals pour pages login et dashboard.

## Definition of done

- Un client manager peut se connecter depuis la vitrine et arriver dans un espace utile sans intervention technique.
- Les roles voient uniquement leurs features et comprennent les limites de leur plan.
- Les pages login sont modernes, accessibles, rapides et testees.
- Les smoke tests staging couvrent API auth, web login client, admin login et mobile contract.
- Toute regression de connexion bloque la PR ou le deploy.
