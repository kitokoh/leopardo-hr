<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Exceptions;

use RuntimeException;

/**
 * Erreur normalisee de provider de paiement Retail (BC-17 RETAIL, #7812) :
 * provider inconnu/non configure ou appel PSP en echec. Convertie en 422
 * (`PAYMENT_PROVIDER_UNAVAILABLE`) sur la surface publique — jamais de
 * details internes exposes.
 */
final class RetailPaymentProviderException extends RuntimeException {}
