<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ActualLMSSeeder extends Seeder
{
    public function run()
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | DISABLE FOREIGN KEYS
            |--------------------------------------------------------------------------
            */

            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            /*
            |--------------------------------------------------------------------------
            | TABLES
            |--------------------------------------------------------------------------
            */

            $tables = [

                'assessment_options',
                'assessment_questions',
                'assessments',

                'topic_contents',
                'topics',
                'chapters',
                'modules',
                'levels',
                'programs',
            ];

            /*
            |--------------------------------------------------------------------------
            | TRUNCATE TABLES
            |--------------------------------------------------------------------------
            */

            foreach ($tables as $table) {

                if (DB::getSchemaBuilder()->hasTable($table)) {

                    DB::table($table)->truncate();

                    /*
                    |--------------------------------------------------------------------------
                    | RESET AUTO INCREMENT
                    |--------------------------------------------------------------------------
                    */

                    DB::statement("ALTER TABLE {$table} AUTO_INCREMENT = 1");

                    $this->command->info("Truncated: {$table}");
                }
            }

            /*
            |--------------------------------------------------------------------------
            | IMPORT ORDER
            |--------------------------------------------------------------------------
            */

            $importOrder = [

                'programs',

                'levels',

                'modules',

                'chapters',

                'topics',

                'topic_contents',

                'assessments',

                'assessment_questions',

                'assessment_options',
            ];

            /*
            |--------------------------------------------------------------------------
            | IMPORT TABLES
            |--------------------------------------------------------------------------
            */

            foreach ($importOrder as $table) {

                $this->importTable($table);
            }

            /*
            |--------------------------------------------------------------------------
            | ENABLE FOREIGN KEYS
            |--------------------------------------------------------------------------
            */

            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            $this->command->info('');
            $this->command->info('========================================');
            $this->command->info('Actual LMS Imported Successfully');
            $this->command->info('========================================');
        } catch (\Throwable $e) {

            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            $this->command->error('');
            $this->command->error('========================================');
            $this->command->error('IMPORT FAILED');
            $this->command->error('========================================');

            $this->command->error($e->getMessage());

            $this->command->error($e->getFile());

            $this->command->error('Line: ' . $e->getLine());

            throw $e;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | IMPORT TABLE
    |--------------------------------------------------------------------------
    */

    protected function importTable($table)
    {
        $path = database_path("seeders/data/{$table}.json");

        /*
        |--------------------------------------------------------------------------
        | FILE EXISTS
        |--------------------------------------------------------------------------
        */

        if (!file_exists($path)) {

            $this->command->warn("Skipped Missing File: {$table}.json");

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | READ JSON
        |--------------------------------------------------------------------------
        */

        $content = file_get_contents($path);

        $json = json_decode($content, true);

        if (!$json) {

            $this->command->warn("Invalid JSON: {$table}.json");

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | EXTRACT DATA
        |--------------------------------------------------------------------------
        */

        $rows = [];

        /*
        |--------------------------------------------------------------------------
        | phpMyAdmin RAW EXPORT
        |--------------------------------------------------------------------------
        */

        if (
            isset($json[0]['type'])
        ) {

            foreach ($json as $item) {

                if (
                    isset($item['type']) &&
                    $item['type'] === 'table' &&
                    isset($item['data'])
                ) {

                    $rows = $item['data'];

                    break;
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | CLEAN JSON ARRAY
        |--------------------------------------------------------------------------
        */ else {

            $rows = $json;
        }

        /*
        |--------------------------------------------------------------------------
        | NO DATA
        |--------------------------------------------------------------------------
        */

        if (empty($rows)) {

            $this->command->warn("No Data Found: {$table}");

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | INSERT CHUNK
        |--------------------------------------------------------------------------
        */

        $chunks = array_chunk($rows, 500);

        foreach ($chunks as $chunk) {

            DB::table($table)->insert($chunk);
        }

        $this->command->info("Imported: {$table} (" . count($rows) . " rows)");
    }
}
