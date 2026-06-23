<?php

namespace App\Services\Google;

use App\Models\GoogleConnection;
use App\Models\User;
use Carbon\CarbonInterface;
use Google\Client;
use Google\Service\Oauth2;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Throwable;
use Illuminate\Support\Facades\Log;

class GoogleOAuthService {
    private const STATE_SESSION_KEY = 'qformly_google_oauth_state';
    private const STATE_TTL_SECONDS = 600;

    public function isConfigured(): bool {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect_uri'));
    }

    public function isMockMode(): bool {
        return (bool) config('services.google.forms_mock', true);
    }

    public function configurationMessage(): string {
        $missing = [];

        foreach (['client_id' => 'client ID', 'client_secret' => 'client secret', 'redirect_uri' => 'redirect URI'] as $key => $label) {
            if (blank(config('services.google.' . $key))) {
                $missing[] = $label;
            }
        }

        return $missing === []
            ? 'Google OAuth is configured.'
            : 'Google OAuth is missing its ' . implode(', ', $missing) . '. Add the values to your environment and clear configuration cache.';
    }

    public function authorizationUrl(User $user, string $returnUrl): string {
        $client = $this->configuredClient();
        $state = Str::random(64);

        Session::put(self::STATE_SESSION_KEY, [
            'hash' => hash('sha256', $state),
            'user_id' => $user->id,
            'created_at' => now()->timestamp,
            'return_url' => $returnUrl,
        ]);

        $client->setState($state);

        return $client->createAuthUrl();
    }

    public function connectUser(User $user, string $code, ?string $state): GoogleConnection {
        $this->validateState($state, $user);
        $client = $this->configuredClient();

        // try {
        //     $token = $client->fetchAccessTokenWithAuthCode($code);
        // } catch (Throwable $exception) {
        //     throw new GoogleIntegrationException('Google could not complete the connection. Please try again.', previous: $exception);
        // }

        try {
            $token = $client->fetchAccessTokenWithAuthCode($code);
        } catch (Throwable $exception) {
            Log::error('Google OAuth token exchange failed', [
                'exception_class' => get_class($exception),
                'message' => $exception->getMessage(),
                'redirect_uri' => config('services.google.redirect_uri'),
                'client_id_tail' => substr((string) config('services.google.client_id'), -16),
                'scopes' => config('services.google.scopes'),
            ]);

            throw new GoogleIntegrationException(
                'Google could not complete the connection. Please check storage/logs/laravel.log for the exact OAuth error.',
                previous: $exception
            );
        }

        // if (! is_array($token) || filled($token['error'] ?? null) || blank($token['access_token'] ?? null)) {
        //     throw new GoogleIntegrationException('Google did not return a usable access token. Please reconnect and approve the requested permissions.');
        // }

        if (! is_array($token) || filled($token['error'] ?? null) || blank($token['access_token'] ?? null)) {
            Log::warning('Google OAuth token response was not usable', [
                'error' => $token['error'] ?? null,
                'error_description' => $token['error_description'] ?? null,
                'redirect_uri' => config('services.google.redirect_uri'),
                'client_id_tail' => substr((string) config('services.google.client_id'), -16),
                'scopes' => config('services.google.scopes'),
            ]);

            throw new GoogleIntegrationException(
                'Google did not return a usable access token: ' . ($token['error_description'] ?? $token['error'] ?? 'Unknown token error')
            );
        }

        $client->setAccessToken($token);
        $connection = $user->googleConnection()->firstOrNew();
        $refreshToken = $token['refresh_token'] ?? $connection->refresh_token;

        $connection->fill([
            'google_email' => $this->googleEmail($client, $token) ?? $connection->google_email,
            'access_token' => $token['access_token'],
            'token_expires_at' => $this->tokenExpiry($token, $connection->token_expires_at),
            'scopes' => $this->tokenScopes($token, $connection->scopes),
        ]);

        if (filled($refreshToken)) {
            $connection->refresh_token = $refreshToken;
        }

        $connection->user()->associate($user);
        $connection->save();

        return $connection;
    }

    public function clientForUser(User $user): Client {
        $connection = $user->googleConnection;

        if (! $connection || blank($connection->access_token)) {
            throw new GoogleIntegrationException('Connect your Google account before generating a real Google Form.');
        }

        $client = $this->configuredClient();
        $client->setAccessToken([
            'access_token' => $connection->access_token,
            'refresh_token' => $connection->refresh_token,
        ]);

        if ($connection->token_expires_at?->isFuture()) {
            return $client;
        }

        if (blank($connection->refresh_token)) {
            throw new GoogleIntegrationException('Your Google connection has expired. Please reconnect your Google account.');
        }

        try {
            $refreshedToken = $client->fetchAccessTokenWithRefreshToken($connection->refresh_token);
        } catch (Throwable $exception) {
            throw new GoogleIntegrationException('Google could not refresh your connection. Please reconnect your Google account.', previous: $exception);
        }

        if (! is_array($refreshedToken) || filled($refreshedToken['error'] ?? null) || blank($refreshedToken['access_token'] ?? null)) {
            throw new GoogleIntegrationException('Your Google connection has expired or was revoked. Please reconnect your Google account.');
        }

        $connection->forceFill([
            'access_token' => $refreshedToken['access_token'],
            'token_expires_at' => $this->tokenExpiry($refreshedToken, $connection->token_expires_at),
        ])->save();

        $refreshedToken['refresh_token'] = $connection->refresh_token;
        $client->setAccessToken($refreshedToken);

        return $client;
    }

    public function consumeReturnUrl(): string {
        $state = Session::pull(self::STATE_SESSION_KEY, []);

        return is_array($state) && filled($state['return_url'] ?? null)
            ? $state['return_url']
            : route('dashboard');
    }

    public function validateState(?string $state, ?User $user = null): void {
        $this->validateAndConsumeState($state, $user?->id);
    }

    private function configuredClient(): Client {
        if (! $this->isConfigured()) {
            throw new GoogleIntegrationException($this->configurationMessage());
        }

        $client = new Client;
        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->setRedirectUri((string) config('services.google.redirect_uri'));
        $client->setScopes(config('services.google.scopes', []));
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setIncludeGrantedScopes(true);

        return $client;
    }

    private function validateAndConsumeState(?string $state, ?int $userId = null): void {
        $storedState = Session::pull(self::STATE_SESSION_KEY);

        if (
            ! is_array($storedState)
            || blank($state)
            || ($userId !== null && (int) ($storedState['user_id'] ?? 0) !== $userId)
            || now()->timestamp - (int) ($storedState['created_at'] ?? 0) > self::STATE_TTL_SECONDS
            || ! hash_equals((string) ($storedState['hash'] ?? ''), hash('sha256', $state))
        ) {
            throw new GoogleIntegrationException('Your Google connection session expired or was invalid. Please start the connection again.');
        }

        Session::put(self::STATE_SESSION_KEY, $storedState);
    }

    /** @param array<string, mixed> $token */
    private function googleEmail(Client $client, array $token): ?string {
        try {
            $idToken = $token['id_token'] ?? null;
            $payload = $idToken ? $client->verifyIdToken($idToken) : null;
            if (is_array($payload) && filled($payload['email'] ?? null)) {
                return $payload['email'];
            }

            return (new Oauth2($client))->userinfo->get()->getEmail();
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $token */
    private function tokenExpiry(array $token, ?CarbonInterface $existingExpiry): ?CarbonInterface {
        return isset($token['expires_in'])
            ? now()->addSeconds((int) $token['expires_in'])
            : $existingExpiry;
    }

    /** @param array<string, mixed> $token @param array<int, string>|null $existingScopes */
    private function tokenScopes(array $token, ?array $existingScopes): array {
        $scopes = $token['scope'] ?? $existingScopes ?? config('services.google.scopes', []);

        return is_array($scopes)
            ? array_values(array_filter($scopes))
            : array_values(array_filter(preg_split('/[\s,]+/', (string) $scopes) ?: []));
    }
}
