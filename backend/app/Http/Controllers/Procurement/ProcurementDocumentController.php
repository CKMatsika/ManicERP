<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Models\Document;
use App\Modules\Procurement\Services\ProcurementDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProcurementDocumentController extends Controller
{
    public function upload(Request $request, string $type, string $record, ProcurementDocumentService $documents): RedirectResponse
    {
        $data = $request->validate([
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,csv,txt,xlsx,docx',
            'replacement_reason' => 'nullable|string|max:2000',
        ]);
        try {
            $document = $documents->upload($type, $record, $data['file'], $request->user(), $data['replacement_reason'] ?? null);
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', "Document version saved ({$document->scan_status}; malware scanning is not configured). Prior versions are retained.");
    }

    public function download(Request $request, Document $document, ProcurementDocumentService $documents): StreamedResponse
    {
        return $documents->download($document, $request->user());
    }

    public function preview(Request $request, Document $document, ProcurementDocumentService $documents): StreamedResponse
    {
        return $documents->preview($document, $request->user());
    }

    public function retention(Request $request, string $record, ProcurementDocumentService $documents): RedirectResponse
    {
        $data = $request->validate(['retained_until' => 'nullable|date', 'reason' => 'required|string|max:2000']);
        try {
            $documents->setRetention($record, $data['retained_until'] ?? null, $data['reason'], $request->user());
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Document retention metadata updated; files are retained and not automatically purged.');
    }
}
