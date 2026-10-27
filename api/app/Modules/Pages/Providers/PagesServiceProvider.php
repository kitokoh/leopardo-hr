<?php

declare(strict_types=1);

namespace App\Modules\Pages\Providers;

use App\Modules\Pages\Domain\Blocks\PageBlockProvider;
use App\Modules\Pages\Domain\Blocks\PageBlockRegistry;
use App\Modules\Pages\Domain\Blocks\PageBlockSchemaValidator;
use App\Modules\Pages\Domain\CustomDomains\Contracts\CustomDomainProvider;
use App\Modules\Pages\Domain\CustomDomains\Dns\DnsResolver;
use App\Modules\Pages\Domain\CustomDomains\Dns\NativeDnsResolver;
use App\Modules\Pages\Domain\CustomDomains\Providers\InMemoryCustomDomainProvider;
use App\Modules\Pages\Domain\Services\PageCustomDomainService;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider du BC PAGES (BC PAGES, #8723-#8731).
 *
 * ## Pourquoi le registre de blocs est un singleton paresseux
 *
 * Construire le registre coûte peu, mais le construire **à chaque injection**
 * serait une faute de conception : un fournisseur pourrait alors enregistrer
 * deux fois, et deux résolutions dans la même requête pourraient voir deux
 * catalogues différents. En `singleton`, il est construit une fois par requête
 * et figé — ce qui est l'invariant dont dépend la validation (#8724).
 *
 * ## Le tag `pages.block_providers`
 *
 * C'est le point d'extension : un BC vertical enregistre son fournisseur dans
 * SON propre service provider avec
 * `$this->app->tag([RestaurantBlocks::class], 'pages.block_providers')`, sans
 * que Pages ne connaisse sa classe. Pages ne fait que résoudre le tag. Si un
 * vertical n'est pas installé, le tag est vide et le registre ne contient que
 * `core.*` — aucun `class_exists`, aucune dépendance dure.
 *
 * Le registre n'est résolu par le conteneur que lorsque quelque chose en a
 * besoin : une requête qui ne touche pas aux Pages ne paie pas la construction
 * du catalogue.
 */
final class PagesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PageBlockSchemaValidator::class, static fn (): PageBlockSchemaValidator => new PageBlockSchemaValidator);

        $this->app->singleton(PageBlockRegistry::class, function ($app): PageBlockRegistry {
            /** @var iterable<PageBlockProvider> $tagged */
            $tagged = $app->tagged('pages.block_providers');

            /** @var list<PageBlockProvider> $providers */
            $providers = [];
            foreach ($tagged as $provider) {
                $providers[] = $provider;
            }

            return PageBlockRegistry::build($providers);
        });

        // ── Domaines personnalisés (#8726) ────────────────────────────────
        //
        // Le résolveur DNS et l'adaptateur fournisseur sont des singletons :
        // les lier en `bind` créerait une instance par injection, donc un
        // journal d'opérations fournisseur différent à chaque résolution —
        // impossible à asserter en test.
        $this->app->singleton(DnsResolver::class, static fn (): DnsResolver => new NativeDnsResolver);

        $this->app->singleton(CustomDomainProvider::class, function ($app): CustomDomainProvider {
            /** @var string $driver */
            $driver = $app['config']->get('pages.custom_domains.provider', 'memory');

            // Aucun adaptateur réseau n'est branché à ce stade : le choix
            // Vercel/Cloudflare est une décision produit (cf. #8726). En
            // attendant, tout nom de fournisseur inconnu retombe sur l'adaptateur
            // mémoire, qui ne sert JAMAIS de trafic réel — fail-closed.
            return match ($driver) {
                'memory' => new InMemoryCustomDomainProvider,
                default => new InMemoryCustomDomainProvider($driver, false),
            };
        });

        // #6568 — le Domain n'importe pas la facade `DB` : la transaction
        // passe par un port, cet adaptateur est le seul a connaitre Eloquent.
        $this->app->singleton(\App\Shared\Contracts\Persistence\TransactionRunner::class, \App\Shared\Support\Persistence\LaravelTransactionRunner::class);

        $this->app->bind(PageCustomDomainService::class, function ($app): PageCustomDomainService {
            return new PageCustomDomainService(
                $app->make(DnsResolver::class),
                $app->make(CustomDomainProvider::class),
                $app->make(\App\Shared\Contracts\Entitlements\FeatureEntitlements::class),
                $app->make(\App\Shared\Contracts\Persistence\TransactionRunner::class),
            );
        });
    }
}
