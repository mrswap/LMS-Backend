<?php

namespace App\Modules\Admin\Import\Services;

class HtmlCleanerService
{
    public function clean(
        string $html
    ): string {

        return trim($html);
    }
}
