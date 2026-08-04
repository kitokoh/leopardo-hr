<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Billing\Application\Actions\ProvisionGuidedTrial;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProvisionDemoTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $email,
        public readonly string $companyName,
    ) {}

    public function handle(ProvisionGuidedTrial $provisioner): void
    {
        Log::info('ProvisionDemoTenantJob started', ['company_name' => $this->companyName, 'email' => $this->email]);

        try {
            $result = $provisioner->execute($this->email, $this->companyName);
            
            // TODO: Generate magic link and send email to the user
            Log::info('Sandbox provisioned successfully', ['company_id' => $result['company']->id]);
            
        } catch (\Throwable $e) {
            Log::error('Failed to provision sandbox', ['error' => $e->getMessage()]);
        }
    }
}
