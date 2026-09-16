# TERMES — lexique produit opposable (protocole P03)

Termes autorisés / interdits sur toutes les surfaces (vitrine, README, docs, pitchs).

| Contexte | À dire | À ne PAS dire |
|---|---|---|
| Nom produit | **Leopardo** | « Leopardo RH » dans une phrase de présentation (c'est un identifiant technique, pas un nom d'usage) |
| Catégorie du produit | **suite métier** (FR) · **business suite** (EN) · **işletme yönetimi paketi** (TR) · **حزمة الأعمال** (AR) | « logiciel RH », « SaaS RH », « HR SaaS », « HR software », « İK yazılımı » |
| Contenu RH de la suite | « RH & paie » (comme périmètre, dans une phrase qui décrit ce que la suite fait) | présenter l'entreprise comme une société « uniquement RH » |
| Site marketing | la vitrine | le site web (ambigu) |
| Espace client | portail client / dashboard | back-office |
| Super-admin | plateforme / admin plateforme | admin client |
| Pointage | pointage mobile/kiosk/biométrie | chronométrage |
| Paie pays | règles pays en cours de validation (statut pilot) | paie 100 % conforme |
| Statut produit | open-source, multi-tenant, mobile-first | « entreprise » tant que non prouvé |
| Apps | Leopardo Employee / Manager / RH / Platform Admin / Accounting / Marketing / Travel Agent | « l'app » (ambigu) |

Règle : toute nouvelle surface de présentation doit reprendre ce lexique — écart = bug de contenu (label `content`).

Garde automatique de non-régression (dette gelée, mesurée) : `dev-hub/tools/check-naming-drift.sh` + `dev-hub/tools/naming-baseline.json` (#7428).
Décision de positionnement complète : `POSITIONNEMENT_SUITE_METIER.md`.
