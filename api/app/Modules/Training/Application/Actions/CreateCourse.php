<?php

declare(strict_types=1);

namespace App\Modules\Training\Application\Actions;

use App\Modules\Training\Application\DTOs\CreateCourseDTO;
use App\Models\TrainingCourse;
use Illuminate\Support\Facades\DB;

/**
 * Use Case: Create a new training course.
 */
final class CreateCourse
{
    public function execute(CreateCourseDTO $dto, string $companyId): TrainingCourse
    {
        return DB::transaction(function () use ($dto, $companyId): TrainingCourse {
            /** @var TrainingCourse $course */
            $course = TrainingCourse::create([
                'company_id'  => $companyId,
                'title'       => $dto->title,
                'description' => $dto->description,
                'duration'    => $dto->duration,
                'category'    => $dto->category,
                'is_mandatory'=> $dto->isMandatory ?? false,
            ]);

            return $course;
        });
    }
}
