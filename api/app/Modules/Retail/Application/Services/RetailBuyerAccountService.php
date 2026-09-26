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

    // #7979 : TTL court (7 j, était 30) — une rotation courte borne la
    // fenêtre d'abus d'un jeton volé.
    private const TOKEN_TTL_DAYS = 7;

    /**
     * #8096 — cookie de session acheteur (cible du compromis #7979 : la
     * session ne vit plus en localStorage, lisible par toute XSS).
     * Posé en `HttpOnly; Secure; SameSite=None` à register/login et au
     * endpoint de restauration ; le middleware `market.buyer` accepte le
     * Bearer historique (migration douce) PUIS ce cookie.
     *
     * Chemin borné à la surface publique marché : le navigateur n'envoie
     * le jeton que sur `/api/v1/public/market/*`.
     */
    public const SESSION_COOKIE = 'market_buyer_session';

    public const SESSION_COOKIE_PATH = '/api/v1/public/market';

    /**
     * Durée de vie du cookie = TTL du jeton (minutes, helper cookie()).
     */
    public const SESSION_COOKIE_MINUTES = self::TOKEN_TTL_DAYS * 24 * 60;

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
     * Resout l'acheteur porte par un jeton opaque — null si jeton absent,
     * malforme, inconnu ou expire (fail-closed). Accepte indifféremment
     * le Bearer historique et la valeur du cookie HttpOnly (#8096) : c'est
     * le même jeton opaque, seul le transport change.
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
     * Revoque le jeton porte par la requete, qu'il vienne du header Bearer
     * ou du cookie HttpOnly (#8096) — logout. Idempotent.
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
