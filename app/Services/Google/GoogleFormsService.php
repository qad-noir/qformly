<?php

namespace App\Services\Google;

use App\Models\QuestionnaireProject;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleFormsService
{
    public function __construct(private readonly GoogleOAuthService $oauth)
    {
    }

    /** @return array{google_form_id: string, respondent_url: string, edit_url: string} */
    public function createFormFromProject(QuestionnaireProject $project): array
    {
        if ($this->oauth->isMockMode()) {
            $id = 'mock-'.Str::lower((string) Str::uuid());

            return [
                'google_form_id' => $id,
                'respondent_url' => 'https://docs.google.com/forms/d/e/'.$id.'/viewform',
                'edit_url' => 'https://docs.google.com/forms/d/'.$id.'/edit',
            ];
        }

        if (! $this->oauth->isConfigured()) {
            throw new RuntimeException('Google Forms is not configured. Add Google OAuth credentials or enable GOOGLE_FORMS_MOCK.');
        }

        // TODO: Exchange/refresh the connected account token and call the Google Forms API here.
        // Keeping this explicit prevents a half-configured integration from failing silently.
        throw new RuntimeException('Real Google Forms generation has not been enabled yet. Set GOOGLE_FORMS_MOCK=true to generate locally.');
    }
}
