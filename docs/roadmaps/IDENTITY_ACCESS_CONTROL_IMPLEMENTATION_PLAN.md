# Plan d'implémentation — Identity & Access Control

- **Date** : 31 octobre 2026 · **HEAD de référence** : `31ef78e04`
- **Statut** : proposition — **aucune implémentation ne démarre avant validation du
  propriétaire**
- **Documents liés** : audit, matrice, architecture cible, ADR, contrat d'API public

---

## 0. Règles du plan

1. **Tests de sécurité d'abord.** Chaque lot commence par l'écriture du test qui
   **échoue**, puis l'implémentation qui le fait passer. Un lot sans test écrit en premier
   n'est pas accepté en revue.
2. **Aucune modification de guard ni de middleware.** Les lots s'ajoutent ; si un lot
   semble nécessiter un changement de guard, c'est le lot qui est mal découpé.
3. **Aucune migration destructive, aucune migration de données.**
4. **Relecture du propriétaire obligatoire** sur les lots marqués 🔒 — ce sont ceux qui
   touchent une décision produit ou une frontière de sécurité.
5. **Chaque lot est indépendant** : on peut s'arrêter après n'importe lequel sans laisser
   le système dans un état incohérent.

---

## L1 — Fiabiliser l'OTP (P0) · ~1 j

**Pourquoi** : un code peut n'être délivré **nulle part**, silencieusement. Un stub SMS
retourne un `traceId` sans émettre d'appel (`LogOnlySmsCodeSender.php:119-128`) et fait
sauter l'e-mail (`LoginCodeController.php:87,102`).

### Tests à écrire EN PREMIER

| Test | Assertion |
|---|---|
| `SmsChannelTest::test_incomplete_sms_config_does_not_swallow_the_email` | endpoint seul, ou clé seule, ou driver ≠ `http` ⇒ **l'e-mail part** |
| `SmsChannelTest::test_send_returns_null_when_no_http_call_was_made` | `send()` ne rend un `traceId` que si un appel HTTP a abouti |
| `LoginCodeControllerTest::test_delivery_failure_returns_explicit_error` | envoi impossible ⇒ `503 LOGIN_CODE_SEND_FAILED`, **pas** `200 success` |
| `LoginCodeControllerTest::test_cache_is_cleared_on_delivery_failure` | un nouvel essai produit un **nouveau** code |

### Implémentation

- `isConfigured()` **fail-closed** : exige `SMS_OTP_DRIVER=http` **et** endpoint **et**
  clé **et** transport capable d'émettre.
- `send()` rend `null` si aucun appel n'a abouti → repli e-mail.
- `catch` du contrôleur : réponse d'erreur explicite + log + alerte, et **purge du cache**.
- `HttpSmsTransport` : implémenter réellement le POST que l'ancien code annonçait.

### Relecture 🔒
Décision produit : **accepter ou non** de révéler l'échec de livraison. Cela renonce à une
partie de l'anti-énumération (on révèle qu'un envoi a échoué, pas qu'un compte existe).

### Sortie
La dev ne peut plus « répondre succès » sans qu'un code soit parti.

---

## L2 — Sélection de contexte tenant (P0) · ~3 j · 🔒

**Pourquoi** : une personne dans N entreprises obtient un contexte **arbitraire** (premier
schéma alphabétique). C'est le cœur de la vision « plusieurs relations sans confusion ».

### Tests à écrire EN PREMIER

| Test | Assertion |
|---|---|
| `ContextSelectionTest::test_contexts_lists_every_company_of_the_person` | N entreprises ⇒ N entrées, chacune avec son rôle |
| `ContextSelectionTest::test_employee_cannot_select_a_company_they_do_not_belong_to` | **escalade** : sélection de B par un employé de A ⇒ `403` |
| `ContextSelectionTest::test_issued_token_is_bound_to_the_selected_company` | le token réémis porte `tenant_company:<id>` |
| `ContextSelectionTest::test_selecting_context_does_not_widen_permissions` | permissions ⊆ celles du rôle dans cette entreprise |
| `ContextSelectionTest::test_person_in_two_companies_gets_different_roles` | scénario #5 de la matrice, **enfin couvert** |

> Le 2ᵉ test est **le** test critique : sans lui, l'endpoint devient un moyen de choisir
> son tenant, donc une faille.

### Implémentation

- `GET /api/v1/auth/contexts` — liste, depuis `user_employee_links` si présent, sinon
  balayage des schémas (comportement actuel, conservé en repli).
- `POST /api/v1/auth/context` — vérifie l'appartenance **avant** de réémettre, réutilise
  les abilities Sanctum existantes (`AuthService.php:214-220`), pas de colonne nouvelle.
- Tests sur **deux schémas tenant réels** (le harnais `CreatesMvpSchema` mono-schéma ne
  peut pas les héberger — voir §4.3 de l'audit).

### Relecture 🔒
Deux décisions produit : (a) que faire si la personne n'a **aucun** lien `users` (repli par
e-mail accepté ?) ; (b) le contexte par défaut à la connexion quand N > 1 — premier,
dernier utilisé, ou **demande explicite** (recommandé).

---

## L3 — Sécurité des sessions & du dernier principal (P1) · ~2 j

**Pourquoi** : trois écarts réels, tous prouvés.

| Écart | Preuve |
|---|---|
| 2 chemins de login sans expiration | `AuthController.php:463,552` |
| `UserAuthService::issueToken()` sans expiration | `UserAuthService.php:145` |
| Aucun garde « dernier principal » de tenant | (asymétrie avec `LAST_SUPER_ADMIN_REQUIRED`) |

### Tests à écrire EN PREMIER

| Test | Assertion |
|---|---|
| `SessionExpiryTest::test_every_login_path_issues_a_token_with_expiry` | parcourt **tous** les chemins, aucun token null-expiry |
| `LastPrincipalTest::test_cannot_demote_the_last_active_principal` | `422 LAST_PRINCIPAL_REQUIRED` |
| `LastPrincipalTest::test_suspended_principal_does_not_protect_the_gate` | miroir de `activeSuperAdminCount()` |
| `ImpersonationConsentTest::test_impersonation_notifies_the_impersonated_person` | notification émise + `consent_notified_at` renseigné |

### Implémentation

- `createToken($name, $abilities, $expiresAt)` sur les 3 chemins manquants.
- `activePrincipalCount()` calqué sur `PlatformTeamController::activeSuperAdminCount()`
  (`:181-190`) — **copier le mécanisme existant, ne pas en inventer un**.
- `consent_notified_at` + `consent_reason_code` sur `platform_impersonation_sessions`.

---

## L4 — Convergence du grand public (P0) · ~5 j · 🔒

**Pourquoi** : le public visé est mobile-first et ne peut se connecter que par e-mail. La
3ᵉ identité `travel_customer_accounts` vit hors du graphe `users`.

### Tests à écrire EN PREMIER

| Test | Assertion |
|---|---|
| `PublicIdentityResolutionTest::test_same_phone_resolves_to_the_same_user` | idempotence |
| `PublicIdentityResolutionTest::test_existing_user_is_reused_not_duplicated` | pas de doublon |
| `PublicIdentityResolutionTest::test_two_users_are_never_auto_merged` | garde anti-prise-de-contrôle |
| `PhoneOtpLoginTest::test_unknown_phone_returns_204_and_sends_nothing` | anti-énumération |
| `PhoneOtpLoginTest::test_code_is_hashed_in_cache` | jamais de code en clair |
| `PhoneOtpRateLimitTest::test_three_codes_per_phone_per_fifteen_minutes` | coût SMS |
| `AccountDeletionTest::test_deletion_requires_reauthentication` | un token volé ne supprime pas |
| `AccountDeletionTest::test_deletion_is_reversible_for_thirty_days` | récupération |

### Implémentation

- `travel_customer_accounts` : + `phone_e164` (unique partiel, même discipline que #8736),
  + `user_id` (FK nullable).
- `resolveUserForPublicAccount()` — algorithme de l'ADR §6, **sans fusion automatique**.
- OTP par téléphone sous `user_api` (contrat §2.3).
- Rate limiting SMS (3/15 min par numéro, 10/h par IP).
- `DELETE /user/account` différé 30 j.

### Relecture 🔒
Trois décisions produit : (a) réponse à la réattribution de numéro (ADR O1) ; (b) conserver
ou non `travel_customer_accounts` comme profil (ADR O3) ; (c) politique de rétention avant
anonymisation.

---

## L5 — Rôles personnalisés (P2) · ~4 j · 🔒

**Pourquoi** : un admin de tenant ne peut pas créer « Caissier ». Limite produit, pas
faille — d'où la priorité basse.

### Tests à écrire EN PREMIER

| Test | Assertion |
|---|---|
| `RoleAssignmentTest::test_custom_role_cannot_exceed_its_creator_permissions` | **scénario #11**, aujourd'hui sans objet |
| `RoleAssignmentTest::test_revoking_a_custom_role_removes_access_immediately` | scénario #6 |
| `RoleAssignmentTest::test_role_without_module_cannot_call_its_endpoints` | scénario #12, dans le contexte custom |
| `RoleAssignmentTest::test_principal_can_still_delegate_below_their_own_level` | moindre privilège |

### Implémentation

Table `role_assignments` (§3.2 de l'architecture cible), lue **après** les enums.
`effectivePermissions = enumPermissions ∪ roleAssignments(active)`.

**Ne pas** introduire Spatie : imposerait de réécrire les gates existants.

---

## L6 — Hygiène transverse (P2, continu)

| Sujet | Action | Priorité |
|---|---|---|
| Tokens machines | Registre (portée, rotation, révocation, journal) — **absent** aujourd'hui hors paiement | P2 |
| Desktop OAuth2 + PKCE | Aucune surface ; à concevoir | P2 |
| Consentement / export / suppression RGPD | `DELETE /account` couvre la suppression ; export et durée de rétention des journaux à définir | P1 (RGPD) |
| Dette de test | 4 tests utilisent des mots de passe refusés par la politique (`password123`) | P2 — **rapide, à faire au passage** |

---

## Séquencement

```
L1 (OTP fiable)  ──►  L2 (contexte)  ──►  L4 (grand public)
                            │
L3 (sessions) ──────────────┘
                            └──────────►  L5 (rôles custom) ──► L6
```

- **L1 avant tout** : sans envoi fiable, L4 est un mensonge.
- **L2 avant L4** : le grand public multi-entreprises a besoin du contexte.
- **L3 en parallèle** : indépendant, faible risque.
- **L5 après L2/L3** : dépend de la notion de contexte pour être testable.

**Total P0+P1 ≈ 11 jours** (L1 1 j + L2 3 j + L3 2 j + L4 5 j).

---

## Ce que ce plan refuse de faire

| Refus | Raison |
|---|---|
| Migrer `travel_customer_accounts` d'un coup | Migration de données interdite ; casse le contrat de la marketplace |
| Fusionner `super_admins` dans `users` | Population interne, guards disjoints, aucune confusion — gain cosmétique |
| Remplacer les enums par Spatie | Réécriture des gates existants = risque de régression de sécurité |
| Ajouter un service d'autorisation externe | Un aller-retour réseau par requête, sans gain |
| Toucher aux paiements | Hors périmètre (lecture seule) |
| Modifier les guards ou middlewares | Contrainte explicite du propriétaire |

---

## Conditions de sortie

Un lot est **terminé** quand :

1. ses tests de sécurité ont été écrits **avant** le code et **passent réellement**
   (exécution tracée, pas « considérés comme passants ») ;
2. aucun test existant n'a régressé (suite `Auth` + `Tenant` verte) ;
3. la matrice de tests de sécurité (§4 de l'audit) est mise à jour avec le **résultat
   réel** ;
4. le propriétaire a relu les lots 🔒.
