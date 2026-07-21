# Audit i18n — multilingue reel a tous les niveaux — 2026-07-19

Statut: complete pour publication
Auteur: audit interne KiloClaw (agent), a la demande de kitokoh
Perimetre: `shared/i18n/*`, `api/lang/*`, `api/resources/views/{pdf,emails}`, `front/web`, `front/admin-dashboard`, `front/mobile_apps/*`, `front/zkteco-kiosk`, CI `i18n-enterprise.yml`, outillage `dev-hub/tools/validate-i18n-debt.ps1`.

Ce document complete `08_AUDIT_ARCHITECTURE_TECH.md` et `09_AUDIT_MODULES_API_STRUCTURE.md` avec un audit dedie a l'internationalisation. Les tickets d'action issus de cet audit sont listes en fin de fichier et repris dans `02_BACKLOG_ATOMIQUE.md` / `03_GITHUB_PROJECT_IMPORT.csv` sous le prefixe `PA2-I18N-*` (suite de `PA2-I18N-001` a `004` deja existants).

Methode : lecture directe du code (pas de supposition), execution reelle des validateurs/scripts existants (`node shared/i18n/validators/validate.js`, `node shared/i18n/sync/sync-{backend,mobile,web}.js`), comptage de fichiers/cles par surface, lecture du rapport de dette deja genere (`docs/validation/I18N_DEBT_REPORT_2026_06_06.md`), lecture de la gouvernance existante (`docs/GUIDES/GUIDE_JULES_TRADUCTION_MULTILINGUE.md`, `docs/archive/PLAN_ACTION/24_PLAN_MULTILINGUE_JULES_TRANSLATION.md`, `docs/archive/PLAN_ACTION/47_PLAN_I18N_MOBILE_MULTI_APP_ALIGNMENT.md`).

---

## 1. Ce qui fonctionne deja bien (a ne pas casser)

- Source de verite unique correcte : `shared/i18n/locales/{fr,en,ar,tr}.json`, 56 cles chacune, `node shared/i18n/validators/validate.js` retourne `I18N_VALIDATION_OK (4 locales)` sans erreur, 0 mojibake detecte sur `ar`/`tr`.
- 3 scripts de sync fonctionnels et executes sans erreur au moment de l'audit : `sync-backend.js` (→ `api/lang/*/shared.php` + `emails.enterprise.php`), `sync-mobile.js` (→ ARB Flutter `leopardo_core`), `sync-web.js` (→ JSON `admin-dashboard` + `front/web`).
- CI `i18n-enterprise.yml` execute les 3 syncs + `validate.js` + `git diff --exit-code` sur push/PR — bon pattern anti-drift, deja en place.
- Cote API Laravel : middleware `SetLocale` (priorite `preferred_language` employe → `language` entreprise → header `Accept-Language` → `fr`), `I18nCatalog::isRtl()`, endpoint `GET /api/v1/i18n/catalog/{locale}`, champ `localized_message` sur les erreurs JSON standard (`bootstrap/app.php`). Quasiment aucun texte metier code en dur trouve dans `api/app/Modules/**/Controllers` (1 seule occurrence identifiee, voir section 4).
- Cote Flutter (`leopardo_core`) : `flutter_localizations`, `AppLocalizations` generes depuis ARB, `supportedLocales`, `localeResolutionCallback`, gestion `isRtl` persistee (Hive), header `Accept-Language` injecte par l'intercepteur API — le socle technique est correct dans les 4 apps qui en dependent (`employee`, `manager`, `hr`, `platform_admin`).
- Glossaire verrouille (`shared/i18n/glossary/glossary.json`) et regles de style par langue deja documentees pour le traducteur (Jules) — bonne base de coherence terminologique.

## 2. Le vrai probleme : le texte reste code en dur en dehors des catalogues

L'infrastructure existe, mais elle est trop peu **branchee** dans le code de presentation. Preuves chiffrees (comptage direct, pas le rapport PS bruite) :

| Surface | Fichiers de code | Fichiers avec texte FR/TR visible hors catalogue (grep accents heuristique) | Cles dans le catalogue dedie |
|---|---|---|---|
| `front/web/src` (ts/tsx hors `lib/i18n`) | 209 | 11 fichiers confirmes (dont vitrine, dashboard, auth) | 56 cles (`front/web/src/lib/i18n/locales`) |
| `front/admin-dashboard/src` (vue) | 83 | 8 fichiers confirmes (toasts succes, libelles) | 56 cles |
| `front/mobile_apps/leopardo_manager/lib` (dart) | 93 | ~1 gros fichier direct, mais tres peu d'ecrans appellent `context.l10n` malgre l'ARB dispo | 53 cles ARB core |
| `front/mobile_apps/leopardo_employee/lib` (dart) | 76 | idem | 53 cles ARB core |
| `front/mobile_apps/leopardo_platform_admin/lib` (dart) | 12 | 100% du texte UI en dur (ecran login, creation client) | 53 cles ARB core, non utilisees ici |
| `front/zkteco-kiosk` | 2 fichiers principaux + HTML | **aucune infrastructure i18n du tout** | 0 |

Le rapport `docs/validation/I18N_DEBT_REPORT_2026_06_06.md` (11 642 signaux) surestime largement le probleme car son detecteur compte les classes Tailwind/CSS comme "texte" (`"flex items-center justify-between"` compte comme signal P1). Une fois ce bruit filtre a la main, la dette reelle confirmee reste neanmoins importante et concentree sur : ecrans mobile manager/employee/platform_admin, pages `app/(dashboard)` et `app/(landing)` du web, vues metier de l'admin-dashboard (toasts, libelles), et l'integralite du kiosk.

## 3. Trous silencieux ou l'infra existe mais n'est jamais appelee

Ce sont les cas les plus trompeurs : le catalogue existe, mais rien ne l'utilise, donc un audit rapide qui ne regarde que `shared/i18n/` conclurait a tort que tout est traduit.

- **PDF legaux** : `api/lang/{fr,en,ar,tr}/pdf.php` existe (25 cles : `receipt_title`, `invoice_title`, `date`, `employee`, etc.) mais **aucune** vue Blade PDF ne l'utilise. `api/resources/views/pdf/payslip.blade.php` (et `invoice.blade.php`, `contract.blade.php`, `receipt.blade.php`, `payment-document*.blade.php`, `attendance-monthly-report.blade.php`) sont 100% en francais code en dur, avec `<html lang="fr">` fixe. `PaySlipPdfGenerator::generate()` ne fixe jamais `App::setLocale()` — les jobs PDF tournent hors contexte HTTP donc hors middleware `SetLocale`, meme si l'employe a `preferred_language=en`.
- **Emails transactionnels** : `api/lang/*/emails.php` existe (12 cles : sujets + corps generiques) mais seul `cabinet-share.blade.php` (parmi 16+ templates) utilise reellement `__('emails.xxx')`. Tous les autres (`welcome-employee`, `password-reset`, `subscription-confirmed`, `trial-welcome`, `invitation`, `role-assignment`, la serie `trial/day_*`, etc.) sont du francais code en dur dans le Blade, et les `Mailable` correspondants ne fixent jamais la locale du destinataire avant rendu.
- Consequence concrete : un employe qui choisit `en` ou `ar` dans son profil recoit malgre tout son bulletin de paie et ses emails **en francais**, alors que l'UI web/mobile change bien de langue. C'est le trou le plus visible entre "l'infra dit multilingue" et "l'utilisateur vit multilingue".

## 4. Points ponctuels a corriger

- `api/app/Modules/SmartAttendance/Interfaces/Api/V1/GeoSessionController.php:140` retourne `'message' => 'Session approuvee. Le pointage a ete cree.'` en dur, meme reponse API pour tous les clients quelle que soit leur langue — seule occurrence de ce type trouvee dans les 122 controllers de modules, mais reelle et facile a manquer sans grep dedie.
- Formats date/devise fixes en `fr-FR` independamment de la langue active de l'utilisateur, cote `front/web` et `front/admin-dashboard` (`Intl.NumberFormat('fr-FR', …)`, `toLocaleDateString('fr-FR', …)` ou sans argument de locale). Fichiers confirmes : `payroll/page.tsx`, `partner/page.tsx`, `modules/vitrine/lib/utils.ts`, `PayrollView.vue`, `ContractsView.vue`, `DashboardView.vue`, `CompaniesView.vue`, `CompanyDetailView.vue`, `SubscriptionsView.vue`, `EdgeNodesView.vue`, `ChatView.vue`, `UsersView.vue`, `stores/dashboard.js`, `Header.vue`, `SystemStatusCard.vue`, `BackupManagement.vue`, `AutomatedTasksList.vue`, `GrowthDashboardView.vue`. Un utilisateur en `en`/`ar`/`tr` voit donc des montants/dates au format francais dans le dashboard.
- Melange de langues fige dans les donnees, pas dans le catalogue : `front/web/src/modules/vitrine/data/pricing.ts` et `front/web/src/app/(landing)/mobile/page.tsx` contiennent des chaines **turques codees en dur au meme niveau que des chaines francaises** dans le meme objet de donnees (ex. `subtitle: 'Kucuk baslayin, platform degistirmeden buyuyun.'` cote a cote avec du texte francais). Ces pages semblent "avoir de la traduction" mais le contenu turc reste affiche a un utilisateur `fr`/`en`/`ar` — c'est une fausse impression de multilingue plus trompeuse qu'une simple absence de traduction.
- Le kiosk ZKTeco (`front/zkteco-kiosk`) n'a aucun mecanisme i18n : `index.html`, `admin.html`, `app.js`, `admin.js` sont entierement en francais en dur, y compris les libelles d'etat critiques ("Bridge local indisponible.", "Erreur de synchronisation"). C'est le seul composant produit dans ce cas — a trancher explicitement (voir tickets) plutot qu'a laisser par defaut.

## 5. CI et outillage — couverture et fiabilite insuffisantes

- `i18n-enterprise.yml` ne se declenche que sur `shared/i18n/**`, `front/mobile/lib/l10n/**`, `front/mobile_apps/leopardo_core/lib/l10n/**`, `front/admin-dashboard/src/i18n/**`, `front/web/src/lib/i18n/**`, `api/lang/**`. Il ne couvre **pas** `front/mobile_apps/leopardo_{employee,manager,platform_admin}/lib/**`, `front/zkteco-kiosk/**`, ni `api/resources/views/{pdf,emails}/**` — exactement les zones ou la dette est la plus grande. Une regression (nouveau texte en dur) dans ces zones passe aujourd'hui inapercue en CI.
- `dev-hub/tools/validate-i18n-debt.ps1` produit un rapport utile comme point de depart mais peu fiable pour piloter : il compte les classes CSS/Tailwind comme signaux texte, ce qui gonfle le total (11 642 signaux) au point de masquer le signal reel. Il est aussi en PowerShell alors que le reste de l'outillage i18n du repo est en Node (coherence d'environnement CI/dev a ameliorer).
- Aucun garde CI ne bloque aujourd'hui l'introduction d'une **nouvelle** chaine en dur sur une PR — le rapport de dette est informatif, pas bloquant, et ne tourne pas en mode diff.

## 6. Definition de "reellement multilingue" retenue pour ce plan

Un critere binaire et verifiable, pas une impression :

1. Zero texte utilisateur code en dur (web, admin, mobile x4, kiosk, PDF, emails, reponses API) — tout passe par une cle resolue selon la langue active.
2. Formats date/devise suivent la langue active, pas une valeur fixe.
3. La preference de langue de l'utilisateur est respectee jusqu'au bout (UI **et** documents generes **et** emails), pas seulement dans l'ecran ou il l'a choisie.
4. Qualite egale entre langues (glossaire verrouille respecte, pas de mojibake, RTL correct pour `ar`).
5. CI bloque toute regression (nouvelle chaine en dur) sur toutes les surfaces produit, pas seulement celles deja couvertes aujourd'hui.

Tant qu'un seul point est faux sur une surface donnee, cette surface reste "multilingue partiel", pas "reellement multilingue".

## 7. Tickets d'action (prefixe `PA2-I18N-*`, suite de 001-004 existants)

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-I18N-005 | P0 | Localiser les PDF legaux (paie, facture, contrat, recu) | `api/resources/views/pdf`, `api` | chaque vue Blade PDF utilise `__('pdf.xxx')` au lieu de texte en dur; `lang`/`dir` HTML dynamiques via `I18nCatalog::isRtl()`; le generateur fixe `App::setLocale()` selon `employee->preferred_language` ?? `company->language` avant rendu (contexte hors requete HTTP); test genere un bulletin en `ar` et verifie RTL + libelles traduits |
| PA2-I18N-006 | P0 | Localiser les emails transactionnels | `api/resources/views/emails`, `api/app/Mail` | les 16+ templates emails utilisent `__('emails.xxx')` (`api/lang/*/emails.php` complete en consequence); chaque `Mailable` fixe la locale du destinataire avant rendu; test `Mail::fake()` verifie sujet traduit pour `en`/`ar`/`tr` |
| PA2-I18N-007 | P1 | Corriger le message API en dur restant | `api/app/Modules/SmartAttendance` | `GeoSessionController.php:140` utilise une cle `attendance.*` existante ou nouvelle au lieu du francais en dur; regle de lint CI qui detecte toute nouvelle occurrence de `'message' => '...accent...'` dans les controllers |
| PA2-I18N-008 | P1 | Formats date/devise selon la langue active (web + admin) | `front/web`, `front/admin-dashboard` | tous les `Intl.NumberFormat('fr-FR', …)` / `toLocaleDateString('fr-FR', …)` recenses (17 fichiers, liste en section 4) utilisent la locale active de l'utilisateur (`getPreferredLocale()` / `useLocaleStore()`), pas une valeur fixe |
| PA2-I18N-009 | P0 | Extraction texte en dur — mobile employee/manager/platform_admin | `front/mobile_apps/leopardo_{employee,manager,platform_admin}/lib` | ecrans prioritaires (auth, pointage, approbations, creation client) utilisent `context.l10n.xxx` au lieu de `Text('...')` en dur; nouvelles cles ajoutees dans `app_fr.arb` puis traduites (prompts Jules) et synchronisees; captures ecran `ar`/`en` archivees dans `docs/validation/` |
| PA2-I18N-010 | P1 | Extraction texte en dur — web Next.js (`app/(dashboard)` et `app/(landing)`) | `front/web` | pages recensees (payroll, smart-attendance, edge-nodes, settings/developer, offline, pricing, guides/planning-employes, mobile) utilisent le catalogue `front/web/src/lib/i18n` au lieu de texte en dur; catalogue etendu en consequence sur les 4 locales |
| PA2-I18N-011 | P1 | Corriger le melange de langues fige dans les donnees vitrine | `front/web/src/modules/vitrine/data/pricing.ts`, `app/(landing)/mobile/page.tsx` | plus aucune chaine turque (ou autre langue) codee en dur au meme niveau que du francais dans un objet de donnees; contenu deplace dans le catalogue i18n avec la bonne cle par langue |
| PA2-I18N-012 | P1 | Extraction texte en dur — admin-dashboard Vue | `front/admin-dashboard/src` | vues a fort trafic (`UsersView`, `CompaniesView`, `PayrollView`, `SystemView`, `QuickActionsCard`, `Header`) utilisent `$t('xxx')` au lieu de texte en dur (toasts succes, libelles); catalogue `front/admin-dashboard/src/i18n/locales` etendu en consequence |
| PA2-I18N-013 | P2 | Decision produit + implementation i18n kiosk | `front/zkteco-kiosk` | decision ecrite (multilingue requis ou mono-langue assume) documentee dans ce fichier; si multilingue: catalogue minimal 4 langues branche via `data-i18n`/JS, selecteur de langue simple dans `admin.html`, `front/zkteco-kiosk/**` ajoute aux triggers CI `i18n-enterprise.yml` |
| PA2-I18N-014 | P1 | Etendre la couverture CI i18n aux surfaces a risque | `.github/workflows/i18n-enterprise.yml` | triggers etendus a `front/mobile_apps/leopardo_{employee,manager,platform_admin}/lib/**`, `front/zkteco-kiosk/**`, `api/resources/views/{pdf,emails}/**`; job qui echoue si une nouvelle chaine en dur est introduite sur diff de PR (pas seulement rapport informatif) |
| PA2-I18N-015 | P2 | Reecrire l'outil de detection de dette en Node, fiable | `dev-hub/tools` | nouveau `dev-hub/tools/i18n-debt.js` qui ignore les classes CSS/Tailwind et les routes techniques, distingue texte UI visible vs texte log/dev; rapport republie remplace `I18N_DEBT_REPORT_2026_06_06.md` par une mesure fiable de la dette residuelle apres PA2-I18N-005 a 013 |

Ordre d'execution recommande : `PA2-I18N-005/006` (impact utilisateur direct le plus eleve, infra deja prete) → `PA2-I18N-007/008` (corrections ponctuelles rapides) → `PA2-I18N-014/015` en parallele (verrouiller avant d'etendre) → `PA2-I18N-009` puis `010/011/012` (gros volume d'extraction) → `PA2-I18N-013` en dernier (perimetre isole).

---

## 7bis. Suivi d'execution — 2026-07-20

Statut mis a jour par agent KiloClaw a la demande de kitokoh (poursuite du travail multilingue).

- **PA2-I18N-006 (emails transactionnels) — complete pour le perimetre reellement dispatche en production.** Verification prealable : sur 16+ templates email, seuls les Mailables suivants sont reellement instancies/dispatches par du code metier (les autres — `password-reset.blade.php`, `demo-confirmation.blade.php`, `newsletter-welcome.blade.php`, `welcome.blade.php` — n'ont aucun appelant trouve dans `app/**`, a trancher separement comme code mort ou fonctionnalite a cabler). Pour les Mailables actifs :
  - `RoleAssignmentMail` + `role-assignment.blade.php` : localise via `emails.role_assignment_*`, locale resolue depuis `Employee->preferred_language` ?? `Company->language`, `App::setLocale()` fixe avant `subject()`/`view()`, `dir="rtl"` dynamique. `RoleInvitationService::getRoleLabel()` traduit desormais via `emails.role_label_*` au lieu de retourner du francais fixe. `RoleAssignmentController` (3 messages JSON en dur : forbidden x2, not-found, success/removed) migre vers `employees.role_assign_forbidden`, `employees.role_assign_not_in_company`, `employees.role_assigned`, `employees.role_removed`, `employees.team_roles_forbidden`.
  - `UserInvitationMail` + `user-invitation.blade.php` : localise via `emails.user_invitation_*`, meme pattern de resolution de locale (mailable envoye via `Mail::to()->send()` hors requete HTTP dans certains flux d'onboarding).
  - `TrialDripMail` + `trial/day3.blade.php`, `trial/expiring.blade.php`, `trial/expired.blade.php` (dispatche par la commande console `app:send-drip-emails`, donc hors middleware `SetLocale` par construction) : localise via `emails.trial_day3_*`, `emails.trial_expiring_*`, `emails.trial_expired_*`, locale resolue depuis `Employee->preferred_language` ?? `Company->language`.
  - `TrialDayOneMail`, `TrialDayThreeMail`, `TrialDaySevenMail` + `trial/day_one.blade.php`, `trial/day_three.blade.php`, `trial/day_seven.blade.php` (dispatches par `SendTrialDripEmailJob`, un queued job donc egalement hors middleware) : localises via `emails.trial_day1_*`, `emails.trial_day3_mail_*`, `emails.trial_day7_*`; le job passe desormais `manager->preferred_language` en parametre optionnel aux trois Mailables.
  - **Bug reel corrige au passage** : `InvitationMail`, `WelcomeEmployeeMail`, `LicenseExpiringMail`, `SubscriptionConfirmedMail` appelaient `__('mail.*')` alors qu'aucun fichier `api/lang/{locale}/mail.php` n'existait dans aucune des 4 locales — `__()` retournait donc silencieusement la cle brute au lieu d'un sujet traduit. Fichiers `api/lang/{fr,en,ar,tr}/mail.php` crees avec les 4 cles manquantes.
  - Verification : `php -l` sur tous les fichiers PHP touches (0 erreur), verification programmatique que chaque cle `__()`/`trans()` utilisee dans les fichiers modifies resout bien dans les 4 locales (0 cle manquante). Aucun environnement Composer/vendor disponible dans le sandbox d'execution → pas de suite de tests Laravel executee ; verification limitee a l'analyse statique (lint + resolution de cles). A rejouer avec `php artisan test` / `Mail::fake()` avant merge en production.
  - Reste hors perimetre de cette passe (dette residuelle a traiter separement) : templates identifies comme non appeles par du code (`password-reset.blade.php`, `demo-confirmation.blade.php`, `newsletter-welcome.blade.php`, `welcome.blade.php`) et `CabinetShareMail`/`TrialWelcomeMail`/`TrialVerificationMail` deja locale-aware avant cette session (non retouches).
- **PA2-I18N-007 (message API en dur `GeoSessionController`)** : deja corrige avant cette session (le controleur utilise `__('attendance.geo_session_approved')`), verifie par grep dedie — aucune occurrence restante de `'message' => '<francais accentue>'` dans ce fichier.
- **PA2-I18N-011 (melange de langues fige — vitrine)** : `front/web/src/modules/vitrine/data/pricing.ts` est deja correctement structure par locale (`Record<AppLocale, PricingPlan[]>`, aucune chaine turque au meme niveau que du francais) — corrige avant cette session. Le residu reel identifie et corrige ici : `front/web/src/app/(landing)/mobile/page.tsx` avait sa section CTA finale ("En attendant, demandez une demo...", lien "guide d'installation", bouton "Demander une demo") codee en dur en francais independamment du selecteur de langue `lang` de la page (qui pilote tout le reste du contenu via l'objet `copy`). Trois nouvelles cles (`waitingPrefix`, `installGuide`, `demoCta`) ajoutees a `LangCopy`/`copy` pour les 4 langues (`fr`/`en`/`tr`/`ar`) et branchees dans le JSX a la place du texte fixe.
- **Toujours ouvert / non traite dans cette session** : PA2-I18N-005 (PDF legaux), PA2-I18N-008 (formats date/devise), PA2-I18N-009/010/012 (extraction texte en dur mobile/web/admin), PA2-I18N-013 (kiosk), PA2-I18N-014/015 (CI + outillage), et la dette residuelle emails identifiee ci-dessus (templates orphelins a trancher). Voir tableau section 7 pour le detail par ticket.

---

## 8. Recapitulatif executif

| Domaine | Etat | Severite |
|---|---|---|
| Source de verite catalogue central (`shared/i18n`) | Correct, valide, synchronise | OK |
| CI anti-drift sur perimetre actuel | En place et fonctionnelle | OK |
| API Laravel — texte metier en dur | Quasi propre (1 occurrence) | Faible |
| PDF legaux — locale reellement appliquee | Catalogue pret, jamais branche | Eleve |
| Emails transactionnels — locale reellement appliquee | Catalogue pret, quasi jamais branche | Eleve |
| Formats date/devise selon langue active (web/admin) | Fixes en `fr-FR` malgre langue choisie | Moyen-eleve |
| Mobile employee/manager/platform_admin — usage reel des ARB | Infra prete, tres peu utilisee dans les ecrans | Eleve |
| Web Next.js et admin Vue — extraction texte en dur | Catalogues existent, couverture partielle | Eleve |
| Vitrine — melange de langues fige dans les donnees | Trompeur, a corriger en priorite | Moyen |
| Kiosk ZKTeco | Aucune infrastructure i18n | Eleve (perimetre isole) |
| Couverture CI sur zones a risque | Incomplete (mobile x3, kiosk, PDF/emails absents) | Moyen-eleve |
| Fiabilite outil de mesure de dette | Rapport actuel bruite, non pilotable en l'etat | Moyen |
