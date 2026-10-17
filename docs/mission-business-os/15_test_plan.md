# 15 — Plan de test

**Principe :** un test ne prouve rien s'il ne peut pas échouer. Chaque scénario ci-dessous
énonce l'**état initial**, l'**action**, et le **résultat observable attendu** — sans quoi il
n'est pas exécutable par un tiers.

Statut au démarrage : `existant` (couvert aujourd'hui) · `à écrire` (aucun test).

---

## 1. Onboarding — parcours et idempotence

| ID | Scénario | Attendu | Statut |
|---|---|---|---|
| **ONB-T1** | Inscription complète depuis `?trade=international&profile=importer` | tenant créé, `market_type='international'`, `trade_profile='importer'`, 1 site principal | `à écrire` |
| **ONB-T2** | Même inscription avec `?trade=autre` | **422 avant toute écriture**, aucun tenant créé | `à écrire` |
| **ONB-T3** | Deux `POST /trial/signup` avec la même `Idempotency-Key` | **un seul** tenant, 2 réponses 2xx identiques | `à écrire` |
| **ONB-T4** | Deux soumissions concurrentes du même e-mail | un seul tenant (contrainte unique + `23505` géré), aucune 500 | `à écrire` |
| **ONB-T5** | Reprise après abandon à l'étape 4 | l'API renvoie l'étape 4, le front s'ouvre dessus | `à écrire` |
| **ONB-T6** | Étape déjà validée rejouée | aucune duplication (`step_key` unique), horodatage inchangé | `existant` partiel (`SeedDefaultSteps` #4188) |
| **ONB-T7** | Établissement principal manquant (payload sans site) | 422 explicite, pas de tenant partiel | `à écrire` |
| **ONB-T8** | Parcours complet FR/EN/AR/TR | aucune chaîne manquante (`validate.js`), même nombre d'étapes | `existant` (parité i18n) |
| **ONB-T9** | Utilisateur déjà rattaché à un tenant tente « créer » | avertissement + proposition de rattachement, aucun second tenant | `à écrire` |
| **ONB-T10** | Modules pré-cochés par marché+profil | domestique/grossiste → modules retail ; international/importateur → modules Trade | `à écrire` |

## 2. Isolation multi-tenant et sécurité backend

| ID | Scénario | Attendu | Statut |
|---|---|---|---|
| **SEC-T1** | `GET /sites` avec le token du tenant B | uniquement les sites de B | `existant` partiel |
| **SEC-T2** | `GET /sites/{id}` d'un site du tenant A avec le token B | **404** (jamais 403 révélant l'existence) | `à écrire` |
| **SEC-T3** | `DELETE /api/v1/edge/{nodeId}` d'un nœud d'un autre tenant | 404 | `existant` (#8499) |
| **SEC-T4** | Accès à une route module sans `EmployeeModuleGrant` | 403 | `existant` (`api.module.grant`) |
| **SEC-T5** | Route métier sans permission de rôle | 403 | `existant` partiel (couverture à auditer, gap T-02) |
| **SEC-T6** | Superadmin sans `platform.permission:*` sur une action | 403 + entrée d'audit | `à écrire` |
| **SEC-T7** | Impersonation | session tracée, bandeau visible, sortie explicite | `existant` |
| **SEC-T8** | Cross-tenant sur une ressource de verticale (vente fuel, billet travel) | 404 | `existant` (`FuelSalePolicy`, `TravelTicketPolicy`) |

## 3. Applications et distribution

| ID | Scénario | Attendu | Statut |
|---|---|---|---|
| **APP-T1** | Tenant avec module `restaurant` | seules les apps restaurant (+ communes) sont visibles | `existant` (#8597) |
| **APP-T2** | App sans cible `link` | badge `not_distributed`, **aucun** `href` | `existant` |
| **APP-T3** | Distribution Firebase en échec | l'app ne peut pas être `published` | `à écrire` (gap A-05) |
| **APP-T4** | Version inconnue | aucune version affichée (pas de `undefined`, pas de `0.0.0`) | `à écrire` |
| **APP-T5** | Fiche dépliable au clavier | `aria-expanded` bascule, contenu atteignable au Tab | `à écrire` |

## 4. Hors-ligne et synchronisation

| ID | Scénario | Attendu | Statut |
|---|---|---|---|
| **OL-1** | Coupure réseau pendant une vente POS | vente mise en file, bandeau `Hors ligne — 1 en attente`, aucune perte | `à écrire` |
| **OL-2** | Reconnexion après 3 ventes en file | 3 ventes envoyées, **aucune** duplication (même `idempotency_key`) | `à écrire` |
| **OL-3** | Rejeu du même message 5 fois | 1 seule vente en base | `à écrire` |
| **OL-4** | Conflit (stock modifié côté cloud) | statut `conflict`, trace dans `sync_logs`, résolution tracée | `à écrire` |
| **OL-5** | Révocation d'un appareil puis tentative de sync | refus, message clair, aucune donnée acceptée | `existant` partiel |
| **OL-6** | Service worker : `/api/*` en coupure | réseau prioritaire puis repli, jamais de réponse vide | `existant` (`sw-strategies.test.mjs`) |
| **OL-7** | Schéma `sync_queue` tenant vs edge | colonnes alignées (garde CI) | `à écrire` (gap F-02) |

## 5. Parcours verticale de bout en bout

Format commun : inscription → provisioning → configuration → données → **première opération
réussie** → vérification en base.

| ID | Verticale | Première opération attendue | Statut |
|---|---|---|---|
| **V-RESTO** | Restaurant | produit créé + vente POS encaissée + ticket | `à écrire` |
| **V-GROUPE** | Groupe de restaurants | 2 branches, vente sur la branche 2 invisible sur la 1 | `à écrire` |
| **V-FUEL** | Station-service | vente avec `payment_method`, cuve décrémentée, clôture | `existant` partiel (13 échecs préexistants) |
| **V-TRAVEL** | Agence de voyage | trajet créé, siège réservé, billet QR vérifié | `existant` (100 échecs préexistants sur `main`) |
| **V-ECOLE** | École | élève créé, présence enregistrée | `à écrire` |
| **V-SANTE** | Santé | patient + rendez-vous | `à écrire` |
| **V-PHARMA** | Pharmacie | stock + vente | `à écrire` |
| **V-HOTEL** | Hôtellerie | réservation + check-in | `à écrire` |
| **V-RETAIL** | Retail | produit + vente caisse | `à écrire` |
| **V-VTC** | VTC | course attribuée + clôturée | `à écrire` |
| **V-DELIV** | Delivery | commande livrée | `à écrire` |
| **V-CAGNOTTE** | Cagnottes | cagnotte créée + contribution | `à écrire` |

## 6. Branding et pages d'erreur

| ID | Scénario | Attendu | Statut |
|---|---|---|---|
| **BRD-T1** | Rôle insuffisant sur une page | page 403 dédiée, action « demander l'accès » | `à écrire` |
| **BRD-T2** | Erreur serveur forcée | page 500 + identifiant de corrélation, **aucune** trace | `à écrire` |
| **BRD-T3** | Logo tenant contraste insuffisant | 422 au `PATCH /company/branding` | `à écrire` |
| **BRD-T4** | `prefers-reduced-motion` activé | aucune transition d'étape, état final immédiat | `à écrire` |
| **BRD-T5** | Route inconnue | 404 sans nom de fichier ni version | `existant` |
| **BRD-T6** | Assets de marque | `check-brand-assets.mjs` vert (5 assets, pas de damier) | `existant` |

---

## 7. Jeux de données de test

| Jeu | Contenu | Usage |
|---|---|---|
| `tenant_minimal` | 1 entreprise, 1 manager, 0 site | prouver qu'un tenant sans site est **détecté** |
| `tenant_resto_single` | 1 branche, 3 produits, 2 employés | V-RESTO |
| `tenant_resto_group` | 2 branches, données séparées | V-GROUPE |
| `tenant_fuel` | 1 site, 2 cuves, 2 produits | V-FUEL |
| `tenant_trade_intl` | marché international, profil importateur | ONB-T1 |

Chaque jeu est créé par une factory existante ou un seeder ; **aucune** donnée de production.

---

## 8. Ce que ce plan ne prétend pas couvrir

- La charge (au-delà du `k6-load-smoke.yml` existant).
- La compatibilité matérielle réelle (imprimantes thermiques, lecteurs biométriques,
  pompes) — aucune ne peut être testée en CI.
- Les performances perçues sur réseau mobile dégradé, hors scénarios OL.
