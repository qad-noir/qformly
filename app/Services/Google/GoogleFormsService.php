<?php

namespace App\Services\Google;

use App\Models\QuestionnaireProject;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireSection;
use Google\Service\Forms as FormsApi;
use Google\Service\Forms\BatchUpdateFormRequest;
use Google\Service\Forms\ChoiceQuestion;
use Google\Service\Forms\CreateItemRequest;
use Google\Service\Forms\Form as GoogleForm;
use Google\Service\Forms\Grid as FormsGrid;
use Google\Service\Forms\Info;
use Google\Service\Forms\Item;
use Google\Service\Forms\Location;
use Google\Service\Forms\Option;
use Google\Service\Forms\PageBreakItem;
use Google\Service\Forms\PublishSettings;
use Google\Service\Forms\PublishState;
use Google\Service\Forms\Question;
use Google\Service\Forms\QuestionGroupItem;
use Google\Service\Forms\QuestionItem;
use Google\Service\Forms\Request as FormsRequest;
use Google\Service\Forms\RowQuestion;
use Google\Service\Forms\SetPublishSettingsRequest;
use Google\Service\Forms\TextItem;
use Google\Service\Forms\TextQuestion;
use Google\Service\Forms\UpdateFormInfoRequest;
use Illuminate\Support\Str;
use Throwable;

class GoogleFormsService
{
    public function __construct(private readonly GoogleOAuthService $oauth)
    {
    }

    /** @return array{google_form_id: string, respondent_url: ?string, edit_url: string} */
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
            throw new GoogleIntegrationException($this->oauth->configurationMessage());
        }

        $project->loadMissing(['user.googleConnection', 'sections.questions.options']);

        try {
            $forms = new FormsApi($this->oauth->clientForUser($project->user));
            $form = $this->createForm($forms, $project);
            $this->addProjectContent($forms, $form->getFormId(), $project);
            $updatedForm = $this->publishedForm($forms, $form);

            return [
                'google_form_id' => $form->getFormId(),
                'respondent_url' => $updatedForm->getResponderUri() ?: $form->getResponderUri(),
                'edit_url' => 'https://docs.google.com/forms/d/'.$form->getFormId().'/edit',
            ];
        } catch (GoogleIntegrationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new GoogleIntegrationException($this->friendlyErrorMessage($exception), previous: $exception);
        }
    }

    private function createForm(FormsApi $forms, QuestionnaireProject $project): GoogleForm
    {
        $info = new Info;
        $info->setTitle($project->title);
        $info->setDocumentTitle($project->title);

        $form = new GoogleForm;
        $form->setInfo($info);

        return $forms->forms->create($form);
    }

    private function addProjectContent(FormsApi $forms, string $formId, QuestionnaireProject $project): void
    {
        $requests = [];
        $itemIndex = 0;

        if (filled($project->description)) {
            $info = new Info;
            $info->setDescription($project->description);

            $update = new UpdateFormInfoRequest;
            $update->setInfo($info);
            $update->setUpdateMask('description');

            $request = new FormsRequest;
            $request->setUpdateFormInfo($update);
            $requests[] = $request;
        }

        foreach ($project->sections as $sectionIndex => $section) {
            $requests[] = $this->createItemRequest(
                $this->sectionItem($section, $sectionIndex === 0),
                $itemIndex++,
            );

            foreach ($section->questions as $question) {
                $requests[] = $this->createItemRequest($this->questionItem($question), $itemIndex++);
            }
        }

        if ($requests === []) {
            return;
        }

        $batch = new BatchUpdateFormRequest;
        $batch->setRequests($requests);
        $batch->setIncludeFormInResponse(true);
        $forms->forms->batchUpdate($formId, $batch);
    }

    private function sectionItem(QuestionnaireSection $section, bool $isFirst): Item
    {
        $item = new Item;
        $item->setTitle($section->title);
        if (filled($section->help_text)) {
            $item->setDescription($section->help_text);
        }

        if ($isFirst) {
            $item->setTextItem(new TextItem);
        } else {
            $item->setPageBreakItem(new PageBreakItem);
        }

        return $item;
    }

    private function questionItem(QuestionnaireQuestion $question): Item
    {
        if ($question->type === 'multiple_choice_grid') {
            return $this->gridQuestionItem($question);
        }

        $questionModel = new Question;
        $questionModel->setRequired($question->is_required);

        if (in_array($question->type, ['short_text', 'paragraph'], true)) {
            $textQuestion = new TextQuestion;
            $textQuestion->setParagraph($question->type === 'paragraph');
            $questionModel->setTextQuestion($textQuestion);
        } else {
            $options = $question->options->pluck('label')->filter()->values()->all();
            if ($question->type === 'likert' && $options === []) {
                $options = ['Strongly Agree', 'Agree', 'Neutral', 'Disagree', 'Strongly Disagree'];
            }

            if ($options === []) {
                throw new GoogleIntegrationException('"'.$this->questionTitle($question).'" needs at least one option before it can be generated.');
            }

            $choiceQuestion = new ChoiceQuestion;
            $choiceQuestion->setType(match ($question->type) {
                'checkboxes' => ChoiceQuestion::TYPE_CHECKBOX,
                'dropdown' => ChoiceQuestion::TYPE_DROP_DOWN,
                default => ChoiceQuestion::TYPE_RADIO,
            });
            $choiceQuestion->setOptions($this->optionModels($options));
            $questionModel->setChoiceQuestion($choiceQuestion);
        }

        $questionItem = new QuestionItem;
        $questionItem->setQuestion($questionModel);

        $item = new Item;
        $item->setTitle($this->questionTitle($question));
        if (filled($question->help_text)) {
            $item->setDescription($question->help_text);
        }
        $item->setQuestionItem($questionItem);

        return $item;
    }

    private function gridQuestionItem(QuestionnaireQuestion $question): Item
    {
        $rows = $question->options->pluck('label')->filter()->values()->all();
        if ($rows === []) {
            throw new GoogleIntegrationException('"'.$this->questionTitle($question).'" needs at least one row before it can be generated.');
        }

        $columns = $this->gridColumns($question);
        if ($columns === []) {
            throw new GoogleIntegrationException('"'.$this->questionTitle($question).'" needs shared answer choices before it can be generated.');
        }

        $choiceQuestion = new ChoiceQuestion;
        $choiceQuestion->setType(ChoiceQuestion::TYPE_RADIO);
        $choiceQuestion->setOptions($this->optionModels($columns));

        $grid = new FormsGrid;
        $grid->setColumns($choiceQuestion);

        $questionGroup = new QuestionGroupItem;
        $questionGroup->setGrid($grid);
        $questionGroup->setQuestions(array_map(function (string $row) use ($question): Question {
            $rowQuestion = new RowQuestion;
            $rowQuestion->setTitle($row);

            $rowModel = new Question;
            $rowModel->setRequired($question->is_required);
            $rowModel->setRowQuestion($rowQuestion);

            return $rowModel;
        }, $rows));

        $item = new Item;
        $item->setTitle($this->questionTitle($question));

        $description = $this->gridDescription($question);
        if (filled($description)) {
            $item->setDescription($description);
        }

        $item->setQuestionGroupItem($questionGroup);

        return $item;
    }

    /** @param array<int, string> $labels @return array<int, Option> */
    private function optionModels(array $labels): array
    {
        return array_map(function (string $label): Option {
            $option = new Option;
            $option->setValue($label);

            return $option;
        }, $labels);
    }

    /** @return array<int, string> */
    private function gridColumns(QuestionnaireQuestion $question): array
    {
        $helpText = (string) $question->help_text;

        if (preg_match('/response choices\s*:\s*([^\n]+)/iu', $helpText, $match) === 1) {
            return array_values(array_filter(array_map(
                static fn (string $choice): string => trim($choice),
                preg_split('/\s*(?:;|,|\/|\|)\s*/u', $match[1]) ?: []
            )));
        }

        return ['Yes', 'No'];
    }

    private function gridDescription(QuestionnaireQuestion $question): ?string
    {
        $description = preg_replace('/(?:^|\R)\s*response choices\s*:\s*[^\n]+/iu', '', (string) $question->help_text) ?? '';
        $description = trim($description);

        return $description === '' ? null : $description;
    }

    private function createItemRequest(Item $item, int $index): FormsRequest
    {
        $location = new Location;
        $location->setIndex($index);

        $createItem = new CreateItemRequest;
        $createItem->setItem($item);
        $createItem->setLocation($location);

        $request = new FormsRequest;
        $request->setCreateItem($createItem);

        return $request;
    }

    private function publishedForm(FormsApi $forms, GoogleForm $form): GoogleForm
    {
        // New Forms API creations are published by default. Request explicit
        // publishing only when Google did not return a responder URL; this avoids
        // Drive-wide permission changes and respects the owner's domain policies.
        if (filled($form->getResponderUri())) {
            return $form;
        }

        try {
            $publishState = new PublishState;
            $publishState->setIsPublished(true);
            $publishState->setIsAcceptingResponses(true);

            $publishSettings = new PublishSettings;
            $publishSettings->setPublishState($publishState);

            $publishRequest = new SetPublishSettingsRequest;
            $publishRequest->setPublishSettings($publishSettings);
            $publishRequest->setUpdateMask('publish_state');
            $forms->forms->setPublishSettings($form->getFormId(), $publishRequest);
        } catch (Throwable) {
            // A Google Workspace policy may disallow publishing. The owner still
            // receives a working edit URL, and the next get can surface a URI.
        }

        return $forms->forms->get($form->getFormId());
    }

    private function questionTitle(QuestionnaireQuestion $question): string
    {
        return filled($question->question_number)
            ? $question->question_number.'. '.$question->title
            : $question->title;
    }

    private function friendlyErrorMessage(Throwable $exception): string
    {
        $message = mb_strtolower($exception->getMessage());

        return match (true) {
            str_contains($message, 'insufficient') || str_contains($message, 'permission') || str_contains($message, 'forbidden') => 'Google denied the required Forms permissions. Reconnect your account and approve all requested permissions.',
            str_contains($message, 'rate limit') || str_contains($message, 'quota') || str_contains($message, '429') => 'Google is rate-limiting requests right now. Please wait a moment and try again.',
            str_contains($message, 'not enabled') || str_contains($message, 'has not been used') => 'Enable the Google Forms API and Google Drive API in your Google Cloud project, then try again.',
            str_contains($message, 'network') || str_contains($message, 'could not resolve') || str_contains($message, 'timed out') => 'Qformly could not reach Google. Check your connection and try again.',
            default => 'Google could not create this form. Check that the Forms API is enabled and your Google connection is valid.',
        };
    }
}
