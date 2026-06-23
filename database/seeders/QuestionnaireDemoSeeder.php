<?php

namespace Database\Seeders;

use App\Models\QuestionnaireProject;
use App\Services\QuestionnaireProjectSyncService;
use Illuminate\Database\Seeder;

class QuestionnaireDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = \App\Models\User::where('email', 'test@example.com')->first();

        if (! $user || $user->questionnaireProjects()->exists()) {
            return;
        }

        $data = [
            'title' => 'Community Feedback Survey',
            'description' => 'A small Qformly sample project for local exploration.',
            'sections' => [[
                'title' => 'SECTION A: ABOUT YOU',
                'help_text' => 'Please answer the following questions.',
                'questions' => [[
                    'number' => '1',
                    'title' => 'Which option best describes your experience?',
                    'type' => 'multiple_choice',
                    'required' => true,
                    'options' => ['Very satisfied', 'Satisfied', 'Neutral', 'Dissatisfied'],
                ]],
            ]],
        ];

        $project = QuestionnaireProject::create([
            'user_id' => $user->id,
            'title' => $data['title'],
            'description' => $data['description'],
            'extracted_text' => 'Demo project created by the Qformly seeder.',
            'parsed_json' => $data,
            'status' => 'ready',
        ]);

        app(QuestionnaireProjectSyncService::class)->sync($project, $data);
    }
}
