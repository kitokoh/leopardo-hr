<?php

declare(strict_types=1);

namespace App\Modules\Pages\Infrastructure\Services;

use App\Modules\Pages\Domain\Models\Page;
use App\Modules\Pages\Domain\Models\PageModerationAudit;
use Illuminate\Http\Request;

/**
 * Écriture de l'audit de modération plateforme (BC PAGES, #8729).
 *
 * ## Pourquoi l'audit n'est pas écrit par les actions
 *
 * #8729 pose un invariant : **aucune décision de modération sans ligne
 * d'audit**. Si chaque action écrivait sa propre ligne, la cinquième action
 * ajoutée dans six mois l'oublierait — et un accès à une pièce justificative
 * non tracé ne se voit pas en production, il se constate en audit. L'écriture
 * est donc ici, en un seul endroit, appelée par les trois actions de
 * modération et par la lecture des pièces.
 *
 * ## Pourquoi `Infrastructure` et pas `Application`
 *
 * C'est de la persistance, pas de la décision : l'action décide *quoi* tracer,
 * ce service écrit *comment*. La garde `check-application-layer-placement.sh`
 * (#6571) refuse d'ailleurs tout `*Service.php` sous `Application/`. Le
 * placer là aurait rendu la garde rouge pour un fichier qui ne fait
 * effectivement que du stockage.
 *
 * ## Ce que l'audit ne contient pas
 *
 * Ni contenu de pièce, ni code SMS, ni donnée personnelle du tenant. L'état
 * avant/après se limite aux champs de statut — de quoi reconstituer la
 * décision, pas de quoi reconstituer le dossier.
 */
final class PageModerationAuditWriter
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function write(
        Page $page,
        string $actorId,
        ?string $actorRole,
        string $action,
        string $reason,
        ?array $before,
        ?array $after,
        ?Request $request = null,
    ): PageModerationAudit {
        /** @var PageModerationAudit $audit */
        $audit = PageModerationAudit::query()->create([
            'company_id' => $page->company_id,
            'page_id' => $page->id,
            'actor_id' => $actorId,
            'actor_role' => $actorRole,
            'action' => $action,
            'reason' => $reason,
            'before' => $before,
            'after' => $after,
            'request_id' => function_exists('correlation_id') ? correlation_id() : null,
            'ip_address' => $request?->ip(),
            'created_at' => now(),
        ]);

        return $audit;
    }

    /**
     * Photographie des champs de visibilité d'une Page.
     *
     * Volontairement limitée aux champs de statut : un audit qui embarque le
     * contenu de la Page devient une copie de la base dans la base.
     *
     * @return array<string, mixed>
     */
    public function snapshot(Page $page): array
    {
        return [
            'status' => $page->status->value,
            'published_at' => $page->published_at?->toIso8601String(),
            'verification_status' => $page->verification_status->value,
            'suspended_at' => $page->suspended_at?->toIso8601String(),
            'suspension_reason' => $page->suspension_reason,
        ];
    }
}
