<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\CRM\Domain\Contracts\SegmentContactSourceInterface;
use App\Modules\CRM\Domain\Models\CrmSegment;
use App\Modules\CRM\Domain\Models\CrmSegmentMember;
use App\Modules\CRM\Domain\Models\CrmSegmentVersion;
use App\Modules\CRM\Domain\Support\SegmentDefinitionValidator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Segments CRM — Issue #5723.
 *
 * - définition strictement versionnée (SegmentDefinitionValidator, aucun SQL
 *   utilisateur) : chaque mise à jour acceptée fige la version précédente
 *   dans `crm_segment_versions` (snapshot reproductible) ;
 * - rebuild : remplace les membres `computed` depuis la source
 *   (SegmentContactSourceInterface), préserve les membres `manual`, audite ;
 * - appartenance tenant-scoped (BelongsToCompany) ;
 * - toute mutation sensible est auditée (`audit_logs`, module crm).
 */
final class SegmentService
{
    public function __construct(
        private readonly SegmentDefinitionValidator $validator,
        private readonly SegmentContactSourceInterface $source,
    ) {}

    /**
     * @param  array<string, mixed>  $definition
     */
    public function create(string $name, ?string $description, array $definition, ?int $actorId): CrmSegment
    {
        $validated = $this->validator->validate($definition);

        /** @var CrmSegment $segment */
        $segment = CrmSegment::query()->create([
            'name' => $name,
            'description' => $description,
            'definition' => $validated,
            'version' => 1,
            'is_active' => true,
            'created_by' => $actorId,
        ]);

        $this->storeVersion($segment, 1, $validated, $actorId);
        $this->audit($segment, 'segment.created', ['version' => 1]);

        return $segment;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public function update(CrmSegment $segment, string $name, ?string $description, array $definition, ?int $actorId): CrmSegment
    {
        $validated = $this->validator->validate($definition);

        $nextVersion = $segment->version + 1;
        $segment->update([
            'name' => $name,
            'description' => $description,
            'definition' => $validated,
            'version' => $nextVersion,
        ]);

        $this->storeVersion($segment, $nextVersion, $validated, $actorId);
        $this->audit($segment, 'segment.updated', [
            'version' => $nextVersion,
            'previous_version' => $segment->version - 1,
        ]);

        return $segment->refresh();
    }

    public function toggleActive(CrmSegment $segment, bool $isActive, ?int $actorId): CrmSegment
    {
        $segment->update(['is_active' => $isActive]);
        $this->audit($segment, 'segment.toggled', ['is_active' => $isActive]);

        return $segment->refresh();
    }

    /**
     * Reconstruit les membres `computed` depuis la définition.
     * No-op documenté si la source est indisponible (tables CRM absentes).
     */
    public function rebuild(CrmSegment $segment, ?int $actorId): CrmSegment
    {
        $definition = $segment->definition;

        if (! $this->source->supports($definition)) {
            throw ValidationException::withMessages([
                'segment' => 'Source de contacts CRM indisponible (tables CRM pas encore migrées).',
            ]);
        }

        $ids = $this->source->matchingContactIds($definition);

        DB::transaction(function () use ($segment, $ids): void {
            CrmSegmentMember::query()
                ->where('segment_id', $segment->id)
                ->where('source', 'computed')
                ->delete();

            $now = now();
            foreach ($ids as $contactId) {
                CrmSegmentMember::query()->create([
                    'segment_id' => $segment->id,
                    'contact_id' => $contactId,
                    'source' => 'computed',
                    'built_at' => $now,
                ]);
            }
        });

        $this->audit($segment, 'segment.rebuilt', [
            'version' => $segment->version,
            'members_computed' => count($ids),
        ]);

        return $segment->refresh();
    }

    public function addMember(CrmSegment $segment, int $contactId, ?int $actorId): CrmSegmentMember
    {
        $member = CrmSegmentMember::query()->firstOrCreate(
            [
                'segment_id' => $segment->id,
                'contact_id' => $contactId,
            ],
            [
                'source' => 'manual',
                'built_at' => now(),
            ],
        );

        if ($member->source !== 'manual') {
            $member->update(['source' => 'manual', 'built_at' => now()]);
        }

        $this->audit($segment, 'segment.member_added', ['contact_id' => $contactId]);

        return $member;
    }

    public function removeMember(CrmSegment $segment, int $contactId, ?int $actorId): void
    {
        CrmSegmentMember::query()
            ->where('segment_id', $segment->id)
            ->where('contact_id', $contactId)
            ->delete();

        $this->audit($segment, 'segment.member_removed', ['contact_id' => $contactId]);
    }

    public function destroy(CrmSegment $segment, ?int $actorId): void
    {
        $this->audit($segment, 'segment.deleted', ['name' => $segment->name]);

        CrmSegmentMember::query()->where('segment_id', $segment->id)->delete();
        CrmSegmentVersion::query()->where('segment_id', $segment->id)->delete();
        $segment->delete();
    }

    /**
     * @param  array{operator: string, conditions: list<array{field: string, op: string, value: mixed}>}  $definition
     */
    private function storeVersion(CrmSegment $segment, int $version, array $definition, ?int $actorId): void
    {
        CrmSegmentVersion::query()->create([
            'segment_id' => $segment->id,
            'version' => $version,
            'definition' => $definition,
            'changed_by' => $actorId,
            'changed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $newValues
     */
    private function audit(CrmSegment $segment, string $action, array $newValues): void
    {
        $userId = Auth::guard('sanctum')->id();

        AuditLog::create([
            'company_id' => $segment->company_id,
            'user_id' => $userId !== null ? (int) $userId : null,
            'action' => $action,
            'module' => 'crm',
            'auditable_type' => CrmSegment::class,
            'auditable_id' => $segment->id,
            'new_values' => $newValues,
            'metadata' => [
                'segment_id' => $segment->id,
                'name' => $segment->name,
                'version' => $segment->version,
            ],
        ]);
    }
}
