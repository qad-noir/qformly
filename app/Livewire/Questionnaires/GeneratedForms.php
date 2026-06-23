<?php

namespace App\Livewire\Questionnaires;

use App\Models\QuestionnaireProject;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GeneratedForms extends Component
{
    use AuthorizesRequests;

    public ?QuestionnaireProject $project = null;

    public function mount(?QuestionnaireProject $project = null): void
    {
        if ($project !== null) {
            $this->authorize('view', $project);
        }
        $this->project = $project;
    }

    public function render()
    {
        return view('livewire.questionnaires.generated-forms', [
            'forms' => $this->project
                ? $this->project->generatedForms()->where('user_id', Auth::id())->latest()->get()
                : Auth::user()->generatedForms()->with('project')->latest()->get(),
        ]);
    }
}
