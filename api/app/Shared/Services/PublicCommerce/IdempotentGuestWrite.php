<?php

declare(strict_types=1);

namespace App\Shared\Services\PublicCommerce;

use Closure;
use Throwable;

/**
 * BOS-050 (#8208) — Idempotence des écritures invitées (sans compte).
 *
 * Mutualise le pattern prouvé par RetailOnlineOrderService,
 * CreateBookingAction (Travel), CreateOnlineOrderAction (Restaurant) et
 * HospitalityReservationService : toute écriture publique invitée porte une
 * clé d'idempotence fournie par le client, unique PAR TENANT
 * (`unique(company_id, idempotency_key)`).
 *
 * Contrat de {@see replay()} :
 *  1. si une ressource existe déjà pour la clé → retournée telle quelle
 *     (`created = false` — le contrôleur répond alors 200 avec le même
 *     payload qu'à la création) ;
 *  2. sinon `$create()` s'exécute (`created = true`) ;
 *  3. course perdue sur la contrainte d'unicité (SQLSTATE 23505 : le pair
 *     a gagné entre la relecture et l'insert) → relecture du résultat
 *     initial (`created = false`).
 *
 * ⚠️ Appeler HORS transaction ouverte : PostgreSQL avorte la transaction
 * courante sur 23505 (toute relecture dans la même transaction échouerait
 * en 25P02). `$create()` peut ouvrir sa PROPRE transaction interne.
 */
final class IdempotentGuestWrite
{
    /**
     * Rejoue une écriture invitée de façon idempotente.
     *
     * @template T
     *
     * @param  Closure(): ?T  $findExisting  relecture par clé d'idempotence (scope tenant courant)
     * @param  Closure(): T  $create  création (jamais appelée si la ressource existe déjà)
     * @return array{result: T, created: bool}
     *
     * @throws Throwable toute erreur de création qui n'est PAS une violation d'unicité,
     *                   ou une 23505 sans ressource relisible (la violation venait d'ailleurs).
     */
    public function replay(Closure $findExisting, Closure $create): array
    {
        $existing = $findExisting();

        if ($existing !== null) {
            return ['result' => $existing, 'created' => false];
        }

        try {
            return ['result' => $create(), 'created' => true];
        } catch (Throwable $e) {
            if (! self::isUniqueViolation($e)) {
                throw $e;
            }

            // Course sur la clé : le premier arrivé a gagné — rejeu
            // idempotent = retourner SON enregistrement.
            $winner = $findExisting();

            if ($winner === null) {
                throw $e;
            }

            return ['result' => $winner, 'created' => false];
        }
    }

    /**
     * Détecte une violation d'unicité PostgreSQL (SQLSTATE 23505), y compris
     * encapsulée dans une exception enveloppe.
     */
    public static function isUniqueViolation(Throwable $e): bool
    {
        for ($t = $e; $t instanceof Throwable; $t = $t->getPrevious()) {
            if (str_contains($t->getMessage(), '23505')) {
                return true;
            }

            $code = $t->getCode();

            if ($code === '23505' || $code === 23505) {
                return true;
            }
        }

        return false;
    }
}
