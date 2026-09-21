<?php

declare(strict_types=1);

namespace App\Modules\Retail\Application\Services;

use App\Modules\Retail\Domain\Models\MarketplaceBuyer;
use App\Modules\Retail\Domain\Models\MarketplaceBuyerToken;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Carbon;

/**
 * Comptes acheteurs de la marketplace Leopardo Marche (BC-17 RETAIL, #7814).
 *
 * Comptes PLATEFORME (tables centrales du schema public) : inscription
 * legere, login email + mot de passe, jetons OPAQUES (`mkb_` + 64 hex)
 * hashes en SHA-256 cote serveur — jamais de Sanctum tenant ici (les
 * personal access tokens Sanctum vivent dans les schemas tenants).
 *
 * Fail-closed : jeton absent/errone/expire → null (le middleware
 * `market.buyer` repond 401 uniforme, sans probing).
 */
final class RetailBuyerAccountService
{
    private const TOKEN_PREFIX = 'mkb_';

    // #7979 : TTL court (7 j, était 30) — la session acheteur vit en
    // localStorage (lisible par XSS) tant que le cookie HttpOnly n'est pas
    // livré ; une rotation courte borne la fenêtre d'abus d'un jeton volé.
    private const TOKEN_TTL_DAYS = 7;

    public function __construct(private readonly Hasher $hasher) {}

    /**
     * Inscription legere. L'unicite email est verifiee en amont
     * (FormRequest) ET garantie par l'index unique en base.
     *
     * @return array{buyer: MarketplaceBuyer, token: string}
     */
    public function register(string $name, string $email, string $password, ?string $phone): array
    {
        $buyer = MarketplaceBuyer::query()->create([
            'name' => $name,
            'email' => mb_strtolower($email),
            'password' => $this->hasher->make($password),
            'phone' => $phone,
        ]);

        return ['buyer' => $buyer, 'token' => $this->issueToken($buyer)];
    }

    /**
     * Login email + mot de passe — null si identifiants invalides (401
     * uniforme cote controleur, jamais de distinction email/mot de passe).
     *
     * @return array{buyer: MarketplaceBuyer, token: string}|null
     */
    public function login(string $email, string $password): ?array
    {
        /** @var MarketplaceBuyer|null $buyer */
        $buyer = MarketplaceBuyer::query()
            ->where('email', mb_strtolower($email))
            ->first();

        if (! $buyer instanceof MarketplaceBuyer
            || ! $this->hasher->check($password, $buyer->password)) {
            return null;
        }

        return ['buyer' => $buyer, 'token' => $this->issueToken($buyer)];
    }

    /**
     * Resout l'acheteur porte par un jeton Bearer — null si jeton absent,
     * malforme, inconnu ou expire (fail-closed).
     */
    public function buyerForBearerToken(?string $bearer): ?MarketplaceBuyer
    {
        $token = trim((string) $bearer);

        if (! str_starts_with($token, self::TOKEN_PREFIX) || strlen($token) !== strlen(self::TOKEN_PREFIX) + 64) {
            return null;
        }

        /** @var MarketplaceBuyerToken|null $row */
        $row = MarketplaceBuyerToken::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $row instanceof MarketplaceBuyerToken) {
            return null;
        }

        if ($row->expires_at instanceof Carbon && $row->expires_at->isPast()) {
            $row->delete();

            return null;
        }

        $row->forceFill(['last_used_at' => Carbon::now()])->saveQuietly();

        /** @var MarketplaceBuyer|null $buyer */
        $buyer = MarketplaceBuyer::query()->find($row->buyer_id);

        return $buyer;
    }

    /**
     * Revoque le jeton porte par la requete (logout). Idempotent.
     */
    public function revokeBearerToken(?string $bearer): void
    {
        $token = trim((string) $bearer);

        if ($token === '') {
            return;
        }

        MarketplaceBuyerToken::query()
            ->where('token_hash', hash('sha256', $token))
            ->delete();
    }

    /**
     * Emet un jeton opaque : le clair part au client, seul le hash
     * SHA-256 est persiste.
     */
    private function issueToken(MarketplaceBuyer $buyer): string
    {
        $plain = self::TOKEN_PREFIX.bin2hex(random_bytes(32));

        MarketplaceBuyerToken::query()->create([
            'buyer_id' => (int) $buyer->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => Carbon::now()->addDays(self::TOKEN_TTL_DAYS),
        ]);

        return $plain;
    }
}
