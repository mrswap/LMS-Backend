<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\Level;
use App\Models\Module;
use App\Models\Topic;

class HierarchyVisibilityService
{
    /*
    |--------------------------------------------------------------------------
    | ACTIVE TOPICS
    |--------------------------------------------------------------------------
    */

    public function activeTopics()
    {
        return Topic::query()
            ->where('status', true)
            ->whereNull('deleted_at')
            ->whereHas('chapter', function ($q) {

                $q->where('status', true)
                    ->whereNull('deleted_at');
            })
            ->whereHas('module', function ($q) {

                $q->where('status', true)
                    ->whereNull('deleted_at');
            })
            ->whereHas('level', function ($q) {

                $q->where('status', true)
                    ->whereNull('deleted_at');
            });
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVE CHAPTERS
    |--------------------------------------------------------------------------
    */

    public function activeChapters()
    {
        return Chapter::query()
            ->where('status', true)
            ->whereNull('deleted_at')
            ->whereHas('module', function ($q) {

                $q->where('status', true)
                    ->whereNull('deleted_at');
            })
            ->whereHas('level', function ($q) {

                $q->where('status', true)
                    ->whereNull('deleted_at');
            });
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVE MODULES
    |--------------------------------------------------------------------------
    */

    public function activeModules()
    {
        return Module::query()
            ->where('status', true)
            ->whereNull('deleted_at')
            ->whereHas('level', function ($q) {

                $q->where('status', true)
                    ->whereNull('deleted_at');
            });
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVE LEVELS
    |--------------------------------------------------------------------------
    */

    public function activeLevels()
    {
        return Level::query()
            ->where('status', true)
            ->whereNull('deleted_at');
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVE TOPIC IDS
    |--------------------------------------------------------------------------
    */

    public function activeTopicIds(): array
    {
        return $this->activeTopics()
            ->pluck('id')
            ->toArray();
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVE CHAPTER IDS
    |--------------------------------------------------------------------------
    */

    public function activeChapterIds(): array
    {
        return $this->activeChapters()
            ->pluck('id')
            ->toArray();
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVE MODULE IDS
    |--------------------------------------------------------------------------
    */

    public function activeModuleIds(): array
    {
        return $this->activeModules()
            ->pluck('id')
            ->toArray();
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVE LEVEL IDS
    |--------------------------------------------------------------------------
    */

    public function activeLevelIds(): array
    {
        return $this->activeLevels()
            ->pluck('id')
            ->toArray();
    }
}
