# Requirements Document - MVP i18n Mobile Pragmatique

## Introduction

Ce document définit les exigences pour finaliser l'implémentation i18n mobile Flutter de Leopardo RH selon une approche **pragmatique business-first** validée par le CTO. L'objectif est d'avoir une application mobile multilingue **crédible pour démos clients en 4-5 jours**, sans over-engineering, avec focus sur les 5 écrans qui génèrent 80% de la valeur business.

### Contexte

**État actuel (2026-05-07):**
- ✅ Configuration Flutter l10n complète (`l10n.yaml`)
- ✅ 4 fichiers `.arb` avec 130 clés traduites (FR, AR, TR, EN = 520 traductions)
- ✅ Extension helper `lib/core/l10n/l10n_extensions.dart`
- ✅ Support RTL arabe
- ✅ Détection automatique langue device
- ✅ Synchronisation avec `employee.language` backend
- ✅ Script validation `scripts/validate_i18n.dart`
- ✅ Workflow GitHub Actions `mobile-i18n.yml`
- ✅ Guide migration `MIGRATION_I18N.md`
- ✅ 1/15 écrans migrés (Settings)

**Objectif business:**
Débloquer l'expansion internationale (Arabie Saoudite, Turquie, Maghreb) avec une app mobile multilingue crédible pour les 10 premiers clients payants.

**Durée cible:** 4-5 jours (pragmatisme business avant perfection technique)

---

## Glossary

- **Translation_System**: Le système Flutter l10n qui gère les traductions multilingues
- **ARB_File**: Application Resource Bundle - fichier JSON contenant les traductions
- **Fallback_Chain**: Mécanisme de repli AR → EN → FR → clé hardcodée
- **Glossary_Lock**: Fichier `glossary.json` avec termes RH verrouillés (`locked: true`)
- **Source_Of_Truth**: Répertoire `/shared/i18n/` contenant les traductions centralisées
- **MVP_Screens**: Les 5 écrans prioritaires (Auth, Home, Attendance, Absences, Payrolls)
- **RTL_Mode**: Right-to-Left mode pour l'affichage arabe
- **Sync_Script**: Script local de synchronisation des traductions (pas d'API)
- **Analytics_Event**: Événement business trackant l'usage multilingue
- **Hierarchy_Level**: Niveau de profondeur dans la structure JSON (max 3)
- **Remote_Update**: Mise à jour des traductions sans release app (sync background)
- **AI_Translation**: Pipeline de traduction automatique avec validation glossary

---

## Requirements

### Requirement 1: Architecture Source of Truth

**User Story:** En tant que développeur, je veux une source de vérité centralisée pour les traductions, afin d'éviter les incohérences entre backend, web et mobile.

#### Acceptance Criteria

1. THE Translation_System SHALL utiliser `/shared/i18n/` comme source de vérité unique
2. THE Translation_System SHALL maintenir un Glossary_Lock avec termes RH verrouillés
3. WHEN une clé de traduction est ajoutée, THE Translation_System SHALL vérifier qu'elle existe dans les 4 langues (FR, AR, TR, EN)
4. THE Translation_System SHALL utiliser des Sync_Scripts locaux (pas d'API de traduction immédiate)
5. THE Translation_System SHALL limiter la hiérarchie JSON à 3 niveaux maximum (exemple: `attendance.check_in.button` ✅, `modules.attendance.actions.checkin.buttons.primary.label` ❌)

**Correctness Properties:**
- **Invariant**: Pour toute clé K dans `app_fr.arb`, K existe dans `app_ar.arb`, `app_tr.arb`, `app_en.arb`
- **Hierarchy Constraint**: Pour toute clé K, `depth(K) <= 3` où `depth("a.b.c") = 3`
- **Glossary Lock**: Pour tout terme T dans `glossary.json` avec `locked: true`, T ne peut être modifié sans validation manuelle

---

### Requirement 2: Fallback Intelligent Obligatoire

**User Story:** En tant qu'utilisateur, je veux que l'application affiche toujours un texte compréhensible, même si une traduction est manquante.

#### Acceptance Criteria

1. WHEN une clé de traduction est manquante en arabe, THE Translation_System SHALL utiliser la version anglaise
2. WHEN une clé de traduction est manquante en anglais, THE Translation_System SHALL utiliser la version française
3. WHEN une clé de traduction est manquante en français, THE Translation_System SHALL afficher la clé hardcodée (exemple: `settings.title`)
4. THE Translation_System SHALL logger chaque fallback avec niveau WARNING
5. THE Translation_System SHALL exposer un rapport de fallbacks via Analytics_Event

**Correctness Properties:**
- **Fallback Chain**: `resolve(key, "ar") = arb_ar[key] ?? arb_en[key] ?? arb_fr[key] ?? key`
- **No Empty String**: Pour toute clé K et langue L, `resolve(K, L) != ""`
- **Logging Completeness**: Pour tout fallback F, un log WARNING existe avec `{key, requested_lang, fallback_lang}`

---

### Requirement 3: Migration MVP 5 Écrans (80% Valeur Business)

**User Story:** En tant que product owner, je veux migrer les 5 écrans qui génèrent 80% de la valeur business, afin de maximiser le ROI en 4-5 jours.

#### Acceptance Criteria

1. THE Translation_System SHALL migrer les écrans Auth (login, register, welcome)
2. THE Translation_System SHALL migrer l'écran Home
3. THE Translation_System SHALL migrer les écrans Attendance (check-in/out, history)
4. THE Translation_System SHALL migrer les écrans Absences (list, request)
5. THE Translation_System SHALL migrer les écrans Payrolls (list, detail)
6. WHEN un écran MVP est migré, THE Translation_System SHALL remplacer 100% des textes hardcodés par `context.l10n.key`
7. THE Translation_System SHALL ajouter les clés manquantes dans les 4 fichiers ARB_File

**Correctness Properties:**
- **Migration Completeness**: Pour tout écran S dans MVP_Screens, `hardcoded_strings(S) = 0`
- **Key Coverage**: Pour tout écran S dans MVP_Screens, toutes les clés utilisées existent dans les 4 ARB_Files
- **RTL Validation**: Pour tout écran S dans MVP_Screens, l'affichage en arabe respecte RTL_Mode

---

### Requirement 4: Analytics Business Obligatoires

**User Story:** En tant que product manager, je veux tracker l'usage multilingue, afin de mesurer l'adoption et identifier les problèmes.

#### Acceptance Criteria

1. WHEN un utilisateur sélectionne une langue, THE Translation_System SHALL envoyer un Analytics_Event `language_selected` avec `{user_id, old_lang, new_lang, timestamp}`
2. THE Translation_System SHALL tracker la rétention par locale avec Analytics_Event `locale_retention` (nombre de sessions par langue)
3. THE Translation_System SHALL tracker l'usage par écran et locale avec Analytics_Event `screen_locale_usage` (exemple: `attendance_screen` en arabe)
4. WHEN un utilisateur utilise l'arabe, THE Translation_System SHALL tracker la durée de session RTL avec Analytics_Event `rtl_session_duration`
5. THE Translation_System SHALL exposer un dashboard analytics avec métriques clés (langue préférée, taux de fallback, durée session RTL)

**Correctness Properties:**
- **Event Completeness**: Pour toute sélection de langue L, un événement `language_selected` existe avec timestamp T
- **Retention Tracking**: Pour tout utilisateur U et langue L, le nombre de sessions est comptabilisé dans `locale_retention`
- **Screen Coverage**: Pour tout écran S dans MVP_Screens et langue L, l'usage est tracké dans `screen_locale_usage`

---

### Requirement 5: Emails Multilingues Backend

**User Story:** En tant qu'utilisateur, je veux recevoir les emails dans ma langue préférée, afin d'améliorer l'expérience utilisateur.

#### Acceptance Criteria

1. WHEN un email est envoyé, THE Translation_System SHALL utiliser `employee.language` pour déterminer la langue
2. THE Translation_System SHALL utiliser les templates Laravel `api/lang/{locale}/emails.php`
3. WHEN la langue de l'utilisateur n'est pas supportée, THE Translation_System SHALL utiliser le Fallback_Chain (AR → EN → FR)
4. THE Translation_System SHALL traduire les emails suivants: welcome, invitation, password_reset, absence_approved, payroll_ready
5. THE Translation_System SHALL inclure le support RTL dans les templates HTML pour l'arabe

**Correctness Properties:**
- **Email Language Match**: Pour tout email E envoyé à utilisateur U, `email_lang(E) = employee.language(U) ?? fallback_chain()`
- **Template Completeness**: Pour tout type d'email T et langue L, un template existe dans `api/lang/{L}/emails.php`
- **RTL Email**: Pour tout email E en arabe, le template HTML inclut `dir="rtl"`

---

### Requirement 6: QA Minimale (Validation Clés + Placeholders)

**User Story:** En tant que développeur, je veux une validation automatique des traductions, afin d'éviter les erreurs en production.

#### Acceptance Criteria

1. THE Translation_System SHALL valider que toutes les clés existent dans les 4 langues
2. THE Translation_System SHALL valider que les placeholders sont cohérents (exemple: `{name}` présent dans toutes les langues)
3. WHEN une validation échoue, THE Translation_System SHALL bloquer le build CI/CD
4. THE Translation_System SHALL générer un rapport de validation avec clés manquantes et placeholders incohérents
5. THE Translation_System SHALL valider que la hiérarchie JSON ne dépasse pas 3 niveaux

**Correctness Properties:**
- **Key Parity**: Pour toute langue L1 et L2, `keys(arb_L1) = keys(arb_L2)`
- **Placeholder Consistency**: Pour toute clé K avec placeholders P, `placeholders(arb_fr[K]) = placeholders(arb_ar[K]) = placeholders(arb_tr[K]) = placeholders(arb_en[K])`
- **Hierarchy Validation**: Pour toute clé K, `depth(K) <= 3`
- **CI Gate**: Si validation échoue, alors `build_status = FAILED`

---

### Requirement 7: RTL Validation Arabe

**User Story:** En tant qu'utilisateur arabophone, je veux que l'interface s'affiche correctement en mode RTL, afin d'avoir une expérience utilisateur naturelle.

#### Acceptance Criteria

1. WHEN la langue arabe est sélectionnée, THE Translation_System SHALL appliquer `textDirection: TextDirection.rtl`
2. THE Translation_System SHALL inverser les icônes et layouts (exemple: flèche retour à droite)
3. THE Translation_System SHALL aligner le texte à droite
4. THE Translation_System SHALL tester le RTL sur les 5 écrans MVP
5. WHEN un écran contient des nombres ou dates, THE Translation_System SHALL les formater selon la locale arabe

**Correctness Properties:**
- **RTL Activation**: Si `employee.language = "ar"`, alors `textDirection = RTL`
- **Icon Mirroring**: Pour tout écran S en arabe, les icônes directionnelles sont inversées
- **Text Alignment**: Pour tout texte T en arabe, `textAlign = TextAlign.right`
- **MVP Coverage**: Pour tout écran S dans MVP_Screens, le RTL est validé manuellement

---

### Requirement 8: Stratégie IA à Prévoir (Post-PMF)

**User Story:** En tant que product owner, je veux préparer une stratégie de traduction IA, afin de scaler rapidement vers de nouvelles langues après le PMF.

#### Acceptance Criteria

1. THE Translation_System SHALL documenter une stratégie AI_Translation avec GPT contextualisé
2. THE Translation_System SHALL définir un prompt RH spécialisé pour les traductions (contexte: logiciel RH, termes métier)
3. THE Translation_System SHALL valider les traductions IA contre le Glossary_Lock
4. THE Translation_System SHALL marquer les traductions IA avec flag `needs_human_review: true`
5. THE Translation_System SHALL estimer le coût de traduction IA (exemple: ~0,01$ par clé avec GPT-4)

**Correctness Properties:**
- **Glossary Validation**: Pour toute traduction IA T contenant un terme G du Glossary_Lock, T respecte la traduction verrouillée de G
- **Review Flag**: Pour toute traduction IA T, `metadata(T).needs_human_review = true`
- **Cost Estimation**: Pour N clés à traduire, `cost = N * 0.01$` (GPT-4)

---

### Requirement 9: Remote Translation Updates (Post-PMF)

**User Story:** En tant que product owner, je veux corriger les traductions sans release app, afin de réagir rapidement aux feedbacks clients.

#### Acceptance Criteria

1. THE Translation_System SHALL synchroniser les traductions en background (pas de reload temps réel)
2. THE Translation_System SHALL activer les nouvelles traductions au redémarrage de l'app
3. THE Translation_System SHALL stocker les traductions localement (cache SQLite ou SharedPreferences)
4. WHEN une mise à jour de traduction est disponible, THE Translation_System SHALL la télécharger en background
5. THE Translation_System SHALL notifier l'utilisateur qu'un redémarrage est recommandé pour appliquer les nouvelles traductions

**Correctness Properties:**
- **Background Sync**: Les traductions sont synchronisées sans bloquer l'UI
- **Activation on Restart**: Les nouvelles traductions sont appliquées uniquement après redémarrage
- **Local Cache**: Les traductions sont stockées localement pour fonctionnement offline
- **No Real-Time Reload**: Pas de changement de langue en temps réel pendant l'utilisation

---

### Requirement 10: Parser et Pretty Printer (Validation Round-Trip)

**User Story:** En tant que développeur, je veux un parser et pretty printer pour les fichiers ARB, afin de garantir la cohérence des traductions.

#### Acceptance Criteria

1. THE Translation_System SHALL parser les fichiers ARB_File en objets Dart
2. THE Translation_System SHALL valider la syntaxe JSON des fichiers ARB_File
3. THE Translation_System SHALL implémenter un pretty printer pour formater les fichiers ARB_File
4. FOR ALL valid ARB_File objects, parsing then printing then parsing SHALL produce an equivalent object (round-trip property)
5. WHEN un fichier ARB_File est invalide, THE Translation_System SHALL retourner une erreur descriptive

**Correctness Properties:**
- **Round-Trip**: Pour tout fichier ARB valide A, `parse(print(parse(A))) = parse(A)`
- **Syntax Validation**: Pour tout fichier ARB invalide A, `parse(A)` retourne une erreur avec ligne et colonne
- **Pretty Print Idempotence**: Pour tout fichier ARB A, `print(print(A)) = print(A)`

---

## Priorités et Phases

### Phase 1 (1 jour) : Architecture
- `/shared/i18n/` source of truth
- `glossary.json` avec termes RH verrouillés
- Scripts sync locaux (pas d'API encore)
- Fallback system (AR → EN → FR → key)

### Phase 2 (2-3 jours) : MVP 5 écrans
- Auth (login, register, welcome)
- Home
- Attendance (check-in/out, history)
- Absences (list, request)
- Payrolls (list, detail)

### Phase 3 (1 jour) : Emails + QA + RTL
- Emails multilingues backend
- QA minimale (validation clés, placeholders)
- RTL validation arabe

### Phase 4 (post-PMF) : Remote + Analytics + IA
- Remote translation updates
- Analytics business avancées
- AI translation pipeline
- Golden tests

---

## Critères de Succès

### Phase 1
- [ ] `/shared/i18n/` créé avec structure centralisée
- [ ] `glossary.json` créé avec termes RH verrouillés
- [ ] Script de sync local fonctionnel
- [ ] Fallback chain implémenté et testé

### Phase 2
- [ ] 5 écrans MVP migrés (0% texte hardcodé)
- [ ] Toutes les clés existent dans les 4 langues
- [ ] Tests manuels sur device réel (FR, AR, TR, EN)

### Phase 3
- [ ] 5 emails traduits dans les 4 langues
- [ ] Validation CI/CD bloque si clés manquantes
- [ ] RTL validé manuellement sur les 5 écrans MVP

### Phase 4 (post-PMF)
- [ ] Remote updates fonctionnels (sync background)
- [ ] Analytics dashboard avec métriques clés
- [ ] Pipeline IA documenté et estimé

---

## Notes Importantes

### Pragmatisme CTO

**✅ CE QUI EST EXCELLENT (à conserver):**
1. Source of truth centralisée (`/shared/i18n/`)
2. Glossary verrouillé (`glossary.json` avec `locked: true`)
3. MVP basé sur usage réel (Auth + Attendance + Payrolls + Absences = 80% valeur)
4. Remote translation updates (corrections sans release)
5. QA automation avancée

**❌ CORRECTIONS CRITIQUES (implémentées):**
1. **PAS de reload temps réel** → sync background + activation au redémarrage
2. **PAS d'API traduction immédiate** → sync scripts locaux d'abord
3. **JSON hiérarchique max 3 niveaux** → `attendance.check_in` pas `modules.attendance.actions.checkin.buttons.primary.label`
4. **Fallback intelligent obligatoire** → AR → EN → FR → hardcoded key
5. **Analytics business obligatoires** → `language_selected`, `locale_retention`, `screen_locale_usage`, `rtl_session_duration`
6. **Stratégie IA à prévoir** → GPT contextualisé avec prompt RH + validation glossary + flag `needs_human_review`

### Scalabilité

- **Architecture prête pour 10+ langues** : Ajouter une langue = ajouter un dossier
- **Performance** : Cache local, lazy loading, génération compile-time
- **Maintenance** : Validation automatique, synchronisation CI/CD

### Recommandations

1. **Commencer par Phase 1** : Architecture solide, ROI immédiat
2. **Prioriser Phase 2** : MVP 5 écrans = 80% valeur business
3. **Phase 3 rapide** : QA minimale + emails + RTL
4. **Phase 4 post-PMF** : Remote updates + analytics + IA quand le produit scale

---

**Fin du document requirements**
