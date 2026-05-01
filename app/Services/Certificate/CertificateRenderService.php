<?php

namespace App\Services\Certificate;

class CertificateRenderService
{
    public static function render($template, $meta, $certificate)
    {
        $replace = [

            // USER
            '{{name}}' => $meta['user']['name'] ?? '',
            '{{email}}' => $meta['user']['email'] ?? '',
            '{{employee_id}}' => $meta['user']['employee_id'] ?? '',

            // CONTEXT
            '{{type}}' => $meta['context']['type'] ?? '',
            '{{title}}' => $meta['context']['title'] ?? '',

            // RESULT
            '{{score}}' => $meta['result']['score'] ?? '',
            '{{percentage}}' => $meta['result']['percentage'] ?? '',
            '{{status}}' => $meta['result']['status'] ?? '',

            // QUESTIONS
            '{{total_questions}}' => $meta['questions']['total'] ?? '',
            '{{attempted}}' => $meta['questions']['attempted'] ?? '',
            '{{correct}}' => $meta['questions']['correct'] ?? '',
            '{{incorrect}}' => $meta['questions']['incorrect'] ?? '',
            '{{skipped}}' => $meta['questions']['skipped'] ?? '',

            // MARKS
            '{{total_marks}}' => $meta['marks']['total_marks'] ?? '',
            '{{obtained_marks}}' => $meta['marks']['obtained_marks'] ?? '',
            '{{passing_marks}}' => $meta['marks']['passing_marks'] ?? '',

            // TIME
            '{{date}}' => optional($certificate->issued_at)->format('d M Y'),
            '{{time_taken}}' => $meta['time']['time_taken_seconds'] ?? '',

            // CERTIFICATE
            '{{certificate_id}}' => $certificate->certificate_id,
        ];

        return str_replace(array_keys($replace), array_values($replace), $template);
    }
}
