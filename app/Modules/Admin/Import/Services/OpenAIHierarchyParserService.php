<?php

namespace App\Modules\Admin\Import\Services;

use Exception;
use OpenAI;

class OpenAIHierarchyParserService
{
    /*
    |--------------------------------------------------------------------------
    | Parse HTML Using AI
    |--------------------------------------------------------------------------
    */

    public function parse(
        string $html
    ): array {

        /*
        |--------------------------------------------------------------------------
        | Prevent Huge Payload Issues
        |--------------------------------------------------------------------------
        */

        $html = trim($html);

        $html = substr(
            $html,
            0,
            120000
        );

        /*
        |--------------------------------------------------------------------------
        | OpenAI Client
        |--------------------------------------------------------------------------
        */

        $client = OpenAI::client(
            env('OPENAI_API_KEY')
        );

        /*
        |--------------------------------------------------------------------------
        | Prompt
        |--------------------------------------------------------------------------
        */

        $prompt = <<<PROMPT

You are a STRICT LMS HTML STRUCTURE EXTRACTION ENGINE.

IMPORTANT:

You are NOT allowed to:
- summarize
- rewrite
- improve
- shorten
- clean
- modify
- optimize
- translate
- reformat
- remove
- merge
- simplify
- interpret content

You MUST preserve:
- exact wording
- exact HTML
- exact tables
- exact lists
- exact paragraphs
- exact bold tags
- exact image tags
- exact formatting
- exact structure
- exact spelling
- exact sequence

Your ONLY job is:

1. Read HTML
2. Detect hierarchy
3. Slice content into:
   - modules
   - chapters
   - topics
   - topic content sections
   - assessments

You are ONLY performing STRUCTURAL SEGMENTATION.

DO NOT CHANGE CONTENT.

DO NOT GENERATE NEW CONTENT.

DO NOT FIX SPELLING.

DO NOT REMOVE DUPLICATES.

DO NOT MODIFY HTML.

DO NOT STRIP TAGS.

DO NOT REARRANGE CONTENT.

DO NOT COMPRESS CONTENT.

KEEP EVERYTHING EXACTLY AS PROVIDED.

--------------------------------------------------
HIERARCHY RULES
--------------------------------------------------

MODULE FORMAT:
Module X:

CHAPTER FORMAT:
Chapter X.X:

TOPIC FORMAT:
Topic X.X.X:

SECTION FORMAT:
X.X.X.H1
X.X.X.H2
X.X.X.H3

Examples:
1.1.1.H1
1.1.1.H2
2.4.3.H5

--------------------------------------------------
SECTION RULES
--------------------------------------------------

When a new Hx heading appears:

Example:

1.1.1.H2 Explanation

Create a new topic content section.

All HTML after this heading belongs to this section UNTIL:
- next Hx heading
- next topic
- next chapter
- next module

--------------------------------------------------
ASSESSMENT RULES
--------------------------------------------------

Detect MCQs.

Extract:
- question
- options
- correct_answer

DO NOT MODIFY QUESTIONS.

DO NOT MODIFY OPTIONS.

--------------------------------------------------
VERY IMPORTANT
--------------------------------------------------

PRESERVE RAW HTML EXACTLY.

If HTML contains:
- tables
- ul
- ol
- li
- strong
- b
- span
- div
- images
- inline styles

KEEP THEM EXACTLY.

--------------------------------------------------
RETURN FORMAT
--------------------------------------------------

Return ONLY RFC8259 VALID JSON.

NO markdown.

NO explanation.

NO comments.

NO extra text.

NO code block.

All quotes inside HTML attributes MUST be escaped properly.

DO NOT truncate JSON.

--------------------------------------------------
JSON STRUCTURE
--------------------------------------------------

{
  "modules": [
    {
      "title": "",
      "chapters": [
        {
          "title": "",
          "topics": [
            {
              "title": "",
              "contents": [
                {
                  "type": "",
                  "title": "",
                  "html": ""
                }
              ],
              "assessments": [
                {
                  "question": "",
                  "options": [],
                  "correct_answer": ""
                }
              ]
            }
          ]
        }
      ]
    }
  ]
}

--------------------------------------------------
HTML INPUT
--------------------------------------------------

{$html}

PROMPT;

        /*
        |--------------------------------------------------------------------------
        | OpenAI Request
        |--------------------------------------------------------------------------
        */

        $response = $client->chat()->create([

            'model' => env(
                'OPENAI_MODEL',
                'gpt-5.5'
            ),

            'messages' => [

                [
                    'role' => 'system',
                    'content' => 'You ONLY return valid raw JSON.'
                ],

                [
                    'role' => 'user',
                    'content' => $prompt
                ],
            ],

            'max_completion_tokens' => 16000,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Extract Response
        |--------------------------------------------------------------------------
        */

        $content = $response
            ->choices[0]
            ->message
            ->content ?? '';

        /*
        |--------------------------------------------------------------------------
        | Cleanup
        |--------------------------------------------------------------------------
        */

        $content = trim($content);

        $content = preg_replace(
            '/^```json/i',
            '',
            $content
        );

        $content = preg_replace(
            '/^```/i',
            '',
            $content
        );

        $content = preg_replace(
            '/```$/',
            '',
            $content
        );

        $content = trim($content);

        /*
        |--------------------------------------------------------------------------
        | Extract JSON Only
        |--------------------------------------------------------------------------
        */

        $start = strpos(
            $content,
            '{'
        );

        $end = strrpos(
            $content,
            '}'
        );

        if (
            $start !== false
            && $end !== false
        ) {

            $content = substr(
                $content,
                $start,
                $end - $start + 1
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Fix UTF8
        |--------------------------------------------------------------------------
        */

        $content = mb_convert_encoding(
            $content,
            'UTF-8',
            'UTF-8'
        );

        /*
        |--------------------------------------------------------------------------
        | Remove Invalid Control Characters
        |--------------------------------------------------------------------------
        */

        $content = preg_replace(
            '/[\x00-\x1F\x7F]/u',
            '',
            $content
        );

        /*
        |--------------------------------------------------------------------------
        | Decode JSON
        |--------------------------------------------------------------------------
        */

        $data = json_decode(
            $content,
            true
        );

        /*
        |--------------------------------------------------------------------------
        | JSON Validation
        |--------------------------------------------------------------------------
        */

        if (
            json_last_error() !== JSON_ERROR_NONE
        ) {

            logger()->error(
                'AI RAW RESPONSE',
                [
                    'content' => $content
                ]
            );

            throw new Exception(
                'Invalid AI JSON response: '
                . json_last_error_msg()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Structure Validation
        |--------------------------------------------------------------------------
        */

        if (
            !isset($data['modules'])
            || !is_array($data['modules'])
        ) {

            throw new Exception(
                'AI response missing modules array.'
            );
        }

        return $data;
    }
}
