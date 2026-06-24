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

    #[Test]
    public function it_parses_unnumbered_questions_plain_option_blocks_and_likert_rows(): void
    {
        $parsed = (new QuestionnaireParserService)->parse(<<<'TEXT'
QUESTIONNAIRE
Family Planning Survey

SECTION A: SOCIODEMOGRAPHIC CHARACTERISTICS
Please tick one option unless otherwise stated.
Age
18–20 years
21–23 years
Sex
Male
Female

SECTION B: KNOWLEDGE
Which methods do you know or have you heard of?
(You may tick more than one)
Male condom
Female condom
Oral contraceptive pills
For each method below, indicate whether you think it can effectively prevent pregnancy.
a. Male condom
Yes
No
b. Oral contraceptive pills
Yes
No

SECTION E: ATTITUDES
For questions below, please indicate your opinion using the scale below:
1 = Strongly Disagree
2 = Disagree
3 = Neutral
4 = Agree
5 = Strongly Agree
Using family planning is against my religious beliefs.
[ ] 1 [ ] 2 [ ] 3 [ ] 4 [ ] 5
Condoms reduce sexual pleasure.
[ ] 1 [ ] 2 [ ] 3 [ ] 4 [ ] 5
TEXT);

        $demographics = $parsed['sections'][0]['questions'];
        $knowledge = $parsed['sections'][1]['questions'];
        $attitudes = $parsed['sections'][2]['questions'];

        $this->assertSame('Age', $demographics[0]['title']);
        $this->assertSame(['18–20 years', '21–23 years'], $demographics[0]['options']);
        $this->assertSame(['Male', 'Female'], $demographics[1]['options']);
        $this->assertSame('checkboxes', $knowledge[0]['type']);
        $this->assertSame(['Male condom', 'Female condom', 'Oral contraceptive pills'], $knowledge[0]['options']);
        $this->assertSame('Male condom', $knowledge[1]['title']);
        $this->assertSame(['Yes', 'No'], $knowledge[1]['options']);
        $this->assertSame('likert', $attitudes[0]['type']);
        $this->assertSame(['Strongly Disagree', 'Disagree', 'Neutral', 'Agree', 'Strongly Agree'], $attitudes[0]['options']);
        $this->assertCount(2, $attitudes);
    }
}
