<?php

namespace App\Http\Controllers;

use App\Modules\Customer\Services\AccountLinkService;
use App\Modules\Licensing\Models\LicenceApplication;
use App\Modules\Platform\Models\Document;
use App\Modules\Platform\Services\DocumentService;
use App\Modules\Platform\Services\PermissionService;
use App\Modules\Platform\Services\TenantManager;

class DocumentController extends Controller
{
    public function download(Document $document, DocumentService $documents, AccountLinkService $links, PermissionService $permissions, TenantManager $tenants)
    {
        if ($document->classification === 'confidential' || $document->classification === 'restricted') {
            if ($document->documentable_type === LicenceApplication::class) {
                $application = LicenceApplication::findOrFail($document->documentable_id);
                $user = request()->user();
                $isCitizen = $tenants->requireMembership()->member_type === 'citizen';
                if ($isCitizen) {
                    $links->requireAccount($user, $application->customer_account_id);
                } elseif (! collect(['licensing.applications.review', 'licensing.applications.manage', 'licensing.applications.approve', 'licensing.certificates.issue'])->contains(fn (string $permission) => $permissions->allows($user, $permission))) {
                    abort(404);
                }
            } elseif ($document->uploaded_by !== request()->user()?->getKey()) {
                abort(404);
            }
        }

        return $documents->download($document);
    }
}
