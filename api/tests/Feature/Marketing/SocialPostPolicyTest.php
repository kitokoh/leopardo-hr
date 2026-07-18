<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Marketing\Domain\Models\SocialAccount;
use App\Modules\Marketing\Domain\Models\SocialPost;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

class SocialPostPolicyTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makePost(string $companyId, string $status = SocialPost::STATUS_DRAFT): SocialPost
    {
        $account = SocialAccount::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'provider' => 'ayrshare',
            'provider_profile_ref' => 'profile-key-xyz',
            'status' => 'active',
        ]);

        return SocialPost::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'social_account_id' => $account->id,
            'content' => 'Contenu de test',
            'target_platforms' => ['linkedin'],
            'status' => $status,
        ]);
    }

    public function test_marketing_manager_can_create_update_delete_draft(): void
    {
        $company = Company::factory()->create();
        $post = $this->makePost($company->id);

        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'marketing',
        ]);

        $this->assertTrue($manager->can('viewAny', SocialPost::class));
        $this->assertTrue($manager->can('create', SocialPost::class));
        $this->assertTrue($manager->can('view', $post));
        $this->assertTrue($manager->can('update', $post));
        $this->assertTrue($manager->can('delete', $post));
        $this->assertTrue($manager->can('publish', $post));
    }

    public function test_cannot_update_or_delete_published_post(): void
    {
        $company = Company::factory()->create();
        $post = $this->makePost($company->id, SocialPost::STATUS_PUBLISHED);

        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'marketing',
        ]);

        $this->assertFalse($manager->can('update', $post));
        $this->assertFalse($manager->can('delete', $post));
        $this->assertFalse($manager->can('publish', $post));
    }

    public function test_non_marketing_manager_cannot_manage_posts(): void
    {
        $company = Company::factory()->create();
        $post = $this->makePost($company->id);

        $deptManager = Employee::factory()->managerDept()->create(['company_id' => $company->id]);

        $this->assertFalse($deptManager->can('create', SocialPost::class));
        $this->assertFalse($deptManager->can('view', $post));
    }

    public function test_employee_cannot_manage_posts(): void
    {
        $company = Company::factory()->create();
        $post = $this->makePost($company->id);

        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $this->assertFalse($employee->can('viewAny', SocialPost::class));
        $this->assertFalse($employee->can('create', SocialPost::class));
    }
}
