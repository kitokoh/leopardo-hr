# Multilinguisme — Leopardo RH Backend

## Langues supportées

| Code | Langue    | Direction | Statut      |
|------|-----------|-----------|-------------|
| `fr` | Français  | LTR       | Base        |
| `ar` | العربية   | RTL       | Production  |
| `tr` | Türkçe    | LTR       | Production  |
| `en` | English   | LTR       | Production  |

## Architecture

### Résolution de la locale (priorité)

Le middleware `SetLocale` résout la locale dans cet ordre :

1. **Préférence utilisateur** — `employees.preferred_language`
2. **Langue entreprise** — `companies.language`
3. **Header HTTP** — `Accept-Language`
4. **Défaut** — `fr`

### Fichiers de traduction

```
api/lang/
├── fr/          # Source de vérité
│   ├── errors.php
│   ├── auth.php
│   ├── attendance.php
│   ├── employees.php
│   ├── finance.php
│   ├── emails.php
│   ├── pdf.php
│   ├── cameras.php
│   └── validation.php
├── ar/          # Même structure
├── tr/          # Même structure
└── en/          # Même structure
```

### Modèles

- **`Language`** — Table `public.languages`, constantes `SUPPORTED` et `DEFAULT`
- **`Employee.preferred_language`** — CHAR(2) nullable, override personnel
- **`Company.language`** — Langue par défaut de l'entreprise

### Endpoint

```
PATCH /api/v1/auth/language
Content-Type: application/json
Authorization: Bearer {token}

{ "language": "ar" }
```

Retourne le profil complet avec le champ `language` mis à jour.

## Utilisation dans le code

```php
// Message traduit
__('errors.INVALID_CREDENTIALS')

// Avec paramètres
__('attendance.hours_worked', ['hours' => 8])

// Vérifier si une langue est supportée
Language::isSupported('fr') // true

// Vérifier si une langue est RTL
Language::isRtl('ar') // true
```

## Ajouter une nouvelle langue

1. Insérer dans la table `languages` (migration ou seeder)
2. Ajouter le code dans `Language::SUPPORTED`
3. Créer le dossier `api/lang/{code}/` avec tous les fichiers
4. Traduire chaque fichier depuis `lang/fr/` (source de vérité)
