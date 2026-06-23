<?php

namespace App\Livewire\Questionnaires;

use App\Models\GeneratedForm;
use App\Models\QuestionnaireProject;
use App\Services\Google\GoogleFormsService;
use App\Services\Google\GoogleOAuthService;
use App\Services\QuestionnaireProjectSyncService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
class EditQuestionnaireProject extends Component
{
    use AuthorizesRequests;

    public QuestionnaireProject $project;
    public string $title = '';
    public ?string $description = null;
    /** @var array<int, array<string, mixed>> */
    public array $sections = [];
    public array $questionTypes = [
        'short_text' => 'Short text',
        'paragraph' => 'Paragraph',
        'multiple_choice' => 'Multiple choice',
        'checkboxes' => 'Checkboxes',
        'dropdown' => 'Dropdown',
        'likert' => 'Likert scale',
    ];

    public function mount(QuestionnaireProject $project): void
    {
        $this->authorize('view', $project);
        $this->project = $project;
        $this->title = $project->title;
        $this->description = $project->description;
        $this->sections = $this->sectionsFromProject();
    }

    public function addSection(): void
    {
        $this->sections[] = ['title' => 'New section', 'help_text' => null, 'questions' => []];
    }

    public function removeSection(int $sectionIndex): void
    {
        unset($this->sections[$sectionIndex]);
        $this->sections = array_values($this->sections);
    }

    public function addQuestion(int $sectionIndex): void
    {
        $this->sections[$sectionIndex]['questions'][] = $this->emptyQuestion();
    }

    public function removeQuestion(int $sectionIndex, int $questionIndex): void
    {
        unset($this->sections[$sectionIndex]['questions'][$questionIndex]);
        $this->sections[$sectionIndex]['questions'] = array_values($this->sections[$sectionIndex]['questions']);
    }

    public function addOption(int $sectionIndex, int $questionIndex): void
    {
        $this->sections[$sectionIndex]['questions'][$questionIndex]['options'][] = ['label' => '', 'value' => null];
    }

    public function removeOption(int $sectionIndex, int $questionIndex, int $optionIndex): void
    {
        unset($this->sections[$sectionIndex]['questions'][$questionIndex]['options'][$optionIndex]);
        $this->sections[$sectionIndex]['questions'][$questionIndex]['options'] = array_values(
            $this->sections[$sectionIndex]['questions'][$questionIndex]['options']
        );
    }

    public function save(): void
    {
        $this->persistQuestionnaire();
        session()->flash('success', 'Your questionnaire changes have been saved.');
    }

    public function generateForm(): void
    {
        $this->persistQuestionnaire();

        $form = GeneratedForm::create([
            'user_id' => Auth::id(),
            'questionnaire_project_id' => $this->project->id,
            'status' => 'pending',
        ]);

        try {
            $result = app(GoogleFormsService::class)->createFormFromProject($this->project->fresh(['sections.questions.options']));
            $form->update([...$result, 'status' => 'completed']);
            $this->project->update(['status' => 'generated']);
            session()->flash('success', 'Google Form generated successfully. Its links are ready below.');
        } catch (Throwable $exception) {
            report($exception);
            $form->update(['status' => 'failed', 'error_message' => $exception->getMessage()]);
            session()->flash('error', 'Form generation failed: '.$exception->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.questionnaires.edit-questionnaire-project', [
            'hasGoogleConnection' => Auth::user()->googleConnection()->exists(),
            'googleConfigured' => app(GoogleOAuthService::class)->isConfigured(),
            'latestForm' => $this->project->generatedForms()->latest()->first(),
        ]);
    }

    private function persistQuestionnaire(): void
    {
        $this->authorize('update', $this->project);
        $this->validateQuestionnaire();

        $data = [
            'title' => trim($this->title),
            'description' => $this->blankToNull($this->description),
            'sections' => $this->sections,
        ];

        $this->project->update([
            'title' => $data['title'],
            'description' => $data['description'],
            'parsed_json' => $data,
            'status' => 'ready',
            'parse_error' => null,
        ]);
        app(QuestionnaireProjectSyncService::class)->sync($this->project, $data);
        $this->project->refresh();
    }

    private function validateQuestionnaire(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sections' => ['array'],
            'sections.*.title' => ['nullable', 'string', 'max:255'],
            'sections.*.help_text' => ['nullable', 'string', 'max:5000'],
            'sections.*.questions' => ['array'],
            'sections.*.questions.*.number' => ['nullable', 'string', 'max:50'],
            'sections.*.questions.*.title' => ['required', 'string', 'max:5000'],
            'sections.*.questions.*.type' => ['required', Rule::in(array_keys($this->questionTypes))],
            'sections.*.questions.*.required' => ['boolean'],
            'sections.*.questions.*.help_text' => ['nullable', 'string', 'max:5000'],
            'sections.*.questions.*.options' => ['array'],
            'sections.*.questions.*.options.*.label' => ['nullable', 'string', 'max:255'],
            'sections.*.questions.*.options.*.value' => ['nullable', 'string', 'max:255'],
        ]);

        $optionErrors = [];

        foreach ($this->sections as $sectionIndex => $section) {
            foreach ($section['questions'] as $questionIndex => $question) {
                if (! $this->usesOptions((string) $question['type'])) {
                    continue;
                }

                $options = $question['options'] ?? [];
                if ($options === []) {
                    $optionErrors["sections.$sectionIndex.questions.$questionIndex.options"] = 'Add at least one option for this question type.';
                }

                foreach ($options as $optionIndex => $option) {
                    if (blank($option['label'] ?? null)) {
                        $optionErrors["sections.$sectionIndex.questions.$questionIndex.options.$optionIndex.label"] = 'An option label is required.';
                    }
                }
            }
        }

        if ($optionErrors !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages($optionErrors);
        }
    }

    private function sectionsFromProject(): array
    {
        $sections = $this->project->sections()->with('questions.options')->get();

        if ($sections->isEmpty() && is_array($this->project->parsed_json)) {
            return $this->normaliseSections($this->project->parsed_json['sections'] ?? []);
        }

        return $sections->map(fn ($section) => [
            'title' => $section->title,
            'help_text' => $section->help_text,
            'questions' => $section->questions->map(fn ($question) => [
                'number' => $question->question_number,
                'title' => $question->title,
                'type' => $question->type,
                'required' => $question->is_required,
                'help_text' => $question->help_text,
                'options' => $question->options->map(fn ($option) => [
                    'label' => $option->label,
                    'value' => $option->value,
                ])->values()->all(),
            ])->values()->all(),
        ])->values()->all();
    }

    private function normaliseSections(array $sections): array
    {
        return array_map(fn (array $section) => [
            'title' => $section['title'] ?? 'Questions',
            'help_text' => $section['help_text'] ?? null,
            'questions' => array_map(fn (array $question) => [
                'number' => $question['number'] ?? null,
                'title' => $question['title'] ?? '',
                'type' => $question['type'] ?? 'short_text',
                'required' => $question['required'] ?? true,
                'help_text' => $question['help_text'] ?? null,
                'options' => array_map(fn ($option) => is_array($option)
                    ? ['label' => $option['label'] ?? '', 'value' => $option['value'] ?? null]
                    : ['label' => $option, 'value' => null], $question['options'] ?? []),
            ], $section['questions'] ?? []),
        ], $sections);
    }

    private function emptyQuestion(): array
    {
        return [
            'number' => null,
            'title' => '',
            'type' => 'short_text',
            'required' => true,
            'help_text' => null,
            'options' => [],
        ];
    }

    private function usesOptions(string $type): bool
    {
        return in_array($type, ['multiple_choice', 'checkboxes', 'dropdown', 'likert'], true);
    }

    private function blankToNull(?string $value): ?string
    {
        return blank($value) ? null : trim($value);
    }
}
