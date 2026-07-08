<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Topic Pagination
    |--------------------------------------------------------------------------
    */

    'topic_pagination' => [

        /*
         | Approximate readable words per generated page.
         | Can be adjusted after testing different documents.
         */
        'target_words_per_page' => 350,

        /*
         | Minimum pages to generate for every topic.
         */
        'minimum_pages' => 5,

        /*
         | Footer appended to every page except the last.
         */
        'page_footer' => '<hr><p><strong>Click Next Page (PTO) →</strong></p><hr>',
    ],

];