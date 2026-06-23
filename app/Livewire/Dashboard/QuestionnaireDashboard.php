<?php

namespace App\Livewire\Dashboard;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class QuestionnaireDashboard extends Component
{
    public function render()
    {
        $user = Auth::user();

        return view('livewire.dashboard.questionnaire-dashboard', [
            'projectCount' => $user->questionnaireProjects()->count(),
            'formCount' => $user->generatedForms()->where('status', 'completed')->count(),
            'recentProjects' => $user->questionnaireProjects()
                ->withCount('generatedForms')
                ->latest()
                ->take(6)
                ->get(),
        ]);
    }
}
