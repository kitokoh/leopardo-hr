# ADR-001 — Adoption de la Clean Architecture

**Date** : 2026-06-27
**Statut** : Accepted
**Décideurs** : Équipe technique Leopardo HR

## Contexte

Le projet a démarré avec une architecture plate (flat) standard Laravel :
- Tous les controllers dans `app/Http/Controllers/Api/V1/` (80+ fichiers)
- Tous les services dans `app/Services/` (40+ fichiers)
- Tous les modèles dans `app/Models/`

Cette structure devient difficile à maintenir au-delà de 20 000 lignes de code :
- Impossible de savoir quel controller appartient à quel domaine métier
- Les services s'importent mutuellement sans frontières claires
- Ajouter un module risque de casser l'auth ou la paie

## Décision

Adopter la **Clean Architecture** avec séparation en modules DDD :

```
app/Modules/{MODULE}/
  Application/    # Use cases, DTOs, Actions
  Domain/         # Modèles, Exceptions, Enums, Events
  Infrastructure/ # Services, Repositories, Exports
  Interfaces/     # Controllers, Requests, Resources
  Providers/      # Service Provider du module
```

## Conséquences positives

- **Isolation** : modifier le module Paie n'impacte pas le module RH
- **Testabilité** : chaque module peut être testé indépendamment
- **Onboarding** : un développeur trouve tous les fichiers d'un domaine au même endroit
- **Scalabilité** : ajouter un nouveau module = copier un stub + créer ses routes

## Conséquences négatives

- **Migration progressive** nécessaire — on ne peut pas tout migrer d'un coup
- **Double structure** temporaire (flat + modulaire) pendant la transition
- **Formation** nécessaire pour les contributeurs

## Alternatives considérées

- **Garder la structure flat** : rejeté — trop difficile à maintenir en équipe
- **Microservices** : rejeté — trop tôt, overhead infrastructure non justifié
- **Laravel Modules package** : rejeté — ajoute une dépendance externe, même résultat faisable nativement
