# MESSAGE — registre canonique (protocole P03)

**Une seule source de vérité pour présenter Leopardo** (vitrine, README, stores, pitchs). Toute divergence signalée comme bug de contenu.

> **#7428 (2026-09-16)** — Leopardo n'est plus présenté comme un « logiciel RH » : c'est une **suite métier**. Règle complète, décision et plan d'application : `docs/REFERENTIEL_PRODUIT/POSITIONNEMENT_SUITE_METIER.md`.

## Pitch canonique (1 phrase)
FR : « Leopardo est la suite métier des entreprises de terrain — RH & paie, pointage, absences, CRM, comptabilité et opérations, sur web, mobile et bornes. »
EN : "Leopardo is the business suite for field-based companies — HR & payroll, attendance, leave, CRM, accounting and operations, on web, mobile and kiosks."
TR : « Leopardo, saha ekipleri için işletme yönetimi paketidir — İK ve bordro, yoklama, izin, CRM, muhasebe ve saha operasyonları; web, mobil ve kiosk üzerinde. »
AR : « ليوباردو حزمة الأعمال للشركات الميدانية — الموارد البشرية والرواتب، الحضور، الإجازات، إدارة العملاء، المحاسبة والعمليات الميدانية، عبر الويب والجوال وأجهزة الحضور. »

## Positionnement (à utiliser tel quel)
- **Cible** : PME terrain 5-250 salariés — marchés prioritaires Maghreb (DZ/MA/TN), Afrique de l'Ouest (SN/CI), Turquie.
- **Différenciation** : mobile-first + paie multi-pays + biométrie/kiosk + open-source, multi-tenant.
- **Promesses autorisées** : trial 14 j sans CB ; démo guidée ; onboarding < 30 min ; export paie ; isolation tenant.
- **Promesses INTERDITES** (anti-sur-promesse, garde #7058) : « conformité légale validée » (les règles pays sont en statut *pilot* — cf. warning `/me/balance`) ; chiffres d'adoption non sourcés ; « disponible sur iOS » tant que TestFlight n'est pas public ; prix fermes hors page pricing à jour.

## Termes
- **Toujours « Leopardo »** comme nom de produit ; la **catégorie** est « suite métier » (FR) / « business suite » (EN) / « işletme yönetimi paketi » (TR) / « حزمة الأعمال » (AR).
- **Jamais** « logiciel RH », « SaaS RH », « HR SaaS », « HR software » comme *catégorie* — « RH & paie » reste nommable comme **contenu** de la suite.
- Le nom complet historique **« Leopardo RH »** n'est plus une dénomination : il ne subsiste que comme **identifiant technique figé** (dépôt, domaine, bundle ids, paquets Dart, commandes `leopardo:*`, clés de stockage). Liste exhaustive : `POSITIONNEMENT_SUITE_METIER.md` §4 — et un renommage de ces identifiants est **explicitement écarté**.
- « Vitrine » = site marketing (`front/web`, Vercel). « Portail client » = dashboard des tenants. « Plateforme » = super-admin.
- Voir `docs/REFERENTIEL_PRODUIT/TERMES.md` pour le lexique complet.

## Révision
Rituel mensuel de revue vitrine & protocoles (issue #7066) — toute modification de ce fichier passe par une PR dédiée.
