# Positionnement : « Leopardo » est une **suite métier** (décision P03, issue #7428)

**Statut** : décision prise · **Date** : 2026-09-16 · **Portée** : toutes les surfaces de présentation (vitrine, README, stores, e-mails, docs, apps).
**Décideur** : PM (autorisation explicite du fondateur sur la session du 2026-09-16) · **Protocole** : P03 (MESSAGE / TERMES) · **Remplace** : la règle « Toujours Leopardo RH » de `MESSAGE.md`.

---

## 1. Le constat, en une phrase

Le dépôt présentait Leopardo comme un **logiciel RH / HR SaaS**, alors que le produit porte des outils **RH *et* horizontaux *et* verticaux métier** (compta, CRM, vitrine, formation, agences de voyage, stations-service, restauration, éducation, flottes, caméras…). Qualifier le produit de « RH » ne le minimise pas seulement : **c'est faux**, et cela oblige à réexpliquer ce que le produit est.

## 2. Décision

| Élément | Décision |
|---|---|
| **Marque** | **Leopardo** (mot seul). Le nom complet **« Leopardo RH »** n'est plus une dénomination de présentation : c'est un **identifiant historique figé** (voir §4). |
| **Catégorie** (FR) | **suite métier** — jamais « logiciel RH », « SaaS RH », « logiciel de gestion RH ». |
| **Catégorie** (EN) | **business suite** — jamais « HR SaaS », « HR software ». |
| **Catégorie** (TR) | **işletme yönetimi paketi** — plus « İK yazılımı ». |
| **Catégorie** (AR) | **حزمة الأعمال** — plus « نظام موارد بشرية ». |
| **Exception assumée** | Dans une **phrase de présentation**, la brique RH reste nommable : « RH & paie » est un *contenu*, pas la *catégorie*. |

### Phrases canoniques (à réutiliser telles quelles)

- **FR** : « Leopardo est la suite métier des entreprises de terrain — RH & paie, pointage, absences, CRM, comptabilité et opérations, sur web, mobile et bornes. »
- **EN** : "Leopardo is the business suite for field-based companies — HR & payroll, attendance, leave, CRM, accounting and operations, on web, mobile and kiosks."
- **TR** : « Leopardo, saha ekipleri için işletme yönetimi paketidir — İK ve bordro, yoklama, izin, CRM, muhasebe ve saha operasyonları; web, mobil ve kiosk üzerinde. »
- **AR** : « ليوباردو حزمة الأعمال للشركات الميدانية — الموارد البشرية والرواتب، الحضور، الإجازات، إدارة العملاء، المحاسبة والعمليات الميدانية، عبر الويب والجوال وأجهزة الحضور. »

> ⚠️ Les traductions TR/AR sont **proposées par l'agent** : elles doivent être relues par un locuteur natif avant une campagne de communication (voir §6, étape 3). Aucune promesse nouvelle n'est introduite dans ces phrases (garde anti-sur-promesse #7058).

## 3. Pourquoi ce choix plutôt qu'un autre nom

- Le **nom propre** (`leopardo-rh.com`, bundle ids `com.leopardo.*`, paquets Dart, dépôt `kitokoh/leopardo-hr`, images, clés de stockage) est **figé dans l'existant** : le changer coûte une migration d'identifiants (déconnexion des utilisateurs, publication de nouveaux binaires sur les stores, redirections) pour **zéro** gain produit.
- Ce qui coûte cher dans le constat, c'est la **catégorie** (« RH » comme définition), pas la marque. En séparant les deux, on corrige le fond **sans** migration technique.
- C'est cohérent avec ce que le dépôt dit déjà par ailleurs (`README.md` : *open-source business operations platform*).

## 4. Non renommable — **décision d'arrêt** (ne pas tenter, même plus tard)

| Identifiant | Valeur |
|---|---|
| Dépôt, badges, canonical | `kitokoh/leopardo-hr`, `kitokoh.github.io/leopardo-hr` |
| Domaine | `leopardo-rh.com` (+ sous-domaines `proxy.leopardo-rh.com`…) |
| Bundle ids natifs | `com.leopardo.rh`, `com.leopardo.employee`, … |
| Paquets/dossiers Dart, `melos.yaml` | `leopardo_*` |
| Commandes Artisan | `leopardo:*` |
| Clés Redis / salts | `leopardo*` |
| `APP_NAME` / `MAIL_BRAND_NAME` | valeur courante (impact e-mails sortants) |
| Clés de stockage navigateur | `auth_user`, `preferred_locale` (**un renommage déconnecte**) |

Règle : ces identifiants ne doivent **jamais** apparaître dans une phrase de présentation, et ne doivent **jamais** être renommés « pour l'alignement » — les colonnes ci-dessus sont la référence.

## 5. Application par étapes (plan opposable)

| # | Surface | Fichiers | Statut |
|---|---|---|---|
| 1 | Sources de vérité internes | `docs/REFERENTIEL_PRODUIT/MESSAGE.md`, `TERMES.md`, ce document | ✅ **fait** (cette PR) |
| 1bis | README + fiche GitHub du dépôt | `README.md`, `package.json` (description), métadonnées GitHub : description, topics, homepage (hors code) | ✅ **fait** (2026-09-19) — pitch canonique EN, carte produit avec les solutions verticales, description GitHub « business suite » |
| 2 | Vitrine & SEO public | `modules/vitrine/lib/seo.ts` (42 occ.), `data/faq-page.ts` (27), `legal-content.ts` (20), `data/videos.ts` (18), `case-studies.ts` (14), `lib/vitrine-locale.ts`, `app/llms*.txt`, `components/JsonLd.tsx`, `site/gh-pages/index.html`, `app/layout.tsx` (13), `app/manifest/route.ts`, catalogues i18n | ⏳ suivi par issue dédiée (copy marketing → relecture fondateur) |
| 3 | E-mails transactionnels | `api/lang/{fr,en,tr,ar}/emails.php` (≈21 chacun) + `MAIL_BRAND_NAME` | ⏳ à faire **avec** test d'envoi (le changement de nom expéditeur est visible client) |
| 4 | Stores & apps | `android:label`, `Info.plist`, libellés de fiches | ⏳ **hors code** : nécessite une publication de version |
| 5 | Admin plateforme | `front/admin-dashboard/index.html` + i18n (12 occ.) | ⏳ |

Le classement est volontairement **par rentabilité et par risque** : la copie publique avant les identifiants, jamais l'inverse.

## 6. Garde de non-régression

Tant que les étapes 2-5 ne sont pas faites, la dette ne doit pas **grossir** : `dev-hub/tools/check-naming-drift.sh` refuse toute **nouvelle** occurrence de catégorie interdite (« logiciel RH », « SaaS RH », « HR SaaS », « HR software », « İK yazılımı ») sur les surfaces publiques, en s'appuyant sur un **instantané de référence** (`dev-hub/tools/naming-baseline.json`). La dette existante est donc *gelée* et mesurée : le fichier de référence ne peut que **décroître**, et son évolution est visible en revue.

### Ce que la garde ne compte PAS comme dette

Deux usages sont **volontairement conservés** (donc figés, pas interdits) :

- **les listes de mots-clés SEO** — `rootSeoL10n.keywords` (« SaaS RH », « logiciel RH », « HR SaaS », « İK yazılımı », « نظام موارد بشرية سحابي ») et les `keywords` de `pageMetadata` : ils captent l'**intention de recherche** existante. Critère 4 de l'issue : « le repositionnement élargit, il ne remplace pas » — retirer ces mots-clés ferait perdre du trafic sans rien gagner ;
- **les usages descriptifs** — un titre d'étude de cas (« HR software for a fast-growing startup ») ou un libellé de résultat (« HR software cost ») décrivent un **poste de coût**, ils ne définissent pas le produit.

État mesuré après cette tranche : **24 occurrences** (contre 30 avant), toutes de ces deux natures, dans 4 fichiers de la vitrine. La garde les fige : elles ne peuvent que décroître, et toute hausse (même dans un fichier encore propre) échoue.

## 7. Ce que la décision ne change pas

- Aucune ligne de code produit, aucun identifiant technique, aucune donnée.
- Les promesses autorisées/interdites de `MESSAGE.md` (§ anti-sur-promesse) restent **inchangées**.
