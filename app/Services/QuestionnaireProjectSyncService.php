<?php

namespace App\Services;

use App\Models\QuestionnaireProject;
use Illuminate\Support\Facades\DB;

class QuestionnaireProjectSyncService
{
    /** @param array<string, mixed> $data */
    public function sync(QuestionnaireProject $project, array $data): void
    {
        DB::transaction(function () use ($project, $data): void {
            $project->sections()->delete();

            foreach (array_values($data['sections'] ?? []) as $sectionOrder => $sectionData) {
                $section = $project->sections()->create([
                    'title' => trim((string) ($sectionData['title'] ?? '')) ?: 'Untitled section',
                    'help_text' => $this->nullableText($sectionData['help_text'] ?? null),
                    'sort_order' => $sectionOrder,
                ]);

                foreach (array_values($sectionData['questions'] ?? []) as $questionOrder => $questionData) {
                    $question = $section->questions()->create([
                        'question_number' => $this->nullableText($questionData['number'] ?? null),
                        'title' => trim((string) ($questionData['title'] ?? '')),
                        'type' => (string) ($questionData['type'] ?? 'short_text'),
                        'is_required' => (bool) ($questionData['required'] ?? $questionData['is_required'] ?? true),
                        'help_text' => $this->nullableText($questionData['help_text'] ?? null),
                        'sort_order' => $questionOrder,
                    ]);

                    foreach (array_values($questionData['options'] ?? []) as $optionOrder => $optionData) {
                        $label = is_array($optionData) ? ($optionData['label'] ?? '') : $optionData;
                        $value = is_array($optionData) ? ($optionData['value'] ?? null) : null;

                        if (trim((string) $label) === '') {
                            continue;
                        }

                        $question->options()->create([
                            'label' => trim((string) $label),
                            'value' => $this->nullableText($value),
                            'sort_order' => $optionOrder,
                        ]);
                    }
                }
            }
        });
    }

    private function nullableText(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === '' || $value === null ? null : (string) $value;
    }
}
