solution d aide aux RH
pointage
presence
et realisation des taches


# LEOPARDO RH — DOSSIER PROJET COMPLET
## Architecture du dossier & Guide de démarrage
**Version 2.0 | Mars 2026 | Statut : PRÊT POUR EXÉCUTION**

---

## STRUCTURE DU PROJET

```
leopardo-rh/
│
├── README.md                          ← CE FICHIER — lire en premier
│
├── dossier_de_conception/             ← Référence technique figée
│   ├── 01_PROMPT_MASTER_CLAUDE_CODE.md
│   ├── 02_API_CONTRATS.md
│   ├── 03_ERD_COMPLET.md
│   ├── 04_SPRINT_0_CHECKLIST.md
│   ├── 05_SEEDERS_ET_DONNEES_INITIALES.md
│   ├── 06_PROMPT_MASTER_JULES_FLUTTER.md
│   ├── 07_SCHEMA_SQL_COMPLET.sql
│   ├── 08_FEUILLE_DE_ROUTE.md
│   ├── 09_REGLES_METIER_COMPLETES.md      ← NOUVEAU
│   ├── 10_RBAC_COMPLET.md                 ← NOUVEAU (remplace rbac_matrrix.md)
│   ├── 11_MULTITENANCY_STRATEGY.md        ← NOUVEAU (remplace multitenancy_strategy.md)
│   ├── 12_SECURITY_SPEC_COMPLETE.md       ← NOUVEAU (remplace security_specification.md)
│   ├── 13_USER_FLOWS_VALIDES.md           ← NOUVEAU (remplace users_flows_complet.md)
│   ├── 14_NOTIFICATION_TEMPLATES.md       ← NOUVEAU
│   └── 15_GUIDE_AJOUT_PAYS.md             ← NOUVEAU
│
├── prompts_execution/                 ← Prompts prêts à coller dans Claude Code et Jules
│   ├── LIRE_EN_PREMIER.md
│   ├── backend/
│   │   ├── P01_INIT_INFRASTRUCTURE.md
│   │   ├── P02_AUTH_MULTITENANT.md
│   │   ├── P03_MODULE_EMPLOYES.md
│   │   ├── P04_MODULE_POINTAGE.md
│   │   ├── P05_MODULE_ABSENCES.md
│   │   ├── P06_MODULE_TACHES.md
│   │   ├── P07_MODULE_PAIE.md
│   │   ├── P08_MODULE_NOTIFICATIONS.md
│   │   ├── P09_SUPER_ADMIN.md
│   │   └── P10_DEPLOIEMENT_O2SWITCH.md
│   └── mobile/
│       ├── P01_INIT_FLUTTER.md
│       ├── P02_ECRAN_POINTAGE.md
│       ├── P03_ECRANS_EMPLOYE.md
│       ├── P04_ECRANS_GESTIONNAIRE.md
│       └── P05_BUILD_PUBLICATION.md
│
└── config/
    ├── .env.example                   ← NOUVEAU — toutes les variables d'env
    ├── lang_fr.php                    ← NOUVEAU — squelette Laravel i18n
    ├── lang_ar.php                    ← NOUVEAU — squelette Laravel i18n
    ├── lang_tr.php                    ← NOUVEAU — squelette Laravel i18n
    ├── lang_en.php                    ← NOUVEAU — squelette Laravel i18n
    ├── app_fr.arb                     ← NOUVEAU — squelette Flutter i18n
    ├── app_ar.arb                     ← NOUVEAU — squelette Flutter i18n
    ├── app_tr.arb                     ← NOUVEAU — squelette Flutter i18n
    └── app_en.arb                     ← NOUVEAU — squelette Flutter i18n
```

---

## ARCHITECTURE TECHNIQUE — VUE D'ENSEMBLE

### Stack complète
| Couche | Technologie | Rôle |
|--------|-------------|------|
| Backend API | Laravel 11 (PHP 8.3) | Logique métier, API REST, PDF, emails |
| Base de données | PostgreSQL 16 multi-schéma | 1 schéma isolé par entreprise cliente |
| Cache & Queues | Redis 7 | Sessions, cache paramètres, jobs async |
| Frontend Web | Vue.js 3 + Inertia.js | Dashboard gestionnaire web |
| Application Mobile | Flutter 3.x | Interface employé et gestionnaire |
| Notifications Push | Firebase Cloud Messaging | Alertes temps réel sur mobile |
| Serveur | Nginx + PHP-FPM (o2switch VPS) | Production |
| Biométrique | ZKTeco SDK (Push + Pull) | Pointage par empreinte |

### Architecture multi-tenant
```
STRATÉGIE : Multi-schéma PostgreSQL (PAS shared table avec tenant_id)
─────────────────────────────────────────────────────────────────────
Schéma PUBLIC (partagé)
  └── companies, plans, super_admins, invoices, languages, hr_model_templates

Schéma company_{uuid} (1 par entreprise)
  └── employees, attendance_logs, absences, payrolls, tasks... (20 tables)

Isolation garantie par PostgreSQL SET search_path — TenantMiddleware Laravel
```

### Flux d'une requête
```
Flutter / Navigateur
    → Nginx (SSL, rate limit)
    → Laravel API
        → Sanctum : valide le token Bearer
        → TenantMiddleware : SET search_path TO company_{uuid}
        → SetLocale : App::setLocale(company.language)
        → Controller → Service → Eloquent Model
        → Réponse JSON ISO 8601
```

---

## ORDRE DE LECTURE DES DOCUMENTS

### Pour Claude Code (Backend)
```
1. CE README (architecture globale)
2. 11_MULTITENANCY_STRATEGY.md (stratégie multi-tenant)
3. 12_SECURITY_SPEC_COMPLETE.md (sécurité, auth)
4. 10_RBAC_COMPLET.md (permissions)
5. 03_ERD_COMPLET.md (modèle de données)
6. 07_SCHEMA_SQL_COMPLET.sql (schéma PostgreSQL exécutable)
7. 02_API_CONTRATS.md (endpoints, payloads, codes erreur)
8. 09_REGLES_METIER_COMPLETES.md (calculs, cas limites)
9. 05_SEEDERS_ET_DONNEES_INITIALES.md (données à insérer)
10. 01_PROMPT_MASTER_CLAUDE_CODE.md (règles de code)
11. prompts_execution/backend/P01_*.md → P10_*.md (prompts d'exécution)
```

### Pour Jules (Flutter Mobile)
```
1. CE README (architecture globale)
2. 10_RBAC_COMPLET.md (qui voit quoi)
3. 02_API_CONTRATS.md (endpoints consommés)
4. 13_USER_FLOWS_VALIDES.md (flux UX validés)
5. 06_PROMPT_MASTER_JULES_FLUTTER.md (règles Flutter)
6. 04_SPRINT_0_CHECKLIST.md section 0-D (initialisation Flutter)
7. prompts_execution/mobile/P01_*.md → P05_*.md (prompts d'exécution)
```

---

## DÉCISIONS FIGÉES — NE PAS REMETTRE EN QUESTION

Ces décisions ont été prises, documentées et validées. Elles ne se discutent plus pendant le développement.

| Décision | Choix retenu |
|----------|-------------|
| Architecture multi-tenant | Multi-schéma PostgreSQL (PAS tenant_id) |
| Auth mobile | Sanctum tokens opaques (PAS JWT) |
| Framework mobile | Flutter uniquement (PAS React Native) |
| État Flutter | Riverpod 2.x uniquement (PAS BLoC, PAS Provider) |
| Pointage par défaut | Déclaratif — sans photo ni GPS |
| Photo au pointage | Option désactivée par défaut |
| GPS au pointage | Option désactivée par défaut |
| Avances sur salaire | Module désactivé par défaut |
| Facturation | Manuelle Phase 1, automatique Phase 2 |
| Biométrique | ZKTeco uniquement |
| Hébergement | o2switch VPS |
| Langues initiales | FR + AR (RTL) + TR + EN |

---

## RÈGLE DE COMMUNICATION INTER-ÉQUIPE

```
Claude Code modifie un endpoint → Mettre à jour 02_API_CONTRATS.md en premier
Jules détecte un bug API       → Signaler avec : endpoint + comportement observé + attendu
Tout changement de schéma      → Mettre à jour 03_ERD_COMPLET.md + 07_SCHEMA_SQL_COMPLET.sql
Breaking change API            → Versionner /v2/ — JAMAIS casser /v1/
```

---

## CONTACTS TECHNIQUES

| Rôle | Outil | Priorité |
|------|-------|----------|
| Backend Laravel + Vue.js | Claude Code | Messages directs via prompts_execution/backend/ |
| Mobile Flutter | Jules | Messages directs via prompts_execution/mobile/ |
| Architecture & décisions | Ce dossier | Référence unique — pas d'oral |

---

*Leopardo RH — Dossier Projet v2.0 — Mars 2026*
*Ce README est le point d'entrée unique. Tout commence ici.*