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
