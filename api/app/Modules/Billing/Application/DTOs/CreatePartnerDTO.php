<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\DTOs;

/**
 * Candidature partenaire (ex-`Modules\Growth`, absorbe par Billing — BOS-026).
 *
 * Les champs `phone` / `website` / `commissionRate` etaient **consommes** par
 * `ApplyAsPartner` sans avoir jamais ete declares : la classe les documentait
 * comme absents alors que le code les lisait (`$dto->phone`), donc toute
 * candidature portant un telephone partait avec `null` et PHPStan niveau 8
 * signalait une propriete indefinie. Les trois champs sont explicites ici.
 */
final class CreatePartnerDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $company = null,
        public readonly ?string $referralCode = null,
        public readonly ?string $phone = null,
        public readonly ?string $website = null,
        public readonly ?float $commissionRate = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $rate = $data['commission_rate'] ?? null;

        return new self(
            name: is_string($data['name'] ?? null) ? $data['name'] : '',
            email: is_string($data['email'] ?? null) ? $data['email'] : '',
            company: isset($data['company']) && is_string($data['company']) ? $data['company'] : null,
            referralCode: isset($data['referral_code']) && is_string($data['referral_code']) ? $data['referral_code'] : null,
            phone: isset($data['phone']) && is_string($data['phone']) ? $data['phone'] : null,
            website: isset($data['website']) && is_string($data['website']) ? $data['website'] : null,
            // Un taux arrive d'un formulaire HTTP en chaine : on ne le caste pas
            // en silence, on le refuse si ce n'est pas un nombre.
            commissionRate: is_numeric($rate) ? (float) $rate : null,
        );
    }
}
