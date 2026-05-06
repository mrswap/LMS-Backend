<?php

namespace App\Modules\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Program;
use App\Models\Level;
use App\Models\Module;
use App\Models\Chapter;
use App\Models\Topic;
use App\Models\TopicContent;

class CommonPublishStatusController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | MODEL MAP
    |--------------------------------------------------------------------------
    */

    protected array $models = [

        'program' => Program::class,
        'level' => Level::class,
        'module' => Module::class,
        'chapter' => Chapter::class,
        'topic' => Topic::class,
        'topic_content' => TopicContent::class,
    ];

    /*
    |--------------------------------------------------------------------------
    | UPDATE PUBLISH STATUS
    |--------------------------------------------------------------------------
    */

    public function update(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([

            'type' => 'required|string|in:program,level,module,chapter,topic,topic_content',

            'id' => 'required|integer',

            'publish_status' => 'required|in:draft,published,unpublished',
        ]);

        /*
        |--------------------------------------------------------------------------
        | GET MODEL CLASS
        |--------------------------------------------------------------------------
        */

        $modelClass = $this->models[$validated['type']];

        /*
        |--------------------------------------------------------------------------
        | FIND RECORD
        |--------------------------------------------------------------------------
        */

        $item = $modelClass::findOrFail($validated['id']);

        /*
        |--------------------------------------------------------------------------
        | UPDATE STATUS
        |--------------------------------------------------------------------------
        */

        $item->update([

            'publish_status' => $validated['publish_status']
        ]);

        /*
        |--------------------------------------------------------------------------
        | REFRESH MODEL
        |--------------------------------------------------------------------------
        */

        $item->refresh();

        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return response()->json([

            'success' => true,

            'message' => 'Publish status updated successfully.',

            'data' => [

                'type' => $validated['type'],
                'id' => $item->id,

                'status' => $item->status,

                'publish_status' => $item->publish_status,
            ]
        ]);
    }
}
