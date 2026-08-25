<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Providers;

use App\Events\CompanyCreated;
use App\Modules\Accounting\Application\Listeners\ProvisionAccountingSettings;
use App\Modules\Accounting\Domain\Contracts\DocumentNumberingInterface;
use App\Modules\Accounting\Domain\Contracts\PdfRendererInterface;
use App\Modules\Accounting\Infrastructure\Services\DocumentNumberingService;
use App\Modules\Accounting\Infrastructure\Services\DocumentPdfRenderer;
        // Issue #5274 — démo exploitable en 1 clic (données vitrine, jamais réelles).
        $this->commands([
            SeedAccountingDemoCommand::class,
        ]);    }

    public function boot(): void
    {
        // Issue #5232 — défauts pays appliqués à la création d'entreprise.
        // Enregistrement local au module (Event::listen) pour ne pas toucher
        // EventServiceProvider central (isolation module, anti-collision).
        Event::listen(CompanyCreated::class, ProvisionAccountingSettings::class);
    }
}
