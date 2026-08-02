# 🎨 Leopardo Core - Design System

Ce package `leopardo_core` contient les fondations du Design System "Premium Glassmorphism" utilisé par toutes les applications mobiles de la plateforme Leopardo RH.

## 📦 Widgets Disponibles

### Layout Principal
- **`MobilePage`** : Remplace le `Scaffold` standard. Fournit le fond sombre/adaptatif, la `SafeArea`, et un `ListView` avec des paddings optimisés.
- **`MobileTopBar`** : Remplace `AppBar`. Design épuré, pas d'élévation, typographie hiérarchisée.

### Surfaces "Glassmorphism"
- **`GlassCard`** : ⭐️ Le composant central du nouveau design. Remplace les anciens `Card` ou `Container` plats. Apporte l'effet de verre trempé (blur), bordure subtile et ombre diffuse.
- **`GlassTile`** : Version spécialisée de `GlassCard` pour les tuiles de navigation ou d'action (avec icône, titre, sous-titre).
- **`MobilePanel`** : Alternative sans l'effet blur coûteux en performance, mais respectant les mêmes tokens visuels (couleurs, rayons de bordure).

### Éléments UI
- **`MobileSectionLabel`** : Pour les en-têtes de section en petites majuscules.
- **`MobileStatusPill`** : Badge de statut arrondi.
- **`MobilePrimaryAction`** : Bouton d'action principal (émeraude).
- **`MobileEmptyLoading`** : Indicateur de chargement standardisé.

## 🛠 Comment utiliser ?

**AVANT (Legacy) :**
```dart
Scaffold(
  appBar: AppBar(title: Text('Mon Écran')),
  body: Container(
    padding: EdgeInsets.all(16),
    color: Colors.white,
    child: Column(
      children: [
        Card(
          elevation: 2,
          child: Padding(
            padding: EdgeInsets.all(16),
            child: Text('Contenu'),
          )
        )
      ]
    )
  )
)
```

**APRÈS (Premium Glassmorphism) :**
```dart
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/core/widgets/glass_card.dart';

MobilePage(
  appBar: MobileTopBar(title: 'Mon Écran'),
  children: [
    GlassCard(
      child: Text('Contenu'),
    ),
  ],
)
```

## 🎨 Tokens (Couleurs & Typographie)

Utilisez exclusivement :
- `AppColors` (`package:leopardo_core/core/theme/app_colors.dart`)
- `AppTypography` (`package:leopardo_core/core/theme/app_typography.dart`)
- `MobileSurface` (`package:leopardo_core/core/widgets/mobile_surface.dart`) pour les couleurs de fond/bordure sémantiques.
