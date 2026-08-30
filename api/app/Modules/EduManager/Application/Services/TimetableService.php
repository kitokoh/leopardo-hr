<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Application\Services;

use App\Core\Tenant\Domain\Exceptions\TenantContextMissingException;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Exceptions\TimetableConflictException;
use App\Modules\EduManager\Domain\Models\EduTimetableSlot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Issue #5822 (EDU-006) — emplois du temps (créneaux).
 *
 * Règles métier :
 *   - conflit détecté AVANT création : même CLASSE sur deux créneaux du
 *     même jour dont les intervalles [start,end) se chevauchent → exception,
 *     ou même ENSEIGNANT sur deux créneaux du même jour qui se chevauchent
 *     → exception (les deux règles s'appliquent indépendamment, bornées au
 *     tenant) ;
 *   - deux créneaux adjacents (08:00-09:00 puis 09:00-10:00) ne sont PAS en
 *     conflit (intervalle demi-ouvert [start, end)) ;
 *   - tenant-scoped strict : tout est résolu sur `company_id` du contexte
 *     courant (ou explicite dans $data), jamais cross-tenant ;
 *   - calendrier paginé par classe, ordonné (day_of_week, start_time), avec
 *     un jour par défaut résolu dans le FUSEAU du tenant (timezone tenant) :
 *     le « jour courant » de l'établissement, pas celui du serveur/UTC.
 */
class TimetableService
{
    public const DEFAULT_PAGE_SIZE = 15;

    /**
     * Crée un créneau après vérification des conflits (classe puis
     * enseignant), borné au tenant.
     *
     * @param  array{
     *     company_id?: string|null,
     *     class_id: int,
     *     subject_id: int,
     *     teacher_id: int,
     *     day_of_week: int,
     *     start_time: string,
     *     end_time: string,
     *     room?: string|null,
     * }  $data
     *
     * @throws TimetableConflictException si la classe OU l'enseignant a déjà
     *                                    un créneau qui se chevauche ce jour-là
     * @throws TenantContextMissingException si aucun tenant ne peut être résolu
     */
    public function create(array $data): EduTimetableSlot
    {
        $companyId = $this->resolveCompanyId($data);
        $classId = (int) $data['class_id'];
        $subjectId = (int) $data['subject_id'];
        $teacherId = (int) $data['teacher_id'];
        $dayOfWeek = (int) $data['day_of_week'];
        $startTime = (string) $data['start_time'];
        $endTime = (string) $data['end_time'];

        $this->assertNoConflict($companyId, $classId, $teacherId, $dayOfWeek, $startTime, $endTime);

        /** @var EduTimetableSlot $slot */
        $slot = EduTimetableSlot::query()->create([
            'company_id' => $companyId,
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'teacher_id' => $teacherId,
            'day_of_week' => $dayOfWeek,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'room' => isset($data['room']) && is_string($data['room']) && $data['room'] !== ''
                ? $data['room']
                : null,
        ]);

        return $slot;
    }

    /**
     * Calendrier paginé d'une classe : créneaux du tenant, ordonnés par
     * (day_of_week, start_time). `$day` (1=lundi..7=dimanche) filtre un jour
     * précis ; s'il est null, le « jour courant » est résolu dans le fuseau
     * du tenant (timezone tenant), pas en UTC.
     *
     * @return LengthAwarePaginator<int, EduTimetableSlot>
     *
     * @throws TenantContextMissingException si aucun tenant ne peut être résolu
     */
    public function calendarForClass(
        int $classId,
        ?string $day = null,
        int $perPage = self::DEFAULT_PAGE_SIZE,
        int $page = 1,
    ): LengthAwarePaginator {
        $companyId = $this->resolveCompanyId([]);

        $query = EduTimetableSlot::query()
            ->where('company_id', $companyId)
            ->where('class_id', $classId)
            ->orderBy('day_of_week')
            ->orderBy('start_time');

        if ($day !== null) {
            $query->where('day_of_week', (int) $day);
        } else {
            $query->where('day_of_week', $this->tenantToday($companyId));
        }

        /** @var LengthAwarePaginator<int, EduTimetableSlot> $paginator */
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return $paginator;
    }

    /**
     * Conflits (même classe OU même enseignant, même jour) sur l'intervalle
     * demi-ouvert [start,end) — bornés au tenant.
     */
    private function assertNoConflict(
        string $companyId,
        int $classId,
        int $teacherId,
        int $dayOfWeek,
        string $startTime,
        string $endTime,
    ): void {
        $classOverlap = EduTimetableSlot::query()
            ->where('company_id', $companyId)
            ->where('class_id', $classId)
            ->where('day_of_week', $dayOfWeek)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->exists();

        if ($classOverlap) {
            throw new TimetableConflictException(
                sprintf(
                    'La classe [%d] a déjà un créneau le jour %d entre %s et %s.',
                    $classId,
                    $dayOfWeek,
                    $startTime,
                    $endTime
                )
            );
        }

        $teacherOverlap = EduTimetableSlot::query()
            ->where('company_id', $companyId)
            ->where('teacher_id', $teacherId)
            ->where('day_of_week', $dayOfWeek)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->exists();

        if ($teacherOverlap) {
            throw new TimetableConflictException(
                sprintf(
                    "L'enseignant [%d] a déjà un créneau le jour %d entre %s et %s.",
                    $teacherId,
                    $dayOfWeek,
                    $startTime,
                    $endTime
                )
            );
        }
    }

    /**
     * Jour de la semaine (1=lundi..7=dimanche, ISO-8601) courant dans le
     * fuseau du tenant.
     */
    private function tenantToday(string $companyId): int
    {
        return (int) Carbon::now($this->tenantTimezone($companyId))->dayOfWeekIso;
    }

    /**
     * Fuseau du tenant : `companies.timezone` (cf. CompanyFactory : pays
     * supportés → fuseau réel), repli sur la config applicative.
     */
    private function tenantTimezone(string $companyId): string
    {
        if (app()->bound('current_company') && currentCompany()->id === $companyId) {
            $timezone = currentCompany()->timezone;
        } else {
            /** @var string|null $timezone */
            $timezone = Company::query()->whereKey($companyId)->value('timezone');
        }

        return is_string($timezone) && $timezone !== '' ? $timezone : (string) config('app.timezone', 'UTC');
    }

    /**
     * Résolution stricte du tenant : `company_id` explicite dans les données,
     * sinon contexte courant (`current_company`), sinon échec fail-closed.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveCompanyId(array $data): string
    {
        if (isset($data['company_id']) && is_string($data['company_id']) && $data['company_id'] !== '') {
            return $data['company_id'];
        }

        if (app()->bound('current_company') && currentCompany() instanceof Company) {
            return currentCompany()->id;
        }

        throw new TenantContextMissingException();
    }
}
