<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Domain\Enums\CrmMergeEntityType;
use App\Modules\CRM\Domain\Exceptions\CrmMergeException;
use App\Modules\CRM\Domain\Models\CrmAccount;
use App\Modules\CRM\Domain\Models\CrmContact;
use App\Modules\CRM\Domain\Models\CrmLead;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * #5718 — Déduplication et fusion SUPERVISÉE (jamais automatique).
 *
 *  - `suggestions()` : paires explicables (email normalisé identique ou
 *    nom similaire), scopées au tenant, jamais cross-tenant.
 *  - `preview()` : diff de champs + relations à transférer, AUCUNE écriture.
 *  - `merge()` : fusion transactionnelle — champs gagnants complétés par
 *    le perdant, relations transférées, perdant ARCHIVÉ (jamais supprimé,
 *    rollback logique possible), audit `crm.merge.*`.
 */
final class CrmDeduplicationService
{
    public const MAX_SUGGESTIONS = 50;

    public const DEFAULT_SUGGESTIONS = 20;

    /** Distance de Levenshtein maximale pour « noms similaires ». */
    public const NAME_SIMILARITY_THRESHOLD = 2;

    /** @var array<string, list<string>> */
    private const FIELD_MAPS = [
        'accounts' => ['name', 'email', 'phone', 'notes', 'status', 'owner_id'],
        'contacts' => ['first_name', 'last_name', 'email', 'phone', 'title', 'notes'],
        'leads' => ['first_name', 'last_name', 'company_name', 'email', 'phone', 'source', 'status', 'notes'],
    ];

    /**
     * Suggestions de doublons, explicables et bornées.
     *
     * @return list<array{
     *     entity: string,
     *     reason: string,
     *     score: float,
     *     winner: array{id: int, label: string},
     *     loser: array{id: int, label: string}
     * }>
     */
    public function suggestions(CrmMergeEntityType $entity, string $companyId, int $limit): array
    {
        $limit = min(max($limit, 1), self::MAX_SUGGESTIONS);

        $rows = $this->model($entity)::query()
            ->where('company_id', $companyId)
            ->whereNull($this->archiveColumn($entity))
            ->get(['id', ...$this->fieldMap($entity)]);

        if ($rows->count() < 2) {
            return [];
        }

        $suggestions = [];

        foreach ($rows as $i => $rowA) {
            foreach ($rows->slice($i + 1) as $rowB) {
                $emailA = $this->emailOf($entity, $rowA);
                $emailB = $this->emailOf($entity, $rowB);

                $match = null;
                if ($emailA !== null && $emailB !== null && $emailA === $emailB) {
                    $match = ['reason' => 'same_email', 'score' => 0.95];
                } else {
                    $nameA = $this->normalize($this->labelOf($entity, $rowA));
                    $nameB = $this->normalize($this->labelOf($entity, $rowB));

                    if ($nameA !== '' && $nameB !== '' && levenshtein($nameA, $nameB) <= self::NAME_SIMILARITY_THRESHOLD) {
                        $match = ['reason' => 'similar_name', 'score' => 0.7];
                    }
                }

                if ($match === null) {
                    continue;
                }

                $suggestions[] = [
                    'entity' => $entity->value,
                    'reason' => $match['reason'],
                    'score' => $match['score'],
                    'winner' => ['id' => (int) $rowA->getAttribute('id'), 'label' => $this->labelOf($entity, $rowA)],
                    'loser' => ['id' => (int) $rowB->getAttribute('id'), 'label' => $this->labelOf($entity, $rowB)],
                ];

                if (count($suggestions) >= $limit) {
                    return $suggestions;
                }
            }
        }

        return $suggestions;
    }

    /**
     * Preview de fusion — AUCUNE écriture.
     *
     * @return array{
     *     entity: string,
     *     winner: array{id: int, label: string},
     *     loser: array{id: int, label: string},
     *     field_updates: list<array{field: string, value: mixed}>,
     *     relations_to_transfer: array<string, int>,
     *     will_archive_loser: bool
     * }
     */
    public function preview(CrmMergeEntityType $entity, int $winnerId, int $loserId, string $companyId): array
    {
        $winner = $this->findScoped($entity, $winnerId, $companyId);
        $loser = $this->findScoped($entity, $loserId, $companyId);

        $fieldUpdates = [];
        foreach ($this->fieldMap($entity) as $field) {
            if ($this->blank($winner->getAttribute($field)) && ! $this->blank($loser->getAttribute($field))) {
                $fieldUpdates[] = ['field' => $field, 'value' => $loser->getAttribute($field)];
            }
        }

        $relations = [];
        if ($entity === CrmMergeEntityType::Accounts) {
            $relations['contacts'] = CrmContact::query()
                ->where('company_id', $companyId)
                ->where('account_id', $loserId)
                ->count();
        }

        return [
            'entity' => $entity->value,
            'winner' => ['id' => (int) $winner->getAttribute('id'), 'label' => $this->labelOf($entity, $winner)],
            'loser' => ['id' => (int) $loser->getAttribute('id'), 'label' => $this->labelOf($entity, $loser)],
            'field_updates' => $fieldUpdates,
            'relations_to_transfer' => $relations,
            'will_archive_loser' => true,
        ];
    }

    /**
     * Fusion supervisée (permission élevée vérifiée au contrôleur).
     *
     * @return array<string, mixed>
     */
    public function merge(CrmMergeEntityType $entity, int $winnerId, int $loserId, Employee $actor): array
    {
        if ($winnerId === $loserId) {
            throw CrmMergeException::sameEntity();
        }

        $result = [];

        $companyId = $this->companyId($actor);

        DB::transaction(function () use ($entity, $winnerId, $loserId, $companyId, $actor, &$result): void {
            $winner = $this->findScoped($entity, $winnerId, $companyId);
            $loser = $this->findScoped($entity, $loserId, $companyId);

            $oldValues = $loser->only($this->fieldMap($entity));

            // 1. Champs : le gagnant conserve ses valeurs ; le perdant
            //    complète les champs vides du gagnant.
            $updatedFields = [];
            foreach ($this->fieldMap($entity) as $field) {
                if ($this->blank($winner->getAttribute($field)) && ! $this->blank($loser->getAttribute($field))) {
                    $winner->setAttribute($field, $loser->getAttribute($field));
                    $updatedFields[] = $field;
                }
            }
            $winner->save();

            // 2. Relations : contacts du compte perdant → compte gagnant.
            $transferred = 0;
            if ($entity === CrmMergeEntityType::Accounts) {
                $transferred = CrmContact::query()
                    ->where('company_id', $actor->company_id)
                    ->where('account_id', $loserId)
                    ->update(['account_id' => $winnerId]);
            }

            // 3. Le perdant est ARCHIVÉ (jamais supprimé — rollback logique).
            $loser->forceFill([$this->archiveColumn($entity) => now()])->save();

            // 4. Audit complet (avant/après) — conservation historique.
            AuditLog::create([
                'company_id' => $actor->company_id,
                'user_id' => $actor->id,
                'action' => "crm.merge.{$entity->value}",
                'module' => 'crm',
                'auditable_type' => $winner->getMorphClass(),
                'auditable_id' => (int) $winner->getAttribute('id'),
                'old_values' => ['loser_id' => $loserId, 'loser_values' => $oldValues],
                'new_values' => [
                    'winner_id' => $winnerId,
                    'updated_fields' => $updatedFields,
                    'transferred_contacts' => $transferred,
                    'archived_loser' => true,
                ],
            ]);

            $result = [
                'entity' => $entity->value,
                'winner_id' => (int) $winner->getAttribute('id'),
                'loser_id' => (int) $loser->getAttribute('id'),
                'updated_fields' => $updatedFields,
                'transferred_contacts' => $transferred,
                'archived_loser' => true,
            ];
        });

        return $result;
    }

    /**
     * Identifiant du tenant obligatoire (fusion scopée) — 404 sûr, jamais
     * de fusion cross-tenant possible sans compagnie courante.
     */
    private function companyId(Employee $actor): string
    {
        return $actor->company_id ?? throw CrmMergeException::crossTenant();
    }

    /**
     * @return class-string<Model>
     */
    private function model(CrmMergeEntityType $entity): string
    {
        return match ($entity) {
            CrmMergeEntityType::Accounts => CrmAccount::class,
            CrmMergeEntityType::Contacts => CrmContact::class,
            CrmMergeEntityType::Leads => CrmLead::class,
        };
    }

    /**
     * @return list<string>
     */
    private function fieldMap(CrmMergeEntityType $entity): array
    {
        return self::FIELD_MAPS[$entity->value];
    }

    private function findScoped(CrmMergeEntityType $entity, int $id, string $companyId): Model
    {
        $model = $this->model($entity)::query()
            ->where('company_id', $companyId)
            ->find($id);

        if (! $model instanceof Model) {
            throw CrmMergeException::notFound(strtoupper($entity->value));
        }

        return $model;
    }

    /**
     * Colonne d'archivage par entité : `archived_at` (accounts/contacts) ou
     * `deleted_at` (leads — softDeletes de la migration #5709).
     */
    private function archiveColumn(CrmMergeEntityType $entity): string
    {
        return $entity === CrmMergeEntityType::Leads ? 'deleted_at' : 'archived_at';
    }

    private function emailOf(CrmMergeEntityType $entity, Model $row): ?string
    {
        $email = $row->getAttribute('email');

        if (! is_string($email) || trim($email) === '') {
            return null;
        }

        return mb_strtolower(trim($email));
    }

    private function labelOf(CrmMergeEntityType $entity, Model $row): string
    {
        return match ($entity) {
            CrmMergeEntityType::Accounts => (string) ($row->getAttribute('name') ?? ''),
            CrmMergeEntityType::Contacts, CrmMergeEntityType::Leads => trim(
                (string) ($row->getAttribute('first_name') ?? '')
                .' '
                .(string) ($row->getAttribute('last_name') ?? '')
            ),
        };
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        $value = str_replace(['.', ',', '-', '_', "'", '’'], '', $value);

        return trim($value);
    }

    private function blank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}
