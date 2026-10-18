# Audit du générateur de site existant — BC-27 Showcase

**État du dépôt audité :** branche `main`, commit `9c78677cf22f393f24dd3435193643658078e9cd` (checkout local superficiel). Audit statique du dépôt seulement; aucune migration, modification de code, interrogation de base de production ou exécution des suites de tests n’a été effectuée. Les lignes citées sont celles du commit audité.

## Alertes sécurité et publication

1. **À traiter avant d’ouvrir plus largement le contenu public :** les schémas de blocs acceptent des URL sous forme de chaînes, sans validation sémantique évidente des protocoles (`image_url`, `cta_url`, liens de pied de page); le rendu les place dans des attributs `src`/`href`. Les fichiers examinés ne démontrent pas une exploitation XSS, mais la politique d’URL n’est pas assez explicite pour un contenu public. Références : `api/app/Modules/Showcase/Domain/Support/ShowcaseSectionSchemaRegistry.php:43-56, 84-106, 170-192`, `api/app/Modules/Showcase/Domain/Support/ShowcaseSectionSchemaValidator.php:151-171`, `front/web/src/app/vitrine/[slug]/page.tsx` (rendu des blocs). **Criticité P1, validation défensive à effectuer et tester avant extension des blocs.**
2. Le jeton d’aperçu est conservé en base en clair et son API indique `expires: null`; il est transmis dans une query string. La rotation et la publication le révoquent, mais les logs, historiques et en-têtes Referer constituent une exposition résiduelle. Références : `api/app/Modules/Showcase/Application/Actions/RotateShowcasePreviewTokenAction.php:12-27`, `api/app/Modules/Showcase/Interfaces/Api/V1/Controllers/ShowcaseController.php:101-114`, `api/app/Modules/Showcase/Interfaces/Api/V1/Controllers/ShowcasePublicController.php:93-107`. **P1 de durcissement avant extension à des Pages plus largement découvrables; aucun accès non autorisé n’a été observé dans cet audit.**
3. **Écart de publication/SEO :** le front sert la page navigateur sous `/vitrine/{slug}`, alors que l’API/sitemap et le champ `canonical_path` emploient `/public/vitrine/{slug}`. En outre, le sitemap Next des pages marketing ne référence pas les vitrines tenant; le sitemap API est limité aux 500 premiers tenants. Références : `front/web/src/app/vitrine/[slug]/page.tsx:14-24, 202-221`, `api/app/Modules/Showcase/Interfaces/Api/V1/Resources/VitrinePublicResource.php:165-170`, `api/app/Modules/Showcase/Interfaces/Api/V1/Controllers/ShowcasePublicController.php:146-194`, `front/web/src/app/sitemap.ts`. **P1 de correction/validation avant migration SEO et annuaire.**
4. L’XML du sitemap Showcase émet des éléments `xhtml:link` sans déclaration visible du namespace XHTML dans le `urlset` (`ShowcasePublicController.php:151-184`). Vérifier par validation XML automatisée et corriger si confirmé. **P2, constat statique; aucun parseur n’a été lancé.**
5. Les médias Showcase utilisent le disque Laravel `local` (`api/app/Modules/Showcase/Infrastructure/Services/ShowcaseMediaService.php:17-31, 39-40, 76-98`); la présence/configuration S3 générique n’implique pas que Showcase l’utilise. Durabilité après redémarrage/redéploiement en production : **non déterminée**. Références complémentaires : `api/config/filesystems.php:16, 33-39, 60-70` (si ce chemin existe dans le checkout; confirmer la configuration d’environnement avant décision).

**Constat XSS :** le contenu textuel visible est rendu comme texte React; aucun `dangerouslySetInnerHTML` n’a été identifié dans le rendu Showcase audité. Les URL et valeurs de thème doivent cependant être traitées comme non fiables. Le code examiné présente de bons garde-fous d’isolation : routes tenant privées protégées, `BelongsToCompany`, résolution publique de tenant, DTO public en allowlist, aperçu non mis en cache, et vérifications 404 sur les sections/médias d’un autre tenant. Références : `api/routes/modules/showcase.php:31-78`, `api/app/Modules/Showcase/Interfaces/Api/V1/Controllers/ShowcasePublicController.php:71-139`, `api/app/Modules/Showcase/Interfaces/Api/V1/Resources/VitrinePublicResource.php:17-36, 64-91, 177-196`, `api/app/Modules/Showcase/Interfaces/Api/V1/Controllers/ShowcaseSectionController.php:77-112`, `api/app/Shared/Traits/BelongsToCompany.php:39-132`. Aucun défaut d’accès inter-tenant exploitable n’a été démontré dans l’audit statique; cela ne remplace pas les tests d’intrusion ou l’exécution de tests.

## Verdict par composant

| Composant | Verdict | Preuves et justification |
|---|---|---|
| BC de génération, commandes et workflow | ✅ Réutilisable | `api/app/Modules/Showcase/` suit Application/Domain/Infrastructure/Interfaces; routes privées/public séparées (`api/routes/modules/showcase.php:31-78`); création, publication, dépublication et aperçu existent. C’est le socle le plus pertinent, plutôt qu’un générateur à rebâtir de zéro. |
| Modèle Showcase et contenu courant | ⚠️ À ajuster | `company_showcases` impose une seule vitrine par tenant (`2026_09_06_000003_6865_create_company_showcases_table.php:27-45`). Il faut découpler une Page par établissement, gérer les slugs publics globalement et définir la relation à l’établissement. Le champ `custom_domain` n’est qu’une réserve de schéma, pas une fonctionnalité. |
| Sections typées et contenu structuré | ⚠️ À ajuster | `company_showcase_sections` conserve `type`, `content` JSON, ordre et `schema_version` (`2026_09_08_000002_6866_create_company_showcase_sections_table.php:30-45`); le registre valide des schémas par type. C’est réutilisable, mais les types sont actuellement fermés au registre Showcase; les BC verticaux ne peuvent pas déclarer leurs types de blocs de façon autonome. |
| Modèle horizontal d’établissement | ✅ Réutilisable | `App\Core\Tenant\Domain\Models\Site` est déjà tenant-scoped et porte nom, code, type, statut, adresse, GPS, fuseau, devise (`api/app/Core/Tenant/Domain/Models/Site.php:13-79`). Le code stable par tenant est documenté et indexé (`api/database/migrations/tenant/2026_10_17_120000_8624_sites_first_class.php:23-75`). Ajouter un second agrégat « Location/Establishment » créerait un doublon. |
| Adresse, horaires, média et données publiques du lieu | ⚠️ À ajuster | `Site` n’a qu’une adresse texte et pas d’horaires d’ouverture, média ou contacts structurés. `RestaurantBranch` possède un profil public, géolocalisation et relation d’horaires, mais appartient au vertical Restaurant (`api/app/Modules/RestaurantManager/Domain/Models/RestaurantBranch.php:15-84`). Réutiliser les concepts; ne pas faire de `RestaurantBranch` le socle horizontal. |
| Publication HTML/SSR | ⚠️ À ajuster | Une route Next publique SSR existe sous `front/web/src/app/vitrine/[slug]/page.tsx:202-221`; elle consomme le DTO de l’API Laravel. La doc de spécification historique qui parle de rendu Laravel (`docs/specifications/SOLUTION_SITE_VITRINE.md:37-39`) est donc à corriger. Rendre le nouveau profil Page dans l’écosystème via un point de résolution public stable, puis brancher les domaines premium. |
| Domaine personnalisé | ❌ À reconstruire | Seul `custom_domain` nullable est présent dans la migration Showcase; aucun flux d’enregistrement, de vérification DNS, de routage Host ou de certificat n’a été trouvé. Le dépôt documente Vercel pour `front/web` et Render pour l’API; le chemin exact du rendu public et les plans comptes de production doivent être confirmés. |
| Internationalisation/RTL | ⚠️ À ajuster | Showcase accepte `fr/en/ar/tr` et `content_i18n` (`ShowcaseLocales.php`; migration `2026_09_10_000002_6874_showcase_sections_i18n.php:11-35`). Le front principal a un support RTL arabe (`front/web/src/app/layout.tsx:158-175`), mais le traitement directionnel du nouveau rendu public établi par Page doit être testé. La parité des catalogues marketing ne prouve pas la parité de contenu des tenants. |
| SEO, sitemap, Open Graph | ⚠️ À ajuster | Le DTO dérive `title`, `description`, `og_image`, canonical (`VitrinePublicResource.php:133-171`), le contrôleur fournit sitemap/robots (`ShowcasePublicController.php:142-211`). Les incohérences d’URL et d’intégration du sitemap sont à corriger avant migration. |
| Médias de Page | ⚠️ À ajuster | Upload limité, noms générés et service public existent (`ShowcaseMediaService.php:46-60, 133-207`; `ShowcaseMediaPublicController.php:42-75`); le disque local durci ne garantit pas de durabilité/scale partagée en production. |
| Avis, messagerie, paiement, géolocalisation | ❌ À construire au niveau Page | Pas d’intégration générique Page trouvée. Des capacités verticales (ex. commande/avis Restaurant) existent, mais aucun contrat d’action commun au BC Pages n’a été identifié. Les connexions doivent rester des références/ports et ne pas importer directement les domaines des verticaux. |
| Tests et assurance qualité | ⚠️ À ajuster | Tests Showcase ciblés présents (`api/tests/Feature/Showcase/*`, `api/tests/Unit/Showcase/ShowcaseSectionSchemaTest.php`); les E2E authentifiés peuvent être conditionnels et la suite front ne couvre pas nécessairement chaque route de Page tenant. Aucun test n’a été exécuté pendant cette mission. |

## Architecture et données actuelles

Le BC est `api/app/Modules/Showcase/` (BC-27). La migration `company_showcases` impose une vitrine par tenant, un slug global unique, un statut `draft|published`, thème/settings JSON et `custom_domain` nullable. Les sections sont ordonnées, typées et versionnées. `content_i18n` ajoute les surcharges `fr/en/ar/tr`, avec repli vers le contenu principal. Les images sont conservées par un service Laravel sur disque `local` et servies par une route contrôlée; l’infrastructure réelle du disque en production n’est pas vérifiée.

Le rendu public est SSR par Next Server Component, et non un fichier statique ni un rendu HTML Laravel : `/vitrine/[slug]` appelle l’API Laravel pour obtenir les données; l’API publique `/api/v1/public/vitrine/{slug}` sert un JSON dédié. L’éditeur locataire existe dans `front/web/src/app/(dashboard)/showcase/page.tsx`; un éditeur Superadmin Vue existe dans `front/admin-dashboard/src/views/showcase/ShowcaseEditorView.vue`. La dérive entre ces deux surfaces doit être limitée par un contrat commun.

Le modèle transversal déjà existant est `Site` sous `Core/Tenant`. Il ne connaît pas l’horaire d’ouverture; les `schedules` dans la migration initiale décrivent des horaires de travail RH et ne sont pas des horaires publics. `RestaurantBranch` et `RestaurantHour` sont des précédents verticaux plus riches, à ne pas transformer en entités horizontales. L’adresse structurée multi-pays, les exceptions d’horaires et le fuseau par défaut sont à concevoir. Pas de volume/migration de production vérifiable depuis le dépôt seul.

## Hébergement et domaines

L’architecture du monorepo documente `front/web` déployé sur Vercel et un backend Laravel exécuté sur Render (`ARCHITECTURE.md`, sections Vue d’ensemble et Infrastructure; `docs/ops/DEPLOYMENT_URLS.md:90-110`). Le rendu SSR du Showcase étant Next, un domaine personnalisé doit pointer vers le service qui rend la page, pas uniquement vers le backend JSON. Les environnements, noms de domaines et variables effectives d’URL/DNS en production ne sont pas consultables dans ce dépôt.

- **Vercel Domains API / plateforme Vercel :** s’intègre directement au rendu Next et dispose d’un flux ajout → DNS TXT/CNAME/A → vérification → certificat; Vercel décrit l’ajout des domaines via SDK/API et l’émission TLS automatique. Les domaines wildcard exigent un accès aux challenges DNS; les comptes Hobby sont limités à 50 domaines personnalisés par projet et l’offre Hobby est réservée à l’usage personnel non commercial. À revalider selon le compte et les tarifs du tenant Leopardo. [1] [2]
- **Cloudflare for SaaS :** option de proxy multi-tenant plus découplée de Vercel; l’API Custom Hostnames permet de faire suivre le trafic vers le service d’origine. La grille officielle consultée indique 100 hostnames inclus, 50 000 maximum et 0,10 USD par hostname additionnel pour les paliers Free/Pro/Business. Il faut confirmer l’activation, l’origine compatible et les coûts/produits associés; ce service n’est pas démontré comme activé dans le dépôt. [3]
- **Décision recommandée à valider :** privilégier un seul point de terminaison SSR public et un fournisseur d’entrée (Vercel si le domaine pointe vers `front/web`, Cloudflare for SaaS si vous souhaitez un proxy indépendant), avec un registre interne `tenant_domain` et un adaptateur fournisseur. Ne pas mettre le domaine dans un simple champ de contenu et ne pas automatiser la modification des DNS du tenant.

## Cartographie et géocodage

Le stockage actuel `Site.gps_lat/gps_lng` convient à des coordonnées saisies/confirmées; il ne fournit pas géocodage, recherche de lieux ou tuiles cartographiques. Pour un MVP multi-région, **Geoapify est le candidat à évaluer en premier** : l’offre publiée donne 3 000 crédits/jour gratuits et jusqu’à 5 requêtes/s; le forfait API 10 est affiché à 59 USD/mois pour 10 000 crédits/jour. La page indique l’usage commercial en production sur l’offre gratuite dans ses quotas avec attribution. Les coûts sont en USD, hors taxes et sujets à changement. Ajouter une interface fournisseur, du cache, une limite d’usage et une saisie manuelle de coordonnées. [4]

MapTiler annonce une offre Flex à 30 USD/mois incluant 3 000 sessions de recherche mensuelles et 500 000 requêtes API, mais son offre gratuite est indiquée comme adaptée au test/prototypage/personnel/non commercial. Ce peut être une alternative cartographique, sous réserve de comparer la couverture locale et les termes d’attribution. [5] Le service public Nominatim d’OpenStreetMap n’est pas une dépendance de production recommandée : maximum absolu d’une requête/s, pas d’autocomplétion, cache/proxy demandé et service révocable sans préavis. Cela ne s’applique pas à une instance Nominatim auto-hébergée ou un fournisseur tiers. [6]

## Dette, tests et risques de migration

- Les migrations Showcase n’ont pas de FK SQL pour les références vitrine/section; l’intégrité repose sur les scopes/contrôles de l’application (`...6866...:20-22`, `...6872...:24-26`). Cela impose des tests de cohérence et un nettoyage des orphelins avant toute bascule.
- La nouvelle entité doit intégrer les Sites déjà existants et les lieux verticaux au lieu de repartir d’un schéma vide. Les formes d’adresse/GPS et slugs diffèrent. La branche horizontale peut préserver le `Site` comme identité géographique/opérationnelle tandis que les verticales gardent leurs données spécifiques et les exposent via contrats.
- La unicité « une Showcase par tenant » ne se transpose pas en « une Page par Site » sans migration additive et règles de conversion. Les anciens tenants sans Site principal ou avec plusieurs Sites exigent une stratégie d’affectation non destructive.
- Les fichiers de production, DNS et volumes de données des tenants, la configuration réelle de stockage, la couverture de migrations déployées et le nombre d’anciens sites publiés sont **non déterminés** depuis le dépôt.
- Tests observés : `api/tests/Feature/Showcase/ShowcaseApiTest.php`, `ShowcasePublicApiTest.php`, `ShowcasePublishWorkflowTest.php`, `ShowcaseMediaApiTest.php`, `ShowcaseI18nThemesProductsTest.php`, `ShowcaseSectionApiTest.php`, `CompanyShowcaseDomainTest.php`, `api/tests/Unit/Showcase/ShowcaseSectionSchemaTest.php`; `api/tests/Feature/BusinessOs/SitesFirstClassTest.php` et `SiteApiContractTest.php`. La présence de tests ne vaut pas résultat vert. Les builds, tests et appels de production n’ont pas été lancés.

## Commandes et périmètre de preuve

Commandes représentatives lancées dans ce dépôt :

```bash
git rev-parse HEAD
git status --short --branch
find api/app/Modules/Showcase -type f | sort
find api/database/migrations/tenant -maxdepth 1 -type f -iname '*showcase*' | sort
rg -n -i 'showcase|vitrine|custom_domain|public/vitrine' api front .github docs
rg -n 'Schema::create\('\''sites' api/database/migrations
nl -ba <fichier> | sed -n '<lignes de début>,<lignes de fin>p'
```

Le clonage et ces commandes ont servi à établir les preuves de fichiers/ligne; aucun contenu suivi du dépôt n’a été modifié dans l’audit. Les documents de conception du présent lot sont les seuls changements prévus sur la branche dédiée.

## Références externes

[1]: https://vercel.com/docs/platforms/multi-tenant-platforms/configuring-domains "Vercel Configuring Custom Domains for Multi-Tenant Platforms"
[2]: https://vercel.com/docs/domains/working-with-domains/add-a-domain "Vercel Adding and Configuring a Custom Domain"
[3]: https://developers.cloudflare.com/cloudflare-for-platforms/cloudflare-for-saas/plans/ "Cloudflare for SaaS Plans"
[4]: https://www.geoapify.com/pricing/ "Geoapify API Pricing"
[5]: https://www.maptiler.com/cloud/pricing/ "MapTiler Cloud Pricing"
[6]: https://operations.osmfoundation.org/policies/nominatim/ "Nominatim Usage Policy"
