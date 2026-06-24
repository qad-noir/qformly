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

        if (! $this->containsNumberedQuestions($lines)) {
            return $this->parseUnnumberedQuestionnaire($lines);
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
                [$questionTitle, $inlineOptions] = $this->extractInlineOptions(trim($questionMatch[2]));
                $currentQuestion = [
                    'number' => $questionMatch[1],
                    'title' => $questionTitle,
                    'type' => 'short_text',
                    'required' => true,
                    'options' => $inlineOptions,
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

    private function containsNumberedQuestions(array $lines): bool
    {
        foreach ($lines as $line) {
            if ($this->questionMatch($line, $match)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, string> $lines */
    private function parseUnnumberedQuestionnaire(array $lines): array
    {
        $firstSection = $this->firstSectionIndex($lines);
        $preamble = array_slice($lines, 0, $firstSection ?? count($lines));
        $title = $preamble[0] ?? 'Untitled Questionnaire';
        $descriptionLines = array_slice($preamble, 1);
        $sections = [];
        $sectionTitle = null;
        $sectionLines = [];

        foreach ($lines as $line) {
            if ($this->isSectionHeading($line)) {
                if ($sectionTitle !== null) {
                    $sections[] = $this->parseUnnumberedSection($sectionTitle, $sectionLines);
                }

                $sectionTitle = $line;
                $sectionLines = [];
                continue;
            }

            if ($sectionTitle !== null) {
                $sectionLines[] = $line;
            }
        }

        if ($sectionTitle !== null) {
            $sections[] = $this->parseUnnumberedSection($sectionTitle, $sectionLines);
        }

        if ($sections === []) {
            $sections[] = $this->parseUnnumberedSection('Questions', array_slice($lines, 1));
        }

        return [
            'title' => $title,
            'description' => $descriptionLines === [] ? null : implode("\n", $descriptionLines),
            'sections' => $sections,
        ];
    }

    /** @param array<int, string> $lines @return array<string, mixed> */
    private function parseUnnumberedSection(string $title, array $lines): array
    {
        $section = ['title' => $title, 'help_text' => null, 'questions' => []];
        $currentQuestion = null;
        $matrixInstruction = null;
        $likertOptions = $this->likertOptions($lines);

        foreach ($lines as $index => $line) {
            $nextLine = $lines[$index + 1] ?? null;

            if ($this->isLikertStatement($line, $nextLine)) {
                $this->finishQuestion($section, $currentQuestion);
                $currentQuestion = [
                    'number' => null,
                    'title' => $line,
                    'type' => 'likert',
                    'forced_type' => 'likert',
                    'required' => true,
                    'help_text' => null,
                    'options' => $likertOptions ?: ['Strongly Disagree', 'Disagree', 'Neutral', 'Agree', 'Strongly Agree'],
                ];
                $this->finishQuestion($section, $currentQuestion);
                continue;
            }

            if ($this->isLikertScaleDefinition($line)) {
                $this->appendSectionHelp($section, $line);
                continue;
            }

            if ($this->isLikertResponseLine($line)) {
                continue;
            }

            if ($this->isMatrixInstruction($line)) {
                $this->finishQuestion($section, $currentQuestion);
                $matrixInstruction = $line;
                continue;
            }

            if ($matrixInstruction !== null && $this->matrixRowMatch($line, $matrixRow)) {
                $this->finishQuestion($section, $currentQuestion);
                $currentQuestion = [
                    'number' => null,
                    'title' => $matrixRow[1],
                    'type' => 'multiple_choice',
                    'required' => true,
                    'help_text' => $matrixInstruction,
                    'options' => [],
                ];
                continue;
            }

            if ($this->isUnnumberedQuestionPrompt($line)) {
                $this->finishQuestion($section, $currentQuestion);
                $matrixInstruction = null;
                $currentQuestion = [
                    'number' => null,
                    'title' => $line,
                    'type' => 'short_text',
                    'required' => true,
                    'help_text' => null,
                    'options' => [],
                ];
                continue;
            }

            if ($currentQuestion !== null) {
                if ($this->isQuestionInstruction($line)) {
                    $this->appendQuestionHelp($currentQuestion, $line);
                    continue;
                }

                [$option, $condition] = $this->optionAndCondition($line);
                if ($option !== '') {
                    $currentQuestion['options'][] = $option;
                }
                if ($condition !== null) {
                    $this->appendQuestionHelp($currentQuestion, $condition);
                }
                continue;
            }

            $this->appendSectionHelp($section, $line);
        }

        $this->finishQuestion($section, $currentQuestion);

        return $section;
    }

    private function isUnnumberedQuestionPrompt(string $line): bool
    {
        if (str_ends_with($line, '?')) {
            return true;
        }

        return in_array(mb_strtolower(trim($line)), [
            'age', 'sex', 'marital status', 'religion', 'faculty', 'level of study',
        ], true);
    }

    private function isQuestionInstruction(string $line): bool
    {
        return (bool) preg_match('/^\((?:you may|tick all|choose one|select all|choose all|optional)/iu', $line);
    }

    private function isMatrixInstruction(string $line): bool
    {
        $lower = mb_strtolower($line);

        return str_starts_with($lower, 'for each ')
            && (str_contains($lower, 'indicate') || str_contains($lower, 'whether'));
    }

    private function matrixRowMatch(string $line, ?array &$match): bool
    {
        return preg_match('/^[a-z]\s*[.)]\s+(.+)$/iu', $line, $match) === 1;
    }

    private function isLikertStatement(string $line, ?string $nextLine): bool
    {
        return $nextLine !== null
            && ! $this->isLikertScaleDefinition($line)
            && $this->isLikertResponseLine($nextLine);
    }

    private function isLikertResponseLine(string $line): bool
    {
        return preg_match_all('/\[\s*\]\s*\d+/u', $line) >= 3;
    }

    private function isLikertScaleDefinition(string $line): bool
    {
        return preg_match('/^\d+\s*=\s*.+$/u', $line) === 1;
    }

    /** @param array<int, string> $lines @return array<int, string> */
    private function likertOptions(array $lines): array
    {
        $options = [];

        foreach ($lines as $line) {
            if (preg_match('/^(\d+)\s*=\s*(.+)$/u', $line, $match) === 1) {
                $options[(int) $match[1]] = trim($match[2]);
            }
        }

        ksort($options);

        return array_values($options);
    }

    /** @return array{0: string, 1: ?string} */
    private function optionAndCondition(string $line): array
    {
        $parts = preg_split('/\s*→\s*/u', $line, 2) ?: [$line];

        return [trim($parts[0]), isset($parts[1]) ? trim($parts[1]) : null];
    }

    /** @param array<string, mixed> $section */
    private function appendSectionHelp(array &$section, string $line): void
    {
        $section['help_text'] = trim(($section['help_text'] ? $section['help_text'].' ' : '').$line);
    }

    /** @param array<string, mixed> $question */
    private function appendQuestionHelp(array &$question, string $line): void
    {
        $question['help_text'] = trim(($question['help_text'] ? $question['help_text'].' ' : '').$line);
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
        return preg_match('/^(?:[☐☑☒□]\s*)*(?:[a-z]|[ivxlcdm]+)\s*[.)]\s+(.+)$/iu', $line, $match) === 1;
    }

    private function finishQuestion(?array &$section, ?array &$question): void
    {
        if ($section === null || $question === null) {
            return;
        }

        [$question['title'], $inlineOptions] = $this->extractInlineOptions($question['title']);
        if ($inlineOptions !== []) {
            $question['options'] = array_values(array_merge($question['options'], $inlineOptions));
        }

        $context = implode(' ', [
            $section['title'] ?? '',
            $section['help_text'] ?? '',
            $question['title'],
            $question['help_text'] ?? '',
            implode(' ', $question['options']),
        ]);

        $question['type'] = $question['forced_type'] ?? $this->detectType($question['title'], $question['options'], $context);
        unset($question['forced_type']);
        if ($question['type'] === 'likert' && $question['options'] === []) {
            $question['options'] = ['Strongly Agree', 'Agree', 'Neutral', 'Disagree', 'Strongly Disagree'];
        }
        $section['questions'][] = $question;
        $question = null;
    }

    /** @return array{0: string, 1: array<int, string>} */
    private function extractInlineOptions(string $text): array
    {
        $matches = [];
        preg_match_all('/(?:[☐☑☒□]\s*)+\s*([a-z])\s*[.)]\s*/iu', $text, $matches, PREG_OFFSET_CAPTURE);

        if (($matches[0] ?? []) === []) {
            return [trim($text), []];
        }

        $questionTitle = trim(substr($text, 0, $matches[0][0][1]));
        $questionTitle = preg_replace('/[☐☑☒□\s]+$/u', '', $questionTitle) ?? $questionTitle;
        $options = [];
        $textLength = strlen($text);

        foreach ($matches[0] as $index => $match) {
            $start = $match[1] + strlen($match[0]);
            $end = $matches[0][$index + 1][1] ?? $textLength;
            $option = trim(substr($text, $start, $end - $start));
            $option = preg_replace('/\s*[☐☑☒□]\s*$/u', '', $option) ?? $option;

            if ($option !== '') {
                $options[] = $option;
            }
        }

        return [trim($questionTitle), $options];
    }

    private function detectType(string $title, array $options, string $context): string
    {
        $lower = mb_strtolower($context);

        if (str_contains($lower, 'likert') ||
            preg_match('/strongly agree.*agree.*(?:neutral|neither).*disagree/iu', $lower)) {
            return 'likert';
        }

        if (preg_match('/tick all that apply|tick more than one|select all|choose all|more than one/iu', $lower)) {
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
