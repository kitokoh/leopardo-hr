# 18 - Plan experience client connexion et espace moderne

Date : 2026-05-21

## Objectif

Garantir qu'un client peut reellement se connecter, comprendre son espace, acceder aux features de son plan et vivre une experience moderne, fluide et rassurante sur web, mobile et admin.

## Lot 18.1 - Contrat connexion client reel

- [x] Verifier le parcours complet : vitrine -> login -> dashboard manager.
- [~] Tester identifiants valides, mauvais mot de passe, compte inactif, compte sans tenant, session expiree.
- [x] Garantir les redirections par role : manager principal, RH, comptable, employe, super admin.
- [x] Ajouter un smoke E2E preview qui valide login client + affichage des donnees dashboard non vides.
- [x] Documenter les variables d'environnement requises : API base, URL vitrine, URL admin, credentials demo/staging.

## Lot 18.2 - Acces features par plan et par role

- [x] Afficher dans l'espace client les modules disponibles selon `features`/plan.
- [x] Bloquer proprement les modules non inclus avec message upgrade, jamais avec 404 confuse.
- [~] Verifier les features critiques : employees, attendance, absences, payroll, reports, billing, integrations.
- [~] Ajouter tests API + UI sur feature accessible, feature interdite, feature en trial.

Note 2026-05-21 : le portail client calcule les modules depuis les `capabilities`, les `features` entreprise/plan et le role utilisateur. Les tests UI couvrent module accessible, module interdit, module en trial et blocage role employe. Les tests API de gate serveur restent a etendre cote backend si de nouveaux endpoints feature-gated sont ajoutes.

## Lot 18.3 - Modernisation login UX

- [~] Harmoniser les pages login web client, admin plateforme, mobile et kiosque.
- [~] Ajouter et tester : afficher/masquer mot de passe, etat loading, erreurs lisibles, recuperation mot de passe, acces demo si autorise.
- [x] Optimiser responsive mobile, contraste, navigation clavier, focus visible et ARIA.
- [x] Eviter les pages marketing dans le login : priorite a l'action, confiance, securite et clarte.

Note 2026-05-21 : le login web client est modernise et couvert par Playwright. Les cas compte inactif / sans tenant dependront du contrat d'erreur backend dedie, et la recuperation mot de passe reste a brancher quand le flux email sera expose.

## Lot 18.4 - Premiere experience apres connexion

- [~] Dashboard manager : etat de l'entreprise, actions prioritaires, onboarding incomplet, donnees RH recentes.
- [~] Employe : pointage, absences, bulletins, notifications, langue.
- [~] Super admin : sante plateforme, demandes clients, tenants a risque.
- [x] Kiosque : etat appareil, synchro, mode offline clair.

Note 2026-05-21 : le dashboard web client presente maintenant l entreprise, les actions prioritaires et les donnees RH recentes pour manager ; un espace employe dedie expose pointage, absences, bulletins et langue ; un super admin est oriente vers le dashboard plateforme. Le kiosque affiche l etat appareil, la file locale, le mode offline et la derniere synchronisation. L onboarding incomplet et les notifications reelles restent a brancher avec les endpoints dedies.

## Lot 18.5 - Qualite et observabilite UX

- [x] Mesurer temps login -> dashboard utilisable.
- [x] Ajouter tracking evenements : login_success, login_failed, dashboard_loaded, feature_blocked, demo_user_selected.
- [x] Ajouter captures E2E Playwright sur login et dashboard.
- [x] Definir seuils Lighthouse/Web Vitals pour pages login et dashboard.

Note 2026-05-21 : le portail web emet maintenant les evenements `leopardo:analytics` via `trackClientEvent`, couverts par Playwright. Les captures login/dashboard sont attachees au rapport CI `web-client-playwright-report`, et les seuils UX sont formalises dans `docs/validation/CLIENT_UX_OBSERVABILITY.md`. La page login est ajoutee au contrat Lighthouse. Le kiosque expose aussi un evenement `leopardo:kiosk-status` et une derniere synchronisation lisible pour clarifier l etat offline.

Note 2026-05-22 : les evenements authentifies sont persistables via `POST /api/v1/client-events` dans la table tenant `client_events`, avec rate limit dedie, allowlist d evenements et minimisation des proprietes pour eviter la fuite PII. `login_failed` reste volontairement local tant que le tenant n est pas fiable.

Note 2026-05-22 : la vitrine est renforcee pour le lancement marketing avec liens directs `/blog`, `/guides/rh-startup`, `/pricing` et `/demo`, une section de conversion reliee au parcours client, des metadonnees sociales et des assets PWA/SEO propres. Cette couche oriente le trafic public vers un parcours concret : lire, comparer, demander une demo, s'inscrire, puis se connecter a l'espace client.

## Definition of done

- Un client manager peut se connecter depuis la vitrine et arriver dans un espace utile sans intervention technique.
- Les roles voient uniquement leurs features et comprennent les limites de leur plan.
- Les pages login sont modernes, accessibles, rapides et testees.
- Les smoke tests staging couvrent API auth, web login client, admin login et mobile contract.
- Toute regression de connexion bloque la PR ou le deploy.
- Lot 18 web client : livre fonctionnellement. Les reliquats futurs sont des integrations serveur/ops dediees : lecture analytics agregee, contrats backend pour compte inactif/sans tenant et endpoints notifications/onboarding/kiosque avances.
