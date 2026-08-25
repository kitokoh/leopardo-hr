<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1\Requests;

/**
 * Complétion de l'activation guidée du module Comptabilité — issue #5288.
 *
 * Réutilise les règles de `UpdateAccountingSettingsRequest` (#5232) : chaque
 * champ est optionnel et remplace la valeur par défaut dérivée du pays de
 * l'entreprise quand il est fourni (devise, langue des documents, TVA,
 * séries de numérotation, mentions légales, conditions de paiement).
 */
final class CompleteActivationRequest extends UpdateAccountingSettingsRequest {}
