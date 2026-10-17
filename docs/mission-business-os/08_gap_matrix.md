# 08 — Matrice des écarts

**Base :** `main` @ `e9c5f986c` · **Branche de mission :** `mission/leopardo-business-os`
**Classification :** `R` = Réutilisable en l'état · `C` = À compléter · `X` = À corriger ·
`N` = À créer · `V` = À vérifier (capacité affirmée mais non prouvée)
Chaque ligne porte un identifiant repris par les issues GitHub (`17_issues_plan.md`).

---

## 1. Parcours utilisateur

| ID | Capacité | Classe | Constat vérifié | Cible |
|---|---|---|---|---|
| **O-01** | Onboarding universel : marché + sous-profil | `C` | `TRADE_MODES = ['international','domestic']` seulement ; sous-profils (importateur/exportateur/grossiste/détail) absents de `trade-intent.ts` | ajouter la dimension sous-profil, allowlist serveur |
| **O-02** | Transport de l'intention Trade jusqu'au provisioning | `X` | `/signup` ne lit pas `?trade=` ; seul `?sector=` est consommé (`lib/signup-sector.ts`) | persister l'intention et l'envoyer à `CompanyProvisioningService` |
| **O-03** | Établissement principal créé à l'onboarding | `N` | aucune création de `sites` dans `provisionSharedCompany()` ni dans le parcours | créer le site principal dans la transaction |
| **O-04** | Profil professionnel (nom, fonction) collecté | `N` | `setup-interview` ne demande ni fonction ni profil | ajouter au parcours, sans dupliquer `employees` |
| **O-05** | Idempotence prouvée du provisioning | `V` | `ProvisionGuidedTrial` idempotent (#3600) mais **aucun test de course** ; pas de clé d'idempotence cliente sur `/trial/signup` | test de double soumission + clé cliente |
| **O-06** | Reprise d'onboarding interrompu avant OTP | `N` | état serveur uniquement post-tenant (`companies.metadata`) | brouillon serveur ou ADR explicite d'abandon |
| **O-07** | Première opération métier guidée | `C` | checklist par étapes, aucune « première vente/service » générique | étape finale exécutable par verticale |
| **O-08** | « Rejoindre une entreprise » sans doublon de tenant | `C` | `tradeJoinHref` → `/auth/login` ; aucune détection d'appartenance | détection e-mail → proposition de rattachement |
| **O-09** | Modules proposés cohérents avec marché + profil | `C` | liste de solutions statique du `SignupForm` ; pas de pré-cochage par marché | pré-sélection serveur par `market_type` + profil |

## 2. Tenants réellement opérationnels

| ID | Capacité | Classe | Constat vérifié | Cible |
|---|---|---|---|---|
| **T-01** | Données réellement séparées par établissement | `C` | `sites` sans `company_id` indexé, sans type/status/code ; filtrage applicatif seulement ; aucune vue groupe générique | colonnes + index, scope par site, vue consolidée |
| **T-02** | Permissions appliquées sur chaque route métier | `V` | `api.module.grant:<module>` + policies existent ; couverture exhaustive par module non auditée | audit route → permission, test par module |
| **T-03** | Première opération E2E par verticale | `N` | aucun scénario E2E par verticale exécuté en CI | 1 scénario par verticale (12) |
| **T-04** | Rôles métier de verticale installés | `C` | `SolutionPermissionInstaller` existe ; `manager_role` est un rôle RH, pas un rôle métier | installer les rôles de chaque manifest |
| **T-05** | Catalogue produits/services utilisable immédiatement | `R` | par verticale (`restaurant_products`, `fuel_products`, …) | — |
| **T-06** | Tâches opérationnelles | `R` | module `Planning` | — |
| **T-07** | Notifications configurables | `R` | `NotificationPreferenceController` | — |
| **T-08** | Documents | `R` | modules `Documents` / `Showcase` | — |
| **T-09** | Administration tenant unifiée | `C` | écrans dispersés (branding, banque, support, appareils) | point d'entrée unique |
| **T-10** | Rapports consolidés multi-établissements | `N` | rapports par module uniquement | agrégation par tenant |

## 3. Applications et distribution

| ID | Capacité | Classe | Constat vérifié | Cible |
|---|---|---|---|---|
| **A-01** | Version réelle affichée par application | `N` | `software-catalog.ts` ne porte aucune version | version issue du build/channel |
| **A-02** | État de configuration par tenant | `N` | fiche décrit l'app, pas son état chez le tenant | « configurée / appareils enrôlés / à activer » |
| **A-03** | Statut dérivé des canaux réels | `R` | `softwareAvailability()` (#8597) | — |
| **A-04** | Aucun lien fictif | `R` | cibles `soon` sans `href`, règle #6697 | — |
| **A-05** | Canal « build testeurs » opérationnel | `X` | 4 jobs « Distribute … to Firebase » en échec sur `main` | réparer ou requalifier le statut en `not_distributed` |
| **A-06** | Distribution iOS | `N` | aucun canal (pas de signature/TestFlight) | documenter l'absence, ne pas l'annoncer |
| **A-07** | Filtrage par modules activés | `R` | `visibleSoftware(access)` | — |

## 4. Hors-ligne et synchronisation

| ID | Capacité | Classe | Constat vérifié | Cible |
|---|---|---|---|---|
| **F-01** | Déclaration honnête du mode hors-ligne par app | `X` | `SoftwareApp.offline` est un booléen déclaratif, sans preuve | remplacer par `verified`/`implemented`/`absent` |
| **F-02** | Cohérence schéma `sync_queue` tenant ↔ edge | `V` | deux définitions (tenant + SQLite) sans garde automatique | garde de comparaison |
| **F-03** | File d'attente visible avec âge et dernier échec | `C` | compteur + « Réessayer » seulement | ajouter âge, motif, horodatage |
| **F-04** | Idempotence du rejeu prouvée | `V` | clé stable POS/KDS ; aucun test E2E de coupure | index unique + test de rejeu |
| **F-05** | Arbitrage des conflits | `C` | modèle `conflict_resolution` présent, **aucune UI** | écran d'arbitrage + trace |
| **F-06** | Révocation d'appareil effective | `R` | `DELETE /api/v1/edge/{nodeId}` scoped tenant | — (test d'isolation #8496) |
| **F-07** | Diagnostic sync/installation | `N` | rien d'unifié | panneau par tenant |

## 5. Superadmin

| ID | Capacité | Classe | Constat vérifié | Cible |
|---|---|---|---|---|
| **S-01** | Vue du provisionnement (étapes, échecs, blocages) | `N` | aucun écran | tableau + détail par tenant |
| **S-02** | Permissions et journalisation exhaustives | `V` | `platform.permission:*` + `audit_logs` ; couverture non auditée | audit route → permission |
| **S-03** | Catalogue verticales/applications | `N` | `views/solutions` = statistiques de survey | catalogue avec maturité |
| **S-04** | Diagnostic sync par tenant | `N` | `views/edge` global | vue par tenant et par nœud |
| **S-05** | Statut d'intégrations dérivé de la config | `N` | registre spécifique Trade sur branche, absent sur `main` | registre générique read-only |
| **S-06** | Branding global | `C` | tokens globaux, aucun écran | écran de réglage |
| **S-07** | Impersonation bornée et tracée | `R` | `PlatformImpersonationSession` | — |

## 6. Branding et erreurs

| ID | Capacité | Classe | Constat vérifié | Cible |
|---|---|---|---|---|
| **B-01** | Identité sur connexion/onboarding/chargement | `C` | logo global, pas de branding tenant sur ces écrans | appliquer le branding contrôlé |
| **B-02** | Page 403 dédiée | `N` | aucune (message API brut) | page dédiée + demande d'accès |
| **B-03** | Page 500 avec corrélation | `C` | `error.tsx` générique, pas d'identifiant visible | page 500 + ID de corrélation |
| **B-04** | Branding tenant borné | `C` | `CompanyBrandingController` sans contrôle de contraste/format exhaustif | validation serveur |
| **B-05** | `prefers-reduced-motion` respecté | `R` | `GeoBackdrop`, `HeroSection` | — |
| **B-06** | 404 sans fuite technique | `R` | `not-found.tsx` | — |

## 7. Catalogue de solutions

| ID | Capacité | Classe | Constat vérifié | Cible |
|---|---|---|---|---|
| **C-01** | 12 verticales réellement présentes | `R` | 12 manifests sur `main`, tous `pilot` | — |
| **C-02** | Un seul code canonique par verticale | `X` | `restaurant` et `restaurantmanager` coexistent | alias documenté, code canonique unique |
| **C-03** | Maturité honnête exposée partout | `C` | `pilot` dans le manifest, absent de la console et de la vitrine | exposer dans les 3 surfaces |
| **C-04** | Verticale Trade présente sur `main` | `X` | absente (branche `bc/bc-36-trade-os`, PR #8487) | dépendance externe, pas de duplication |
| **C-05** | Verticale Farm présente sur `main` | `X` | absente (branche `bc/bc-37-farm-os`) | idem |

## 8. Tests, CI, gouvernance

| ID | Capacité | Classe | Constat vérifié | Cible |
|---|---|---|---|---|
| **Q-01** | CI `main` verte | `X` | 17 checks en échec sur `main` (`e9c5f986c`) : 4 Firebase, 13 autres | baseline documentée, non-régression mesurée |
| **Q-02** | Tests d'isolation multi-tenant généralisés | `C` | par module, pas de scénario transversal | suite dédiée |
| **Q-03** | Tests d'onboarding complets | `C` | tests unitaires partiels | parcours complet, reprise, idempotence |
| **Q-04** | Scénarios E2E par verticale | `N` | absents | 12 scénarios |
| **Q-05** | Tests hors-ligne de bout en bout | `N` | absents | 5 scénarios OL |
| **Q-06** | Gardes d'architecture | `R` | `architecture-check.yml`, isolation modules, PHPStan niveau 8 | — |

---

## Matrice de compatibilité — verticale × capacité

Légende : `✓` disponible · `~` partiel · `—` non applicable/absent · `?` non vérifié

| Capacité | Resto | Groupe resto | Station | Travel | École | Santé | Pharmacie | Hôtel | Retail | VTC | Delivery | Cagnottes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Tenant + provisioning | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Établissement dédié | ✓ | ✓ | ✓ | ✓ | — | — | — | ✓ | — | — | — | — |
| Données séparées par site | ~ | ~ | ✓ | ✓ | — | — | — | ✓ | — | — | — | — |
| Employés + rôles | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Permissions métier installées | ✓ | ✓ | ✓ | ✓ | ? | ? | ? | ? | ? | ? | ? | ? |
| Produits / stock | ✓ | ✓ | ✓ | ✓ | — | — | ✓ | ✓ | ✓ | — | ✓ | — |
| Tâches | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Vente / encaissement | ✓ | ✓ | ✓ | ✓ | — | — | ✓ | ✓ | ✓ | ✓ | ✓ | — |
| Hors-ligne testé | ~ | ~ | — | — | — | — | — | — | — | — | — | — |
| Application mobile | — | — | — | ✓ | — | — | — | — | — | — | — | — |
| Rapport consolidé groupe | — | — | — | — | — | — | — | — | — | — | — | — |
| E2E prouvé | — | — | — | — | — | — | — | — | — | — | — | — |

---

## Synthèse quantitative

Décompte **recalculé mécaniquement** sur les lignes du tableau (script de contrôle),
pas saisi à la main :

| Classe | Nombre | Part |
|---|---|---|
| Réutilisable (`R`) | 13 | 23 % |
| À compléter (`C`) | 16 | 28 % |
| À corriger (`X`) | 7 | 12 % |
| À créer (`N`) | 16 | 28 % |
| À vérifier (`V`) | 5 | 9 % |
| **Total** | **57** | |

Contrôle de cohérence :

```bash
python3 - <<'PY'
import re, collections
t = open('docs/mission-business-os/08_gap_matrix.md', encoding='utf-8').read()
rows = [l for l in t.split('\n') if re.match(r'^\| \*\*[A-Z]-\d+\*\*', l)]
print(len(rows), collections.Counter(r.split('|')[3].strip().strip('`') for r in rows))
# → 57 Counter({'C': 16, 'N': 16, 'R': 13, 'X': 7, 'V': 5})
PY
```

**Trois écarts bloquants pour un pilote réel :**

1. **O-02/O-03** — le parcours d'onboarding ne porte ni l'intention Trade ni un
   établissement : un tenant peut exister sans lieu d'exploitation.
2. **T-01** — l'établissement n'est pas un axe de données de premier ordre.
3. **F-04** — l'idempotence du rejeu hors-ligne n'est pas prouvée : risque de double vente.
