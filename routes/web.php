<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Dashboard\QuestionnaireDashboard;
use App\Livewire\Questionnaires\CreateQuestionnaireProject;
use App\Livewire\Questionnaires\EditQuestionnaireProject;
use App\Livewire\Questionnaires\GeneratedForms;

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
});
