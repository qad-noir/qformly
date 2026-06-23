<?php

namespace Tests\Unit;

use App\Services\QuestionnaireParserService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class QuestionnaireParserServiceTest extends TestCase
{
    #[Test]
    public function it_parses_sections_questions_options_and_question_types(): void
    {
        $parsed = (new QuestionnaireParserService)->parse(<<<'TEXT'
Community Needs Survey
Tell us how to improve local services.

SECTION A: DEMOGRAPHICS
1. What is your gender?
a. Female
b. Male
2. Select all services you use.
a. Clinic
b. Library

SECTION B: OPINIONS
Please use this Likert scale: Strongly Agree, Agree, Neutral, Disagree, Strongly Disagree.
3. The service meets my needs.
TEXT);

        $this->assertSame('Community Needs Survey', $parsed['title']);
        $this->assertCount(2, $parsed['sections']);
        $this->assertSame('multiple_choice', $parsed['sections'][0]['questions'][0]['type']);
        $this->assertSame(['Female', 'Male'], $parsed['sections'][0]['questions'][0]['options']);
        $this->assertSame('checkboxes', $parsed['sections'][0]['questions'][1]['type']);
        $this->assertSame('likert', $parsed['sections'][1]['questions'][0]['type']);
        $this->assertSame('3', $parsed['sections'][1]['questions'][0]['number']);
    }

    #[Test]
    public function it_extracts_checkbox_options_that_are_inline_in_a_docx_paragraph(): void
    {
        $parsed = (new QuestionnaireParserService)->parse(<<<'TEXT'
Breakfast Habits Survey
SECTION B: BREAKFAST
13. What are your main reasons for skipping breakfast? (Tick all that apply) ☐ a. Lack of time due to morning lectures ☐ b. No appetite in the morning ☐ c. Trying to lose or manage weight
TEXT);

        $question = $parsed['sections'][0]['questions'][0];

        $this->assertSame('checkboxes', $question['type']);
        $this->assertSame('What are your main reasons for skipping breakfast? (Tick all that apply)', $question['title']);
        $this->assertSame([
            'Lack of time due to morning lectures',
            'No appetite in the morning',
            'Trying to lose or manage weight',
        ], $question['options']);
    }
}
