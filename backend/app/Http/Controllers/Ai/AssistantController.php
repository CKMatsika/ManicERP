<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Modules\Ai\Services\PolicyAssistantService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AssistantController extends Controller
{
    public function __invoke(Request $request, PolicyAssistantService $assistant)
    {
        $question = trim((string) $request->query('q', ''));
        $result = null;

        if ($question !== '') {
            $result = $assistant->ask(
                $question,
                $request->user(),
                (int) config('manicerp.ai.retrieval_limit', 3),
            );
        }

        return Inertia::render('Ai/Assistant', [
            'question' => $question,
            'result' => $result,
            'enabled' => $assistant->enabled(),
            'budgetRemainingMinor' => $assistant->budgetRemainingMinor(),
            'disclaimer' => config('manicerp.ai.disclaimer'),
        ]);
    }
}
