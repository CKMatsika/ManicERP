<?php

namespace App\Http\Controllers;

use App\Modules\Platform\Models\Document;
use App\Modules\Platform\Services\DocumentService;

class DocumentController extends Controller
{
    public function download(Document $document, DocumentService $documents)
    {
        return $documents->download($document);
    }
}
