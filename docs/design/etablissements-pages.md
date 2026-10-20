# Conception — Établissements & Pages

**Statut :** proposition à valider; aucune décision ci-dessous ne vaut autorisation d’implémenter. Cette conception s’appuie sur l’audit du commit `9c78677cf22f393f24dd3435193643658078e9cd` et sur les sources citées dans [`docs/audits/audit-generateur-site.md`](../audits/audit-generateur-site.md).

## 1. Intention et frontières

Un **Établissement** est le lieu opérationnel durable, rattaché à un tenant et réutilisable par tous les verticaux. Une **Page** est sa présence publique dans Leopardo. Le site autonome sur domaine propre est une surface premium qui consomme la même Page publiée, sans dupliquer ses données. La Page devient ainsi la source canonique des contenus publics et de la découverte; le rendu web n’est qu’un adaptateur.

Le modèle générique existant `App\Core\Tenant\Domain\Models\Site` est le candidat naturel pour le noyau Établissement. Il possède déjà code/type/statut, adresse texte, GPS, fuseau et devise. Il lui manque notamment une adresse structurée, des contacts/médias publics, des horaires d’ouverture et un processus de vérification. Ne pas assimiler les horaires RH de `schedules` aux horaires d’ouverture. `RestaurantBranch`/`RestaurantHour` fournit un précédent vertical plus riche, mais ne doit pas devenir la dépendance horizontale. Les écarts et preuves figurent dans l’audit.

Le BC Pages doit dépendre du noyau Tenant/Site et consommer des contrats fournis par les verticaux. Les verticaux ne doivent pas importer les modèles Eloquent de Pages ni imposer au contenant de connaître leurs tables. Identity, Paiements, Messagerie, Géolocalisation, Réputation, Découverte et Billing fournissent des ports ou consommateurs d’événements selon le besoin.

## 2. Modèle de données proposé

### Établissement

Entité `Site`/Établissement rattachée par `company_id`, avec identifiant stable et `code` unique au sein du tenant. Garder les champs existants (`name`, `site_type`, `status`, `gps_lat/gps_lng`, `timezone`, `currency`) et migrer progressivement les consommateurs vers des value objects/DTO compatibles. Le type reste ouvert à de nouveaux codes validés par politique de domaine, pas à une énumération de tous les métiers dans Pages.

Ajouter une adresse structurée indépendante du fournisseur de géocodage : `country_code` ISO 3166-1 alpha-2, lignes d’adresse, localité, région/département, code postal optionnel, libellé normalisé et éventuellement instructions d’accès. Conserver temporairement le texte historique et établir une règle de priorité/retour lors de l’affichage. Les champs facultatifs reflètent les formats des marchés CEMAC, Maghreb, Europe et Turquie; le code postal ne doit pas être requis universellement.

Les coordonnées doivent garder leurs limites de latitude/longitude, un indicateur `precision` ou source (`manual`, `provider`, `verified`), la date d’actualisation et, si utile, la référence externe selon les conditions du fournisseur. Permettre la saisie/correction humaine; le géocodage n’est jamais une précondition à la création d’un lieu.

Ajouter les contacts avec type et visibilité (`phone`, `email`, `website`, `messaging`), une collection de médias référencés par ID et un fuseau IANA du lieu. Ne pas copier les clés de stockage ni les URLs non contrôlées dans un DTO public.

Les horaires d’ouverture ne doivent pas réutiliser les horaires de travail RH. Prévoir une collection par jour ISO, avec plages locales pouvant contenir plusieurs intervalles (ex. pause de midi), état « fermé » explicite et exceptions datées (jours fériés, fermeture exceptionnelle, horaires spéciaux). Valider `opens < closes`, traiter les plages traversant minuit par une règle à décider et toujours interpréter les heures dans le fuseau de l’établissement. Fournir un état « horaires non renseignés » distinct de « fermé ».

Les rôles d’accès spécifiques à l’établissement ne sont pas démontrés comme un contrat horizontal déjà prêt. Ils relèvent d’Identity/RBAC; la conception doit éviter d’encoder des propriétaires arbitraires dans `Site` avant validation de la stratégie Identity.

### Page

Nouvelle entité `Page`, une par Établissement dans la première version, indexée par `site_id` et `company_id` et protégée par une contrainte d’unicité. Garder `company_id` dans la table pour le scope tenant; toute relation Site/Page doit vérifier le même tenant, en plus des scopes applicatifs. La migration de l’actuel « une vitrine par tenant » à « une Page par établissement » doit gérer les tenants qui n’ont pas de Site principal et éviter l’affectation implicite d’une Page au mauvais lieu.

Propriétés minimales : `id`, `company_id`, `site_id`, `slug` public global unique, état, locales disponibles, langue par défaut, liste ordonnée de blocs, SEO éditable ou dérivé avec règles documentées, horodatages et version de schéma. États proposés : `draft`, `published`, `verified`, `suspended`. **`verified` décrit la vérification de la Page/du lieu et non une publication automatique.** Suspendre doit retirer la Page des résultats publics, domaines et sitemaps tout en gardant le brouillon et les preuves pour modération.

L’unicité globale du slug doit être transactionnelle et indépendante de la colonne `company_showcases.slug`; prévoir redirections/réservations de l’ancien slug lors d’un renommage. Ne pas inférer le slug d’un nom de société après la création initiale.

## 3. Blocs de contenu extensibles

Réutiliser les idées déjà éprouvées dans Showcase — blocs JSON typés, ordre explicite et `schema_version` — mais remplacer le registre fermé par un **contrat de contribution**. Pages possède le contenant et le validateur/orchestrateur; chaque BC vertical enregistre ses types et schémas par un manifeste/port. Les détails du mécanisme de découverte de plugins restent à figer avant codage.

Contrat recommandé d’un bloc : `type` qualifié (ex. `core.hero`, `restaurant.menu`, `retail.catalog`), `schema_version`, `owner_bc`, `position`, contenu localisé et références médias. Valider avec un schéma strict (`additionalProperties: false` par défaut), une liste d’URL externes/protocoles permise, des tailles/cardinalités maximales et un rendu accessible. Ne pas enregistrer de HTML arbitraire. Le front public choisit un renderer enregistré; bloc inconnu ou version non supportée est omis avec télémétrie contrôlée, jamais exécuté comme markup brut.

La migration initiale peut réutiliser les types `hero`, `features`, `gallery`, `testimonials`, `products`, `contact` et `footer` du BC Showcase en les renommant/encapsulant dans un espace de noms neutre. Le bloc `products` montre déjà une dépendance optionnelle vers `PublishedProductsProvider`; généraliser ce pattern en contrat partagé/versionné sans importer les modèles Catalogue.

## 4. Événements et intégrations

Émettre via outbox, seulement après commit, des événements versionnés : `EstablishmentCreated`, `EstablishmentUpdated`, `EstablishmentLocationChanged`, `EstablishmentVerificationRequested`, `EstablishmentVerified`, `EstablishmentSuspended`, `PageCreated`, `PagePublished`, `PageUpdated`, `PageUnpublished` et `PageSuspended`. Inclure les IDs du tenant/établissement/Page, version de contrat, date, changement pertinent et statut; ne pas inclure de PII de vérification, de média secret ou de données de paiement. Les consommateurs doivent être idempotents et reconstruire un état à partir de la clé et de la version.

- **Découverte/Annuaire :** consomme Page publiée + Site public, et projette nom, catégorie, localisation, horaires, disponibilité, slug et médias explicitement publics. Indexer des coordonnées exactes uniquement si la visibilité/consentement métier le permet. Retirer immédiatement une page suspendue/dépubliée.
- **Paiements :** le bloc d’action appelle une capability/URL d’intention fournie par Paiements; la Page ne garde ni secrets ni données carte. La redirection doit être générée/validée côté serveur.
- **Messagerie :** lien Écrire vers un identifiant conversationnel opaque fourni par le BC Messagerie; authentification, consentement et anti-spam restent de son ressort.
- **Géolocalisation :** Y aller peut fournir coordonnées et adresse formatées; ne pas exposer de trace de visite ou de position d’utilisateur.
- **Réputation :** le bloc Avis utilise un provider public agrégé et des commandes rédigées/modérées dans le BC Réputation; Page n’autorise pas de faux avis par injection de payload.
- **Verticaux :** le BC vertical fournit ses blocs/actions via contrat déclaré; Pages n’importe pas ses tables.

### Interface minimale pour avancer en parallèle

1. **Identity** fournit `tenant_id`, `site_id`, acteur authentifié et décision `can_manage_site(actor, site_id, permission)`; proposition séparée à valider pour rôles `owner`, `manager`, `editor` et `viewer` par établissement. Toute réponse doit être fail-closed et refléter l’appartenance tenant.
2. **Billing** expose `has_entitlement(tenant_id, feature='custom_domain')` et les limites incluses (nombre de domaines, Page/site, analytics); aucune logique de plan codée en dur dans le contrôleur Pages.
3. **Paiements/Messagerie/Réputation/Géo** exposent des providers de capacités publiques versionnés et des entrées de commande documentées; Pages ne dépend que de leurs interfaces.
4. **Découverte** consomme les événements Page/Site et peut réindexer une projection; contrat de retrait/effacement et délai de propagation à convenir.

## 5. Vérification de propriété

Séparer le fait d’avoir un compte tenant, la capacité à éditer un Site et la preuve de contrôle d’un lieu public. Ne pas exposer les pièces et données de vérification au public.

Proposer trois méthodes configurables :

1. **SMS au téléphone professionnel saisi** : code court, durée de vie, limites de tentatives et cooldown; simple mais dépend du coût/couverture SMS et ne prouve pas le droit légal d’exploiter le lieu.
2. **Document justificatif** (registre d’entreprise, facture ou autorisation adaptée au pays) : revue humaine avec accès limité, conservation courte, motif de décision et journal d’audit; charge de traitement et risque de données personnelles élevés. Délai et base légale à établir par marché.
3. **Validation Superadmin** : approbation manuelle via dossier et procédure de litige; flexible au lancement mais coûteuse, lente et vulnérable à l’incohérence opérationnelle. Exiger double contrôle pour escalades sensibles.

État séparé de vérification (`unverified`, `pending`, `verified`, `rejected`, `disputed`, `revoked`), acteur/méthode/date, expiration éventuelle et historique immuable. Révoquer ou suspendre doit couper la visibilité et déclencher l’événement correspondant. Ne pas marquer vérifié à la seule validation d’un domaine DNS : la propriété du domaine ne prouve pas l’exploitation du lieu.

## 6. Publication sur domaine personnalisé et offre premium

Le domaine de l’écosystème (slug de Page) reste disponible selon les règles produit de publication. Un domaine personnalisé ajoute seulement une surface d’accès premium au même `page_id`; le contenu édité et le canonical primaire restent uniques.

Flux proposé :

1. Tenant demande un nom d’hôte; normaliser punycode/IDNA, rejeter les domaines réservés, suffixes de la plateforme, ports, IP privées/locales et hôtes déjà affectés. Vérifier le droit de gestion et le droit de plan.
2. Stocker une demande avec statut `pending_dns`; afficher des instructions TXT de propriété et CNAME (ou A/ALIAS si le provider et l’apex le permettent). Ne pas écrire dans la zone DNS du client.
3. Vérifier la propriété de manière répétée avec TTL raisonnable; ne pas activer le trafic avant la validation. Empêcher rebinding, dangling CNAME, collisions tenant et takeover par recyclage de domaine.
4. Provisionner le hostname via un adaptateur fournisseur, demander certificat, attendre statut SSL `active`, puis router par Host vers le renderer SSR et la Page exacte. Toujours vérifier host ↔ page en base et répondre fail-closed.
5. Canonicaliser vers le domaine principal de la Page, rediriger HTTP→HTTPS et conserver hreflang/metadata sur le même origin. Domaines non validés, suspendus ou au plan expiré sont désactivés avec une page d’état maîtrisée.
6. Sur suppression/renouvellement/plan expiré, révoquer le hostname, confirmer retrait du certificat/routage et conserver un audit de domaine. Prévoir l’idempotence et un job de réconciliation pour incidents.

**Option fournisseur à valider :** Vercel est le chemin le plus direct si le rendu de pages locataires reste sur le projet `front/web`; l’API permet ajout/vérification et émission TLS, avec DNS requis pour les wildcard. La documentation indique la limite de 50 domaines/projet pour Hobby, une offre réservée à l’usage personnel/non commercial et HTTPS inclus dans les plans listés. Pour une offre commerciale multi-tenant, confirmer l’éligibilité et les limites du projet Leopardo avant engagement. Cloudflare for SaaS est plus neutre vis-à-vis de l’origine et publie 100 hostnames inclus puis 0,10 USD par hostname additionnel avec un maximum 50 000 pour les plans Free/Pro/Business; vérifier service, origine, fonctionnalités et conditions contractuelles avant sélection. Les prix/tarifs sont indicatifs à la date de consultation; voir les références [Vercel multi-tenant](https://vercel.com/docs/platforms/multi-tenant-platforms/configuring-domains), [Vercel plans](https://vercel.com/pricing) et [Cloudflare for SaaS plans](https://developers.cloudflare.com/cloudflare-for-platforms/cloudflare-for-saas/plans/).

### Gating d’abonnement

Lire un entitlement `custom_domain` par tenant côté serveur au moment de la demande, du renouvellement et de chaque résolution publique pertinente. Décider si la désactivation intervient immédiatement après fin de période ou après grâce; définir quota par plan et comportement des domaines existants. Le BC Billing possède `EntitlementGuard`/plans, mais aucun contrat exact pour ce cas n’a été vérifié; la propriété commerciale reste à confirmer.

## 7. Migration du Showcase existant, sans interruption

Utiliser une migration additive avec double lecture/écriture transitoire; ne supprimer ni ne renommer les tables existantes dans la première livraison.

1. **Inventorier avant de basculer :** compter par tenant les Showcase, sections, médias, statut, slug, custom_domain renseignés, Site principal, pièces manquantes; exporter une sauvegarde vérifiée. Les données de production n’étaient pas accessibles pendant cet audit.
2. **Ajouter le schéma :** Page, slug registry, relation Page↔Site, tables d’adresse/horaire/exceptions, status/verification, domain requests et éventuel outbox. Les colonnes initialement nullables ou migrations dédiées, index compatibles avec la DB déployée et backfill rejouable; ne pas ajouter de contrainte obligatoire avant le backfill.
3. **Mapper :** créer une Page par ancienne `CompanyShowcase`; choisir le Site seulement selon une règle explicite (Site principal validé, sinon tenant en attente de résolution, jamais choix silencieux si plusieurs Sites). Migrer `slug`, `status`, `theme/settings/legal`, sections versionnées et traductions. Remplacer IDs Showcase par refs Page après validation des counts/contraintes; conserver les IDs externes ou une table de correspondance.
4. **Compatibilité :** pendant la période de double lecture, l’ancienne route `/vitrine/{slug}` résout la même Page que le nouveau profil; l’ancien éditeur écrit vers un adaptateur ou est mis en lecture seule selon la décision produit. Un seul store maître à chaque étape pour éviter la divergence.
5. **Canary :** choisir des tenants consentants; comparer old/new payloads, canonical, sitemap, cache, images et rendu en différentes locales. Tracer les erreurs sans contenu privé. Prévoir interrupteur de routage et rollback vers le chemin existant.
6. **Bascule progressive :** déplacer une fraction de tenants à la fois; contrôler 404/5xx, association tenant/Site, erreurs cache et TLS. Ne supprimer l’ancien code/tables qu’après validation fonctionnelle/SEO, fenêtre d’observation et sauvegarde/restauration testée.
7. **Rollback :** l’ancien endpoint et les données Showcase restent lisibles jusqu’au feu vert; interrompre les nouveaux domaines avant de réorienter les routes; répéter le backfill de manière idempotente. Les changements déjà apportés aux pages pendant double fonctionnement doivent pouvoir être réconciliés ou ramenés sans perte.

## 8. Surfaces produit

- **Tenant :** CRUD Sites, adresse structurée, GPS, contacts, horaires/exceptions, médias; choix d’un Site; éditeur de blocs par Page avec aperçu avant publication; traductions; état de vérification; domaine et état SSL; statistiques agrégées (visites, clics) avec consentement et politique de conservation. Masquer les options premium selon entitlement, mais afficher pourquoi un domaine est bloqué.
- **Superadmin :** recherche et modération des établissements/pages, vérification/revocation, litiges, motifs d’action, état d’abonnement, domaines/DNS/certificats et historique, outils de revalidation et suspension d’urgence. Séparer l’autorisation plateforme de la capacité d’édition tenant.
- **Public/superapp :** rendu SSR HTML accessible avec metadata, URL stable par Page, actions disponibles selon provider et informations de lieu; erreurs 404 uniformes pour brouillons/suspendus/non attribués. Les Pages vérifiées et publiées alimentent l’annuaire/carte selon opt-in et filtres de visibilité.

Les statistiques détaillées, publicité et suivi d’utilisateur ne font pas partie de la première tranche tant que la politique de confidentialité/consentement n’est pas validée.

## 9. Cartographie, multilingue et RTL

Le MVP doit conserver des coordonnées saisies manuellement et proposer le géocodage comme aide optionnelle derrière une interface (ex. `GeocodingProvider`). Geoapify est la première option à évaluer : sa page tarifaire consultée indique 3 000 crédits/jour et 5 req/s sur l’offre gratuite, usage commercial autorisé sous quotas avec attribution, et un plan affiché à 59 USD/mois pour 10 000 crédits/jour. MapTiler Flex est affiché à 30 USD/mois avec 3 000 sessions de recherche/mois, tandis que sa page réserve le gratuit au prototypage/personnel/non commercial. Éviter le serveur public Nominatim pour l’autocomplete/production générale : politique max. 1 req/s et restrictions strictes. Chiffrer selon volume réel, couverture CEMAC/Maghreb/Turquie et clauses de localisation des données avant contrat; mettre cache, attribution et mécanisme de remplacement. Voir les sources dans l’audit et les [tarifs Geoapify](https://www.geoapify.com/pricing/), [tarifs MapTiler](https://www.maptiler.com/cloud/pricing/) et [policy Nominatim](https://operations.osmfoundation.org/policies/nominatim/).

Garder les textes et valeurs dans la locale canonique, avec traductions tenant par `locale`, état de traduction (complète/partielle) et fallback explicite vers la langue par défaut. Déclarer `lang` et `dir` au SSR, RTL pour l’arabe, mise en page logique CSS (`margin-inline`, `text-align:start`) et tests visuels clavier/mobile. Le registre actuel Showcase `fr/en/ar/tr` est un point de départ, pas une preuve que chaque tenant traduit toutes ses pages.

## 10. Ordre proposé d’implémentation (à approuver)

1. Valider modèle de Site/Pages, règles de publication/vérification, compatibilité slug et décision du domaine de rendu.
2. Stabiliser les contrats Identity/RBAC par établissement et Billing/entitlement; décider stockage médias durable et politique d’adresses/horaire.
3. Ajouter modèle Page relié à `Site`, schémas de blocs provider, validation URL/thème et événements; migrer d’abord en mode shadow sans changer les URL publiques.
4. Ajouter CRUD/éditeur tenant et modération Superadmin, avec intégrité multi-tenant et outbox.
5. Publier la Page SSR dans l’écosystème, brancher canonical/hreflang/sitemaps/robots, les premières actions publiques et les tests de sécurité/rendu.
6. Brancher l’annuaire/carte comme projection des Pages publiées/vérifiées et respecter leur visibilité.
7. Ouvrir les domaines personnalisés premium après validation hébergement/provider, entitlement, DNS/SSL, sécurité Host et rollback.
8. Déprécier le Showcase tenant-par-tenant seulement après migration vérifiée et période de compatibilité observée.

Les numéros correspondent à une séquence recommandée, pas à des issues prêtes à être implémentées.

## 11. Décisions à trancher par le propriétaire produit

1. **Périmètre « page par établissement » :** une seule Page par Site en V1? Peut-elle être non publiée pendant que le site autonome continue de vivre?
2. **Propriété et création :** qui peut créer un établissement, qui est propriétaire légal et qui peut le gérer? Quels rôles par Site et quelles délégations au sein d’un tenant?
3. **Vérification :** un Site non vérifié peut-il publier et être découvert? Quelles méthodes accepter par pays, quelles preuves/documentations, durée de conservation, SLA de revue et procédure de litige?
4. **États :** publication et vérification sont-elles deux axes indépendants? Qui peut suspendre; quels motifs et recours; que voit le public pendant une suspension?
5. **Identifiants publics :** le slug est-il globalement unique sur toute la superapp? Politique de changement, redirections, réservation de slug et suppression/recyclage?
6. **Visibilité des données :** adresse exacte ou zone approximative? Téléphone/email/horaire/GPS sont-ils opt-in séparément? Quelles données sont accessibles sans connexion?
7. **Horaires :** fuseau obligatoire? Plusieurs périodes/jour? Support horaires qui traversent minuit, jours fériés nationaux, fermetures exceptionnelles et « sur rendez-vous »?
8. **Contenu/blocs :** qui approuve les types contribués par les verticaux? Quels blocs core initiaux, limites de longueur/média, liens externes et règles de sécurité/accessibilité?
9. **Langues :** locales obligatoires ou opportunistes; stratégie d’absence de traduction; l’arabe est-il activé dès V1 dans tous les éditeurs et rendus?
10. **Plan gratuit/premium :** quelle part de Pages, médias et analytics est gratuite? Nombre de domaines premium par tenant, période de grâce et rétention à l’expiration?
11. **Hébergement/domaines :** quelle origine rend la Page SSR, quelle source de vérité URL de production et quel fournisseur (Vercel ou Cloudflare for SaaS)? Qui supporte TXT/CNAME/apex, renouvellement, apex proxy et support client?
12. **Migration Showcase :** quelle Page/Site pour tenants existants sans Site ou avec plusieurs Sites? Quelle route/canonical hérite du slug existant et pendant combien de temps l’ancienne API reste compatible?
13. **Géocodage/cartes :** quels volumes et budget, attribution, couverture validée par pays, clauses de confidentialité/résidence et achat/contrat fournisseur?
14. **Actions publiques :** quels BC/API sont prêts à fournir Payer, Écrire, Y aller et Avis? Quelles actions sont prioritaires et quels contrats versionnés sont publiables en V1?
15. **Modération/statistiques :** quelles métriques autorisées, durée de conservation et consentement? Quel processus de suppression d’un Site/Page et de retrait de l’index de Découverte?
16. **Contrôle release :** quelles suites deviennent bloquantes pour les URL host-based, sitemap, pages RTL, média durable et tests inter-tenant avant d’ouvrir la fonctionnalité?
