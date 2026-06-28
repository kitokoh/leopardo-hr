# Core — Socle Partagé

Ce répertoire contient les composants **transversaux** consommés par tous les modules.

## Structure

- `Auth/` — Authentification (Login, Register, Tokens, SSO)
- `Tenant/` — Multi-tenant management

## Règle fondamentale

**Les modules ne modifient jamais Core.**
Core expose des contrats/interfaces. Les modules les consomment.

## Migration en cours

Les fichiers originaux dans `app/Http/Controllers/Api/V1/`, `app/Services/` et `app/Models/`
sont conservés pendant la transition. Une fois les tests validés sur la nouvelle structure,
les originaux seront supprimés progressivement.
