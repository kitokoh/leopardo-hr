<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Providers;

use App\Modules\Recruitment\Application\Services\ResumeScanService;
use App\Modules\Recruitment\Domain\Contracts\ResumeScanner;
use App\Modules\Recruitment\Infrastructure\Antivirus\ClamAvResumeScanner;
use App\Modules\Recruitment\Infrastructure\Antivirus\NullResumeScanner;
use Illuminate\Support\ServiceProvider;

class RecruitmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // BC-10 (#9217) — le scanner est résolu par la configuration, pas par
        // le contrôleur : changer de moteur (ou passer à `none`) ne doit pas
        // toucher au flux de candidature.
        $this->app->singleton(ResumeScanner::class, function (): ResumeScanner {
            $driver = (string) config('recruitment.antivirus.driver', 'none');

            return match ($driver) {
                'clamav' => new ClamAvResumeScanner(
                    host: (string) config('recruitment.antivirus.host', '127.0.0.1'),
                    port: (int) config('recruitment.antivirus.port', 3310),
                    timeoutSeconds: (int) config('recruitment.antivirus.timeout', 10),
                ),
                // Tout driver inconnu retombe sur `none`, qui rend
                // `unavailable` — jamais `clean`. Une faute de frappe dans une
                // variable d'environnement ne doit pas désactiver silencieusement
                // la sécurité en prétendant que les fichiers sont sains.
                default => new NullResumeScanner,
            };
        });

        $this->app->singleton(ResumeScanService::class, fn ($app): ResumeScanService => new ResumeScanService(
            scanner: $app->make(ResumeScanner::class),
            policy: (string) config('recruitment.antivirus.policy', ResumeScanService::POLICY_REJECT),
        ));
    }

    public function boot(): void {}
}
