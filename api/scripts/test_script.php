<?php
use App\Core\Tenant\Domain\Models\Company;
$company = Company::factory()->create(['referrer_partner_id' => 123]);
echo "referrer_partner_id: " . ($company->referrer_partner_id ?? 'null') . "\n";

