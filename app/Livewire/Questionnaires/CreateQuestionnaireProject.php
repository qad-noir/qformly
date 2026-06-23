<?php

namespace App\Livewire\Questionnaires;

use App\Models\QuestionnaireProject;
use App\Services\QuestionnaireParserService;
use App\Services\QuestionnaireProjectSyncService;
use App\Services\QuestionnaireTextExtractorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

#[Layout('layouts.app')]
class CreateQuestionnaireProject extends Component
{
    use WithFileUploads;

    public string $title = '';
    public ?string $description = null;
    public ?TemporaryUploadedFile $file = null;

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'file' => ['required', 'file', 'max:5120', 'mimes:txt,docx'],
        ];
    }

    public function save(): mixed
    {
        $validated = $this->validate();
        $storedPath = $this->file->store('questionnaire-projects/'.Auth::id(), 'local');

        $project = QuestionnaireProject::create([
            'user_id' => Auth::id(),
            'title' => $validated['title'],
            'description' => $validated['description'],
            'original_filename' => $this->file->getClientOriginalName(),
            'stored_file_path' => $storedPath,
            'status' => 'parsing',
        ]);

        try {
            $text = app(QuestionnaireTextExtractorService::class)->extract($this->file);
            $parsed = app(QuestionnaireParserService::class)->parse($text);
            $parsed['title'] = $project->title;
            $parsed['description'] = $project->description ?? $parsed['description'];

            $project->update([
                'extracted_text' => $text,
                'parsed_json' => $parsed,
                'status' => 'parsed',
                'parse_error' => null,
            ]);
            app(QuestionnaireProjectSyncService::class)->sync($project, $parsed);
        } catch (ValidationException $exception) {
            $project->update(['status' => 'failed', 'parse_error' => collect($exception->errors())->flatten()->implode(' ')]);
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $project->update(['status' => 'failed', 'parse_error' => $exception->getMessage()]);
            $this->addError('file', 'We could not extract this questionnaire. '.$exception->getMessage());

            return null;
        }

        session()->flash('success', 'Questionnaire parsed. Review and refine it before generating your form.');

        return $this->redirectRoute('questionnaires.edit', ['project' => $project], navigate: true);
    }

    public function render()
    {
        return view('livewire.questionnaires.create-questionnaire-project');
    }
}
