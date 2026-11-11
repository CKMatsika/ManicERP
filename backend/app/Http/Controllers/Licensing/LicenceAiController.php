<?php

namespace App\Http\Controllers\Licensing;

use App\Http\Controllers\Controller;
use App\Modules\Licensing\Models\LicenceApplication;
use App\Modules\Licensing\Services\LicenceAiAssistantService;
use Illuminate\Http\Request;

class LicenceAiController extends Controller
{
    public function extract(Request $request, LicenceApplication $application, string $document, LicenceAiAssistantService $assistant)
    {
        $link = $application->documents()->findOrFail($document);
        $assistant->extract($request->user(), $application, $link);

        return back()->with('success', 'Extraction saved as a review-only draft.');
    }

    public function reviewExtraction(Request $request, LicenceApplication $application, string $extraction, LicenceAiAssistantService $assistant)
    {
        $item = $application->extractions()->findOrFail($extraction);
        $data = $request->validate(['decision' => ['required', 'in:accepted,corrected,rejected'], 'fields' => ['sometimes', 'array'], 'note' => ['required', 'string', 'max:2000']]);
        $assistant->reviewExtraction($request->user(), $item, $data['decision'], $data['fields'] ?? [], $data['note']);

        return back()->with('success', 'Human extraction review recorded. Authoritative submitted fields are unchanged.');
    }

    public function draft(Request $request, LicenceApplication $application, LicenceAiAssistantService $assistant)
    {
        $data = $request->validate(['type' => ['required', 'in:information_request,staff_correspondence']]);
        $assistant->draft($request->user(), $application, $data['type']);

        return back()->with('success', 'Correspondence created as a draft for human review.');
    }

    public function reviewDraft(Request $request, LicenceApplication $application, string $draft, LicenceAiAssistantService $assistant)
    {
        $item = $application->assistantDrafts()->findOrFail($draft);
        $data = $request->validate(['decision' => ['required', 'in:accepted,rejected'], 'note' => ['required', 'string', 'max:2000']]);
        $assistant->reviewDraft($request->user(), $item, $data['decision'], $data['note']);

        return back()->with('success', 'Draft review recorded. Accepting does not send the message.');
    }
}
