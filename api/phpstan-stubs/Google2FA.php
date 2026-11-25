<?php

declare(strict_types=1);

/**
 * Stub PHPStan pour `PragmaRX\Google2FA\Google2FA` (#9922).
 *
 * ## Pourquoi ce stub existe
 *
 * `SuperAdminService` appelle `generateSecretKey()`, `verifyKey()` et
 * `getQRCodeUrl()` sur une instance de `Google2FA` — mais **uniquement si la
 * classe existe** :
 *
 *     private function google2fa(): ?object
 *     {
 *         if (! class_exists(Google2FA::class)) {
 *             return null;
 *         }
 *
 *         return new Google2FA;
 *     }
 *
 * La dépendance `pragmarx/google2fa` n'est **pas** dans `composer.json` : le
 * service est écrit pour fonctionner sans elle, avec un repli TOTP maison
 * (`totpAt()`, `base32Encode()`, `base32Decode()`). C'est un choix délibéré —
 * la 2FA super-admin ne doit pas dépendre d'un paquet optionnel.
 *
 * Conséquence pour l'analyse statique : `class_exists()` ne restreint pas le
 * type de retour, donc `google2fa()` est déclaré `?object`, et PHPStan signale
 * trois `method.notFound` (« Call to an undefined method
 * object::generateSecretKey() »). Ces trois erreurs étaient gelées dans
 * `phpstan-strict-baseline.neon`.
 *
 * ## Pourquoi un stub plutôt qu'un `ignoreErrors`
 *
 * Une entrée baseline dit « cette erreur est acceptable ». Ici l'erreur n'est
 * pas acceptable : elle signale un **vrai trou de typage**. Le stub décrit
 * l'interface réellement attendue, ce qui :
 *
 *   1. supprime les trois erreurs sans les masquer ;
 *   2. fait vérifier par PHPStan que les appels correspondent bien à la
 *      signature réelle de la bibliothèque (nom, arité, types) ;
 *   3. documente le contrat pour le prochain lecteur.
 *
 * Le stub n'est **pas** chargé à l'exécution : il n'est référencé que par
 * `phpstan.neon` (`scanFiles`), donc il n'entre jamais dans l'autoload de
 * production et ne peut pas masquer l'absence du paquet.
 *
 * ## Si `pragmarx/google2fa` est ajouté un jour
 *
 * Ce fichier devient inutile : la vraie classe sera résolue par Composer et
 * `scanFiles` n'aura plus d'effet. Le supprimer alors, et retirer
 * `phpstan-stubs/Google2FA.php` de `phpstan.neon`.
 *
 * @see app/Core/Auth/Infrastructure/Services/SuperAdminService.php
 */
namespace PragmaRX\Google2FA;

/**
 * Surface utilisée par `SuperAdminService`, alignée sur l'API publique de
 * `pragmarx/google2fa` v8.
 *
 * Déclaration INCONDITIONNELLE : PHPStan n'enregistre pas une classe déclarée
 * dans un `if (! class_exists(...))`, et le stub n'est de toute façon jamais
 * chargé à l'exécution (`scanFiles` uniquement). Le garde `class_exists` vit
 * dans `SuperAdminService::google2fa()`, pas ici.
 */
class Google2FA
{
    /**
     * Génère une clé secrète base32.
     *
     * @param  int  $length  Longueur de la clé (16 par défaut dans la bibliothèque).
     */
    public function generateSecretKey(int $length = 16): string
    {
        return '';
    }

    /**
     * Vérifie un code TOTP contre un secret.
     *
     * @param  string  $secret  Secret base32 de l'utilisateur.
     * @param  string  $key  Code à 6 chiffres fourni par l'utilisateur.
     * @param  int|null  $window  Fenêtre de tolérance en pas de 30 s.
     * @param  int|null  $timestamp  Horodatage de référence (tests).
     */
    public function verifyKey(string $secret, string $key, ?int $window = null, ?int $timestamp = null): bool
    {
        return false;
    }

    /**
     * Construit l'URI `otpauth://` à encoder en QR code.
     */
    public function getQRCodeUrl(string $company, string $holder, string $secret): string
    {
        return '';
    }
}
