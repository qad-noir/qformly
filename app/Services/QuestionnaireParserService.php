<?php

namespace App\Services;

class QuestionnaireParserService
{
    /**
     * Convert a modestly structured questionnaire into predictable editable data.
     * This deliberately favours safe, simple detection over clever guesses.
     *
     * @return array{title: string, description: ?string, sections: array<int, array<string, mixed>>}
     */
    public function parse(string $text): array
    {
        $lines = array_values(array_filter(array_map(
            static fn (string $line): string => trim($line),
            preg_split('/\R/u', $text) ?: []
        ), static fn (string $line): bool => $line !== ''));

        if ($lines === []) {
            return ['title' => 'Untitled Questionnaire', 'description' => null, 'sections' => []];
        }

        $firstSection = $this->firstSectionIndex($lines);
        $preamble = array_slice($lines, 0, $firstSection ?? count($lines));
        $title = $preamble[0] ?? 'Untitled Questionnaire';
        $descriptionLines = array_slice($preamble, 1);
        $sections = [];
        $currentSection = null;
        $currentQuestion = null;

        foreach ($lines as $line) {
            if ($this->isSectionHeading($line)) {
                $this->finishQuestion($currentSection, $currentQuestion);
                if ($currentSection !== null) {
                    $sections[] = $currentSection;
                }
                $currentSection = ['title' => $line, 'help_text' => null, 'questions' => []];
                $currentQuestion = null;
                continue;
            }

            if ($this->questionMatch($line, $questionMatch)) {
                $this->finishQuestion($currentSection, $currentQuestion);
                $currentSection ??= ['title' => 'Questions', 'help_text' => null, 'questions' => []];
                $currentQuestion = [
                    'number' => $questionMatch[1],
                    'title' => trim($questionMatch[2]),
                    'type' => 'short_text',
                    'required' => true,
                    'options' => [],
                ];
                continue;
            }

            if ($currentQuestion !== null && $this->optionMatch($line, $optionMatch)) {
                $currentQuestion['options'][] = trim($optionMatch[1]);
                continue;
            }

            if ($currentQuestion !== null) {
                $currentQuestion['title'] = trim($currentQuestion['title'].' '.$line);
                continue;
            }

            if ($currentSection !== null) {
                $currentSection['help_text'] = trim(($currentSection['help_text'] ? $currentSection['help_text'].' ' : '').$line);
            }
        }

        $this->finishQuestion($currentSection, $currentQuestion);
        if ($currentSection !== null) {
            $sections[] = $currentSection;
        }

        if ($sections === []) {
            $sections[] = ['title' => 'Questions', 'help_text' => null, 'questions' => []];
        }

        return [
            'title' => $title,
            'description' => $descriptionLines === [] ? null : implode("\n", $descriptionLines),
            'sections' => $sections,
        ];
    }

    private function firstSectionIndex(array $lines): ?int
    {
        foreach ($lines as $index => $line) {
            if ($this->isSectionHeading($line)) {
                return $index;
            }
        }

        return null;
    }

    private function isSectionHeading(string $line): bool
    {
        return (bool) preg_match('/^SECTION\s+(?:[A-Z]|\d+|[IVXLCDM]+)\b(?:\s*[:.\-–—].*)?$/i', $line);
    }

    private function questionMatch(string $line, ?array &$match): bool
    {
        return preg_match('/^(\d+(?:\.\d+)?)\s*[.)]\s+(.+)$/u', $line, $match) === 1;
    }

    private function optionMatch(string $line, ?array &$match): bool
    {
        return preg_match('/^(?:[a-z]|[ivxlcdm]+)\s*[.)]\s+(.+)$/iu', $line, $match) === 1;
    }

    private function finishQuestion(?array &$section, ?array &$question): void
    {
        if ($section === null || $question === null) {
            return;
        }

        $context = implode(' ', [
            $section['title'] ?? '',
            $section['help_text'] ?? '',
            $question['title'],
            implode(' ', $question['options']),
        ]);

        $question['type'] = $this->detectType($question['title'], $question['options'], $context);
        if ($question['type'] === 'likert' && $question['options'] === []) {
            $question['options'] = ['Strongly Agree', 'Agree', 'Neutral', 'Disagree', 'Strongly Disagree'];
        }
        $section['questions'][] = $question;
        $question = null;
    }

    private function detectType(string $title, array $options, string $context): string
    {
        $lower = mb_strtolower($context);

        if (str_contains($lower, 'likert') ||
            preg_match('/strongly agree.*agree.*(?:neutral|neither).*disagree/iu', $lower)) {
            return 'likert';
        }

        if (preg_match('/tick all that apply|select all|choose all/iu', $lower)) {
            return 'checkboxes';
        }

        if ($options !== []) {
            return 'multiple_choice';
        }

        $wordCount = str_word_count($title);
        if (mb_strlen($title) > 140 || $wordCount > 20 || preg_match('/\b(explain|describe|discuss|why|how|comments?|suggestions?)\b/iu', $title)) {
            return 'paragraph';
        }

        return 'short_text';
    }
}
