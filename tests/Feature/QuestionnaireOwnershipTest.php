<?php

namespace Tests\Feature;

use App\Models\QuestionnaireProject;
use App\Models\GeneratedForm;
use App\Models\User;
use App\Services\Google\GoogleFormsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class QuestionnaireOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_cannot_open_another_users_questionnaire(): void
    {
        $owner = User::factory()->create();
        $visitor = User::factory()->create();
        $project = QuestionnaireProject::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($visitor)
            ->get(route('questionnaires.edit', $project))
            ->assertForbidden();
    }

    public function test_mock_google_forms_generation_returns_safe_links(): void
    {
        config()->set('services.google.forms_mock', true);
        $project = QuestionnaireProject::factory()->create();

        $form = app(GoogleFormsService::class)->createFormFromProject($project);

        $this->assertStringStartsWith('mock-', $form['google_form_id']);
        $this->assertStringContainsString('/viewform', $form['respondent_url']);
        $this->assertStringContainsString('/edit', $form['edit_url']);
    }

    public function test_a_user_can_upload_parse_and_store_a_text_questionnaire(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('staff-survey.txt', <<<TEXT
Staff Survey
SECTION A: TEAM FEEDBACK
1. Which team are you in?
a. Product
b. Support
TEXT);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Questionnaires\CreateQuestionnaireProject::class)
            ->set('title', 'Staff Survey')
            ->set('file', $file)
            ->call('save')
            ->assertHasNoErrors();

        $project = QuestionnaireProject::firstOrFail();
        $this->assertSame('parsed', $project->status);
        $this->assertSame('Staff Survey', $project->parsed_json['title']);
        $this->assertDatabaseCount('questionnaire_sections', 1);
        $this->assertDatabaseCount('questionnaire_questions', 1);
        $this->assertDatabaseCount('questionnaire_options', 2);
        Storage::disk('local')->assertExists($project->stored_file_path);
    }

    public function test_an_owner_can_generate_a_mock_form_from_the_editor(): void
    {
        config()->set('services.google.forms_mock', true);
        $user = User::factory()->create();
        $project = QuestionnaireProject::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Questionnaires\EditQuestionnaireProject::class, ['project' => $project])
            ->call('generateForm')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('generated_forms', [
            'user_id' => $user->id,
            'questionnaire_project_id' => $project->id,
            'status' => 'completed',
        ]);
    }

    public function test_dashboard_and_generated_forms_pages_render_for_an_owner(): void
    {
        $user = User::factory()->create();
        $project = QuestionnaireProject::factory()->create(['user_id' => $user->id, 'title' => 'Owner Survey']);
        GeneratedForm::create([
            'user_id' => $user->id,
            'questionnaire_project_id' => $project->id,
            'status' => 'completed',
            'respondent_url' => 'https://example.test/respond',
            'edit_url' => 'https://example.test/edit',
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Dashboard\QuestionnaireDashboard::class)
            ->assertSee('Questionnaire projects')
            ->assertSee('Owner Survey');

        Livewire::actingAs($user)
            ->test(\App\Livewire\Questionnaires\GeneratedForms::class)
            ->assertSee('Generated forms')
            ->assertSee('Owner Survey');
    }
}
