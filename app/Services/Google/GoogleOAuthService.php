<?php

namespace App\Services\Google;

class GoogleOAuthService
{
    public function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect_uri'));
    }

    public function isMockMode(): bool
    {
        return (bool) config('services.google.forms_mock', true);
    }
}
