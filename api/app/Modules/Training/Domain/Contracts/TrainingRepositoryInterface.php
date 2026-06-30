<?php

declare(strict_types=1);

namespace App\Modules\Training\Domain\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TrainingRepositoryInterface
{
    public function findCourseById(int $id): ?object;

    /** @return LengthAwarePaginator<int, object> */
    public function paginateCourses(int $companyId, int $perPage = 15): LengthAwarePaginator;

    public function saveCourse(object $course): object;

    public function deleteCourse(int $id): void;

    /** @return Collection<int, object> */
    public function getEnrollmentsByEmployee(int $employeeId): Collection;

    public function enroll(int $sessionId, int $employeeId): object;
}
