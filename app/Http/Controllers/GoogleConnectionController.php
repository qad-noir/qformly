<?php

namespace App\Http\Controllers;

use App\Services\Google\GoogleIntegrationException;
use App\Services\Google\GoogleOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class GoogleConnectionController extends Controller
{
    public function redirectToGoogle(Request $request, GoogleOAuthService $oauth): RedirectResponse
    {
        $returnUrl = route('dashboard');

        if ($request->filled('project')) {
            $project = Auth::user()->questionnaireProjects()->findOrFail($request->integer('project'));
            $returnUrl = route('questionnaires.edit', $project);
        }

        if ($oauth->isMockMode()) {
            return redirect()->to($returnUrl)->with('error', 'Google connection is disabled while GOOGLE_FORMS_MOCK is enabled.');
        }

        try {
            return redirect()->away($oauth->authorizationUrl(Auth::user(), $returnUrl));
        } catch (GoogleIntegrationException $exception) {
            return redirect()->to($returnUrl)->with('error', $exception->getMessage());
        }
    }

    public function handleGoogleCallback(Request $request, GoogleOAuthService $oauth): RedirectResponse
    {
        try {
            if ($request->filled('error')) {
                $oauth->validateState($request->string('state')->toString(), Auth::user());
                throw new GoogleIntegrationException('Google connection was cancelled or denied.');
            }

            $oauth->connectUser(
                Auth::user(),
                $request->string('code')->toString(),
                $request->string('state')->toString(),
            );

            return redirect()->to($oauth->consumeReturnUrl())->with('success', 'Your Google account is connected.');
        } catch (GoogleIntegrationException $exception) {
            return redirect()->to($oauth->consumeReturnUrl())->with('error', $exception->getMessage());
        } catch (Throwable) {
            return redirect()->to($oauth->consumeReturnUrl())->with('error', 'Google connection could not be completed. Please try again.');
        }
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $request->user()->googleConnection()?->delete();

        return back()->with('success', 'Your Google account has been disconnected from Qformly.');
    }
}
