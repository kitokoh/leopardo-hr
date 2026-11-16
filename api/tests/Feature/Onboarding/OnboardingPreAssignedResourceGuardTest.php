<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\Site;
use App\Modules\HR\Domain\Models\UserInvitation;
use App\Modules\HR\Infrastructure\Services\UserInvitationService;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Garde fail-closed de la PRE-ASSIGNATION de ressource a l'invitation.
 *
 * `UserInvitationService::createAndSend()` accepte une liste
 * `resource_assignments` (R4 de l'epique #7597) : les acces sont portes par
 * l'invitation puis materialises a l'activation. Le service refuse
 * explicitement (`abort_unless(..., 422)`) toute ressource qui n'existe pas
 * pour l'entreprise — sans cette garde, un manager pourrait pre-assigner un
 * identifiant d'une AUTRE entreprise et l'acces serait ecrit a l'activation.
 *
 * Aucun test n'exercait ce refus : `OnboardingActivationConvergenceTest` couvre
 * la materialisation d'une ressource VALIDE, jamais le rejet d'une invalide.
 *
 * Ce fichier verrouille :
 *  - le refus 422 d'une ressource inexistante ;
 *  - le refus 422 d'une ressource appartenant a une AUTRE entreprise
 *    (isolation inter-tenant du geste de pre-assignation) ;
 *  - l'absence d'effet de bord : ni employe cree, ni invitation emise.
 *
 * Aucun code applicatif n'est modifie.
 */
class OnboardingPreAssignedResourceGuardTest extends TestCase
{
    use RefreshTenantDatabase;

    protected Company $company;

    protected Employee $principal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['status' => 'active']);
        $this->principal = Employee::factory()->manager()->create([
            'company_id' => $this->company->id,
        ]);
    }

    /**
     * @param  list<array{resource_type: string, resource_id: int, access_level: string}>  $assignments
     */
    private function inviteWithAssignments(Employee $employee, array $assignments): void
    {
        app(UserInvitationService::class)->createAndSend(
            company: $this->company,
            employee: $employee,
            invitedByType: 'manager',
            invitedByEmail: $this->principal->email,
            resourceAssignments: $assignments,
        );
    }

    public function test_a_nonexistent_resource_is_refused_with_422(): void
    {
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        try {
            $this->inviteWithAssignments($employee, [
                ['resource_type' => 'site', 'resource_id' => 999999, 'access_level' => 'view'],
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());

            // Aucune invitation ne doit avoir ete emise pour cet employe.
            $this->assertSame(
                0,
                UserInvitation::query()->where('employee_id', $employee->id)->count(),
                'Un refus de pre-assignation ne doit laisser aucune invitation derriere lui.',
            );

            throw $e;
        }
    }

    /**
     * Isolation inter-tenant : une ressource reelle mais appartenant a une
     * AUTRE entreprise doit etre refusee exactement comme une ressource
     * inexistante — sinon la pre-assignation devient un oracle d'existence
     * et, a l'activation, une fuite d'acces cross-tenant.
     */
    public function test_a_resource_of_another_company_is_refused_with_422(): void
    {
        $otherCompany = Company::factory()->create(['status' => 'active']);
        $foreignSite = Site::query()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Etablissement Etranger',
            'code' => 'SITE-FOREIGN',
            'status' => Site::STATUS_ACTIVE,
        ]);

        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        try {
            $this->inviteWithAssignments($employee, [
                ['resource_type' => 'site', 'resource_id' => (int) $foreignSite->id, 'access_level' => 'manage'],
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertSame(
                0,
                UserInvitation::query()->where('employee_id', $employee->id)->count(),
                'Une ressource etrangere ne doit produire aucune invitation.',
            );

            throw $e;
        }
    }

    /**
     * Contre-epreuve : une ressource VALIDE du tenant est acceptee et portee
     * par l'invitation. Sans ce test, les deux refus ci-dessus pourraient
     * passer en refusant TOUT.
     */
    public function test_a_valid_resource_of_the_tenant_is_accepted(): void
    {
        $site = Site::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Etablissement Principal',
            'code' => 'SITE-OK',
            'status' => Site::STATUS_ACTIVE,
        ]);

        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->inviteWithAssignments($employee, [
            ['resource_type' => 'site', 'resource_id' => (int) $site->id, 'access_level' => 'manage'],
        ]);

        $invitation = UserInvitation::query()
            ->where('company_id', $this->company->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        // `metadata` est un cast `array` : l'ordre des cles n'est pas garanti
        // apres l'aller-retour JSON, on verifie donc les valeurs une a une.
        $stored = $invitation->metadata['resource_assignments'] ?? null;

        $this->assertIsArray($stored);
        $this->assertCount(1, $stored);
        $this->assertSame('site', $stored[0]['resource_type']);
        $this->assertSame((int) $site->id, (int) $stored[0]['resource_id']);
        $this->assertSame('manage', $stored[0]['access_level']);
    }
}
