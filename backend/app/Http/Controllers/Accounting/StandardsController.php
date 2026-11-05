<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\AccountingStandard;
use App\Modules\Accounting\Services\AccountingStandardsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StandardsController extends Controller
{
    public function index(Request $request, AccountingStandardsService $service)
    {
        if (AccountingStandard::count() === 0) {
            $service->seedDefaults();
        }

        $standards = AccountingStandard::orderBy('code')->get()->map(fn (AccountingStandard $standard) => [
            'code' => $standard->code,
            'title' => $standard->title,
            'edition' => $standard->edition,
            'effective_from' => $standard->international_effective_from?->toDateString(),
            'jurisdiction' => $standard->jurisdiction,
            'applicability' => $standard->applicability,
            'transition_method' => $standard->transition_method,
            'verification_status' => $standard->verification_status,
            'approved' => $standard->approved_at !== null,
            'notes' => $standard->notes,
        ]);

        return Inertia::render('Accounting/Standards', [
            'standards' => $standards,
            'summary' => [
                'total' => $standards->count(),
                'verified' => $standards->where('verification_status', 'verified')->count(),
                'unverified' => $standards->where('verification_status', 'unverified')->count(),
            ],
        ]);
    }
}
