<?php

declare(strict_types=1);

namespace Tests\Unit\Branding;

use App\Exceptions\DomainException;
use App\Modules\Branding\Domain\Exceptions\BrandHostException;
use App\Modules\Branding\Domain\Exceptions\BrandPublishException;
use App\Modules\Branding\Domain\Exceptions\BrandSlugException;
use PHPUnit\Framework\TestCase;

/**
 * #9661 — le contrat d'erreur des surfaces Branding.
 *
 * ## Le défaut mesuré
 *
 * `bootstrap/app.php` ne rend en JSON `{error, message, localized_message}`
 * qu'une seule classe : `App\Exceptions\DomainException` (#8247). Les trois
 * exceptions Branding héritaient du `\DomainException` **global** : un slug
 * invalide, un slug réservé ou une publication sans `identity.display_name`
 * renvoyaient donc **500 Server Error** à l'appelant, alors que les quatre
 * catalogues `lang/<locale>/errors.php` contenaient déjà leur traduction — preuve que
 * l'intention était bien un 422.
 *
 * Ces tests verrouillent les deux moitiés du contrat : la classe de base (donc
 * le renderer) ET le code stable (`error`), sans quoi un client ne peut pas
 * distinguer « slug réservé » de « nom manquant ».
 */
final class BrandingErrorContractTest extends TestCase
{
    public function test_slug_exceptions_are_rendered_as_client_errors(): void
    {
        $this->assertContract(BrandSlugException::invalid('!!!'), 'BRAND_SLUG_INVALID');
        $this->assertContract(BrandSlugException::reserved('admin'), 'BRAND_SLUG_RESERVED');
    }

    public function test_publish_exceptions_are_rendered_as_client_errors(): void
    {
        $this->assertContract(
            BrandPublishException::missingDisplayName(),
            'BRAND_PUBLISH_MISSING_DISPLAY_NAME',
        );
        $this->assertContract(BrandPublishException::suspended(), 'BRAND_PUBLISH_SUSPENDED');
    }

    public function test_host_exception_is_rendered_as_a_client_error(): void
    {
        $this->assertContract(BrandHostException::invalid('chez_lea.sn'), 'BRAND_HOST_INVALID');
    }

    /**
     * Le message brut reste diagnosticable en log : il garde le code en tête et
     * la valeur fautive, même si le client ne reçoit que la traduction.
     */
    public function test_raw_message_keeps_the_code_and_the_offending_value(): void
    {
        $exception = BrandSlugException::reserved('admin');

        $this->assertStringContainsString('BRAND_SLUG_RESERVED', $exception->getMessage());
        $this->assertStringContainsString('admin', $exception->getMessage());
    }

    private function assertContract(DomainException $exception, string $expectedCode): void
    {
        // La classe de base décide du rendu : hors de cet héritage, la réponse
        // est un 500 générique et la traduction n'est jamais atteinte.
        $this->assertSame(422, $exception->statusCode());
        $this->assertSame($expectedCode, $exception->errorCode());
        $this->assertStringStartsWith($expectedCode, $exception->getMessage());
    }
}
