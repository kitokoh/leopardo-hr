# Feature Specification: Bulletin de paie DZ — modèle officiel + RTL (issue #5242)

**Feature Branch**: `mod/payroll/5242-bulletin-dz-pdf`

**Created**: 2026-08-23

**Status**: Draft → Implemented

**Module**: `payroll` — périmètre touché : `api/app/Modules/Payroll/**`
(générateur PDF + nouveau `Infrastructure/Pdf/ArabicPdfText`), la vue
`api/resources/views/pdf/payslip.blade.php`, les locales `api/lang/*/pdf.php`,
la police `api/resources/fonts/Almarai-*.ttf`, `api/tests/**`, CHANGELOG.
Aucune collision : les agents moteur/exports (5241, 5243, 5245, 5317) ne
touchent pas le générateur PDF ni la vue bulletin (branches/PR vérifiées).

## Contexte

Le bulletin DZ doit être conforme aux mentions légales (déjà couvert : NIF/RC/
CNAS employeur/ID.Nat, cumuls — `PaySlipDzMentionsTest`) **et** lisible en
arabe RTL. État vérifié le 2026-08-23 :

| Brique | Existant | Gap |
|---|---|---|
| Mentions légales | ✅ Blocs employeur/employé/période/rémunération + cumuls (`BULLETIN_DZ_MENTIONS.md` [x]) | DoD « vérifié par un comptable » = suivi humain (docs) |
| Historique + téléchargement | ✅ `/me/pay-slips`, `/pay-slips`, `/pay-slips/{id}/pdf` (web + mobile) | — |
| **Rendu arabe RTL** | `dir=rtl` posé, locales ar présentes, **mais dompdf rend l'arabe DÉCONNECTÉ** (pas de shaping) + police DejaVu (couverture partielle) → **cassure visuelle** | **Le DoD « fr + ar sans cassure »** |
| Numérotation | — | « un bulletin par page, numérotation » |

## Problème racine (prouvé par test)

dompdf n'a **pas de moteur de shaping OpenType/HarfBuzz** : les lettres arabes
sont rendues isolées (non jointes) et dans l'ordre logique (donc inversées
visuellement en RTL). Vérifié empiriquement : le PDF AR extrait des mots
déconnectés (« الفترٜ ») avec DejaVuSans.

## Décisions

1. **`ArabicPdfText`** (`api/app/Modules/Payroll/Infrastructure/Pdf/`) :
   shaping contextuel minimal (formes de présentation U+FB50–U+FEFF selon les
   voisines, ligatures lām-alef ﻻ ﻷ ﻹ ﻵ) + inversion RTL par runs (bidi
   minimal : runs arabes inversés, ordre des runs inversé, latin/chiffres
   intacts). Zéro dépendance (pas d'ar-php, licence GPL incompatible).
2. **Police Almarai** (OFL, google/fonts, TTFs statiques) embarquée dans
   `api/resources/fonts/` et enregistrée dompdf par
   `PaySlipPdfGenerator::ensureArabicFonts()` (cache métriques dans
   `storage/fonts`, runtime gitignoré). Couverture vérifiée : formes de
   présentation B 91/112.
3. **Vue** : helper `$t()` qui applique le shaping quand la locale est RTL ;
   `font-family: Almarai` en RTL ; alignements `text-align: right`.
4. **Numérotation** : « Bulletin N° :n » (id du bulletin) sous le titre,
   clé i18n ×4.
5. **Golden-ish test** `PaySlipBilingualRenderTest` (DoD) : rendu FR (mentions
   + numérotation + pas de U+FFFD) et rendu AR (titre shaped présent,
   police Almarai embarquée, pas de U+FFFD). + tests unitaires
   `ArabicPdfTextTest` (5 cas : latin intact, shaping, ligature, mixte, titre).

## User Scenarios & Testing

### US1 — Bulletin lisible en arabe RTL (DoD)

**Independent Test**: `php artisan test --filter=PaySlipBilingualRenderTest`
→ 2/2 verts ; `php artisan test --filter=ArabicPdfTextTest` → 5/5 verts.

**Acceptance Scenarios**:

1. **Given** une company DZ `language=ar`, **When** le bulletin est généré,
   **Then** le titre « كشف الراتب » est rendu en formes jointes + ordre RTL
   (extrait = formes de présentation, pas de lettres de base), avec la police
   Almarai embarquée, **sans U+FFFD**.
2. **Given** une company DZ `language=fr`, **When** le bulletin est généré,
   **Then** titre + mentions légales + « Bulletin N° » présents, sans U+FFFD.
3. **Given** une ligne mixte « étiquette arabe : 47 558 », **When** rendue,
   **Then** la valeur latine reste intacte et l'ordre d'affichage est RTL.

### US2 — Numérotation + un bulletin par page

1. **Given** un bulletin, **When** généré, **Then** « Bulletin N° <id> »
   apparaît sous le titre (fr/ar/en/tr) et le PDF fait une page A4.

## Edge Cases

- **Texte latin pur** : `shape()` retourne la chaîne inchangée (pas d'inversion).
- **Lettres à 2 formes** (ا د ذ ر ز و ى ة ء آ أ إ ؤ) : n'acceptent pas de
  liaison à droite ; la précédente prend sa forme initiale/médiane (règle
  corrigée après test : `nextConnects` = toute lettre arabe).
- **Ligature lām-alef** : uniquement quand ل est suivi immédiatement de
  ا/أ/إ/آ (ex. « لا ») — pas dans « الراتب » (ا ل ر).
- **Fonts absentes** (dev partiel) : `ensureArabicFonts()` retombe sur DejaVu
  sans crash.
- **dompdf scinde l'arabe en runs TJ** : l'extracteur de test normalise les
  espaces avant comparaison.

## Deliverables

- [x] Spec `.specify/features/5242-bulletin-dz-pdf/spec.md`
- [x] `ArabicPdfText` (shaping + bidi minimal) + 5 tests unitaires
- [x] Police Almarai (Regular/Bold, OFL) + enregistrement dompdf
- [x] Vue `payslip.blade.php` : `$t()` RTL, police, alignements, numérotation
- [x] Clés i18n `payslip_number` ×4
- [x] `PaySlipBilingualRenderTest` (golden-ish fr + ar, DoD)
- [x] `docs/payroll/BULLETIN_DZ_MENTIONS.md` mis à jour
- [x] Entrée CHANGELOG `[Unreleased]` + PR `Closes #5242`
