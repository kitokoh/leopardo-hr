# Plan 58 - Personnalisation entreprise et image premium

## Source

Point utilisateur 32.

## Objectif

Permettre a chaque entreprise cliente de personnaliser l'apparence de son espace : logo, couleurs, nom affiche, branding interne et experience premium.

## Perimetre

- Backend tenant settings.
- Manager mobile.
- Dashboard client web/admin si surface existante.
- Assets publics et stockage medias.

## Lots d'execution

### Lot 58.1 - Contrat backend branding

- Ajouter ou consolider un modele/setting tenant pour `display_name`, `logo_url`, `primary_color`, `accent_color`, `brand_mode`.
- Ajouter endpoints `GET/PATCH /api/v1/company/branding`.
- Valider couleurs par format hex allowliste.
- Verifier isolation tenant stricte.

### Lot 58.2 - Upload logo

- Ajouter upload image securise : type mime, taille, dimensions, stockage, suppression ancienne version.
- Prevoir fallback si storage cloud absent.
- Retourner URL stable dans resource.

### Lot 58.3 - UI manager

- Ajouter ecran "Identite entreprise" dans manager mobile et/ou dashboard.
- Preview immediate du logo et des couleurs.
- UX simple : logo, nom, couleur principale, couleur accent.

### Lot 58.4 - Application du theme

- Utiliser branding sur headers, cartes importantes et ecrans entreprise.
- Ne pas casser la lisibilite : contraste minimum, fallback AppColors.

## Fichiers probables

- `api/app/Http/Controllers/Api/V1/*Branding*`
- `api/app/Models/CompanySetting.php` ou equivalent
- `api/database/migrations/**`
- `front/mobile_apps/leopardo_manager/lib/**`
- `front/admin-dashboard/src/**`

## Tests et validations

- Feature test tenant isolation.
- Validation upload fichier.
- Mobile apps analysis/build.
- Frontend API contract si nouveau endpoint.

## Criteres d'acceptation

- Un manager principal/RH autorise peut modifier branding de son entreprise.
- Un autre tenant ne voit jamais ce branding.
- App reste lisible meme avec couleur client invalide ou absente.
