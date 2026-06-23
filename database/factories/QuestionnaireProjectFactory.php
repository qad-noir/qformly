<?php

namespace Database\Factories;

use App\Models\QuestionnaireProject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuestionnaireProject> */
class QuestionnaireProjectFactory extends Factory
{
    protected $model = QuestionnaireProject::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'status' => 'draft',
        ];
    }
}
