<?php

declare(strict_types=1);

namespace Tests\Unit\Branding;

use App\Modules\Branding\Domain\Enums\BrandStatus;
use App\Modules\Branding\Domain\Exceptions\BrandPublishException;
use App\Modules\Branding\Domain\Exceptions\BrandSlugException;
use App\Modules\Branding\Domain\Models\Brand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * #9578 — invariants de l'agrégat Brand.
 *
 * Ces tests n'ont pas besoin de base : les règles testées (slug, transitions,
 * locales, identité) sont des fonctions pures du modèle. Les invariants qui
 * dépendent du SCHÉMA (unicité du défaut, unicité du host) sont couverts par
 * les tests de feature, parce que c'est la base qui les garantit.
 */
/**
 * #9578 — transitions de statut.
 */
final class BrandStatusTest extends TestCase
{
    public function test_only_published_is_publicly_visible(): void
    {
        $this->assertTrue(BrandStatus::Published->isPubliclyVisible());
        $this->assertFalse(BrandStatus::Draft->isPubliclyVisible());
        $this->assertFalse(BrandStatus::Suspended->isPubliclyVisible());
    }

    /**
     * Une Brand suspendue ne se republie pas directement : elle doit repasser
     * par un brouillon conscient. C'est ce qui évite qu'une levée de
     * suspension remette en ligne du contenu non revu.
     */
    public function test_suspended_cannot_be_published_directly(): void
    {
        $this->assertFalse(BrandStatus::Suspended->canTransitionTo(BrandStatus::Published));
        $this->assertTrue(BrandStatus::Suspended->canTransitionTo(BrandStatus::Draft));
    }

    public function test_draft_can_be_published(): void
    {
        $this->assertTrue(BrandStatus::Draft->canTransitionTo(BrandStatus::Published));
    }

    public function test_forbidden_transition_raises(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('BRAND_STATUS_TRANSITION_FORBIDDEN');

        BrandStatus::Suspended->assertCanTransitionTo(BrandStatus::Published);
    }
}

/**
 * #9578 — publication et suspension sur l'agrégat.
 */
