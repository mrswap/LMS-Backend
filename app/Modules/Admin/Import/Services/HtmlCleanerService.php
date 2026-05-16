<?php

namespace App\Modules\Admin\Import\Services;

class HtmlCleanerService
{
    public function clean(
        string $html
    ): string {

        $html = str_replace(
            [
                '&nbsp;',
                "\r",
            ],
            [
                ' ',
                '',
            ],
            $html
        );

        return trim($html);
    }
}
