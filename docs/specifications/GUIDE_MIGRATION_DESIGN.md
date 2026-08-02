# 🚀 Guide de Migration : Design System "Premium Glassmorphism"

Ce guide est la référence absolue pour migrer les pages et écrans de l'écosystème Leopardo RH (Mobile, Admin Web, Vitrine) vers le nouveau design premium.

## 📱 Partie 1 : Applications Mobiles Flutter (`leopardo_core`)

### 1. Remplacer `Scaffold` par `MobilePage`

**Legacy :**
```dart
return Scaffold(
  appBar: AppBar(title: const Text('Titre')),
  body: SingleChildScrollView(
    padding: const EdgeInsets.all(16),
    child: Column(children: [ ... ]),
  ),
);
```

**Nouveau (Premium) :**
```dart
import 'package:leopardo_core/core/widgets/mobile_surface.dart';

return MobilePage(
  appBar: const MobileTopBar(title: 'Titre'),
  children: [ 
    // Éléments de la page ici, pas besoin de Column/SingleChildScrollView
    // MobilePage gère le ListView et les SafeArea automatiquement
  ],
);
```

### 2. Remplacer `Card` ou `Container` plat par `GlassCard`

**Legacy :**
```dart
Card(
  elevation: 2,
  child: Padding(
    padding: const EdgeInsets.all(16),
    child: Text('Contenu'),
  ),
)
```

**Nouveau (Premium) :**
```dart
import 'package:leopardo_core/core/widgets/glass_card.dart';

GlassCard(
  padding: const EdgeInsets.all(16), // Défaut 16.0
  child: Text('Contenu', style: AppTypography.body),
)
```

### 3. Typographie et Couleurs

- **Ne JAMAIS utiliser `Colors.white`, `Colors.black`, ou `Colors.grey`.**
- Utilisez `AppColors.mobileDarkText`, `AppColors.mobileDarkMutedAlt`, etc.
- Utilisez `AppTypography` pour les styles de texte (ex: `AppTypography.titleSmall`).

## 🌐 Partie 2 : Dashboard Admin Vue.js

### 1. Surfaces et Cartes
Remplacer toutes les occurrences de `bg-white shadow rounded-lg` par les classes du Glassmorphism.

**Legacy :**
```html
<div class="bg-white shadow rounded-lg p-6">
  Contenu
</div>
```

**Nouveau (Premium) :**
```html
<div class="glass-card p-6">
  Contenu
</div>
```

### 2. Couleurs de fond
S'assurer que la vue principale n'est pas sur fond gris (`bg-gray-50`) mais utilise les tokens premium.
```html
<div class="glass-bg min-h-screen">
  <!-- Contenu du Dashboard -->
</div>
```

## 🌍 Partie 3 : Vitrine Next.js

1. S'assurer que `globals.css` inclut les variables CSS de base (`--glass-bg`, `--glass-border`).
2. Remplacer les styles ad-hoc par ces variables via les classes utilitaires.
3. Supprimer tout fond "plat" non dégradé pour les sections principales.

---
*Ce guide doit être appliqué à **tous** les nouveaux écrans et lors de la refonte des anciens.*
