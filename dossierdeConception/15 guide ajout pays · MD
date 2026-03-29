# GUIDE — AJOUT D'UN NOUVEAU PAYS
# Version 1.0 | Mars 2026

---

## QUI PEUT AJOUTER UN PAYS ?

Uniquement le Super Admin, depuis son panneau d'administration.
Aucun gestionnaire client n'a accès à cette fonctionnalité.

---

## PROCÉDURE EN 5 ÉTAPES

### Étape 1 — Préparer les données du pays

Collecter les informations suivantes pour le nouveau pays :

**Cotisations sociales** :
- Liste des cotisations salariales (nom, taux %, base, plafond si applicable, multiplicateur)
- Liste des cotisations patronales (même structure)

**Impôt sur le revenu** :
- Tranches d'imposition : min, max (null = illimité), taux %, déduction forfaitaire
- Abattements éventuels

**Règles de congé** :
- Taux d'acquisition mensuel (jours/mois)
- Report annuel (oui/non, plafond)
- Solde maximum
- Délai de prévenance minimum

**Jours fériés** :
- Fêtes à date fixe (format MM-DD) : `is_recurring = true`
- Fêtes mobiles (islamiques, etc.) pour l'année en cours : `is_recurring = false, date = YYYY-MM-DD`

---

### Étape 2 — Vérifier les données

**⚠️ Avertissement légal important :** Les taux de cotisations et les barèmes IR
évoluent chaque année dans la plupart des pays. Vérifier systématiquement auprès
des sources officielles (site du ministère du travail, bulletin officiel) avant de saisir.

Les données dans Leopardo RH sont données à titre indicatif et doivent être validées
par un comptable ou expert RH local avant utilisation en production.

---

### Étape 3 — Insérer dans hr_model_templates

```php
// Via le panneau Super Admin ou via un Seeder
HRModelTemplate::create([
    'country_code' => 'XX',  // Code ISO 3166-1 alpha-2
    'name'         => 'Droit du travail de [Pays]',
    'cotisations'  => json_encode([
        'salariales' => [
            [
                'name'       => 'Nom de la cotisation',
                'rate'       => 9.0,          // % (ex: 9.0 = 9%)
                'base'       => 'gross',       // base de calcul : 'gross' uniquement pour l'instant
                'ceiling'    => null,          // plafond mensuel en devise locale, null = sans plafond
                'multiplier' => 1.0,           // coefficient (1.0 = normal, 0.9825 = pour CSG française)
            ],
            // ... autres cotisations
        ],
        'patronales' => [
            // même structure
        ],
    ]),
    'ir_brackets' => json_encode([
        ['min' => 0,      'max' => 100000, 'rate' => 0,  'deduction' => 0],
        ['min' => 100001, 'max' => 300000, 'rate' => 20, 'deduction' => 20000],
        ['min' => 300001, 'max' => null,   'rate' => 30, 'deduction' => 50000],
        // max = null signifie "et au-delà"
    ]),
    'leave_rules' => json_encode([
        'accrual_rate_monthly' => 2.5,   // jours acquis par mois travaillé
        'initial_balance'      => 0,      // jours accordés à l'embauche
        'carry_over'           => false,  // report d'une année à l'autre
        'carry_over_max_days'  => 0,      // 0 si carry_over = false
        'max_balance'          => 60,     // plafond d'accumulation
        'min_notice_days'      => 3,      // préavis minimum pour demander un congé
    ]),
    'holiday_calendar' => json_encode([
        // Fêtes à date fixe (MM-DD)
        ['date' => '01-01', 'name' => 'Jour de l\'An',     'is_recurring' => true],
        ['date' => '05-01', 'name' => 'Fête du Travail',   'is_recurring' => true],
        // Fêtes mobiles pour l'année en cours (format YYYY-MM-DD)
        ['date' => '2026-03-20', 'name' => 'Aïd Al-Fitr', 'is_recurring' => false],
    ]),
]);
```

---

### Étape 4 — Mettre à jour les jours fériés mobiles chaque année

**Pourquoi ?** Les fêtes islamiques (Aïd Al-Fitr, Aïd Al-Adha, etc.) changent de date
chaque année car basées sur le calendrier lunaire (environ -11 jours/an).

**Quand ?** Chaque année en **novembre**, avant l'année suivante.

**Comment ?**
1. Super Admin → "Gestion des pays" → sélectionner le pays
2. Section "Jours fériés variables" → modifier les dates de l'année suivante
3. Les entreprises utilisant ce modèle voient les nouvelles dates automatiquement

**Un cron alerte le Super Admin en novembre :**
```
Sujet : [Leopardo RH] Mise à jour des jours fériés {annee+1} requise
Corps : Les pays suivants ont des fêtes mobiles à mettre à jour : DZ, MA, TN, TR...
        Accéder au panneau : [lien]
```

---

### Étape 5 — Activer le pays dans le panneau

Dans le panneau Super Admin, activer le nouveau pays :
- Il apparaîtra dans la liste lors de la création d'une entreprise
- Les gestionnaires pourront sélectionner "Appliquer le modèle [Pays]" dans leurs paramètres

---

## PAYS DÉJÀ DISPONIBLES (Phase 1)

| Code | Pays | Statut | Mise à jour IR |
|------|------|--------|----------------|
| DZ | Algérie | ✅ Disponible | 2026 |
| MA | Maroc | ✅ Disponible | 2026 |
| TN | Tunisie | ✅ Disponible | 2026 |
| FR | France | ✅ Disponible | 2026 |
| TR | Turquie | ✅ Disponible | 2026 |
| SN | Sénégal | ✅ Disponible | 2026 |
| CI | Côte d'Ivoire | ✅ Disponible | 2026 |

---

## FORMAT DU CALENDRIER DES JOURS FÉRIÉS ISLAMIQUES 2026

Pour référence lors de la mise à jour annuelle :

| Fête | Date 2026 (approximative) | Note |
|------|--------------------------|------|
| Aïd Al-Fitr (fin Ramadan) | 20-21 mars 2026 | 1 ou 2 jours selon pays |
| Aïd Al-Adha | 16-17 juin 2026 | 1 ou 2 jours selon pays |
| Nouvel An Hégirien | 7 juillet 2026 | |
| Mawlid Ennabaoui | 15 septembre 2026 | |

*Les dates exactes dépendent de l'observation de la lune — vérifier avec les autorités religieuses locales.*

---

## PAYS PRÉVUS EN PHASE 2

- Mauritanie (MR)
- Niger (NE)
- Mali (ML)
- Cameroun (CM)
- Gabon (GA)
- Belgique (BE)
- Suisse (CH)
- Canada / Québec (CA)