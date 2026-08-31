<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Models\TravelQuiz;
use App\Modules\TravelAgency\Domain\Models\TravelQuizParticipation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelQuizParticipation>
 */
class TravelQuizParticipationFactory extends Factory
{
    protected $model = TravelQuizParticipation::class;

    public function definition(): array
    {
        return [
            'quiz_id' => TravelQuiz::factory(),
            'participant_contact_id' => null,
            'participant_email' => $this->faker->unique()->safeEmail,
            'participant_name' => $this->faker->name,
            'answers' => [['question_id' => 1, 'selected_option' => 1]],
            'score' => 1,
            'bonus' => 0,
            'status' => 'submitted',
        ];
    }
}
