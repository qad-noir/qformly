<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Dashboard\QuestionnaireDashboard;
use App\Livewire\Questionnaires\CreateQuestionnaireProject;
use App\Livewire\Questionnaires\EditQuestionnaireProject;
use App\Livewire\Questionnaires\GeneratedForms;
use App\Http\Controllers\GoogleConnectionController;
use App\Services\Google\GoogleOAuthService;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', QuestionnaireDashboard::class)->name('dashboard');
    Route::get('/questionnaires', QuestionnaireDashboard::class)->name('questionnaires.index');
    Route::get('/questionnaires/create', CreateQuestionnaireProject::class)->name('questionnaires.create');
    Route::get('/questionnaires/{project}/edit', EditQuestionnaireProject::class)->name('questionnaires.edit');
    Route::get('/questionnaires/{project}/forms', GeneratedForms::class)->name('questionnaires.forms');
    Route::get('/generated-forms', GeneratedForms::class)->name('generated-forms.index');
    Route::get('/google/connect', [GoogleConnectionController::class, 'redirectToGoogle'])->name('google.connect');
    Route::get('/google/callback', [GoogleConnectionController::class, 'handleGoogleCallback'])->name('google.callback');
    Route::post('/google/disconnect', [GoogleConnectionController::class, 'disconnect'])->name('google.disconnect');
});

Route::get('/debug/google-url', function (GoogleOAuthService $service) {
    return response()->json([
        'redirect_uri' => config('services.google.redirect_uri'),
        'client_id_exists' => filled(config('services.google.client_id')),
        'client_secret_exists' => filled(config('services.google.client_secret')),
        'scopes' => config('services.google.scopes'),
        'forms_mock' => config('services.google.forms_mock'),
        'auth_url' => $service->authorizationUrl(
            auth()->user(),
            route('dashboard')
        ),
    ]);
})->middleware(['auth']);
