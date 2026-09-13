# Spécification — Auto-activation des modules par le client & navigation à une seule ligne

- **Statut :** partie A implémentée (branche `feat/7322-module-self-activation`) ; partie B planifiée.
- **BC :** BC-02 TENANT (activation) · BC-01 PLATFORM (navigation de l'espace client).
- **Issues :** #7322 (auto-activation) ; navigation : issue à ouvrir (partie B).
- **Demande propriétaire (2026-09-13)** : « le client doit pouvoir s'auto-activer les modules ; le menu horizontal doit faire un avec la première ligne horizontale, optimisé, pas besoin de deux lignes horizontales ; les trucs liés aux RH doivent être regroupés sous un même menu avec des sous-menus ».

---

## Partie A — Auto-activation des modules (IMPLÉMENTÉE, #7322)

### Constat

Avant ce lot, **aucun endpoint tenant** ne permettait à un responsable d'activer un module : `companies.features` et `metadata.modules` n'étaient écrits que par le provisioning (`ProvisionGuidedTrial`), l'admin plateforme (`PATCH /platform/companies/{id}/features`) et les activateurs de solutions (BC-25). Le panneau « Modules & plan » affichait donc « Demander l'activation » et un module livré/enregistré (ex. `company_showcase`) restait inutilisable pour le client.

### Décision

- **Endpoint tenant** : `POST /api/v1/company/modules/{module}/activate`.
  - RBAC : `CompanyModuleController` exige un manager `principal`/`rh` (miroir de `CompanyBrandingController::update`) — 403 sinon.
  - **Allowlist fail-closed** : seule une clé de `Company::HORIZONTAL_TOOLS` est acceptée ; inconnue ou **verticale** → **422** et **aucune écriture**.
  - Écrit **les deux sources de vérité** : `metadata.modules[key] = true` (sélection client, lue par `client-features.ts`) et, quand il existe, le **flag plateforme miroir** via `Company::HORIZONTAL_TOOL_FEATURES` (`accounting`, `crm`, `showcase` → `company_showcase`).
  - **Idempotent** (déjà actif → 200 `already_active`), **audité** (`module.activated`, une seule entrée par activation réelle).
  - Écriture **qualifiée** `public.companies` (piège `search_path` documenté) et strictement limitée à la société courante.
- **Panneau « Modules & plan »** : pour un outil horizontal verrouillé, le CTA devient un bouton **« Activer »** ; après succès, `/auth/me` est rechargé et la **navigation se met à jour sans reconnexion** (même surface que le rafraîchissement #7245).

### Hors périmètre

- **Verticales** (restaurant, travel, fuel, éducation) : elles requièrent des seeders et des dépendances de pack (BC-25) → restent « sur demande » (admin plateforme).
- **Désactivation** d'un module par le client (risque d'arrêt de service : paie, pointage) — non traité.

### Critères d'acceptation (couverts par `CompanyModuleActivationTest`)

1. `principal` active un outil sans flag plateforme (`employees`) → 200, `metadata.modules.employees = true`, aucun flag inventé.
2. `rh` active `showcase` → `metadata.modules.showcase = true` **et** `features.company_showcase = true`.
3. Clé inconnue → 422, aucune écriture. 4. Verticales → 422, aucune écriture.
5. `comptable` / `employee` → 403, aucune écriture. 6. Réactivation → 200 `already_active`.
7. Côté web : `SELF_ACTIVATABLE_MODULE_KEYS` (miroir de l'allowlist) est verrouillé par test unitaire.

---

## Partie B — Navigation à une seule ligne + RH en sous-menus (PLANIFIÉE)

### Constat

L'en-tête de l'espace client empile **deux lignes horizontales** :
1. la barre `h-16` (marque + « Modules & plan » + notifications + langue + profil) ;
2. un **bandeau « Entreprise »** (`py-2`) sous la barre, listant une pastille par module transverse activé (`Employés`, `Pointages`, `Sessions GPS`, `Absences`, `Contrats`, `Formations`, `Paie`, `Rapports`, `Comptabilité`, `CRM`, `Marketing`, `Site vitrine`…).

Sur un tenant équipé, ce bandeau déborde horizontalement et le RH occupe 6 pastilles.

### Décision proposée

- **Une seule ligne horizontale** : la barre `h-16` absorbe le menu (bandeau supprimé). Le rail vertical « Mon métier » (verticales) est conservé — il n'est pas une ligne horizontale.
- **Regroupement RH** : un menu **« RH »** avec sous-menus —
  `Employés · Pointages · Sessions GPS · Absences · Contrats · Formations`.
- Reste en entrée directe : `Tableau de bord`, `Paie`, `Rapports`, `Comptabilité`, `CRM`, `Marketing`, `Site vitrine` ; les modules de plateforme (`Facturation`, `Intégrations`) restent dans « Modules & plan » (ou un menu « Compte »).
- Comportement responsive : débordement géré par scroll horizontal sur desktop étroit ; sous `md`, le menu devient un tiroir (comme aujourd'hui).
- A11y : `aria-expanded`, fermeture `Escape` / clic extérieur, libellés localisés ×4 (fr/en/ar/tr), RTL.

### État

Partie B **non implémentée** : elle touche le layout principal et mérite sa propre PR (revue isolée, risque UI). Le mapping RH ci-dessus est une proposition à valider par le propriétaire.
