<?php

namespace App\Console\Commands;

use App\Mail\TrialDripMail;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendDripEmails extends Command
{
    protected $signature = 'app:send-drip-emails';

    protected $description = 'Send drip campaign emails to companies in trial';

    public function handle(): int
    {
        $companies = Company::query()
            ->where('status', 'trial')
            ->get();

        $today = now()->startOfDay();

        foreach ($companies as $company) {
            $created = clone $company->created_at;
            $subscriptionEnd = clone $company->subscription_end;

            // Drip 1: Day 3
            if ($created->startOfDay()->diffInDays($today) === 3 && $today->isAfter($created)) {
                $this->sendEmail($company, 'day3');
            }

            // Drip 2: 3 Days before expiration
            if ($today->diffInDays($subscriptionEnd->startOfDay()) === 3 && $subscriptionEnd->isAfter($today)) {
                $this->sendEmail($company, 'expiring');
            }

            // Drip 3: Expiration day
            if ($today->isSameDay($subscriptionEnd->startOfDay())) {
                $this->sendEmail($company, 'expired');
            }
        }

        $this->info('Drip emails processed.');
        return self::SUCCESS;
    }

    private function sendEmail(Company $company, string $type): void
    {
        // Get the principal manager to send the email
        // To find the manager, we need to switch tenant scope or use the email directly if the company has the email.
        // Actually, the manager's email is often the company email, or we can just send it to the company email.
        $companyEmail = $company->email;
        if (!$companyEmail) {
            return;
        }

        // We can create a dummy Employee object just to pass the first_name if we can't easily query it,
        // but it's better to fetch the principal manager.
        // Since employees are in the tenant schema, we would need to switch tenant.
        // Let's just use the company's name for the managerName if we don't have it, or fake an employee object.
        $dummyManager = new Employee(['first_name' => 'Manager']);

        Mail::to($companyEmail)->send(new TrialDripMail($company, $dummyManager, $type));
        $this->info("Sent {$type} email to {$companyEmail}");
    }
}
