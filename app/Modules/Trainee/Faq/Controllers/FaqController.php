<?php

namespace App\Modules\Trainee\FAQ\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Faq;
use App\Models\Level;
use App\Models\Module;
use App\Models\Chapter;
use App\Models\Topic;
use App\Services\AuditService;


class FaqController extends Controller
{
    private function resolveLanguage(Request $request)
    {
        return $request->query('lang')
            ?? $request->header('Accept-Language')
            ?? 'en';
    }

    private function resolveModel($type)
    {
        return [
            'level'   => Level::class,
            'module'  => Module::class,
            'chapter' => Chapter::class,
            'topic'   => Topic::class,
        ][$type] ?? null;
    }

    /*
    |-------------------------------------------------------------
    | GET FAQs BY TYPE + ID (Trainee)
    |-------------------------------------------------------------
    */
    public function single(Request $request, $type, $id)
    {
        AuditService::log('faq_viewed', "User viewed FAQs for $type ID: $id");

        $lang = $this->resolveLanguage($request);

        $modelClass = $this->resolveModel($type);

        if (!$modelClass) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid type'
            ], 422);
        }

        // Validate existence
        $model = $modelClass::find($id);
        if (!$model) {
            return response()->json([
                'success' => false,
                'message' => ucfirst($type) . ' not found'
            ], 404);
        }

        // Fetch FAQs
        $faqs = Faq::with('translations')
            ->where('faqable_type', $modelClass)
            ->where('faqable_id', $id)
            ->where('status', true)
            ->get()
            ->map(function ($faq) use ($lang) {

                $translation = $faq->translations
                    ->where('language_code', $lang)
                    ->first()
                    ?? $faq->translations->first(); // fallback

                if (!$translation) return null;

                return [
                    'id'       => $faq->id,
                    'question' => $translation->question,
                    'answer'   => $translation->answer,
                    'image'    => $faq->image,
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'type' => $type,
                'id'   => $id,
                'faqs' => $faqs
            ]
        ]);
    }
}