# 09 — ADR (Architecture Decision Records)

Chaque décision est datée, motivée, et liste les alternatives **écartées**. Une ADR qui
n'a pas d'alternative sérieuse n'est pas une décision, c'est une évidence — ces cas ne
figurent pas ici.

---

## ADR-01 — Ne pas créer d'application par verticale

**Date :** 2026-10-17 · **Statut :** acceptée

**Contexte.** 12 verticales sont présentes ; chacune a des écrans différents (POS resto,
pompe, sièges d'autocar, dossiers élèves). La tentation est de livrer 12 applications
autonomes.

**Décision.** Une verticale = un `SolutionManifest` (déclaration) + un module métier
(`api/app/Modules/*`) + des écrans partagés filtrés par module activé. Aucune application
séparée, aucun second backend.

**Justification.** Le `SolutionCatalogue` + `SolutionActivator` existent et sont allowlistés ;
un second backend dupliquerait l'auth, le multi-tenant, la facturation et l'audit —
quatre surfaces où une divergence devient une faille.

**Alternatives écartées.**
- *Un déploiement par verticale* : 12 bases à migrer, 12 CI, 12 jeux de permissions.
- *Micro-frontends par verticale* : aucun besoin de déploiement indépendant constaté.

**Conséquences.** Les écrans spécifiques vivent sous `front/web/src/app/(dashboard)/<verticale>/`
et partagent les tokens (`05_ui_design_system.md`). Toute divergence de permission doit être
traitée dans le manifest, pas dans l'écran.

---

## ADR-02 — Ne pas fusionner `bc/bc-36-trade-os` dans la branche de mission

**Date :** 2026-10-17 · **Statut :** acceptée

**Contexte.** La PR #8487 apporte `Modules/Trade`, le registre d'intégrations et les
rôles Trade (207 fichiers). La mission doit traiter l'onboarding Trade.

**Décision.** La mission **référence** ce travail comme dépendance et ne le réimplémente
pas. L'onboarding est conçu pour fonctionner avec ou sans le module Trade présent, derrière
un `SolutionManifest` dont l'absence ne casse rien.

**Justification.** Fusionner 207 fichiers non revus dans une branche documentaire rendrait
la revue impossible et mélangerait deux périmètres. Le contrat d'interface (code de solution,
profil de marché, allowlist) est stable indépendamment du module.

**Alternatives écartées.**
- *Fusionner puis documenter* : la PR de mission deviendrait non relisible.
- *Réimplémenter Trade sur cette branche* : duplication certaine, conflits garantis.

**Conséquences.** La colonne `companies.market_type` et l'allowlist sont livrables
immédiatement ; les écrans Trade restent sur l'autre branche jusqu'à sa fusion.

---

## ADR-03 — L'onboarding universel étend l'orchestrateur existant

**Date :** 2026-10-17 · **Statut :** acceptée

**Contexte.** `CompanyProvisioningService::provisionSharedCompany()` et
`ProvisionGuidedTrial` sont transactionnels et idempotents (#3600). Le parcours demandé est
plus riche (8 étapes).

**Décision.** Le parcours devient une **couche de collecte** qui alimente l'orchestrateur
existant. Aucun second chemin de création de tenant.

**Justification.** Un second chemin crée une seconde vérité sur « ce qu'est un tenant
valide » ; les doublons constatés dans les systèmes de ce type viennent toujours de là.

**Alternatives écartées.**
- *Nouveau service dédié à l'onboarding universel* : double écriture, double validation.
- *Provisioning côté front* : interdit (autorité et allowlist côté serveur).

**Conséquences.** Chaque étape doit être exprimable comme un champ du payload de
provisioning ou une écriture post-provisioning idempotente (clé `step_key`).

---

## ADR-04 — Un seul code canonique par verticale

**Date :** 2026-10-17 · **Statut :** acceptée

**Contexte.** `restaurant` et `restaurantmanager` coexistent ; la cascade est documentée
dans `RestaurantManifest` mais reste une source de confusion (activation, catalogue,
statistiques).

**Décision.** Un code canonique par verticale, déclaré dans le manifest. Les alias
historiques sont conservés **en lecture** (résolution à l'activation) et **jamais** exposés
au client.

**Justification.** Deux codes pour une offre produisent des statistiques fausses et des
activations partielles.

**Alternatives écartées.**
- *Supprimer l'alias immédiatement* : casserait les tenants déjà activés.
- *Statuer sur un code sans alias* : migration de données non justifiée par le volume.

**Conséquences.** Le catalogue expose un code ; l'alias est résolu côté
`SolutionCatalogue::resolve()`. Un test vérifie que chaque alias pointe vers un code existant.

---

## ADR-05 — Reprise d'onboarding : état serveur, pas navigateur

**Date :** 2026-10-17 · **Statut :** acceptée (avec réserve)

**Contexte.** Le parcours demandé commence **avant** l'existence du tenant (avant l'OTP).
Il n'y a donc pas encore de `company_id` où persister.

**Décision.** Deux niveaux :
- **Après création du tenant** : `companies.onboarding_state` (jsonb), source de vérité,
  reprise garantie.
- **Avant création du tenant** : aucune persistance serveur dans ce lot. Le formulaire reste
  en mémoire ; un abandon avant OTP redemande la saisie.

**Justification.** Créer une table de brouillons pré-tenant ouvre une surface de données
personnelles non rattachées, avec rétention et purge à définir. Le gain (reprise d'un
formulaire de 2 minutes) ne justifie pas ce coût **aujourd'hui**.

**Alternatives écartées.**
- *Table `onboarding_drafts` pré-tenant* : retenue comme option si la mesure montre un
  abandon significatif avant OTP (à instrumenter d'abord).
- *`localStorage` comme source de vérité* : rejeté — une donnée d'entreprise ne doit jamais
  dépendre d'un navigateur.

**Conséquences.** L'exigence ONB-06 est satisfaite **partiellement** et le dit
explicitement. Toute instrumentation d'abandon avant OTP devra précéder une éventuelle
table de brouillons.

---

## ADR-06 — Statut d'application dérivé, jamais saisi

**Date :** 2026-10-17 · **Statut :** acceptée

**Contexte.** Le centre « Mes logiciels » affiche un statut par application. Un statut saisi
à la main dérive toujours vers la promesse commerciale.

**Décision.** `softwareAvailability()` dérive `published` / `pilot` / `not_distributed` des
cibles de distribution réelles. Aucune application dont le canal échoue ne peut être
`published`.

**Justification.** Règle #6697 déjà en vigueur ; l'étendre au hors-ligne (`verified` /
`implemented` / `absent`) évite la même dérive sur une promesse plus coûteuse.

**Alternatives écartées.**
- *Champ `status` éditable en admin* : mensonge structurel, rejeté.

**Conséquences.** Réparer les jobs Firebase change le statut **sans** toucher au catalogue ;
c'est le comportement recherché.

---

## ADR-07 — Pas de nouvelle table pour le centre « Mes logiciels »

**Date :** 2026-10-17 · **Statut :** acceptée

**Contexte.** On pourrait stocker les fiches d'application en base pour les éditer sans
déploiement.

**Décision.** Le catalogue reste **statique** côté front (`software-catalog.ts`), croisé
avec l'état réel du tenant (`EmployeeModuleGrant`, `edge_nodes`).

**Justification.** Ces fiches décrivent le produit, pas le client. Les mettre en base
introduirait une seconde source de vérité face au code des applications.

**Alternatives écartées.**
- *Table `software_apps` + éditeur admin* : duplication, dérive silencieuse.

**Conséquences.** Une nouvelle application exige un déploiement front — acceptable, le
rythme de livraison des apps est lent.

---

## ADR-08 — Le hors-ligne se déclare par test, pas par intention

**Date :** 2026-10-17 · **Statut :** acceptée

**Contexte.** `SoftwareApp.offline` est un booléen déclaratif ; aucune preuve de
fonctionnement en coupure réelle.

**Décision.** Trois états : `verified` (scénario CI rejouable cité), `implemented` (code
présent, non prouvé E2E), `absent`. Un booléen `true` sans référence de test n'est plus
accepté.

**Justification.** Une perte de vente terrain est un incident financier, pas un défaut
d'UI. La preuve doit précéder la promesse.

**Alternatives écartées.**
- *Conserver le booléen et ajouter une note* : la note sera ignorée.

**Conséquences.** Plusieurs applications passeront de « hors-ligne : oui » à
« `implemented` » — c'est un recul de promesse assumé et documenté.

---

## ADR-09 — Garde de cohérence du schéma `sync_queue`

**Date :** 2026-10-17 · **Statut :** proposée

**Contexte.** `sync_queue` est définie deux fois (schéma tenant + base edge SQLite) sans
vérification d'alignement.

**Décision.** Ajouter une garde CI comparant les colonnes des deux définitions (nom, type,
nullabilité), sur le modèle des gardes de migrations existantes.

**Justification.** Une divergence silencieuse produit des rejeux rejetés côté serveur, donc
des pertes de données terrain silencieuses.

**Alternatives écartées.**
- *Une seule définition générée* : les deux moteurs (PostgreSQL / SQLite) ont des types
  incompatibles ; la génération masquerait les différences légitimes.

**Conséquences.** La garde doit tolérer explicitement les différences de type documentées.
