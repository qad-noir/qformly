<?php

namespace Tests\Feature;

use App\Models\QuestionnaireProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GoogleConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_connect_requires_authentication(): void
    {
        $this->get(route('google.connect'))
            ->assertRedirect(route('login'));
    }

    public function test_google_connect_shows_a_friendly_configuration_error_when_real_mode_is_not_configured(): void
    {
        config()->set('services.google.forms_mock', false);
        config()->set('services.google.client_id', null);
        config()->set('services.google.client_secret', null);
        config()->set('services.google.redirect_uri', null);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('google.connect'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'Google OAuth is missing'));
    }

    public function test_google_connect_redirects_to_consent_with_a_session_bound_state_value(): void
    {
        config()->set('services.google.forms_mock', false);
        config()->set('services.google.client_id', 'test-client-id');
        config()->set('services.google.client_secret', 'test-client-secret');
        config()->set('services.google.redirect_uri', 'http://localhost/google/callback');
        config()->set('services.google.scopes', ['openid', 'email']);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('google.connect'));

        $response->assertRedirectContains('accounts.google.com');
        $this->assertIsArray(session('qformly_google_oauth_state'));
    }

    public function test_google_callback_rejects_an_invalid_state(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('google.callback', ['error' => 'access_denied', 'state' => 'invalid-state']));

        $response->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'Your Google connection session expired or was invalid. Please start the connection again.');
    }

    public function test_google_callback_rejects_a_state_created_for_another_user(): void
    {
        config()->set('services.google.forms_mock', false);
        config()->set('services.google.client_id', 'test-client-id');
        config()->set('services.google.client_secret', 'test-client-secret');
        config()->set('services.google.redirect_uri', 'http://localhost/google/callback');
        config()->set('services.google.scopes', ['openid']);

        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $redirect = $this->actingAs($firstUser)->get(route('google.connect'));
        parse_str((string) parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->actingAs($secondUser)
            ->get(route('google.callback', ['error' => 'access_denied', 'state' => $query['state']]))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'Your Google connection session expired or was invalid. Please start the connection again.');
    }

    public function test_real_mode_without_a_google_connection_records_a_friendly_failure(): void
    {
        config()->set('services.google.forms_mock', false);
        config()->set('services.google.client_id', 'test-client-id');
        config()->set('services.google.client_secret', 'test-client-secret');
        config()->set('services.google.redirect_uri', 'http://localhost/google/callback');
        config()->set('services.google.scopes', ['openid']);

        $user = User::factory()->create();
        $project = QuestionnaireProject::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Questionnaires\EditQuestionnaireProject::class, ['project' => $project])
            ->call('generateForm')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('generated_forms', [
            'user_id' => $user->id,
            'questionnaire_project_id' => $project->id,
            'status' => 'failed',
            'error_message' => 'Connect your Google account before generating a real Google Form.',
        ]);
    }
}
