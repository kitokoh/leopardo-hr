<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Contracts\Queue\TenantScopedJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

/**
 * #7649 — garde d'architecture : tout job queued qui touche des données
 * tenant DOIT établir le contexte multi-tenant avant `handle()`.
 *
 * Les workers de queue sont des processus longue durée qui exécutent en
 * série des jobs de compagnies différentes. Sans `TenantScopedJob` +
 * `EnsureTenantContext`, un job qui ne filtre que par `company_id`
 * fonctionne « par hasard » en mode partagé, mais lit/écrit le mauvais
 * schéma dès qu'une compagnie passe en mode « schema » (search_path jamais
 * positionné pour ce job).
 *
 * Règle vérifiée : toute classe concrète de `app/` implémentant
 * `ShouldQueue` doit implémenter `TenantScopedJob` ET référencer
 * `EnsureTenantContext` dans sa méthode `middleware()`, SAUF :
 *
 *  - les Mailables et Notifications queued (le middleware de job ne
 *    s'applique pas à ces classes ; leur scoping tenant est porté par le
 *    code qui les dispatch) — exclusion par catégorie ;
 *  - les classes listées dans EXEMPTED, chacune avec une justification.
 *
 * Un nouveau job tenant sans middleware fait échouer ce test. Un job
 * réellement « plateforme » (hors tenant) doit être ajouté à EXEMPTED avec
 * une justification — décision explicite, relue en PR.
 *
 * Test volontairement SANS boot Laravel (pur PHPUnit + autoload composer) :
 * il ne dépend d'aucune base de données.
 */
class QueueTenantContextArchitectureTest extends TestCase
{
    /**
     * Classes queued volontairement SANS EnsureTenantContext, avec la raison.
     *
     * @var array<class-string, string>
     */
    private const EXEMPTED = [
        // Job PLATEFORME : la compagnie n'existe pas encore au dispatch —
        // c'est ce job qui la crée (ProvisionGuidedTrial établit lui-même le
        // contexte du tenant nouvellement provisionné).
        \App\Jobs\ProvisionDemoTenantJob::class => 'plateforme : provisionne le tenant, aucun contexte préexistant',

        // Listeners queued : l'événement transporte les modèles sérialisés
        // et les écritures sont filtrées par company_id. Dette connue pour le
        // mode « schema » (#7649) — à convertir en TenantScopedJob si un
        // tenant passe en isolation physique.
        \App\Listeners\AuditLogger::class => 'listener queued : écritures audit filtrées par company_id (dette mode schema)',
        \App\Listeners\WebhookListener::class => 'listener queued : lecture webhooks filtrée par company_id (dette mode schema)',

        // Listeners passés en file par #8206 (BOS-017) : l'événement
        // transporte le modèle sérialisé et les accès sont filtrés par
        // company_id — même dette « mode schema » que les listeners
        // ci-dessus (le middleware de job ne s'applique pas aux listeners
        // queued, CallQueuedListener ne relaie pas middleware()).
        \App\Listeners\NotifyTaxRateValidation::class => 'listener queued (#8206) : le handler établit déjà le contexte tenant via TenantManager::withinTenant() + PlatformCompanyLookup (#1994)',
        \App\Listeners\ProcessCommissionOnPayment::class => 'listener queued (#8206) : écritures commission filtrées par company_id du paiement (dette mode schema)',
        \App\Listeners\SendInvoicePaymentReceipt::class => 'listener queued (#8206) : lecture facture filtrée par company_id + envoi email, aucune écriture tenant (dette mode schema)',

        // Job PLATEFORME (#7649) : `integrations` n'a PAS de colonne
        // `company_id` (migration 2026_10_26_000001_8512, ligne 101) — la
        // table est globale au déploiement, une intégration GSC/GA4/Ads est
        // configurée une fois pour l'installation, pas par société. Le job
        // n'a donc aucun tenant à établir : `tenantCompanyId()` n'aurait
        // rien à retourner. Les tables qu'il écrit (`campaigns`,
        // `campaign_metrics`) sont, elles, tenant — mais il les atteint
        // toujours via une campagne déjà résolue, et le scoping y est porté
        // par `company_id` explicite (dette mode schema, comme les listeners
        // ci-dessus). Lui imposer EnsureTenantContext établirait un
        // `search_path` sans société, ce qui serait pire que l'absence de
        // contexte : une erreur silencieuse au lieu d'une erreur franche.
        \App\Modules\SearchIntelligence\Infrastructure\Jobs\SyncIntegrationMetrics::class => 'job plateforme (#7649) : `integrations` est globale (aucune colonne company_id), aucun tenant à établir',
    ];

    public function test_every_queued_job_touching_tenant_data_uses_ensure_tenant_context(): void
    {
        $violations = [];
        $seen = [];

        foreach ($this->queuedClasses() as $class) {
            $seen[] = $class;

            if (array_key_exists($class, self::EXEMPTED)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if (is_a($class, Mailable::class, true) || is_a($class, Notification::class, true)) {
                continue; // Exclusion par catégorie (voir docblock).
            }

            if (! $reflection->implementsInterface(TenantScopedJob::class)) {
                $violations[] = $class.' : implémente ShouldQueue sans TenantScopedJob';

                continue;
            }

            if (! $reflection->hasMethod('middleware')) {
                $violations[] = $class.' : TenantScopedJob sans méthode middleware()';

                continue;
            }

            $source = (string) file_get_contents((string) $reflection->getFileName());

            if (! str_contains($source, 'EnsureTenantContext')) {
                $violations[] = $class.' : middleware() ne référence pas EnsureTenantContext';
            }
        }

        $this->assertNotEmpty($seen, 'Aucune classe ShouldQueue trouvée sous app/ — le scan est cassé.');

        $this->assertSame(
            [],
            $violations,
            "Jobs queued sans contexte tenant (#7649) :\n - ".implode("\n - ", $violations)
            ."\nSoit le job est tenant → implémenter TenantScopedJob + middleware() [new EnsureTenantContext],"
            ."\nsoit il est réellement plateforme → l'ajouter à EXEMPTED avec une justification."
        );
    }

    public function test_exempted_list_stays_fresh(): void
    {
        foreach (self::EXEMPTED as $class => $reason) {
            $this->assertTrue(
                class_exists($class),
                "Exemption obsolète : {$class} n'existe plus — la retirer de EXEMPTED."
            );

            $this->assertFalse(
                (new ReflectionClass($class))->implementsInterface(TenantScopedJob::class),
                "Exemption obsolète : {$class} implémente désormais TenantScopedJob — la retirer de EXEMPTED."
            );
        }
    }

    /**
     * Toutes les classes concrètes de app/ implémentant ShouldQueue.
     *
     * @return list<class-string>
     */
    private function queuedClasses(): array
    {
        $appPath = dirname(__DIR__, 3).'/app';
        $classes = [];

        $finder = Finder::create()->files()->in($appPath)->name('*.php');

        foreach ($finder as $file) {
            $contents = $file->getContents();

            // Filtre rapide avant autoload : évite de charger tout app/.
            if (! str_contains($contents, 'ShouldQueue')) {
                continue;
            }

            $relative = str_replace('.php', '', $file->getRelativePathname());
            /** @var class-string $class */
            $class = 'App\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || $reflection->isInterface()) {
                continue;
            }

            if (! $reflection->implementsInterface(ShouldQueue::class)) {
                continue;
            }

            $classes[] = $class;
        }

        sort($classes);

        return $classes;
    }
}
