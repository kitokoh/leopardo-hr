<?php

declare(strict_types=1);

namespace Tests\Unit\Branding;

use App\Modules\Branding\Domain\Exceptions\BrandHostException;
use App\Modules\Branding\Domain\Models\Brand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * #9578/#9662 — le domaine personnalisé d'une Brand.
 *
 * Ces tests sont unitaires purs : la règle de format ne dépend ni de la base ni
 * de HTTP. Ils verrouillent le défaut qui a motivé l'extraction : un hostname
 * inexploitable rendait `null`, et les actions enregistraient ce `null` comme
 * « pas de domaine » — à la création la Brand restait injoignable par host, à la
 * mise à jour le domaine existant était EFFACÉ, sans erreur.
 */
final class BrandHostTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function normalizableHosts(): array
    {
        return [
            'simple' => ['chez-lea.sn', 'chez-lea.sn'],
            'uppercase' => ['Chez-Lea.SN', 'chez-lea.sn'],
            'port' => ['chez-lea.sn:443', 'chez-lea.sn'],
            'trailing dot' => ['chez-lea.sn.', 'chez-lea.sn'],
            'surrounding spaces' => ['  chez-lea.sn  ', 'chez-lea.sn'],
            'subdomain' => ['www.chez-lea.sn', 'www.chez-lea.sn'],
        ];
    }

    #[DataProvider('normalizableHosts')]
    public function test_host_is_normalized(string $raw, string $expected): void
    {
        $this->assertSame($expected, Brand::normalizeHost($raw));
        $this->assertSame($expected, Brand::assertValidHost($raw));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unusableHosts(): array
    {
        return [
            'space' => ['chez lea.sn'],
            'accent' => ['chezléa.sn'],
            'underscore' => ['chez_lea.sn'],
            'scheme' => ['https://chez-lea.sn'],
            'path' => ['chez-lea.sn/accueil'],
            'empty' => [''],
            'only a dot' => ['.'],
        ];
    }

    #[DataProvider('unusableHosts')]
    public function test_unusable_host_returns_null_on_normalization(string $raw): void
    {
        $this->assertNull(Brand::normalizeHost($raw));
    }

    #[DataProvider('unusableHosts')]
    public function test_unusable_host_is_rejected_not_silently_dropped(string $raw): void
    {
        $this->expectException(BrandHostException::class);
        $this->expectExceptionMessage('BRAND_HOST_INVALID');

        Brand::assertValidHost($raw);
    }

    /**
     * Le refus est un contrat client : 422 + code stable, pas un 500.
     */
    public function test_host_error_is_a_client_error(): void
    {
        $exception = BrandHostException::invalid('chez_lea.sn');

        $this->assertSame(422, $exception->statusCode());
        $this->assertSame('BRAND_HOST_INVALID', $exception->errorCode());
    }
}
